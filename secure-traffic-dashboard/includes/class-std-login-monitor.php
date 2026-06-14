<?php
/**
 * Login attempt monitoring. Records successful and failed logins (with
 * geolocation) and feeds failures into the brute-force tracker.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Login monitor.
 */
class STD_Login_Monitor {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		if ( ! STD_Settings::get( 'log_logins' ) ) {
			return;
		}

		add_action( 'wp_login', array( $this, 'on_success' ), 10, 1 );
		add_action( 'wp_login_failed', array( $this, 'on_failure' ), 10, 1 );

		// Block the login attempt outright if the IP is locked out / blocked,
		// unless we are in monitor-only mode.
		add_filter( 'authenticate', array( $this, 'maybe_block_login' ), 30, 1 );
	}

	/**
	 * Record a successful login.
	 *
	 * @param string $user_login Username.
	 * @return void
	 */
	public function on_success( $user_login ) {
		$ip  = STD_Helpers::get_client_ip();
		$geo = STD_GeoIP::lookup( $ip );

		STD_Logger::log_login(
			array(
				'ip'         => $ip,
				'username'   => $user_login,
				'success'    => 1,
				'user_agent' => STD_Helpers::get_user_agent(),
				'country'    => $geo['country'],
				'city'       => $geo['city'],
			)
		);
	}

	/**
	 * Record a failed login and update brute-force state.
	 *
	 * @param string $username Attempted username.
	 * @return void
	 */
	public function on_failure( $username ) {
		$ip  = STD_Helpers::get_client_ip();
		$geo = STD_GeoIP::lookup( $ip );

		STD_Logger::log_login(
			array(
				'ip'         => $ip,
				'username'   => $username,
				'success'    => 0,
				'user_agent' => STD_Helpers::get_user_agent(),
				'country'    => $geo['country'],
				'city'       => $geo['city'],
			)
		);

		STD_Firewall::register_failed_login( $ip );
	}

	/**
	 * Short-circuit authentication for blocked / locked-out IPs.
	 *
	 * @param null|WP_User|WP_Error $user Current auth result.
	 * @return null|WP_User|WP_Error
	 */
	public function maybe_block_login( $user ) {
		// Respect monitor-only mode: log only, do not interfere with auth.
		if ( STD_Settings::get( 'monitor_only' ) ) {
			return $user;
		}

		$ip = STD_Helpers::get_client_ip();
		if ( '' === $ip || STD_Mitigation::is_whitelisted_ip( $ip ) ) {
			return $user;
		}

		$country = STD_Firewall::country_blocking_active() ? STD_GeoIP::lookup( $ip )['country'] : '';
		if ( STD_Mitigation::is_blocked( $ip, $country ) ) {
			return new WP_Error(
				'std_blocked',
				esc_html__( 'Access from your location is temporarily blocked due to suspicious activity.', 'secure-traffic-dashboard' )
			);
		}

		return $user;
	}
}
