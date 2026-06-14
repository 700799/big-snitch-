<?php
// Minimal POT generator: scans the plugin source for translation calls with
// our text domain and emits a gettext template. (Production sites would use
// `wp i18n make-pot`; this keeps the committed template current without wp-cli.)
$domain = 'secure-traffic-dashboard';
$root   = $argv[1];
$rii    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
$strings = array(); // msgid => true ; or "ctx\x04id" ; plurals handled separately.
$plurals = array();
foreach ( $rii as $file ) {
	if ( $file->getExtension() !== 'php' ) continue;
	$path = $file->getPathname();
	if ( strpos( $path, '/vendor/' ) !== false || strpos( $path, '/tests/' ) !== false ) continue;
	$code = file_get_contents( $path );
	// __('x','domain'), esc_html__, esc_attr__, _e, esc_html_e, esc_attr_e
	if ( preg_match_all( '/\b(?:__|esc_html__|esc_attr__|_e|esc_html_e|esc_attr_e)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*,\s*([\'"])' . preg_quote($domain,'/') . '\3\s*\)/s', $code, $m ) ) {
		foreach ( $m[2] as $s ) { $strings[ stripcslashes_keepquotes($s) ] = true; }
	}
	// _x('x','ctx','domain')
	if ( preg_match_all( '/\b(?:_x|esc_html_x|esc_attr_x)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*,\s*([\'"])((?:\\\\.|(?!\3).)*)\3\s*,\s*([\'"])' . preg_quote($domain,'/') . '\5\s*\)/s', $code, $m ) ) {
		foreach ( $m[2] as $i => $s ) { $strings[ $m[4][$i] . "\x04" . $s ] = true; }
	}
	// _n('single','plural',n,'domain')
	if ( preg_match_all( '/\b_n\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*,\s*([\'"])((?:\\\\.|(?!\3).)*)\3\s*,.*?,\s*([\'"])' . preg_quote($domain,'/') . '\5\s*\)/s', $code, $m ) ) {
		foreach ( $m[2] as $i => $s ) { $plurals[ $s ] = $m[4][$i]; }
	}
}
function stripcslashes_keepquotes($s){ return str_replace(array('\\\'','\\"','\\n','\\t'), array("'",'"',"\n","\t"), $s); }
function esc_po($s){ return str_replace(array("\\","\"","\n"), array("\\\\","\\\"","\\n"), $s); }
ksort( $strings );
$out  = "# Copyright (C) " . date('Y') . " SecureTraffic\n";
$out .= "# This file is distributed under the GPL-2.0-or-later license.\n";
$out .= 'msgid ""' . "\n";
$out .= 'msgstr ""' . "\n";
$out .= '"Project-Id-Version: SecureTraffic Dashboard 1.1.0\n"' . "\n";
$out .= '"Report-Msgid-Bugs-To: https://example.com/support\n"' . "\n";
$out .= '"MIME-Version: 1.0\n"' . "\n";
$out .= '"Content-Type: text/plain; charset=UTF-8\n"' . "\n";
$out .= '"Content-Transfer-Encoding: 8bit\n"' . "\n";
$out .= '"POT-Creation-Date: ' . gmdate('Y-m-d H:i') . "+0000\\n\"\n";
$out .= '"Language-Team: SecureTraffic\n"' . "\n";
$out .= '"X-Domain: ' . $domain . '\n"' . "\n\n";
foreach ( array_keys( $strings ) as $key ) {
	if ( strpos( $key, "\x04" ) !== false ) {
		list($ctx,$id) = explode("\x04",$key,2);
		$out .= 'msgctxt "' . esc_po($ctx) . '"' . "\n";
		$out .= 'msgid "' . esc_po($id) . '"' . "\n";
	} else {
		$out .= 'msgid "' . esc_po($key) . '"' . "\n";
	}
	$out .= 'msgstr ""' . "\n\n";
}
foreach ( $plurals as $s => $p ) {
	$out .= 'msgid "' . esc_po($s) . '"' . "\n";
	$out .= 'msgid_plural "' . esc_po($p) . '"' . "\n";
	$out .= 'msgstr[0] ""' . "\n" . 'msgstr[1] ""' . "\n\n";
}
file_put_contents( $argv[2], $out );
echo "Wrote " . count($strings) . " strings + " . count($plurals) . " plurals\n";
