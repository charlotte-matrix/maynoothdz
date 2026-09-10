# Check catalog — docs → automation

Maps typical support issues in `docs/*.md` to Matrix Site Monitor coverage.

## Opt-in workflow (optional)

All checks are **enabled by default**. Use **Select all** / **Deselect all**, or untick individual checks.

To verify a single check: **Test one check** → Run this check.

Core suite (recommended always on): `http_smoke`, `cron_health`, `fatal_errors`, `admin_anomalies`, `disk_space`, `php_wp_versions`, `update_availability`, `ssl_expiry`, `backup_status`, plus core WooCommerce cart/order checks.

Legend: **Done** = automated now · **Partial** = related check exists · **Backlog** = planned · **Manual/External** = needs browser, host API, or human review · **AI** = better suited to AI/content review later

## general.md

| Issue | Status | Check ID |
|-------|--------|----------|
| WSOD | Partial | `general` coverage (empty body heuristic) |
| Internal Server Error (500) | Done | `general`, `http_smoke` |
| Error establishing DB connection | Done | `general` (HTML + wpdb) |
| Critical Error on Website | Done | `general`, `fatal_errors` |
| Infinite redirect loop | Done | `general`, `http_smoke` |
| Site stuck in maintenance mode | Done | `general`, `maintenance_mode` |
| Homepage OK but inner pages fail | Done | `general` |
| Broken permalinks (404s) | Done | `general` (sample) |
| Mixed content after SSL | Done | `general` |
| SSL certificate expired | Done | `general`, `ssl_expiry` |
| DNS / domain not resolving | Partial | `general` (gethostbyname) |
| PHP fatals | Done | `general`, `fatal_errors` |
| PHP warnings displayed publicly | Done | `general`, `debug_display` |
| Missing images / media broken | Done | `general` (sample HEAD) |
| Incorrect file permissions / uploads failing | Partial | `general` (uploads writable) |
| Cron not running / scheduled posts | Done | `general`, `cron_health` |

## Hosting.md

| Issue | Status | Check ID |
|-------|--------|----------|
| Disk nearly full | Done | `hosting`, `disk_space` |
| PHP memory exhausted / limit low | Done | `hosting`, `php_memory` |
| PHP version outdated | Done | `hosting`, `php_wp_versions` |
| MySQL down | Done | `hosting` (SELECT 1) |
| Redis unavailable | Partial | `hosting` (WP_REDIS_HOST probe) |
| Object cache failing | Done | `hosting`, `object_cache` |
| OPCache disabled | Done | `hosting` |
| Cron disabled | Done | `hosting`, `cron_health` |
| Email server unavailable | Partial | `hosting` (SMTP plugin; no test send) |
| SSL expiring | Done | `hosting`, `ssl_expiry` |
| Domain expiry | Manual | `hosting` + `msm_hosting_domain_expiry` filter |
| Backup failures | Partial | `hosting`, `backup_status` (UpdraftPlus) |

## Security.md

| Issue | Status | Check ID |
|-------|--------|----------|
| Malware detected | Partial | `security` (PHP in uploads / obfuscation heuristics) |
| Injected JavaScript | Partial | `security` (homepage HTML heuristics) |
| Suspicious admin users | Done | `security`, `admin_anomalies` |
| File changes / core modified | Done | `security` (core checksum sample) |
| Plugin vulnerabilities | Partial | `security`, `security_advisories` (+ WPScan token) |
| Theme vulnerabilities | Partial | `security` (Tested up to) |
| XML-RPC attacks / exposure | Done | `security`, `xmlrpc` |
| Brute force / login flood | Partial | Limit Login signals / filter |
| File editor enabled | Done | `security`, `file_editor` |
| Weak passwords | Manual | filter / policy plugins |
| Directory listing | Done | `security` |

## Maintenance.md

| Issue | Status | Check ID |
|-------|--------|----------|
| WordPress core outdated | Done | `maintenance`, `update_availability` |
| Plugin / theme updates available | Done | `maintenance`, `update_availability` |
| PHP compatibility issues | Done | `maintenance` (RequiresPHP / update API) |
| Deprecated functions | Partial | `maintenance` (debug.log tail) |
| Backup not completed | Partial | `maintenance`, `backup_status` (UpdraftPlus) |
| Backup restore failed | Partial/Manual | `maintenance` when Updraft records error |
| Unused plugins | Done | `maintenance`, `inactive_plugins` (≥5) |
| Unused themes | Done | `maintenance` (≥4 extras) |
| Orphaned uploads | Partial | `maintenance` (capped upload sample) |
| Large log files | Done | `maintenance` (>25MB) |

## Plugin.md

| Issue | Status | Check ID |
|-------|--------|----------|
| Plugin conflicts | Partial | `plugins` (known groups: cache/SEO/security/builders) |
| JavaScript conflicts | Partial | `plugins` (duplicate jQuery on homepage) |
| Fatal errors after update | Partial | `plugins` + `fatal_errors` (plugin-path fatals) |
| Deprecated PHP warnings | Partial | `plugins` (debug.log) |
| Incompatible versions | Done | `plugins` (RequiresPHP / RequiresWP) |
| Plugin abandoned | Partial | `plugins` (last_updated 2yr+) |
| Banned / denylisted plugins | Done | `banned_plugins` (File Manager family + settings / `msm_banned_plugins`) |
| Missing licence | Partial | `plugins` + `msm_plugin_licence_checks` |
| Update failures | Partial | empty package / auto_update_failed |
| Auto-updates disabled | Done | constants; soft skip if none opted in |

## Database.md

| Issue | Status | Check ID |
|-------|--------|----------|
| Corrupt tables | Done | `db_hygiene` coverage (CHECK TABLE sample) |
| Missing tables | Done | `db_hygiene` coverage (core WP tables) |
| Large transients | Done | `db_hygiene` |
| Orphaned metadata | Done | `db_hygiene` (postmeta/usermeta orphans) |
| Spam comments | Done | `db_hygiene` |
| Excessive revisions | Done | `db_hygiene` |
| Failed database upgrades | Done | `db_hygiene` (`db_version` vs `$wp_db_version`) |

## Logic.md

| Issue | Status | Check ID |
|-------|--------|----------|
| Search returning no results | Done | `site_search` |
| Login broken | Done | `http_smoke` (wp-login) |
| Key landing pages unavailable | Partial | smoke URL list |
| Menu / registration / membership | Backlog / Manual | — |
| API / CRM / newsletter / webhooks | Backlog | per-site integrations |

## WooCommerce.md

| Issue | Status | Check ID |
|-------|--------|----------|
| Checkout errors | Done | `woocommerce`, `wc_cart_checkout`, `wc_order_lifecycle` |
| Payment gateway unavailable | Done | `woocommerce`, `wc_gateways` |
| Apple Pay / Google Pay / Stripe / PayPal | Partial | `woocommerce` config heuristics (no live wallet/IPN) |
| Cart not updating | Done | `woocommerce` qty update |
| Coupons failing / rejected | Partial | `woocommerce`, `wc_coupon` |
| Shipping / tax | Partial | zones + rates / calculate_shipping |
| Cart fragments slowing site | Manual | filter |
| Products not purchasable / variations | Done | `woocommerce` |
| Images missing / price mismatch | Done | `woocommerce` sample |
| Wrong price displayed | Partial | product page HTML vs WC price |
| Out-of-stock / stock shown incorrectly | Partial | status vs qty sample |
| Add to Cart / quantity selector | Done | `woocommerce`, `wc_cart_checkout` |
| Checkout button disabled | Partial | place-order markup heuristics |
| Payment declined unexpectedly | Partial | failed-order spike |
| Order confirmation not received | Partial | customer email enabled flags |
| Wishlist broken | Partial | YITH/TI wishlist page probe |
| Account page errors | Done | My Account HTTP / fatal |
| Failed orders spike | Done | `woocommerce`, `wc_failed_orders` |
| Duplicate orders | Partial | email+total within 10m |
| Emails not sending | Partial | WC email enabled (no send) |
| Stock not reducing | Partial | manage_stock option |
| Refund failures | Manual | filter |

## Analytics.md

| Issue | Status | Check ID |
|-------|--------|----------|
| GA4 missing | Done | `analytics_tags` coverage |
| GTM missing | Done | `analytics_tags` coverage |
| Cookie banner blocking analytics incorrectly | Partial | CMP markup detected; blocking behaviour manual |
| Facebook Pixel broken | Done | detects fbq / fbevents.js |
| LinkedIn Insight missing | Done | detects LinkedIn insight scripts |
| Consent Mode issues | Partial | gtag consent / CMP markers |
| Search Console disconnected | Partial | google-site-verification meta; DNS verify manual |

## Accessibility.md

Single opt-in check: `accessibility` (homepage HTML heuristics). Enable → **Test one check** → verify.

| Issue | Status | Notes |
|-------|--------|-------|
| Missing alt text | Done | `accessibility` |
| Duplicate alt text | Done | same alt on ≥3 images |
| Decorative images announced unnecessarily | Partial | skips `role=presentation`; empty alt treated as OK |
| Skipped heading levels | Done | |
| Multiple H1s | Done | |
| Missing H1 | Done | |
| Empty headings | Done | |
| Missing labels | Done | inputs/select/textarea |
| Missing error messages | Backlog | needs form submit interaction |
| Placeholder used instead of labels | Done | heuristic |
| Required fields not announced | Backlog | needs aria-required / required audit depth |
| Keyboard trap | Manual/External | browser |
| Low contrast | Manual/AI | needs rendered CSS / axe |
| Colour-only indicators | Manual/AI | |
| Focus outline removed | Partial | detects `outline:none/0` in HTML/CSS |
| Cannot navigate via keyboard | Manual/External | |
| Incorrect tab order | Manual/External | |
| Hidden focus | Manual/External | |
| Invalid ARIA | Backlog | axe/ARIA validator |
| Missing ARIA labels | Partial | covered where we check accessible names |
| Duplicate IDs | Done | |
| Broken landmarks | Backlog | |
| Missing language attribute | Done | `<html lang>` |
| Missing page title | Done | |
| Empty links | Done | |
| Empty buttons | Done | |
| Broken skip links | Done | skip/content href target missing |
| Tables without headers | Done | |
| Videos without captions | Done | `<track kind=captions\|subtitles>` |

## content.md

| Issue | Status | Notes |
|-------|--------|-------|
| Broken internal / external links | Done | `content` coverage (capped sample) |
| Images / PDFs returning 404 | Done | `content` coverage |
| Missing downloadable files | Partial | PDF sample; Woo downloadables backlog |
| Empty pages | Done | near-empty HTML text |
| Draft pages indexed | Done | draft permalink returns HTTP 200 |
| Placeholder / Lorem Ipsum | Done | |
| Orphaned pages | Partial | not in any nav menu (threshold) |
| Missing featured images / excerpts | Partial | soft (skip), recent posts only |
| Broken embeds / videos | Partial | YouTube/Vimeo iframe HEAD |
| Spelling / grammar / formatting / duplicates / dates | Manual/AI | editorial review |

## forms.md

| Issue | Status | Notes |
|-------|--------|-------|
| Contact Form 7 submissions failing | Partial | `forms` — CF7 mail template / forms present (no live submit) |
| Form shortcode missing from page | Done | `forms` — CF7 / WPForms / GF embed search |
| Spam flooding forms | Partial | Flamingo spam or comment spam spike |
| Mail not delivered from forms | Partial | SMTP plugin / admin_email (no test send) |
| reCAPTCHA / honeypot blocking legit users | Partial/Manual | key mismatch fail; blocking = manual |
| Form confirmation page 404 | Done | redirect URLs + common thank-you slugs |

## Performance.md

| Issue | Status | Notes |
|-------|--------|-------|
| Slow page load / TTFB | Done / Partial | `performance` (fetch time + cURL TTFB) |
| Core Web Vitals / LCP / CLS / TBT | Manual | Lighthouse/CrUX or filter |
| Excessive JS / blocking CSS | Done | script + stylesheet heuristics |
| Large images / missing lazy load | Done | sample HEAD + markup |
| Missing page caching | Partial | headers / cache plugins |
| Cache not purging | Manual | filter / drill |
| Object cache disabled | Done | drop-in + `wp_using_ext_object_cache` |
| Slow queries / autoload / wp_options / revisions | Done | DB metrics |
| High CPU / PHP workers | Manual | host filters |
| High memory usage | Done | peak vs `memory_limit` |

## Theme.md

| Issue | Status | Notes |
|-------|--------|-------|
| Broken layouts | Partial | `theme` — empty/unreachable homepage HTML |
| CSS missing | Done | stylesheet URL 404/5xx |
| JavaScript errors | Partial | theme script 404s / error text |
| Child theme overridden incorrectly | Done | Template header + parent exists |
| Template overrides outdated | Partial | child filemtime + WC @version |
| Responsive issues | Partial/Manual | viewport meta; visuals → Browser |
| Header/footer missing | Done | landmarks in homepage HTML |

## seo.md

| Issue | Status | Notes |
|-------|--------|-------|
| Missing / duplicate titles | Done | `seo` sample |
| Missing meta descriptions | Done | `seo` |
| Duplicate H1s | Done | `seo` |
| Broken / missing canonicals | Done | `seo` |
| Robots.txt issues | Done | Disallow:/, missing Sitemap, HTTP errors |
| XML sitemap missing | Done | wp-sitemap / Yoast-style indexes |
| Broken schema | Partial | JSON-LD parse |
| 404 pages indexed | Partial | sitemap loc HEAD sample |
| Noindex accidentally enabled | Done | meta + blog_public |
| Missing Open Graph / Twitter cards | Done | homepage |
| Broken breadcrumbs | Partial | schema/markup URL probes |

## JavaScript.md

| Issue | Status | Notes |
|-------|--------|-------|
| Console errors | Partial | `javascript` — broken script URLs / error text in HTML |
| jQuery conflicts | Partial | duplicate jQuery core script tags |
| AJAX failures | Done | admin-ajax.php + REST root |
| Infinite loading spinners | Manual | browser |
| Broken menus | Partial | menu location + nav links in sample |
| Broken sliders | Partial | library load vs markup |
| Modals not opening | Manual | browser (script 404s flagged when related) |
| Search autocomplete broken | Partial | search form + REST `/wp/v2/search` |

## Browser.md

| Issue | Status | Notes |
|-------|--------|-------|
| Mobile / tablet layout | AI | `browser` — vision / BrowserStack; viewport preflight only |
| Safari / Firefox rendering | AI | `browser` + `msm_browser_ai_coverage` |
| Chrome-only functionality | AI | `browser` (UA sniff preflight partial) |
| iOS menu / Android touch | AI | `browser` — real device or BrowserStack |

Supply results via filter `msm_browser_ai_coverage` or option `msm_browser_ai_last` (see `docs/Browser.md`).

## GoLive.md

| Area | Status | Notes |
|------|--------|-------|
| Once-live links / forms / buttons | Partial | `golive` + `content` / `forms` |
| Cross browser / device | AI | shares `msm_browser_ai_coverage` |
| Accessibility / cookie / privacy | Partial | `golive` + `accessibility` |
| SEO foundations / GA / GTM / GSC | Partial | `golive` + `seo` / `analytics_tags` |
| Security CMS items | Partial/Automated | admin user, updates, dir listing, uploads PHP |
| Hosting / SLA / support | Manual/Host | `msm_golive_signoff` |

## Preflight (MGPC parity)

Full suite from `Matrix-Go-Live-Preflight-Checks` lives in check ID `preflight` (category **Preflight**). Prefer CLI:

`wp msm preflight --coverage`

| MGPC check ID | Status | Notes |
|---------------|--------|-------|
| `functional.links.internal` | Done | Critical paths + capped HEAD probes |
| `functional.buttons.targets` | Done | Homepage button/anchor count |
| `functional.contact.form` | Done | Configured `contact_path` + captcha/Turnstile markers |
| `functional.response.time` | Done | `critical_paths` vs `response_time_ms` |
| `functional.properties.data_drift` | Done | Skips if no `property` CPT |
| `seo.meta.titles` | Done | Rank Math / Yoast / title sample |
| `seo.sitemap.accessible` | Done | Configurable `sitemap_paths` |
| `seo.robots.accessible` | Done | |
| `seo.permalinks.pretty` | Done | |
| `seo.redirects.dataset` | Done | Settings `redirect_rules` |
| `security.username.hardening` | Done | |
| `security.updates.status` | Done | |
| `security.login.hardening` | Done | Known plugin signals including hide-login |
| `security.version.exposure` | Done | Generator meta |
| `analytics.ga.gtm.present` | Done | + optional configured IDs |
| `analytics.gsc.verification` | Done | |
| `compliance.a11y.statement` | Done | Footer accessibility statement |
| `compliance.cookie.cmp` | Done | CookieScript/Cookiebot + policy |
| `compliance.cookie.dpo` | Partial | DPO/privacy contact text sample |
| `security.smtp.mailer` | Done | SendGrid/Brevo/SMTP mailer (not PHP `mail`) |
| `security.user.display` | Done | Admin nickname vs login |
| `security.headers` | Partial | XSS-related response headers |
| `security.file_edit` | Done | `DISALLOW_FILE_EDIT` |
| `media.webp.sample` | Partial | Recent attachments WebP/AVIF |
| `content.lorem.sample` | Done | Published posts/pages Lorem search |
| Manual browser / device / OS / mod_evasive / GA4 events | Skip | Explicit skips (use Browser AI / host sign-off) |

Also: `wp msm sync-property-meta [--dry-run]` for property sites.

---

## Expanding the docs

Add new bullets to the category markdown files freely. Prefer short issue names. When implementing a check, update this catalog row to **Done** and note the check ID.
