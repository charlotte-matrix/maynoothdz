<?php
/**
 * WooCommerce coupon validation check.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks_Woocommerce;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Wc_Coupon extends Check_Base {
    public function id(): string { return 'wc_coupon'; }
    public function label(): string { return 'Coupon validation'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'synthetic'; }

    public function run(): array {
        $start = microtime(true);
        $code  = Wc_Test_Factory::coupon_code();

        if ($code === '') {
            return $this->skip('No test coupon configured.', $start);
        }

        $coupon = new \WC_Coupon($code);
        if (! $coupon->get_id()) {
            return $this->result(false, 'Coupon "' . $code . '" does not exist.', $start);
        }

        $product_id = Wc_Test_Factory::discover_simple_product() ?: Wc_Test_Factory::discover_variation();
        if (! $product_id || ! Wc_Test_Factory::ensure_cart()) {
            return $this->result(false, 'No product or cart available for coupon test.', $start);
        }

        WC()->cart->empty_cart();
        Wc_Test_Factory::add_product_to_cart($product_id);

        $applied = WC()->cart->apply_coupon($code);
        WC()->cart->empty_cart();

        if (! $applied) {
            $notices = wc_get_notices('error');
            $msg     = ! empty($notices[0]['notice']) ? wp_strip_all_tags((string) $notices[0]['notice']) : 'apply_coupon returned false';
            wc_clear_notices();
            return $this->result(false, 'Coupon rejected: ' . $msg, $start);
        }

        wc_clear_notices();
        return $this->result(true, 'Coupon "' . $code . '" applies successfully.', $start);
    }
}
