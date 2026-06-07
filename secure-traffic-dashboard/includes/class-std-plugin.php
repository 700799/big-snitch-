<?php
/**
 * Plugin orchestrator. A single instance that loads the text domain, runs any
 * pending upgrade, and wires every module to its WordPress hooks.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class (singleton).
 */
class STD_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var STD_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return STD_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor (use instance()).
	 */
	private function __construct() {}

	/**
	 * Boot the plugin: load i18n, migrate, and register all hooks.
	 *
	 * @return void
	 */
	public function run() {
		// Translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Run schema migrations if the plugin was updated via file copy.
		STD_Installer::maybe_upgrade();

		// Cron callbacks.
		add_action( 'std_aggregate', array( 'STD_Metrics', 'aggregate' ) );
		add_action( 'std_purge', array( 'STD_Metrics', 'purge' ) );

		// Firewall: evaluate the request as early as possible. run() already
		// fires on plugins_loaded, so we evaluate immediately for front-end
		// and AJAX requests (admin screens are gated by capability anyway).
		if ( ! wp_doing_cron() ) {
			STD_Firewall::evaluate_request();
		}

		// Monitors.
		( new STD_Traffic_Monitor() )->hooks();
		( new STD_Login_Monitor() )->hooks();

		// Admin UI + AJAX + export (admin context only for the UI pieces).
		if ( is_admin() ) {
			( new STD_Admin() )->hooks();
			( new STD_Ajax() )->hooks();
			( new STD_Export() )->hooks();
		}

		/**
		 * Fires once the plugin has finished wiring its hooks. Use this to
		 * register custom modules, providers or dashboard tabs.
		 *
		 * @param STD_Plugin $plugin The plugin instance.
		 */
		do_action( 'std_loaded', $this );
	}

	/**
	 * Load the plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'secure-traffic-dashboard',
			false,
			dirname( STD_PLUGIN_BASENAME ) . '/languages'
		);
	}
}
