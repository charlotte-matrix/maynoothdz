<?php
/**
 * New administrator account detection.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Admin_Anomalies extends Check_Base {
    public function id(): string { return 'admin_anomalies'; }
    public function label(): string { return 'Administrator account changes'; }
    public function severity(): string { return 'critical'; }
    public function tier(): string { return 'light'; }

    public function run(): array {
        $start    = microtime(true);
        $baseline = get_option(MSM_ADMIN_BASELINE_OPTION, []);

        if (! is_array($baseline) || empty($baseline)) {
            $baseline = $this->current_admin_ids();
            update_option(MSM_ADMIN_BASELINE_OPTION, $baseline, false);
            return $this->result(true, 'Admin baseline established (' . count($baseline) . ' account(s)).', $start);
        }

        $current = $this->current_admin_ids();
        $new     = array_diff($current, $baseline);

        if ($new) {
            $names = [];
            foreach ($new as $user_id) {
                $user = get_userdata((int) $user_id);
                if ($user) {
                    $names[] = $user->user_login . ' (#' . $user_id . ')';
                }
            }

            update_option(MSM_ADMIN_BASELINE_OPTION, $current, false);

            return $this->result(
                false,
                'New administrator account(s): ' . implode(', ', $names) . '.',
                $start
            );
        }

        $removed = array_diff($baseline, $current);
        if ($removed) {
            update_option(MSM_ADMIN_BASELINE_OPTION, $current, false);
        }

        return $this->result(true, count($current) . ' administrator account(s), no new additions.', $start);
    }

    /**
     * Get IDs of users with administrator role.
     *
     * @return array<int, int>
     */
    private function current_admin_ids(): array {
        $users = get_users(['role' => 'administrator', 'fields' => 'ID']);
        return array_map('intval', (array) $users);
    }
}
