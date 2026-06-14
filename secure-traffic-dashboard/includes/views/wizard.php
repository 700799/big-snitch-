<?php
/**
 * Multi-step quick-start wizard shown on the dashboard until dismissed.
 * Steps are toggled client-side (admin.js); completion is persisted via the
 * std_complete_wizard AJAX action.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_url = admin_url( 'admin.php?page=secure-traffic-dashboard-settings' );

$steps = array(
	array(
		'icon'  => 'dashicons-visibility',
		'title' => __( 'Watch your traffic', 'secure-traffic-dashboard' ),
		'body'  => __( 'SecureTraffic starts in monitor-only mode — it logs everything but blocks nothing. Spend a day or two on the Traffic and Login Attempts tabs to learn what normal looks like for your site.', 'secure-traffic-dashboard' ),
	),
	array(
		'icon'  => 'dashicons-admin-network',
		'title' => __( 'Whitelist your own IP', 'secure-traffic-dashboard' ),
		'body'  => __( 'Before enabling enforcement, add your own IP address to the whitelist in Settings so you can never accidentally lock yourself out.', 'secure-traffic-dashboard' ),
	),
	array(
		'icon'  => 'dashicons-shield',
		'title' => __( 'Turn on enforcement', 'secure-traffic-dashboard' ),
		'body'  => __( 'When you are confident, switch off monitor-only mode so the firewall actively blocks malicious requests. Your before/after impact is tracked automatically and shown in Reports.', 'secure-traffic-dashboard' ),
	),
	array(
		'icon'  => 'dashicons-chart-area',
		'title' => __( 'Review your posture', 'secure-traffic-dashboard' ),
		'body'  => __( 'Check the Status tab for a security score and prioritized recommendations, and enable the scheduled email digest to stay informed automatically.', 'secure-traffic-dashboard' ),
	),
);
$total = count( $steps );
?>
<div class="std-wizard" id="std-wizard">
	<button type="button" class="std-wizard-close" aria-label="<?php esc_attr_e( 'Dismiss', 'secure-traffic-dashboard' ); ?>">
		<span class="dashicons dashicons-no-alt"></span>
	</button>

	<div class="std-wizard-progress">
		<span class="std-wizard-progress-bar"></span>
	</div>

	<div class="std-wizard-body">
		<?php foreach ( $steps as $index => $step ) : ?>
			<div class="std-wizard-step<?php echo 0 === $index ? ' is-active' : ''; ?>" data-step="<?php echo esc_attr( $index ); ?>">
				<span class="std-wizard-icon dashicons <?php echo esc_attr( $step['icon'] ); ?>"></span>
				<h2>
					<?php
					printf(
						/* translators: 1: current step number, 2: total steps. */
						esc_html__( 'Step %1$d of %2$d', 'secure-traffic-dashboard' ),
						(int) $index + 1,
						(int) $total
					);
					?>
				</h2>
				<h3><?php echo esc_html( $step['title'] ); ?></h3>
				<p><?php echo esc_html( $step['body'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="std-wizard-foot">
		<div class="std-wizard-dots">
			<?php for ( $i = 0; $i < $total; $i++ ) : ?>
				<span<?php echo 0 === $i ? ' class="is-on"' : ''; ?>></span>
			<?php endfor; ?>
		</div>
		<div class="std-wizard-actions">
			<a class="button" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Open Settings', 'secure-traffic-dashboard' ); ?></a>
			<button type="button" class="button std-wizard-prev" disabled><?php esc_html_e( 'Back', 'secure-traffic-dashboard' ); ?></button>
			<button type="button" class="button button-primary std-wizard-next"><?php esc_html_e( 'Next', 'secure-traffic-dashboard' ); ?></button>
		</div>
	</div>
</div>
