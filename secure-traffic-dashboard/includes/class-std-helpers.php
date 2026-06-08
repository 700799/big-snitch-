<?php
/**
 * Shared helper utilities: IP detection, table names, sanitizers and
 * presentation formatting.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stateless collection of helper methods used across the plugin.
 */
class STD_Helpers {

	/**
	 * Return the fully-prefixed name of one of the plugin's custom tables.
	 *
	 * @param string $name Logical table name: traffic|logins|blocks|metrics.
	 * @return string Full table name including the WordPress table prefix.
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'std_' . $name;
	}

	/**
	 * Determine the client IP address for the current request.
	 *
	 * By default only REMOTE_ADDR is trusted because forwarded headers can be
	 * spoofed. When the administrator enables the "trust proxy" setting (e.g.
	 * the site sits behind Cloudflare or a load balancer) we consult the
	 * configured forwarding header instead.
	 *
	 * @return string A validated IP address, or an empty string if none found.
	 */
	public static function get_client_ip() {
		$settings = STD_Settings::get_settings();
		$remote   = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$remote   = self::validate_ip( $remote );

		if ( empty( $settings['trust_proxy'] ) ) {
			return $remote;
		}

		// Trusted-proxy mode: read the configured forwarding header and take the
		// first (left-most) public address in the chain.
		$header_key = 'HTTP_' . strtoupper( str_replace( '-', '_', $settings['proxy_header'] ) );

		if ( ! empty( $_SERVER[ $header_key ] ) ) {
			$forwarded = wp_unslash( $_SERVER[ $header_key ] );
			$parts     = explode( ',', $forwarded );

			foreach ( $parts as $candidate ) {
				$candidate = self::validate_ip( trim( $candidate ) );
				if ( '' !== $candidate ) {
					return $candidate;
				}
			}
		}

		return $remote;
	}

	/**
	 * Validate an IP address (IPv4 or IPv6).
	 *
	 * @param string $ip Raw IP string.
	 * @return string The IP if valid, otherwise an empty string.
	 */
	public static function validate_ip( $ip ) {
		$ip = trim( (string) $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Sanitize a free-text user-agent string for storage.
	 *
	 * @return string Sanitized user agent (max 255 chars).
	 */
	public static function get_user_agent() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		return substr( sanitize_text_field( $ua ), 0, 255 );
	}

	/**
	 * Current request method (GET, POST, ...).
	 *
	 * @return string
	 */
	public static function get_request_method() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? wp_unslash( $_SERVER['REQUEST_METHOD'] ) : '';
		return substr( sanitize_text_field( $method ), 0, 10 );
	}

	/**
	 * Current request URI, sanitized and length-capped.
	 *
	 * @return string
	 */
	public static function get_request_uri() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return substr( esc_url_raw( $uri ), 0, 255 );
	}

	/**
	 * Referer header, sanitized.
	 *
	 * @return string
	 */
	public static function get_referer() {
		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '';
		return substr( esc_url_raw( $ref ), 0, 255 );
	}

	/**
	 * Convert a two-letter ISO country code to its emoji flag.
	 *
	 * Uses Unicode regional indicator symbols, so no external image requests
	 * are made. Returns a globe emoji when the code is unknown.
	 *
	 * @param string $code ISO 3166-1 alpha-2 country code.
	 * @return string Emoji flag.
	 */
	public static function country_flag( $code ) {
		$code = strtoupper( trim( (string) $code ) );

		if ( 2 !== strlen( $code ) || ! ctype_alpha( $code ) ) {
			return '🌐';
		}

		$flag = '';
		for ( $i = 0; $i < 2; $i++ ) {
			// 0x1F1E6 is the regional indicator for "A"; ord('A') is 65.
			$flag .= mb_convert_encoding( '&#' . ( 0x1F1E6 + ( ord( $code[ $i ] ) - 65 ) ) . ';', 'UTF-8', 'HTML-ENTITIES' );
		}

		return $flag;
	}

	/**
	 * Whether the request is an obvious static asset (image, css, js, font).
	 *
	 * Used by the traffic monitor to skip noisy asset hits at lower
	 * sensitivity levels.
	 *
	 * @param string $uri Request URI.
	 * @return bool
	 */
	public static function is_static_asset( $uri ) {
		return (bool) preg_match( '/\.(?:css|js|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot|map)(?:\?.*)?$/i', $uri );
	}

	/**
	 * Render an accessible contextual help tooltip (a "?" marker with a title).
	 *
	 * Output is escaped and safe to echo directly. The tooltip text is exposed
	 * both as a native `title` and via `aria-label` for screen readers.
	 *
	 * @param string $text Help text.
	 * @return string HTML for the help tip.
	 */
	public static function help_tip( $text ) {
		$text = wp_strip_all_tags( $text );
		return '<span class="std-help-tip dashicons dashicons-editor-help" tabindex="0" role="img" aria-label="'
			. esc_attr( $text ) . '" title="' . esc_attr( $text ) . '"></span>';
	}

	/**
	 * Human-friendly "time ago" string for a MySQL datetime.
	 *
	 * @param string $datetime MySQL datetime in site/UTC time.
	 * @return string
	 */
	public static function time_ago( $datetime ) {
		$timestamp = mysql2date( 'U', $datetime, false );
		if ( ! $timestamp ) {
			return esc_html__( 'unknown', 'secure-traffic-dashboard' );
		}

		/* translators: %s: human-readable time difference, e.g. "5 mins". */
		return sprintf( esc_html__( '%s ago', 'secure-traffic-dashboard' ), human_time_diff( $timestamp, time() ) );
	}
}
