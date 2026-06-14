<?php
/**
 * Tests for STD_Helpers pure utilities.
 *
 * @package SecureTraffic_Dashboard
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers STD_Helpers
 */
class Helpers_Test extends TestCase {

	public function set_up() {}

	public function test_validate_ip_accepts_valid_addresses() {
		$this->assertSame( '203.0.113.5', STD_Helpers::validate_ip( '203.0.113.5' ) );
		$this->assertSame( '2001:db8::1', STD_Helpers::validate_ip( '2001:db8::1' ) );
		$this->assertSame( '8.8.8.8', STD_Helpers::validate_ip( '  8.8.8.8  ' ) );
	}

	public function test_validate_ip_rejects_invalid() {
		$this->assertSame( '', STD_Helpers::validate_ip( 'not-an-ip' ) );
		$this->assertSame( '', STD_Helpers::validate_ip( '999.999.999.999' ) );
		$this->assertSame( '', STD_Helpers::validate_ip( '' ) );
	}

	public function test_country_flag_for_valid_code() {
		$flag = STD_Helpers::country_flag( 'us' );
		// Two regional indicator symbols => 8 bytes in UTF-8.
		$this->assertSame( 8, strlen( $flag ) );
		$this->assertNotSame( '🌐', $flag );
	}

	public function test_country_flag_fallback_for_invalid() {
		$this->assertSame( '🌐', STD_Helpers::country_flag( 'ZZZ' ) );
		$this->assertSame( '🌐', STD_Helpers::country_flag( '1' ) );
		$this->assertSame( '🌐', STD_Helpers::country_flag( '' ) );
	}

	public function test_is_static_asset() {
		$this->assertTrue( STD_Helpers::is_static_asset( '/wp-content/x.css' ) );
		$this->assertTrue( STD_Helpers::is_static_asset( '/a/b/app.js?ver=1' ) );
		$this->assertTrue( STD_Helpers::is_static_asset( '/img/logo.PNG' ) );
		$this->assertFalse( STD_Helpers::is_static_asset( '/some/page/' ) );
		$this->assertFalse( STD_Helpers::is_static_asset( '/?p=12' ) );
	}

	public function test_help_tip_outputs_accessible_markup() {
		$html = STD_Helpers::help_tip( 'Hello <b>world</b>' );
		$this->assertStringContainsString( 'aria-label="Hello world"', $html );
		$this->assertStringContainsString( 'std-help-tip', $html );
		// Tags must be stripped from the tip text.
		$this->assertStringNotContainsString( '<b>', $html );
	}
}
