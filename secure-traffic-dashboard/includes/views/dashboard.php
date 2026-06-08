<?php
/**
 * Dashboard shell view: header, quick-start wizard, tab navigation and the
 * active tab's partial.
 *
 * Variables provided by STD_Admin::render_dashboard():
 *
 * @var array $summary       Summary metrics.
 * @var array $impact        Before/after impact figures.
 * @var array $active_blocks Active block rows.
 * @var array $settings      Plugin settings.
 * @var bool  $show_wizard   Whether to show the quick-start wizard.
 * @var array $allowed_tabs  Tab slugs.
 * @var string $active_tab   Current tab slug.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_url = admin_url( 'admin.php?page=secure-traffic-dashboard' );

$tab_labels = array(
	'overview'   => array( 'dashicons-dashboard', __( 'Overview', 'secure-traffic-dashboard' ) ),
	'traffic'    => array( 'dashicons-networking', __( 'Traffic', 'secure-traffic-dashboard' ) ),
	'logins'     => array( 'dashicons-admin-users', __( 'Login Attempts', 'secure-traffic-dashboard' ) ),
	'analytics'  => array( 'dashicons-chart-area', __( 'Analytics', 'secure-traffic-dashboard' ) ),
	'mitigation' => array( 'dashicons-shield', __( 'Mitigation', 'secure-traffic-dashboard' ) ),
	'reports'    => array( 'dashicons-analytics', __( 'Reports', 'secure-traffic-dashboard' ) ),
	'status'     => array( 'dashicons-heart', __( 'Status', 'secure-traffic-dashboard' ) ),
);
?>
<div class="wrap std-wrap">

	<h1 class="std-title">
		<span class="dashicons dashicons-shield-alt"></span>
		<?php esc_html_e( 'SecureTraffic Dashboard', 'secure-traffic-dashboard' ); ?>
	</h1>

	<?php if ( ! empty( $settings['monitor_only'] ) ) : ?>
		<div class="notice notice-info std-mode-notice">
			<p>
				<span class="dashicons dashicons-visibility"></span>
				<strong><?php esc_html_e( 'Monitor-only mode is active.', 'secure-traffic-dashboard' ); ?></strong>
				<?php esc_html_e( 'Events are logged but no requests are blocked. Disable monitor-only mode in Settings once you are confident the rules are tuned correctly.', 'secure-traffic-dashboard' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $show_wizard ) : ?>
		<?php require STD_PLUGIN_DIR . 'includes/views/wizard.php'; ?>
	<?php endif; ?>

	<h2 class="nav-tab-wrapper std-tabs">
		<?php foreach ( $allowed_tabs as $tab ) : ?>
			<?php
			$icon  = isset( $tab_labels[ $tab ][0] ) ? $tab_labels[ $tab ][0] : 'dashicons-admin-generic';
			$label = isset( $tab_labels[ $tab ][1] ) ? $tab_labels[ $tab ][1] : ucfirst( $tab );
			$url   = add_query_arg( 'tab', $tab, $base_url );
			$class = ( $tab === $active_tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>">
				<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</h2>

	<div class="std-tab-content" data-active-tab="<?php echo esc_attr( $active_tab ); ?>">
		<?php
		// Load the active tab partial. Each is presentation-only and escapes
		// its own output. Custom tabs added via the std_dashboard_tabs filter
		// can hook std_render_tab_{slug} to render their content.
		$partial = STD_PLUGIN_DIR . 'includes/views/tab-' . $active_tab . '.php';
		if ( file_exists( $partial ) ) {
			require $partial;
		} else {
			/**
			 * Render a custom dashboard tab registered via std_dashboard_tabs.
			 *
			 * @param array $settings Plugin settings.
			 */
			do_action( 'std_render_tab_' . $active_tab, $settings );
		}
		?>
	</div>

	<p class="std-footer">
		<?php
		printf(
			/* translators: %s: plugin version. */
			esc_html__( 'SecureTraffic Dashboard v%s', 'secure-traffic-dashboard' ),
			esc_html( STD_VERSION )
		);
		?>
	</p>
</div>
