<?php
/**
 * Tests for STD_Settings sanitization.
 *
 * @package SecureTraffic_Dashboard
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers STD_Settings
 */
class Settings_Test extends TestCase {

	protected function setUp(): void {
		std_test_reset();
	}

	public function test_checkboxes_default_to_zero_when_absent() {
		$clean = STD_Settings::sanitize( array() );
		$this->assertSame( 0, $clean['monitor_only'] );
		$this->assertSame( 0, $clean['firewall_enabled'] );
		$this->assertSame( 0, $clean['geoip_trust_headers'] );
	}

	public function test_checkboxes_become_one_when_present() {
		$clean = STD_Settings::sanitize( array( 'monitor_only' => 'on', 'firewall_enabled' => '1' ) );
		$this->assertSame( 1, $clean['monitor_only'] );
		$this->assertSame( 1, $clean['firewall_enabled'] );
	}

	public function test_enum_falls_back_to_default_on_bad_value() {
		$clean = STD_Settings::sanitize( array( 'sensitivity' => 'bogus', 'geoip_provider' => 'evil' ) );
		$this->assertSame( 'medium', $clean['sensitivity'] );
		$this->assertSame( 'headers', $clean['geoip_provider'] );
	}

	public function test_enum_accepts_valid_values() {
		$clean = STD_Settings::sanitize( array(
			'sensitivity'      => 'high',
			'geoip_provider'   => 'ip-api',
			'digest_frequency' => 'weekly',
		) );
		$this->assertSame( 'high', $clean['sensitivity'] );
		$this->assertSame( 'ip-api', $clean['geoip_provider'] );
		$this->assertSame( 'weekly', $clean['digest_frequency'] );
	}

	public function test_integer_floors_enforced() {
		$clean = STD_Settings::sanitize( array(
			'login_max_attempts' => '0',
			'login_window'       => '5',
			'login_lockout'      => '10',
		) );
		$this->assertSame( 1, $clean['login_max_attempts'] );
		$this->assertSame( 30, $clean['login_window'] );
		$this->assertSame( 60, $clean['login_lockout'] );
	}

	public function test_email_sanitization() {
		$clean = STD_Settings::sanitize( array( 'alert_email' => 'ok@example.com', 'digest_email' => 'bad-email' ) );
		$this->assertSame( 'ok@example.com', $clean['alert_email'] );
		$this->assertSame( '', $clean['digest_email'] );
	}

	public function test_ip_list_drops_invalid_keeps_cidr() {
		$clean = STD_Settings::sanitize( array(
			'whitelist_ips' => "203.0.113.5\nnot-an-ip\n10.0.0.0/8",
		) );
		$this->assertStringContainsString( '203.0.113.5', $clean['whitelist_ips'] );
		$this->assertStringContainsString( '10.0.0.0/8', $clean['whitelist_ips'] );
		$this->assertStringNotContainsString( 'not-an-ip', $clean['whitelist_ips'] );
	}

	public function test_country_list_uppercases_and_validates() {
		$clean = STD_Settings::sanitize( array( 'blocked_countries' => "ru\nUSA\nde" ) );
		$this->assertStringContainsString( 'RU', $clean['blocked_countries'] );
		$this->assertStringContainsString( 'DE', $clean['blocked_countries'] );
		$this->assertStringNotContainsString( 'USA', $clean['blocked_countries'] );
	}
}
