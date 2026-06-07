<?php
/**
 * Traffic tab: searchable/filterable inbound request log loaded via AJAX, plus
 * an explanatory note on outbound monitoring limitations.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="std-panel">
	<div class="std-toolbar">
		<h3><?php esc_html_e( 'Inbound Traffic', 'secure-traffic-dashboard' ); ?></h3>
		<div class="std-toolbar-actions">
			<input type="search" class="std-search" data-table="traffic"
				placeholder="<?php esc_attr_e( 'Search IP, URL, agent…', 'secure-traffic-dashboard' ); ?>" />
			<label class="std-filter">
				<input type="checkbox" class="std-filter-blocked" data-table="traffic" />
				<?php esc_html_e( 'Blocked only', 'secure-traffic-dashboard' ); ?>
			</label>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=std_export&table=traffic&format=csv' ), 'std_export' ) ); ?>">
				<span class="dashicons dashicons-media-spreadsheet"></span> <?php esc_html_e( 'CSV', 'secure-traffic-dashboard' ); ?>
			</a>
		</div>
	</div>

	<table class="widefat striped std-log-table" data-table="traffic">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'IP', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Origin', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Method', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'URL', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Status', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'User Agent', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'State', 'secure-traffic-dashboard' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr class="std-loading-row"><td colspan="8"><?php esc_html_e( 'Loading…', 'secure-traffic-dashboard' ); ?></td></tr>
		</tbody>
	</table>

	<div class="std-pagination" data-table="traffic"></div>
</div>

<div class="std-panel std-note">
	<h3><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'About outbound connections', 'secure-traffic-dashboard' ); ?></h3>
	<p>
		<?php esc_html_e( 'WordPress (PHP) can only observe outbound HTTP requests that it makes itself through its HTTP API (for example, plugin update checks and REST callbacks). These are logged above with an "OUTBOUND" prefix when outbound logging is enabled.', 'secure-traffic-dashboard' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'Arbitrary outbound traffic from other processes or raw sockets is invisible to PHP. For complete outbound visibility, use server-level logging such as your firewall/iptables logs, a reverse proxy (Nginx/Cloudflare) access log, or a host-based monitoring agent.', 'secure-traffic-dashboard' ); ?>
	</p>
</div>
