<?php
/**
 * PHP and WordPress version check.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Php_Wp_Versions extends Check_Base {
    public function id(): string { return 'php_wp_versions'; }
    public function label(): string { return 'PHP and WordPress versions'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start  = microtime(true);
        $issues = [];

        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $issues[] = 'PHP ' . PHP_VERSION . ' is below 8.0 (EOL risk)';
        }

        global $wp_version;
        $updates = get_site_transient('update_core');
        if (is_object($updates) && ! empty($updates->updates)) {
            foreach ($updates->updates as $update) {
                if (isset($update->response) && $update->response === 'upgrade') {
                    $issues[] = 'WordPress core update available (' . ($update->version ?? 'unknown') . ')';
                    break;
                }
            }
        }

        if ($issues) {
            return $this->result(false, implode('; ', $issues) . '.', $start);
        }

        return $this->result(
            true,
            sprintf('PHP %s, WordPress %s.', PHP_VERSION, $wp_version),
            $start
        );
    }
}
