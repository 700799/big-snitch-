=== SecureTraffic Dashboard ===
Contributors: securetraffic
Tags: security, firewall, login security, traffic, monitoring, brute force, geoip, logging
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A unified security dashboard to monitor inbound traffic and login attempts, geolocate origins, apply mitigations, and measure the before/after impact.

== Description ==

SecureTraffic Dashboard gives you a single, clean admin screen (WP Admin → Security Dashboard) to understand who is hitting your site and to act on threats.

**Core features**

* **Zero dependencies** – all charts and the world map are drawn by in-house canvas modules. No third-party libraries, no external CDN, no map tile service.
* **Unified dashboard** with tabs: Overview, Traffic, Login Attempts, Analytics, Mitigation, Reports and Status.
* **Inbound traffic logging** – IP, method, URL, user agent, status code, referer, with searchable/filterable, paginated tables.
* **Login attempt logging** – successful and failed logins, usernames tried, IPs, timestamps and geolocation.
* **Frequency analytics** – in-house charts for the last 24h / 7 days / 30 days, plus top IPs, top countries and most-targeted endpoints, and a self-drawn world map of origins.
* **Offline-first geolocation** – country is read from CDN/proxy headers (Cloudflare, CloudFront, …) with no external request; optional ip-api.com / MaxMind providers are off by default.
* **Mitigation & firewall** – block IPs (temporary/permanent), block countries, brute-force lockout, rate limiting, and conservative bad-pattern rules (SQLi/XSS/traversal). One-click block/unblock from the dashboard.
* **Security posture (Status tab)** – a 0–100 score with prioritized, actionable recommendations.
* **Recommendations panel** – actionable breach-avoidance advice (2FA, strong passwords, updates, hiding the login URL, Cloudflare, backups).
* **Before & after impact** – tracks failed logins before vs. after you enable mitigation and shows the percentage reduction.
* **Reporting** – branded printable report (Print / Save as PDF), scheduled email digest (daily/weekly), and CSV / JSON export.
* **Email alerts** – get notified when failed-login activity exceeds a threshold.
* **Polished UX** – toast notifications, loading skeletons, empty states, contextual help tips, a multi-step quick-start wizard, an "At a glance" dashboard widget, and dark-mode-friendly styling.
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

* The default mode, **Headers only**, is fully offline: if your site is behind a CDN/proxy that sets a country header (e.g. Cloudflare's `CF-IPCountry`), that value is used instantly with no external request. Nothing to configure.
* Optionally, you can select an external provider for sites without a country header: **ip-api.com** (free, no key) or **MaxMind GeoLite2** (create a free account at maxmind.com and enter the key as `accountId:licenseKey`). These are off by default.
* Note: bundled offline GeoIP *databases* carry their own license/EULA, which is why none is shipped — the offline option relies on request headers instead.

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

= 1.1.0 =
* Now 100% dependency-free: removed Chart.js, Leaflet and the OpenStreetMap tile service. Charts and the world map are rendered by our own lightweight, in-house canvas modules. No third-party code, no external CDN, no runtime tile requests.
* Geolocation is offline-first: country is read from CDN/proxy headers (Cloudflare, CloudFront, etc.) with no external request. External API providers (ip-api, MaxMind) are now optional and off by default.
* New Status tab with a security posture score and prioritized, actionable recommendations.
* New scheduled email digest (daily/weekly) summarising activity.
* New branded, printable report page (Print / Save as PDF — no PDF library).
* New "At a glance" widget on the main WordPress dashboard.
* Redesigned UI: toast notifications, loading skeletons, empty states, contextual help tips, a custom menu icon and a multi-step quick-start wizard.
* Developer tooling: PHPUnit test suite, WordPress Coding Standards (phpcs) ruleset, GitHub Actions CI, and a translation template (.pot).

= 1.0.0 =
* Initial release: dashboard, traffic & login logging, GeoIP, analytics, firewall/mitigation, before/after reports, CSV/JSON export, email alerts, quick-start wizard.

== Upgrade Notice ==

= 1.1.0 =
Dependency-free release: in-house charts/map replace Chart.js and Leaflet, and geolocation is now offline-first. No action required; review the new Status tab and Scheduled Digest settings.

= 1.0.0 =
Initial release.

== Third-party libraries ==

None. As of 1.1.0 the plugin ships with ZERO third-party runtime code — all charts and the
geographic map are rendered by in-house, dependency-free modules. No external CDN or tile service is
contacted. (Development-only tools such as PHPUnit and PHP_CodeSniffer are listed in composer.json
under require-dev and are never shipped to your site.)

Geolocation is offline-first via CDN/proxy request headers. The optional external GeoIP providers
(ip-api.com, MaxMind GeoLite2) are off by default and only contacted if you explicitly enable them.
