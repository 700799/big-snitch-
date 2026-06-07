<?php
/**
 * Plugin Name:       SecureTraffic Dashboard
 * Plugin URI:        https://example.com/secure-traffic-dashboard
 * Description:       A comprehensive admin dashboard to monitor inbound traffic and login attempts, geolocate request origins, apply mitigations (IP/country blocking, rate limiting, login lockdown, basic firewall) and measure the before/after security impact.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            SecureTraffic
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       secure-traffic-dashboard
 * Domain Path:       /languages
 *
 * @package SecureTraffic_Dashboard
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * ---------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------------
 */

define( 'STD_VERSION', '1.0.0' );

/**
 * Database schema version. Bump this when the table structure changes so the
 * upgrade routine in STD_Installer can run the appropriate migration.
 */
define( 'STD_DB_VERSION', '1.0.0' );

define( 'STD_PLUGIN_FILE', __FILE__ );
define( 'STD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Capability required to view and manage the dashboard. Granted to the
 * administrator role on activation. Code paths fall back to manage_options.
 */
define( 'STD_CAPABILITY', 'manage_secure_traffic' );

/*
 * ---------------------------------------------------------------------------
 * Autoloader
 * ---------------------------------------------------------------------------
 *
 * Lightweight PSR-style autoloader keyed off the "STD_" class prefix. Maps a
 * class such as STD_Login_Monitor to includes/class-std-login-monitor.php.
 * Avoids a Composer dependency while keeping one class per file.
 */
spl_autoload_register(
	function ( $class ) {
		if ( 0 !== strpos( $class, 'STD_' ) ) {
			return;
		}

		// STD_Login_Monitor -> class-std-login-monitor.php
		$file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		$path = STD_PLUGIN_DIR . 'includes/' . $file;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

/*
 * ---------------------------------------------------------------------------
 * Activation / Deactivation / Uninstall hooks
 * ---------------------------------------------------------------------------
 *
 * Uninstall is handled by the dedicated uninstall.php file (WordPress runs it
 * automatically), so we only register activation and deactivation here.
 */

register_activation_hook(
	__FILE__,
	function () {
		STD_Activator::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		STD_Deactivator::deactivate();
	}
);

/*
 * ---------------------------------------------------------------------------
 * Bootstrap
 * ---------------------------------------------------------------------------
 *
 * Instantiate the orchestrator on plugins_loaded so all WordPress APIs are
 * available. STD_Plugin wires every module to the relevant hooks.
 */
add_action(
	'plugins_loaded',
	function () {
		STD_Plugin::instance()->run();
	}
);
