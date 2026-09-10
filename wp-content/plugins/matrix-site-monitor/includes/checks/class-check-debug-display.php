<?php
/**
 * Detect WP_DEBUG_DISPLAY exposing errors publicly.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;
use Matrix_Site_Monitor\Settings;

defined('ABSPATH') || exit;

class Check_Debug_Display extends Check_Base {
    public function id(): string { return 'debug_display'; }
    public function label(): string { return 'Public PHP error display'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'light'; }

    public function run(): array {
        $start = microtime(true);

        if ($this->is_dev_environment()) {
            return $this->skip('Skipped in development QA profile (display_errors often On). Use live profile for go-live checks.', $start);
        }

        $debug          = defined('WP_DEBUG') && WP_DEBUG;
        $debug_display  = defined('WP_DEBUG_DISPLAY') ? (bool) WP_DEBUG_DISPLAY : true;
        $display_errors = (string) ini_get('display_errors');
        $ini_on         = ($display_errors === '1' || strtolower($display_errors) === 'on');

        if ($debug && $debug_display) {
            return $this->fail('WP_DEBUG and WP_DEBUG_DISPLAY are enabled — PHP errors may show to visitors.', $start);
        }

        if ($ini_on && Settings::wp_environment_is_dev()) {
            return $this->skip('Local php.ini has display_errors On. WP_DEBUG is off; confirm Off on production.', $start);
        }

        if ($ini_on) {
            return $this->fail('PHP display_errors is On — warnings may appear publicly.', $start);
        }

        return $this->pass('Error display is not exposed publicly.', $start);
    }
}
