<?php
/**
 * Plugin, theme, and core update availability check.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Update_Availability extends Check_Base {
    public function id(): string { return 'update_availability'; }
    public function label(): string { return 'Pending updates'; }
    public function severity(): string { return 'info'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);

        if (! function_exists('wp_get_update_data')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }

        wp_update_plugins();
        wp_update_themes();

        $data = wp_get_update_data();
        $counts = $data['counts'] ?? [];

        $total = (int) ($counts['total'] ?? 0);
        $plugins = (int) ($counts['plugins'] ?? 0);
        $themes = (int) ($counts['themes'] ?? 0);
        $core = (int) ($counts['wordpress'] ?? 0);

        if ($total > 0) {
            return $this->result(
                false,
                sprintf('%d update(s) pending: %d plugin(s), %d theme(s), %d core.', $total, $plugins, $themes, $core),
                $start
            );
        }

        return $this->result(true, 'No pending updates.', $start);
    }
}
