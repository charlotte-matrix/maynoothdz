<?php
/**
 * Site profile example — copy to config/site-profile.php and adjust.
 *
 * @package Matrix_Site_Monitor
 */

defined('ABSPATH') || exit;

/**
 * Add site-specific smoke test URLs (only real, existing paths).
 *
 * @param array<string, string> $urls URL map.
 * @return array<string, string>
 */
add_filter('msm_smoke_urls', static function (array $urls): array {
    // Example: $urls['about'] = home_url('/about/');
    return $urls;
});

/**
 * Add site-specific WooCommerce page URLs for synthetic checks.
 *
 * @param array<string, string> $urls URL map.
 * @return array<string, string>
 */
add_filter('msm_wc_smoke_urls', static function (array $urls): array {
    // Prefer auto-discovered product category from Check_Wc_Pages.
    // Only add hard-coded paths you have verified return 200.
    return $urls;
});

/**
 * Override default check config for this site.
 *
 * @param array<string, mixed> $settings Settings array.
 * @return array<string, mixed>
 */
add_filter('msm_check_config', static function (array $settings): array {
    if ($settings['alert_emails'] === '') {
        $settings['alert_emails'] = 'developers@matrixinternet.ie';
    }

    // Prefer absolute GB free on shared/local disks where % free is noisy.
    $settings['disk_threshold_mode'] = 'gb';
    $settings['disk_threshold_gb']   = 5;

    // Preflight (MGPC) — site-specific paths / IDs.
    // $settings['critical_paths'] = "/\n/contact/\n/about/";
    // $settings['contact_path'] = '/contact/';
    // $settings['response_time_ms'] = 1500;
    // $settings['ga4_measurement_ids'] = 'G-XXXXXXXX';
    // $settings['gtm_container_ids'] = 'GTM-XXXXXXX';
    // $settings['redirect_rules'] = "/old-page => /new-page\n";
    // $settings['qa_profile'] = 'live'; // go-live QA even when WP_ENVIRONMENT_TYPE is local

    // Extra banned plugins (File Manager family is always denylisted).
    // $settings['banned_plugins'] = "hello-dolly\nsome-risky-plugin";

    return $settings;
});

/**
 * Full denylist override/extend (slugs, file paths, or name substrings).
 *
 * @param array<int, string> $rules Rules.
 * @return array<int, string>
 */
add_filter('msm_banned_plugins', static function (array $rules): array {
    // Example: $rules[] = 'all-in-one-wp-migration';
    return $rules;
});

/**
 * Site-specific checks: put classes in config/site-checks/class-check-*.php
 * and follow docs/SITE_SPECIFIC_CHECKS.md (Cursor prompt included there).
 */
