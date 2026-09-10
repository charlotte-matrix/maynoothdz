<?php
/**
 * Admin UI for Matrix Site Monitor.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Admin;

use Matrix_Site_Monitor\Check_Registry;
use Matrix_Site_Monitor\Check_Runner;
use Matrix_Site_Monitor\Report;
use Matrix_Site_Monitor\Settings;
use Matrix_Site_Monitor\Storage;

defined('ABSPATH') || exit;

class Admin {
    /**
     * Register admin hooks.
     */
    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_msm_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_msm_run_checks', [$this, 'handle_run_checks']);
        add_action('admin_post_msm_download_report', [$this, 'handle_download_report']);
    }

    /**
     * Enqueue admin CSS/JS on our page only.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets(string $hook): void {
        if ($hook !== 'tools_page_matrix-site-monitor') {
            return;
        }
        wp_enqueue_style(
            'msm-admin',
            MSM_URL . 'assets/admin.css',
            [],
            MSM_VERSION
        );
        wp_enqueue_script(
            'msm-admin',
            MSM_URL . 'assets/admin.js',
            [],
            MSM_VERSION,
            true
        );
    }

    /**
     * Register tools submenu.
     */
    public function register_menu(): void {
        $fail_count = self::menu_fail_count();
        $title      = __('Site Monitor', 'matrix-site-monitor');
        if ($fail_count > 0) {
            $title = sprintf(
                /* translators: %s: failure count bubble */
                __('Site Monitor %s', 'matrix-site-monitor'),
                '<span class="awaiting-mod">' . (int) $fail_count . '</span>'
            );
        }

        add_management_page(
            __('Site Monitor', 'matrix-site-monitor'),
            $title,
            'manage_options',
            'matrix-site-monitor',
            [$this, 'render_page']
        );
    }

    /**
     * Count currently failing checks for the Tools menu badge.
     */
    private static function menu_fail_count(): int {
        $map = Storage::get_check_results();
        if (empty($map) || ! is_array($map)) {
            return 0;
        }
        $disabled = Settings::get()['disabled_checks'] ?? [];
        if (! is_array($disabled)) {
            $disabled = [];
        }
        $n = 0;
        foreach ($map as $id => $row) {
            if (! is_array($row) || in_array($id, $disabled, true)) {
                continue;
            }
            if (Admin_View::status_key($row) === 'fail') {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Render admin page.
     */
    public function render_page(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings  = Settings::get();
        $light     = Storage::get_tier_result('light');
        $heavy     = Storage::get_tier_result('heavy');
        $synthetic = Storage::get_tier_result('synthetic');
        $single    = get_option('msm_last_single_result', []);
        $catalog   = Check_Registry::catalog();
        $results_map = Storage::get_check_results();

        // Backfill from older tier payloads if the rolling map is empty.
        if (empty($results_map)) {
            foreach ([$light, $heavy, $synthetic] as $payload) {
                if (! empty($payload['results']) && is_array($payload['results'])) {
                    Storage::merge_check_results(
                        $payload['results'],
                        (string) ($payload['time'] ?? current_time('mysql')),
                        (string) ($payload['tier'] ?? '')
                    );
                }
            }
            if (! empty($single['results']) && is_array($single['results'])) {
                Storage::merge_check_results(
                    $single['results'],
                    (string) ($single['time'] ?? current_time('mysql')),
                    'single'
                );
            }
            $results_map = Storage::get_check_results();
        }

        include MSM_DIR . 'includes/admin/views/settings.php';
    }

    /**
     * Handle settings form save.
     */
    public function handle_save_settings(): void {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'matrix-site-monitor'));
        }

        check_admin_referer('msm_save_settings');

        $settings = Settings::get();

        $settings['alert_emails']            = sanitize_text_field(wp_unslash($_POST['alert_emails'] ?? ''));
        $settings['digest_emails']           = sanitize_text_field(wp_unslash($_POST['digest_emails'] ?? ''));
        $settings['digest_enabled']          = ! empty($_POST['digest_enabled']) ? 1 : 0;
        $settings['digest_day']              = sanitize_key(wp_unslash($_POST['digest_day'] ?? 'monday'));
        $settings['digest_time']             = sanitize_text_field(wp_unslash($_POST['digest_time'] ?? '09:00'));
        $settings['smoke_urls']              = sanitize_textarea_field(wp_unslash($_POST['smoke_urls'] ?? ''));
        $settings['smoke_page_sample']       = max(0, min(50, (int) ($_POST['smoke_page_sample'] ?? 8)));
        $settings['smoke_post_sample']       = max(0, min(20, (int) ($_POST['smoke_post_sample'] ?? 3)));
        $settings['disk_threshold_percent']  = max(1, min(95, (int) ($_POST['disk_threshold_percent'] ?? 15)));
        $settings['disk_threshold_gb']       = max(0.5, (float) ($_POST['disk_threshold_gb'] ?? 5));
        $settings['disk_threshold_mode']     = in_array($_POST['disk_threshold_mode'] ?? '', ['percent', 'gb'], true)
            ? $_POST['disk_threshold_mode'] : 'percent';
        $settings['ssl_warn_days']           = max(1, (int) ($_POST['ssl_warn_days'] ?? 14));
        $settings['enable_rest']             = ! empty($_POST['enable_rest']) ? 1 : 0;
        $settings['api_token']               = sanitize_text_field(wp_unslash($_POST['api_token'] ?? ''));
        $settings['webhook_url']             = esc_url_raw(wp_unslash($_POST['webhook_url'] ?? ''));
        $settings['wc_enabled']              = ! empty($_POST['wc_enabled']) ? 1 : 0;
        $settings['wc_coupon_code']          = sanitize_text_field(wp_unslash($_POST['wc_coupon_code'] ?? ''));
        $settings['wc_simple_product_id']    = (int) ($_POST['wc_simple_product_id'] ?? 0);
        $settings['wc_variation_id']         = (int) ($_POST['wc_variation_id'] ?? 0);
        $settings['wc_skip_if_light_failed'] = ! empty($_POST['wc_skip_if_light_failed']) ? 1 : 0;
        $settings['synthetic_interval']      = in_array($_POST['synthetic_interval'] ?? '', ['hourly', 'four_hours'], true)
            ? $_POST['synthetic_interval'] : 'hourly';
        $settings['heavy_schedule_time']     = sanitize_text_field(wp_unslash($_POST['heavy_schedule_time'] ?? '03:00'));
        $settings['wpscan_api_token']        = sanitize_text_field(wp_unslash($_POST['wpscan_api_token'] ?? ''));
        $settings['expect_analytics']        = ! empty($_POST['expect_analytics']) ? 1 : 0;
        $settings['critical_paths']          = sanitize_textarea_field(wp_unslash($_POST['critical_paths'] ?? ''));
        $settings['contact_path']            = sanitize_text_field(wp_unslash($_POST['contact_path'] ?? '/contact/'));
        $settings['response_time_ms']        = max(200, (int) ($_POST['response_time_ms'] ?? 1500));
        $settings['max_link_checks']         = max(1, min(200, (int) ($_POST['max_link_checks'] ?? 60)));
        $settings['request_timeout']         = max(3, min(60, (int) ($_POST['request_timeout'] ?? 10)));
        $settings['sitemap_paths']           = sanitize_textarea_field(wp_unslash($_POST['sitemap_paths'] ?? ''));
        $settings['redirect_rules']          = sanitize_textarea_field(wp_unslash($_POST['redirect_rules'] ?? ''));
        $settings['ga4_measurement_ids']     = sanitize_text_field(wp_unslash($_POST['ga4_measurement_ids'] ?? ''));
        $settings['gtm_container_ids']       = sanitize_text_field(wp_unslash($_POST['gtm_container_ids'] ?? ''));
        $settings['banned_plugins']          = sanitize_textarea_field(wp_unslash($_POST['banned_plugins'] ?? ''));
        $qa_profile                          = sanitize_key(wp_unslash($_POST['qa_profile'] ?? 'auto'));
        $settings['qa_profile']              = in_array($qa_profile, ['auto', 'development', 'live'], true) ? $qa_profile : 'auto';

        // Enabled checkboxes → disabled_checks is the inverse.
        $enabled_posted = isset($_POST['enabled_checks']) && is_array($_POST['enabled_checks'])
            ? array_map('sanitize_key', wp_unslash($_POST['enabled_checks']))
            : [];
        $disabled = [];
        foreach (Check_Registry::catalog() as $check) {
            if (! in_array($check->id(), $enabled_posted, true)) {
                $disabled[] = $check->id();
            }
        }
        $settings['disabled_checks'] = $disabled;

        if ($settings['api_token'] === '' && ! empty($settings['enable_rest'])) {
            $settings['api_token'] = wp_generate_password(32, false);
        }

        Settings::update($settings);

        wp_safe_redirect(add_query_arg([
            'page'    => 'matrix-site-monitor',
            'tab'     => 'settings',
            'updated' => '1',
        ], admin_url('tools.php')));
        exit;
    }

    /**
     * Handle manual check run.
     */
    public function handle_run_checks(): void {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'matrix-site-monitor'));
        }

        check_admin_referer('msm_run_checks');

        $tier     = sanitize_key(wp_unslash($_POST['tier'] ?? 'light'));
        $check_id = sanitize_key(wp_unslash($_POST['check_id'] ?? ''));
        $runner   = new Check_Runner();

        if ($tier === 'single' && $check_id !== '') {
            $runner->run_check($check_id);
            wp_safe_redirect(add_query_arg([
                'page'  => 'matrix-site-monitor',
                'tab'   => 'failures',
                'ran'   => 'single',
                'check' => $check_id,
            ], admin_url('tools.php')));
            exit;
        }

        if ($tier === 'all') {
            $runner->run_all();
        } else {
            $runner->run_tier($tier, true);
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'matrix-site-monitor',
            'tab'  => 'failures',
            'ran'  => $tier,
        ], admin_url('tools.php')));
        exit;
    }

    /**
     * Stream HTML triage report download.
     */
    public function handle_download_report(): void {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'matrix-site-monitor'));
        }

        check_admin_referer('msm_download_report');
        Report::stream_download();
    }
}
