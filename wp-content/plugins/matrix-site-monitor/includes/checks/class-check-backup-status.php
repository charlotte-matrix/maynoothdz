<?php
/**
 * Backup plugin status check.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Backup_Status extends Check_Base {
    public function id(): string { return 'backup_status'; }
    public function label(): string { return 'Backup status'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);

        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (is_plugin_active('updraftplus/updraftplus.php')) {
            return $this->check_updraftplus($start);
        }

        return $this->skip('No supported backup plugin detected.', $start);
    }

    /**
     * Check UpdraftPlus last backup time.
     *
     * @param float $start microtime(true).
     */
    private function check_updraftplus(float $start): array {
        $last = get_option('updraft_last_backup');
        if (! is_array($last) || empty($last['backup_time'])) {
            return $this->result(false, 'UpdraftPlus has no recorded backup.', $start);
        }

        $backup_time = (int) $last['backup_time'];
        $age_days    = (time() - $backup_time) / DAY_IN_SECONDS;

        if ($age_days > 7) {
            return $this->result(
                false,
                sprintf('Last UpdraftPlus backup was %.1f days ago.', $age_days),
                $start
            );
        }

        return $this->result(
            true,
            sprintf('UpdraftPlus backup %.1f day(s) ago.', $age_days),
            $start
        );
    }
}
