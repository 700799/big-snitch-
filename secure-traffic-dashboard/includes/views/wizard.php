<?php
/**
 * Quick-start wizard shown on the dashboard until the admin dismisses it.
 * Purely informational + a dismiss action handled via AJAX (std_complete_wizard).
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="std-wizard" id="std-wizard">
	<button type="button" class="std-wizard-close" id="std-wizard-dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'secure-traffic-dashboard' ); ?>">
		<span class="dashicons dashicons-no-alt"></span>
	</button>
	<h2><span class="dashicons dashicons-superhero"></span> <?php esc_html_e( 'Quick start', 'secure-traffic-dashboard' ); ?></h2>
	<p><?php esc_html_e( 'Welcome to SecureTraffic Dashboard. Here is how to get protected in three steps:', 'secure-traffic-dashboard' ); ?></p>
	<ol class="std-wizard-steps">
		<li>
			<strong><?php esc_html_e( '1. Watch your traffic', 'secure-traffic-dashboard' ); ?></strong>
			<?php esc_html_e( 'The plugin starts in monitor-only mode. Browse the Traffic and Login Attempts tabs over a day or two to see what is normal for your site.', 'secure-traffic-dashboard' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( '2. Tune the rules', 'secure-traffic-dashboard' ); ?></strong>
			<?php
			printf(
				/* translators: %s: settings page URL. */
				wp_kses_post( __( 'In <a href="%s">Settings</a>, whitelist your own IP, set your logging sensitivity, and review the brute-force thresholds.', 'secure-traffic-dashboard' ) ),
				esc_url( admin_url( 'admin.php?page=secure-traffic-dashboard-settings' ) )
			);
			?>
		</li>
		<li>
			<strong><?php esc_html_e( '3. Turn on enforcement', 'secure-traffic-dashboard' ); ?></strong>
			<?php esc_html_e( 'Once you are confident, switch off monitor-only mode so the firewall actively blocks malicious requests. Your before/after impact will be tracked automatically.', 'secure-traffic-dashboard' ); ?>
		</li>
	</ol>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=secure-traffic-dashboard-settings' ) ); ?>">
			<?php esc_html_e( 'Open Settings', 'secure-traffic-dashboard' ); ?>
		</a>
		<button type="button" class="button" id="std-wizard-dismiss-2"><?php esc_html_e( 'Got it, hide this', 'secure-traffic-dashboard' ); ?></button>
	</p>
</div>
