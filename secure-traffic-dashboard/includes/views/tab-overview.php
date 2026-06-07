<?php
/**
 * Overview tab: summary metric cards plus a 24h activity chart.
 *
 * @var array $summary Summary metrics.
 * @var array $impact  Before/after impact figures.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cards = array(
	array(
		'icon'  => 'dashicons-networking',
		'label' => __( 'Requests (24h)', 'secure-traffic-dashboard' ),
		'value' => (int) $summary['requests_24h'],
		'tip'   => __( 'Total inbound requests logged in the last 24 hours.', 'secure-traffic-dashboard' ),
	),
	array(
		'icon'  => 'dashicons-dismiss',
		'label' => __( 'Blocked (24h)', 'secure-traffic-dashboard' ),
		'value' => (int) $summary['blocked_24h'],
		'tip'   => __( 'Requests matched by a firewall rule or block list in the last 24 hours.', 'secure-traffic-dashboard' ),
		'class' => 'std-card-danger',
	),
	array(
		'icon'  => 'dashicons-lock',
		'label' => __( 'Failed Logins (24h)', 'secure-traffic-dashboard' ),
		'value' => (int) $summary['logins_failed_24h'],
		'tip'   => __( 'Unsuccessful login attempts in the last 24 hours.', 'secure-traffic-dashboard' ),
		'class' => 'std-card-warn',
	),
	array(
		'icon'  => 'dashicons-yes-alt',
		'label' => __( 'Successful Logins (24h)', 'secure-traffic-dashboard' ),
		'value' => (int) $summary['logins_ok_24h'],
		'tip'   => __( 'Successful logins in the last 24 hours.', 'secure-traffic-dashboard' ),
		'class' => 'std-card-ok',
	),
	array(
		'icon'  => 'dashicons-shield',
		'label' => __( 'Active Blocks', 'secure-traffic-dashboard' ),
		'value' => (int) $summary['active_blocks'],
		'tip'   => __( 'IP and country rules currently enforced.', 'secure-traffic-dashboard' ),
	),
	array(
		'icon'  => 'dashicons-chart-bar',
		'label' => __( 'Blocked (all time)', 'secure-traffic-dashboard' ),
		'value' => (int) $summary['blocked_total'],
		'tip'   => __( 'Total blocked events recorded since installation.', 'secure-traffic-dashboard' ),
	),
);
?>
<div class="std-cards">
	<?php foreach ( $cards as $card ) : ?>
		<div class="std-card <?php echo esc_attr( $card['class'] ?? '' ); ?>" title="<?php echo esc_attr( $card['tip'] ); ?>">
			<span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>"></span>
			<div class="std-card-body">
				<span class="std-card-value"><?php echo esc_html( number_format_i18n( $card['value'] ) ); ?></span>
				<span class="std-card-label"><?php echo esc_html( $card['label'] ); ?></span>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<?php if ( $impact['before'] > 0 ) : ?>
	<div class="std-impact-banner">
		<span class="dashicons dashicons-chart-line"></span>
		<?php
		printf(
			/* translators: 1: before count, 2: after count, 3: percent reduction. */
			esc_html__( 'Failed logins before mitigation: %1$s → after: %2$s (%3$s%% reduction).', 'secure-traffic-dashboard' ),
			'<strong>' . esc_html( number_format_i18n( $impact['before'] ) ) . '</strong>',
			'<strong>' . esc_html( number_format_i18n( $impact['after'] ) ) . '</strong>',
			'<strong>' . esc_html( $impact['reduction'] ) . '</strong>'
		);
		?>
	</div>
<?php endif; ?>

<div class="std-panel">
	<h3><?php esc_html_e( 'Activity (last 24 hours)', 'secure-traffic-dashboard' ); ?></h3>
	<canvas id="std-overview-chart" height="90"
		data-std-chart="overview" data-range="24h"></canvas>
</div>
