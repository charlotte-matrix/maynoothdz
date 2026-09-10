<?php
/**
 * Plugin settings helpers.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

class Settings {
    /**
     * Default settings.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array {
        return [
            'alert_emails'              => '',
            'digest_emails'             => '',
            'digest_enabled'            => 0,
            'digest_day'                => 'monday',
            'digest_time'               => '09:00',
            'smoke_urls'                => '',
            'smoke_page_sample'         => 8,
            'smoke_post_sample'         => 3,
            'disk_threshold_percent'    => 15,
            'disk_threshold_gb'         => 5,
            'disk_threshold_mode'       => 'gb',
            'ssl_warn_days'             => 14,
            'enable_rest'               => 0,
            'api_token'                 => '',
            'webhook_url'               => '',
            'wc_enabled'                => 1,
            'wc_coupon_code'            => '',
            'wc_simple_product_id'      => 0,
            'wc_variation_id'           => 0,
            'wc_skip_if_light_failed'   => 1,
            'synthetic_interval'        => 'hourly',
            'heavy_schedule_time'       => '03:00',
            'wpscan_api_token'          => '',
            'expect_analytics'          => 0,
            'content_link_sample'       => 8,
            'content_image_sample'      => 6,
            'disabled_checks'           => [],
            // Preflight (MGPC) suite config.
            'critical_paths'            => "/\n/contact/",
            'contact_path'              => '/contact/',
            'response_time_ms'          => 1500,
            'max_link_checks'           => 60,
            'request_timeout'           => 10,
            'sitemap_paths'             => "/sitemap_index.xml\n/wp-sitemap.xml\n/sitemap.xml",
            'redirect_rules'            => '',
            'ga4_measurement_ids'       => '',
            'gtm_container_ids'         => '',
            // Extra banned plugin slugs/names (defaults always include file-manager family).
            'banned_plugins'            => '',
            // auto = follow WP_ENVIRONMENT_TYPE; development = skip live-only rules; live = production QA.
            'qa_profile'                => 'auto',
        ];
    }

    /**
     * Parse newline- or comma-separated paths (leading slash optional).
     *
     * @param string $raw Raw paths.
     * @return array<int, string>
     */
    public static function parse_path_list(string $raw): array {
        $raw   = str_replace([',', ';'], "\n", $raw);
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $out   = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (preg_match('#^https?://#i', $line)) {
                $path = (string) wp_parse_url($line, PHP_URL_PATH);
                $line = $path !== '' ? $path : '/';
            }
            $out[] = '/' . ltrim($line, '/');
        }
        return array_values(array_unique($out));
    }

    /**
     * Parse comma/newline separated tokens (GA/GTM IDs, etc.).
     *
     * @param string $raw Raw list.
     * @return array<int, string>
     */
    public static function parse_csv_list(string $raw): array {
        $raw   = str_replace([';', ','], "\n", $raw);
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $out   = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $out[] = $line;
        }
        return array_values(array_unique($out));
    }

    /**
     * Parse redirect rules: one "from => to" or "from|to" per line.
     *
     * @param string $raw Raw rules.
     * @return array<int, array{from:string,to:string}>
     */
    public static function parse_redirect_rules(string $raw): array {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $out   = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (preg_match('/^(.+?)\s*(?:=>|->|\|)\s*(.+)$/', $line, $m)) {
                $from = trim($m[1]);
                $to   = trim($m[2]);
                if ($from !== '' && $to !== '') {
                    $out[] = [
                        'from' => '/' . ltrim($from, '/'),
                        'to'   => $to,
                    ];
                }
            }
        }
        return $out;
    }

    /**
     * One-time: enable all checks by default (clears earlier opt-in seed).
     */
    public static function maybe_seed_opt_in_checks(): void {
        if (get_option('msm_enable_all_checks_v2')) {
            return;
        }

        $stored = get_option(MSM_SETTINGS_OPTION, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        $stored['disabled_checks'] = [];
        update_option(MSM_SETTINGS_OPTION, array_merge(self::defaults(), $stored), false);
        update_option('msm_enable_all_checks_v2', 1, false);
        update_option('msm_check_toggles_v1', 1, false);
    }

    /**
     * No-op kept for backward compatibility with older boot calls.
     *
     * @param array<int, string> $ids Check IDs.
     */
    public static function maybe_disable_new_opt_in(array $ids): void {
        unset($ids);
    }

    /**
     * Get merged settings.
     *
     * @return array<string, mixed>
     */
    public static function get(): array {
        $stored = get_option(MSM_SETTINGS_OPTION, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        $settings = array_merge(self::defaults(), $stored);
        return (array) apply_filters('msm_check_config', $settings);
    }

    /**
     * Update settings.
     *
     * @param array<string, mixed> $settings Settings to save.
     */
    public static function update(array $settings): void {
        $merged = array_merge(self::defaults(), $settings);
        update_option(MSM_SETTINGS_OPTION, $merged, false);
        Scheduler::sync_all();
    }

    /**
     * Parse comma-separated email list.
     *
     * @param string $raw Raw email string.
     * @return array<int, string>
     */
    public static function parse_emails(string $raw): array {
        $emails = array_filter(array_map('trim', explode(',', $raw)));
        $valid  = [];

        foreach ($emails as $email) {
            $sanitized = sanitize_email($email);
            if ($sanitized !== '') {
                $valid[] = $sanitized;
            }
        }

        return array_values(array_unique($valid));
    }

    /**
     * Get alert recipient emails.
     *
     * @return array<int, string>
     */
    public static function alert_recipients(): array {
        $settings = self::get();
        $emails   = self::parse_emails((string) ($settings['alert_emails'] ?? ''));

        if (empty($emails)) {
            $admin = get_option('admin_email');
            if (is_string($admin) && $admin !== '') {
                $emails[] = sanitize_email($admin);
            }
        }

        return $emails;
    }

    /**
     * Get digest recipient emails.
     *
     * @return array<int, string>
     */
    public static function digest_recipients(): array {
        $settings = self::get();
        return self::parse_emails((string) ($settings['digest_emails'] ?? ''));
    }

    /**
     * CLI/runtime override: auto | development | live.
     *
     * @var string|null
     */
    private static $qa_profile_override = null;

    /**
     * Override QA profile for this request (CLI --profile).
     */
    public static function set_qa_profile_override(string $profile): void {
        $profile = strtolower(trim($profile));
        if (in_array($profile, ['auto', 'development', 'live'], true)) {
            self::$qa_profile_override = $profile;
        }
    }

    /**
     * Resolved QA profile used by checks: development or live.
     */
    public static function qa_profile(): string {
        $source = self::$qa_profile_override;
        if ($source === null) {
            $source = (string) (self::get()['qa_profile'] ?? 'auto');
            $source = (string) apply_filters('msm_qa_profile', $source);
        }
        if ($source === 'development' || $source === 'live') {
            return $source;
        }
        return self::wp_environment_is_dev() ? 'development' : 'live';
    }

    /**
     * Whether checks should use relaxed development rules (skip Redis, file editor, public debug, XML-RPC).
     */
    public static function is_dev_profile(): bool {
        return self::qa_profile() === 'development';
    }

    /**
     * WordPress environment type is local/development.
     */
    public static function wp_environment_is_dev(): bool {
        if (function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), ['local', 'development'], true)) {
            return true;
        }
        return defined('WP_LOCAL_DEV') && WP_LOCAL_DEV;
    }

    /**
     * Whether a check is disabled.
     *
     * @param string $check_id Check identifier.
     */
    public static function is_check_disabled(string $check_id): bool {
        $settings  = self::get();
        $disabled  = $settings['disabled_checks'] ?? [];
        if (! is_array($disabled)) {
            return false;
        }
        return in_array($check_id, $disabled, true);
    }
}
