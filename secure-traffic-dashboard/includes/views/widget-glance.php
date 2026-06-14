<?php
/**
 * "At a glance" widget shown on the main WordPress dashboard.
 *
 * @var array $summary Summary metrics.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dashboard_url = admin_url( 'admin.php?page=secure-traffic-dashboard' );
?>
<div class="std-glance">
	<ul class="std-glance-list">
		<li>
			<span class="std-glance-num"><?php echo esc_html( number_format_i18n( (int) $summary['requests_24h'] ) ); ?></span>
			<span class="std-glance-cap"><?php esc_html_e( 'Requests (24h)', 'secure-traffic-dashboard' ); ?></span>
		</li>
		<li class="std-glance-danger">
			<span class="std-glance-num"><?php echo esc_html( number_format_i18n( (int) $summary['blocked_24h'] ) ); ?></span>
			<span class="std-glance-cap"><?php esc_html_e( 'Blocked (24h)', 'secure-traffic-dashboard' ); ?></span>
		</li>
		<li class="std-glance-warn">
			<span class="std-glance-num"><?php echo esc_html( number_format_i18n( (int) $summary['logins_failed_24h'] ) ); ?></span>
			<span class="std-glance-cap"><?php esc_html_e( 'Failed logins (24h)', 'secure-traffic-dashboard' ); ?></span>
		</li>
		<li>
			<span class="std-glance-num"><?php echo esc_html( number_format_i18n( (int) $summary['active_blocks'] ) ); ?></span>
			<span class="std-glance-cap"><?php esc_html_e( 'Active blocks', 'secure-traffic-dashboard' ); ?></span>
		</li>
	</ul>
	<p class="std-glance-foot">
		<a href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Open Security Dashboard →', 'secure-traffic-dashboard' ); ?></a>
	</p>
</div>
