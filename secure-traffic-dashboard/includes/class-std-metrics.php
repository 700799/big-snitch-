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
