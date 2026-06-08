<?php
/**
 * PHPUnit bootstrap.
 *
 * These are fast, isolated unit tests for the plugin's pure logic. Rather than
 * standing up the full WordPress test suite (a heavy external dependency), we
 * define just enough WordPress function shims for the classes under test to
 * load and run. This keeps CI lightweight and the tests deterministic.
 *
 * @package SecureTraffic_Dashboard
 */

// Minimal constants the class files expect.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 30 * 24 * 60 * 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

// Simple in-memory option store used by the get/add/update_option shims.
$GLOBALS['std_test_options'] = array();

/**
 * Reset the in-memory option store between tests.
 */
function std_test_reset_options() {
	$GLOBALS['std_test_options'] = array();
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['std_test_options'] ) ? $GLOBALS['std_test_options'][ $key ] : $default;
	}
}
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $key, $value = '' ) {
		if ( ! array_key_exists( $key, $GLOBALS['std_test_options'] ) ) {
			$GLOBALS['std_test_options'][ $key ] = $value;
		}
		return true;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value ) {
		$GLOBALS['std_test_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		unset( $GLOBALS['std_test_options'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, (array) $args );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action() {}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {
		return true;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		$str = (string) $str;
		$str = wp_strip_all_tags( $str );
		$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
		return trim( $str );
	}
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		$email = trim( (string) $email );
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $n ) {
		return abs( (int) $n );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return trim( (string) $url );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return trim( preg_replace( '/<[^>]*>/', '', (string) $string ) );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

// i18n passthroughs.
foreach ( array( '__', 'esc_html__', 'esc_attr__' ) as $fn ) {
	if ( ! function_exists( $fn ) ) {
		eval( "function {$fn}( \$text, \$domain = 'default' ) { return \$text; }" ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test shim only.
	}
}

// Transient shims (used by some classes; no-op cache for unit scope).
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient() {
		return false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient() {
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient() {
		return true;
	}
}

// Load the classes under test.
$base = dirname( __DIR__ ) . '/includes/';
require_once $base . 'class-std-helpers.php';
require_once $base . 'class-std-settings.php';
require_once $base . 'class-std-geoip.php';
require_once $base . 'class-std-mitigation.php';
require_once $base . 'class-std-firewall.php';

/**
 * Reset the static settings cache so each test sees fresh option values.
 */
function std_test_reset_settings_cache() {
	$ref  = new ReflectionProperty( 'STD_Settings', 'cache' );
	$ref->setAccessible( true );
	$ref->setValue( null, null );
}

/**
 * Reset all in-memory state between tests (options + caches + $_SERVER geo).
 */
function std_test_reset() {
	std_test_reset_options();
	std_test_reset_settings_cache();
	foreach ( array( 'HTTP_CF_IPCOUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_GEO_COUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_GEOIP_COUNTRY_CODE' ) as $h ) {
		unset( $_SERVER[ $h ] );
	}
}
