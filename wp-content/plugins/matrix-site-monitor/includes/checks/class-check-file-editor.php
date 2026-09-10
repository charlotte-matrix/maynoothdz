<?php
/**
 * Theme/plugin file editor should be disabled on live sites.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_File_Editor extends Check_Base {
    public function id(): string { return 'file_editor'; }
    public function label(): string { return 'Theme/plugin file editor'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);

        if ($this->is_dev_environment()) {
            return $this->skip('Skipped in development QA profile. Use live profile for go-live checks.', $start);
        }

        if (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) {
            return $this->pass('DISALLOW_FILE_EDIT is enabled.', $start);
        }

        return $this->fail('Theme/plugin file editor is enabled. Set DISALLOW_FILE_EDIT to true in wp-config.php.', $start);
    }
}
