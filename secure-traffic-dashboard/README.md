# SecureTraffic Dashboard

A comprehensive, production-ready WordPress security plugin that gives admins a
single dashboard to **monitor inbound traffic and login attempts, geolocate
request origins, apply mitigations, and measure the before/after security
impact**.

> Slug: `secure-traffic-dashboard` · Requires WP 5.6+, PHP 7.2+ · GPL-2.0-or-later

---

## Highlights

| Area | What you get |
|------|--------------|
| **Zero dependencies** | No third-party runtime code, no CDN, no tile service — in-house canvas charts + a self-drawn world map |
| **Dashboard** | Tabbed UI: Overview, Traffic, Login Attempts, Analytics, Mitigation, Reports, Status |
| **Logging** | Custom tables for traffic, logins, blocks and metrics; prepared queries; pagination |
| **GeoIP** | Offline-first via CDN/proxy country headers; optional `ip-api.com` / MaxMind providers (off by default) |
| **Analytics** | In-house time-series charts (24h/7d/30d), top IPs/countries/endpoints, dot-grid origin map |
| **Mitigation** | IP/country blocking, brute-force lockout, rate limiting, conservative bad-pattern firewall |
| **Status** | 0–100 security posture score with prioritized recommendations |
| **Impact** | Before/after failed-login comparison with % reduction |
| **Reports** | Branded printable report (Print→PDF), scheduled email digest, CSV / JSON export |
| **UX** | Toasts, loading skeletons, empty states, help tips, multi-step wizard, "At a glance" widget, dark mode |
| **Safety** | Monitor-only mode by default, IP/country whitelists, configurable retention with auto-purge |
| **Hardening** | Custom capability, nonce + capability + rate-limit on every AJAX call, escaped output |
| **Tooling** | PHPUnit tests, WPCS ruleset, GitHub Actions CI, `.pot` template |

## Architecture

```
secure-traffic-dashboard/
├── secure-traffic-dashboard.php   # Bootstrap: constants, autoloader, activation/deactivation, boot
├── uninstall.php                  # Guarded cleanup (only if opted-in)
├── includes/
│   ├── class-std-plugin.php       # Orchestrator (singleton) — wires modules to hooks
│   ├── class-std-activator.php    # Tables, defaults, cron, capability
│   ├── class-std-deactivator.php  # Clears cron (keeps data)
│   ├── class-std-installer.php    # dbDelta schema + version-gated migrations
│   ├── class-std-settings.php     # Single-array settings, Settings API, sanitization
│   ├── class-std-logger.php       # All DB reads/writes (prepared statements)
│   ├── class-std-helpers.php      # IP detection, sanitizers, flags, formatting
│   ├── class-std-geoip.php        # Provider abstraction + caching
│   ├── class-std-traffic-monitor.php  # Inbound + WP-outbound logging
│   ├── class-std-login-monitor.php    # Login success/failure + auth gating
│   ├── class-std-firewall.php     # Block enforcement, signatures, brute-force
│   ├── class-std-mitigation.php   # Block list CRUD, whitelist, CIDR matching
│   ├── class-std-metrics.php      # Aggregation cron, summaries, before/after, purge
│   ├── class-std-ajax.php         # Secured AJAX endpoints
│   ├── class-std-export.php       # CSV/JSON streaming
│   └── views/                     # Presentation-only partials (escaped output)
└── assets/
    ├── css/admin.css              # Responsive, dark-mode-aware
    ├── js/admin.js                # Tables, charts, map, mitigation, wizard
    └── vendor/                    # Bundled Chart.js + Leaflet (no CDN)
```

The autoloader maps `STD_Foo_Bar` → `includes/class-std-foo-bar.php`. No
Composer required.

## Data model

Four custom tables (prefix `{$wpdb->prefix}std_`):

- `std_traffic` — inbound (and WP-outbound) request events.
- `std_logins` — login attempts (success/fail) with geo.
- `std_blocks` — active/historical IP & country blocks.
- `std_metrics` — hourly rollups and before/after baselines.

## Extending the plugin

All hooks use the `std_` prefix.

### Actions

| Hook | Fires when |
|------|-----------|
| `std_loaded` | Plugin finished wiring hooks (receives the `STD_Plugin` instance) |
| `std_block_added` | A block is created (`$type, $value, $id`) |
| `std_block_removed` | A block is removed (`$id`) |
| `std_request_blocked` | A request is about to be denied (`$ip, $reason`) |
| `std_bruteforce_lockout` | An IP is locked out for brute force (`$ip, $count`) |
| `std_render_tab_{slug}` | Render a custom dashboard tab (`$settings`) |

### Filters

| Hook | Purpose |
|------|---------|
| `std_before_log_event` | Mutate or skip an event before it is logged (`$data, $type`) |
| `std_firewall_rules` | Add/remove bad-pattern regexes (array, no delimiters) |
| `std_should_block` | Final block decision for a request (`$bool, $ip, $reason`) |
| `std_geoip_providers` | Register GeoIP providers (`slug => callable($ip)`) |
| `std_dashboard_tabs` | Add/remove dashboard tab slugs |
| `std_sanitize_settings` | Adjust settings just before save (`$clean, $input`) |

### Example: register a custom GeoIP provider

```php
add_filter( 'std_geoip_providers', function ( $providers ) {
    $providers['my-service'] = function ( $ip ) {
        // ...call your API...
        return array( 'country' => 'US', 'city' => 'Austin' );
    };
    return $providers;
} );
```

### Example: add a custom firewall rule

```php
add_filter( 'std_firewall_rules', function ( $patterns ) {
    $patterns[] = '/xmlrpc\.php';   // block XML-RPC probing
    return $patterns;
} );
```

### Example: add a dashboard tab

```php
add_filter( 'std_dashboard_tabs', fn( $tabs ) => array_merge( $tabs, [ 'custom' ] ) );
add_action( 'std_render_tab_custom', function ( $settings ) {
    echo '<div class="std-panel"><h3>My tab</h3></div>';
} );
```

## Development & tooling

The shipped plugin contains **zero third-party runtime code**. The visualizations
are our own vanilla-JS canvas modules:

- `assets/js/std-charts.js` — `STDCharts.line/bar/sparkline`
- `assets/js/std-geomap.js` — `STDGeo.map` (equirectangular dot-grid + country bubbles)

Dev tooling lives in a dev-only `composer.json` (`vendor/` is gitignored and
never shipped):

```bash
composer install            # install PHPUnit + PHPCS + WPCS (dev only)

composer test               # PHPUnit unit tests (tests/)
composer phpcs              # WordPress Coding Standards (phpcs.xml.dist)
composer lint               # php -l across the codebase
php bin/make-pot.php . languages/secure-traffic-dashboard.pot   # regenerate the .pot
```

The unit tests are deliberately lightweight: `tests/bootstrap.php` shims just
enough WordPress functions to exercise the pure logic (IP/CIDR matching, settings
sanitization, header-based geolocation, firewall signatures) without standing up
the full WP test suite. GitHub Actions (`.github/workflows/ci.yml`) runs `php -l`
(PHP 7.2–8.4), PHPCS, PHPUnit and a JS syntax check on every push and PR.

- **Coding standards:** WordPress-Extra + PHPCompatibilityWP (PHP 7.2+). Sanitize
  on input, escape on output, prepared SQL everywhere.

See `readme.txt` for the user-facing installation guide, FAQ, conflicts and
testing steps.
