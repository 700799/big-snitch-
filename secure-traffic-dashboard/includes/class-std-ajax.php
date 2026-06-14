<?php
/**
 * Secured AJAX endpoints powering the dashboard's dynamic tables, charts and
 * mitigation actions.
 *
 * Every handler enforces: capability check, nonce verification, and a
 * per-user token-bucket rate limit so the dashboard itself cannot be abused to
 * hammer the database.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Every public handler in this class calls $this->guard() as its first
 * statement, which runs check_ajax_referer() (nonce verification) and the
 * capability + rate-limit checks before any superglobal is read. PHPCS cannot
 * trace that cross-method guarantee, so the nonce-verification sniffs are
 * disabled file-wide here with that documented justification.
 */
// phpcs:disable WordPress.Security.NonceVerification.Missing
// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * AJAX controller.
 */
class STD_Ajax {

	/**
	 * Register all AJAX actions (logged-in only; this is an admin tool).
	 *
	 * @return void
	 */
	public function hooks() {
		$actions = array(
			'std_get_logs',
			'std_get_charts',
			'std_block_ip',
			'std_unblock',
			'std_block_country',
			'std_complete_wizard',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, str_replace( 'std_', 'ajax_', $action ) ) );
		}
	}

	/**
	 * Shared guard: capability, nonce and rate-limit. Dies with a JSON error
	 * if any check fails.
	 *
	 * @return void
	 */
	private function guard() {
		if ( ! current_user_can( STD_CAPABILITY ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'secure-traffic-dashboard' ) ), 403 );
		}

		check_ajax_referer( 'std_ajax', 'nonce' );

		$this->rate_limit();
	}

	/**
	 * Simple per-user rate limit: max 60 dashboard AJAX calls per minute.
	 *
	 * @return void
	 */
	private function rate_limit() {
		$key   = 'std_rl_' . get_current_user_id();
		$count = (int) get_transient( $key );

		if ( $count >= 60 ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please slow down.', 'secure-traffic-dashboard' ) ), 429 );
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
	}

	/**
	 * Return a paginated, filterable page of log rows.
	 *
	 * @return void
	 */
	public function ajax_get_logs() {
		$this->guard();

		$table = isset( $_POST['table'] ) ? sanitize_key( wp_unslash( $_POST['table'] ) ) : 'traffic';
		if ( ! in_array( $table, array( 'traffic', 'logins', 'blocks' ), true ) ) {
			$table = 'traffic';
		}

		$args = array(
			'page'     => isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1,
			'per_page' => isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 25,
			'search'   => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
			'orderby'  => isset( $_POST['orderby'] ) ? sanitize_key( wp_unslash( $_POST['orderby'] ) ) : ( 'blocks' === $table ? 'created' : 'event_time' ),
			'order'    => isset( $_POST['order'] ) && 'asc' === strtolower( wp_unslash( $_POST['order'] ) ) ? 'ASC' : 'DESC',
		);

		// Optional success filter for the logins table.
		if ( 'logins' === $table && isset( $_POST['success'] ) && '' !== $_POST['success'] ) {
			$args['where'] = array( 'success' => absint( wp_unslash( $_POST['success'] ) ) );
		}
		// Optional blocked filter for the traffic table.
		if ( 'traffic' === $table && isset( $_POST['blocked'] ) && '1' === (string) $_POST['blocked'] ) {
			$args['where'] = array( 'is_blocked' => 1 );
		}

		$result = STD_Logger::get_rows( $table, $args );

		// Decorate rows for display (escaping happens client-side via text nodes,
		// but we add a flag emoji and a relative time here).
		foreach ( $result['rows'] as &$row ) {
			if ( isset( $row['country'] ) ) {
				$row['flag'] = STD_Helpers::country_flag( $row['country'] );
			}
			if ( isset( $row['event_time'] ) ) {
				$row['time_ago'] = STD_Helpers::time_ago( $row['event_time'] );
			}
		}
		unset( $row );

		wp_send_json_success( $result );
	}

	/**
	 * Return chart datasets for the analytics tab.
	 *
	 * @return void
	 */
	public function ajax_get_charts() {
		$this->guard();

		$range = isset( $_POST['range'] ) ? sanitize_key( wp_unslash( $_POST['range'] ) ) : '24h';

		switch ( $range ) {
			case '30d':
				$since  = 30 * DAY_IN_SECONDS;
				$bucket = 'day';
				break;
			case '7d':
				$since  = 7 * DAY_IN_SECONDS;
				$bucket = 'day';
				break;
			case '24h':
			default:
				$since  = DAY_IN_SECONDS;
				$bucket = 'hour';
				break;
		}

		$data = array(
			'traffic_series' => STD_Logger::time_series( 'traffic', $since, $bucket ),
			'login_fail'     => STD_Logger::time_series( 'logins', $since, $bucket, array( 'success' => 0 ) ),
			'login_ok'       => STD_Logger::time_series( 'logins', $since, $bucket, array( 'success' => 1 ) ),
			'top_ips'        => STD_Logger::top( 'traffic', 'ip', $since, 10 ),
			'top_countries'  => STD_Logger::top( 'traffic', 'country', $since, 10 ),
			'top_endpoints'  => STD_Logger::top( 'traffic', 'request_uri', $since, 10 ),
		);

		// Add flags to country results.
		foreach ( $data['top_countries'] as $c ) {
			$c->flag = STD_Helpers::country_flag( $c->label );
		}

		wp_send_json_success( $data );
	}

	/**
	 * Block an IP address.
	 *
	 * @return void
	 */
	public function ajax_block_ip() {
		$this->guard();

		$ip       = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
		$scope    = isset( $_POST['scope'] ) && 'perm' === $_POST['scope'] ? 'perm' : 'temp';
		$duration = isset( $_POST['duration'] ) ? absint( $_POST['duration'] ) : HOUR_IN_SECONDS;

		$id = STD_Mitigation::block_ip( $ip, $scope, $duration, __( 'Manually blocked from dashboard', 'secure-traffic-dashboard' ) );

		if ( $id ) {
			// First manual mitigation establishes the before/after baseline.
			STD_Metrics::capture_baseline();
			wp_send_json_success( array( 'message' => __( 'IP blocked.', 'secure-traffic-dashboard' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'Invalid IP address.', 'secure-traffic-dashboard' ) ) );
	}

	/**
	 * Block a country.
	 *
	 * @return void
	 */
	public function ajax_block_country() {
		$this->guard();

		$code = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$id   = STD_Mitigation::block_country( $code, __( 'Manually blocked from dashboard', 'secure-traffic-dashboard' ) );

		if ( $id ) {
			STD_Metrics::capture_baseline();
			wp_send_json_success( array( 'message' => __( 'Country blocked.', 'secure-traffic-dashboard' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'Invalid country code.', 'secure-traffic-dashboard' ) ) );
	}

	/**
	 * Remove a block by ID.
	 *
	 * @return void
	 */
	public function ajax_unblock() {
		$this->guard();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $id && STD_Mitigation::remove_block( $id ) ) {
			wp_send_json_success( array( 'message' => __( 'Block removed.', 'secure-traffic-dashboard' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'Could not remove block.', 'secure-traffic-dashboard' ) ) );
	}

	/**
	 * Mark the quick-start wizard as complete.
	 *
	 * @return void
	 */
	public function ajax_complete_wizard() {
		$this->guard();
		update_option( 'std_wizard_complete', 1 );
		wp_send_json_success();
	}
}
