<?php
/**
 * SEO checks from docs/seo.md — homepage + capped page sample.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Seo extends Check_Base {
    public function id(): string { return 'seo'; }
    public function label(): string { return 'SEO sample'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);

        $findings = [
            'title'       => [],
            'dup_title'   => [],
            'meta_desc'   => [],
            'dup_h1'      => [],
            'canonical'   => [],
            'robots_txt'  => [],
            'sitemap'     => [],
            'schema'      => [],
            'indexed_404' => [],
            'noindex'     => [],
            'og'          => [],
            'twitter'     => [],
            'breadcrumbs' => [],
        ];

        $pages = $this->sample_pages(4);
        if (empty($pages)) {
            return $this->skip('No pages to sample for SEO.', $start, [
                'coverage' => $this->static_coverage('not_run', 'No HTML sample'),
            ]);
        }

        $titles = [];
        foreach ($pages as $page) {
            $html  = $page['html'];
            $label = $page['label'];
            $url   = $page['url'];

            // Title.
            $title = '';
            if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $tm)) {
                $title = trim(html_entity_decode(wp_strip_all_tags($tm[1]), ENT_QUOTES));
            }
            if ($title === '') {
                $findings['title'][] = $label . ' missing <title>';
            } else {
                $key = strtolower($title);
                if (! isset($titles[ $key ])) {
                    $titles[ $key ] = [];
                }
                $titles[ $key ][] = $label;
            }

            // Meta description.
            if (! preg_match('#<meta[^>]+name=["\']description["\'][^>]+content=["\'][^"\']+["\']#i', $html)
                && ! preg_match('#<meta[^>]+content=["\'][^"\']+["\'][^>]+name=["\']description["\']#i', $html)
            ) {
                $findings['meta_desc'][] = $label . ' missing meta description';
            }

            // Duplicate H1s on page.
            $h1_count = preg_match_all('#<h1\b[^>]*>#i', $html);
            if ($h1_count > 1) {
                $findings['dup_h1'][] = $label . ' has ' . $h1_count . ' H1s';
            }

            // Canonical.
            $canonical = '';
            if (preg_match('#<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']#i', $html, $cm)
                || preg_match('#<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']canonical["\']#i', $html, $cm)
            ) {
                $canonical = html_entity_decode($cm[1], ENT_QUOTES);
                $code = $this->probe_code($canonical);
                if ($code === 404 || ($code !== null && $code >= 500)) {
                    $findings['canonical'][] = $label . ' canonical → HTTP ' . $code . ' (' . $canonical . ')';
                }
            } else {
                $findings['canonical'][] = $label . ' missing canonical link';
            }

            // Noindex.
            if (preg_match('#<meta[^>]+name=["\']robots["\'][^>]+content=["\'][^"\']*noindex#i', $html)
                || preg_match('#<meta[^>]+content=["\'][^"\']*noindex[^"\']*["\'][^>]+name=["\']robots["\']#i', $html)
            ) {
                if ($url === home_url('/') || $url === home_url()) {
                    $findings['noindex'][] = 'Homepage has noindex robots meta';
                } else {
                    $findings['noindex'][] = $label . ' has noindex';
                }
            }

            // Open Graph.
            if (! preg_match('#property=["\']og:title["\']#i', $html)) {
                $findings['og'][] = $label . ' missing og:title';
            }
            if (! preg_match('#property=["\']og:image["\']#i', $html)) {
                $findings['og'][] = $label . ' missing og:image';
            }

            // Twitter cards.
            if (! preg_match('#name=["\']twitter:card["\']#i', $html)
                && ! preg_match('#property=["\']twitter:card["\']#i', $html)
            ) {
                $findings['twitter'][] = $label . ' missing twitter:card';
            }

            // Schema / JSON-LD.
            if (preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $jm)) {
                foreach ($jm[1] as $json) {
                    $json = trim(html_entity_decode($json, ENT_QUOTES));
                    $data = json_decode($json, true);
                    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                        $findings['schema'][] = $label . ' has invalid JSON-LD (' . json_last_error_msg() . ')';
                    }
                }
            }

            // Breadcrumbs — schema or markup.
            $has_crumb_schema = (bool) preg_match('/BreadcrumbList/i', $html);
            $has_crumb_nav    = (bool) preg_match('#class=["\'][^"\']*breadcrumb#i', $html);
            if ($has_crumb_schema) {
                if (preg_match('#application/ld\+json["\'][^>]*>(.*?)</script>#is', $html)) {
                    // Check BreadcrumbList item URLs if present.
                    if (preg_match_all('#"item"\s*:\s*"(https?://[^"]+)"#i', $html, $bm)) {
                        foreach (array_slice($bm[1], 0, 3) as $item) {
                            $code = $this->probe_code($item);
                            if ($code === 404 || ($code !== null && $code >= 500)) {
                                $findings['breadcrumbs'][] = 'Breadcrumb URL HTTP ' . $code . ': ' . $item;
                            }
                        }
                    }
                }
            } elseif ($has_crumb_nav) {
                // Markup present — soft OK unless empty links.
                if (preg_match('#class=["\'][^"\']*breadcrumb[^"\']*["\'][^>]*>.*?<a[^>]+href=["\']#is', $html) === 0) {
                    $findings['breadcrumbs'][] = $label . ' breadcrumb markup without links';
                }
            }
        }

        foreach ($titles as $title => $labels) {
            if (count($labels) > 1) {
                $findings['dup_title'][] = '"' . $title . '" on ' . implode(', ', $labels);
            }
        }

        // Soft: OG/Twitter only fail on homepage, not every inner page.
        $home_label = 'Home';
        $findings['og'] = array_values(array_filter($findings['og'], static function (string $line) use ($home_label): bool {
            return strpos($line, $home_label) === 0 || strpos($line, 'Home ') === 0;
        }));
        $findings['twitter'] = array_values(array_filter($findings['twitter'], static function (string $line) use ($home_label): bool {
            return strpos($line, $home_label) === 0 || strpos($line, 'Home ') === 0;
        }));
        // meta description: homepage + sample — keep all but cap.
        $findings['meta_desc'] = array_slice($findings['meta_desc'], 0, 4);
        $findings['noindex']   = array_values(array_unique($findings['noindex']));

        // blog_public / search engine visibility.
        if ((string) get_option('blog_public') === '0') {
            $findings['noindex'][] = 'Search engine visibility discouraged (blog_public=0)';
        }

        // robots.txt
        $robots_url = home_url('/robots.txt');
        $robots     = wp_remote_get($robots_url, [
            'timeout'   => 10,
            'sslverify' => false,
            'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        if (is_wp_error($robots)) {
            $findings['robots_txt'][] = 'Could not fetch robots.txt: ' . $robots->get_error_message();
        } else {
            $rcode = (int) wp_remote_retrieve_response_code($robots);
            $rbody = (string) wp_remote_retrieve_body($robots);
            if ($rcode === 404 || $rcode >= 500) {
                $findings['robots_txt'][] = 'robots.txt HTTP ' . $rcode;
            } elseif (preg_match('/^\s*Disallow:\s*\/\s*$/mi', $rbody)
                && (string) get_option('blog_public') !== '0'
            ) {
                $findings['robots_txt'][] = 'robots.txt Disallow: / while site is public';
            }
            if ($rcode >= 200 && $rcode < 400 && ! preg_match('/^\s*Sitemap:/mi', $rbody)) {
                $findings['robots_txt'][] = 'robots.txt has no Sitemap: directive';
            }
        }

        // XML sitemap.
        $sitemap_urls = [
            home_url('/wp-sitemap.xml'),
            home_url('/sitemap_index.xml'),
            home_url('/sitemap.xml'),
        ];
        $sitemap_ok = false;
        $sitemap_body = '';
        $sitemap_used = '';
        foreach ($sitemap_urls as $surl) {
            $sres = wp_remote_get($surl, [
                'timeout'   => 12,
                'sslverify' => false,
                'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
            ]);
            if (is_wp_error($sres)) {
                continue;
            }
            $scode = (int) wp_remote_retrieve_response_code($sres);
            $sbody = (string) wp_remote_retrieve_body($sres);
            if ($scode >= 200 && $scode < 400 && (stripos($sbody, '<urlset') !== false || stripos($sbody, '<sitemapindex') !== false)) {
                $sitemap_ok   = true;
                $sitemap_body = $sbody;
                $sitemap_used = $surl;
                break;
            }
        }
        if (! $sitemap_ok) {
            $findings['sitemap'][] = 'No XML sitemap found at wp-sitemap.xml / sitemap_index.xml / sitemap.xml';
        } else {
            // 404 pages indexed — sample locs from sitemap.
            if (preg_match_all('#<loc>\s*(https?://[^<]+)\s*</loc>#i', $sitemap_body, $lm)) {
                foreach (array_slice(array_unique($lm[1]), 0, 6) as $loc) {
                    $code = $this->probe_code(trim($loc));
                    if ($code === 404) {
                        $findings['indexed_404'][] = trim($loc) . ' in sitemap but HTTP 404';
                    }
                }
            }
        }

        // Soft breadcrumbs: only fail if we found broken URLs; missing crumbs = skip.
        $has_any_crumb_signal = false;
        foreach ($pages as $page) {
            if (preg_match('/BreadcrumbList|breadcrumb/i', $page['html'])) {
                $has_any_crumb_signal = true;
                break;
            }
        }

        $coverage = $this->build_coverage($findings, [
            'sitemap_ok'   => $sitemap_ok,
            'sitemap_used' => $sitemap_used,
            'has_crumbs'   => $has_any_crumb_signal,
        ]);

        $failed = array_filter($coverage, static function (array $r): bool {
            return ($r['status'] ?? '') === 'fail';
        });

        $extra = ['coverage' => $coverage];

        if ($failed) {
            $msgs = [];
            foreach (array_slice(array_values($failed), 0, 8) as $row) {
                $msgs[] = $row['line'] . (! empty($row['detail']) ? ': ' . $row['detail'] : '');
            }
            return $this->fail(implode('; ', $msgs) . '.', $start, $extra);
        }

        return $this->pass(
            sprintf('SEO sample OK across %d page(s).', count($pages)),
            $start,
            $extra
        );
    }

    /**
     * @return array<int, array{line:string,mode:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'Missing title tags', 'mode' => 'automated'],
            ['line' => 'Duplicate titles', 'mode' => 'automated'],
            ['line' => 'Missing meta descriptions', 'mode' => 'automated'],
            ['line' => 'Duplicate H1s', 'mode' => 'automated'],
            ['line' => 'Broken canonical URLs', 'mode' => 'automated'],
            ['line' => 'Robots.txt issues', 'mode' => 'automated'],
            ['line' => 'XML sitemap missing', 'mode' => 'automated'],
            ['line' => 'Broken schema', 'mode' => 'partial'],
            ['line' => '404 pages indexed', 'mode' => 'partial'],
            ['line' => 'Noindex accidentally enabled', 'mode' => 'automated'],
            ['line' => 'Missing Open Graph tags', 'mode' => 'automated'],
            ['line' => 'Missing Twitter cards', 'mode' => 'automated'],
            ['line' => 'Broken breadcrumbs', 'mode' => 'partial'],
        ];
    }

    /**
     * @param array<string, array<int, string>> $findings Findings.
     * @param array<string, mixed>              $meta     Context.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function build_coverage(array $findings, array $meta): array {
        $map = [
            'Missing title tags'              => 'title',
            'Duplicate titles'                => 'dup_title',
            'Missing meta descriptions'       => 'meta_desc',
            'Duplicate H1s'                   => 'dup_h1',
            'Broken canonical URLs'           => 'canonical',
            'Robots.txt issues'               => 'robots_txt',
            'XML sitemap missing'             => 'sitemap',
            'Broken schema'                   => 'schema',
            '404 pages indexed'               => 'indexed_404',
            'Noindex accidentally enabled'    => 'noindex',
            'Missing Open Graph tags'         => 'og',
            'Missing Twitter cards'           => 'twitter',
            'Broken breadcrumbs'              => 'breadcrumbs',
        ];

        $pass = [
            'title'       => 'Sample pages have <title>',
            'dup_title'   => 'No duplicate titles in sample',
            'meta_desc'   => 'Meta descriptions present in sample',
            'dup_h1'      => 'No multi-H1 pages in sample',
            'canonical'   => 'Canonicals present and reachable',
            'robots_txt'  => 'robots.txt OK',
            'sitemap'     => ! empty($meta['sitemap_used'])
                ? 'Sitemap found: ' . $meta['sitemap_used']
                : 'Sitemap present',
            'schema'      => 'No invalid JSON-LD in sample',
            'indexed_404' => 'No 404 URLs in sitemap sample',
            'noindex'     => 'No accidental sitewide/home noindex',
            'og'          => 'Homepage has og:title and og:image',
            'twitter'     => 'Homepage has twitter:card',
            'breadcrumbs' => 'No broken breadcrumb URLs in sample',
        ];

        $coverage = [];
        foreach (self::doc_lines() as $row) {
            $line = $row['line'];
            $mode = $row['mode'];
            $key  = $map[$line] ?? '';
            $hits = $key !== '' ? ($findings[$key] ?? []) : [];

            if ($key === 'schema' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => 'JSON-LD parse OK (or none present)',
                ];
                continue;
            }

            if ($key === 'breadcrumbs') {
                if (! empty($hits)) {
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'fail',
                        'detail' => implode('; ', array_slice($hits, 0, 3)),
                    ];
                } elseif (empty($meta['has_crumbs'])) {
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'skip',
                        'detail' => 'No breadcrumb markup/schema in sample',
                    ];
                } else {
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'pass',
                        'detail' => $pass['breadcrumbs'],
                    ];
                }
                continue;
            }

            if ($key === 'indexed_404' && empty($hits) && empty($meta['sitemap_ok'])) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'skip',
                    'detail' => 'No sitemap to sample for 404 locs',
                ];
                continue;
            }

            // Soft: missing OG/Twitter on sites without social plugin — still fail homepage if missing.
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

    /**
     * @param int $limit Max pages.
     * @return array<int, array{url:string,label:string,html:string}>
     */
    private function sample_pages(int $limit): array {
        $targets = [
            ['url' => home_url('/'), 'label' => 'Home'],
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
                $targets[] = ['url' => $link, 'label' => get_the_title((int) $id) ?: ('#' . $id)];
            }
        }

        $out  = [];
        $seen = [];
        foreach ($targets as $t) {
            if (isset($seen[ $t['url'] ])) {
                continue;
            }
            $seen[ $t['url'] ] = true;
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
                'label' => $t['label'],
                'html'  => (string) wp_remote_retrieve_body($res),
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    private function probe_code(string $url): ?int {
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
}
