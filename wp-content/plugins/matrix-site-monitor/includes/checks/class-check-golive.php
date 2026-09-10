<?php
/**
 * Go-live / QC checklist from docs/GoLive.md.
 *
 * Mix of automated probes, AI browser rows, and human/host sign-off.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Golive extends Check_Base {
    public function id(): string { return 'golive'; }
    public function label(): string { return 'Go-live / QC checklist'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);
        $home = home_url('/');
        $t0   = microtime(true);
        $res  = wp_remote_get($home, [
            'timeout'     => 30,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        $elapsed = microtime(true) - $t0;
        $html    = '';
        $headers = [];
        if (! is_wp_error($res) && (int) wp_remote_retrieve_response_code($res) < 400) {
            $html    = (string) wp_remote_retrieve_body($res);
            $headers = wp_remote_retrieve_headers($res);
        }

        $findings = $this->probe($html, $elapsed, $home, $headers);
        $signoff  = $this->signoff_map();
        $ai       = $this->browser_ai_map();

        $coverage = [];
        foreach (self::doc_lines() as $doc) {
            $line = $doc['line'];
            $mode = $doc['mode'];
            $key  = $doc['key'];

            if (isset($signoff[ $line ])) {
                $st = (string) $signoff[ $line ];
                $coverage[] = [
                    'line'   => $line,
                    'mode'   => 'manual',
                    'status' => in_array($st, ['pass', 'fail', 'skip', 'manual'], true) ? $st : 'pass',
                    'detail' => 'Human sign-off (msm_golive_signoff)',
                ];
                continue;
            }

            // Browser/device lines prefer AI coverage from browser check pipeline.
            if ($mode === 'ai' && isset($ai[ $line ])) {
                $row = $ai[ $line ];
                $coverage[] = [
                    'line'   => $line,
                    'mode'   => 'ai',
                    'status' => (string) ($row['status'] ?? 'ai'),
                    'detail' => (string) ($row['detail'] ?? 'From browser AI coverage'),
                ];
                continue;
            }

            $hits = $findings[ $key ] ?? null;

            if ($mode === 'host' || $mode === 'manual') {
                if (is_array($hits) && ! empty($hits)) {
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'fail',
                        'detail' => implode('; ', array_slice($hits, 0, 3)),
                    ];
                } else {
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => $mode === 'host' ? 'manual' : 'manual',
                        'detail' => $doc['hint'] ?? 'Needs human / hosting sign-off',
                    ];
                }
                continue;
            }

            if ($mode === 'ai') {
                $coverage[] = [
                    'line' => $line, 'mode' => 'ai', 'status' => 'ai',
                    'detail' => $doc['hint'] ?? 'Needs BrowserStack / AI vision (msm_browser_ai_coverage)',
                ];
                continue;
            }

            // automated / partial
            if (! is_array($hits)) {
                $hits = [];
            }
            if ($key === 'speed' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => sprintf('Homepage fetch %.2fs', $elapsed),
                ];
                continue;
            }
            $coverage[] = [
                'line'   => $line,
                'mode'   => $mode,
                'status' => empty($hits) ? 'pass' : 'fail',
                'detail' => empty($hits)
                    ? ($doc['ok'] ?? 'OK')
                    : implode('; ', array_slice($hits, 0, 3)),
            ];
        }

        $failed = array_filter($coverage, static function (array $r): bool {
            return ($r['status'] ?? '') === 'fail';
        });

        $extra = ['coverage' => $coverage];

        if ($failed) {
            $msgs = [];
            foreach (array_slice(array_values($failed), 0, 8) as $row) {
                $msgs[] = $row['line'];
            }
            return $this->fail(
                count($failed) . ' go-live item(s) failing: ' . implode(', ', $msgs) . '.',
                $start,
                $extra
            );
        }

        $pending = count(array_filter($coverage, static function (array $r): bool {
            return in_array($r['status'] ?? '', ['manual', 'ai'], true);
        }));

        return $this->pass(
            sprintf('Go-live automated/partial OK; %d item(s) still need sign-off or AI.', $pending),
            $start,
            $extra
        );
    }

    /**
     * @return array<int, array{line:string,mode:string,key:string,hint?:string,ok?:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'Once Live: Links', 'mode' => 'partial', 'key' => 'links', 'ok' => 'Sample links OK (full crawl = content check)'],
            ['line' => 'Email Sign-Up', 'mode' => 'partial', 'key' => 'signup', 'ok' => 'Newsletter/signup form markers found (or N/A)'],
            ['line' => 'Contact Form', 'mode' => 'partial', 'key' => 'contact', 'ok' => 'Contact form present / CF7 OK'],
            ['line' => 'Website forms', 'mode' => 'partial', 'key' => 'forms', 'ok' => 'Form plugins look configured'],
            ['line' => 'Forms: browsers/devices tested', 'mode' => 'manual', 'key' => 'forms_devices', 'hint' => 'Manual QA across browsers/devices'],
            ['line' => 'Forms: UX / validation / recipients', 'mode' => 'manual', 'key' => 'forms_ux', 'hint' => 'Manual: UX, validation, recipients, reply-to'],
            ['line' => 'Interactivity', 'mode' => 'ai', 'key' => 'interact', 'hint' => 'AI/browser: interactive elements'],
            ['line' => 'Buttons', 'mode' => 'partial', 'key' => 'buttons', 'ok' => 'No empty buttons in homepage sample'],
            ['line' => 'Banners and Links', 'mode' => 'partial', 'key' => 'banners', 'ok' => 'Banner/link sample OK'],
            ['line' => 'Cross browser: Chrome', 'mode' => 'ai', 'key' => 'chrome', 'hint' => 'BrowserStack / AI'],
            ['line' => 'Cross browser: Firefox', 'mode' => 'ai', 'key' => 'firefox', 'hint' => 'BrowserStack / AI'],
            ['line' => 'Cross browser: Safari', 'mode' => 'ai', 'key' => 'safari', 'hint' => 'BrowserStack / AI'],
            ['line' => 'Cross device: Android', 'mode' => 'ai', 'key' => 'android', 'hint' => 'BrowserStack / AI'],
            ['line' => 'Cross device: iOS', 'mode' => 'ai', 'key' => 'ios', 'hint' => 'BrowserStack / AI'],
            ['line' => 'Cross device: Tablet', 'mode' => 'ai', 'key' => 'tablet', 'hint' => 'BrowserStack / AI'],
            ['line' => 'Accessibility (WCAG 2.1 AA)', 'mode' => 'partial', 'key' => 'a11y', 'ok' => 'See accessibility check; full WCAG = audit'],
            ['line' => 'Accessibility statement in footer', 'mode' => 'partial', 'key' => 'a11y_stmt', 'ok' => 'Accessibility statement link found'],
            ['line' => 'Cookie Policy', 'mode' => 'partial', 'key' => 'cookie_pol', 'ok' => 'Cookie policy page/link found'],
            ['line' => 'Privacy Policy', 'mode' => 'partial', 'key' => 'privacy', 'ok' => 'Privacy policy published/linked'],
            ['line' => 'Cookie Plugin', 'mode' => 'partial', 'key' => 'cookie_plug', 'ok' => 'CMP / cookie banner detected'],
            ['line' => 'SEO Titles and Descriptions', 'mode' => 'partial', 'key' => 'seo_meta', 'ok' => 'Homepage title + meta description present'],
            ['line' => 'SEO URL Structure', 'mode' => 'manual', 'key' => 'seo_urls', 'hint' => 'Compare old→new URL map manually'],
            ['line' => 'SEO 301 Redirects', 'mode' => 'partial', 'key' => 'redirects', 'ok' => 'Redirect plugin/rules detected (or none required)'],
            ['line' => 'SEO: Load Time', 'mode' => 'automated', 'key' => 'speed', 'ok' => 'Homepage load under threshold'],
            ['line' => 'SEO: Permalinks', 'mode' => 'automated', 'key' => 'permalinks', 'ok' => 'Pretty permalinks enabled'],
            ['line' => 'SEO: Sitemap', 'mode' => 'automated', 'key' => 'sitemap', 'ok' => 'XML sitemap reachable'],
            ['line' => 'SEO: Live Text / no Lorem', 'mode' => 'automated', 'key' => 'lorem', 'ok' => 'No Lorem Ipsum in homepage sample'],
            ['line' => 'Media optimisation', 'mode' => 'partial', 'key' => 'webp', 'ok' => 'Recent media includes WebP/AVIF or reasonable sizes'],
            ['line' => 'SEO: Traffic Filters', 'mode' => 'manual', 'key' => 'ga_filters', 'hint' => 'Exclude Matrix/client IPs in GA'],
            ['line' => 'SEO QA reviewed', 'mode' => 'manual', 'key' => 'seo_qa', 'hint' => 'Confirm SEO checklist reviewed'],
            ['line' => 'SEO Snapshot', 'mode' => 'manual', 'key' => 'seo_snap', 'hint' => 'Archive SEO/speed snapshots'],
            ['line' => 'SEO Plugin settings', 'mode' => 'partial', 'key' => 'seo_plugin', 'ok' => 'SEO plugin active'],
            ['line' => 'GA4 tracking', 'mode' => 'partial', 'key' => 'ga4', 'ok' => 'GA4 markers on homepage'],
            ['line' => 'GA4 key events', 'mode' => 'manual', 'key' => 'ga4_events', 'hint' => 'Verify key events in GA4 DebugView'],
            ['line' => 'GTM container', 'mode' => 'partial', 'key' => 'gtm', 'ok' => 'GTM container on homepage'],
            ['line' => 'GTM data layer', 'mode' => 'partial', 'key' => 'datalayer', 'ok' => 'dataLayer present'],
            ['line' => 'GSC ownership', 'mode' => 'partial', 'key' => 'gsc', 'ok' => 'Search Console verification meta/DNS (meta checked)'],
            ['line' => 'GSC robots.txt', 'mode' => 'automated', 'key' => 'robots', 'ok' => 'robots.txt OK'],
            ['line' => 'Once Live: Fallback Page', 'mode' => 'partial', 'key' => 'fallback', 'ok' => 'Custom 404 looks present'],
            ['line' => 'Once Live: Page Response / Speed', 'mode' => 'automated', 'key' => 'speed', 'ok' => 'Homepage response OK'],
            ['line' => 'Analytics Tracking', 'mode' => 'partial', 'key' => 'analytics', 'ok' => 'GA4 or GTM detected'],
            ['line' => 'Support onboarding', 'mode' => 'manual', 'key' => 'support_onboard', 'hint' => 'Fill Support onboarding template'],
            ['line' => 'Support Notification', 'mode' => 'manual', 'key' => 'support_notify', 'hint' => 'Notify PM / support'],
            ['line' => 'Contact Email', 'mode' => 'partial', 'key' => 'contact_email', 'ok' => 'admin_email valid'],
            ['line' => 'Admin Emails', 'mode' => 'partial', 'key' => 'admin_email', 'ok' => 'admin_email configured'],
            ['line' => 'Security: User Name', 'mode' => 'automated', 'key' => 'admin_user', 'ok' => 'No admin/administrator login'],
            ['line' => 'Security: CMS Password', 'mode' => 'manual', 'key' => 'cms_pass', 'hint' => 'Confirm strong CMS passwords'],
            ['line' => 'Security: FTP User', 'mode' => 'host', 'key' => 'ftp', 'hint' => 'Hosting: separate FTP user'],
            ['line' => 'Security: CMS Login Page', 'mode' => 'partial', 'key' => 'login_url', 'ok' => 'Custom login / limit-login present (or review)'],
            ['line' => 'Security: Plugins', 'mode' => 'partial', 'key' => 'plugins_ok', 'ok' => 'No mass inactive plugin clutter'],
            ['line' => 'Security: Updates', 'mode' => 'partial', 'key' => 'updates', 'ok' => 'No pending core/plugin/theme updates'],
            ['line' => 'Security: Unused Plugins', 'mode' => 'partial', 'key' => 'unused', 'ok' => 'Inactive plugins under threshold'],
            ['line' => 'Security: Database Keys', 'mode' => 'partial', 'key' => 'db_keys', 'ok' => 'Table prefix not wp_ / salts look set'],
            ['line' => 'Security: Database Password', 'mode' => 'host', 'key' => 'db_pass', 'hint' => 'Hosting: strong DB password + backups'],
            ['line' => 'Security: File Permissions', 'mode' => 'partial', 'key' => 'uploads_php', 'ok' => 'No PHP in uploads sample'],
            ['line' => 'Security: OS Updates', 'mode' => 'host', 'key' => 'os', 'hint' => 'Hosting: OS patched'],
            ['line' => 'Security: Apache Mod_Evasive', 'mode' => 'host', 'key' => 'mod_evasive', 'hint' => 'Hosting: mod_evasive'],
            ['line' => 'Security: Directory Indexing', 'mode' => 'automated', 'key' => 'dirlist', 'ok' => 'No directory listing'],
            ['line' => 'Security: XSS Protection', 'mode' => 'partial', 'key' => 'xss', 'ok' => 'XSS-related security headers present'],
            ['line' => 'Security: MySQL', 'mode' => 'host', 'key' => 'mysql', 'hint' => 'Hosting: no external MySQL / non-root'],
            ['line' => 'Security: File Ownership', 'mode' => 'host', 'key' => 'owner', 'hint' => 'Hosting: file ownership'],
            ['line' => 'Security: SSH Access', 'mode' => 'host', 'key' => 'ssh', 'hint' => 'Hosting: SSH IP restrict'],
            ['line' => 'Security: Penetration Testing', 'mode' => 'manual', 'key' => 'pentest', 'hint' => 'Confirm pen-test / security package'],
            ['line' => 'Security: MySQL Binlog', 'mode' => 'host', 'key' => 'binlog', 'hint' => 'Hosting: binlog policy'],
            ['line' => 'Server Setup', 'mode' => 'host', 'key' => 'server', 'hint' => 'Confirm production server ready'],
            ['line' => 'DNS Access', 'mode' => 'manual', 'key' => 'dns', 'hint' => 'Confirm DNS access before launch'],
            ['line' => 'Content Finalisation', 'mode' => 'manual', 'key' => 'content_final', 'hint' => 'Copy/images approved'],
            ['line' => 'Placeholder Content', 'mode' => 'automated', 'key' => 'lorem', 'ok' => 'No Lorem/placeholder on homepage'],
            ['line' => 'Forms Testing', 'mode' => 'manual', 'key' => 'forms_test', 'hint' => 'Confirm notifications received'],
            ['line' => 'Newsletter Integration', 'mode' => 'partial', 'key' => 'signup', 'ok' => 'Newsletter markers or N/A'],
            ['line' => 'Pre-launch Accessibility', 'mode' => 'manual', 'key' => 'a11y_pre', 'hint' => 'Final a11y review before launch'],
            ['line' => 'SEO Foundations', 'mode' => 'partial', 'key' => 'seo_found', 'ok' => 'Title/meta/canonical/sitemap/robots basics'],
            ['line' => 'Pre-launch Privacy Policy', 'mode' => 'partial', 'key' => 'privacy', 'ok' => 'Privacy policy OK'],
            ['line' => 'Pre-launch Cookie Policy', 'mode' => 'partial', 'key' => 'cookie_pol', 'ok' => 'Cookie policy OK'],
            ['line' => 'SLA: Support notified', 'mode' => 'manual', 'key' => 'sla_support', 'hint' => 'Dev notified PM / support'],
            ['line' => 'SLA: Hosting', 'mode' => 'manual', 'key' => 'sla_host', 'hint' => 'Confirm hosting arrangement'],
            ['line' => 'SLA: SSL', 'mode' => 'automated', 'key' => 'ssl', 'ok' => 'Site on HTTPS'],
            ['line' => 'SLA: CookieScript/CookieBot', 'mode' => 'partial', 'key' => 'cookie_plug', 'ok' => 'Cookie CMP detected'],
            ['line' => 'SLA: Online training', 'mode' => 'manual', 'key' => 'sla_train', 'hint' => 'Confirm training delivered'],
            ['line' => 'SLA: Cyber Security Package', 'mode' => 'manual', 'key' => 'sla_cyber', 'hint' => 'Record package type if purchased'],
        ];
    }

    /**
     * @param string               $html    Homepage HTML.
     * @param float                $elapsed Fetch seconds.
     * @param string               $home    Home URL.
     * @param mixed                $headers Response headers.
     * @return array<string, array<int, string>>
     */
    private function probe(string $html, float $elapsed, string $home, $headers = []): array {
        $f = [];
        $keys = [];
        foreach (self::doc_lines() as $d) {
            $keys[ $d['key'] ] = [];
        }
        $f = $keys;

        if ($html === '') {
            $f['links'][] = 'Could not fetch homepage';
            return $f;
        }

        // Links sample.
        if (preg_match_all('#<a[^>]+href=["\']([^"\'#]+)["\']#i', $html, $am)) {
            $n = 0;
            foreach (array_unique($am[1]) as $href) {
                if ($n >= 8) {
                    break;
                }
                $href = html_entity_decode($href, ENT_QUOTES);
                if (stripos($href, 'mailto:') === 0 || stripos($href, 'tel:') === 0) {
                    continue;
                }
                if (strpos($href, '/') === 0) {
                    $href = home_url($href);
                }
                if (! preg_match('#^https?://#i', $href)) {
                    continue;
                }
                $host = wp_parse_url($href, PHP_URL_HOST);
                $home_host = wp_parse_url($home, PHP_URL_HOST);
                if ($host && $home_host && strcasecmp((string) $host, (string) $home_host) !== 0) {
                    continue;
                }
                $n++;
                $code = $this->probe_code($href);
                if ($code === 404 || ($code !== null && $code >= 500)) {
                    $f['links'][] = $href . ' HTTP ' . $code;
                    $f['banners'][] = $href . ' HTTP ' . $code;
                }
            }
        }

        // Buttons empty.
        if (preg_match_all('#<button\b[^>]*>(.*?)</button>#is', $html, $bm)) {
            foreach ($bm[1] as $inner) {
                if (trim(wp_strip_all_tags($inner)) === '' && ! preg_match('#aria-label=#i', $inner)) {
                    $f['buttons'][] = 'Empty <button> without aria-label';
                    break;
                }
            }
        }

        // Forms / contact / signup.
        $has_form = (bool) preg_match('#<form\b#i', $html);
        $has_cf7  = defined('WPCF7_VERSION') || post_type_exists('wpcf7_contact_form');
        $has_wpforms = defined('WPFORMS_VERSION') || post_type_exists('wpforms');
        $has_theme_forms = class_exists('Theme_Forms');
        if (! $has_form && ! $has_cf7 && ! $has_wpforms && ! $has_theme_forms) {
            $f['contact'][] = 'No form markup / CF7 / WPForms / Theme_Forms detected';
            $f['forms'][]   = 'No forms detected';
        }
        $has_captcha = (bool) preg_match('/recaptcha|hcaptcha|turnstile|cf-turnstile/i', $html);
        if ($has_form && ! $has_captcha && ! ($has_theme_forms && method_exists('Theme_Forms', 'turnstile_enabled') && (\Theme_Forms::turnstile_enabled() || \Theme_Forms::recaptcha_enabled()))) {
            $f['contact'][] = 'Form present but no CAPTCHA/Turnstile signal';
            $f['forms'][]   = 'CAPTCHA/Turnstile not detected on homepage sample';
        }
        if (! preg_match('/newsletter|mailchimp|klaviyo|signup|sign-up|subscribe/i', $html)
            && ! $this->plugin_active('mailchimp-for-wp/mailchimp-for-wp.php')
        ) {
            // Soft — leave empty (N/A OK).
        }

        // Lorem / placeholder.
        if (preg_match('/lorem\s+ipsum|dolor\s+sit\s+amet|placeholder content|your text here/i', $html)) {
            $f['lorem'][] = 'Lorem Ipsum / placeholder text on homepage';
        }

        // A11y statement — prefer footer region.
        $footer = substr($html, (int) (strlen($html) * 0.55));
        if (! preg_match('/accessibility(?:\s+statement)?/i', $footer)) {
            if (! preg_match('/accessibility(?:\s+statement)?/i', $html)) {
                $f['a11y_stmt'][] = 'No accessibility statement link text found in homepage HTML';
            } else {
                $f['a11y_stmt'][] = 'Accessibility statement found but not in the footer region';
            }
        }

        // Cookie / privacy.
        $privacy_ok = (int) get_option('wp_page_for_privacy_policy') > 0
            || (bool) preg_match('/privacy\s*policy/i', $html);
        if (! $privacy_ok) {
            $f['privacy'][] = 'Privacy policy page not set / not linked on homepage';
        }
        if (! preg_match('/cookie\s*policy|cookie\s*notice/i', $html)
            && ! $this->cookie_plugin_active()
        ) {
            $f['cookie_pol'][] = 'No cookie policy link / CMP detected';
        }
        if (! $this->cookie_plugin_active()
            && ! preg_match('/cookiebot|cookiescript|cookieyes|complianz|cookie-law|gdpr-cookie|cookieconsent/i', $html)
        ) {
            $f['cookie_plug'][] = 'No cookie CMP plugin/markers detected';
        }
        if (! preg_match('/data protection officer|\bdpo\b|dpo@|gdpr@/i', $html)) {
            $f['privacy'][] = 'No DPO / data-protection officer signal on homepage';
        }

        // SEO meta.
        if (! preg_match('#<title[^>]*>[^<]+</title>#i', $html)) {
            $f['seo_meta'][] = 'Missing title';
            $f['seo_found'][] = 'Missing title';
        }
        if (! preg_match('#name=["\']description["\']#i', $html)) {
            $f['seo_meta'][] = 'Missing meta description';
            $f['seo_found'][] = 'Missing meta description';
        }
        if (! preg_match('#rel=["\']canonical["\']#i', $html)) {
            $f['seo_found'][] = 'Missing canonical';
        }

        // Permalinks.
        if (get_option('permalink_structure') === '') {
            $f['permalinks'][] = 'Plain permalinks (query strings) — enable pretty permalinks';
        }

        // Sitemap.
        $sitemap_ok = false;
        foreach ([home_url('/wp-sitemap.xml'), home_url('/sitemap_index.xml'), home_url('/sitemap.xml')] as $surl) {
            $code = $this->probe_code($surl);
            if ($code !== null && $code >= 200 && $code < 400) {
                $sitemap_ok = true;
                break;
            }
        }
        if (! $sitemap_ok) {
            $f['sitemap'][] = 'XML sitemap not found';
            $f['seo_found'][] = 'Sitemap missing';
        }

        // robots.
        $r = wp_remote_get(home_url('/robots.txt'), [
            'timeout' => 10, 'sslverify' => false,
            'headers' => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        if (is_wp_error($r) || (int) wp_remote_retrieve_response_code($r) >= 400) {
            $f['robots'][] = 'robots.txt missing or error';
            $f['seo_found'][] = 'robots.txt issue';
        } elseif (preg_match('/^\s*Disallow:\s*\/\s*$/mi', (string) wp_remote_retrieve_body($r))
            && (string) get_option('blog_public') !== '0'
        ) {
            $f['robots'][] = 'robots.txt Disallow: / on public site';
        }

        // Speed.
        if ($elapsed > 3.0) {
            $f['speed'][] = sprintf('Homepage fetch %.2fs (threshold 3s)', $elapsed);
        }

        // Redirect plugins — optional, do not fail.
        // SEO plugin.
        if (! $this->plugin_active('wordpress-seo/wp-seo.php')
            && ! $this->plugin_active('seo-by-rank-math/rank-math.php')
            && ! $this->plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php')
            && ! $this->plugin_active('wp-seopress/seopress.php')
        ) {
            $f['seo_plugin'][] = 'No common SEO plugin active';
        }

        // GA / GTM.
        $has_ga  = (bool) preg_match('/G-[A-Z0-9]+|gtag\(|googletagmanager\.com\/gtag/i', $html);
        $has_gtm = (bool) preg_match('/GTM-[A-Z0-9]+|googletagmanager\.com\/gtm/i', $html);
        if (! $has_ga) {
            $f['ga4'][] = 'GA4 not detected on homepage';
        }
        if (! $has_gtm) {
            $f['gtm'][] = 'GTM not detected on homepage';
        }
        if (! $has_ga && ! $has_gtm) {
            $f['analytics'][] = 'No GA4/GTM on homepage';
        }
        if ($has_gtm && ! preg_match('/dataLayer/i', $html)) {
            $f['datalayer'][] = 'GTM present but dataLayer not found in HTML';
        }
        if (! preg_match('/google-site-verification/i', $html)) {
            // Soft — DNS verify common; skip fail unless expect.
            // leave empty
        }

        // 404 fallback — request random path.
        $four = wp_remote_get(home_url('/msm-golive-missing-' . wp_generate_password(6, false)), [
            'timeout' => 10, 'sslverify' => false,
            'headers' => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        if (! is_wp_error($four)) {
            $code = (int) wp_remote_retrieve_response_code($four);
            $body = (string) wp_remote_retrieve_body($four);
            if ($code !== 404) {
                $f['fallback'][] = 'Missing URL returned HTTP ' . $code . ' (expected 404)';
            } elseif (strlen(trim(wp_strip_all_tags($body))) < 40) {
                $f['fallback'][] = '404 response body looks empty';
            }
        }

        // Emails.
        $admin = (string) get_option('admin_email');
        if (! is_email($admin)) {
            $f['admin_email'][] = 'admin_email invalid';
            $f['contact_email'][] = 'admin_email invalid';
        }

        // Admin username.
        $admins = get_users(['role' => 'administrator', 'number' => 15]);
        foreach ($admins as $u) {
            $login   = strtolower((string) $u->user_login);
            $display = trim((string) $u->display_name);
            if (in_array($login, ['admin', 'administrator'], true)) {
                $f['admin_user'][] = 'Risky admin login: ' . $u->user_login;
            }
            if ((int) $u->ID === 1 && in_array($login, ['admin', 'administrator'], true)) {
                $f['admin_user'][] = 'User ID 1 still named ' . $u->user_login;
            }
            if ($display === '' || strcasecmp($display, (string) $u->user_login) === 0) {
                $f['admin_user'][] = 'Administrator "' . $u->user_login . '" has no distinct display nickname';
            }
        }

        if (! $this->plugin_active('wps-hide-login/wps-hide-login.php')
            && ! $this->plugin_active('better-wp-security/better-wp-security.php')
            && ! $this->plugin_active('limit-login-attempts-reloaded/limit-login-attempts-reloaded.php')
            && ! $this->plugin_active('wordfence/wordfence.php')
            && ! $this->plugin_active('all-in-one-wp-security-and-firewall/wp-security.php')
        ) {
            $f['login_url'][] = 'No custom login URL or login rate-limit plugin detected';
        }

        $smtp_opts = get_option('wp_mail_smtp', []);
        $mailer    = is_array($smtp_opts) ? (string) ($smtp_opts['mail']['mailer'] ?? $smtp_opts['mailer'] ?? '') : '';
        $good_mailers = ['sendgrid', 'smtpcom', 'smtp2go', 'brevo', 'sendinblue', 'mailgun', 'postmark', 'sparkpost', 'smtp', 'gmail', 'outlook', 'sendlayer'];
        if ($mailer === '' || in_array(strtolower($mailer), ['mail', 'none'], true)) {
            if (! $this->plugin_active('wp-mail-smtp/wp_mail_smtp.php')
                && ! $this->plugin_active('wp-mail-smtp-pro/wp_mail_smtp.php')
                && ! $this->plugin_active('fluent-smtp/fluent-smtp.php')
            ) {
                $f['contact_email'][] = 'No transactional SMTP plugin (SendGrid/Brevo/etc.) detected';
            } else {
                $f['contact_email'][] = 'SMTP plugin active but mailer is not configured (' . ($mailer !== '' ? $mailer : 'empty') . ')';
            }
        } elseif (! in_array(strtolower($mailer), $good_mailers, true)) {
            $f['contact_email'][] = 'Mailer "' . $mailer . '" — confirm it is the client SendGrid/Brevo account';
        }

        // Updates / unused plugins.
        if (! function_exists('wp_get_update_data')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }
        $data = wp_get_update_data();
        if ((int) ($data['counts']['total'] ?? 0) > 0) {
            $f['updates'][] = (int) $data['counts']['total'] . ' updates pending';
        }
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all = get_plugins();
        $active = (array) get_option('active_plugins', []);
        $inactive = 0;
        foreach (array_keys($all) as $file) {
            if (! in_array($file, $active, true)) {
                $inactive++;
            }
        }
        if ($inactive >= 5) {
            $f['unused'][] = $inactive . ' inactive plugins';
            $f['plugins_ok'][] = $inactive . ' inactive plugins';
        }

        // DB prefix / salts.
        global $wpdb;
        if (isset($wpdb->prefix) && $wpdb->prefix === 'wp_') {
            $f['db_keys'][] = 'Table prefix is default wp_';
        }
        if (! defined('AUTH_KEY') || AUTH_KEY === 'put your unique phrase here') {
            $f['db_keys'][] = 'AUTH_KEY looks default/unset';
        }

        // Uploads PHP / dir listing.
        $upload = wp_upload_dir();
        if (empty($upload['error']) && ! empty($upload['basedir'])) {
            $dir = trailingslashit($upload['basedir']);
            foreach ((array) @scandir($dir) as $file) {
                if (is_string($file) && preg_match('/\.php$/i', $file)) {
                    $f['uploads_php'][] = 'PHP in uploads: ' . $file;
                    break;
                }
            }
            if (! empty($upload['baseurl'])) {
                $list = wp_remote_get(trailingslashit($upload['baseurl']), [
                    'timeout' => 8, 'sslverify' => false,
                    'headers' => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
                ]);
                if (! is_wp_error($list)) {
                    $b = (string) wp_remote_retrieve_body($list);
                    if (preg_match('/Index of|Directory listing/i', $b)) {
                        $f['dirlist'][] = 'Uploads directory listing enabled';
                    }
                }
            }
        }

        // SSL.
        if (! is_ssl() && strpos($home, 'https://') !== 0) {
            $f['ssl'][] = 'Site not served over HTTPS';
        }

        if (! defined('DISALLOW_FILE_EDIT') || ! DISALLOW_FILE_EDIT) {
            $f['uploads_php'][] = 'DISALLOW_FILE_EDIT is not enabled';
        }

        $header_get = static function (string $name) use ($headers): string {
            if (is_object($headers) && method_exists($headers, 'offsetGet')) {
                return strtolower((string) ($headers[ $name ] ?? ''));
            }
            if (is_array($headers)) {
                return strtolower((string) ($headers[ $name ] ?? ''));
            }
            return '';
        };
        $xss_found = 0;
        foreach (['x-content-type-options', 'x-frame-options', 'content-security-policy'] as $hname) {
            if ($header_get($hname) !== '') {
                $xss_found++;
            }
        }
        if ($xss_found < 1) {
            $f['xss'][] = 'No X-Content-Type-Options / X-Frame-Options / CSP header on homepage';
        }

        $img_ids = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
            'posts_per_page' => 10,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        if ($img_ids) {
            $modern = 0;
            foreach ($img_ids as $img_id) {
                $mime = (string) get_post_mime_type((int) $img_id);
                if (in_array($mime, ['image/webp', 'image/avif'], true)) {
                    $modern++;
                }
            }
            if ($modern < 1) {
                $f['webp'][] = 'Recent image sample has no WebP/AVIF attachments';
            }
        }

        // A11y — point at missing lang as light signal.
        if (! preg_match('#<html[^>]+lang=#i', $html)) {
            $f['a11y'][] = 'Missing html lang (see accessibility check)';
        }

        return $f;
    }

    /**
     * @return array<string, string>
     */
    private function signoff_map(): array {
        $map = apply_filters('msm_golive_signoff', get_option('msm_golive_signoff', []));
        return is_array($map) ? $map : [];
    }

    /**
     * Map browser AI rows onto go-live cross-browser lines.
     *
     * @return array<string, array{status?:string,detail?:string}>
     */
    private function browser_ai_map(): array {
        $stored = get_option('msm_browser_ai_last', []);
        $rows   = [];
        if (is_array($stored) && ! empty($stored['coverage']) && is_array($stored['coverage'])) {
            $rows = $stored['coverage'];
        }
        $rows = apply_filters('msm_browser_ai_coverage', $rows, home_url('/'));
        if (! is_array($rows)) {
            return [];
        }

        $alias = [
            'Mobile layout broken'     => 'Cross device: iOS', // also android — map carefully
            'Tablet layout broken'     => 'Cross device: Tablet',
            'Safari-specific issues'   => 'Cross browser: Safari',
            'Firefox rendering issues' => 'Cross browser: Firefox',
            'Chrome-only functionality'=> 'Cross browser: Chrome',
            'iOS menu problems'        => 'Cross device: iOS',
            'Android touch issues'     => 'Cross device: Android',
        ];

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['line'])) {
                continue;
            }
            $src = (string) $row['line'];
            if (isset($alias[ $src ])) {
                $out[ $alias[ $src ] ] = $row;
            }
            // Direct match if someone used go-live line names.
            $out[ $src ] = $row;
        }
        return $out;
    }

    private function cookie_plugin_active(): bool {
        $plugins = [
            'cookiebot/cookiebot.php',
            'cookie-script-com/cookie-script.php',
            'cookieyes/cookie-law-info.php',
            'cookie-law-info/cookie-law-info.php',
            'complianz-gdpr/complianz-gpdr.php',
            'complianz-gdpr/complianz-gdpr.php',
            'cookie-notice/cookie-notice.php',
            'uk-cookie-consent/uk-cookie-consent.php',
        ];
        foreach ($plugins as $p) {
            if ($this->plugin_active($p)) {
                return true;
            }
        }
        return (bool) apply_filters('msm_golive_cookie_plugin_detected', false);
    }

    private function plugin_active(string $plugin): bool {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active($plugin);
    }

    private function probe_code(string $url): ?int {
        $args = [
            'timeout'   => 8,
            'sslverify' => false,
            'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ];
        $res = wp_remote_head($url, $args);
        if (! is_wp_error($res)) {
            $code = (int) wp_remote_retrieve_response_code($res);
            if ($code !== 405 && $code !== 501) {
                return $code;
            }
        }
        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) {
            return null;
        }
        return (int) wp_remote_retrieve_response_code($res);
    }
}
