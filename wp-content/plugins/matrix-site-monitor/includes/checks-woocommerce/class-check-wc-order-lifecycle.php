<?php
/**
 * WooCommerce guest order lifecycle check.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks_Woocommerce;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Wc_Order_Lifecycle extends Check_Base {
    public function id(): string { return 'wc_order_lifecycle'; }
    public function label(): string { return 'Guest order lifecycle'; }
    public function severity(): string { return 'critical'; }
    public function tier(): string { return 'synthetic'; }

    public function run(): array {
        $start = microtime(true);

        if (get_option('woocommerce_enable_guest_checkout') !== 'yes') {
            return $this->skip('Guest checkout is disabled.', $start);
        }

        $product_id = Wc_Test_Factory::discover_variation() ?: Wc_Test_Factory::discover_simple_product();
        if (! $product_id) {
            return $this->result(false, 'No purchasable product for order test.', $start);
        }

        $coupon = Wc_Test_Factory::coupon_code();
        $order  = Wc_Test_Factory::create_test_order($product_id, $coupon);

        if (! $order) {
            return $this->result(false, 'Failed to create test order.', $start);
        }

        $order_id = $order->get_id();
        $total    = $order->get_total();
        $items    = count($order->get_items());

        Wc_Test_Factory::teardown_order($order);

        if ($items < 1) {
            return $this->result(false, 'Test order #' . $order_id . ' had no line items.', $start);
        }

        return $this->result(
            true,
            sprintf('Order #%d created (total %s, %d item(s)) and cleaned up.', $order_id, $total, $items),
            $start
        );
    }
}
