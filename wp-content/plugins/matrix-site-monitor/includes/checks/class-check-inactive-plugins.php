<?php
/**
 * Inactive / unused plugins check.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Inactive_Plugins extends Check_Base {
    public function id(): string { return 'inactive_plugins'; }
    public function label(): string { return 'Inactive plugins'; }
    public function severity(): string { return 'info'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);

        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all      = get_plugins();
        $active   = (array) get_option('active_plugins', []);
        $inactive = [];

        foreach ($all as $file => $data) {
            if (! in_array($file, $active, true)) {
                $inactive[] = (string) ($data['Name'] ?? $file);
            }
        }

        $count = count($inactive);
        if ($count >= 5) {
            $sample = array_slice($inactive, 0, 5);
            $suffix = $count > 5 ? ' (+' . ($count - 5) . ' more)' : '';
            return $this->fail(
                $count . ' inactive plugin(s): ' . implode(', ', $sample) . $suffix . '.',
                $start
            );
        }

        if ($count > 0) {
            return $this->pass($count . ' inactive plugin(s) (under threshold).', $start);
        }

        return $this->pass('No inactive plugins.', $start);
    }
}
