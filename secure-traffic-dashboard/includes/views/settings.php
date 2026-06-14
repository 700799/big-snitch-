<?php
/**
 * Settings page view. Renders the single settings array via the WordPress
 * Settings API. Field names use the std_settings[key] convention so the whole
 * array is sanitized by STD_Settings::sanitize().
 *
 * @var array $settings Current settings.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$opt = STD_Settings::OPTION;

/**
 * Helper to render a checkbox bound to a settings key.
 *
 * @param string $key      Settings key.
 * @param string $label    Field label.
 * @param array  $settings Current settings.
 * @param string $opt      Option name.
 * @param string $desc     Optional description.
 */
$checkbox = function ( $key, $label, $settings, $opt, $desc = '' ) {
	?>
	<label class="std-checkbox">
		<input type="checkbox" name="<?php echo esc_attr( $opt . '[' . $key . ']' ); ?>" value="1"
			<?php checked( ! empty( $settings[ $key ] ) ); ?> />
		<?php echo esc_html( $label ); ?>
	</label>
	<?php if ( $desc ) : ?>
		<p class="description"><?php echo esc_html( $desc ); ?></p>
	<?php endif; ?>
	<?php
};
?>
<div class="wrap std-wrap">
	<h1 class="std-title">
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'SecureTraffic Settings', 'secure-traffic-dashboard' ); ?>
	</h1>

	<form method="post" action="options.php">
		<?php settings_fields( STD_Settings::GROUP ); ?>

		<div class="std-panel">
			<h2><?php esc_html_e( 'General & Mode', 'secure-traffic-dashboard' ); ?></h2>
			<?php
			$checkbox( 'monitor_only', __( 'Monitor-only mode (log events but never block)', 'secure-traffic-dashboard' ), $settings, $opt, __( 'Recommended when first installing. Turn off to enforce blocks.', 'secure-traffic-dashboard' ) );
			$checkbox( 'log_traffic', __( 'Log inbound traffic', 'secure-traffic-dashboard' ), $settings, $opt );
			$checkbox( 'log_logins', __( 'Log login attempts', 'secure-traffic-dashboard' ), $settings, $opt );
			$checkbox( 'log_outbound', __( 'Log WordPress outbound HTTP requests', 'secure-traffic-dashboard' ), $settings, $opt );
			?>
			<p>
				<label for="std-sensitivity"><strong><?php esc_html_e( 'Logging sensitivity', 'secure-traffic-dashboard' ); ?></strong></label><br />
				<select id="std-sensitivity" name="<?php echo esc_attr( $opt . '[sensitivity]' ); ?>">
					<option value="low" <?php selected( $settings['sensitivity'], 'low' ); ?>><?php esc_html_e( 'Low (skip assets & admin browsing)', 'secure-traffic-dashboard' ); ?></option>
					<option value="medium" <?php selected( $settings['sensitivity'], 'medium' ); ?>><?php esc_html_e( 'Medium (skip static assets)', 'secure-traffic-dashboard' ); ?></option>
					<option value="high" <?php selected( $settings['sensitivity'], 'high' ); ?>><?php esc_html_e( 'High (log everything)', 'secure-traffic-dashboard' ); ?></option>
				</select>
				<span class="description"><?php esc_html_e( 'Lower sensitivity reduces noise and database growth.', 'secure-traffic-dashboard' ); ?></span>
			</p>
			<p>
				<label for="std-retention"><strong><?php esc_html_e( 'Log retention (days)', 'secure-traffic-dashboard' ); ?></strong></label><br />
				<input type="number" id="std-retention" min="0" name="<?php echo esc_attr( $opt . '[retention_days]' ); ?>"
					value="<?php echo esc_attr( $settings['retention_days'] ); ?>" />
				<span class="description"><?php esc_html_e( '0 = keep forever. Older events are auto-purged daily.', 'secure-traffic-dashboard' ); ?></span>
			</p>
		</div>

		<div class="std-panel">
			<h2><?php esc_html_e( 'Firewall & Brute-force', 'secure-traffic-dashboard' ); ?></h2>
			<?php
			$checkbox( 'firewall_enabled', __( 'Enable firewall', 'secure-traffic-dashboard' ), $settings, $opt );
			$checkbox( 'block_bad_patterns', __( 'Block known bad request patterns (SQLi/XSS/traversal)', 'secure-traffic-dashboard' ), $settings, $opt );
			$checkbox( 'bruteforce_enabled', __( 'Enable brute-force login protection', 'secure-traffic-dashboard' ), $settings, $opt );
			?>
			<p>
				<label><?php esc_html_e( 'Max failed attempts', 'secure-traffic-dashboard' ); ?>
					<input type="number" min="1" name="<?php echo esc_attr( $opt . '[login_max_attempts]' ); ?>" value="<?php echo esc_attr( $settings['login_max_attempts'] ); ?>" />
				</label>
				<label><?php esc_html_e( 'Within window (seconds)', 'secure-traffic-dashboard' ); ?>
					<input type="number" min="30" name="<?php echo esc_attr( $opt . '[login_window]' ); ?>" value="<?php echo esc_attr( $settings['login_window'] ); ?>" />
				</label>
				<label><?php esc_html_e( 'Lockout duration (seconds)', 'secure-traffic-dashboard' ); ?>
					<input type="number" min="60" name="<?php echo esc_attr( $opt . '[login_lockout]' ); ?>" value="<?php echo esc_attr( $settings['login_lockout'] ); ?>" />
				</label>
			</p>
		</div>

		<div class="std-panel">
			<h2><?php esc_html_e( 'GeoIP', 'secure-traffic-dashboard' ); ?></h2>
			<?php $checkbox( 'geoip_enabled', __( 'Enable geolocation lookups', 'secure-traffic-dashboard' ), $settings, $opt ); ?>
			<?php $checkbox( 'geoip_trust_headers', __( 'Use CDN/proxy country headers when available (offline, instant)', 'secure-traffic-dashboard' ), $settings, $opt, __( 'Reads headers like Cloudflare CF-IPCountry or CloudFront-Viewer-Country. No external request, no data to bundle.', 'secure-traffic-dashboard' ) ); ?>
			<p>
				<label for="std-geoprovider"><strong><?php esc_html_e( 'Provider', 'secure-traffic-dashboard' ); ?></strong></label>
				<?php echo STD_Helpers::help_tip( __( 'Offline-first. "Headers only" makes no external requests. The external providers are optional and only used when a country header is not present.', 'secure-traffic-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- help_tip returns escaped HTML. ?>
				<br />
				<select id="std-geoprovider" name="<?php echo esc_attr( $opt . '[geoip_provider]' ); ?>">
					<option value="headers" <?php selected( $settings['geoip_provider'], 'headers' ); ?>><?php esc_html_e( 'Headers only (offline, no external requests)', 'secure-traffic-dashboard' ); ?></option>
					<option value="ip-api" <?php selected( $settings['geoip_provider'], 'ip-api' ); ?>><?php esc_html_e( 'ip-api.com (free external API, no key)', 'secure-traffic-dashboard' ); ?></option>
					<option value="maxmind" <?php selected( $settings['geoip_provider'], 'maxmind' ); ?>><?php esc_html_e( 'MaxMind GeoLite2 (external API key)', 'secure-traffic-dashboard' ); ?></option>
				</select>
			</p>
			<p>
				<label for="std-geokey"><strong><?php esc_html_e( 'MaxMind key (accountId:licenseKey)', 'secure-traffic-dashboard' ); ?></strong></label><br />
				<input type="text" id="std-geokey" class="regular-text" name="<?php echo esc_attr( $opt . '[geoip_api_key]' ); ?>"
					value="<?php echo esc_attr( $settings['geoip_api_key'] ); ?>" autocomplete="off" />
				<span class="description"><?php esc_html_e( 'Only needed for the MaxMind provider. Offline GeoIP databases carry their own license/EULA, so none is bundled — the offline option uses request headers instead.', 'secure-traffic-dashboard' ); ?></span>
			</p>
		</div>

		<div class="std-panel">
			<h2><?php esc_html_e( 'Network & Whitelists', 'secure-traffic-dashboard' ); ?></h2>
			<?php $checkbox( 'trust_proxy', __( 'Trust proxy forwarding header (Cloudflare / load balancer)', 'secure-traffic-dashboard' ), $settings, $opt, __( 'Only enable if your site is genuinely behind a trusted proxy — otherwise client IPs can be spoofed.', 'secure-traffic-dashboard' ) ); ?>
			<p>
				<label for="std-proxyheader"><strong><?php esc_html_e( 'Forwarding header', 'secure-traffic-dashboard' ); ?></strong></label><br />
				<input type="text" id="std-proxyheader" name="<?php echo esc_attr( $opt . '[proxy_header]' ); ?>" value="<?php echo esc_attr( $settings['proxy_header'] ); ?>" />
			</p>
			<div class="std-grid-2">
				<p>
					<label for="std-wl-ips"><strong><?php esc_html_e( 'Whitelisted IPs / CIDRs', 'secure-traffic-dashboard' ); ?></strong></label><br />
					<textarea id="std-wl-ips" rows="4" class="large-text" name="<?php echo esc_attr( $opt . '[whitelist_ips]' ); ?>"><?php echo esc_textarea( $settings['whitelist_ips'] ); ?></textarea>
					<span class="description"><?php esc_html_e( 'One per line. These are never blocked.', 'secure-traffic-dashboard' ); ?></span>
				</p>
				<p>
					<label for="std-wl-cc"><strong><?php esc_html_e( 'Whitelisted countries', 'secure-traffic-dashboard' ); ?></strong></label><br />
					<textarea id="std-wl-cc" rows="4" class="large-text" name="<?php echo esc_attr( $opt . '[whitelist_countries]' ); ?>"><?php echo esc_textarea( $settings['whitelist_countries'] ); ?></textarea>
					<span class="description"><?php esc_html_e( 'ISO-2 codes, one per line.', 'secure-traffic-dashboard' ); ?></span>
				</p>
			</div>
			<p>
				<label for="std-bl-cc"><strong><?php esc_html_e( 'Blocked countries', 'secure-traffic-dashboard' ); ?></strong></label><br />
				<textarea id="std-bl-cc" rows="3" class="large-text" name="<?php echo esc_attr( $opt . '[blocked_countries]' ); ?>"><?php echo esc_textarea( $settings['blocked_countries'] ); ?></textarea>
				<span class="description"><?php esc_html_e( 'ISO-2 codes, one per line. Requires GeoIP and enforcement (monitor-only off).', 'secure-traffic-dashboard' ); ?></span>
			</p>
		</div>

		<div class="std-panel">
			<h2><?php esc_html_e( 'Email Alerts', 'secure-traffic-dashboard' ); ?></h2>
			<?php $checkbox( 'alerts_enabled', __( 'Send email alerts on high failed-login activity', 'secure-traffic-dashboard' ), $settings, $opt ); ?>
			<p>
				<label><?php esc_html_e( 'Threshold (failed logins / hour)', 'secure-traffic-dashboard' ); ?>
					<input type="number" min="1" name="<?php echo esc_attr( $opt . '[alert_threshold]' ); ?>" value="<?php echo esc_attr( $settings['alert_threshold'] ); ?>" />
				</label>
			</p>
			<p>
				<label for="std-alert-email"><strong><?php esc_html_e( 'Alert email', 'secure-traffic-dashboard' ); ?></strong></label><br />
				<input type="email" id="std-alert-email" class="regular-text" name="<?php echo esc_attr( $opt . '[alert_email]' ); ?>"
					value="<?php echo esc_attr( $settings['alert_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
			</p>
		</div>

		<div class="std-panel">
			<h2><?php esc_html_e( 'Scheduled Digest', 'secure-traffic-dashboard' ); ?></h2>
			<p>
				<label for="std-digest-freq"><strong><?php esc_html_e( 'Send a summary report', 'secure-traffic-dashboard' ); ?></strong></label><br />
				<select id="std-digest-freq" name="<?php echo esc_attr( $opt . '[digest_frequency]' ); ?>">
					<option value="off" <?php selected( $settings['digest_frequency'], 'off' ); ?>><?php esc_html_e( 'Off', 'secure-traffic-dashboard' ); ?></option>
					<option value="daily" <?php selected( $settings['digest_frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'secure-traffic-dashboard' ); ?></option>
					<option value="weekly" <?php selected( $settings['digest_frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'secure-traffic-dashboard' ); ?></option>
				</select>
			</p>
			<p>
				<label for="std-digest-email"><strong><?php esc_html_e( 'Digest recipient', 'secure-traffic-dashboard' ); ?></strong></label><br />
				<input type="email" id="std-digest-email" class="regular-text" name="<?php echo esc_attr( $opt . '[digest_email]' ); ?>"
					value="<?php echo esc_attr( $settings['digest_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
				<span class="description"><?php esc_html_e( 'A periodic security summary (totals, top IPs and countries) emailed automatically.', 'secure-traffic-dashboard' ); ?></span>
			</p>
		</div>

		<div class="std-panel">
			<h2><?php esc_html_e( 'Data & Cleanup', 'secure-traffic-dashboard' ); ?></h2>
			<?php $checkbox( 'delete_on_uninstall', __( 'Delete all plugin data when the plugin is deleted', 'secure-traffic-dashboard' ), $settings, $opt, __( 'When off, your logs and settings survive an uninstall so you can reinstall without losing history.', 'secure-traffic-dashboard' ) ); ?>
		</div>

		<?php submit_button( __( 'Save Settings', 'secure-traffic-dashboard' ) ); ?>
	</form>
</div>
