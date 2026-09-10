<?php
/**
 * Detect WordPress stuck in maintenance mode.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Maintenance_Mode extends Check_Base {
    public function id(): string { return 'maintenance_mode'; }
    public function label(): string { return 'Maintenance mode'; }
    public function severity(): string { return 'critical'; }
    public function tier(): string { return 'light'; }

    public function run(): array {
        $start = microtime(true);
        $file  = ABSPATH . '.maintenance';

        if (file_exists($file)) {
            $age = time() - (int) filemtime($file);
            return $this->fail(
                sprintf('.maintenance file present (age %d minute(s)). Site may be stuck in maintenance mode.', (int) floor($age / 60)),
                $start
            );
        }

        return $this->pass('Site is not in maintenance mode.', $start);
    }
}
