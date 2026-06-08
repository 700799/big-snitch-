<?php
/**
 * Tests for STD_Mitigation IP whitelist + CIDR matching.
 *
 * @package SecureTraffic_Dashboard
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers STD_Mitigation::is_whitelisted_ip
 */
class Mitigation_CIDR_Test extends TestCase {

	protected function setUp(): void {
		std_test_reset();
	}

	private function set_whitelist( $value ) {
		update_option( 'std_settings', array( 'whitelist_ips' => $value ) );
		std_test_reset_settings_cache();
	}

	public function test_exact_match() {
		$this->set_whitelist( "203.0.113.7" );
		$this->assertTrue( STD_Mitigation::is_whitelisted_ip( '203.0.113.7' ) );
		$this->assertFalse( STD_Mitigation::is_whitelisted_ip( '203.0.113.8' ) );
	}

	public function test_cidr_range_match() {
		$this->set_whitelist( "10.0.0.0/8" );
		$this->assertTrue( STD_Mitigation::is_whitelisted_ip( '10.5.6.7' ) );
		$this->assertFalse( STD_Mitigation::is_whitelisted_ip( '11.0.0.1' ) );
	}

	public function test_cidr_24_boundaries() {
		$this->set_whitelist( "192.168.1.0/24" );
		$this->assertTrue( STD_Mitigation::is_whitelisted_ip( '192.168.1.1' ) );
		$this->assertTrue( STD_Mitigation::is_whitelisted_ip( '192.168.1.254' ) );
		$this->assertFalse( STD_Mitigation::is_whitelisted_ip( '192.168.2.1' ) );
	}

	public function test_empty_whitelist_matches_nothing() {
		$this->set_whitelist( '' );
		$this->assertFalse( STD_Mitigation::is_whitelisted_ip( '203.0.113.7' ) );
	}
}
