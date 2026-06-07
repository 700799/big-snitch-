<?php
/**
 * Runs on plugin activation: creates tables, seeds default options, registers
 * the custom capability and schedules cron events.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation routine.
 */
class STD_Activator {

	/**
	 * Perform activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		self::add_capability();

		// Create the schema. STD_Settings::get_settings() lazily writes the
		// default option set the first time it is read.
		STD_Installer::create_tables();
		STD_Settings::get_settings();

		self::schedule_events();

		// Mark that the quick-start wizard should be offered on first visit.
		if ( false === get_option( 'std_wizard_complete', false ) ) {
			add_option( 'std_wizard_complete', 0 );
		}

		// Stamp the activation time so before/after baselines have an anchor.
		if ( ! get_option( 'std_activated_at' ) ) {
			add_option( 'std_activated_at', current_time( 'mysql' ) );
		}

		// Ensure rewrite/transient state is clean.
		flush_rewrite_rules();
	}

	/**
	 * Grant the custom capability to the administrator role.
	 *
	 * @return void
	 */
	private static function add_capability() {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( STD_CAPABILITY ) ) {
			$role->add_cap( STD_CAPABILITY );
		}
	}

	/**
	 * Schedule recurring background jobs.
	 *
	 * - std_aggregate: rolls raw events into cached metrics (hourly).
	 * - std_purge:     deletes events older than the retention window (daily).
	 *
	 * @return void
	 */
	private static function schedule_events() {
		if ( ! wp_next_scheduled( 'std_aggregate' ) ) {
			wp_schedule_event( time() + 60, 'hourly', 'std_aggregate' );
		}

		if ( ! wp_next_scheduled( 'std_purge' ) ) {
			wp_schedule_event( time() + 120, 'daily', 'std_purge' );
		}
	}
}
