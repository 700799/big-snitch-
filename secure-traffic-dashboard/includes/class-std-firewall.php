<?php
/**
 * Request-time firewall: enforces active blocks, runs bad-pattern signatures
 * and rate-limits brute-force attempts.
 *
 * Enforcement is gated by "monitor only" mode — when monitor-only is on the
 * firewall records what it *would* do but never actually blocks, guaranteeing
 * zero false positives while the admin tunes sensitivity.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Firewall engine.
 */
class STD_Firewall {

	/**
	 * Whether the current request has already been evaluated (avoid double work).
	 *
	 * @var bool
	 */
	private static $evaluated = false;

	/**
	 * Whether the firewall already wrote a traffic row for this request, so the
	 * traffic monitor can avoid logging it a second time in monitor-only mode.
	 *
	 * @var bool
	 */
	private static $request_logged = false;

	/**
	 * Whether the firewall has already logged the current request.
	 *
	 * @return bool
	 */
	public static function request_was_logged() {
		return self::$request_logged;
	}

	/**
	 * Evaluate the current request early in the WordPress lifecycle.
	 *
	 * Hooked very early so a blocked request is rejected before WordPress does
	 * expensive work. Returns the resolved client IP/country for reuse by the
	 * traffic monitor.
	 *
	 * @return void
	 */
	public static function evaluate_request() {
		if ( self::$evaluated ) {
			return;
		}
		self::$evaluated = true;

		if ( ! STD_Settings::get( 'firewall_enabled' ) ) {
			return;
		}

		$ip = STD_Helpers::get_client_ip();
		if ( '' === $ip ) {
			return;
		}

		// Whitelisted IPs bypass the firewall entirely.
		if ( STD_Mitigation::is_whitelisted_ip( $ip ) ) {
			return;
		}

		// Only resolve the country when country-level blocking is actually in
		// use, so the common (IP-only) case never makes an external lookup at
		// request time.
		$country = '';
		if ( self::country_blocking_active() ) {
			$geo     = STD_GeoIP::lookup( $ip );
			$country = $geo['country'];
		}

		$reason = '';

		// 1. Existing block (IP or country).
		if ( STD_Mitigation::is_blocked( $ip, $country ) ) {
			$reason = 'blocked-list';
		}

		// 2. Bad-pattern signatures in the request.
		if ( '' === $reason && STD_Settings::get( 'block_bad_patterns' ) && self::matches_bad_pattern() ) {
			$reason = 'bad-pattern';
		}

		/**
		 * Filter the final block decision for a request.
		 *
		 * @param bool   $should_block Whether to block.
		 * @param string $ip           Client IP.
		 * @param string $reason       Reason slug ('' if no rule matched).
		 */
		$should_block = apply_filters( 'std_should_block', ( '' !== $reason ), $ip, $reason );

		if ( $should_block ) {
			self::handle_block( $ip, $country, $reason );
		}
	}

	/**
	 * Whether any country-level blocking is configured (DB block or setting).
	 *
	 * Cached for the request via a static flag; the underlying block map is
	 * itself transient-cached, so this is cheap.
	 *
	 * @return bool
	 */
	public static function country_blocking_active() {
		$map = STD_Mitigation::get_active_blocks_map();
		if ( ! empty( $map['country'] ) ) {
			return true;
		}
		$blocked = STD_Settings::parse_list( STD_Settings::get( 'blocked_countries', '' ) );
		return ! empty( $blocked );
	}

	/**
	 * React to a matched rule: always log it; enforce only outside monitor mode.
	 *
	 * @param string $ip      Client IP.
	 * @param string $country Country code.
	 * @param string $reason  Reason slug.
	 * @return void
	 */
	private static function handle_block( $ip, $country, $reason ) {
		STD_Logger::log_traffic(
			array(
				'ip'          => $ip,
				'method'      => STD_Helpers::get_request_method(),
				'request_uri' => STD_Helpers::get_request_uri(),
				'user_agent'  => STD_Helpers::get_user_agent(),
				'referer'     => STD_Helpers::get_referer(),
				'status_code' => 403,
				'is_blocked'  => 1,
				'country'     => $country,
				'city'        => '',
			)
		);
		self::$request_logged = true;

		// Monitor-only: record but do not actually block.
		if ( STD_Settings::get( 'monitor_only' ) ) {
			return;
		}

		/**
		 * Fires immediately before a request is rejected.
		 *
		 * @param string $ip     Client IP.
		 * @param string $reason Reason slug.
		 */
		do_action( 'std_request_blocked', $ip, $reason );

		self::deny();
	}

	/**
	 * Send a 403 response and stop execution.
	 *
	 * @return void
	 */
	private static function deny() {
		if ( ! headers_sent() ) {
			status_header( 403 );
			nocache_headers();
		}
		wp_die(
			esc_html__( 'Your request has been blocked by SecureTraffic Dashboard.', 'secure-traffic-dashboard' ),
			esc_html__( 'Access Denied', 'secure-traffic-dashboard' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Check the request URI / query string against known-bad signatures.
	 *
	 * Deliberately conservative to limit false positives: targets obvious
	 * traversal, SQLi and XSS probes in the URL only.
	 *
	 * @return bool True if a signature matched.
	 */
	private static function matches_bad_pattern() {
		$uri   = rawurldecode( STD_Helpers::get_request_uri() );
		$query = isset( $_SERVER['QUERY_STRING'] ) ? rawurldecode( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
		$haystack = strtolower( $uri . ' ' . $query );

		/**
		 * Filter the firewall signature list.
		 *
		 * @param array $patterns Array of regex patterns (without delimiters).
		 */
		$patterns = apply_filters(
			'std_firewall_rules',
			array(
				'\.\./\.\./',                      // Directory traversal.
				'union\s+select',                  // SQL injection.
				'select.+from\s+information_schema',
				'<script\b',                       // Reflected XSS.
				'javascript:',
				'base64_decode\s*\(',              // PHP code injection probes.
				'\beval\s*\(',
				'/etc/passwd',                     // Local file disclosure.
				'(?:wp-config\.php|\.git/)',       // Sensitive file probes.
			)
		);

		foreach ( $patterns as $pattern ) {
			if ( @preg_match( '#' . $pattern . '#i', $haystack ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- guards against a malformed custom pattern.
				return true;
			}
		}

		return false;
	}

	/**
	 * Record a failed login for brute-force tracking and, if the threshold is
	 * exceeded, create a temporary lockout block.
	 *
	 * Uses a transient counter per IP within a rolling window so no extra DB
	 * writes are needed for the common case.
	 *
	 * @param string $ip Client IP.
	 * @return void
	 */
	public static function register_failed_login( $ip ) {
		if ( ! STD_Settings::get( 'bruteforce_enabled' ) ) {
			return;
		}

		$ip = STD_Helpers::validate_ip( $ip );
		if ( '' === $ip || STD_Mitigation::is_whitelisted_ip( $ip ) ) {
			return;
		}

		$window  = (int) STD_Settings::get( 'login_window', 300 );
		$max     = (int) STD_Settings::get( 'login_max_attempts', 5 );
		$lockout = (int) STD_Settings::get( 'login_lockout', 900 );

		$key   = 'std_bf_' . md5( $ip );
		$count = (int) get_transient( $key );
		++$count;
		set_transient( $key, $count, $window );

		if ( $count >= $max ) {
			// Threshold breached: lock the IP out for the configured duration.
			STD_Mitigation::add_block(
				'lockdown',
				$ip,
				'temp',
				$lockout,
				/* translators: %d: number of failed attempts. */
				sprintf( __( 'Brute-force lockout after %d failed logins', 'secure-traffic-dashboard' ), $count )
			);
			delete_transient( $key );

			/**
			 * Fires when an IP is locked out for brute force.
			 *
			 * @param string $ip    Client IP.
			 * @param int    $count Failed attempt count.
			 */
			do_action( 'std_bruteforce_lockout', $ip, $count );
		}
	}

	/**
	 * Whether an IP is currently in brute-force lockout (used to short-circuit
	 * the login form).
	 *
	 * @param string $ip Client IP.
	 * @return bool
	 */
	public static function is_locked_out( $ip ) {
		$map = STD_Mitigation::get_active_blocks_map();
		return isset( $map['ip'][ $ip ] );
	}
}
