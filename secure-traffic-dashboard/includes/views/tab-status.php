<?php
/**
 * Status tab: overall security posture score and a checklist of prioritized,
 * actionable recommendations.
 *
 * @var array $health Result of STD_Health::checks() (score + items).
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$score = isset( $health['score'] ) ? (int) $health['score'] : 0;
$items = isset( $health['items'] ) ? $health['items'] : array();

// Score ring colour band.
if ( $score >= 80 ) {
	$band = 'good';
} elseif ( $score >= 50 ) {
	$band = 'warn';
} else {
	$band = 'bad';
}

$status_meta = array(
	'good' => array( 'dashicons-yes-alt', __( 'Good', 'secure-traffic-dashboard' ) ),
	'warn' => array( 'dashicons-warning', __( 'Review', 'secure-traffic-dashboard' ) ),
	'bad'  => array( 'dashicons-dismiss', __( 'Action needed', 'secure-traffic-dashboard' ) ),
	'info' => array( 'dashicons-info', __( 'Info', 'secure-traffic-dashboard' ) ),
);
?>
<div class="std-grid-2">
	<div class="std-panel std-score-panel">
		<div class="std-score std-score-<?php echo esc_attr( $band ); ?>" style="--std-score: <?php echo esc_attr( $score ); ?>;">
			<span class="std-score-num"><?php echo esc_html( $score ); ?></span>
			<span class="std-score-unit">/100</span>
		</div>
		<h3><?php esc_html_e( 'Security posture', 'secure-traffic-dashboard' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'A quick assessment of your current configuration. Resolve the items on the right to raise your score.', 'secure-traffic-dashboard' ); ?>
		</p>
	</div>

	<div class="std-panel">
		<h3><?php esc_html_e( 'Recommendations', 'secure-traffic-dashboard' ); ?></h3>
		<ul class="std-health-list">
			<?php foreach ( $items as $item ) : ?>
				<?php
				$st   = isset( $item['status'] ) ? $item['status'] : 'info';
				$icon = isset( $status_meta[ $st ] ) ? $status_meta[ $st ][0] : 'dashicons-info';
				$lbl  = isset( $status_meta[ $st ] ) ? $status_meta[ $st ][1] : '';
				?>
				<li class="std-health-<?php echo esc_attr( $st ); ?>">
					<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
					<div class="std-health-body">
						<strong><?php echo esc_html( $item['label'] ); ?>
							<span class="std-health-tag"><?php echo esc_html( $lbl ); ?></span>
						</strong>
						<span class="std-health-advice"><?php echo esc_html( $item['advice'] ); ?></span>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=secure-traffic-dashboard-settings' ) ); ?>">
			<?php esc_html_e( 'Open Settings', 'secure-traffic-dashboard' ); ?>
		</a>
	</div>
</div>
