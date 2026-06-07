=== SecureTraffic Dashboard ===
Contributors: securetraffic
Tags: security, firewall, login security, traffic, monitoring, brute force, geoip, logging
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A unified security dashboard to monitor inbound traffic and login attempts, geolocate origins, apply mitigations, and measure the before/after impact.

== Description ==

SecureTraffic Dashboard gives you a single, clean admin screen (WP Admin → Security Dashboard) to understand who is hitting your site and to act on threats.

**Core features**

* **Unified dashboard** with tabs: Overview, Traffic, Login Attempts, Analytics, Mitigation, Reports.
* **Inbound traffic logging** – IP, method, URL, user agent, status code, referer, with searchable/filterable, paginated tables.
* **Login attempt logging** – successful and failed logins, usernames tried, IPs, timestamps and geolocation.
* **Frequency analytics** – charts for the last 24h / 7 days / 30 days, plus top IPs, top countries and most-targeted endpoints, and a world map of origins.
* **Geolocation** – pluggable GeoIP with a free ip-api.com default (no key) or optional MaxMind GeoLite2 via API key. Lookups are cached for up to a month.
* **Mitigation & firewall** – block IPs (temporary/permanent), block countries, brute-force lockout, rate limiting, and conservative bad-pattern rules (SQLi/XSS/traversal). One-click block/unblock from the dashboard.
* **Recommendations panel** – actionable breach-avoidance advice (2FA, strong passwords, updates, hiding the login URL, Cloudflare, backups).
* **Before & after impact** – tracks failed logins before vs. after you enable mitigation and shows the percentage reduction.
* **Exportable reports** – download traffic, logins and blocks as CSV or JSON.
* **Email alerts** – get notified when failed-login activity exceeds a threshold.
* **Monitor-only mode (default)** – logs everything but blocks nothing until you are confident, keeping false positives at zero.

**Performance & safety**

* Lightweight single-row inserts at request time; heavy aggregation runs on WP-Cron and is cached in transients.
* Configurable log retention with automatic daily purge.
* Sensitivity levels to control how much is logged and limit database growth.
* All database access uses prepared statements; all output is escaped; all AJAX is nonce- and capability-protected and rate-limited.

== Installation ==

1. Upload the `secure-traffic-dashboard` folder to `/wp-content/plugins/`, or install the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** screen. Custom database tables are created automatically on activation.
3. Go to **Security Dashboard** in the admin menu. A quick-start wizard walks you through the first steps.
4. (Recommended) Open **Security Dashboard → Settings**, whitelist your own IP address, and review the firewall thresholds.
5. Leave **Monitor-only mode** on for a day or two to learn what normal traffic looks like, then turn it off to enable active blocking.

**GeoIP setup**

* The default provider, **ip-api.com**, works immediately with no configuration (free, rate-limited).
* For higher accuracy, create a free **MaxMind GeoLite2** account at maxmind.com, generate a license key, and enter it in Settings as `accountId:licenseKey` while selecting the MaxMind provider.

== Frequently Asked Questions ==

= Does this replace Wordfence / Sucuri / other security plugins? =
It complements them. SecureTraffic Dashboard focuses on visibility (traffic + login analytics) plus lightweight mitigation. Running two firewalls at once can cause duplicate logging or conflicting blocks — see "Potential conflicts" below.

= Can it monitor outbound connections? =
PHP can only see outbound HTTP requests that WordPress itself makes (e.g. update checks), which are logged when outbound logging is enabled. Arbitrary outbound traffic from other processes is invisible to PHP; use server-level logging (firewall/iptables, reverse-proxy access logs, or a host agent) for complete outbound visibility.

= Will it lock me out? =
Monitor-only mode is on by default, so nothing is blocked until you opt in. Always whitelist your own IP in Settings before enabling enforcement. Whitelisted IPs are never blocked.

= Where is data stored, and what happens on uninstall? =
Events live in four custom tables (`{prefix}std_traffic`, `std_logins`, `std_blocks`, `std_metrics`). On uninstall, data is **kept** unless you enable "Delete all plugin data" in Settings, in which case tables, options, transients and the custom capability are removed.

= Potential conflicts =
* Other security plugins (Wordfence, iThemes, Sucuri) may also hook `wp_login_failed`/`authenticate`; expect overlapping logs and consider running only one enforcement layer.
* Caching/CDN layers may mask the real client IP — enable "Trust proxy" and set the correct forwarding header (e.g. `CF-Connecting-IP` for Cloudflare).
* "Hide login URL" plugins change where login events originate but logging still works.

== Testing steps ==

1. Activate the plugin and confirm the **Security Dashboard** menu appears and the four tables exist.
2. Log out and submit a wrong password a few times; confirm rows appear under **Login Attempts** (Failed).
3. Browse several front-end pages; confirm rows appear under **Traffic** and the Overview/Analytics charts populate.
4. On **Mitigation**, block a test IP (not your own). With monitor-only off, confirm that IP receives a 403.
5. Export CSV and JSON from **Reports** and verify the downloads.
6. Deactivate the plugin: scheduled jobs are cleared but data is retained. Reactivate to confirm history persists.
7. With "Delete all plugin data" enabled, delete the plugin and confirm the tables/options are removed.

== Changelog ==

= 1.0.0 =
* Initial release: dashboard, traffic & login logging, GeoIP, analytics, firewall/mitigation, before/after reports, CSV/JSON export, email alerts, quick-start wizard.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Third-party libraries ==

This plugin bundles the following open-source libraries locally (no external CDN):

* Chart.js 4.4.1 — MIT License — https://www.chartjs.org/
* Leaflet 1.9.4 — BSD-2-Clause License — https://leafletjs.com/

Map tiles are served by OpenStreetMap (https://www.openstreetmap.org/copyright) only when you open the Analytics map.
