# Matrix Site Monitor

Proactive health monitoring for managed WordPress sites. Runs lightweight baseline checks on every site, with optional WooCommerce synthetic order journeys for e-commerce stores.

## Features

- **CLI-first** — `wp msm …` is the main way to run checks (avoids PHP/web timeouts); admin UI is optional for results and light tests
- **Tiered scheduling** — light (hourly), heavy (daily), synthetic WooCommerce (hourly/4h), weekly digest
- **Baseline checks** — HTTP smoke, cron health, disk space, PHP/WP versions, pending updates, SSL expiry, security advisories, fatal errors, backup status, admin anomalies
- **Go-live preflight** — full MGPC suite (`preflight` check) plus doc-driven category coverage
- **WooCommerce pack** — page smoke, cart/checkout, coupon validation, guest order lifecycle with automatic cleanup
- **Secure by default** — mail suppression during tests, transient run locks, token-protected REST API (disabled by default)
- **Multi-recipient alerts** — email as soon as a critical check fails; the same open issue is not re-sent on every run (reminder after 24 hours, plus a mail when it clears) + scheduled digest reports
- **Per-site customisation** — filters and optional `config/site-profile.php`

## Installation

1. Upload `matrix-site-monitor` to `/wp-content/plugins/`
2. Activate the plugin
3. Prefer CLI for first run (below); optionally open **Tools → Site Monitor** to view results, **Download report** (HTML triage), and configure alerts

## WP-CLI (primary runner)

```bash
# Tiers
wp msm run                         # Light tier (default)
wp msm run --tier=heavy
wp msm run --tier=synthetic
wp msm run --tier=all

# Single check / category
wp msm run --check=seo
wp msm run --category=Security
wp msm run --tier=heavy --exclude=browser,golive

# MGPC-parity go-live preflight (preferred for launch QA)
wp msm preflight --profile=live
wp msm preflight --coverage
wp msm run --check=golive --profile=live --coverage
wp msm run --check=preflight --format=json

# Inventory & last results
wp msm list
wp msm list --tier=heavy
wp msm status --tier=light

# Property sites only (legacy MGPC helper)
wp msm sync-property-meta --dry-run
```

Admin **Tools → Site Monitor** remains available for viewing coverage and optional light one-off runs. Prefer CLI for heavy / all / preflight.

## REST API (Orchestrator Integration)

Enable in **Tools → Site Monitor → Settings → REST API**.

```
GET /wp-json/matrix-site-monitor/v1/status
Header: X-MSM-Token: <your-token>
```

Or pass `?token=<your-token>` as a query parameter.

### Response shape

```json
{
  "site": { "name": "Example Site", "url": "https://example.com/" },
  "overall_ok": true,
  "tiers": {
    "light": { "time": "...", "ok": true, "results": [...] },
    "heavy": { ... },
    "synthetic": { ... }
  },
  "generated_at": "2026-07-21 09:00:00",
  "plugin_version": "1.0.0"
}
```

### matrix-support-orchestrator integration

Poll each managed site's status endpoint on a schedule. When `overall_ok` is `false`, create an investigation task in the orchestrator. Optionally configure a **Failure webhook URL** in plugin settings to receive a JSON POST immediately on critical failures:

```json
{
  "site": "https://example.com/",
  "name": "Example Site",
  "tier": "light",
  "ok": false,
  "time": "2026-07-21 09:00:00",
  "results": [...]
}
```

## Per-site customisation

Copy `config/site-profile.example.php` to `config/site-profile.php` and adjust.

**Site-specific checks** (payments, CPTs, critical URLs unique to one client) go in `config/site-checks/` — see **[docs/SITE_SPECIFIC_CHECKS.md](docs/SITE_SPECIFIC_CHECKS.md)** for the Cursor prompt and conventions.

Also:

- `msm_smoke_urls` — extra URLs for HTTP smoke tests
- `msm_wc_smoke_urls` — extra WooCommerce URLs
- `msm_check_config` — override settings programmatically (critical paths, contact path, GA/GTM IDs, `qa_profile`, etc.)
- `msm_qa_profile` — force `auto`, `development`, or `live` without saving settings
- `msm_registered_checks` — add custom check classes
- `msm_banned_plugins` — extend the plugin denylist

## QA profile (development vs live)

Checks that only make sense on production (public error display, `DISALLOW_FILE_EDIT`, XML-RPC, Redis/WP Rocket) **skip** while the QA profile is `development`.

| Setting | What it does |
|---------|----------------|
| **Auto** (default) | Follows `WP_ENVIRONMENT_TYPE` (`local`/`development` → development rules; `staging`/`production` → live rules) |
| **Development** | Always use relaxed local rules |
| **Live / go-live** | Always apply production rules — use this during launch QA, even on Local |

Switch in **Tools → Site Monitor → Settings → QA profile**, or for one CLI run:

```bash
wp msm preflight --profile=live
wp msm run --check=golive --profile=live --coverage
wp msm run --tier=heavy --profile=development
```

`WP_ENVIRONMENT_TYPE` in `wp-config.php` still describes the actual WordPress environment. Do not set it to `production` on Local just to change these checks — use the QA profile instead.

## Security notes

- Test orders are flagged with `_msm_selftest` meta and auto-deleted
- All WooCommerce emails are suppressed during synthetic runs
- REST endpoint is disabled until explicitly enabled
- Scheduled checks run via WP-Cron; on-demand runs should use WP-CLI

## Check tiers

| Tier | Schedule | Checks |
|------|----------|--------|
| Light | Hourly | HTTP smoke, cron health, fatal errors, admin anomalies |
| Heavy | Daily | Disk, PHP/WP versions, updates, SSL, security, backups, preflight, doc suites |
| Synthetic | Hourly/4h | WooCommerce pages, cart, coupon, order lifecycle |
