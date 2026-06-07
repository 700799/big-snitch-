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
			'dashicons-shield-alt',
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
			__( 'Settings', 'secure-traffic-dashboard' ),
			__( 'Settings', 'secure-traffic-dashboard' ),
			$cap,
			'secure-traffic-dashboard-settings',
			array( $this, 'render_settings' )
		);
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
		// Only load on our pages.
		if ( false === strpos( $hook, 'secure-traffic-dashboard' ) ) {
			return;
		}

		// Vendored libraries (bundled locally, no CDN). Tables are rendered by
		// the plugin's own lightweight AJAX code, so no DataTables dependency.
		wp_enqueue_style( 'std-leaflet', STD_PLUGIN_URL . 'assets/vendor/leaflet.css', array(), '1.9.4' );

		wp_enqueue_script( 'std-chartjs', STD_PLUGIN_URL . 'assets/vendor/chart.min.js', array(), '4.4.1', true );
		wp_enqueue_script( 'std-leaflet', STD_PLUGIN_URL . 'assets/vendor/leaflet.js', array(), '1.9.4', true );

		// Plugin assets.
		wp_enqueue_style( 'std-admin', STD_PLUGIN_URL . 'assets/css/admin.css', array(), STD_VERSION );
		wp_enqueue_script(
			'std-admin',
			STD_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'std-chartjs', 'std-leaflet' ),
			STD_VERSION,
			true
		);

		// Localised config: AJAX URL, nonce and i18n strings.
		wp_localize_script(
			'std-admin',
			'STD_DATA',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'std_ajax' ),
				'monitor'    => (bool) STD_Settings::get( 'monitor_only' ),
				'i18n'       => array(
					'confirmBlock'   => __( 'Block this IP address?', 'secure-traffic-dashboard' ),
					'confirmUnblock' => __( 'Remove this block?', 'secure-traffic-dashboard' ),
					'loading'        => __( 'Loading…', 'secure-traffic-dashboard' ),
					'noData'         => __( 'No data for this period.', 'secure-traffic-dashboard' ),
					'error'          => __( 'Something went wrong. Please try again.', 'secure-traffic-dashboard' ),
					'blocked'        => __( 'Blocked', 'secure-traffic-dashboard' ),
					'allowed'        => __( 'Allowed', 'secure-traffic-dashboard' ),
					'success'        => __( 'Success', 'secure-traffic-dashboard' ),
					'failed'         => __( 'Failed', 'secure-traffic-dashboard' ),
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
		$summary      = STD_Metrics::get_summary();
		$impact       = STD_Metrics::get_impact();
		$active_blocks = STD_Mitigation::get_active_block_rows( 200 );
		$settings     = STD_Settings::get_settings();
		$show_wizard  = ! get_option( 'std_wizard_complete' );

		// Determine the active tab from the query string.
		$allowed_tabs = apply_filters(
			'std_dashboard_tabs',
			array( 'overview', 'traffic', 'logins', 'analytics', 'mitigation', 'reports' )
		);
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
}
