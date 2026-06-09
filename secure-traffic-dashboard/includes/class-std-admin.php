<?php
/**
 * Admin UI: registers the menu, enqueues assets and renders the dashboard /
 * settings pages by including the relevant view partials.
 *
 * Views are presentation-only; all data is prepared here or fetched via AJAX,
 * and every dynamic value is escaped at output time inside the views.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin controller.
 */
class STD_Admin {

	/**
	 * Admin page hook suffix, stored so we only enqueue assets on our screens.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( STD_Settings::class, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . STD_PLUGIN_BASENAME, array( $this, 'action_links' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
	}

	/**
	 * Branded SVG menu icon as a base64 data-URI (no external image request).
	 *
	 * A simple shield glyph that inherits the admin menu colour via
	 * `fill="currentColor"` rendering through WordPress's data-URI handling.
	 *
	 * @return string
	 */
	private function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">'
			. '<path fill="black" d="M10 1L3 4v5c0 4.4 3 8.5 7 10 4-1.5 7-5.6 7-10V4l-7-3z'
			. 'm0 2.2l5 2.1V9c0 3.3-2.1 6.5-5 7.8C7.1 15.5 5 12.3 5 9V5.3l5-2.1z'
			. 'M9 7v3H8l2 3 2-3h-1V7H9z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- data-URI, not obfuscation.
	}

	/**
	 * Capability used to gate the menu/pages.
	 *
	 * @return string
	 */
	private function cap() {
		return current_user_can( STD_CAPABILITY ) ? STD_CAPABILITY : 'manage_options';
	}

	/**
	 * Register the top-level menu and sub-pages.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap = $this->cap();

		$this->hook_suffix = add_menu_page(
			__( 'Security Dashboard', 'secure-traffic-dashboard' ),
			__( 'Security Dashboard', 'secure-traffic-dashboard' ),
			$cap,
			'secure-traffic-dashboard',
			array( $this, 'render_dashboard' ),
			$this->menu_icon(),
			3
		);

		add_submenu_page(
			'secure-traffic-dashboard',
			__( 'Dashboard', 'secure-traffic-dashboard' ),
			__( 'Dashboard', 'secure-traffic-dashboard' ),
			$cap,
			'secure-traffic-dashboard',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'secure-traffic-dashboard',
			__( 'Report', 'secure-traffic-dashboard' ),
			__( 'Report', 'secure-traffic-dashboard' ),
			$cap,
			'secure-traffic-dashboard-report',
			array( $this, 'render_report' )
		);

		add_submenu_page(
			'secure-traffic-dashboard',
			__( 'Settings', 'secure-traffic-dashboard' ),
			__( 'Settings', 'secure-traffic-dashboard' ),
			$cap,
			'secure-traffic-dashboard-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Register the "At a glance" widget on the main WordPress dashboard.
	 *
	 * @return void
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( $this->cap() ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'std_glance_widget',
			__( 'SecureTraffic — At a glance', 'secure-traffic-dashboard' ),
			array( $this, 'render_glance_widget' )
		);
	}

	/**
	 * Render the WordPress dashboard "At a glance" widget.
	 *
	 * @return void
	 */
	public function render_glance_widget() {
		$summary = STD_Metrics::get_summary();
		require STD_PLUGIN_DIR . 'includes/views/widget-glance.php';
	}

	/**
	 * Add a quick "Settings" link on the Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url  = admin_url( 'admin.php?page=secure-traffic-dashboard-settings' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'secure-traffic-dashboard' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * Enqueue CSS/JS and vendored libraries on our admin screens only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// The in-house chart module is needed both on our screens and on the
		// main WP dashboard (for the "At a glance" sparkline).
		$is_our_screen = ( false !== strpos( $hook, 'secure-traffic-dashboard' ) );
		$is_wp_dash    = ( 'index.php' === $hook );

		if ( ! $is_our_screen && ! $is_wp_dash ) {
			return;
		}

		// The "At a glance" widget needs only the stylesheet for its layout.
		wp_enqueue_style( 'std-admin', STD_PLUGIN_URL . 'assets/css/admin.css', array(), STD_VERSION );

		if ( $is_wp_dash && ! $is_our_screen ) {
			return;
		}

		// In-house, dependency-free visualization modules (no third-party code).
		wp_enqueue_script( 'std-charts', STD_PLUGIN_URL . 'assets/js/std-charts.js', array(), STD_VERSION, true );
		wp_enqueue_script( 'std-geomap', STD_PLUGIN_URL . 'assets/js/std-geomap.js', array(), STD_VERSION, true );
		wp_enqueue_script(
			'std-admin',
			STD_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'std-charts', 'std-geomap' ),
			STD_VERSION,
			true
		);

		// Localised config: AJAX URL, nonce and i18n strings.
		wp_localize_script(
			'std-admin',
			'STD_DATA',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'std_ajax' ),
				'monitor' => (bool) STD_Settings::get( 'monitor_only' ),
				'i18n'    => array(
					'confirmBlock'   => __( 'Block this IP address?', 'secure-traffic-dashboard' ),
					'confirmUnblock' => __( 'Remove this block?', 'secure-traffic-dashboard' ),
					'loading'        => __( 'Loading…', 'secure-traffic-dashboard' ),
					'noData'         => __( 'No data for this period.', 'secure-traffic-dashboard' ),
					'error'          => __( 'Something went wrong. Please try again.', 'secure-traffic-dashboard' ),
					'blocked'        => __( 'Blocked', 'secure-traffic-dashboard' ),
					'allowed'        => __( 'Allowed', 'secure-traffic-dashboard' ),
					'success'        => __( 'Success', 'secure-traffic-dashboard' ),
					'failed'         => __( 'Failed', 'secure-traffic-dashboard' ),
					'next'           => __( 'Next', 'secure-traffic-dashboard' ),
					'finish'         => __( 'Finish', 'secure-traffic-dashboard' ),
				),
			)
		);
	}

	/**
	 * Render the main dashboard page (tabbed shell).
	 *
	 * @return void
	 */
	public function render_dashboard() {
		if ( ! current_user_can( $this->cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'secure-traffic-dashboard' ) );
		}

		// Data prepared for the server-rendered portions of the views.
		$summary       = STD_Metrics::get_summary();
		$impact        = STD_Metrics::get_impact();
		$active_blocks = STD_Mitigation::get_active_block_rows( 200 );
		$settings      = STD_Settings::get_settings();
		$health        = STD_Health::checks();
		$show_wizard   = ! get_option( 'std_wizard_complete' );

		// Determine the active tab from the query string.
		$allowed_tabs = apply_filters(
			'std_dashboard_tabs',
			array( 'overview', 'traffic', 'logins', 'analytics', 'mitigation', 'reports', 'status' )
		);
		// Read-only tab switch (no state change); value is sanitized and then
		// validated against the allow-list below, so no nonce is required.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
			$active_tab = 'overview';
		}

		require STD_PLUGIN_DIR . 'includes/views/dashboard.php';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings() {
		if ( ! current_user_can( $this->cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'secure-traffic-dashboard' ) );
		}

		$settings = STD_Settings::get_settings();
		require STD_PLUGIN_DIR . 'includes/views/settings.php';
	}

	/**
	 * Render the branded printable report page.
	 *
	 * @return void
	 */
	public function render_report() {
		if ( ! current_user_can( $this->cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'secure-traffic-dashboard' ) );
		}

		$summary = STD_Metrics::get_summary();
		$impact  = STD_Metrics::get_impact();
		$top_ips = STD_Logger::top( 'traffic', 'ip', 7 * DAY_IN_SECONDS, 10 );
		$top_cc  = STD_Logger::top( 'traffic', 'country', 7 * DAY_IN_SECONDS, 10 );
		$top_url = STD_Logger::top( 'traffic', 'request_uri', 7 * DAY_IN_SECONDS, 10 );

		require STD_PLUGIN_DIR . 'includes/views/report-print.php';
	}
}
