<?php
/**
 * Uninstall routine. WordPress runs this automatically when the plugin is
 * deleted from the Plugins screen.
 *
 * Destructive cleanup (dropping tables, deleting options, removing the custom
 * capability) only happens when the admin opted in via the
 * "delete_on_uninstall" setting. Otherwise data is preserved so a reinstall
 * keeps the history.
 *
 * @package SecureTraffic_Dashboard
 */

// Bail unless WordPress is performing a legitimate uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all plugin data from a single site.
 *
 * @return void
 */
function std_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'std_settings', array() );

	// Respect the user's choice: keep data unless explicitly told to delete.
	if ( empty( $settings['delete_on_uninstall'] ) ) {
		return;
	}

	// Drop custom tables.
	$tables = array( 'std_traffic', 'std_logins', 'std_blocks', 'std_metrics' );
	foreach ( $tables as $table ) {
		$name = $wpdb->prefix . $table;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$name}" );
	}

	// Delete options.
	$options = array(
		'std_settings',
		'std_db_version',
		'std_wizard_complete',
		'std_activated_at',
		'std_baseline',
		'std_baseline_captured',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete our transients (cache + rate-limit + geo cache).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '\_transient\_std\_%'
		    OR option_name LIKE '\_transient\_timeout\_std\_%'"
	);

	// Remove the custom capability from all roles.
	$roles = wp_roles();
	if ( $roles ) {
		foreach ( $roles->role_objects as $role ) {
			if ( $role->has_cap( 'manage_secure_traffic' ) ) {
				$role->remove_cap( 'manage_secure_traffic' );
			}
		}
	}

	// Clear any scheduled events (belt and braces; deactivation also does this).
	wp_clear_scheduled_hook( 'std_aggregate' );
	wp_clear_scheduled_hook( 'std_purge' );
}

// Run cleanup across the network on multisite, or just the current site.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		std_uninstall_site();
		restore_current_blog();
	}
} else {
	std_uninstall_site();
}
