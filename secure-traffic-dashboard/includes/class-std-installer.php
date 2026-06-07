<?php
/**
 * Schema definition + upgrade routines.
 *
 * Centralises the CREATE TABLE statements (run through dbDelta so the same
 * code handles both fresh installs and in-place schema upgrades) and the
 * version-gated migration logic.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles table creation and database migrations.
 */
class STD_Installer {

	/**
	 * Create or update all custom tables using dbDelta.
	 *
	 * dbDelta compares the desired schema with the existing one and issues the
	 * minimal ALTER/CREATE statements required, which makes it safe to call on
	 * every upgrade.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$traffic = STD_Helpers::table( 'traffic' );
		$logins  = STD_Helpers::table( 'logins' );
		$blocks  = STD_Helpers::table( 'blocks' );
		$metrics = STD_Helpers::table( 'metrics' );

		// Inbound traffic events.
		$sql_traffic = "CREATE TABLE {$traffic} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_time DATETIME NOT NULL,
			ip VARCHAR(45) NOT NULL DEFAULT '',
			method VARCHAR(10) NOT NULL DEFAULT '',
			request_uri VARCHAR(255) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			referer VARCHAR(255) NOT NULL DEFAULT '',
			status_code SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			is_blocked TINYINT(1) NOT NULL DEFAULT 0,
			country CHAR(2) NOT NULL DEFAULT '',
			city VARCHAR(100) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY event_time (event_time),
			KEY ip (ip),
			KEY is_blocked (is_blocked)
		) {$charset_collate};";

		// Login attempts (success and failure).
		$sql_logins = "CREATE TABLE {$logins} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_time DATETIME NOT NULL,
			ip VARCHAR(45) NOT NULL DEFAULT '',
			username VARCHAR(180) NOT NULL DEFAULT '',
			success TINYINT(1) NOT NULL DEFAULT 0,
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			country CHAR(2) NOT NULL DEFAULT '',
			city VARCHAR(100) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY event_time (event_time),
			KEY ip (ip),
			KEY success (success)
		) {$charset_collate};";

		// Active and historical blocks.
		$sql_blocks = "CREATE TABLE {$blocks} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			block_type VARCHAR(20) NOT NULL DEFAULT 'ip',
			value VARCHAR(64) NOT NULL DEFAULT '',
			scope VARCHAR(10) NOT NULL DEFAULT 'temp',
			reason VARCHAR(255) NOT NULL DEFAULT '',
			created DATETIME NOT NULL,
			expires DATETIME NULL DEFAULT NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY block_type (block_type),
			KEY value (value),
			KEY active (active),
			KEY expires (expires)
		) {$charset_collate};";

		// Aggregated metrics and before/after baselines.
		$sql_metrics = "CREATE TABLE {$metrics} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			metric_key VARCHAR(64) NOT NULL DEFAULT '',
			period VARCHAR(10) NOT NULL DEFAULT '',
			metric_value DOUBLE NOT NULL DEFAULT 0,
			captured DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY metric_key (metric_key),
			KEY period (period)
		) {$charset_collate};";

		dbDelta( $sql_traffic );
		dbDelta( $sql_logins );
		dbDelta( $sql_blocks );
		dbDelta( $sql_metrics );

		update_option( 'std_db_version', STD_DB_VERSION );
	}

	/**
	 * Run migrations if the stored schema version is behind the code version.
	 *
	 * Called on every load (cheaply, via a version compare) so that updating
	 * the plugin files alone is enough to migrate the schema without requiring
	 * a manual reactivation.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'std_db_version' );

		if ( STD_DB_VERSION === $installed ) {
			return;
		}

		// Future version-specific migrations branch here, e.g.:
		// if ( version_compare( $installed, '1.1.0', '<' ) ) { ... }

		// Re-running dbDelta is idempotent and brings any older schema in line
		// with the current definition.
		self::create_tables();
	}
}
