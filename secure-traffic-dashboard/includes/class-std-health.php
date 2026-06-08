<?php
/**
 * Security posture self-assessment. Evaluates the current configuration and
 * environment and returns prioritized, actionable recommendations shown on the
 * Status tab.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Health / posture checks.
 */
class STD_Health {

	/**
	 * Run all checks and return a result set with an overall score.
	 *
	 * @return array {
	 *     @type int   $score  0-100 posture score.
	 *     @type array $items  List of check results (status, label, advice).
	 * }
	 */
	public static function checks() {
		$s     = STD_Settings::get_settings();
		$items = array();

		// Enforcement vs monitor-only.
		$items[] = self::item(
			empty( $s['monitor_only'] ) ? 'good' : 'warn',
			__( 'Active enforcement', 'secure-traffic-dashboard' ),
			empty( $s['monitor_only'] )
				? __( 'The firewall is actively blocking malicious requests.', 'secure-traffic-dashboard' )
				: __( 'Monitor-only mode is on — nothing is being blocked yet. Disable it in Settings once your rules are tuned.', 'secure-traffic-dashboard' )
		);

		// Firewall enabled.
		$items[] = self::item(
			! empty( $s['firewall_enabled'] ) ? 'good' : 'bad',
			__( 'Firewall', 'secure-traffic-dashboard' ),
			! empty( $s['firewall_enabled'] )
				? __( 'The request firewall is enabled.', 'secure-traffic-dashboard' )
				: __( 'The firewall is disabled. Enable it in Settings to evaluate incoming requests.', 'secure-traffic-dashboard' )
		);

		// Brute-force protection.
		$items[] = self::item(
			! empty( $s['bruteforce_enabled'] ) ? 'good' : 'warn',
			__( 'Brute-force protection', 'secure-traffic-dashboard' ),
			! empty( $s['bruteforce_enabled'] )
				? __( 'Repeated failed logins trigger a temporary lockout.', 'secure-traffic-dashboard' )
				: __( 'Brute-force protection is off. Turn it on to limit password-guessing attacks.', 'secure-traffic-dashboard' )
		);

		// Whitelist your own IP.
		$has_wl  = '' !== trim( (string) $s['whitelist_ips'] );
		$items[] = self::item(
			$has_wl ? 'good' : 'warn',
			__( 'Admin IP whitelist', 'secure-traffic-dashboard' ),
			$has_wl
				? __( 'At least one trusted IP is whitelisted and can never be blocked.', 'secure-traffic-dashboard' )
				: __( 'No IPs are whitelisted. Add your own IP in Settings so you cannot be locked out.', 'secure-traffic-dashboard' )
		);

		// Geolocation source.
		$geo_ok  = ! empty( $s['geoip_enabled'] );
		$items[] = self::item(
			$geo_ok ? 'good' : 'warn',
			__( 'Geolocation', 'secure-traffic-dashboard' ),
			$geo_ok
				? __( 'Geolocation is enabled (offline headers first, optional external provider).', 'secure-traffic-dashboard' )
				: __( 'Geolocation is off, so origin country data will be missing.', 'secure-traffic-dashboard' )
		);

		// Retention configured.
		$items[] = self::item(
			(int) $s['retention_days'] > 0 ? 'good' : 'warn',
			__( 'Log retention', 'secure-traffic-dashboard' ),
			(int) $s['retention_days'] > 0
				/* translators: %d: number of days. */
				? sprintf( __( 'Old events are auto-purged after %d days.', 'secure-traffic-dashboard' ), (int) $s['retention_days'] )
				: __( 'Retention is set to keep logs forever, which can grow the database. Consider setting a limit.', 'secure-traffic-dashboard' )
		);

		// HTTPS in use.
		$items[] = self::item(
			is_ssl() ? 'good' : 'warn',
			__( 'HTTPS', 'secure-traffic-dashboard' ),
			is_ssl()
				? __( 'The admin area is served over HTTPS.', 'secure-traffic-dashboard' )
				: __( 'This page is not using HTTPS. Serve your site over TLS to protect credentials.', 'secure-traffic-dashboard' )
		);

		// File editing disabled.
		$file_edit_off = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
		$items[]       = self::item(
			$file_edit_off ? 'good' : 'warn',
			__( 'Dashboard file editing', 'secure-traffic-dashboard' ),
			$file_edit_off
				? __( 'Theme/plugin file editing is disabled in wp-admin.', 'secure-traffic-dashboard' )
				: __( 'Consider defining DISALLOW_FILE_EDIT in wp-config.php to block in-dashboard code edits.', 'secure-traffic-dashboard' )
		);

		// Email alerts.
		$items[] = self::item(
			! empty( $s['alerts_enabled'] ) ? 'good' : 'info',
			__( 'Email alerts', 'secure-traffic-dashboard' ),
			! empty( $s['alerts_enabled'] )
				? __( 'You will be emailed when failed-login activity spikes.', 'secure-traffic-dashboard' )
				: __( 'Email alerts are off. Enable them to be notified of attacks in real time.', 'secure-traffic-dashboard' )
		);

		/**
		 * Filter the list of health-check items.
		 *
		 * @param array $items    Check results.
		 * @param array $settings Current settings.
		 */
		$items = apply_filters( 'std_health_checks', $items, $s );

		// Score: good = 1, info = 1 (neutral), warn = 0.5, bad = 0.
		$weights = array(
			'good' => 1,
			'info' => 1,
			'warn' => 0.5,
			'bad'  => 0,
		);
		$total   = 0;
		$count   = 0;
		foreach ( $items as $it ) {
			if ( 'info' === $it['status'] ) {
				continue; // Informational items don't affect the score.
			}
			$total += $weights[ $it['status'] ];
			++$count;
		}
		$score = $count > 0 ? (int) round( ( $total / $count ) * 100 ) : 100;

		return array(
			'score' => $score,
			'items' => $items,
		);
	}

	/**
	 * Build a single check item.
	 *
	 * @param string $status good|warn|bad|info.
	 * @param string $label  Short label.
	 * @param string $advice Description / advice.
	 * @return array
	 */
	private static function item( $status, $label, $advice ) {
		return array(
			'status' => $status,
			'label'  => $label,
			'advice' => $advice,
		);
	}
}
