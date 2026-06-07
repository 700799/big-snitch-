<?php
/**
 * Login attempts tab: searchable/filterable log of successful and failed
 * logins with geolocation, loaded via AJAX.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="std-panel">
	<div class="std-toolbar">
		<h3><?php esc_html_e( 'Login Attempts', 'secure-traffic-dashboard' ); ?></h3>
		<div class="std-toolbar-actions">
			<input type="search" class="std-search" data-table="logins"
				placeholder="<?php esc_attr_e( 'Search IP, username…', 'secure-traffic-dashboard' ); ?>" />
			<select class="std-filter-success" data-table="logins">
				<option value=""><?php esc_html_e( 'All results', 'secure-traffic-dashboard' ); ?></option>
				<option value="0"><?php esc_html_e( 'Failed only', 'secure-traffic-dashboard' ); ?></option>
				<option value="1"><?php esc_html_e( 'Successful only', 'secure-traffic-dashboard' ); ?></option>
			</select>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=std_export&table=logins&format=csv' ), 'std_export' ) ); ?>">
				<span class="dashicons dashicons-media-spreadsheet"></span> <?php esc_html_e( 'CSV', 'secure-traffic-dashboard' ); ?>
			</a>
		</div>
	</div>

	<table class="widefat striped std-log-table" data-table="logins">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'IP', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Origin', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Username', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Result', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'User Agent', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Action', 'secure-traffic-dashboard' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr class="std-loading-row"><td colspan="7"><?php esc_html_e( 'Loading…', 'secure-traffic-dashboard' ); ?></td></tr>
		</tbody>
	</table>

	<div class="std-pagination" data-table="logins"></div>
</div>
