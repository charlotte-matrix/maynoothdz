<?php
/**
 * Performance heuristics from docs/Performance.md — line coverage.
 *
 * No Lighthouse/CrUX — TTFB/load from server-side fetch; CWV stay manual.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Performance extends Check_Base {
    public function id(): string { return 'performance'; }
    public function label(): string { return 'Performance sample'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        global $wpdb;
        $start = microtime(true);

        $findings = [
            'slow_load'    => [],
            'ttfb'         => [],
            'cwv'          => [],
            'lcp'          => [],
            'cls'          => [],
            'tbt'          => [],
            'excess_js'    => [],
            'blocking_css' => [],
            'large_images' => [],
            'lazy'         => [],
            'page_cache'   => [],
            'cache_purge'  => [],
            'object_cache' => [],
            'slow_queries' => [],
            'autoload'     => [],
            'wp_options'   => [],
            'revisions'    => [],
            'cpu'          => [],
            'memory'       => [],
            'workers'      => [],
        ];

        $url = home_url('/');
        $t0  = microtime(true);
        $res = wp_remote_get($url, [
            'timeout'     => 30,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        $elapsed   = microtime(true) - $t0;
        $ttfb      = null;
        $cache_hit = ['present' => false, 'detail' => 'No page cache detected'];

        $html    = '';
        $headers = [];
        if (is_wp_error($res)) {
            $findings['slow_load'][] = 'Homepage fetch failed: ' . $res->get_error_message();
            $findings['ttfb'][]      = 'Could not measure TTFB';
        } else {
            $code = (int) wp_remote_retrieve_response_code($res);
            $html = (string) wp_remote_retrieve_body($res);
            $headers = wp_remote_retrieve_headers($res);
            if ($code < 200 || $code >= 400) {
                $findings['slow_load'][] = 'Homepage HTTP ' . $code;
            }
            // Full request time as load proxy (includes download).
            if ($elapsed > 3.0) {
                $findings['slow_load'][] = sprintf('Homepage fetch took %.2fs (threshold 3s)', $elapsed);
            }
            // TTFB approximation: total time when body is small, or use curl if available.
            $ttfb = $this->estimate_ttfb($url, $elapsed);
            if ($ttfb !== null && $ttfb > 1.2) {
                $findings['ttfb'][] = sprintf('TTFB ~%.2fs (threshold 1.2s)', $ttfb);
            }

            // Page cache headers / plugins.
            $cache_hit = $this->detect_page_cache($headers, $html);
            if (! $cache_hit['present']) {
                $findings['page_cache'][] = 'No page-cache headers or known cache plugin markers detected';
            }

            // Excessive JS / render-blocking CSS / images / lazy.
            if ($html !== '') {
                $scripts = [];
                if (preg_match_all('#<script[^>]+src=["\']([^"\']+)["\']#i', $html, $sm)) {
                    $scripts = $sm[1];
                }
                if (count($scripts) > 25) {
                    $findings['excess_js'][] = count($scripts) . ' external script tags (threshold 25)';
                }
                $js_bytes = 0;
                foreach (array_slice($scripts, 0, 15) as $src) {
                    $abs = $this->abs_url($src);
                    if (! $abs) {
                        continue;
                    }
                    $len = $this->remote_content_length($abs);
                    if ($len !== null) {
                        $js_bytes += $len;
                    }
                }
                if ($js_bytes > 1.5 * 1024 * 1024) {
                    $findings['excess_js'][] = 'Sampled JS ~' . size_format($js_bytes) . ' (threshold 1.5MB)';
                }

                $blocking = 0;
                if (preg_match_all('#<link[^>]+rel=["\']stylesheet["\'][^>]*>#i', $html, $lm)) {
                    foreach ($lm[0] as $tag) {
                        if (preg_match('#media=["\']print["\']#i', $tag)) {
                            continue;
                        }
                        if (preg_match('#\b(media=["\'][^"\']*["\'])#i', $tag, $mm)
                            && ! preg_match('#media=["\'](?:all|screen)?["\']#i', $tag)
                        ) {
                            continue;
                        }
                        $blocking++;
                    }
                }
                if ($blocking > 8) {
                    $findings['blocking_css'][] = $blocking . ' render-blocking stylesheets in sample';
                }

                $imgs = [];
                if (preg_match_all('#<img\b[^>]*>#i', $html, $im)) {
                    $imgs = $im[0];
                }
                $no_lazy = 0;
                foreach (array_slice($imgs, 0, 20) as $tag) {
                    if (! preg_match('#\bloading=["\']lazy["\']#i', $tag)
                        && ! preg_match('#\bfetchpriority=["\']high["\']#i', $tag)
                    ) {
                        // Skip tiny icons / tracking pixels.
                        if (preg_match('#(?:width|height)=["\'](?:1|2)["\']#i', $tag)) {
                            continue;
                        }
                        $no_lazy++;
                    }
                    if (preg_match('#src=["\']([^"\']+)#i', $tag, $srcm)) {
                        $abs = $this->abs_url($srcm[1]);
                        if ($abs && preg_match('#\.(jpe?g|png|webp|gif)(\?|$)#i', $abs)) {
                            $len = $this->remote_content_length($abs);
                            if ($len !== null && $len > 500 * 1024) {
                                $findings['large_images'][] = basename(wp_parse_url($abs, PHP_URL_PATH) ?: $abs) . ' ' . size_format($len);
                            }
                        }
                    }
                }
                if ($no_lazy >= 8) {
                    $findings['lazy'][] = $no_lazy . ' images in sample without loading="lazy"';
                }
                if (count($findings['large_images']) > 3) {
                    $findings['large_images'] = array_slice($findings['large_images'], 0, 3);
                }
            }
        }

        // Object cache.
        $dropin = WP_CONTENT_DIR . '/object-cache.php';
        if (! file_exists($dropin) || ! wp_using_ext_object_cache()) {
            $findings['object_cache'][] = file_exists($dropin)
                ? 'object-cache.php present but external cache not active'
                : 'No persistent object cache drop-in';
        }

        // Slow DB — time a few typical queries.
        $q0 = microtime(true);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->get_results("SELECT option_name FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto') LIMIT 50");
        $q_elapsed = microtime(true) - $q0;
        if ($q_elapsed > 1.0) {
            $findings['slow_queries'][] = sprintf('Sample DB queries took %.2fs', $q_elapsed);
        }

        // Autoloaded options size.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $autoload_bytes = (int) $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto')"
        );
        if ($autoload_bytes > 1024 * 1024) {
            $findings['autoload'][] = 'Autoloaded options ~' . size_format($autoload_bytes) . ' (threshold 1MB)';
        }

        // wp_options oversized (row count / data length).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $opt_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $opt_bytes = (int) $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options}");
        if ($opt_count > 5000 || $opt_bytes > 5 * 1024 * 1024) {
            $findings['wp_options'][] = sprintf(
                '%d rows, ~%s data',
                $opt_count,
                size_format(max(0, $opt_bytes))
            );
        }

        // Revisions.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $revisions = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
            'revision'
        ));
        if ($revisions > 500) {
            $findings['revisions'][] = $revisions . ' post revisions';
        }

        // Memory.
        $limit = $this->to_bytes((string) ini_get('memory_limit'));
        $peak  = memory_get_peak_usage(true);
        if ($limit > 0 && $peak > ($limit * 0.85)) {
            $findings['memory'][] = sprintf(
                'Peak %s is >85%% of limit %s',
                size_format($peak),
                ini_get('memory_limit')
            );
        }

        // Host-provided hooks for CPU / workers / CWV / cache purge.
        $cpu = apply_filters('msm_performance_cpu_issue', null);
        if (is_string($cpu) && $cpu !== '') {
            $findings['cpu'][] = $cpu;
        }
        $workers = apply_filters('msm_performance_workers_issue', null);
        if (is_string($workers) && $workers !== '') {
            $findings['workers'][] = $workers;
        }
        $purge = apply_filters('msm_performance_cache_purge_issue', null);
        if (is_string($purge) && $purge !== '') {
            $findings['cache_purge'][] = $purge;
        }
        $cwv = apply_filters('msm_performance_cwv_issue', null);
        if (is_string($cwv) && $cwv !== '') {
            $findings['cwv'][] = $cwv;
            $findings['lcp'][] = $cwv;
            $findings['cls'][] = $cwv;
            $findings['tbt'][] = $cwv;
        }

        $meta = [
            'elapsed'     => $elapsed,
            'ttfb'        => $ttfb,
            'cache'       => ! empty($cache_hit['present']),
            'cache_detail'=> (string) ($cache_hit['detail'] ?? ''),
            'autoload'    => $autoload_bytes,
            'revisions'   => $revisions,
        ];

        $coverage = $this->build_coverage($findings, $meta);
        $failed   = array_filter($coverage, static function (array $r): bool {
            return ($r['status'] ?? '') === 'fail';
        });

        $extra = ['coverage' => $coverage, 'metrics' => $meta];

        if ($failed) {
            $msgs = [];
            foreach (array_slice(array_values($failed), 0, 8) as $row) {
                $msgs[] = $row['line'] . (! empty($row['detail']) ? ': ' . $row['detail'] : '');
            }
            return $this->fail(implode('; ', $msgs) . '.', $start, $extra);
        }

        return $this->pass(
            sprintf('Performance sample OK (home fetch %.2fs).', $elapsed),
            $start,
            $extra
        );
    }

    /**
     * @return array<int, array{line:string,mode:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'Slow page load', 'mode' => 'automated'],
            ['line' => 'High Time To First Byte (TTFB)', 'mode' => 'partial'],
            ['line' => 'Poor Core Web Vitals', 'mode' => 'manual'],
            ['line' => 'Largest Contentful Paint (LCP) too slow', 'mode' => 'manual'],
            ['line' => 'Cumulative Layout Shift (CLS)', 'mode' => 'manual'],
            ['line' => 'High Total Blocking Time', 'mode' => 'manual'],
            ['line' => 'Excessive JavaScript', 'mode' => 'automated'],
            ['line' => 'Render blocking CSS', 'mode' => 'automated'],
            ['line' => 'Large unoptimised images', 'mode' => 'automated'],
            ['line' => 'Missing lazy loading', 'mode' => 'automated'],
            ['line' => 'Missing page caching', 'mode' => 'partial'],
            ['line' => 'Cache not purging', 'mode' => 'manual'],
            ['line' => 'Object cache disabled', 'mode' => 'automated'],
            ['line' => 'Slow database queries', 'mode' => 'partial'],
            ['line' => 'Excessive autoloaded options', 'mode' => 'automated'],
            ['line' => 'Oversized wp_options table', 'mode' => 'automated'],
            ['line' => 'Huge post revisions table', 'mode' => 'automated'],
            ['line' => 'High CPU usage', 'mode' => 'manual'],
            ['line' => 'High memory usage', 'mode' => 'automated'],
            ['line' => 'PHP workers exhausted', 'mode' => 'manual'],
        ];
    }

    /**
     * @param array<string, array<int, string>> $findings Findings.
     * @param array<string, mixed>              $meta     Metrics.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function build_coverage(array $findings, array $meta): array {
        $map = [
            'Slow page load'                           => 'slow_load',
            'High Time To First Byte (TTFB)'           => 'ttfb',
            'Poor Core Web Vitals'                     => 'cwv',
            'Largest Contentful Paint (LCP) too slow'  => 'lcp',
            'Cumulative Layout Shift (CLS)'            => 'cls',
            'High Total Blocking Time'                 => 'tbt',
            'Excessive JavaScript'                     => 'excess_js',
            'Render blocking CSS'                      => 'blocking_css',
            'Large unoptimised images'                 => 'large_images',
            'Missing lazy loading'                     => 'lazy',
            'Missing page caching'                     => 'page_cache',
            'Cache not purging'                        => 'cache_purge',
            'Object cache disabled'                    => 'object_cache',
            'Slow database queries'                    => 'slow_queries',
            'Excessive autoloaded options'             => 'autoload',
            'Oversized wp_options table'               => 'wp_options',
            'Huge post revisions table'                => 'revisions',
            'High CPU usage'                           => 'cpu',
            'High memory usage'                        => 'memory',
            'PHP workers exhausted'                    => 'workers',
        ];

        $pass = [
            'slow_load'    => sprintf('Homepage fetch %.2fs', (float) ($meta['elapsed'] ?? 0)),
            'ttfb'         => isset($meta['ttfb']) && $meta['ttfb'] !== null
                ? sprintf('TTFB ~%.2fs', (float) $meta['ttfb'])
                : 'TTFB within threshold / not measured',
            'excess_js'    => 'Script count/size within thresholds',
            'blocking_css' => 'Stylesheet count within threshold',
            'large_images' => 'No sampled images over 500KB',
            'lazy'         => 'Most sampled images use lazy loading (or few imgs)',
            'page_cache'   => (string) ($meta['cache_detail'] ?? 'Page cache signals present'),
            'object_cache' => 'External object cache active',
            'slow_queries' => 'Sample queries under 1s',
            'autoload'     => 'Autoload size under 1MB',
            'wp_options'   => 'wp_options size/count OK',
            'revisions'    => (int) ($meta['revisions'] ?? 0) . ' revisions (threshold 500)',
            'memory'       => 'Peak memory under 85% of limit',
        ];

        $manual_detail = [
            'cwv'         => 'Needs Lighthouse / CrUX / PageSpeed (msm_performance_cwv_issue filter)',
            'lcp'         => 'Needs lab/field LCP measurement',
            'cls'         => 'Needs lab/field CLS measurement',
            'tbt'         => 'Needs lab TBT / long-task measurement',
            'cache_purge' => 'Needs purge drill or msm_performance_cache_purge_issue filter',
            'cpu'         => 'Needs host metrics (msm_performance_cpu_issue filter)',
            'workers'     => 'Needs host PHP worker metrics (msm_performance_workers_issue filter)',
        ];

        $coverage = [];
        foreach (self::doc_lines() as $row) {
            $line = $row['line'];
            $mode = $row['mode'];
            $key  = $map[$line] ?? '';
            $hits = $key !== '' ? ($findings[$key] ?? []) : [];

            if ($mode === 'manual') {
                if (! empty($hits)) {
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'fail',
                        'detail' => implode('; ', $hits),
                    ];
                } else {
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'manual',
                        'detail' => $manual_detail[$key] ?? 'Manual / external',
                    ];
                }
                continue;
            }

            // Soft: missing page cache is skip on local.
            if ($key === 'page_cache' && ! empty($hits) && $this->is_local()) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'skip',
                    'detail' => 'No page cache on local — expected',
                ];
                continue;
            }

            if ($key === 'object_cache' && ! empty($hits) && $this->is_local_object_cache_optional()) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'skip',
                    'detail' => implode('; ', $hits) . ' (Redis/object cache is a host concern on Local)',
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
     * @param mixed $headers Response headers.
     * @param string $html HTML body.
     * @return array{present:bool,detail:string}
     */
    private function detect_page_cache($headers, string $html): array {
        $hay = '';
        if (is_object($headers) && method_exists($headers, 'getAll')) {
            $hay = strtolower(wp_json_encode($headers->getAll()) ?: '');
        } elseif (is_array($headers)) {
            $hay = strtolower(wp_json_encode($headers) ?: '');
        }

        $signals = [
            'x-cache', 'cf-cache-status', 'x-vercel-cache', 'x-drupal-cache',
            'x-proxy-cache', 'x-litespeed-cache', 'x-wp-cf-super-cache',
            'x-sucuri-cache', 'age:', 'x-cache-enabled',
        ];
        foreach ($signals as $sig) {
            if (strpos($hay, $sig) !== false) {
                return ['present' => true, 'detail' => 'Cache-related response header present'];
            }
        }

        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = [
            'wp-super-cache/wp-cache.php'           => 'WP Super Cache',
            'w3-total-cache/w3-total-cache.php'     => 'W3 Total Cache',
            'litespeed-cache/litespeed-cache.php'   => 'LiteSpeed Cache',
            'wp-rocket/wp-rocket.php'               => 'WP Rocket',
            'cache-enabler/cache-enabler.php'       => 'Cache Enabler',
            'sg-cachepress/sg-cachepress.php'       => 'SiteGround Optimizer',
            'nginx-helper/nginx-helper.php'         => 'Nginx Helper',
        ];
        foreach ($plugins as $file => $name) {
            if (is_plugin_active($file)) {
                return ['present' => true, 'detail' => $name . ' active'];
            }
        }

        if (defined('WP_CACHE') && WP_CACHE) {
            return ['present' => true, 'detail' => 'WP_CACHE is true'];
        }

        if (preg_match('/<!--\s*(?:Cached|Performance optimized).*(?:WP Rocket|LiteSpeed|W3 Total Cache)/i', $html)) {
            return ['present' => true, 'detail' => 'Cache comment in HTML'];
        }

        return ['present' => false, 'detail' => 'No page cache detected'];
    }

    private function estimate_ttfb(string $url, float $fallback): ?float {
        if (! function_exists('curl_init')) {
            return $fallback;
        }
        $ch = curl_init($url);
        if (! $ch) {
            return $fallback;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'matrix-site-monitor/' . MSM_VERSION,
            CURLOPT_NOBODY         => false,
        ]);
        curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        if (! empty($info['starttransfer_time'])) {
            return (float) $info['starttransfer_time'];
        }
        return $fallback;
    }

    private function abs_url(string $src): ?string {
        $src = html_entity_decode($src, ENT_QUOTES);
        if (strpos($src, 'data:') === 0) {
            return null;
        }
        if (strpos($src, '//') === 0) {
            $src = (is_ssl() ? 'https:' : 'http:') . $src;
        } elseif (strpos($src, '/') === 0) {
            $src = home_url($src);
        }
        return preg_match('#^https?://#i', $src) ? $src : null;
    }

    private function remote_content_length(string $url): ?int {
        $res = wp_remote_head($url, [
            'timeout'   => 6,
            'sslverify' => false,
            'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        if (is_wp_error($res)) {
            return null;
        }
        $len = wp_remote_retrieve_header($res, 'content-length');
        if (is_array($len)) {
            $len = $len[0] ?? '';
        }
        if ($len !== '' && is_numeric($len)) {
            return (int) $len;
        }
        return null;
    }

    private function to_bytes(string $value): int {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $unit = strtolower(substr($value, -1));
        $num  = (int) $value;
        switch ($unit) {
            case 'g':
                return $num * 1024 * 1024 * 1024;
            case 'm':
                return $num * 1024 * 1024;
            case 'k':
                return $num * 1024;
            default:
                return (int) $value;
        }
    }

    private function is_local(): bool {
        return $this->is_dev_environment();
    }

    /**
     * Redis/object-cache is a host concern. Live QA still requires page cache (WP Rocket),
     * but Local WP cannot provide a persistent object cache without Redis.
     */
    private function is_local_object_cache_optional(): bool {
        if ($this->is_dev_environment()) {
            return true;
        }

        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : '';
        return in_array($env, ['local', 'development'], true);
    }
}
