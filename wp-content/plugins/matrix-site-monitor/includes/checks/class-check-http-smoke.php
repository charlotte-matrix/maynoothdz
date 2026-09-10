<?php
/**
 * HTTP smoke test — key pages + capped sample load without fatals.
 *
 * Does NOT crawl the whole site (too heavy). Checks:
 * - Always: home, wp-login
 * - WooCommerce (if active): shop, cart, checkout, my-account, one product
 * - Sample of published pages + recent posts (capped)
 * - Extra URLs from settings / msm_smoke_urls filter
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;
use Matrix_Site_Monitor\Settings;

defined('ABSPATH') || exit;

class Check_Http_Smoke extends Check_Base {
    public function id(): string { return 'http_smoke'; }
    public function label(): string { return 'Key pages load without fatals'; }
    public function severity(): string { return 'critical'; }
    public function tier(): string { return 'light'; }

    public function run(): array {
        $start = microtime(true);
        $urls  = $this->urls_to_check();
        $failures = [];

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
                $msg = $res->get_error_message();
                if (stripos($msg, 'redirect') !== false || stripos($msg, 'too many') !== false) {
                    $failures[] = $label . ' (possible redirect loop: ' . $msg . ')';
                } else {
                    $failures[] = $label . ' (request error: ' . $msg . ')';
                }
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 400) {
                $failures[] = $label . ' (HTTP ' . $code . ')';
                continue;
            }

            $body = (string) wp_remote_retrieve_body($res);
            if (preg_match('/Fatal error|There has been a critical error|Parse error|Uncaught (?:Error|Exception)/i', $body)) {
                $failures[] = $label . ' (PHP fatal/critical error in output)';
            }
            if (preg_match('/Briefly unavailable for scheduled maintenance|site is undergoing maintenance/i', $body)) {
                $failures[] = $label . ' (maintenance mode page)';
            }
            if (preg_match('/Error establishing a database connection/i', $body)) {
                $failures[] = $label . ' (database connection error)';
            }
        }

        $labels = implode(', ', array_keys($urls));

        if ($failures) {
            return $this->result(
                false,
                'Problem pages: ' . implode('; ', $failures) . '. Checked: ' . $labels . '.',
                $start
            );
        }

        return $this->result(
            true,
            count($urls) . ' pages loaded cleanly (' . $labels . ').',
            $start
        );
    }

    /**
     * Build URL list for smoke test.
     *
     * @return array<string, string>
     */
    private function urls_to_check(): array {
        $settings = Settings::get();
        $page_limit = max(0, (int) ($settings['smoke_page_sample'] ?? 8));
        $post_limit = max(0, (int) ($settings['smoke_post_sample'] ?? 3));

        $urls = [
            'home'     => home_url('/'),
            'wp-login' => wp_login_url(),
        ];

        // Separate posts page when using a static front page.
        $page_for_posts = (int) get_option('page_for_posts');
        if ($page_for_posts > 0) {
            $link = get_permalink($page_for_posts);
            if ($link) {
                $urls['blog'] = $link;
            }
        }

        if (class_exists('WooCommerce')) {
            $shop = wc_get_page_permalink('shop');
            if ($shop) {
                $urls['shop'] = $shop;
            }
            $urls['cart']     = wc_get_cart_url();
            $urls['checkout'] = wc_get_checkout_url();
            $account = wc_get_page_permalink('myaccount');
            if ($account) {
                $urls['my-account'] = $account;
            }

            $products = wc_get_products([
                'status' => 'publish',
                'limit'  => 1,
                'return' => 'ids',
            ]);
            if (! empty($products[0])) {
                $plink = get_permalink((int) $products[0]);
                if ($plink) {
                    $urls['product'] = $plink;
                }
            }
        }

        // Sample of published pages (menu-depth / recent), capped.
        if ($page_limit > 0) {
            $pages = get_posts([
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => $page_limit,
                'orderby'        => 'menu_order title',
                'order'          => 'ASC',
                'fields'         => 'ids',
            ]);
            foreach ((array) $pages as $i => $page_id) {
                $link = get_permalink((int) $page_id);
                if ($link && ! in_array($link, $urls, true)) {
                    $urls['page_' . ((int) $i + 1)] = $link;
                }
            }
        }

        // Sample of recent posts, capped.
        if ($post_limit > 0) {
            $posts = get_posts([
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => $post_limit,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'fields'         => 'ids',
            ]);
            foreach ((array) $posts as $i => $post_id) {
                $link = get_permalink((int) $post_id);
                if ($link && ! in_array($link, $urls, true)) {
                    $urls['post_' . ((int) $i + 1)] = $link;
                }
            }
        }

        // Manual extras from settings.
        $extra = array_filter(array_map('trim', explode("\n", (string) ($settings['smoke_urls'] ?? ''))));
        foreach ($extra as $i => $url) {
            $urls['custom_' . ($i + 1)] = esc_url_raw($url);
        }

        $urls = array_filter($urls);
        return apply_filters('msm_smoke_urls', $urls);
    }
}
