<?php
/**
 * Inbound traffic logging and (best-effort) outbound HTTP logging.
 *
 * Inbound: records one row per non-asset front-end / admin request, honouring
 * the sensitivity setting to control how much is captured.
 *
 * Outbound: WordPress can only observe HTTP requests made through its own HTTP
 * API (wp_remote_*). Arbitrary outbound connections (raw sockets, other
 * processes) are invisible to PHP — the UI documents server-level logging as
 * the complete solution.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Traffic monitor.
 */
class STD_Traffic_Monitor {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		if ( STD_Settings::get( 'log_traffic' ) ) {
			// Log late so the matched status code is known where possible.
			add_action( 'wp', array( $this, 'log_inbound' ), 99 );
			add_action( 'admin_init', array( $this, 'log_inbound' ), 99 );
		}

		if ( STD_Settings::get( 'log_outbound' ) ) {
			add_action( 'http_api_debug', array( $this, 'log_outbound' ), 10, 5 );
		}
	}

	/**
	 * Record the current inbound request.
	 *
	 * @return void
	 */
	public function log_inbound() {
		// Avoid double logging within a single request.
		static $logged = false;
		if ( $logged ) {
			return;
		}
		$logged = true;

		// Never log the plugin's own AJAX/cron noise.
		if ( wp_doing_cron() ) {
			return;
		}

		// If the firewall already logged this request (e.g. a blocked hit in
		// monitor-only mode), don't record it a second time.
		if ( STD_Firewall::request_was_logged() ) {
			return;
		}

		$uri         = STD_Helpers::get_request_uri();
		$sensitivity = STD_Settings::get( 'sensitivity', 'medium' );

		// Sensitivity gating:
		// - low:    skip static assets AND logged-in admin browsing.
		// - medium: skip static assets.
		// - high:   log everything.
		if ( 'high' !== $sensitivity && STD_Helpers::is_static_asset( $uri ) ) {
			return;
		}
		if ( 'low' === $sensitivity && is_user_logged_in() && current_user_can( STD_CAPABILITY ) ) {
			return;
		}

		$ip = STD_Helpers::get_client_ip();

		// Geolocation is intentionally NOT performed here. Doing a synchronous
		// external lookup on every front-end request would delay page loads for
		// uncached visitor IPs. Country/city are left blank and backfilled in
		// bulk by the hourly aggregation cron (STD_GeoIP::backfill_recent()),
		// which is invisible to visitors.
		STD_Logger::log_traffic(
			array(
				'ip'          => $ip,
				'method'      => STD_Helpers::get_request_method(),
				'request_uri' => $uri,
				'user_agent'  => STD_Helpers::get_user_agent(),
				'referer'     => STD_Helpers::get_referer(),
				'status_code' => (int) http_response_code(),
				'is_blocked'  => 0,
				'country'     => '',
				'city'        => '',
			)
		);
	}

	/**
	 * Log a WordPress-originated outbound HTTP request.
	 *
	 * Fires via the core `http_api_debug` action after every wp_remote_* call.
	 *
	 * @param array|WP_Error $response  HTTP response or error.
	 * @param string         $context   Context ('response').
	 * @param string         $transport Transport class name (unused).
	 * @param array          $args      Request args.
	 * @param string         $url       Target URL.
	 * @return void
	 */
	public function log_outbound( $response, $context, $transport, $args, $url ) {
		// Only record the completed response context, not retries.
		if ( 'response' !== $context ) {
			return;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return;
		}

		$status = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$method = isset( $args['method'] ) ? $args['method'] : 'GET';

		// Store outbound calls in the traffic table, tagged via a synthetic URI
		// so they can be filtered out in the inbound view.
		STD_Logger::log_traffic(
			array(
				'ip'          => '',
				'method'      => $method,
				'request_uri' => 'OUTBOUND ' . esc_url_raw( $url ),
				'user_agent'  => 'wp-http',
				'referer'     => home_url(),
				'status_code' => $status,
				'is_blocked'  => 0,
				'country'     => '',
				'city'        => '',
			)
		);
	}
}
