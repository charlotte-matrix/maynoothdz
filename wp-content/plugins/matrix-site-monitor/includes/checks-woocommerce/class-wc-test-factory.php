<?php
/**
 * WooCommerce synthetic order factory.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks_Woocommerce;

use Matrix_Site_Monitor\Settings;

defined('ABSPATH') || exit;

class Wc_Test_Factory {
    /**
     * Ensure WooCommerce cart/session are available in cron/admin/CLI.
     */
    public static function ensure_cart(): bool {
        if (! function_exists('WC') || ! WC()) {
            return false;
        }

        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        if (is_null(WC()->session) && class_exists('WC_Session_Handler')) {
            $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');
            if (class_exists($session_class)) {
                WC()->session = new $session_class();
                WC()->session->init();
            }
        }

        if (is_null(WC()->customer) && class_exists('WC_Customer')) {
            WC()->customer = new \WC_Customer(get_current_user_id(), true);
        }

        if (is_null(WC()->cart) && class_exists('WC_Cart')) {
            WC()->cart = new \WC_Cart();
            WC()->cart->get_cart();
        }

        return WC()->cart instanceof \WC_Cart;
    }

    /**
     * Add a product (simple or variation) to the cart.
     *
     * @param int $product_id Product or variation ID.
     * @return string|false Cart item key or false.
     */
    public static function add_product_to_cart(int $product_id) {
        if (! self::ensure_cart()) {
            return false;
        }

        $product = wc_get_product($product_id);
        if (! $product) {
            return false;
        }

        if ($product->is_type('variation')) {
            return WC()->cart->add_to_cart(
                $product->get_parent_id(),
                1,
                $product_id,
                $product->get_variation_attributes()
            );
        }

        return WC()->cart->add_to_cart($product_id, 1);
    }

    /**
     * Get configured or auto-discovered simple product ID.
     */
    public static function discover_simple_product(): ?int {
        $settings = Settings::get();
        $override = (int) ($settings['wc_simple_product_id'] ?? 0);
        if ($override && wc_get_product($override)) {
            return $override;
        }

        $products = wc_get_products([
            'status' => 'publish',
            'limit'  => 10,
            'type'   => 'simple',
            'return' => 'ids',
        ]);

        foreach ((array) $products as $id) {
            $product = wc_get_product((int) $id);
            if ($product && $product->is_purchasable() && $product->is_in_stock()) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * Get configured or auto-discovered variation ID.
     */
    public static function discover_variation(): ?int {
        $settings = Settings::get();
        $override = (int) ($settings['wc_variation_id'] ?? 0);
        if ($override && wc_get_product($override)) {
            return $override;
        }

        $products = wc_get_products([
            'status' => 'publish',
            'limit'  => 10,
            'type'   => 'variable',
        ]);

        foreach ((array) $products as $product) {
            if (! $product instanceof \WC_Product_Variable) {
                continue;
            }
            foreach ($product->get_children() as $vid) {
                $v = wc_get_product((int) $vid);
                if ($v && $v->is_purchasable() && $v->is_in_stock()) {
                    return (int) $vid;
                }
            }
        }

        return null;
    }

    /**
     * Get test coupon code.
     */
    public static function coupon_code(): string {
        $settings = Settings::get();
        $code     = trim((string) ($settings['wc_coupon_code'] ?? ''));
        return (string) apply_filters('msm_wc_coupon_code', $code);
    }

    /**
     * Create a flagged test order via cart checkout.
     *
     * @param int    $product_id Product or variation ID.
     * @param string $coupon     Optional coupon code.
     * @return \WC_Order|null
     */
    public static function create_test_order(int $product_id, string $coupon = ''): ?\WC_Order {
        if (! self::ensure_cart()) {
            return null;
        }

        WC()->cart->empty_cart();
        $added = self::add_product_to_cart($product_id);

        if (! $added || WC()->cart->is_empty()) {
            return null;
        }

        if ($coupon !== '') {
            WC()->cart->apply_coupon($coupon);
        }

        WC()->cart->calculate_totals();

        $data = [
            'payment_method'     => self::payment_method(),
            'billing_first_name' => 'MSM',
            'billing_last_name'  => 'SelfTest',
            'billing_email'      => 'msm-selftest@example.invalid',
            'billing_phone'      => '0123456789',
            'billing_address_1'  => '1 Test Street',
            'billing_city'       => 'Dublin',
            'billing_postcode'   => 'D01AB12',
            'billing_country'    => 'IE',
        ];

        $order_id = WC()->checkout()->create_order($data);
        if (is_wp_error($order_id) || ! $order_id) {
            WC()->cart->empty_cart();
            return null;
        }

        $order = wc_get_order((int) $order_id);
        if (! $order) {
            return null;
        }

        $order->update_meta_data(MSM_SELFTEST_META, 'yes');
        $order->save();
        WC()->cart->empty_cart();

        return $order;
    }

    /**
     * Cancel and delete a test order.
     *
     * @param \WC_Order $order Order to remove.
     */
    public static function teardown_order(\WC_Order $order): void {
        if ($order->get_meta(MSM_SELFTEST_META) !== 'yes') {
            return;
        }
        $order->update_status('cancelled', 'MSM self-test cleanup');
        $order->delete(true);
    }

    /**
     * Remove orphaned self-test orders.
     */
    public static function purge_orphans(): void {
        $orders = wc_get_orders([
            'limit'      => 50,
            'meta_key'   => MSM_SELFTEST_META,
            'meta_value' => 'yes',
            'return'     => 'objects',
        ]);

        foreach ((array) $orders as $order) {
            if ($order instanceof \WC_Order) {
                $order->delete(true);
            }
        }
    }

    /**
     * Safe payment method for test orders (never charges).
     */
    private static function payment_method(): string {
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        if (isset($gateways['cod'])) {
            return 'cod';
        }
        if (isset($gateways['bacs'])) {
            return 'bacs';
        }
        $keys = array_keys($gateways);
        return $keys[0] ?? 'cod';
    }
}
