<?php
/**
 * Security advisories check via WP.org plugin API compatibility data.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;
use Matrix_Site_Monitor\Settings;

defined('ABSPATH') || exit;

class Check_Security_Advisories extends Check_Base {
    public function id(): string { return 'security_advisories'; }
    public function label(): string { return 'Security advisories'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start  = microtime(true);
        $issues = [];

        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        global $wp_version;
        foreach (get_plugins() as $plugin_file => $plugin_data) {
            if (! is_plugin_active($plugin_file)) {
                continue;
            }

            $tested = isset($plugin_data['Tested']) ? trim((string) $plugin_data['Tested']) : '';
            $name   = (string) ($plugin_data['Name'] ?? $plugin_file);

            if ($tested && version_compare($tested, $wp_version, '<')) {
                $issues[] = $name . ' not tested for WP ' . $wp_version;
            }
        }

        $wpscan_issues = $this->wpscan_vulnerabilities();
        $issues = array_merge($issues, $wpscan_issues);

        if ($issues) {
            $display = array_slice($issues, 0, 5);
            $suffix  = count($issues) > 5 ? ' (+' . (count($issues) - 5) . ' more)' : '';
            return $this->result(false, implode('; ', $display) . $suffix . '.', $start);
        }

        return $this->result(true, 'No known security advisories detected.', $start);
    }

    /**
     * Query WPScan API for vulnerabilities (cached 24h).
     *
     * @return array<int, string>
     */
    private function wpscan_vulnerabilities(): array {
        $settings = Settings::get();
        $token    = trim((string) ($settings['wpscan_api_token'] ?? ''));

        if ($token === '') {
            return [];
        }

        $cache_key = 'msm_wpscan_' . md5($token);
        $cached    = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $issues = [];

        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach (get_plugins() as $plugin_file => $plugin_data) {
            if (! is_plugin_active($plugin_file)) {
                continue;
            }

            $slug = dirname($plugin_file);
            if ($slug === '.') {
                continue;
            }

            $response = wp_remote_get(
                'https://wpscan.com/api/v3/plugins/' . rawurlencode($slug),
                [
                    'timeout' => 15,
                    'headers' => ['Authorization' => 'Token token=' . $token],
                ]
            );

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                continue;
            }

            $body = json_decode((string) wp_remote_retrieve_body($response), true);
            if (! is_array($body) || empty($body[$slug]['vulnerabilities'])) {
                continue;
            }

            $installed_version = (string) ($plugin_data['Version'] ?? '');
            foreach ($body[$slug]['vulnerabilities'] as $vuln) {
                $fixed = (string) ($vuln['fixed_in'] ?? '');
                if ($fixed === '' || version_compare($installed_version, $fixed, '<')) {
                    $title = (string) ($vuln['title'] ?? 'Unknown vulnerability');
                    $issues[] = ($plugin_data['Name'] ?? $slug) . ': ' . $title;
                }
            }
        }

        set_transient($cache_key, $issues, DAY_IN_SECONDS);
        return $issues;
    }
}
