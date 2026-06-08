<?php
/**
 * Tests for STD_GeoIP::header_country (offline header-based geolocation).
 *
 * @package SecureTraffic_Dashboard
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers STD_GeoIP::header_country
 */
class GeoIP_Headers_Test extends TestCase {

	protected function setUp(): void {
		std_test_reset();
	}

	public function test_reads_cloudflare_header() {
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'de';
		$this->assertSame( 'DE', STD_GeoIP::header_country() );
	}

	public function test_reads_cloudfront_header() {
		$_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] = 'FR';
		$this->assertSame( 'FR', STD_GeoIP::header_country() );
	}

	public function test_ignores_placeholder_values() {
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'XX';
		$this->assertSame( '', STD_GeoIP::header_country() );

		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'T1';
		$this->assertSame( '', STD_GeoIP::header_country() );
	}

	public function test_returns_empty_without_headers() {
		$this->assertSame( '', STD_GeoIP::header_country() );
	}

	public function test_respects_disabled_setting() {
		update_option( 'std_settings', array( 'geoip_trust_headers' => 0 ) );
		std_test_reset_settings_cache();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';
		$this->assertSame( '', STD_GeoIP::header_country() );
	}
}
