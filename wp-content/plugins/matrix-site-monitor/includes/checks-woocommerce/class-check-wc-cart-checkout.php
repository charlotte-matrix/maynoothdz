<?php
/**
 * WooCommerce add-to-cart and checkout availability.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks_Woocommerce;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Wc_Cart_Checkout extends Check_Base {
    public function id(): string { return 'wc_cart_checkout'; }
    public function label(): string { return 'Add to cart and checkout'; }
    public function severity(): string { return 'critical'; }
    public function tier(): string { return 'synthetic'; }

    public function run(): array {
        $start = microtime(true);

        if (! Wc_Test_Factory::ensure_cart()) {
            return $this->result(false, 'WooCommerce cart not available.', $start);
        }

        $product_id = Wc_Test_Factory::discover_variation() ?: Wc_Test_Factory::discover_simple_product();
        if (! $product_id) {
            return $this->result(false, 'No purchasable product found for cart test.', $start);
        }

        WC()->cart->empty_cart();
        $added = Wc_Test_Factory::add_product_to_cart($product_id);
        if (! $added) {
            return $this->result(false, 'Could not add product #' . $product_id . ' to cart.', $start);
        }

        if (WC()->cart->is_empty()) {
            return $this->result(false, 'Cart is empty after add_to_cart.', $start);
        }

        $checkout_url = wc_get_checkout_url();
        if (! $checkout_url) {
            WC()->cart->empty_cart();
            return $this->result(false, 'Checkout URL not configured.', $start);
        }

        WC()->cart->empty_cart();
        return $this->result(true, 'Add to cart and checkout URL verified for product #' . $product_id . '.', $start);
    }
}
