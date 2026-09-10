<?php
/**
 * Activation and deactivation hooks.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

class Activator {
    /**
     * Run on plugin activation.
     */
    public static function activate(): void {
        if (! get_option(MSM_SETTINGS_OPTION)) {
            update_option(MSM_SETTINGS_OPTION, Settings::defaults(), false);
        }

        Scheduler::sync_all();
        Storage::maybe_upgrade();
    }

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate(): void {
        Scheduler::clear_all();
    }
}
