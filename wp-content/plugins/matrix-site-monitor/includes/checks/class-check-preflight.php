<?php
/**
 * Go-live preflight suite — parity with Matrix Go-Live Preflight Checks (MGPC).
 *
 * Automated probes + explicit manual skips (browser/device/host). Prefer running
 * via WP-CLI: `wp msm run --check=preflight` or `wp msm run --category=Preflight`.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;
use Matrix_Site_Monitor\Settings;

defined('ABSPATH') || exit;

class Check_Preflight extends Check_Base {
    public function id(): string {
        return 'preflight';
    }

    public function label(): string {
        return 'Go-live preflight (MGPC suite)';
    }

    public function severity(): string {
        return 'warning';
    }

    public function tier(): string {
        return 'heavy';
    }

    public function run(): array {
        $start = microtime(true);
        $coverage = [];

        foreach (self::suite() as $item) {
            $fn = $item['fn'];
            try {
                $row = $this->{$fn}();
            } catch (\Throwable $e) {
                $row = [
                    'status'  => 'fail',
                    'message' => 'Fatal: ' . $e->getMessage(),
                    'details' => [],
                ];
            }

            $coverage[] = [
                'line'   => $item['id'],
                'label'  => $item['label'],
                'group'  => $item['group'],
                'mode'   => $item['mode'],
                'status' => (string) ($row['status'] ?? 'fail'),
                'detail' => (string) ($row['message'] ?? ''),
                'details'=> $row['details'] ?? [],
            ];
        }

        $failed = array_filter($coverage, static function (array $r): bool {
            return ($r['status'] ?? '') === 'fail';
        });
        $warned = array_filter($coverage, static function (array $r): bool {
            return ($r['status'] ?? '') === 'warn';
        });

        $extra = [
            'coverage' => $coverage,
            'summary'  => [
                'pass' => count(array_filter($coverage, static fn($r) => ($r['status'] ?? '') === 'pass')),
                'fail' => count($failed),
                'warn' => count($warned),
                'skip' => count(array_filter($coverage, static fn($r) => in_array(($r['status'] ?? ''), ['skip', 'manual'], true))),
            ],
        ];

        if ($failed) {
            $msgs = [];
            foreach (array_slice(array_values($failed), 0, 6) as $row) {
                $msgs[] = ($row['line'] ?? '') . ': ' . ($row['detail'] ?? '');
            }
            return $this->fail(implode('; ', $msgs), $start, $extra);
        }

        if ($warned) {
            return $this->pass(
                sprintf(
                    'Preflight OK with %d warning(s). pass=%d warn=%d skip=%d',
                    count($warned),
                    $extra['summary']['pass'],
                    $extra['summary']['warn'],
                    $extra['summary']['skip']
                ),
                $start,
                $extra
            );
        }

        return $this->pass(
            sprintf(
                'Preflight suite OK. pass=%d skip=%d',
                $extra['summary']['pass'],
                $extra['summary']['skip']
            ),
            $start,
            $extra
        );
    }

    /**
     * Suite definition (MGPC registry parity).
     *
     * @return array<int, array{id:string,group:string,label:string,mode:string,fn:string}>
     */
    public static function suite(): array {
        return [
            ['id' => 'functional.links.internal', 'group' => 'functional', 'label' => 'Internal links (critical paths)', 'mode' => 'automated', 'fn' => 'check_internal_links'],
            ['id' => 'functional.buttons.targets', 'group' => 'functional', 'label' => 'Button / anchor targets', 'mode' => 'automated', 'fn' => 'check_button_targets'],
            ['id' => 'functional.contact.form', 'group' => 'functional', 'label' => 'Contact form markers', 'mode' => 'automated', 'fn' => 'check_contact_form'],
            ['id' => 'functional.response.time', 'group' => 'functional', 'label' => 'Critical path response time', 'mode' => 'automated', 'fn' => 'check_response_time'],
            ['id' => 'functional.properties.data_drift', 'group' => 'functional', 'label' => 'Property data vs legacy meta drift', 'mode' => 'automated', 'fn' => 'check_property_drift'],
            ['id' => 'seo.meta.titles', 'group' => 'seo', 'label' => 'Title & meta description coverage', 'mode' => 'automated', 'fn' => 'check_seo_meta'],
            ['id' => 'seo.sitemap.accessible', 'group' => 'seo', 'label' => 'Sitemap endpoint reachable', 'mode' => 'automated', 'fn' => 'check_sitemap'],
            ['id' => 'seo.robots.accessible', 'group' => 'seo', 'label' => 'robots.txt reachable', 'mode' => 'automated', 'fn' => 'check_robots'],
            ['id' => 'seo.permalinks.pretty', 'group' => 'seo', 'label' => 'Pretty permalinks', 'mode' => 'automated', 'fn' => 'check_permalinks'],
            ['id' => 'seo.redirects.dataset', 'group' => 'seo', 'label' => 'Configured redirect rules', 'mode' => 'automated', 'fn' => 'check_redirects'],
            ['id' => 'security.username.hardening', 'group' => 'security', 'label' => 'Default admin username patterns', 'mode' => 'automated', 'fn' => 'check_admin_usernames'],
            ['id' => 'security.updates.status', 'group' => 'security', 'label' => 'Plugin / theme updates & inactive plugins', 'mode' => 'automated', 'fn' => 'check_updates'],
            ['id' => 'security.login.hardening', 'group' => 'security', 'label' => 'Login hardening signals', 'mode' => 'automated', 'fn' => 'check_login_hardening'],
            ['id' => 'security.version.exposure', 'group' => 'security', 'label' => 'WordPress generator meta exposure', 'mode' => 'automated', 'fn' => 'check_generator'],
            ['id' => 'analytics.ga.gtm.present', 'group' => 'analytics', 'label' => 'GA4 / GTM script presence', 'mode' => 'automated', 'fn' => 'check_analytics'],
            ['id' => 'analytics.gsc.verification', 'group' => 'analytics', 'label' => 'Search Console verification meta', 'mode' => 'automated', 'fn' => 'check_gsc'],
            ['id' => 'compliance.a11y.statement', 'group' => 'compliance', 'label' => 'Accessibility statement in footer', 'mode' => 'automated', 'fn' => 'check_a11y_statement'],
            ['id' => 'compliance.cookie.cmp', 'group' => 'compliance', 'label' => 'CookieScript / Cookiebot / CMP + policy', 'mode' => 'automated', 'fn' => 'check_cookie_cmp'],
            ['id' => 'compliance.cookie.dpo', 'group' => 'compliance', 'label' => 'DPO / data-protection contact signal', 'mode' => 'automated', 'fn' => 'check_dpo'],
            ['id' => 'security.smtp.mailer', 'group' => 'security', 'label' => 'Transactional mailer (SendGrid / Brevo / SMTP)', 'mode' => 'automated', 'fn' => 'check_smtp_mailer'],
            ['id' => 'security.user.display', 'group' => 'security', 'label' => 'Admin display nickname vs login', 'mode' => 'automated', 'fn' => 'check_user_display'],
            ['id' => 'security.headers', 'group' => 'security', 'label' => 'XSS-related security headers', 'mode' => 'automated', 'fn' => 'check_security_headers'],
            ['id' => 'security.file_edit', 'group' => 'security', 'label' => 'DISALLOW_FILE_EDIT', 'mode' => 'automated', 'fn' => 'check_file_edit'],
            ['id' => 'media.webp.sample', 'group' => 'performance', 'label' => 'Modern image formats (WebP/AVIF sample)', 'mode' => 'automated', 'fn' => 'check_media_webp'],
            ['id' => 'content.lorem.sample', 'group' => 'content', 'label' => 'Global Lorem Ipsum sample', 'mode' => 'automated', 'fn' => 'check_lorem_sample'],
            ['id' => 'security.os.updates.manual', 'group' => 'manual', 'label' => 'Manual: OS patch level on host', 'mode' => 'manual', 'fn' => 'manual_os'],
            ['id' => 'security.apache.mod_evasive.manual', 'group' => 'manual', 'label' => 'Manual: Apache mod_evasive', 'mode' => 'manual', 'fn' => 'manual_mod_evasive'],
            ['id' => 'manual.cross_browser.chrome', 'group' => 'manual', 'label' => 'Manual: Chrome smoke test', 'mode' => 'manual', 'fn' => 'manual_chrome'],
            ['id' => 'manual.cross_browser.firefox', 'group' => 'manual', 'label' => 'Manual: Firefox smoke test', 'mode' => 'manual', 'fn' => 'manual_firefox'],
            ['id' => 'manual.cross_browser.safari', 'group' => 'manual', 'label' => 'Manual: Safari smoke test', 'mode' => 'manual', 'fn' => 'manual_safari'],
            ['id' => 'manual.cross_device.android', 'group' => 'manual', 'label' => 'Manual: Android device check', 'mode' => 'manual', 'fn' => 'manual_android'],
            ['id' => 'manual.cross_device.ios', 'group' => 'manual', 'label' => 'Manual: iOS device check', 'mode' => 'manual', 'fn' => 'manual_ios'],
            ['id' => 'manual.cross_device.tablet', 'group' => 'manual', 'label' => 'Manual: Tablet check', 'mode' => 'manual', 'fn' => 'manual_tablet'],
            ['id' => 'manual.analytics.ga4.events', 'group' => 'manual', 'label' => 'Manual: GA4 key events', 'mode' => 'manual', 'fn' => 'manual_ga4_events'],
        ];
    }

    /**
     * Doc lines for admin UI (id as line).
     *
     * @return array<int, array{line:string,mode:string}>
     */
    public static function doc_lines(): array {
        $rows = [];
        foreach (self::suite() as $item) {
            $rows[] = [
                'line' => $item['id'] . ' — ' . $item['label'],
                'mode' => $item['mode'],
            ];
        }
        return $rows;
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_internal_links(): array {
        $cfg          = Settings::get();
        $host         = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $source_paths = Settings::parse_path_list((string) ($cfg['critical_paths'] ?? ''));
        if (empty($source_paths)) {
            $source_paths = ['/'];
        }
        $source_paths = array_values(array_unique(array_merge(['/'], $source_paths)));
        $max          = max(1, (int) ($cfg['max_link_checks'] ?? 60));
        $timeout      = max(3, (int) ($cfg['request_timeout'] ?? 10));

        $link_refs = [];
        foreach ($source_paths as $source_path) {
            $source_path = '/' . ltrim($source_path, '/');
            $html        = $this->fetch_html($source_path, $timeout);
            if (! $html['ok']) {
                continue;
            }
            preg_match_all('/<a\b[^>]*(?:\s|^)href=[\'"]([^\'"]+)[\'"][^>]*>/i', $html['body'], $matches, PREG_SET_ORDER);
            foreach ((array) $matches as $match) {
                $href = $match[1] ?? '';
                $tag  = $match[0] ?? '';
                if ($href === '' || $href[0] === '#' || strpos($href, 'mailto:') === 0 || strpos($href, 'tel:') === 0) {
                    continue;
                }
                $parsed_host = wp_parse_url($href, PHP_URL_HOST);
                if (! empty($parsed_host) && $parsed_host !== $host) {
                    continue;
                }
                $target_url = strpos($href, 'http') === 0 ? $href : home_url('/' . ltrim($href, '/'));
                $label      = '';
                if (preg_match('/aria-label=[\'"]([^\'"]+)[\'"]/i', $tag, $aria)) {
                    $label = $aria[1];
                } elseif (preg_match('/title=[\'"]([^\'"]+)[\'"]/i', $tag, $title)) {
                    $label = $title[1];
                }
                $link_refs[] = [
                    'target_url'  => $target_url,
                    'linked_from' => home_url($source_path),
                    'anchor_text' => $label,
                ];
            }
        }

        if (empty($link_refs)) {
            return $this->row('warn', 'No internal anchor links found in configured source pages.');
        }

        $unique = array_values(array_unique(array_map(static fn($i) => $i['target_url'], $link_refs)));
        $unique = array_slice($unique, 0, $max);
        $status = [];
        foreach ($unique as $target) {
            $res = wp_remote_head($target, [
                'timeout'   => $timeout,
                'sslverify' => false,
                'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
            ]);
            $status[ $target ] = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
        }

        $broken = [];
        foreach ($link_refs as $ref) {
            if (! isset($status[ $ref['target_url'] ])) {
                continue;
            }
            $code = (int) $status[ $ref['target_url'] ];
            if ($code === 0 || $code >= 400) {
                $broken[] = array_merge($ref, ['code' => $code]);
            }
        }

        if ($broken) {
            return $this->row(
                'fail',
                sprintf('Found %d broken internal link reference(s) across %d source page(s).', count($broken), count($source_paths)),
                array_slice($broken, 0, 20)
            );
        }

        return $this->row('pass', sprintf('Checked %d internal link targets from %d source page(s).', count($unique), count($source_paths)));
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_button_targets(): array {
        $html = $this->fetch_html('/');
        if (! $html['ok']) {
            return $this->row('fail', 'Unable to fetch homepage for button validation.');
        }
        preg_match_all('/<(a|button)[^>]*>/i', $html['body'], $matches);
        $count = count($matches[0] ?? []);
        if ($count === 0) {
            return $this->row('warn', 'No button/anchor elements found on homepage.');
        }
        return $this->row('pass', sprintf('Detected %d interactive button/anchor element(s).', $count));
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_contact_form(): array {
        $cfg  = Settings::get();
        $path = (string) ($cfg['contact_path'] ?? '/contact/');
        if ($path === '') {
            $path = '/contact/';
        }
        $path = '/' . ltrim($path, '/');
        $res  = $this->fetch_html($path);
        if (! $res['ok']) {
            return $this->row('warn', 'Contact page unavailable at configured path (' . $path . '). Update contact_path in settings.');
        }
        $body         = strtolower($res['body']);
        $has_form     = strpos($body, '<form') !== false;
        $captcha_hint = strpos($body, 'captcha') !== false
            || strpos($body, 'recaptcha') !== false
            || strpos($body, 'hcaptcha') !== false
            || strpos($body, 'turnstile') !== false
            || strpos($body, 'cf-turnstile') !== false;

        if ($has_form && $captcha_hint) {
            return $this->row('pass', 'Contact form markup and captcha signal detected at ' . $path);
        }
        if ($has_form) {
            return $this->row('warn', 'Form found at ' . $path . ' but captcha marker was not detected.');
        }
        return $this->row('fail', 'No contact form markup found on configured contact page (' . $path . ').');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_response_time(): array {
        $cfg   = Settings::get();
        $paths = Settings::parse_path_list((string) ($cfg['critical_paths'] ?? ''));
        if (empty($paths)) {
            $paths = ['/'];
        }
        $limit   = max(200, (int) ($cfg['response_time_ms'] ?? 1500));
        $timeout = max(5, (int) ($cfg['request_timeout'] ?? 10));
        $slow    = [];

        foreach ($paths as $path) {
            $path  = '/' . ltrim($path, '/');
            $start = microtime(true);
            $res   = wp_remote_get(home_url($path), [
                'timeout'   => $timeout,
                'sslverify' => false,
                'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
            ]);
            $ms = (int) round((microtime(true) - $start) * 1000);
            if (is_wp_error($res) || $ms > $limit) {
                $slow[] = ['path' => $path, 'ms' => $ms, 'error' => is_wp_error($res) ? $res->get_error_message() : ''];
            }
        }

        if ($slow) {
            return $this->row('warn', 'One or more critical paths are slower than ' . $limit . 'ms.', $slow);
        }
        return $this->row('pass', 'Critical paths are within response-time threshold (' . $limit . 'ms).');
    }

    /**
     * Property CPT drift (MGPC) — skips when post type absent.
     *
     * @return array{status:string,message:string,details?:array}
     */
    private function check_property_drift(): array {
        if (! post_type_exists('property')) {
            return $this->row('skip', 'No property post type — drift check not applicable.');
        }

        $properties = get_posts([
            'post_type'      => 'property',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'fields'         => 'ids',
        ]);

        if (empty($properties)) {
            return $this->row('skip', 'No published properties found for drift validation.');
        }

        $drift = [];
        foreach ($properties as $property_id) {
            $source_bed  = $this->normalize_value($this->property_data_row_value((int) $property_id, 'Bedrooms'));
            $source_bath = $this->normalize_value($this->property_data_row_value((int) $property_id, 'Bathrooms'));
            $source_area = $this->normalize_value((string) get_post_meta((int) $property_id, 'flexible_content_blocks_0_size', true));
            $legacy_bed  = $this->normalize_value((string) get_post_meta((int) $property_id, 'bedrooms', true));
            $legacy_bath = $this->normalize_value((string) get_post_meta((int) $property_id, 'bathrooms', true));
            $legacy_area = $this->normalize_value((string) get_post_meta((int) $property_id, 'area', true));

            $row       = [
                'property_id' => (int) $property_id,
                'title'       => get_the_title((int) $property_id),
                'url'         => get_permalink((int) $property_id),
            ];
            $has_drift = false;

            if ($source_bed !== '' && $legacy_bed !== '' && $source_bed !== $legacy_bed) {
                $row['bedrooms_source'] = $source_bed;
                $row['bedrooms_legacy'] = $legacy_bed;
                $has_drift              = true;
            }
            if ($source_bath !== '' && $legacy_bath !== '' && $source_bath !== $legacy_bath) {
                $row['bathrooms_source'] = $source_bath;
                $row['bathrooms_legacy'] = $legacy_bath;
                $has_drift               = true;
            }
            if ($source_area !== '' && $legacy_area !== '' && $source_area !== $legacy_area) {
                $row['area_source'] = $source_area;
                $row['area_legacy'] = $legacy_area;
                $has_drift          = true;
            }

            if ($has_drift) {
                $drift[] = $row;
            }
        }

        if ($drift) {
            return $this->row(
                'fail',
                sprintf('Detected data drift on %d properties between property_data and legacy meta.', count($drift)),
                array_slice($drift, 0, 25)
            );
        }

        return $this->row('pass', sprintf('No data drift found across %d properties.', count($properties)));
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_seo_meta(): array {
        $posts = get_posts([
            'post_type'      => ['page', 'post'],
            'post_status'    => 'publish',
            'posts_per_page' => 25,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        if (empty($posts)) {
            return $this->row('skip', 'No published posts/pages to evaluate for SEO metadata.');
        }

        $missing = [];
        foreach ($posts as $post) {
            $seo_title = trim((string) get_post_meta($post->ID, 'rank_math_title', true));
            if ($seo_title === '') {
                $seo_title = trim((string) get_post_meta($post->ID, '_yoast_wpseo_title', true));
            }
            $title = $seo_title !== '' ? $seo_title : get_the_title($post);
            $desc  = (string) get_post_meta($post->ID, 'rank_math_description', true);
            if ($desc === '') {
                $desc = (string) get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
            }
            if ($title === '' || mb_strlen($title) < 10 || $desc === '') {
                $missing[] = [
                    'id'    => $post->ID,
                    'title' => $title,
                    'url'   => get_permalink($post),
                ];
            }
        }

        if ($missing) {
            return $this->row('warn', 'Some pages are missing strong title/meta description coverage.', array_slice($missing, 0, 15));
        }

        return $this->row('pass', 'Recent pages/posts include acceptable title and meta description coverage.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_sitemap(): array {
        $cfg        = Settings::get();
        $candidates = Settings::parse_path_list((string) ($cfg['sitemap_paths'] ?? ''));
        if (empty($candidates)) {
            $candidates = ['/sitemap_index.xml', '/wp-sitemap.xml', '/sitemap.xml'];
        }

        foreach ($candidates as $path) {
            $path = '/' . ltrim($path, '/');
            $res  = wp_remote_get(home_url($path), [
                'timeout'   => 10,
                'sslverify' => false,
                'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
            ]);
            if (is_wp_error($res)) {
                continue;
            }
            $code = (int) wp_remote_retrieve_response_code($res);
            if ($code >= 400) {
                continue;
            }
            $body = strtolower((string) wp_remote_retrieve_body($res));
            if (strpos($path, '.xml') !== false && strpos($body, '<urlset') === false && strpos($body, '<sitemapindex') === false) {
                return $this->row('warn', sprintf('Sitemap path %s is reachable but XML sitemap markers were not detected.', $path));
            }
            return $this->row('pass', sprintf('Sitemap is accessible at %s.', $path));
        }

        return $this->row('fail', 'No accessible sitemap endpoint found.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_robots(): array {
        $res = wp_remote_get(home_url('/robots.txt'), [
            'timeout'   => 10,
            'sslverify' => false,
            'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        if (is_wp_error($res)) {
            return $this->row('fail', 'robots.txt request failed: ' . $res->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code >= 400) {
            return $this->row('fail', 'robots.txt is not accessible (HTTP ' . $code . ').');
        }
        return $this->row('pass', 'robots.txt is accessible.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_permalinks(): array {
        $structure = (string) get_option('permalink_structure', '');
        if ($structure === '') {
            return $this->row('fail', 'Permalink structure is plain/query-string based.');
        }
        return $this->row('pass', 'Pretty permalinks enabled: ' . $structure);
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_redirects(): array {
        $rules = Settings::parse_redirect_rules((string) (Settings::get()['redirect_rules'] ?? ''));
        if (empty($rules)) {
            return $this->row('skip', 'No redirect rules configured for validation.');
        }

        $issues = [];
        foreach ($rules as $rule) {
            $from = (string) ($rule['from'] ?? '');
            $to   = (string) ($rule['to'] ?? '');
            if ($from === '' || $to === '') {
                continue;
            }
            $res  = wp_remote_get(home_url($from), [
                'timeout'     => 10,
                'redirection' => 0,
                'sslverify'   => false,
                'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
            ]);
            $code = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
            $loc  = is_wp_error($res) ? '' : (string) wp_remote_retrieve_header($res, 'location');
            if ($code !== 301 || strpos($loc, $to) === false) {
                $issues[] = ['from' => $from, 'code' => $code, 'location' => $loc, 'expected' => $to];
            }
        }

        if ($issues) {
            return $this->row('fail', 'One or more configured redirects failed validation.', $issues);
        }
        return $this->row('pass', 'Configured redirect rules validated successfully.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_admin_usernames(): array {
        $admin_like = get_users([
            'search'         => 'admin',
            'search_columns' => ['user_login'],
            'fields'         => ['ID', 'user_login'],
        ]);
        $user_one = get_user_by('ID', 1);
        $issues   = [];
        foreach ($admin_like as $user) {
            if (in_array(strtolower($user->user_login), ['admin', 'administrator'], true)) {
                $issues[] = $user->user_login;
            }
        }
        if ($user_one && in_array(strtolower($user_one->user_login), ['admin', 'administrator'], true)) {
            $issues[] = 'ID:1=' . $user_one->user_login;
        }
        if ($issues) {
            return $this->row('fail', 'Weak admin username pattern detected.', array_values(array_unique($issues)));
        }
        return $this->row('pass', 'No default admin usernames detected.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_updates(): array {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (! function_exists('get_plugin_updates')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_updates = get_plugin_updates();
        $theme_updates  = get_theme_updates();
        $inactive       = [];
        foreach (get_plugins() as $file => $data) {
            unset($data);
            if (! is_plugin_active($file)) {
                $inactive[] = $file;
            }
        }

        if (! empty($plugin_updates) || ! empty($theme_updates)) {
            return $this->row('warn', 'Plugin/theme updates are pending.', [
                'plugin_updates' => count($plugin_updates),
                'theme_updates'  => count($theme_updates),
                'inactive_count' => count($inactive),
            ]);
        }

        return $this->row('pass', 'No pending plugin/theme updates. Inactive plugins: ' . count($inactive));
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_login_hardening(): array {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $signals = [
            'wordfence/wordfence.php',
            'all-in-one-wp-security-and-firewall/wp-security.php',
            'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php',
            'better-wp-security/better-wp-security.php',
            'wps-hide-login/wps-hide-login.php',
            'login-lockdown/loginlockdown.php',
        ];
        foreach ($signals as $plugin) {
            if (is_plugin_active($plugin)) {
                return $this->row('pass', 'Login hardening signal found via active plugin: ' . $plugin);
            }
        }
        return $this->row('warn', 'No known login-hardening plugin signal found. Verify custom login URL/rate-limits manually.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_generator(): array {
        $home = $this->fetch_html('/');
        if (! $home['ok']) {
            return $this->row('warn', 'Could not fetch homepage to check version exposure.');
        }
        $body = strtolower($home['body']);
        if (strpos($body, 'name="generator" content="wordpress') !== false) {
            return $this->row('warn', 'WordPress generator meta tag is exposed.');
        }
        return $this->row('pass', 'WordPress generator meta tag is not exposed on homepage.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_analytics(): array {
        $cfg  = Settings::get();
        $home = $this->fetch_html('/');
        if (! $home['ok']) {
            return $this->row('fail', 'Homepage unavailable for analytics script validation.');
        }
        $body    = $home['body'];
        $ga4_ids = Settings::parse_csv_list((string) ($cfg['ga4_measurement_ids'] ?? ''));
        $gtm_ids = Settings::parse_csv_list((string) ($cfg['gtm_container_ids'] ?? ''));
        $ga      = (strpos($body, 'googletagmanager.com/gtag/js') !== false) || (bool) preg_match('/G-[A-Z0-9]+/', $body);
        $gtm     = (strpos($body, 'googletagmanager.com/gtm.js') !== false) || (bool) preg_match('/GTM-[A-Z0-9]+/', $body);
        $missing = [];

        foreach ($ga4_ids as $id) {
            if (strpos($body, $id) === false) {
                $missing[] = $id;
            } else {
                $ga = true;
            }
        }
        foreach ($gtm_ids as $id) {
            if (strpos($body, $id) === false) {
                $missing[] = $id;
            } else {
                $gtm = true;
            }
        }

        if ($ga || $gtm) {
            if ($missing) {
                return $this->row('warn', 'Analytics scripts detected, but one or more configured IDs were not found.', $missing);
            }
            return $this->row('pass', sprintf('Analytics script signals detected (GA4: %s, GTM: %s).', $ga ? 'yes' : 'no', $gtm ? 'yes' : 'no'));
        }

        if (! empty($cfg['expect_analytics'])) {
            return $this->row('fail', 'No GA4/GTM script signatures detected on homepage (expect_analytics is on).');
        }

        return $this->row('warn', 'No GA4/GTM script signatures detected on homepage.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_gsc(): array {
        $home = $this->fetch_html('/');
        if (! $home['ok']) {
            return $this->row('warn', 'Homepage unavailable for Search Console verification check.');
        }
        $body = strtolower($home['body']);
        if (strpos($body, 'google-site-verification') !== false) {
            return $this->row('pass', 'Search Console verification meta tag detected.');
        }
        return $this->row('skip', 'Search Console ownership often uses DNS or file methods. No meta tag found.');
    }

    /** @return array{status:string,message:string,details?:array} */
    private function manual_os(): array {
        return $this->row('skip', 'Manual-only: verify operating system patch level on host.');
    }

    /** @return array{status:string,message:string,details?:array} */
    private function manual_mod_evasive(): array {
        return $this->row('skip', 'Manual-only: verify Apache mod_evasive installation/configuration on host.');
    }

    /** @return array{status:string,message:string,details?:array} */
    private function manual_chrome(): array {
        return $this->row('skip', 'Manual-only: run end-to-end validation in Chrome (or supply msm_browser_ai_coverage).');
    }

    /** @return array{status:string,message:string,details?:array} */
    private function manual_firefox(): array {
        return $this->row('skip', 'Manual-only: run end-to-end validation in Firefox.');
    }

    /** @return array{status:string,message:string,details?:array} */
    private function manual_safari(): array {
        return $this->row('skip', 'Manual-only: run end-to-end validation in Safari.');
    }

    /** @return array{status:string,message:string,details?:array} */
    private function manual_android(): array {
        return $this->row('skip', 'Manual-only: run UI/form flow validation on Android devices.');
    }

    /** @return array{status:string,message:string,details?:array} */
    private function manual_ios(): array {
        return $this->row('skip', 'Manual-only: run UI/form flow validation on iOS devices.');
    }

    /** @return array{status:string,message:string,details?:array} */
    private function manual_tablet(): array {
        return $this->row('skip', 'Manual-only: run UI/form flow validation on tablet devices.');
    }

    /** @return array{status:string,message:string,details?:array} */
    private function manual_ga4_events(): array {
        return $this->row('skip', 'Partial/manual: verify GA4 key events in DebugView or GTM Preview.');
    }

    /**
     * Sync legacy property meta from property_data block (MGPC helper).
     *
     * @param array<string, mixed> $args Args.
     * @return array<string, mixed>
     */
    public static function sync_property_meta_from_property_data(array $args = []): array {
        $args    = wp_parse_args($args, [
            'dry_run' => false,
            'limit'   => 300,
        ]);
        $dry_run = ! empty($args['dry_run']);
        $limit   = max(1, (int) $args['limit']);

        if (! post_type_exists('property')) {
            return [
                'dry_run'             => $dry_run,
                'properties_seen'     => 0,
                'properties_synced'   => 0,
                'details'             => [],
                'message'             => 'No property post type.',
            ];
        }

        $self = new self();
        $properties = get_posts([
            'post_type'      => 'property',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
        ]);

        $updated = [];
        foreach ($properties as $property_id) {
            $changes     = [];
            $source_bed  = $self->normalize_value($self->property_data_row_value((int) $property_id, 'Bedrooms'));
            $source_bath = $self->normalize_value($self->property_data_row_value((int) $property_id, 'Bathrooms'));
            $source_area = $self->normalize_value((string) get_post_meta((int) $property_id, 'flexible_content_blocks_0_size', true));
            $legacy_bed  = $self->normalize_value((string) get_post_meta((int) $property_id, 'bedrooms', true));
            $legacy_bath = $self->normalize_value((string) get_post_meta((int) $property_id, 'bathrooms', true));
            $legacy_area = $self->normalize_value((string) get_post_meta((int) $property_id, 'area', true));

            if ($source_bed !== '' && $source_bed !== $legacy_bed) {
                $changes['bedrooms'] = $source_bed;
            }
            if ($source_bath !== '' && $source_bath !== $legacy_bath) {
                $changes['bathrooms'] = $source_bath;
            }
            if ($source_area !== '' && $source_area !== $legacy_area) {
                $changes['area'] = $source_area;
            }

            if (empty($changes)) {
                continue;
            }

            if (! $dry_run) {
                foreach ($changes as $meta_key => $meta_value) {
                    update_post_meta((int) $property_id, $meta_key, $meta_value);
                }
            }

            $updated[] = [
                'property_id' => (int) $property_id,
                'title'       => get_the_title((int) $property_id),
                'url'         => get_permalink((int) $property_id),
                'changes'     => $changes,
            ];
        }

        return [
            'dry_run'           => $dry_run,
            'properties_seen'   => count($properties),
            'properties_synced' => count($updated),
            'details'           => $updated,
        ];
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_a11y_statement(): array {
        $home = $this->fetch_html('/');
        if (! $home['ok']) {
            return $this->row('fail', 'Homepage unavailable for accessibility-statement check.');
        }
        $html   = $home['body'];
        $footer = substr($html, (int) (strlen($html) * 0.55));
        if (! preg_match('/accessibility(?:\s+statement)?/i', $footer)
            || ! preg_match('/<a\b[^>]+href=/i', $footer)
        ) {
            if (preg_match('/accessibility(?:\s+statement)?/i', $html)) {
                return $this->row('warn', 'Accessibility statement text found, but not clearly in the footer.');
            }
            return $this->row('fail', 'No accessibility statement link found on the homepage.');
        }

        $page = $this->fetch_html('/accessibility-statement/');
        if (! $page['ok']) {
            return $this->row('fail', 'Footer links to an accessibility statement, but /accessibility-statement/ did not load.');
        }
        $body = $page['body'];
        $missing = [];
        if (! preg_match('/WCAG/i', $body)) {
            $missing[] = 'WCAG';
        }
        if (! preg_match('/mailto:|contact-us/i', $body)) {
            $missing[] = 'contact method';
        }
        if (! preg_match('/<(h1|h2)\b/i', $body)) {
            $missing[] = 'heading';
        }
        if (strlen(wp_strip_all_tags($body)) < 800) {
            $missing[] = 'substance (statement is too short)';
        }
        if ($missing) {
            return $this->row('fail', 'Accessibility statement page is missing: ' . implode(', ', $missing) . '.');
        }

        return $this->row('pass', 'Accessibility statement linked in the footer and the page includes WCAG, headings, and a contact method.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_cookie_cmp(): array {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = [
            'cookiebot/cookiebot.php',
            'cookie-script-com/cookie-script.php',
            'cookieyes-cal/cookieyes-cal.php',
            'cookieyes/cookie-law-info.php',
            'cookie-law-info/cookie-law-info.php',
            'complianz-gdpr/complianz-gdpr.php',
            'uk-cookie-consent/uk-cookie-consent.php',
            'cookie-notice/cookie-notice.php',
        ];
        $active = [];
        foreach ($plugins as $file) {
            if (is_plugin_active($file)) {
                $active[] = $file;
            }
        }
        $home = $this->fetch_html('/');
        $html = $home['ok'] ? strtolower($home['body']) : '';
        $marker = (bool) preg_match('/cookiebot|cookiescript|cookieyes|complianz|cookie-law|gdpr-cookie|cookieconsent/i', $html);
        $policy = (bool) preg_match('/cookie\s*policy|cookie\s*notice/i', $html);
        if ($active && $policy) {
            return $this->row('pass', 'Cookie CMP plugin active and cookie policy linked.', $active);
        }
        if ($marker && $policy) {
            return $this->row('pass', 'Cookie CMP script and cookie policy detected on homepage.');
        }
        if ($active || $marker) {
            return $this->row('warn', 'Cookie CMP detected but cookie policy link was not found.');
        }
        if ($policy) {
            return $this->row('warn', 'Cookie policy linked but no CookieScript/Cookiebot/CMP plugin or script found.');
        }
        return $this->row('fail', 'No CookieScript/Cookiebot/CMP and no cookie policy link detected.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_dpo(): array {
        $paths = ['/', '/privacy-policy/', '/privacy/', '/cookie-policy/', '/cookies/'];
        foreach ($paths as $path) {
            $page = $this->fetch_html($path);
            if (! $page['ok']) {
                continue;
            }
            if (preg_match('/data protection officer|\bdpo\b|dpo@|gdpr@|privacy@/i', $page['body'])) {
                return $this->row('pass', 'DPO / data-protection contact signal found on ' . $path);
            }
        }
        return $this->row('warn', 'No DPO / data-protection officer contact signal found on privacy/cookie pages.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_smtp_mailer(): array {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $smtp_plugins = [
            'wp-mail-smtp/wp_mail_smtp.php',
            'wp-mail-smtp-pro/wp_mail_smtp.php',
            'easy-wp-smtp/easy-wp-smtp.php',
            'fluent-smtp/fluent-smtp.php',
            'post-smtp/postman-smtp.php',
        ];
        $plugin_ok = false;
        foreach ($smtp_plugins as $file) {
            if (is_plugin_active($file)) {
                $plugin_ok = true;
                break;
            }
        }
        $opts   = get_option('wp_mail_smtp', []);
        $mailer = '';
        if (is_array($opts)) {
            $mailer = (string) ($opts['mail']['mailer'] ?? $opts['mailer'] ?? '');
        }
        $good = ['sendgrid', 'smtpcom', 'smtp2go', 'brevo', 'sendinblue', 'mailgun', 'postmark', 'sparkpost', 'smtp', 'gmail', 'outlook', 'sendlayer', 'elasticemail'];
        if ($mailer !== '' && in_array(strtolower($mailer), $good, true)) {
            return $this->row('pass', 'Transactional mailer configured: ' . $mailer);
        }
        if ($plugin_ok && ($mailer === '' || in_array(strtolower($mailer), ['mail', 'none'], true))) {
            return $this->row('fail', 'SMTP plugin is active but mailer is not a client transactional account (current: ' . ($mailer !== '' ? $mailer : 'empty') . ').');
        }
        if ($plugin_ok) {
            return $this->row('warn', 'SMTP plugin active; mailer=' . $mailer . ' — confirm it is the client SendGrid/Brevo account.');
        }
        return $this->row('fail', 'No WP Mail SMTP / FluentSMTP / Post SMTP plugin active.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_user_display(): array {
        $issues = [];
        $user_one = get_user_by('id', 1);
        if ($user_one && in_array(strtolower((string) $user_one->user_login), ['admin', 'administrator'], true)) {
            $issues[] = 'User ID 1 still uses login "' . $user_one->user_login . '"';
        }
        foreach (get_users(['role' => 'administrator', 'number' => 10]) as $user) {
            $login   = (string) $user->user_login;
            $display = trim((string) $user->display_name);
            $nick    = trim((string) $user->nickname);
            if ($display === '' || strcasecmp($display, $login) === 0) {
                $issues[] = 'Administrator "' . $login . '" has no distinct display nickname';
            } elseif ($nick !== '' && strcasecmp($nick, $login) === 0 && strcasecmp($display, $login) === 0) {
                $issues[] = 'Administrator "' . $login . '" nickname matches login';
            }
        }
        if ($issues) {
            return $this->row('fail', 'Admin username / nickname hardening failed.', $issues);
        }
        return $this->row('pass', 'Admin logins are not admin/administrator and display nicknames look set.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_security_headers(): array {
        $res = wp_remote_get(home_url('/'), [
            'timeout'   => 10,
            'sslverify' => false,
            'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        if (is_wp_error($res)) {
            return $this->row('fail', 'Could not fetch homepage headers: ' . $res->get_error_message());
        }
        $headers = wp_remote_retrieve_headers($res);
        $get     = static function (string $name) use ($headers): string {
            if (is_object($headers) && method_exists($headers, 'offsetGet')) {
                return strtolower((string) ($headers[ $name ] ?? ''));
            }
            return strtolower((string) ($headers[ $name ] ?? ''));
        };
        $found = [];
        foreach (['x-content-type-options', 'x-frame-options', 'content-security-policy', 'referrer-policy'] as $name) {
            if ($get($name) !== '') {
                $found[] = $name;
            }
        }
        if (count($found) >= 2) {
            return $this->row('pass', 'XSS-related headers present: ' . implode(', ', $found));
        }
        if ($found) {
            return $this->row('warn', 'Only partial XSS-related headers found: ' . implode(', ', $found));
        }
        return $this->row('warn', 'No X-Content-Type-Options / X-Frame-Options / CSP headers on homepage.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_file_edit(): array {
        if (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) {
            return $this->row('pass', 'DISALLOW_FILE_EDIT is enabled.');
        }
        return $this->row('warn', 'DISALLOW_FILE_EDIT is not enabled — theme/plugin file editor may be available.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_media_webp(): array {
        $ids = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'],
            'posts_per_page' => 12,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        if (empty($ids)) {
            return $this->row('skip', 'No image attachments to sample.');
        }
        $modern = 0;
        $heavy  = 0;
        foreach ($ids as $id) {
            $mime = (string) get_post_mime_type((int) $id);
            if (in_array($mime, ['image/webp', 'image/avif'], true)) {
                $modern++;
            }
            $file = get_attached_file((int) $id);
            if (is_string($file) && is_file($file) && filesize($file) > 500000) {
                $heavy++;
            }
        }
        if ($modern > 0) {
            return $this->row('pass', sprintf('Image sample includes %d WebP/AVIF of %d recent attachments.', $modern, count($ids)));
        }
        if ($heavy > 0) {
            return $this->row('warn', sprintf('No WebP/AVIF in recent sample (%d attachments); %d files are over 500KB.', count($ids), $heavy));
        }
        return $this->row('warn', 'Recent image sample has no WebP/AVIF attachments.');
    }

    /**
     * @return array{status:string,message:string,details?:array}
     */
    private function check_lorem_sample(): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $hits = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND (post_content LIKE '%lorem ipsum%' OR post_title LIKE '%lorem ipsum%' OR post_excerpt LIKE '%lorem ipsum%')"
        );
        if ($hits > 0) {
            return $this->row('fail', $hits . ' published post/page(s) still contain Lorem Ipsum.');
        }
        return $this->row('pass', 'No published posts/pages matched a Lorem Ipsum search.');
    }

    /**
     * @param string               $status  pass|fail|warn|skip.
     * @param string               $message Message.
     * @param array<int|string,mixed> $details Details.
     * @return array{status:string,message:string,details:array}
     */
    private function row(string $status, string $message, array $details = []): array {
        return [
            'status'  => $status,
            'message' => $message,
            'details' => $details,
        ];
    }

    /**
     * @param string $path    Path.
     * @param int    $timeout Timeout seconds.
     * @return array{ok:bool,body:string,status?:int,message?:string}
     */
    private function fetch_html(string $path = '/', int $timeout = 0): array {
        if ($timeout <= 0) {
            $timeout = max(3, (int) (Settings::get()['request_timeout'] ?? 10));
        }
        $url = home_url('/' . ltrim($path, '/'));
        $res = wp_remote_get($url, [
            'timeout'   => $timeout,
            'sslverify' => false,
            'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        if (is_wp_error($res)) {
            return ['ok' => false, 'message' => $res->get_error_message(), 'body' => ''];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        return [
            'ok'     => $code < 400,
            'status' => $code,
            'body'   => (string) wp_remote_retrieve_body($res),
        ];
    }

    private function normalize_value($value): string {
        $value = is_array($value) ? implode(' ', array_map('strval', $value)) : (string) $value;
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        $value = wp_strip_all_tags($value);
        $value = preg_replace('/\s+/u', ' ', trim($value));
        $value = preg_replace('/^\s*area\s*:\s*/iu', '', (string) $value);
        return strtolower(trim((string) $value));
    }

    private function property_data_row_value(int $post_id, string $target_label): string {
        $count = (int) get_post_meta($post_id, 'flexible_content_blocks_0_extra_rows', true);
        if ($count <= 0) {
            return '';
        }
        $target_label = strtolower(trim($target_label));
        for ($i = 0; $i < $count; $i++) {
            $label = strtolower(trim((string) get_post_meta($post_id, "flexible_content_blocks_0_extra_rows_{$i}_label", true)));
            if ($label === $target_label) {
                return (string) get_post_meta($post_id, "flexible_content_blocks_0_extra_rows_{$i}_value", true);
            }
        }
        return '';
    }
}
