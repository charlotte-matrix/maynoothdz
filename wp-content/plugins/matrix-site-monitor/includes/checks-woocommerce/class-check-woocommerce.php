<?php
/**
 * WooCommerce coverage from docs/WooCommerce.md — line-by-line.
 *
 * Does not take real payments or send customer mail.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks_Woocommerce;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Woocommerce extends Check_Base {
    public function id(): string { return 'woocommerce'; }
    public function label(): string { return 'WooCommerce coverage'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'synthetic'; }

    public function run(): array {
        $start = microtime(true);

        if (! class_exists('WooCommerce') || ! function_exists('WC')) {
            return $this->skip('WooCommerce not active.', $start, [
                'coverage' => $this->static_coverage('skip', 'WooCommerce inactive'),
            ]);
        }

        $findings = [
            'checkout'        => [],
            'gateway'         => [],
            'apple_pay'       => [],
            'google_pay'      => [],
            'stripe'          => [],
            'paypal'          => [],
            'cart'            => [],
            'add_to_cart'     => [],
            'qty'             => [],
            'coupons'         => [],
            'shipping'        => [],
            'tax'             => [],
            'fragments'       => [],
            'purchasable'     => [],
            'oos'             => [],
            'variable'        => [],
            'images'          => [],
            'price'           => [],
            'price_display'   => [],
            'failed_orders'   => [],
            'duplicate'       => [],
            'emails'          => [],
            'confirm_email'   => [],
            'stock_reduce'    => [],
            'stock_display'   => [],
            'refunds'         => [],
            'checkout_btn'    => [],
            'payment_decline' => [],
            'wishlist'        => [],
            'account'         => [],
        ];

        // --- Checkout / cart ---
        if (! Wc_Test_Factory::ensure_cart()) {
            $findings['checkout'][]    = 'WooCommerce cart not available';
            $findings['cart'][]        = 'Cart API unavailable';
            $findings['add_to_cart'][] = 'Cart API unavailable — add to cart cannot run';
            $findings['qty'][]         = 'Cart API unavailable — quantity cannot be tested';
        } else {
            $product_id = Wc_Test_Factory::discover_variation() ?: Wc_Test_Factory::discover_simple_product();
            if (! $product_id) {
                $findings['checkout'][]    = 'No purchasable product for checkout test';
                $findings['purchasable'][] = 'No purchasable simple/variation product found';
                $findings['add_to_cart'][] = 'No purchasable product to add to cart';
            } else {
                WC()->cart->empty_cart();
                if (! Wc_Test_Factory::add_product_to_cart($product_id)) {
                    $findings['checkout'][]    = 'Could not add product #' . $product_id . ' to cart';
                    $findings['cart'][]        = 'add_to_cart failed for #' . $product_id;
                    $findings['add_to_cart'][] = 'add_to_cart failed for product #' . $product_id;
                    $findings['purchasable'][] = 'Product #' . $product_id . ' not addable to cart';
                } else {
                    // Cart updating — change quantity.
                    $cart = WC()->cart->get_cart();
                    $key  = $cart ? array_key_first($cart) : null;
                    if ($key) {
                        WC()->cart->set_quantity($key, 2, true);
                        $qty = (int) (WC()->cart->get_cart()[ $key ]['quantity'] ?? 0);
                        if ($qty !== 2) {
                            $findings['cart'][] = 'Cart quantity did not update to 2';
                            $findings['qty'][]  = 'Quantity selector / set_quantity failed (expected 2, got ' . $qty . ')';
                        }
                    } else {
                        $findings['qty'][] = 'No cart item key after add_to_cart';
                    }

                    try {
                        WC()->cart->calculate_shipping();
                        WC()->cart->calculate_totals();
                        $packages = WC()->shipping()->get_packages();
                        if (! empty($packages)) {
                            $has_rate = false;
                            foreach ($packages as $package) {
                                if (! empty($package['rates'])) {
                                    $has_rate = true;
                                    break;
                                }
                            }
                            if (! $has_rate && get_option('woocommerce_ship_to_countries') !== '') {
                                $findings['shipping'][] = 'Cart has packages but no shipping rates returned';
                            }
                        }
                    } catch (\Throwable $e) {
                        $findings['shipping'][] = 'Shipping calculation error: ' . $e->getMessage();
                    }

                    $checkout = wc_get_checkout_url();
                    if (! $checkout) {
                        $findings['checkout'][] = 'Checkout URL not configured';
                    } else {
                        $cres = wp_remote_get($checkout, [
                            'timeout'   => 20,
                            'sslverify' => false,
                            'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
                        ]);
                        if (is_wp_error($cres)) {
                            $findings['checkout'][] = 'Checkout fetch failed: ' . $cres->get_error_message();
                        } else {
                            $ccode = (int) wp_remote_retrieve_response_code($cres);
                            $cbody = (string) wp_remote_retrieve_body($cres);
                            if ($ccode >= 400) {
                                $findings['checkout'][] = 'Checkout HTTP ' . $ccode;
                            }
                            if (preg_match('/critical error|Fatal error|Checkout is not available/i', $cbody)) {
                                $findings['checkout'][] = 'Checkout page shows error / unavailable message';
                            }
                            if ($ccode < 400 && ! preg_match('/id=["\']place_order["\']|wc-block-components-checkout-place-order-button|name=["\']woocommerce_checkout_place_order["\']/i', $cbody)) {
                                if (! preg_match('/woocommerce-checkout|wp-block-woocommerce-checkout/i', $cbody)) {
                                    $findings['checkout_btn'][] = 'Checkout page missing place-order button / checkout block markup';
                                }
                            }
                            if (preg_match('/id=["\']place_order["\'][^>]*disabled|place_order["\'][^>]*aria-disabled=["\']true["\']/i', $cbody)) {
                                $findings['checkout_btn'][] = 'Place order button appears disabled in checkout HTML';
                            }
                        }
                    }
                }
                WC()->cart->empty_cart();
            }
        }

        // --- My Account ---
        $account_url = wc_get_page_permalink('myaccount');
        if (! $account_url) {
            $findings['account'][] = 'My Account page not configured';
        } else {
            $ares = wp_remote_get($account_url, [
                'timeout'   => 15,
                'sslverify' => false,
                'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
            ]);
            if (is_wp_error($ares)) {
                $findings['account'][] = 'My Account fetch failed: ' . $ares->get_error_message();
            } else {
                $acode = (int) wp_remote_retrieve_response_code($ares);
                $abody = (string) wp_remote_retrieve_body($ares);
                if ($acode >= 500) {
                    $findings['account'][] = 'My Account HTTP ' . $acode;
                } elseif (preg_match('/critical error|Fatal error|There has been a critical error/i', $abody)) {
                    $findings['account'][] = 'My Account page shows a critical/fatal error';
                }
            }
        }

        // --- Wishlist (known plugins) ---
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $wishlist_plugins = [
            'yith-woocommerce-wishlist/init.php',
            'ti-woocommerce-wishlist/ti-woocommerce-wishlist.php',
            'woocommerce-wishlists/woocommerce-wishlists.php',
        ];
        $wishlist_active = false;
        foreach ($wishlist_plugins as $wp_file) {
            if (is_plugin_active($wp_file)) {
                $wishlist_active = true;
                break;
            }
        }
        if ($wishlist_active) {
            $wish_page = (int) get_option('yith_wcwl_wishlist_page_id', 0);
            if (! $wish_page) {
                $wish_page = (int) get_option('tinvwl-page', 0);
            }
            if ($wish_page) {
                $wurl = get_permalink($wish_page);
                if ($wurl) {
                    $wres = wp_remote_get($wurl, [
                        'timeout'   => 12,
                        'sslverify' => false,
                        'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
                    ]);
                    if (is_wp_error($wres) || (int) wp_remote_retrieve_response_code($wres) >= 500) {
                        $findings['wishlist'][] = 'Wishlist page error / HTTP failure';
                    } elseif (preg_match('/critical error|Fatal error/i', (string) wp_remote_retrieve_body($wres))) {
                        $findings['wishlist'][] = 'Wishlist page shows a critical error';
                    }
                }
            }
        }

        // --- Gateways ---
        $enabled = [];
        if (WC()->payment_gateways()) {
            foreach ((array) WC()->payment_gateways()->payment_gateways() as $id => $gateway) {
                if (isset($gateway->enabled) && $gateway->enabled === 'yes') {
                    $enabled[ $id ] = $gateway;
                }
            }
        }
        if (empty($enabled)) {
            $findings['gateway'][] = 'No payment gateways enabled';
        }

        // Stripe / Apple Pay / Google Pay / PayPal heuristics.
        foreach ($enabled as $id => $gateway) {
            $id_l = strtolower((string) $id);
            if (strpos($id_l, 'stripe') !== false) {
                $pk = '';
                if (method_exists($gateway, 'get_option')) {
                    $pk = (string) $gateway->get_option('publishable_key');
                    if ($pk === '') {
                        $pk = (string) $gateway->get_option('test_publishable_key');
                    }
                }
                if ($pk === '' && empty(get_option('woocommerce_stripe_settings'))) {
                    $findings['stripe'][] = 'Stripe gateway enabled but publishable key not found in settings';
                }
                $settings = get_option('woocommerce_stripe_settings', []);
                if (is_array($settings)) {
                    if (($settings['payment_request'] ?? '') === 'yes' || ($settings['apple_google_pay'] ?? '') === 'yes') {
                        // Payment Request Button covers Apple/Google — presence OK unless keys missing.
                        if ($pk === '' && empty($settings['publishable_key']) && empty($settings['test_publishable_key'])) {
                            $findings['apple_pay'][]  = 'Payment Request enabled but Stripe keys look empty';
                            $findings['google_pay'][] = 'Payment Request enabled but Stripe keys look empty';
                        }
                    }
                }
            }
            if (strpos($id_l, 'paypal') !== false || strpos($id_l, 'ppec') !== false || strpos($id_l, 'ppcp') !== false) {
                // Enabled PayPal is OK; callback failures need live IPN — soft pass unless settings empty.
                if (method_exists($gateway, 'get_option')) {
                    $email = (string) $gateway->get_option('email');
                    $client = (string) $gateway->get_option('client_id');
                    if ($email === '' && $client === '' && empty(get_option('woocommerce_paypal_settings'))) {
                        $findings['paypal'][] = 'PayPal gateway enabled but credentials look empty';
                    }
                }
            }
        }

        // --- Coupons ---
        $coupons = get_posts([
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
        ]);
        if (empty($coupons)) {
            // skip — no coupons to test
        } else {
            $code = $coupons[0]->post_title;
            $coupon = new \WC_Coupon($code);
            if (! $coupon->get_id()) {
                $findings['coupons'][] = 'Could not load coupon "' . $code . '"';
            } elseif ($coupon->get_date_expires() && $coupon->get_date_expires()->getTimestamp() < time()) {
                $findings['coupons'][] = 'Sample coupon "' . $code . '" is expired';
            }
        }

        // --- Shipping ---
        $zones = [];
        if (class_exists('WC_Shipping_Zones')) {
            $zones = \WC_Shipping_Zones::get_zones();
            $zone0 = \WC_Shipping_Zones::get_zone(0);
            if ($zone0) {
                $methods = $zone0->get_shipping_methods(true);
                if (empty($zones) && empty($methods)) {
                    $findings['shipping'][] = 'No shipping zones/methods configured';
                }
            }
        }

        // --- Tax ---
        if (wc_tax_enabled()) {
            $classes = \WC_Tax::get_tax_classes();
            // Always has Standard — just ensure tax rates exist for shop country.
            $base = wc_get_base_location();
            $rates = \WC_Tax::find_rates([
                'country'   => $base['country'] ?? '',
                'state'     => $base['state'] ?? '',
                'tax_class' => '',
            ]);
            if (empty($rates) && empty($classes)) {
                $findings['tax'][] = 'Taxes enabled but no rates found for base location';
            } elseif (empty($rates)) {
                $findings['tax'][] = 'Taxes enabled but no rates for base country ' . ($base['country'] ?? '');
            }
        }

        // --- Cart fragments ---
        $home = wp_remote_get(home_url('/'), [
            'timeout'   => 15,
            'sslverify' => false,
            'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        if (! is_wp_error($home)) {
            $html = (string) wp_remote_retrieve_body($home);
            if (strpos($html, 'cart-fragments') !== false || strpos($html, 'wc-cart-fragments') !== false) {
                // Present — OK. Could note as potential perf issue only if filter says so.
                $slow = apply_filters('msm_wc_cart_fragments_slow', false);
                if ($slow) {
                    $findings['fragments'][] = 'Cart fragments flagged as slow (msm_wc_cart_fragments_slow)';
                }
            }
        }

        // --- Products sample ---
        $products = wc_get_products([
            'status' => 'publish',
            'limit'  => 8,
            'orderby'=> 'date',
            'order'  => 'DESC',
        ]);
        $has_variable = false;
        foreach ($products as $product) {
            if (! $product instanceof \WC_Product) {
                continue;
            }
            if ($product->is_type('variable')) {
                $has_variable = true;
                $children = $product->get_children();
                if (empty($children)) {
                    $findings['variable'][] = $product->get_name() . ' has no variations';
                } else {
                    $purchasable_var = false;
                    foreach (array_slice($children, 0, 5) as $vid) {
                        $v = wc_get_product($vid);
                        if ($v && $v->is_purchasable() && $v->is_in_stock()) {
                            $purchasable_var = true;
                            break;
                        }
                    }
                    if (! $purchasable_var) {
                        $findings['variable'][] = $product->get_name() . ' has no purchasable in-stock variation';
                    }
                }
            }
            if ($product->is_purchasable() && ! $product->is_in_stock() && $product->get_catalog_visibility() !== 'hidden') {
                // Purchasable flag with OOS can confuse — flag manage_stock quirks.
                if ($product->managing_stock() && $product->get_stock_quantity() !== null && (int) $product->get_stock_quantity() <= 0) {
                    $findings['oos'][] = $product->get_name() . ' is out of stock (qty 0)';
                }
            }
            if (! $product->get_image_id()) {
                $findings['images'][] = $product->get_name() . ' missing featured image';
            } else {
                $img = wp_get_attachment_url($product->get_image_id());
                if ($img) {
                    $code = $this->probe_code($img);
                    if ($code === 404 || ($code !== null && $code >= 500)) {
                        $findings['images'][] = $product->get_name() . ' image HTTP ' . $code;
                    }
                }
            }
            $regular = (float) $product->get_regular_price();
            $sale    = $product->get_sale_price() !== '' ? (float) $product->get_sale_price() : null;
            if ($product->get_price() === '' && $product->is_type('simple')) {
                $findings['price'][] = $product->get_name() . ' has empty price';
            }
            if ($sale !== null && $regular > 0 && $sale > $regular) {
                $findings['price'][] = $product->get_name() . ' sale price > regular price';
                $findings['price_display'][] = $product->get_name() . ' sale price > regular (wrong price risk)';
            }

            // Stock shown incorrectly — status vs quantity mismatches.
            if ($product->managing_stock()) {
                $qty = $product->get_stock_quantity();
                if ($qty !== null) {
                    if ((int) $qty <= 0 && $product->is_in_stock() && ! $product->backorders_allowed()) {
                        $findings['stock_display'][] = $product->get_name() . ' shows in stock but qty is ' . (int) $qty;
                    }
                    if ((int) $qty > 0 && ! $product->is_in_stock()) {
                        $findings['stock_display'][] = $product->get_name() . ' shows out of stock but qty is ' . (int) $qty;
                    }
                }
            }
        }
        $findings['images'] = array_slice($findings['images'], 0, 5);
        $findings['oos']    = array_slice($findings['oos'], 0, 5);
        $findings['price']  = array_slice($findings['price'], 0, 5);
        $findings['price_display'] = array_slice($findings['price_display'], 0, 5);
        $findings['stock_display'] = array_slice($findings['stock_display'], 0, 5);

        // Wrong price displayed — compare WC price to product page HTML (sample 2).
        foreach (array_slice($products, 0, 2) as $product) {
            if (! $product instanceof \WC_Product || $product->is_type('variable')) {
                continue;
            }
            $plink = $product->get_permalink();
            $wc_price = $product->get_price();
            if ($plink === '' || $wc_price === '' || $wc_price === null) {
                continue;
            }
            $pres = wp_remote_get($plink, [
                'timeout'   => 15,
                'sslverify' => false,
                'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
            ]);
            if (is_wp_error($pres) || (int) wp_remote_retrieve_response_code($pres) >= 400) {
                continue;
            }
            $phtml = (string) wp_remote_retrieve_body($pres);
            $amount = wc_format_decimal((float) $wc_price, wc_get_price_decimals());
            // Look for the numeric amount somewhere in price markup.
            if (! preg_match('/woocommerce-Price-amount|amount\s*["\']?\s*[:>]/i', $phtml)) {
                $findings['price_display'][] = $product->get_name() . ' product page missing price markup';
            } elseif (strpos($phtml, (string) $amount) === false && strpos($phtml, number_format((float) $wc_price, wc_get_price_decimals(), '.', '')) === false) {
                // Soft: currency formatting differs — only flag if no digits of integer part appear near price class.
                $intpart = (string) (int) floor((float) $wc_price);
                if ($intpart !== '0' && ! preg_match('/woocommerce-Price-amount[^<]{0,80}' . preg_quote($intpart, '/') . '/i', $phtml)) {
                    $findings['price_display'][] = $product->get_name() . ' page price may not match WC price (' . $amount . ')';
                }
            }
        }

        // --- Orders ---
        $failed = wc_get_orders([
            'status'       => 'failed',
            'limit'        => 50,
            'date_created' => '>' . (time() - DAY_IN_SECONDS),
            'return'       => 'ids',
        ]);
        $failed_n = is_array($failed) ? count($failed) : 0;
        $threshold = (int) apply_filters('msm_wc_failed_orders_threshold', 10);
        if ($failed_n >= $threshold) {
            $findings['failed_orders'][]    = $failed_n . ' failed orders in 24h (threshold ' . $threshold . ')';
            $findings['payment_decline'][]  = $failed_n . ' failed/declined orders in 24h (threshold ' . $threshold . ')';
        } elseif ($failed_n > 0) {
            // Soft visibility for payment declines without failing overall unless threshold hit.
            // Keep empty for pass; details available via wc_failed_orders check.
        }

        // Duplicate orders — same billing email + total within 10 minutes (sample recent).
        $recent = wc_get_orders([
            'limit'        => 30,
            'date_created' => '>' . (time() - DAY_IN_SECONDS),
            'orderby'      => 'date',
            'order'        => 'DESC',
        ]);
        $seen = [];
        foreach ((array) $recent as $order) {
            if (! $order instanceof \WC_Order) {
                continue;
            }
            $email = strtolower((string) $order->get_billing_email());
            $total = (string) $order->get_total();
            $created = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
            if ($email === '') {
                continue;
            }
            $sig = $email . '|' . $total;
            if (isset($seen[ $sig ]) && abs($created - $seen[ $sig ]) < 10 * MINUTE_IN_SECONDS) {
                $findings['duplicate'][] = 'Possible duplicate: ' . $email . ' total ' . $total;
            }
            $seen[ $sig ] = $created;
        }
        $findings['duplicate'] = array_slice(array_unique($findings['duplicate']), 0, 5);

        // Emails — WC email classes enabled (no send).
        $mailer = WC()->mailer();
        if ($mailer && method_exists($mailer, 'get_emails')) {
            $emails = $mailer->get_emails();
            $new_order = $emails['WC_Email_New_Order'] ?? null;
            if ($new_order && isset($new_order->enabled) && $new_order->enabled !== 'yes') {
                $findings['emails'][] = 'New order email is disabled';
            }
            $proc = $emails['WC_Email_Customer_Processing_Order'] ?? null;
            if ($proc && isset($proc->enabled) && $proc->enabled !== 'yes') {
                $findings['emails'][] = 'Customer processing order email is disabled';
                $findings['confirm_email'][] = 'Customer processing/order confirmation email is disabled';
            }
            $completed = $emails['WC_Email_Customer_Completed_Order'] ?? null;
            if ($completed && isset($completed->enabled) && $completed->enabled !== 'yes') {
                $findings['confirm_email'][] = 'Customer completed order email is disabled';
            }
        }

        // Stock reducing — managing stock globally + sample completed order with stock items.
        if ('yes' !== get_option('woocommerce_manage_stock')) {
            $findings['stock_reduce'][] = 'woocommerce_manage_stock is disabled';
        }

        // Refunds — recent refunds with failed note; or capability.
        $refund_issue = apply_filters('msm_wc_refund_issue', null);
        if (is_string($refund_issue) && $refund_issue !== '') {
            $findings['refunds'][] = $refund_issue;
        }

        $coverage = $this->build_coverage($findings, [
            'has_variable'  => $has_variable,
            'has_wishlist'  => ! empty($wishlist_active),
            'tax_on'        => wc_tax_enabled(),
            'gateways'      => array_keys($enabled),
            'failed_n'      => $failed_n,
        ]);

        $failed_rows = array_filter($coverage, static function (array $r): bool {
            return ($r['status'] ?? '') === 'fail';
        });

        $extra = ['coverage' => $coverage];

        if ($failed_rows) {
            $msgs = [];
            foreach (array_slice(array_values($failed_rows), 0, 8) as $row) {
                $msgs[] = $row['line'] . (! empty($row['detail']) ? ': ' . $row['detail'] : '');
            }
            return $this->fail(implode('; ', $msgs) . '.', $start, $extra);
        }

        return $this->pass('WooCommerce coverage sample OK.', $start, $extra);
    }

    /**
     * @return array<int, array{line:string,mode:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'Checkout errors', 'mode' => 'automated'],
            ['line' => 'Payment gateway unavailable', 'mode' => 'automated'],
            ['line' => 'Apple Pay broken', 'mode' => 'partial'],
            ['line' => 'Google Pay broken', 'mode' => 'partial'],
            ['line' => 'Stripe API issues', 'mode' => 'partial'],
            ['line' => 'PayPal callback failures', 'mode' => 'partial'],
            ['line' => 'Cart not updating', 'mode' => 'automated'],
            ['line' => 'Coupons failing', 'mode' => 'partial'],
            ['line' => 'Shipping calculation wrong', 'mode' => 'partial'],
            ['line' => 'Tax incorrect', 'mode' => 'partial'],
            ['line' => 'Cart fragments slowing site', 'mode' => 'manual'],
            ['line' => 'Products not purchasable', 'mode' => 'automated'],
            ['line' => 'Out-of-stock issues', 'mode' => 'partial'],
            ['line' => 'Variable products broken', 'mode' => 'automated'],
            ['line' => 'Images missing', 'mode' => 'automated'],
            ['line' => 'Price mismatch', 'mode' => 'automated'],
            ['line' => 'Failed orders', 'mode' => 'automated'],
            ['line' => 'Duplicate orders', 'mode' => 'partial'],
            ['line' => 'Emails not sending', 'mode' => 'partial'],
            ['line' => 'Stock not reducing', 'mode' => 'partial'],
            ['line' => 'Refund failures', 'mode' => 'manual'],
            ['line' => 'Add to Cart button not working', 'mode' => 'automated'],
            ['line' => 'Quantity selector broken', 'mode' => 'automated'],
            ['line' => 'Product variations unavailable', 'mode' => 'automated'],
            ['line' => 'Wrong price displayed', 'mode' => 'partial'],
            ['line' => 'Coupon code rejected', 'mode' => 'partial'],
            ['line' => 'Shipping cost incorrect', 'mode' => 'partial'],
            ['line' => 'Checkout button disabled', 'mode' => 'partial'],
            ['line' => 'Payment declined unexpectedly', 'mode' => 'partial'],
            ['line' => 'Order confirmation not received', 'mode' => 'partial'],
            ['line' => 'Stock shown incorrectly', 'mode' => 'partial'],
            ['line' => 'Wishlist broken', 'mode' => 'partial'],
            ['line' => 'Account page errors', 'mode' => 'automated'],
        ];
    }

    /**
     * @param array<string, array<int, string>> $findings Findings.
     * @param array<string, mixed>              $meta     Context.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function build_coverage(array $findings, array $meta): array {
        $map = [
            'Checkout errors'                   => 'checkout',
            'Payment gateway unavailable'       => 'gateway',
            'Apple Pay broken'                  => 'apple_pay',
            'Google Pay broken'                 => 'google_pay',
            'Stripe API issues'                 => 'stripe',
            'PayPal callback failures'          => 'paypal',
            'Cart not updating'                 => 'cart',
            'Coupons failing'                   => 'coupons',
            'Shipping calculation wrong'        => 'shipping',
            'Tax incorrect'                     => 'tax',
            'Cart fragments slowing site'       => 'fragments',
            'Products not purchasable'          => 'purchasable',
            'Out-of-stock issues'               => 'oos',
            'Variable products broken'          => 'variable',
            'Images missing'                    => 'images',
            'Price mismatch'                    => 'price',
            'Failed orders'                     => 'failed_orders',
            'Duplicate orders'                  => 'duplicate',
            'Emails not sending'                => 'emails',
            'Stock not reducing'                => 'stock_reduce',
            'Refund failures'                   => 'refunds',
            'Add to Cart button not working'    => 'add_to_cart',
            'Quantity selector broken'          => 'qty',
            'Product variations unavailable'    => 'variable',
            'Wrong price displayed'             => 'price_display',
            'Coupon code rejected'              => 'coupons',
            'Shipping cost incorrect'           => 'shipping',
            'Checkout button disabled'          => 'checkout_btn',
            'Payment declined unexpectedly'     => 'payment_decline',
            'Order confirmation not received'   => 'confirm_email',
            'Stock shown incorrectly'           => 'stock_display',
            'Wishlist broken'                   => 'wishlist',
            'Account page errors'               => 'account',
        ];

        $pass = [
            'checkout'         => 'Add-to-cart + checkout page OK',
            'gateway'          => 'Enabled gateway(s): ' . implode(', ', $meta['gateways'] ?? []),
            'cart'             => 'Cart quantity update OK',
            'add_to_cart'      => 'add_to_cart succeeded for sample product',
            'qty'              => 'Cart quantity update OK',
            'purchasable'      => 'Purchasable product available',
            'variable'         => 'Variable products have purchasable variations (or none in sample)',
            'images'           => 'Sample products have reachable images',
            'price'            => 'No sale>regular / empty price in sample',
            'price_display'    => 'Product page price markup matches WC sample',
            'failed_orders'    => (int) ($meta['failed_n'] ?? 0) . ' failed orders in 24h',
            'payment_decline'  => 'Failed-order volume under threshold',
            'duplicate'        => 'No obvious duplicate orders in 24h sample',
            'emails'           => 'Core order emails enabled',
            'confirm_email'    => 'Customer order confirmation emails enabled',
            'stock_reduce'     => 'Stock management enabled',
            'stock_display'    => 'No stock status/qty mismatches in sample',
            'shipping'         => 'Shipping zones/methods / rates OK',
            'tax'              => 'Tax rates configured for base location',
            'coupons'          => 'Sample coupon loadable (or none published)',
            'stripe'           => 'No Stripe key issues detected',
            'paypal'           => 'No PayPal credential issues detected',
            'apple_pay'        => 'No Apple Pay config issues detected',
            'google_pay'       => 'No Google Pay config issues detected',
            'oos'              => 'No confusing OOS products flagged in sample',
            'fragments'        => 'No cart-fragments slow flag',
            'refunds'          => 'No refund issue reported',
            'checkout_btn'     => 'Checkout place-order markup looks present',
            'wishlist'         => 'Wishlist OK (or plugin not active)',
            'account'          => 'My Account page reachable without fatal',
        ];

        $coverage = [];
        foreach (self::doc_lines() as $row) {
            $line = $row['line'];
            $mode = $row['mode'];
            $key  = $map[$line] ?? '';
            $hits = $key !== '' ? ($findings[$key] ?? []) : [];

            if ($mode === 'manual' && empty($hits)) {
                $detail = $key === 'fragments'
                    ? 'Needs performance profiling (msm_wc_cart_fragments_slow filter)'
                    : 'Needs live refund drill (msm_wc_refund_issue filter)';
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'manual', 'detail' => $detail,
                ];
                continue;
            }

            if ($key === 'tax' && empty($meta['tax_on']) && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'skip',
                    'detail' => 'Taxes disabled in WooCommerce settings',
                ];
                continue;
            }

            if ($key === 'variable' && empty($meta['has_variable']) && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'skip',
                    'detail' => 'No variable products in sample',
                ];
                continue;
            }

            if (in_array($key, ['apple_pay', 'google_pay', 'stripe', 'paypal'], true) && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'skip',
                    'detail' => 'Not configured / no issues in enabled gateways (live wallet/IPN not tested)',
                ];
                continue;
            }

            if ($key === 'coupons' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => $pass['coupons'],
                ];
                continue;
            }

            if ($key === 'wishlist' && empty($hits)) {
                if (empty($meta['has_wishlist'])) {
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'skip',
                        'detail' => 'No known wishlist plugin active',
                    ];
                } else {
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'pass',
                        'detail' => $pass['wishlist'],
                    ];
                }
                continue;
            }

            if ($key === 'payment_decline' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => $pass['payment_decline'],
                ];
                continue;
            }

            if ($key === 'checkout_btn' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => $pass['checkout_btn'],
                ];
                continue;
            }

            if ($key === 'confirm_email' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => $pass['confirm_email'],
                ];
                continue;
            }

            if ($key === 'account' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => $pass['account'],
                ];
                continue;
            }

            if ($key === 'price_display' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => $pass['price_display'],
                ];
                continue;
            }

            if ($key === 'stock_display' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => $pass['stock_display'],
                ];
                continue;
            }

            if (in_array($key, ['add_to_cart', 'qty'], true) && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => $pass[$key] ?? 'OK',
                ];
                continue;
            }

            if ($key === 'stock_reduce' && ! empty($hits)) {
                // Disabled stock mgmt is skip on catalogs that don't use stock.
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'skip',
                    'detail' => implode('; ', $hits) . ' (OK if you do not track stock)',
                ];
                continue;
            }

            $coverage[] = [
                'line'   => $line,
                'mode'   => $mode,
                'status' => empty($hits) ? 'pass' : 'fail',
                'detail' => empty($hits) ? ($pass[$key] ?? 'OK') : implode('; ', array_slice($hits, 0, 3)),
            ];
        }

        return $coverage;
    }

    /**
     * @param string $status Status.
     * @param string $detail Detail.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function static_coverage(string $status, string $detail): array {
        $rows = [];
        foreach (self::doc_lines() as $row) {
            $rows[] = [
                'line'   => $row['line'],
                'mode'   => $row['mode'],
                'status' => $status,
                'detail' => $detail,
            ];
        }
        return $rows;
    }

    private function probe_code(string $url): ?int {
        $args = [
            'timeout'   => 8,
            'sslverify' => false,
            'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ];
        $res = wp_remote_head($url, $args);
        if (! is_wp_error($res)) {
            $code = (int) wp_remote_retrieve_response_code($res);
            if ($code !== 405 && $code !== 501) {
                return $code;
            }
        }
        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) {
            return null;
        }
        return (int) wp_remote_retrieve_response_code($res);
    }
}
