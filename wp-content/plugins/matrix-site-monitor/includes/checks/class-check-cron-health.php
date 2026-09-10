<?php
/**
 * WP-Cron health check.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Cron_Health extends Check_Base {
    public function id(): string { return 'cron_health'; }
    public function label(): string { return 'WP-Cron health'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'light'; }

    public function run(): array {
        $start  = microtime(true);
        $issues = [];

        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $issues[] = 'DISABLE_WP_CRON is true — ensure system cron triggers wp-cron.php';
        }

        $cron = _get_cron_array();
        if (! is_array($cron)) {
            return $this->result(false, 'Unable to read cron array.', $start);
        }

        $now      = time();
        $overdue  = 0;
        foreach ($cron as $timestamp => $hooks) {
            if ($timestamp < ($now - DAY_IN_SECONDS)) {
                $overdue += count((array) $hooks);
            }
        }

        if ($overdue > 0) {
            $issues[] = $overdue . ' cron event(s) overdue by more than 24 hours';
        }

        if (function_exists('as_get_scheduled_actions')) {
            $pending = as_get_scheduled_actions([
                'status'   => 'pending',
                'per_page' => 1,
                'date'     => gmdate('Y-m-d H:i:s', $now - DAY_IN_SECONDS),
                'date_compare' => '<',
            ], 'ids');
            if (! empty($pending)) {
                $issues[] = 'Action Scheduler has overdue pending actions';
            }
        }

        if ($issues) {
            return $this->result(false, implode('; ', $issues) . '.', $start);
        }

        return $this->result(true, 'Cron appears healthy.', $start);
    }
}
