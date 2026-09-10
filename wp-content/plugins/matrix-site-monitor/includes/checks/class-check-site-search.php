<?php
/**
 * Front-end search returns results.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Site_Search extends Check_Base {
    public function id(): string { return 'site_search'; }
    public function label(): string { return 'Site search returns results'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'light'; }

    public function run(): array {
        $start = microtime(true);

        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        if (empty($posts)) {
            $posts = get_posts([
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
            ]);
        }

        if (empty($posts)) {
            return $this->skip('No published posts/pages to search against.', $start);
        }

        $term = $posts[0]->post_title;
        $words = preg_split('/\s+/', wp_strip_all_tags($term));
        $query = is_array($words) && ! empty($words[0]) ? $words[0] : 'a';

        $found = get_posts([
            's'              => $query,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
        ]);

        if (empty($found)) {
            return $this->fail('Search for "' . $query . '" returned no results.', $start);
        }

        $url = home_url('/?s=' . rawurlencode($query));
        $res = wp_remote_get($url, [
            'timeout'     => 15,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);

        if (is_wp_error($res)) {
            return $this->fail('Search page request failed: ' . $res->get_error_message(), $start);
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code < 200 || $code >= 400) {
            return $this->fail('Search page returned HTTP ' . $code . '.', $start);
        }

        return $this->pass('Search for "' . $query . '" returned results.', $start);
    }
}
