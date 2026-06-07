<?php
/**
 * Settings storage, defaults, and the WordPress Settings API registration.
 *
 * All configuration lives in a single option array (std_settings) to minimise
 * row lookups. Reads go through get_settings() which merges stored values over
 * the defaults so newly-introduced keys always have a value.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configuration manager.
 */
class STD_Settings {

	/**
	 * Option name under which the settings array is stored.
	 */
	const OPTION = 'std_settings';

	/**
	 * Settings API group / page slug.
	 */
	const GROUP = 'std_settings_group';

	/**
	 * In-request cache of the merged settings array.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default settings. Conservative defaults: monitor-only is ON so the plugin
	 * never blocks legitimate traffic until the admin opts in.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Master / mode.
			'monitor_only'        => 1,   // 1 = log only, never block.
			'log_traffic'         => 1,
			'log_logins'          => 1,
			'log_outbound'        => 1,

			// Sensitivity: low|medium|high. Higher levels log more (incl. assets).
			'sensitivity'         => 'medium',

			// Retention in days for raw events (0 = keep forever).
			'retention_days'      => 30,

			// Firewall.
			'firewall_enabled'    => 1,
			'block_bad_patterns'  => 1,
			'bruteforce_enabled'  => 1,
			'login_max_attempts'  => 5,
			'login_window'        => 300,   // seconds.
			'login_lockout'       => 900,   // seconds.

			// GeoIP.
			'geoip_enabled'       => 1,
			'geoip_provider'      => 'ip-api',  // ip-api|maxmind.
			'geoip_api_key'       => '',

			// Network / proxy.
			'trust_proxy'         => 0,
			'proxy_header'        => 'X-Forwarded-For',

			// Whitelists (newline-separated, parsed into arrays on read).
			'whitelist_ips'       => '',
			'whitelist_countries' => '',
			'blocked_countries'   => '',

			// Email alerts.
			'alerts_enabled'      => 0,
			'alert_threshold'     => 50,    // failed logins per hour.
			'alert_email'         => '',

			// Cleanup.
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * Return the merged settings array (defaults overlaid with stored values).
	 *
	 * @return array
	 */
	public static function get_settings() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION, false );

		if ( false === $stored ) {
			// First read: persist the defaults so other code (and the Settings
			// API) has a concrete row to work with.
			$stored = self::defaults();
			add_option( self::OPTION, $stored );
		}

		self::$cache = wp_parse_args( $stored, self::defaults() );

		return self::$cache;
	}

	/**
	 * Convenience accessor for a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback if the key is missing.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::get_settings();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Parse a newline/comma separated textarea value into a clean array.
	 *
	 * @param string $raw Raw textarea content.
	 * @return array
	 */
	public static function parse_list( $raw ) {
		$items = preg_split( '/[\r\n,]+/', (string) $raw );
		$items = array_filter( array_map( 'trim', (array) $items ) );
		return array_values( array_unique( $items ) );
	}

	/**
	 * Register the setting and its sanitization callback with the Settings API.
	 *
	 * Field rendering is handled by the settings view rather than add_settings_field
	 * so the layout can be grouped into tabs/cards.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize the full settings array submitted from the settings form.
	 *
	 * Every value is explicitly cast/validated; unknown keys are dropped.
	 *
	 * @param array $input Raw $_POST data for the option.
	 * @return array Clean settings array.
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$clean    = array();

		// Checkboxes (stored as 1/0).
		$checkboxes = array(
			'monitor_only',
			'log_traffic',
			'log_logins',
			'log_outbound',
			'firewall_enabled',
			'block_bad_patterns',
			'bruteforce_enabled',
			'geoip_enabled',
			'trust_proxy',
			'alerts_enabled',
			'delete_on_uninstall',
		);
		foreach ( $checkboxes as $cb ) {
			$clean[ $cb ] = empty( $input[ $cb ] ) ? 0 : 1;
		}

		// Enumerated values.
		$clean['sensitivity'] = in_array( $input['sensitivity'] ?? '', array( 'low', 'medium', 'high' ), true )
			? $input['sensitivity']
			: $defaults['sensitivity'];

		$clean['geoip_provider'] = in_array( $input['geoip_provider'] ?? '', array( 'ip-api', 'maxmind' ), true )
			? $input['geoip_provider']
			: $defaults['geoip_provider'];

		// Integers (with sane floors).
		$clean['retention_days']     = max( 0, absint( $input['retention_days'] ?? $defaults['retention_days'] ) );
		$clean['login_max_attempts'] = max( 1, absint( $input['login_max_attempts'] ?? $defaults['login_max_attempts'] ) );
		$clean['login_window']       = max( 30, absint( $input['login_window'] ?? $defaults['login_window'] ) );
		$clean['login_lockout']      = max( 60, absint( $input['login_lockout'] ?? $defaults['login_lockout'] ) );
		$clean['alert_threshold']    = max( 1, absint( $input['alert_threshold'] ?? $defaults['alert_threshold'] ) );

		// Strings.
		$clean['geoip_api_key'] = sanitize_text_field( $input['geoip_api_key'] ?? '' );
		$clean['proxy_header']  = sanitize_text_field( $input['proxy_header'] ?? $defaults['proxy_header'] );
		$clean['alert_email']   = sanitize_email( $input['alert_email'] ?? '' );

		// Textareas (kept as text; parsed into arrays at read time).
		$clean['whitelist_ips']       = self::sanitize_list_field( $input['whitelist_ips'] ?? '', 'ip' );
		$clean['whitelist_countries'] = self::sanitize_list_field( $input['whitelist_countries'] ?? '', 'cc' );
		$clean['blocked_countries']   = self::sanitize_list_field( $input['blocked_countries'] ?? '', 'cc' );

		// Reset the in-request cache so subsequent reads see the new values.
		self::$cache = null;

		/**
		 * Filter the sanitized settings before they are saved.
		 *
		 * @param array $clean Sanitized settings.
		 * @param array $input Raw input.
		 */
		return apply_filters( 'std_sanitize_settings', $clean, $input );
	}

	/**
	 * Sanitize a textarea list, keeping only valid IPs/CIDRs or country codes.
	 *
	 * @param string $raw  Raw textarea value.
	 * @param string $type 'ip' or 'cc' (country code).
	 * @return string Newline-joined clean values.
	 */
	private static function sanitize_list_field( $raw, $type ) {
		$items = self::parse_list( $raw );
		$valid = array();

		foreach ( $items as $item ) {
			if ( 'cc' === $type ) {
				$item = strtoupper( $item );
				if ( 2 === strlen( $item ) && ctype_alpha( $item ) ) {
					$valid[] = $item;
				}
			} else {
				// Accept plain IPs and simple CIDR notation.
				$ip = strpos( $item, '/' ) !== false ? strstr( $item, '/', true ) : $item;
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					$valid[] = $item;
				}
			}
		}

		return implode( "\n", array_unique( $valid ) );
	}
}
