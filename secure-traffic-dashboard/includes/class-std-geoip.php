<?php
/**
 * GeoIP lookups with a pluggable provider abstraction and aggressive caching.
 *
 * Lookups are cached for 30 days in a transient keyed by IP, so a busy site
 * makes at most one external call per unique address per month. The default
 * provider is the free ip-api.com endpoint (no key, rate limited). An optional
 * MaxMind GeoLite2 web-service provider can be selected by supplying an API key
 * in the settings. Developers can register additional providers via the
 * `std_geoip_providers` filter.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Geolocation service.
 */
class STD_GeoIP {

	/**
	 * Transient key prefix for cached lookups.
	 */
	const CACHE_PREFIX = 'std_geo_';

	/**
	 * Cache lifetime for a successful lookup.
	 */
	const CACHE_TTL = MONTH_IN_SECONDS;

	/**
	 * Resolve an IP to a country/city.
	 *
	 * Returns an array with `country` (ISO-2) and `city`. Private/reserved and
	 * invalid addresses, or a disabled GeoIP setting, yield empty values
	 * without any network call.
	 *
	 * @param string $ip IP address.
	 * @return array{country:string,city:string}
	 */
	public static function lookup( $ip ) {
		$empty = array(
			'country' => '',
			'city'    => '',
		);

		$ip = STD_Helpers::validate_ip( $ip );
		if ( '' === $ip ) {
			return $empty;
		}

		if ( ! STD_Settings::get( 'geoip_enabled' ) ) {
			return $empty;
		}

		// Never geolocate private / reserved ranges.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return $empty;
		}

		// Serve from cache when possible.
		$cache_key = self::CACHE_PREFIX . md5( $ip );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return wp_parse_args( $cached, $empty );
		}

		$provider = STD_Settings::get( 'geoip_provider', 'ip-api' );
		$result   = self::query_provider( $provider, $ip );

		// Cache even partial/empty results to avoid hammering the API on repeat
		// failures (shorter TTL for misses).
		$ttl = ( '' !== $result['country'] ) ? self::CACHE_TTL : HOUR_IN_SECONDS;
		set_transient( $cache_key, $result, $ttl );

		return $result;
	}

	/**
	 * Backfill country/city for recently-logged rows that have no geo yet.
	 *
	 * Called from the hourly aggregation cron so visitor-facing requests never
	 * block on an external lookup. Processes a bounded batch of distinct IPs to
	 * keep the cron run short; lookups are cached so repeats are free.
	 *
	 * @param int $max_ips Maximum distinct IPs to resolve per run.
	 * @return int Number of rows updated.
	 */
	public static function backfill_recent( $max_ips = 50 ) {
		global $wpdb;

		if ( ! STD_Settings::get( 'geoip_enabled' ) ) {
			return 0;
		}

		$traffic = STD_Helpers::table( 'traffic' );
		$logins  = STD_Helpers::table( 'logins' );
		$max_ips = min( 200, max( 1, absint( $max_ips ) ) );

		// Collect distinct, recent IPs still missing a country from either table.
		$ips = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ip FROM (
					SELECT ip FROM {$traffic} WHERE country = '' AND ip <> ''
					UNION
					SELECT ip FROM {$logins} WHERE country = '' AND ip <> ''
				) t GROUP BY ip LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$max_ips
			)
		);

		$updated = 0;
		foreach ( (array) $ips as $ip ) {
			$geo = self::lookup( $ip );
			if ( '' === $geo['country'] ) {
				continue; // Private/unknown IP: skip (leave blank).
			}

			$updated += (int) $wpdb->update(
				$traffic,
				array(
					'country' => $geo['country'],
					'city'    => $geo['city'],
				),
				array(
					'ip'      => $ip,
					'country' => '',
				),
				array( '%s', '%s' ),
				array( '%s', '%s' )
			);

			$wpdb->update(
				$logins,
				array(
					'country' => $geo['country'],
					'city'    => $geo['city'],
				),
				array(
					'ip'      => $ip,
					'country' => '',
				),
				array( '%s', '%s' ),
				array( '%s', '%s' )
			);
		}

		return $updated;
	}

	/**
	 * Dispatch to the selected provider.
	 *
	 * @param string $provider Provider slug.
	 * @param string $ip       IP address.
	 * @return array{country:string,city:string}
	 */
	private static function query_provider( $provider, $ip ) {
		/**
		 * Filter the map of available GeoIP providers.
		 *
		 * Each entry maps a slug to a callable that accepts an IP string and
		 * returns array( 'country' => 'XX', 'city' => '...' ).
		 *
		 * @param array $providers Provider slug => callable.
		 */
		$providers = apply_filters(
			'std_geoip_providers',
			array(
				'ip-api'  => array( __CLASS__, 'provider_ip_api' ),
				'maxmind' => array( __CLASS__, 'provider_maxmind' ),
			)
		);

		if ( isset( $providers[ $provider ] ) && is_callable( $providers[ $provider ] ) ) {
			$result = call_user_func( $providers[ $provider ], $ip );
			if ( is_array( $result ) ) {
				return wp_parse_args(
					$result,
					array(
						'country' => '',
						'city'    => '',
					)
				);
			}
		}

		return array(
			'country' => '',
			'city'    => '',
		);
	}

	/**
	 * Free ip-api.com provider. No API key required.
	 *
	 * @param string $ip IP address.
	 * @return array{country:string,city:string}
	 */
	public static function provider_ip_api( $ip ) {
		$empty = array(
			'country' => '',
			'city'    => '',
		);

		$url = add_query_arg(
			array( 'fields' => 'status,countryCode,city' ),
			'http://ip-api.com/json/' . rawurlencode( $ip )
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 3,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $empty;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ( $body['status'] ?? '' ) !== 'success' ) {
			return $empty;
		}

		return array(
			'country' => strtoupper( substr( sanitize_text_field( $body['countryCode'] ?? '' ), 0, 2 ) ),
			'city'    => sanitize_text_field( $body['city'] ?? '' ),
		);
	}

	/**
	 * MaxMind GeoLite2 web-service provider. Requires an account ID:license key
	 * supplied as the API key (format "accountId:licenseKey").
	 *
	 * @param string $ip IP address.
	 * @return array{country:string,city:string}
	 */
	public static function provider_maxmind( $ip ) {
		$empty = array(
			'country' => '',
			'city'    => '',
		);

		$key = STD_Settings::get( 'geoip_api_key', '' );
		if ( '' === $key || strpos( $key, ':' ) === false ) {
			// Misconfigured key: silently fall back to no result.
			return $empty;
		}

		list( $account_id, $license ) = array_pad( explode( ':', $key, 2 ), 2, '' );

		$response = wp_remote_get(
			'https://geolite.info/geoip/v2.1/city/' . rawurlencode( $ip ),
			array(
				'timeout' => 4,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $account_id . ':' . $license ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth, not obfuscation.
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $empty;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return $empty;
		}

		return array(
			'country' => strtoupper( substr( sanitize_text_field( $body['country']['iso_code'] ?? '' ), 0, 2 ) ),
			'city'    => sanitize_text_field( $body['city']['names']['en'] ?? '' ),
		);
	}
}
