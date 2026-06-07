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
| **Dashboard** | Tabbed UI: Overview, Traffic, Login Attempts, Analytics, Mitigation, Reports |
| **Logging** | Custom tables for traffic, logins, blocks and metrics; prepared queries; pagination |
| **GeoIP** | Pluggable provider — free `ip-api.com` default, optional MaxMind GeoLite2 |
| **Analytics** | Chart.js time-series (24h/7d/30d), top IPs/countries/endpoints, Leaflet origin map |
| **Mitigation** | IP/country blocking, brute-force lockout, rate limiting, conservative bad-pattern firewall |
| **Impact** | Before/after failed-login comparison with % reduction |
| **Reports** | CSV / JSON export of any dataset |
| **Safety** | Monitor-only mode by default, IP/country whitelists, configurable retention with auto-purge |
| **Hardening** | Custom capability, nonce + capability + rate-limit on every AJAX call, escaped output |

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

## Development notes

- **PHP lint:** `find . -name '*.php' -print0 | xargs -0 -n1 php -l`
- **Coding standards:** WordPress-Core/WordPress-Extra (PHPCS). Sanitize on
  input, escape on output, prepared SQL everywhere.
- **Vendored libraries** are fetched from the npm registry (Chart.js, Leaflet)
  and committed under `assets/vendor/` so the plugin works offline and complies
  with WordPress.org guidelines (no runtime CDN).

See `readme.txt` for the user-facing installation guide, FAQ, conflicts and
testing steps.
