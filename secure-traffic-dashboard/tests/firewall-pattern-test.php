<?php
/**
 * Tests for STD_Firewall::is_bad_pattern signature matching.
 *
 * @package SecureTraffic_Dashboard
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers STD_Firewall::is_bad_pattern
 */
class Firewall_Pattern_Test extends TestCase {

	public function test_detects_sql_injection() {
		$this->assertTrue( STD_Firewall::is_bad_pattern( '/?id=1 UNION SELECT password FROM users' ) );
		$this->assertTrue( STD_Firewall::is_bad_pattern( '/?q=select x from information_schema.tables' ) );
	}

	public function test_detects_traversal_and_file_probes() {
		$this->assertTrue( STD_Firewall::is_bad_pattern( '/../../etc/passwd' ) );
		$this->assertTrue( STD_Firewall::is_bad_pattern( '/wp-config.php~' ) );
		$this->assertTrue( STD_Firewall::is_bad_pattern( '/.git/config' ) );
	}

	public function test_detects_xss() {
		$this->assertTrue( STD_Firewall::is_bad_pattern( '/?x=<script>alert(1)</script>' ) );
		$this->assertTrue( STD_Firewall::is_bad_pattern( '/?u=javascript:alert(1)' ) );
	}

	public function test_allows_normal_requests() {
		$this->assertFalse( STD_Firewall::is_bad_pattern( '/2026/06/my-post/' ) );
		$this->assertFalse( STD_Firewall::is_bad_pattern( '/wp-json/wp/v2/posts?per_page=10' ) );
		$this->assertFalse( STD_Firewall::is_bad_pattern( '/shop/?orderby=price&select=size' ) );
	}
}
