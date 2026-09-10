<?php
/**
 * WooCommerce storefront page smoke test.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks_Woocommerce;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Wc_Pages extends Check_Base {
    public function id(): string { return 'wc_pages'; }
    public function label(): string { return 'WooCommerce pages load'; }
    public function severity(): string { return 'critical'; }
    public function tier(): string { return 'synthetic'; }

    public function run(): array {
        $start    = microtime(true);
        $urls     = [];
        $failures = [];

        $urls['shop'] = wc_get_page_permalink('shop');
        $urls['cart'] = wc_get_cart_url();
        $urls['checkout'] = wc_get_checkout_url();
        $urls['my-account'] = wc_get_page_permalink('myaccount');

        $product_id = Wc_Test_Factory::discover_simple_product() ?: Wc_Test_Factory::discover_variation();
        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $parent_id = $product->get_parent_id() ?: $product->get_id();
                $urls['product'] = get_permalink($parent_id);

                $terms = get_the_terms($parent_id, 'product_cat');
                if (is_array($terms) && ! empty($terms[0]) && ! is_wp_error($terms[0])) {
                    $term_link = get_term_link($terms[0]);
                    if (! is_wp_error($term_link)) {
                        $urls['product-category'] = $term_link;
                    }
                }
            }
        }

        $urls = apply_filters('msm_wc_smoke_urls', array_filter($urls));

        foreach ($urls as $label => $url) {
            if (! $url) {
                continue;
            }

            $res = wp_remote_get($url, [
                'timeout'     => 15,
                'redirection' => 3,
                'sslverify'   => false,
                'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
            ]);

            if (is_wp_error($res)) {
                $failures[] = $label . ': ' . $res->get_error_message();
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 400) {
                $failures[] = $label . ' (HTTP ' . $code . ')';
                continue;
            }

            $body = (string) wp_remote_retrieve_body($res);
            if (preg_match('/Fatal error|critical error|Parse error/i', $body)) {
                $failures[] = $label . ' (fatal in output)';
            }
        }

        if ($failures) {
            return $this->result(false, implode('; ', $failures), $start);
        }

        return $this->result(true, count($urls) . ' WooCommerce pages OK.', $start);
    }
}
