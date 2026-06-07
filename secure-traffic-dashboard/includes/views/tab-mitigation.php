<?php
/**
 * Mitigation tab: manual block controls, active block list, firewall status
 * and an actionable recommendations panel.
 *
 * @var array $active_blocks Active block rows.
 * @var array $settings      Plugin settings.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="std-grid-2">

	<div class="std-panel">
		<h3><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Block an IP address', 'secure-traffic-dashboard' ); ?></h3>
		<div class="std-form-row">
			<input type="text" id="std-block-ip" placeholder="<?php esc_attr_e( 'e.g. 203.0.113.10', 'secure-traffic-dashboard' ); ?>" />
			<select id="std-block-scope">
				<option value="temp"><?php esc_html_e( 'Temporary (1 hour)', 'secure-traffic-dashboard' ); ?></option>
				<option value="perm"><?php esc_html_e( 'Permanent', 'secure-traffic-dashboard' ); ?></option>
			</select>
			<button type="button" class="button button-primary" id="std-block-ip-btn"><?php esc_html_e( 'Block IP', 'secure-traffic-dashboard' ); ?></button>
		</div>

		<h3><span class="dashicons dashicons-admin-site"></span> <?php esc_html_e( 'Block a country', 'secure-traffic-dashboard' ); ?></h3>
		<div class="std-form-row">
			<input type="text" id="std-block-country" maxlength="2" placeholder="<?php esc_attr_e( 'ISO code, e.g. RU', 'secure-traffic-dashboard' ); ?>" />
			<button type="button" class="button button-primary" id="std-block-country-btn"><?php esc_html_e( 'Block Country', 'secure-traffic-dashboard' ); ?></button>
		</div>

		<p class="description">
			<?php
			if ( ! empty( $settings['monitor_only'] ) ) {
				echo '<span class="std-warn">';
				esc_html_e( 'Note: Monitor-only mode is on, so new blocks are recorded but not enforced until you turn it off in Settings.', 'secure-traffic-dashboard' );
				echo '</span>';
			} else {
				esc_html_e( 'Blocks take effect immediately. Whitelisted IPs/countries are never blocked.', 'secure-traffic-dashboard' );
			}
			?>
		</p>
	</div>

	<div class="std-panel">
		<h3><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Firewall status', 'secure-traffic-dashboard' ); ?></h3>
		<ul class="std-status-list">
			<li class="<?php echo ! empty( $settings['firewall_enabled'] ) ? 'on' : 'off'; ?>">
				<?php esc_html_e( 'Firewall', 'secure-traffic-dashboard' ); ?>
			</li>
			<li class="<?php echo ! empty( $settings['block_bad_patterns'] ) ? 'on' : 'off'; ?>">
				<?php esc_html_e( 'Bad-pattern rules', 'secure-traffic-dashboard' ); ?>
			</li>
			<li class="<?php echo ! empty( $settings['bruteforce_enabled'] ) ? 'on' : 'off'; ?>">
				<?php esc_html_e( 'Brute-force protection', 'secure-traffic-dashboard' ); ?>
			</li>
			<li class="<?php echo empty( $settings['monitor_only'] ) ? 'on' : 'off'; ?>">
				<?php esc_html_e( 'Active enforcement (monitor-only off)', 'secure-traffic-dashboard' ); ?>
			</li>
		</ul>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=secure-traffic-dashboard-settings' ) ); ?>">
			<?php esc_html_e( 'Adjust firewall settings', 'secure-traffic-dashboard' ); ?>
		</a>
	</div>
</div>

<div class="std-panel">
	<h3><?php esc_html_e( 'Active blocks', 'secure-traffic-dashboard' ); ?></h3>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Type', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Value', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Scope', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Reason', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Created', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Expires', 'secure-traffic-dashboard' ); ?></th>
				<th><?php esc_html_e( 'Action', 'secure-traffic-dashboard' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $active_blocks ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No active blocks.', 'secure-traffic-dashboard' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $active_blocks as $block ) : ?>
					<tr>
						<td><?php echo esc_html( $block['block_type'] ); ?></td>
						<td><code><?php echo esc_html( $block['value'] ); ?></code></td>
						<td><?php echo esc_html( $block['scope'] ); ?></td>
						<td><?php echo esc_html( $block['reason'] ); ?></td>
						<td><?php echo esc_html( $block['created'] ); ?></td>
						<td><?php echo esc_html( $block['expires'] ? $block['expires'] : __( 'never', 'secure-traffic-dashboard' ) ); ?></td>
						<td>
							<button type="button" class="button-link std-unblock" data-id="<?php echo esc_attr( $block['id'] ); ?>">
								<?php esc_html_e( 'Remove', 'secure-traffic-dashboard' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<div class="std-panel std-recommendations">
	<h3><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Breach-avoidance recommendations', 'secure-traffic-dashboard' ); ?></h3>
	<ul>
		<li><?php esc_html_e( 'Enable two-factor authentication (2FA) for all administrator accounts.', 'secure-traffic-dashboard' ); ?></li>
		<li><?php esc_html_e( 'Use strong, unique passwords and a password manager; never reuse credentials.', 'secure-traffic-dashboard' ); ?></li>
		<li><?php esc_html_e( 'Keep WordPress core, themes and plugins updated; remove anything unused.', 'secure-traffic-dashboard' ); ?></li>
		<li><?php esc_html_e( 'Hide or rename the default login URL (/wp-login.php) to cut automated brute-force noise.', 'secure-traffic-dashboard' ); ?></li>
		<li><?php esc_html_e( 'Limit login attempts (enabled here) and consider a CAPTCHA on the login form.', 'secure-traffic-dashboard' ); ?></li>
		<li><?php esc_html_e( 'Put your site behind a CDN/WAF such as Cloudflare for edge filtering and DDoS protection.', 'secure-traffic-dashboard' ); ?></li>
		<li><?php esc_html_e( 'Disable file editing in wp-admin (define DISALLOW_FILE_EDIT) and restrict file permissions.', 'secure-traffic-dashboard' ); ?></li>
		<li><?php esc_html_e( 'Take regular off-site backups and test that you can restore them.', 'secure-traffic-dashboard' ); ?></li>
	</ul>
</div>
