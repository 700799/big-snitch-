<?php
/**
 * Reports tab: before/after security impact and exportable reports.
 *
 * @var array $impact   Before/after impact figures.
 * @var array $summary  Summary metrics.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$export = function ( $table, $format ) {
	return wp_nonce_url(
		admin_url( 'admin-post.php?action=std_export&table=' . $table . '&format=' . $format ),
		'std_export'
	);
};
?>
<div class="std-panel">
	<h3><?php esc_html_e( 'Security impact: before & after', 'secure-traffic-dashboard' ); ?></h3>

	<?php if ( $impact['before'] > 0 ) : ?>
		<div class="std-impact-grid">
			<div class="std-impact-box">
				<span class="std-impact-num"><?php echo esc_html( number_format_i18n( $impact['before'] ) ); ?></span>
				<span class="std-impact-cap"><?php esc_html_e( 'Failed logins / 7d before mitigation', 'secure-traffic-dashboard' ); ?></span>
			</div>
			<div class="std-impact-arrow dashicons dashicons-arrow-right-alt"></div>
			<div class="std-impact-box">
				<span class="std-impact-num"><?php echo esc_html( number_format_i18n( $impact['after'] ) ); ?></span>
				<span class="std-impact-cap"><?php esc_html_e( 'Failed logins / 7d now', 'secure-traffic-dashboard' ); ?></span>
			</div>
			<div class="std-impact-box std-impact-result">
				<span class="std-impact-num"><?php echo esc_html( $impact['reduction'] ); ?>%</span>
				<span class="std-impact-cap"><?php esc_html_e( 'Reduction', 'secure-traffic-dashboard' ); ?></span>
			</div>
		</div>
		<?php if ( $impact['since'] ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: baseline capture date. */
					esc_html__( 'Baseline captured on %s, when mitigation was first enabled.', 'secure-traffic-dashboard' ),
					esc_html( $impact['since'] )
				);
				?>
			</p>
		<?php endif; ?>
	<?php else : ?>
		<p>
			<?php esc_html_e( 'No baseline yet. The before/after comparison is captured automatically the first time you apply a mitigation (block an IP/country or disable monitor-only mode).', 'secure-traffic-dashboard' ); ?>
		</p>
	<?php endif; ?>
</div>

<div class="std-panel">
	<h3><?php esc_html_e( 'Export reports', 'secure-traffic-dashboard' ); ?></h3>
	<p><?php esc_html_e( 'Download your logs for offline analysis or compliance records.', 'secure-traffic-dashboard' ); ?></p>
	<table class="widefat striped" style="max-width:640px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Data set', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'CSV', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'JSON', 'secure-traffic-dashboard' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php esc_html_e( 'Inbound traffic', 'secure-traffic-dashboard' ); ?></td>
				<td><a class="button" href="<?php echo esc_url( $export( 'traffic', 'csv' ) ); ?>"><?php esc_html_e( 'Download', 'secure-traffic-dashboard' ); ?></a></td>
				<td><a class="button" href="<?php echo esc_url( $export( 'traffic', 'json' ) ); ?>"><?php esc_html_e( 'Download', 'secure-traffic-dashboard' ); ?></a></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Login attempts', 'secure-traffic-dashboard' ); ?></td>
				<td><a class="button" href="<?php echo esc_url( $export( 'logins', 'csv' ) ); ?>"><?php esc_html_e( 'Download', 'secure-traffic-dashboard' ); ?></a></td>
				<td><a class="button" href="<?php echo esc_url( $export( 'logins', 'json' ) ); ?>"><?php esc_html_e( 'Download', 'secure-traffic-dashboard' ); ?></a></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Blocks', 'secure-traffic-dashboard' ); ?></td>
				<td><a class="button" href="<?php echo esc_url( $export( 'blocks', 'csv' ) ); ?>"><?php esc_html_e( 'Download', 'secure-traffic-dashboard' ); ?></a></td>
				<td><a class="button" href="<?php echo esc_url( $export( 'blocks', 'json' ) ); ?>"><?php esc_html_e( 'Download', 'secure-traffic-dashboard' ); ?></a></td>
			</tr>
		</tbody>
	</table>
</div>
