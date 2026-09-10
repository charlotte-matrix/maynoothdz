<?php
/**
 * Fatal errors since last check.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;
use Matrix_Site_Monitor\Fatal_Error_Logger;
use Matrix_Site_Monitor\Storage;

defined('ABSPATH') || exit;

class Check_Fatal_Errors extends Check_Base {
    public function id(): string { return 'fatal_errors'; }
    public function label(): string { return 'Recent PHP fatal errors'; }
    public function severity(): string { return 'critical'; }
    public function tier(): string { return 'light'; }

    public function run(): array {
        $start  = microtime(true);
        $errors = Storage::get_fatal_errors();

        $last_run = get_option('msm_fatal_check_since', 0);
        $recent   = [];

        foreach ($errors as $error) {
            if (! is_array($error) || Fatal_Error_Logger::is_noise($error)) {
                continue;
            }
            $at = isset($error['occurred_at']) ? strtotime((string) $error['occurred_at']) : 0;
            if ($at >= (int) $last_run) {
                $recent[] = $error;
            }
        }

        update_option('msm_fatal_check_since', time(), false);

        if ($recent) {
            $first = $recent[0];
            $msg   = sprintf(
                '%d fatal error(s) since last check. Latest: %s',
                count($recent),
                (string) ($first['message'] ?? 'unknown')
            );
            return $this->result(false, $msg, $start);
        }

        return $this->result(true, 'No new fatal errors since last check.', $start);
    }
}
