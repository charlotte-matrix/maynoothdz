<?php
/**
 * Forms checks from docs/forms.md — CF7 / WPForms / Gravity Forms heuristics.
 *
 * Does not submit forms or send mail (avoids spam and Mailguard noise).
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Forms extends Check_Base {
    public function id(): string { return 'forms'; }
    public function label(): string { return 'Forms (theme / booking)'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        global $wpdb;
        $start = microtime(true);

        $plugins = $this->detect_form_plugins();
        if (empty($plugins)) {
            return $this->skip(
                'No Contact Form 7, WPForms, Gravity Forms, or Theme_Forms detected.',
                $start,
                ['coverage' => $this->static_coverage('skip', 'No form plugin active')]
            );
        }

        $findings = [
            'cf7_fail'      => [],
            'shortcode'     => [],
            'spam'          => [],
            'mail'          => [],
            'recaptcha'     => [],
            'confirmation'  => [],
        ];

        // --- Shortcodes embedded on published content ---
        if (! empty($plugins['cf7'])) {
            $forms = get_posts([
                'post_type'      => 'wpcf7_contact_form',
                'post_status'    => 'publish',
                'posts_per_page' => 20,
            ]);
            foreach ($forms as $form) {
                $fid = (int) $form->ID;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $used = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE post_status = 'publish'
                       AND post_type NOT IN ('revision','wpcf7_contact_form','nav_menu_item','attachment')
                       AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s)",
                    '%[contact-form-7%id="' . $fid . '"%',
                    "%[contact-form-7%id='" . $fid . "'%",
                    '%[contact-form-7%id=' . $fid . '%]'
                ));
                // Also match id attribute with spaces / html entities loosely.
                if ($used === 0) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $used = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->posts}
                         WHERE post_status = 'publish'
                           AND post_type NOT IN ('revision','wpcf7_contact_form','nav_menu_item','attachment')
                           AND post_content LIKE %s",
                        '%contact-form-7%' . $fid . '%'
                    ));
                }
                if ($used === 0) {
                    $findings['shortcode'][] = 'CF7 "' . $form->post_title . '" (ID ' . $fid . ') not found in published content';
                }

                // Confirmation / redirect URLs from additional settings.
                $additional = (string) get_post_meta($fid, '_additional_settings', true);
                if ($additional !== '' && preg_match_all(
                    '#(?:redirect|on_sent_ok)\s*[:=]\s*[\'"]?(https?://[^\s\'";]+)#i',
                    $additional,
                    $m
                )) {
                    foreach (array_slice(array_unique($m[1]), 0, 3) as $url) {
                        $code = $this->probe_url($url);
                        if ($code !== null && ($code === 404 || $code >= 500)) {
                            $findings['confirmation'][] = $url . ' (HTTP ' . $code . ')';
                        }
                    }
                }
            }

            // CF7 recent mail failures (if Flamingo or CF7 failure meta exists — soft).
            if (post_type_exists('flamingo_inbound')) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $spam_in = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE post_type = 'flamingo_inbound'
                       AND post_status = 'flamingo-spam'
                       AND post_date > DATE_SUB(NOW(), INTERVAL 7 DAY)"
                );
                if ($spam_in > 50) {
                    $findings['spam'][] = $spam_in . ' Flamingo spam submissions in 7 days';
                }
            }
        }

        if (! empty($plugins['wpforms'])) {
            $forms = get_posts([
                'post_type'      => 'wpforms',
                'post_status'    => ['publish', 'draft'],
                'posts_per_page' => 15,
            ]);
            foreach ($forms as $form) {
                $fid = (int) $form->ID;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $used = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE post_status = 'publish'
                       AND post_type NOT IN ('revision','wpforms','nav_menu_item','attachment')
                       AND (post_content LIKE %s OR post_content LIKE %s)",
                    '%[wpforms%id="' . $fid . '"%',
                    '%[wpforms%' . $fid . '%'
                ));
                if ($used === 0) {
                    // Block embed: <!-- wp:wpforms/form-selector {"formId":"123"} -->
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $used = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->posts}
                         WHERE post_status = 'publish'
                           AND post_content LIKE %s",
                        '%"formId":"' . $fid . '"%'
                    ));
                }
                if ($used === 0) {
                    $findings['shortcode'][] = 'WPForms "' . $form->post_title . '" (ID ' . $fid . ') not found in published content';
                }

                $data = json_decode((string) $form->post_content, true);
                if (is_array($data) && ! empty($data['settings']['confirmations'])) {
                    foreach ($data['settings']['confirmations'] as $conf) {
                        if (($conf['type'] ?? '') !== 'redirect') {
                            continue;
                        }
                        $url = (string) ($conf['redirect'] ?? $conf['redirect_url'] ?? '');
                        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
                            continue;
                        }
                        $code = $this->probe_url($url);
                        if ($code !== null && ($code === 404 || $code >= 500)) {
                            $findings['confirmation'][] = $url . ' (HTTP ' . $code . ')';
                        }
                    }
                }
            }
        }

        if (! empty($plugins['gf'])) {
            // Gravity Forms stores forms in its own table.
            $table = $wpdb->prefix . 'gf_form';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists === $table) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $gf_forms = $wpdb->get_results("SELECT id, title FROM {$table} WHERE is_trash = 0 LIMIT 15", ARRAY_A);
                foreach ((array) $gf_forms as $gf) {
                    $fid = (int) ($gf['id'] ?? 0);
                    if ($fid < 1) {
                        continue;
                    }
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $used = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->posts}
                         WHERE post_status = 'publish'
                           AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s)",
                        '%[gravityform%id="' . $fid . '"%',
                        "%[gravityform%id='" . $fid . "'%",
                        '%[gravityforms%id="' . $fid . '"%'
                    ));
                    if ($used === 0) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $used = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->posts}
                             WHERE post_status = 'publish' AND post_content LIKE %s",
                            '%gravityform%' . $fid . '%'
                        ));
                    }
                    if ($used === 0) {
                        $findings['shortcode'][] = 'Gravity Forms "' . ($gf['title'] ?? $fid) . '" (ID ' . $fid . ') not found in published content';
                    }
                }
            }
        }

        // Soft cap: unused form drafts are common — only fail if a few published forms are unused.
        if (count($findings['shortcode']) > 5) {
            $findings['shortcode'] = array_slice($findings['shortcode'], 0, 5);
        }

        $theme_pages = apply_filters('msm_theme_form_pages', []);
        if (! empty($plugins['theme']) && is_array($theme_pages)) {
            foreach ($theme_pages as $page) {
                $path    = (string) ($page['path'] ?? '');
                $needles = (array) ($page['needles'] ?? ['data-theme-form']);
                if ($path === '') {
                    continue;
                }
                $url = home_url('/' . ltrim($path, '/'));
                $res = wp_remote_get($url, [
                    'timeout'     => 15,
                    'redirection' => 3,
                    'sslverify'   => false,
                    'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
                ]);
                if (is_wp_error($res)) {
                    $findings['shortcode'][] = 'Theme form page failed: ' . $path;
                    continue;
                }
                $code = (int) wp_remote_retrieve_response_code($res);
                $body = (string) wp_remote_retrieve_body($res);
                if ($code >= 400) {
                    $findings['shortcode'][] = 'Theme form page HTTP ' . $code . ' (' . $path . ')';
                    continue;
                }
                foreach ($needles as $needle) {
                    if ($needle !== '' && stripos($body, (string) $needle) === false) {
                        $findings['shortcode'][] = 'Theme form missing "' . $needle . '" on ' . $path;
                    }
                }
            }
        }

        // Common thank-you pages (confirmation 404 heuristic when redirects not configured).
        if (empty($findings['confirmation'])) {
            foreach (['thank-you', 'thanks', 'confirmation', 'form-thank-you'] as $slug) {
                $page = get_page_by_path($slug);
                if (! $page instanceof \WP_Post) {
                    continue;
                }
                $url  = get_permalink($page);
                $code = $this->probe_url((string) $url);
                if ($code !== null && ($code === 404 || $code >= 500)) {
                    $findings['confirmation'][] = $url . ' (HTTP ' . $code . ')';
                }
            }
        }

        // Mail delivery readiness (SMTP / From address) — does not send mail.
        $smtp_ok = $this->smtp_plugin_active();
        $from    = (string) get_option('admin_email');
        if (! is_email($from)) {
            $findings['mail'][] = 'admin_email is not a valid address';
        }
        if (! $smtp_ok && $this->looks_like_local_mail()) {
            $findings['mail'][] = 'No SMTP plugin detected; local/mail() delivery often fails on hosting';
        }

        // reCAPTCHA / honeypot presence (configuration signal only).
        $recaptcha = $this->recaptcha_signal($plugins);
        if ($recaptcha['configured'] && $recaptcha['missing_secret']) {
            $findings['recaptcha'][] = 'reCAPTCHA/hCaptcha appears partially configured (site key without secret or vice versa)';
        }

        // CF7 "submissions failing" — structural readiness, not live submit.
        if (! empty($plugins['cf7'])) {
            $form_count = (int) wp_count_posts('wpcf7_contact_form')->publish;
            if ($form_count < 1) {
                $findings['cf7_fail'][] = 'Contact Form 7 active but no published forms';
            }
            // Mail components: CF7 needs mail template; empty mail subject is a common break.
            $forms = get_posts([
                'post_type'      => 'wpcf7_contact_form',
                'post_status'    => 'publish',
                'posts_per_page' => 10,
            ]);
            foreach ($forms as $form) {
                $props = get_post_meta((int) $form->ID, '_mail', true);
                if (! is_array($props)) {
                    continue;
                }
                $subject = trim((string) ($props['subject'] ?? ''));
                $body    = trim((string) ($props['body'] ?? ''));
                $recipient = trim((string) ($props['recipient'] ?? ''));
                if ($subject === '' || $body === '' || $recipient === '') {
                    $findings['cf7_fail'][] = 'CF7 "' . $form->post_title . '" has incomplete Mail template';
                }
            }
        }

        // Comment spam flood as weak proxy when Flamingo absent.
        if (empty($findings['spam'])) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $spam_week = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->comments}
                 WHERE comment_approved = 'spam'
                   AND comment_date > DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
            if ($spam_week > 200) {
                $findings['spam'][] = $spam_week . ' spam comments in 7 days (possible form/comment flood)';
            }
        }

        $coverage = $this->build_coverage($findings, $plugins, $recaptcha, $smtp_ok);
        $failed   = array_filter($coverage, static function (array $r): bool {
            return ($r['status'] ?? '') === 'fail';
        });

        $extra = [
            'coverage' => $coverage,
            'plugins'  => array_keys($plugins),
        ];

        if ($failed) {
            $msgs = [];
            foreach (array_slice(array_values($failed), 0, 6) as $row) {
                $msgs[] = $row['line'] . (! empty($row['detail']) ? ': ' . $row['detail'] : '');
            }
            return $this->fail(implode('; ', $msgs) . '.', $start, $extra);
        }

        return $this->pass(
            'Forms sample OK (' . implode(', ', array_keys($plugins)) . ').',
            $start,
            $extra
        );
    }

    /**
     * @return array<int, array{line:string,mode:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'Contact Form 7 submissions failing', 'mode' => 'partial'],
            ['line' => 'Form shortcode missing from page', 'mode' => 'automated'],
            ['line' => 'Spam flooding forms', 'mode' => 'partial'],
            ['line' => 'Mail not delivered from forms', 'mode' => 'partial'],
            ['line' => 'reCAPTCHA / honeypot blocking legit users', 'mode' => 'partial'],
            ['line' => 'Form confirmation page 404', 'mode' => 'automated'],
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function detect_form_plugins(): array {
        $out = [];
        if (defined('WPCF7_VERSION') || class_exists('WPCF7') || post_type_exists('wpcf7_contact_form')) {
            $out['cf7'] = true;
        }
        if (defined('WPFORMS_VERSION') || function_exists('wpforms') || post_type_exists('wpforms')) {
            $out['wpforms'] = true;
        }
        if (class_exists('GFCommon') || class_exists('GFForms') || function_exists('gravity_form')) {
            $out['gf'] = true;
        }
        if (class_exists('Theme_Forms')) {
            $out['theme'] = true;
        }
        return $out;
    }

    private function smtp_plugin_active(): bool {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $candidates = [
            'wp-mail-smtp/wp_mail_smtp.php',
            'wp-mail-smtp-pro/wp_mail_smtp.php',
            'easy-wp-smtp/easy-wp-smtp.php',
            'post-smtp/postman-smtp.php',
            'fluent-smtp/fluent-smtp.php',
            'smtp-mailer/main.php',
        ];
        foreach ($candidates as $plugin) {
            if (is_plugin_active($plugin)) {
                return true;
            }
        }
        return (bool) apply_filters('msm_forms_smtp_detected', false);
    }

    private function looks_like_local_mail(): bool {
        $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        return (bool) preg_match('/localhost|\\.local$|\\.test$|127\\.0\\.0\\.1/i', $host);
    }

    /**
     * @param array<string, bool> $plugins Active form plugins.
     * @return array{configured:bool,missing_secret:bool,detail:string}
     */
    private function recaptcha_signal(array $plugins): array {
        $configured = false;
        $missing    = false;
        $detail     = 'No reCAPTCHA/hCaptcha/Turnstile markers found';

        if (class_exists('Theme_Forms')) {
            $turnstile = method_exists('Theme_Forms', 'turnstile_enabled') && \Theme_Forms::turnstile_enabled();
            $recaptcha = method_exists('Theme_Forms', 'recaptcha_enabled') && \Theme_Forms::recaptcha_enabled();
            if ($turnstile || $recaptcha) {
                $configured = true;
                $detail     = $turnstile ? 'Theme Turnstile enabled' : 'Theme reCAPTCHA enabled';
            }
        }

        if (! empty($plugins['cf7'])) {
            $site_key = (string) get_option('wpcf7_recaptcha_sitekey', '');
            $secret   = (string) get_option('wpcf7_recaptcha_secret', '');
            $integ    = get_option('wpcf7', []);
            if (is_array($integ) && isset($integ['recaptcha']) && is_array($integ['recaptcha'])) {
                $site_key = $site_key !== '' ? $site_key : (string) ($integ['recaptcha']['sitekey'] ?? '');
                $secret   = $secret !== '' ? $secret : (string) ($integ['recaptcha']['secret'] ?? '');
            }
            if ($site_key !== '' || $secret !== '') {
                $configured = true;
                $detail     = 'CF7 reCAPTCHA keys present';
                if (($site_key === '') xor ($secret === '')) {
                    $missing = true;
                    $detail  = 'CF7 reCAPTCHA site/secret key mismatch';
                }
            }
        }

        return [
            'configured'     => $configured,
            'missing_secret' => $missing,
            'detail'         => $detail,
        ];
    }

    /**
     * @param array<string, mixed> $findings Findings.
     * @param array<string, bool>  $plugins  Plugins.
     * @param array<string, mixed> $recaptcha Recaptcha signal.
     * @param bool                 $smtp_ok  SMTP detected.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function build_coverage(array $findings, array $plugins, array $recaptcha, bool $smtp_ok): array {
        $coverage = [];
        foreach (self::doc_lines() as $row) {
            $line = $row['line'];
            $mode = $row['mode'];

            switch ($line) {
                case 'Contact Form 7 submissions failing':
                    if (empty($plugins['cf7'])) {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => 'skip',
                            'detail' => 'CF7 not active — skipped',
                        ];
                        break;
                    }
                    $hits = $findings['cf7_fail'] ?? [];
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'CF7 forms present with Mail templates (no live submit)'
                            : implode('; ', array_slice($hits, 0, 3)),
                    ];
                    break;

                case 'Form shortcode missing from page':
                    $hits = $findings['shortcode'] ?? [];
                    if (empty($hits) && ! empty($plugins['theme']) && empty($plugins['cf7']) && empty($plugins['wpforms']) && empty($plugins['gf'])) {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => 'pass',
                            'detail' => 'Theme_Forms pages checked (no CF7 / WPForms / Gravity Forms)',
                        ];
                        break;
                    }
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'Published forms appear embedded in content'
                            : implode('; ', array_slice($hits, 0, 3)),
                    ];
                    break;

                case 'Spam flooding forms':
                    $hits = $findings['spam'] ?? [];
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'No recent Flamingo/comment spam flood signal'
                            : implode('; ', $hits),
                    ];
                    break;

                case 'Mail not delivered from forms':
                    $hits = $findings['mail'] ?? [];
                    if (! empty($hits)) {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => 'fail',
                            'detail' => implode('; ', $hits),
                        ];
                    } else {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => $smtp_ok ? 'pass' : 'skip',
                            'detail' => $smtp_ok
                                ? 'SMTP plugin detected; delivery not verified with a test send'
                                : 'No SMTP plugin detected — verify delivery manually / with a test',
                        ];
                    }
                    break;

                case 'reCAPTCHA / honeypot blocking legit users':
                    $hits = $findings['recaptcha'] ?? [];
                    if (! empty($hits)) {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => 'fail',
                            'detail' => implode('; ', $hits),
                        ];
                    } else {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => 'manual',
                            'detail' => ($recaptcha['detail'] ?? 'OK') . ' — whether legit users are blocked needs a manual submit test',
                        ];
                    }
                    break;

                case 'Form confirmation page 404':
                    $hits = $findings['confirmation'] ?? [];
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'No broken confirmation/redirect URLs in sample'
                            : implode('; ', array_slice($hits, 0, 3)),
                    ];
                    break;

                default:
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => 'manual',
                        'detail' => 'Unhandled',
                    ];
                    break;
            }
        }

        return $coverage;
    }

    /**
     * @param string $status Status.
     * @param string $detail Detail.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function static_coverage(string $status, string $detail): array {
        $rows = [];
        foreach (self::doc_lines() as $row) {
            $rows[] = [
                'line'   => $row['line'],
                'mode'   => $row['mode'],
                'status' => $status,
                'detail' => $detail,
            ];
        }
        return $rows;
    }

    /**
     * @param string $url URL.
     * @return int|null HTTP code.
     */
    private function probe_url(string $url): ?int {
        $args = [
            'timeout'     => 8,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
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
