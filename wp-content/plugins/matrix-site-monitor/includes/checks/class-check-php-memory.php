<?php
/**
 * PHP memory limit sanity check.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Php_Memory extends Check_Base {
    public function id(): string { return 'php_memory'; }
    public function label(): string { return 'PHP memory limit'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);
        $limit = (string) ini_get('memory_limit');
        $bytes = $this->to_bytes($limit);

        if ($bytes < 0) {
            return $this->pass('PHP memory_limit is unlimited (' . $limit . ').', $start);
        }

        $wp_limit = defined('WP_MEMORY_LIMIT') ? (string) WP_MEMORY_LIMIT : '';
        $min      = 128 * 1024 * 1024;

        if ($bytes > 0 && $bytes < $min) {
            return $this->fail(
                sprintf('PHP memory_limit is %s (below recommended 128M). WP_MEMORY_LIMIT=%s.', $limit, $wp_limit ?: 'n/a'),
                $start
            );
        }

        return $this->pass(sprintf('PHP memory_limit is %s.', $limit), $start);
    }

    /**
     * Convert PHP size string to bytes.
     *
     * @param string $value Size string.
     */
    private function to_bytes(string $value): int {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $num  = (int) $value;

        switch ($unit) {
            case 'g':
                return $num * 1024 * 1024 * 1024;
            case 'm':
                return $num * 1024 * 1024;
            case 'k':
                return $num * 1024;
            default:
                return (int) $value;
        }
    }
}
