<?php
/**
 * Content quality sampler from docs/content.md.
 *
 * Capped homepage + recent posts/pages — not a full-site crawl.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;
use Matrix_Site_Monitor\Settings;

defined('ABSPATH') || exit;

class Check_Content extends Check_Base {
    public function id(): string { return 'content'; }
    public function label(): string { return 'Content quality sample'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);
        $settings = Settings::get();
        $link_limit  = max(1, min(20, (int) ($settings['content_link_sample'] ?? 8)));
        $image_limit = max(1, min(20, (int) ($settings['content_image_sample'] ?? 6)));

        $pages = $this->sample_html_pages(4);
        if (empty($pages)) {
            return $this->skip('No published pages/posts to sample.', $start, [
                'coverage' => $this->static_coverage('not_run', 'No content to sample'),
            ]);
        }

        $findings = [
            'broken_internal'   => [],
            'broken_external'   => [],
            'broken_images'     => [],
            'broken_pdfs'       => [],
            'empty_pages'       => [],
            'placeholder'       => [],
            'lorem'             => [],
            'missing_featured'  => [],
            'missing_excerpts'  => [],
            'draft_indexed'     => [],
            'orphaned'          => [],
            'broken_embeds'     => [],
            'videos'            => [],
        ];

        $host = wp_parse_url(home_url('/'), PHP_URL_HOST);

        foreach ($pages as $page) {
            $html = $page['html'];
            $url  = $page['url'];
            $title = $page['title'];

            // Empty / near-empty content (strip tags/scripts).
            $text = wp_strip_all_tags(
                preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html) ?? $html
            );
            $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
            if (strlen($text) < 80 && strpos($url, 'wp-login') === false) {
                $findings['empty_pages'][] = $title . ' (' . $url . ')';
            }

            if (preg_match('/lorem\s+ipsum|dolor\s+sit\s+amet|consectetur\s+adipiscing/i', $html)) {
                $findings['lorem'][] = $title;
            }
            if (preg_match('/\b(placeholder|coming soon|todo:|tbd|sample text|your text here)\b/i', $text)) {
                $findings['placeholder'][] = $title;
            }

            // Broken embeds / videos (YouTube/Vimeo iframes — presence only; availability = HEAD).
            if (preg_match_all('#<iframe[^>]+src=["\']([^"\']+)#i', $html, $m)) {
                foreach (array_slice($m[1], 0, 3) as $src) {
                    if (! preg_match('/youtube|youtu\.be|vimeo|wistia|player\./i', $src)) {
                        continue;
                    }
                    $code = $this->probe_url($src);
                    if ($code !== null && ($code < 200 || $code >= 400)) {
                        $findings['videos'][] = $src . ' (HTTP ' . $code . ')';
                        $findings['broken_embeds'][] = $src;
                    }
                }
            }

            // Collect links and images from this page.
            $internal = [];
            $external = [];
            $images   = [];
            $pdfs     = [];

            if (preg_match_all('~<a[^>]+href=["\']([^"\'#]+)["\']~i', $html, $am)) {
                foreach ($am[1] as $href) {
                    $href = html_entity_decode($href, ENT_QUOTES);
                    if (stripos($href, 'mailto:') === 0 || stripos($href, 'tel:') === 0 || stripos($href, 'javascript:') === 0) {
                        continue;
                    }
                    if (strpos($href, '//') === 0) {
                        $href = (is_ssl() ? 'https:' : 'http:') . $href;
                    } elseif (strpos($href, '/') === 0) {
                        $href = home_url($href);
                    } elseif (! preg_match('#^https?://#i', $href)) {
                        continue;
                    }

                    $link_host = wp_parse_url($href, PHP_URL_HOST);
                    $path = (string) wp_parse_url($href, PHP_URL_PATH);
                    if (preg_match('/\.pdf($|\?)/i', $path)) {
                        $pdfs[] = $href;
                    } elseif ($link_host && $host && strcasecmp((string) $link_host, (string) $host) === 0) {
                        $internal[] = $href;
                    } else {
                        $external[] = $href;
                    }
                }
            }

            if (preg_match_all('#<img[^>]+src=["\']([^"\']+)["\']#i', $html, $im)) {
                foreach ($im[1] as $src) {
                    $src = html_entity_decode($src, ENT_QUOTES);
                    if (strpos($src, 'data:') === 0) {
                        continue;
                    }
                    if (strpos($src, '//') === 0) {
                        $src = (is_ssl() ? 'https:' : 'http:') . $src;
                    } elseif (strpos($src, '/') === 0) {
                        $src = home_url($src);
                    }
                    if (preg_match('#^https?://#i', $src)) {
                        $images[] = $src;
                    }
                }
            }

            foreach (array_slice(array_unique($internal), 0, $link_limit) as $href) {
                $code = $this->probe_url($href);
                if ($code !== null && ($code === 404 || $code >= 500)) {
                    $findings['broken_internal'][] = $href . ' (HTTP ' . $code . ')';
                }
            }
            foreach (array_slice(array_unique($external), 0, (int) ceil($link_limit / 2)) as $href) {
                $code = $this->probe_url($href);
                if ($code !== null && ($code === 404 || $code >= 500)) {
                    $findings['broken_external'][] = $href . ' (HTTP ' . $code . ')';
                }
            }
            foreach (array_slice(array_unique($images), 0, $image_limit) as $src) {
                $code = $this->probe_url($src);
                if ($code !== null && ($code === 404 || $code >= 500)) {
                    $findings['broken_images'][] = $src . ' (HTTP ' . $code . ')';
                }
            }
            foreach (array_slice(array_unique($pdfs), 0, 4) as $pdf) {
                $code = $this->probe_url($pdf);
                if ($code !== null && ($code === 404 || $code >= 500)) {
                    $findings['broken_pdfs'][] = $pdf . ' (HTTP ' . $code . ')';
                }
            }
        }

        // Draft pages should not be publicly reachable / in public sitemap sense.
        $drafts = get_posts([
            'post_type'      => ['page', 'post'],
            'post_status'    => 'draft',
            'posts_per_page' => 5,
            'fields'         => 'ids',
        ]);
        foreach ($drafts as $draft_id) {
            // Drafts should 404 for guests; HTTP 200 is a leak.
            $permalink = get_permalink((int) $draft_id);
            if (! $permalink) {
                continue;
            }
            $code = $this->probe_url($permalink);
            // If a draft returns 200 to anonymous probe, that's a problem.
            if ($code === 200) {
                $findings['draft_indexed'][] = get_the_title((int) $draft_id) . ' (HTTP 200 while draft)';
            }
        }

        // Missing featured images / excerpts on recent posts.
        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
        ]);
        foreach ($posts as $post) {
            if (! has_post_thumbnail($post)) {
                $findings['missing_featured'][] = $post->post_title;
            }
            if (trim((string) $post->post_excerpt) === '' && str_word_count(wp_strip_all_tags($post->post_content)) > 50) {
                // Soft signal only — many themes don't use excerpts.
                $findings['missing_excerpts'][] = $post->post_title;
            }
        }

        // Orphaned pages: published pages not in any nav menu and not the front/posts page.
        $menu_ids = $this->menu_object_ids();
        $front    = (int) get_option('page_on_front');
        $blog     = (int) get_option('page_for_posts');
        $candidates = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 30,
            'post_parent'    => 0,
        ]);
        foreach ($candidates as $page) {
            $pid = (int) $page->ID;
            if ($pid === $front || $pid === $blog) {
                continue;
            }
            if (! in_array($pid, $menu_ids, true)) {
                $findings['orphaned'][] = $page->post_title;
            }
        }
        // Only flag if many orphans — avoid noise on brochure sites that don't use menus.
        if (count($findings['orphaned']) < 5) {
            $findings['orphaned'] = [];
        } else {
            $findings['orphaned'] = array_slice($findings['orphaned'], 0, 5);
        }

        $coverage = $this->build_coverage($findings);
        $failed   = array_filter($coverage, static function (array $r): bool {
            return ($r['status'] ?? '') === 'fail';
        });

        $extra = ['coverage' => $coverage];

        if ($failed) {
            $msgs = [];
            foreach (array_slice(array_values($failed), 0, 6) as $row) {
                $msgs[] = $row['line'] . (! empty($row['detail']) ? ': ' . $row['detail'] : '');
            }
            return $this->fail(implode('; ', $msgs) . '.', $start, $extra);
        }

        return $this->pass(
            sprintf('Content sample OK across %d page(s).', count($pages)),
            $start,
            $extra
        );
    }

    /**
     * @return array<int, array{line:string,mode:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'Broken internal links', 'mode' => 'automated'],
            ['line' => 'External links returning 404', 'mode' => 'automated'],
            ['line' => 'Images returning 404', 'mode' => 'automated'],
            ['line' => 'PDF links broken', 'mode' => 'automated'],
            ['line' => 'Missing downloadable files', 'mode' => 'partial'],
            ['line' => 'Empty pages', 'mode' => 'automated'],
            ['line' => 'Draft pages indexed', 'mode' => 'automated'],
            ['line' => 'Placeholder content', 'mode' => 'automated'],
            ['line' => 'Lorem Ipsum still present', 'mode' => 'automated'],
            ['line' => 'Orphaned pages', 'mode' => 'partial'],
            ['line' => 'Spelling mistakes', 'mode' => 'manual'],
            ['line' => 'Grammar mistakes', 'mode' => 'manual'],
            ['line' => 'Broken formatting', 'mode' => 'manual'],
            ['line' => 'Missing featured images', 'mode' => 'partial'],
            ['line' => 'Missing excerpts', 'mode' => 'partial'],
            ['line' => 'Duplicate content', 'mode' => 'manual'],
            ['line' => 'Incorrect publication dates', 'mode' => 'manual'],
            ['line' => 'Accessibility language issues', 'mode' => 'manual'],
            ['line' => 'Broken embeds', 'mode' => 'partial'],
            ['line' => 'Videos unavailable', 'mode' => 'partial'],
        ];
    }

    /**
     * @param array<string, array<int, string>> $findings Findings.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function build_coverage(array $findings): array {
        $map = [
            'Broken internal links'       => 'broken_internal',
            'External links returning 404'=> 'broken_external',
            'Images returning 404'        => 'broken_images',
            'PDF links broken'            => 'broken_pdfs',
            'Missing downloadable files'  => 'broken_pdfs', // partial: PDF sample doubles as downloadables
            'Empty pages'                 => 'empty_pages',
            'Draft pages indexed'         => 'draft_indexed',
            'Placeholder content'         => 'placeholder',
            'Lorem Ipsum still present'   => 'lorem',
            'Orphaned pages'              => 'orphaned',
            'Missing featured images'     => 'missing_featured',
            'Missing excerpts'            => 'missing_excerpts',
            'Broken embeds'               => 'broken_embeds',
            'Videos unavailable'          => 'videos',
        ];

        $coverage = [];
        foreach (self::doc_lines() as $row) {
            $line = $row['line'];
            $mode = $row['mode'];

            if ($mode === 'manual') {
                $coverage[] = [
                    'line'   => $line,
                    'mode'   => $mode,
                    'status' => 'manual',
                    'detail' => 'Better suited to AI / editorial review',
                ];
                continue;
            }

            $key = $map[$line] ?? '';
            $hits = $key !== '' ? ($findings[$key] ?? []) : [];

            // Soft lines: missing excerpts / orphans are info unless many.
            if ($line === 'Missing excerpts') {
                $coverage[] = [
                    'line'   => $line,
                    'mode'   => $mode,
                    'status' => empty($hits) ? 'pass' : 'skip',
                    'detail' => empty($hits)
                        ? 'Recent posts have excerpts or are short'
                        : 'No excerpt on: ' . implode(', ', array_slice($hits, 0, 3)) . ' (often OK)',
                ];
                continue;
            }

            if ($line === 'Missing featured images') {
                $coverage[] = [
                    'line'   => $line,
                    'mode'   => $mode,
                    'status' => empty($hits) ? 'pass' : 'skip',
                    'detail' => empty($hits)
                        ? 'Recent posts have featured images'
                        : 'Missing on: ' . implode(', ', array_slice($hits, 0, 3)) . ' (often OK)',
                ];
                continue;
            }

            if ($line === 'Missing downloadable files') {
                $coverage[] = [
                    'line'   => $line,
                    'mode'   => $mode,
                    'status' => empty($hits) ? 'pass' : 'fail',
                    'detail' => empty($hits)
                        ? 'No broken PDFs in sample (full downloadable product check is WooCommerce backlog)'
                        : implode('; ', array_slice($hits, 0, 3)),
                ];
                continue;
            }

            $coverage[] = [
                'line'   => $line,
                'mode'   => $mode,
                'status' => empty($hits) ? 'pass' : 'fail',
                'detail' => empty($hits) ? 'OK in sample' : implode('; ', array_slice($hits, 0, 3)),
            ];
        }

        return $coverage;
    }

    /**
     * @param string $status Default status.
     * @param string $detail Detail.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function static_coverage(string $status, string $detail): array {
        $rows = [];
        foreach (self::doc_lines() as $row) {
            $rows[] = [
                'line'   => $row['line'],
                'mode'   => $row['mode'],
                'status' => $row['mode'] === 'manual' ? 'manual' : $status,
                'detail' => $detail,
            ];
        }
        return $rows;
    }

    /**
     * Fetch a small set of HTML pages for sampling.
     *
     * @param int $limit Max pages.
     * @return array<int, array{url:string,title:string,html:string}>
     */
    private function sample_html_pages(int $limit): array {
        $targets = [
            ['url' => home_url('/'), 'title' => 'Home'],
        ];

        $ids = get_posts([
            'post_type'      => ['page', 'post'],
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);
        foreach ($ids as $id) {
            $link = get_permalink((int) $id);
            if ($link) {
                $targets[] = ['url' => $link, 'title' => get_the_title((int) $id)];
            }
        }

        $out = [];
        $seen = [];
        foreach ($targets as $t) {
            if (isset($seen[$t['url']])) {
                continue;
            }
            $seen[$t['url']] = true;
            $res = wp_remote_get($t['url'], [
                'timeout'     => 15,
                'redirection' => 3,
                'sslverify'   => false,
                'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
            ]);
            if (is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) >= 400) {
                continue;
            }
            $out[] = [
                'url'   => $t['url'],
                'title' => $t['title'],
                'html'  => (string) wp_remote_retrieve_body($res),
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * Lightweight URL probe (HEAD then GET fallback).
     *
     * @param string $url URL.
     * @return int|null HTTP code or null on transport error.
     */
    private function probe_url(string $url): ?int {
        $args = [
            'timeout'     => 8,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
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

    /**
     * Object IDs referenced in nav menus.
     *
     * @return array<int, int>
     */
    private function menu_object_ids(): array {
        $ids = [];
        $locations = get_nav_menu_locations();
        if (! is_array($locations)) {
            return [];
        }
        foreach ($locations as $menu_id) {
            $items = wp_get_nav_menu_items((int) $menu_id);
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (isset($item->object_id) && (int) $item->object_id > 0) {
                    $ids[] = (int) $item->object_id;
                }
            }
        }
        return array_values(array_unique($ids));
    }
}
