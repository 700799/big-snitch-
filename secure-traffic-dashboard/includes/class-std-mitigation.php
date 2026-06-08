<?php
/**
 * Block management: create/remove IP and country blocks, evaluate whether a
 * given IP/country is currently blocked, and expose the active block list.
 *
 * This class is the source of truth for "is this request allowed?". The
 * firewall consults is_blocked() before enforcement.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mitigation / block-list manager.
 */
class STD_Mitigation {

	/**
	 * Block an IP address.
	 *
	 * @param string $ip       IP address to block.
	 * @param string $scope    'temp' or 'perm'.
	 * @param int    $duration Seconds until expiry (ignored for permanent).
	 * @param string $reason   Human-readable reason.
	 * @return int|false Block row ID or false on failure.
	 */
	public static function block_ip( $ip, $scope = 'temp', $duration = 3600, $reason = '' ) {
		$ip = STD_Helpers::validate_ip( $ip );
		if ( '' === $ip ) {
			return false;
		}
		return self::add_block( 'ip', $ip, $scope, $duration, $reason );
	}

	/**
	 * Block a country by ISO-2 code.
	 *
	 * @param string $code   Country code.
	 * @param string $reason Reason.
	 * @return int|false
	 */
	public static function block_country( $code, $reason = '' ) {
		$code = strtoupper( substr( sanitize_text_field( $code ), 0, 2 ) );
		if ( 2 !== strlen( $code ) ) {
			return false;
		}
		return self::add_block( 'country', $code, 'perm', 0, $reason );
	}

	/**
	 * Insert a block record (or refresh an existing active one).
	 *
	 * @param string $type     ip|country|rate|lockdown.
	 * @param string $value    IP or country code.
	 * @param string $scope    temp|perm.
	 * @param int    $duration Seconds until expiry (0/perm = no expiry).
	 * @param string $reason   Reason.
	 * @return int|false
	 */
	public static function add_block( $type, $value, $scope = 'temp', $duration = 3600, $reason = '' ) {
		global $wpdb;

		$scope   = in_array( $scope, array( 'temp', 'perm' ), true ) ? $scope : 'temp';
		$expires = ( 'perm' === $scope || $duration <= 0 )
			? null
			: gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + absint( $duration ) );

		$ok = $wpdb->insert(
			STD_Helpers::table( 'blocks' ),
			array(
				'block_type' => substr( $type, 0, 20 ),
				'value'      => substr( $value, 0, 64 ),
				'scope'      => $scope,
				'reason'     => substr( sanitize_text_field( $reason ), 0, 255 ),
				'created'    => current_time( 'mysql' ),
				'expires'    => $expires,
				'active'     => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', ( null === $expires ? null : '%s' ), '%d' )
		);

		if ( ! $ok ) {
			return false;
		}

		$id = (int) $wpdb->insert_id;

		// Bust the active-block cache so enforcement sees the new rule at once.
		self::flush_cache();

		/**
		 * Fires after a block is created.
		 *
		 * @param string $type  Block type.
		 * @param string $value Blocked value.
		 * @param int    $id    Block row ID.
		 */
		do_action( 'std_block_added', $type, $value, $id );

		return $id;
	}

	/**
	 * Deactivate (soft-remove) a block by its ID.
	 *
	 * @param int $id Block ID.
	 * @return bool
	 */
	public static function remove_block( $id ) {
		global $wpdb;

		$ok = $wpdb->update(
			STD_Helpers::table( 'blocks' ),
			array( 'active' => 0 ),
			array( 'id' => absint( $id ) ),
			array( '%d' ),
			array( '%d' )
		);

		self::flush_cache();

		if ( false !== $ok ) {
			/**
			 * Fires after a block is removed.
			 *
			 * @param int $id Block ID.
			 */
			do_action( 'std_block_removed', absint( $id ) );
		}

		return false !== $ok;
	}

	/**
	 * Determine whether the given IP (and its country) is currently blocked.
	 *
	 * Whitelisted IPs and countries always return false. Expired temporary
	 * blocks are treated as inactive.
	 *
	 * @param string $ip      IP address.
	 * @param string $country Optional ISO-2 country for the IP.
	 * @return bool
	 */
	public static function is_blocked( $ip, $country = '' ) {
		$ip = STD_Helpers::validate_ip( $ip );
		if ( '' === $ip ) {
			return false;
		}

		// Whitelist always wins.
		if ( self::is_whitelisted_ip( $ip ) ) {
			return false;
		}

		$blocks = self::get_active_blocks_map();

		// Direct IP block.
		if ( isset( $blocks['ip'][ $ip ] ) ) {
			return true;
		}

		// Country block (settings list or DB block), unless the country is
		// whitelisted.
		$country = strtoupper( $country );
		if ( '' !== $country && ! self::is_whitelisted_country( $country ) ) {
			if ( isset( $blocks['country'][ $country ] ) ) {
				return true;
			}

			$blocked_countries = STD_Settings::parse_list( STD_Settings::get( 'blocked_countries', '' ) );
			if ( in_array( $country, $blocked_countries, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an IP is on the configured whitelist (supports CIDR ranges).
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public static function is_whitelisted_ip( $ip ) {
		$list = STD_Settings::parse_list( STD_Settings::get( 'whitelist_ips', '' ) );

		foreach ( $list as $entry ) {
			if ( $entry === $ip ) {
				return true;
			}
			if ( strpos( $entry, '/' ) !== false && self::ip_in_cidr( $ip, $entry ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a country code is whitelisted.
	 *
	 * @param string $country ISO-2 code.
	 * @return bool
	 */
	public static function is_whitelisted_country( $country ) {
		$list = STD_Settings::parse_list( STD_Settings::get( 'whitelist_countries', '' ) );
		return in_array( strtoupper( $country ), array_map( 'strtoupper', $list ), true );
	}

	/**
	 * Return the active blocks as a fast lookup map, cached in a transient.
	 *
	 * @return array{ip:array,country:array}
	 */
	public static function get_active_blocks_map() {
		$cached = get_transient( 'std_active_blocks' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table = STD_Helpers::table( 'blocks' );
		$now   = current_time( 'mysql' );

		// Active rows whose expiry is null or in the future.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT block_type, value FROM {$table} WHERE active = 1 AND ( expires IS NULL OR expires > %s )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now
			)
		);

		$map = array(
			'ip'      => array(),
			'country' => array(),
		);
		foreach ( (array) $rows as $row ) {
			if ( 'country' === $row->block_type ) {
				$map['country'][ strtoupper( $row->value ) ] = true;
			} else {
				$map['ip'][ $row->value ] = true;
			}
		}

		set_transient( 'std_active_blocks', $map, 5 * MINUTE_IN_SECONDS );

		return $map;
	}

	/**
	 * Fetch active block rows for display in the admin UI.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public static function get_active_block_rows( $limit = 200 ) {
		global $wpdb;
		$table = STD_Helpers::table( 'blocks' );
		$limit = min( 500, max( 1, absint( $limit ) ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE active = 1 ORDER BY created DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Clear the active-blocks cache.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		delete_transient( 'std_active_blocks' );
	}

	/**
	 * Test whether an IPv4 address falls within a CIDR range.
	 *
	 * IPv6 CIDRs are not range-matched (only exact match handled by caller).
	 *
	 * @param string $ip   IP address.
	 * @param string $cidr CIDR notation, e.g. 192.168.1.0/24.
	 * @return bool
	 */
	private static function ip_in_cidr( $ip, $cidr ) {
		if ( strpos( $cidr, '/' ) === false ) {
			return false;
		}

		list( $subnet, $bits ) = explode( '/', $cidr, 2 );
		$bits                  = (int) $bits;

		// Only handle IPv4 range maths here.
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )
			|| false === filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}

		$ip_long     = ip2long( $ip );
		$subnet_long = ip2long( $subnet );
		if ( false === $ip_long || false === $subnet_long || $bits < 0 || $bits > 32 ) {
			return false;
		}

		$mask = -1 << ( 32 - $bits );
		return ( $ip_long & $mask ) === ( $subnet_long & $mask );
	}
}
