<?php
/**
 * Branded, printable security report. Uses the browser's print pipeline
 * ("Print → Save as PDF") so no PDF library is required.
 *
 * @var array $summary Summary metrics.
 * @var array $impact  Before/after impact.
 * @var array $top_ips Top source IPs (objects: label,total).
 * @var array $top_cc  Top countries (objects: label,total).
 * @var array $top_url Top endpoints (objects: label,total).
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$generated = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
?>
<div class="wrap std-wrap std-report">
	<div class="std-report-toolbar no-print">
		<h1 class="std-title">
			<span class="dashicons dashicons-analytics"></span>
			<?php esc_html_e( 'Security Report', 'secure-traffic-dashboard' ); ?>
		</h1>
		<button type="button" class="button button-primary" onclick="window.print();">
			<span class="dashicons dashicons-printer"></span>
			<?php esc_html_e( 'Print / Save as PDF', 'secure-traffic-dashboard' ); ?>
		</button>
	</div>

	<div class="std-report-doc">
		<header class="std-report-head">
			<div>
				<h2><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
				<p class="std-report-sub"><?php echo esc_html( home_url() ); ?></p>
			</div>
			<div class="std-report-meta">
				<strong><?php esc_html_e( 'SecureTraffic Dashboard', 'secure-traffic-dashboard' ); ?></strong><br />
				<?php
				printf(
					/* translators: %s: date/time the report was generated. */
					esc_html__( 'Generated: %s', 'secure-traffic-dashboard' ),
					esc_html( $generated )
				);
				?>
			</div>
		</header>

		<h3><?php esc_html_e( 'Summary', 'secure-traffic-dashboard' ); ?></h3>
		<table class="widefat std-report-table">
			<tbody>
				<tr><th><?php esc_html_e( 'Requests (24h)', 'secure-traffic-dashboard' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $summary['requests_24h'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Requests (7d)', 'secure-traffic-dashboard' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $summary['requests_7d'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Blocked (24h)', 'secure-traffic-dashboard' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $summary['blocked_24h'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Blocked (all time)', 'secure-traffic-dashboard' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $summary['blocked_total'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Failed logins (24h)', 'secure-traffic-dashboard' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $summary['logins_failed_24h'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Successful logins (24h)', 'secure-traffic-dashboard' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $summary['logins_ok_24h'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Active blocks', 'secure-traffic-dashboard' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $summary['active_blocks'] ) ); ?></td></tr>
			</tbody>
		</table>

		<?php if ( $impact['before'] > 0 ) : ?>
			<h3><?php esc_html_e( 'Mitigation impact', 'secure-traffic-dashboard' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: 1: before, 2: after, 3: percent. */
					esc_html__( 'Failed logins per 7 days fell from %1$s to %2$s — a %3$s%% reduction.', 'secure-traffic-dashboard' ),
					'<strong>' . esc_html( number_format_i18n( $impact['before'] ) ) . '</strong>',
					'<strong>' . esc_html( number_format_i18n( $impact['after'] ) ) . '</strong>',
					'<strong>' . esc_html( $impact['reduction'] ) . '</strong>'
				);
				?>
			</p>
		<?php endif; ?>

		<div class="std-report-cols">
			<?php
			$blocks = array(
				array( __( 'Top source IPs', 'secure-traffic-dashboard' ), $top_ips, false ),
				array( __( 'Top countries', 'secure-traffic-dashboard' ), $top_cc, true ),
				array( __( 'Most targeted endpoints', 'secure-traffic-dashboard' ), $top_url, false ),
			);
			foreach ( $blocks as $block ) :
				list( $heading, $rows, $is_cc ) = $block;
				?>
				<div class="std-report-col">
					<h3><?php echo esc_html( $heading ); ?></h3>
					<table class="widefat std-report-table">
						<tbody>
							<?php if ( empty( $rows ) ) : ?>
								<tr><td><?php esc_html_e( 'No data.', 'secure-traffic-dashboard' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $rows as $row ) : ?>
									<tr>
										<th>
											<?php
											if ( $is_cc ) {
												echo esc_html( STD_Helpers::country_flag( $row->label ) . ' ' . $row->label );
											} else {
												echo esc_html( $row->label );
											}
											?>
										</th>
										<td><?php echo esc_html( number_format_i18n( (int) $row->total ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>
		</div>

		<footer class="std-report-foot">
			<?php
			printf(
				/* translators: %s: plugin version. */
				esc_html__( 'Produced by SecureTraffic Dashboard v%s', 'secure-traffic-dashboard' ),
				esc_html( STD_VERSION )
			);
			?>
		</footer>
	</div>
</div>
