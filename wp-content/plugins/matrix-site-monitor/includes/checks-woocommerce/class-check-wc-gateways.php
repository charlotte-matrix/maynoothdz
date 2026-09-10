<?php
/**
 * WooCommerce payment gateways availability.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks_Woocommerce;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Wc_Gateways extends Check_Base {
    public function id(): string { return 'wc_gateways'; }
    public function label(): string { return 'Payment gateways available'; }
    public function severity(): string { return 'critical'; }
    public function tier(): string { return 'synthetic'; }

    public function run(): array {
        $start = microtime(true);

        if (! function_exists('WC') || ! WC()->payment_gateways()) {
            return $this->fail('WooCommerce payment gateways API unavailable.', $start);
        }

        // get_available_payment_gateways() is empty in admin/cron without a cart —
        // check enabled gateways instead (still catches "all gateways disabled").
        $all     = WC()->payment_gateways()->payment_gateways();
        $enabled = [];

        foreach ((array) $all as $id => $gateway) {
            if (isset($gateway->enabled) && $gateway->enabled === 'yes') {
                $enabled[$id] = $gateway->get_title() ?: $id;
            }
        }

        if (empty($enabled)) {
            return $this->fail('No payment gateways are enabled in WooCommerce settings.', $start);
        }

        return $this->pass(
            count($enabled) . ' gateway(s) enabled: ' . implode(', ', $enabled) . '.',
            $start
        );
    }
}
