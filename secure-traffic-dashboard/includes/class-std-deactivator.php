<?php
/**
 * Runs on plugin deactivation. Clears scheduled jobs but intentionally keeps
 * all logged data and settings, so that re-activating restores the previous
 * state. Destructive cleanup happens only on uninstall (see uninstall.php).
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivation routine.
 */
class STD_Deactivator {

	/**
	 * Perform deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'std_aggregate' );
		wp_clear_scheduled_hook( 'std_purge' );

		flush_rewrite_rules();
	}
}
