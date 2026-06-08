<?php
/**
 * Metrics: summary numbers for the dashboard cards, cron-driven aggregation,
 * before/after impact baselines and the daily purge.
 *
 * Summary figures are cached in a short transient so the dashboard never runs
 * the heavy COUNT queries on every page view.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Metrics and aggregation.
 */
class STD_Metrics {

	/**
	 * Transient key for the cached summary block.
	 */
	const SUMMARY_CACHE = 'std_summary_cache';

	/**
	 * Return the headline summary metrics for the overview cards.
	 *
	 * @param bool $force Bypass the cache.
	 * @return array
	 */
	public static function get_summary( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::SUMMARY_CACHE );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$summary = array(
			'requests_24h'      => STD_Logger::count( 'traffic', DAY_IN_SECONDS ),
			'requests_7d'       => STD_Logger::count( 'traffic', 7 * DAY_IN_SECONDS ),
			'blocked_24h'       => STD_Logger::count( 'traffic', DAY_IN_SECONDS, array( 'is_blocked' => 1 ) ),
			'blocked_total'     => STD_Logger::count( 'traffic', 0, array( 'is_blocked' => 1 ) ),
			'logins_failed_24h' => STD_Logger::count( 'logins', DAY_IN_SECONDS, array( 'success' => 0 ) ),
			'logins_ok_24h'     => STD_Logger::count( 'logins', DAY_IN_SECONDS, array( 'success' => 1 ) ),
			'active_blocks'     => self::count_active_blocks(),
		);

		set_transient( self::SUMMARY_CACHE, $summary, 5 * MINUTE_IN_SECONDS );

		return $summary;
	}

	/**
	 * Count currently-active block rules.
	 *
	 * @return int
	 */
	public static function count_active_blocks() {
		$map = STD_Mitigation::get_active_blocks_map();
		return count( $map['ip'] ) + count( $map['country'] );
	}

	/**
	 * Hourly aggregation cron callback.
	 *
	 * Rolls recent counts into the metrics table and refreshes the summary
	 * cache. Keeping a historical metrics trail lets the reports tab show
	 * trends even after raw events are purged.
	 *
	 * @return void
	 */
	public static function aggregate() {
		global $wpdb;

		$now     = current_time( 'mysql' );
		$metrics = array(
			'failed_logins_1h' => STD_Logger::count( 'logins', HOUR_IN_SECONDS, array( 'success' => 0 ) ),
			'requests_1h'      => STD_Logger::count( 'traffic', HOUR_IN_SECONDS ),
			'blocked_1h'       => STD_Logger::count( 'traffic', HOUR_IN_SECONDS, array( 'is_blocked' => 1 ) ),
		);

		foreach ( $metrics as $key => $value ) {
			$wpdb->insert(
				STD_Helpers::table( 'metrics' ),
				array(
					'metric_key'   => $key,
					'period'       => '1h',
					'metric_value' => (float) $value,
					'captured'     => $now,
				),
				array( '%s', '%s', '%f', '%s' )
			);
		}

		// Backfill geolocation for recently-logged rows (kept off the request
		// path for performance).
		STD_GeoIP::backfill_recent( 50 );

		// Refresh cached summary.
		self::get_summary( true );

		// Evaluate email alert threshold.
		self::maybe_send_alert( $metrics['failed_logins_1h'] );
	}

	/**
	 * Scheduled digest cron callback. Emails a security summary at the
	 * configured cadence (daily/weekly).
	 *
	 * @return void
	 */
	public static function send_digest() {
		$freq = STD_Settings::get( 'digest_frequency', 'off' );
		if ( 'off' === $freq ) {
			return;
		}

		$window  = ( 'weekly' === $freq ) ? 7 * DAY_IN_SECONDS : DAY_IN_SECONDS;
		$summary = self::get_summary( true );

		$top_ips = STD_Logger::top( 'traffic', 'ip', $window, 5 );
		$top_cc  = STD_Logger::top( 'traffic', 'country', $window, 5 );

		$to = STD_Settings::get( 'digest_email' );
		if ( ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}

		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subject = sprintf(
			/* translators: 1: cadence (daily/weekly), 2: site name. */
			__( '[%2$s] SecureTraffic %1$s security digest', 'secure-traffic-dashboard' ),
			$freq,
			$site
		);

		// Plain-text body (deliverable everywhere, no markup dependency).
		$lines   = array();
		$lines[] = sprintf(
			/* translators: %s: site name. */
			__( 'Security digest for %s', 'secure-traffic-dashboard' ),
			$site
		);
		$lines[] = str_repeat( '=', 48 );
		$lines[] = '';
		/* translators: %d: number of requests. */
		$lines[] = sprintf( __( 'Requests (24h): %d', 'secure-traffic-dashboard' ), (int) $summary['requests_24h'] );
		/* translators: %d: number of blocked events. */
		$lines[] = sprintf( __( 'Blocked (24h): %d', 'secure-traffic-dashboard' ), (int) $summary['blocked_24h'] );
		/* translators: %d: number of failed logins. */
		$lines[] = sprintf( __( 'Failed logins (24h): %d', 'secure-traffic-dashboard' ), (int) $summary['logins_failed_24h'] );
		/* translators: %d: number of successful logins. */
		$lines[] = sprintf( __( 'Successful logins (24h): %d', 'secure-traffic-dashboard' ), (int) $summary['logins_ok_24h'] );
		/* translators: %d: number of active blocks. */
		$lines[] = sprintf( __( 'Active blocks: %d', 'secure-traffic-dashboard' ), (int) $summary['active_blocks'] );
		$lines[] = '';

		if ( $top_ips ) {
			$lines[] = __( 'Top source IPs:', 'secure-traffic-dashboard' );
			foreach ( $top_ips as $row ) {
				$lines[] = '  ' . $row->label . ' — ' . $row->total;
			}
			$lines[] = '';
		}

		if ( $top_cc ) {
			$lines[] = __( 'Top countries:', 'secure-traffic-dashboard' );
			foreach ( $top_cc as $row ) {
				$lines[] = '  ' . $row->label . ' — ' . $row->total;
			}
			$lines[] = '';
		}

		$lines[] = __( 'Open the dashboard:', 'secure-traffic-dashboard' );
		$lines[] = admin_url( 'admin.php?page=secure-traffic-dashboard' );

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}

	/**
	 * Reschedule the digest cron when settings change. Hooked to the settings
	 * option update so a frequency change takes effect immediately.
	 *
	 * @return void
	 */
	public static function reschedule_digest() {
		wp_clear_scheduled_hook( 'std_digest' );

		$freq = STD_Settings::get( 'digest_frequency', 'off' );
		if ( 'off' === $freq ) {
			return;
		}

		$recurrence = ( 'weekly' === $freq ) ? 'weekly' : 'daily';

		// WordPress ships a 'weekly' schedule since 5.4; fall back to daily.
		$schedules = wp_get_schedules();
		if ( ! isset( $schedules[ $recurrence ] ) ) {
			$recurrence = 'daily';
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, $recurrence, 'std_digest' );
	}

	/**
	 * Daily purge cron callback. Honours the retention setting.
	 *
	 * @return void
	 */
	public static function purge() {
		$days = (int) STD_Settings::get( 'retention_days', 30 );
		if ( $days <= 0 ) {
			return; // Keep forever.
		}

		STD_Logger::purge_older_than( 'traffic', $days );
		STD_Logger::purge_older_than( 'logins', $days );

		// Also deactivate expired temporary blocks.
		global $wpdb;
		$table = STD_Helpers::table( 'blocks' );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET active = 0 WHERE active = 1 AND expires IS NOT NULL AND expires < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql' )
			)
		);
		STD_Mitigation::flush_cache();
	}

	/**
	 * Capture a before/after baseline when mitigation is first switched on.
	 *
	 * Records the failed-login rate over the 7 days *before* the cutover so the
	 * reports tab can compare it against the period after.
	 *
	 * @return void
	 */
	public static function capture_baseline() {
		if ( get_option( 'std_baseline_captured' ) ) {
			return;
		}

		$before = STD_Logger::count( 'logins', 7 * DAY_IN_SECONDS, array( 'success' => 0 ) );

		update_option(
			'std_baseline',
			array(
				'failed_logins_before' => (int) $before,
				'captured_at'          => current_time( 'mysql' ),
			)
		);
		update_option( 'std_baseline_captured', 1 );
	}

	/**
	 * Compute before/after impact for the reports tab.
	 *
	 * @return array{before:int,after:int,reduction:float,since:string}
	 */
	public static function get_impact() {
		$baseline = get_option( 'std_baseline', array() );
		$before   = isset( $baseline['failed_logins_before'] ) ? (int) $baseline['failed_logins_before'] : 0;
		$since    = isset( $baseline['captured_at'] ) ? $baseline['captured_at'] : '';

		// "After" = failed logins in the most recent 7-day window.
		$after = STD_Logger::count( 'logins', 7 * DAY_IN_SECONDS, array( 'success' => 0 ) );

		$reduction = 0.0;
		if ( $before > 0 ) {
			$reduction = round( ( ( $before - $after ) / $before ) * 100, 1 );
		}

		return array(
			'before'    => $before,
			'after'     => $after,
			'reduction' => $reduction,
			'since'     => $since,
		);
	}

	/**
	 * Send an email alert when the failed-login rate breaches the threshold.
	 *
	 * Rate-limited to at most one alert per hour via a transient flag.
	 *
	 * @param int $failed_last_hour Failed logins in the last hour.
	 * @return void
	 */
	private static function maybe_send_alert( $failed_last_hour ) {
		if ( ! STD_Settings::get( 'alerts_enabled' ) ) {
			return;
		}

		$threshold = (int) STD_Settings::get( 'alert_threshold', 50 );
		if ( $failed_last_hour < $threshold ) {
			return;
		}

		if ( get_transient( 'std_alert_sent' ) ) {
			return; // Already alerted this hour.
		}

		$to = STD_Settings::get( 'alert_email' );
		if ( ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}

		$subject = sprintf(
			/* translators: %s: site name. */
			__( '[%s] SecureTraffic Dashboard: high failed-login activity', 'secure-traffic-dashboard' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$message = sprintf(
			/* translators: 1: failed login count, 2: threshold, 3: dashboard URL. */
			__( "SecureTraffic Dashboard detected %1\$d failed login attempts in the last hour, which exceeds your alert threshold of %2\$d.\n\nReview the activity here:\n%3\$s", 'secure-traffic-dashboard' ),
			$failed_last_hour,
			$threshold,
			admin_url( 'admin.php?page=secure-traffic-dashboard' )
		);

		wp_mail( $to, $subject, $message );
		set_transient( 'std_alert_sent', 1, HOUR_IN_SECONDS );
	}
}
