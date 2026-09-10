<?php
/**
 * WooCommerce failed-orders spike check.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks_Woocommerce;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Wc_Failed_Orders extends Check_Base {
    public function id(): string { return 'wc_failed_orders'; }
    public function label(): string { return 'Failed orders spike'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'synthetic'; }

    public function run(): array {
        $start = microtime(true);

        $failed = wc_get_orders([
            'status'       => 'failed',
            'limit'        => 50,
            'date_created' => '>' . (time() - DAY_IN_SECONDS),
            'return'       => 'ids',
        ]);

        $count = is_array($failed) ? count($failed) : 0;
        $threshold = (int) apply_filters('msm_wc_failed_orders_threshold', 10);

        if ($count >= $threshold) {
            return $this->fail(
                sprintf('%d failed order(s) in the last 24 hours (threshold %d).', $count, $threshold),
                $start
            );
        }

        return $this->pass(
            sprintf('%d failed order(s) in the last 24 hours.', $count),
            $start
        );
    }
}
