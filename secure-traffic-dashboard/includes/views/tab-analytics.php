<?php
/**
 * Analytics tab: time-series charts, top IPs / countries / endpoints and a
 * world map of request origins. Data is loaded via AJAX and rendered with the
 * plugin's in-house, dependency-free STDCharts and STDGeo canvas modules.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="std-panel">
	<div class="std-toolbar">
		<h3><?php esc_html_e( 'Traffic & Login Analytics', 'secure-traffic-dashboard' ); ?></h3>
		<div class="std-range-switch" role="group" aria-label="<?php esc_attr_e( 'Time range', 'secure-traffic-dashboard' ); ?>">
			<button type="button" class="button std-range active" data-range="24h"><?php esc_html_e( '24h', 'secure-traffic-dashboard' ); ?></button>
			<button type="button" class="button std-range" data-range="7d"><?php esc_html_e( '7 days', 'secure-traffic-dashboard' ); ?></button>
			<button type="button" class="button std-range" data-range="30d"><?php esc_html_e( '30 days', 'secure-traffic-dashboard' ); ?></button>
		</div>
	</div>

	<div class="std-chart-grid">
		<div class="std-chart-box">
			<h4><?php esc_html_e( 'Requests over time', 'secure-traffic-dashboard' ); ?></h4>
			<canvas id="std-analytics-traffic" data-std-chart="traffic" height="120"></canvas>
		</div>
		<div class="std-chart-box">
			<h4><?php esc_html_e( 'Login attempts over time', 'secure-traffic-dashboard' ); ?></h4>
			<canvas id="std-analytics-logins" data-std-chart="logins" height="120"></canvas>
		</div>
	</div>
</div>

<div class="std-panel">
	<div class="std-chart-grid std-three">
		<div class="std-top-box">
			<h4><?php esc_html_e( 'Top IPs', 'secure-traffic-dashboard' ); ?></h4>
			<ol class="std-top-list" id="std-top-ips"></ol>
		</div>
		<div class="std-top-box">
			<h4><?php esc_html_e( 'Top Countries', 'secure-traffic-dashboard' ); ?></h4>
			<ol class="std-top-list" id="std-top-countries"></ol>
		</div>
		<div class="std-top-box">
			<h4><?php esc_html_e( 'Most Targeted Endpoints', 'secure-traffic-dashboard' ); ?></h4>
			<ol class="std-top-list" id="std-top-endpoints"></ol>
		</div>
	</div>
</div>

<div class="std-panel">
	<h3><?php esc_html_e( 'Where requests come from', 'secure-traffic-dashboard' ); ?></h3>
	<div id="std-map" class="std-map"></div>
	<p class="description">
		<?php esc_html_e( 'Bubbles are positioned at each country’s approximate centroid and sized by request volume. Enable GeoIP in Settings for richer location data.', 'secure-traffic-dashboard' ); ?>
	</p>
</div>
