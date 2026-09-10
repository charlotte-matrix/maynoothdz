# Site-specific checks

Generic Matrix Site Monitor coverage is shared across all managed sites. **Site-specific checks** live only on the site that needs them (payments, CPTs, theme quirks, critical URLs).

## Where they live

```
plugins/matrix-site-monitor/config/
  site-profile.php          # always load this on the site
  site-checks/
    class-check-*.php       # one class per concern (created by Cursor)
```

`site-checks/` is loaded automatically when present. Each file should self-register via `msm_registered_checks`.

Do **not** put site-specific logic in `includes/checks/` — that ships to every site.

## Cursor prompt (paste each time)

Copy/paste and fill the blanks:

```
Add a site-specific Matrix Site Monitor check for this WordPress site.

Context:
- Plugin: plugins/matrix-site-monitor
- Site profile: plugins/matrix-site-monitor/config/site-profile.php
- Put the new class in: plugins/matrix-site-monitor/config/site-checks/class-check-<slug>.php
- Follow docs/SITE_SPECIFIC_CHECKS.md
- Extend Matrix_Site_Monitor\Check_Base (or implement Check_Interface)
- Namespace: Matrix_Site_Monitor\Site_Checks
- Self-register with add_filter('msm_registered_checks', ...)
- Category: "Site"
- Choose tier: light | heavy | synthetic
- Choose severity: critical | warning | info
- Keep it resource-light (samples, no full crawls, no real payments/emails)
- Add a one-line note to config/site-profile.php listing the check ID
- Document the check ID + what it covers in a short comment at the top of the class

Check to implement:
- ID: <slug>
- Label: <human label>
- What to verify: <bullet list>
- Pass when: <...>
- Fail when: <...>
- Skip when: <not applicable on this site>

Verify with: wp msm run --check=<slug>
```

## Checklist for Cursor / reviewer

1. File under `config/site-checks/class-check-<slug>.php`
2. Unique `id()` (prefix with site slug if useful, e.g. `laser_contact_form`)
3. `category()` returns `Site` (or rely on registry map via filter `msm_check_categories`)
4. Self-registers on `msm_registered_checks`
5. No secrets in code (read from WC gateway options / WP options)
6. Runs cleanly when dependency missing → `skip`, not `fail`
7. CLI: `wp msm run --check=<id>` and appears under Failures / All results → Site

## Example skeleton

```php
<?php
/**
 * Site check: <what>
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Site_Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Example extends Check_Base {
    public function id(): string { return 'site_example'; }
    public function label(): string { return 'Site: example'; }
    public function category(): string { return 'Site'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);
        // ... probe ...
        return $this->pass('OK.', $start);
    }
}

add_filter('msm_registered_checks', static function (array $checks): array {
    $checks[] = new Check_Example();
    return $checks;
});
```

---

## This site (Laser Centre) — suggested checks

Theme: **matrix-starter** · Stack: Laser Centre clinic site + custom contact/booking forms + Rank Math + WP Rocket/Redis.

### Already added

| ID | Why |
|----|-----|
| `laser_contact_form` | Contact form on `/contact-us/` — submit a tagged test entry, verify, delete |
| `laser_booking_form` | Booking form on `/book-appointment/` — submit a tagged test appointment, verify, delete |
| `laser_mail_smtp` | WP Mail SMTP mailer configured |
| `laser_critical_urls` | home / contact-us / book-appointment |
| `laser_rankmath_sitemap` | Rank Math sitemap |
| `laser_redis_rocket` | Redis + WP Rocket (skip on Local) |

```bash
wp msm list --category=Site
wp msm run --category=Site
```

### Template for the next site

Use the Cursor prompt above after analysing that site’s plugins/theme/CPTs.
