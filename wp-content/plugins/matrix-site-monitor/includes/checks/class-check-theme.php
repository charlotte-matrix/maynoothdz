<?php
/**
 * Theme health from docs/Theme.md — line coverage.
 *
 * No visual browser — HTML/CSS/JS asset heuristics + child-theme structure.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Theme extends Check_Base {
    public function id(): string { return 'theme'; }
    public function label(): string { return 'Theme health'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);

        $findings = [
            'layout'     => [],
            'css'        => [],
            'js'         => [],
            'child'      => [],
            'overrides'  => [],
            'responsive' => [],
            'headerfoot' => [],
        ];

        $theme      = wp_get_theme();
        $stylesheet = get_stylesheet();
        $template   = get_template();
        $is_child   = ($stylesheet !== $template);

        $res = wp_remote_get(home_url('/'), [
            'timeout'     => 20,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);

        $html = '';
        if (is_wp_error($res)) {
            $findings['layout'][] = 'Could not fetch homepage: ' . $res->get_error_message();
        } else {
            $code = (int) wp_remote_retrieve_response_code($res);
            $html = (string) wp_remote_retrieve_body($res);
            if ($code < 200 || $code >= 400) {
                $findings['layout'][] = 'Homepage HTTP ' . $code;
            }
            if ($html !== '' && strlen(trim(wp_strip_all_tags(
                preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html) ?? $html
            ))) < 40) {
                $findings['layout'][] = 'Homepage body nearly empty (possible broken layout)';
            }
        }

        if ($html !== '') {
            // CSS missing — broken stylesheet URLs (prefer theme/content stylesheets).
            $css_urls = [];
            if (preg_match_all('#<link[^>]+rel=["\']stylesheet["\'][^>]*>#i', $html, $lm)) {
                foreach ($lm[0] as $tag) {
                    if (preg_match('#href=["\']([^"\']+)#i', $tag, $hm)) {
                        $css_urls[] = $hm[1];
                    }
                }
            }
            $theme_css = 0;
            foreach (array_slice(array_unique($css_urls), 0, 12) as $href) {
                $abs = $this->abs_url($href);
                if (! $abs) {
                    continue;
                }
                if (stripos($abs, '/themes/') !== false || stripos($abs, '/uploads/') !== false) {
                    $theme_css++;
                }
                $scode = $this->probe_code($abs);
                if ($scode === 404 || ($scode !== null && $scode >= 500)) {
                    $findings['css'][] = $abs . ' (HTTP ' . $scode . ')';
                }
            }
            if ($theme_css === 0 && count($css_urls) === 0) {
                $findings['css'][] = 'No stylesheet <link> tags on homepage';
            }

            // JS errors proxy — theme script 404s + uncaught text.
            if (preg_match_all('#<script[^>]+src=["\']([^"\']+)["\']#i', $html, $sm)) {
                foreach (array_slice(array_unique($sm[1]), 0, 12) as $src) {
                    $abs = $this->abs_url($src);
                    if (! $abs || stripos($abs, '/themes/') === false) {
                        continue;
                    }
                    $scode = $this->probe_code($abs);
                    if ($scode === 404 || ($scode !== null && $scode >= 500)) {
                        $findings['js'][] = $abs . ' (HTTP ' . $scode . ')';
                    }
                }
            }
            if (preg_match('/Uncaught\s+(?:TypeError|ReferenceError|SyntaxError)/i', $html)) {
                $findings['js'][] = 'Uncaught JS error text in homepage HTML';
            }

            // Header / footer missing.
            $has_header = (bool) preg_match('#<(?:header|div)[^>]*(?:id|class)=["\'][^"\']*header#i', $html)
                || (bool) preg_match('#<header\b#i', $html);
            $has_footer = (bool) preg_match('#<(?:footer|div)[^>]*(?:id|class)=["\'][^"\']*footer#i', $html)
                || (bool) preg_match('#<footer\b#i', $html);
            $has_wp_footer = (strpos($html, 'wp-emoji') !== false)
                || (bool) preg_match('#wp-includes/js/#', $html)
                || (strpos($html, '</body>') !== false && preg_match('#<script#i', substr($html, -8000)));

            if (! $has_header) {
                $findings['headerfoot'][] = 'No <header> / header landmark detected';
            }
            if (! $has_footer) {
                $findings['headerfoot'][] = 'No <footer> / footer landmark detected';
            }
            // wp_footer absence is a strong broken-theme signal.
            if (strpos($html, '</body>') !== false && ! $has_wp_footer && ! preg_match('#wp-content/themes/#', $html)) {
                $findings['headerfoot'][] = 'Homepage may be missing wp_footer output';
            }
            if (! $has_header && ! $has_footer) {
                $findings['layout'][] = 'Missing both header and footer landmarks';
            }

            // Responsive — viewport meta (partial).
            if (! preg_match('#<meta[^>]+name=["\']viewport["\']#i', $html)) {
                $findings['responsive'][] = 'Missing viewport meta tag';
            }
        }

        // Child theme structure.
        if ($is_child) {
            $parent = wp_get_theme($template);
            if (! $parent->exists()) {
                $findings['child'][] = 'Child theme parent "' . $template . '" does not exist';
            }
            $style = get_stylesheet_directory() . '/style.css';
            if (is_readable($style)) {
                $headers = get_file_data($style, [
                    'Template' => 'Template',
                    'Name'     => 'Theme Name',
                ]);
                $declared = trim((string) ($headers['Template'] ?? ''));
                if ($declared === '') {
                    $findings['child'][] = 'Child style.css missing Template: header';
                } elseif ($declared !== $template && $declared !== $parent->get_stylesheet()) {
                    // Template header is usually the parent directory slug.
                    if ($declared !== $template) {
                        $findings['child'][] = 'style.css Template "' . $declared . '" does not match active parent "' . $template . '"';
                    }
                }
            } else {
                $findings['child'][] = 'Child theme style.css missing/unreadable';
            }
            // Child should not replace functions.php incorrectly — if child has no style enqueue of parent, soft.
            if (! function_exists('wp_get_theme')) {
                // no-op
            }
        }

        // Template overrides outdated — child copies of parent PHP with older filemtime, or WC notices.
        if ($is_child) {
            $child_dir  = trailingslashit(get_stylesheet_directory());
            $parent_dir = trailingslashit(get_template_directory());
            $overrides  = $this->find_template_overrides($child_dir, $parent_dir, 8);
            foreach ($overrides as $rel => $info) {
                if (! empty($info['parent_newer'])) {
                    $findings['overrides'][] = $rel . ' — parent template is newer than child override';
                }
            }
            // WooCommerce template versions.
            if (class_exists('WooCommerce') && function_exists('wc_get_template')) {
                $wc_outdated = $this->woocommerce_outdated_templates(5);
                foreach ($wc_outdated as $msg) {
                    $findings['overrides'][] = $msg;
                }
            }
        }

        $coverage = $this->build_coverage($findings, [
            'is_child'   => $is_child,
            'theme_name' => $theme->get('Name') ?: $stylesheet,
        ]);

        $failed = array_filter($coverage, static function (array $r): bool {
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
            sprintf('Theme sample OK (%s%s).', $theme->get('Name') ?: $stylesheet, $is_child ? ', child' : ''),
            $start,
            $extra
        );
    }

    /**
     * @return array<int, array{line:string,mode:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'Broken layouts', 'mode' => 'partial'],
            ['line' => 'CSS missing', 'mode' => 'automated'],
            ['line' => 'JavaScript errors', 'mode' => 'partial'],
            ['line' => 'Child theme overridden incorrectly', 'mode' => 'automated'],
            ['line' => 'Template overrides outdated', 'mode' => 'partial'],
            ['line' => 'Responsive issues', 'mode' => 'partial'],
            ['line' => 'Header/footer missing', 'mode' => 'automated'],
        ];
    }

    /**
     * @param array<string, array<int, string>> $findings Findings.
     * @param array<string, mixed>              $meta     Context.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function build_coverage(array $findings, array $meta): array {
        $coverage = [];
        foreach (self::doc_lines() as $row) {
            $line = $row['line'];
            $mode = $row['mode'];

            switch ($line) {
                case 'Broken layouts':
                    $hits = $findings['layout'];
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'Homepage HTML not empty / reachable (visual layout still needs browser)'
                            : implode('; ', array_slice($hits, 0, 3)),
                    ];
                    break;

                case 'CSS missing':
                    $hits = $findings['css'];
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'Stylesheets in sample load (no 404/5xx)'
                            : implode('; ', array_slice($hits, 0, 3)),
                    ];
                    break;

                case 'JavaScript errors':
                    $hits = $findings['js'];
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'No broken theme script URLs / error text (runtime console still needs browser)'
                            : implode('; ', array_slice($hits, 0, 3)),
                    ];
                    break;

                case 'Child theme overridden incorrectly':
                    if (empty($meta['is_child'])) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'skip',
                            'detail' => 'Active theme is not a child theme',
                        ];
                        break;
                    }
                    $hits = $findings['child'];
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'Child Template header / parent exists'
                            : implode('; ', $hits),
                    ];
                    break;

                case 'Template overrides outdated':
                    if (empty($meta['is_child'])) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'skip',
                            'detail' => 'No child overrides to compare',
                        ];
                        break;
                    }
                    $hits = $findings['overrides'];
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'No outdated child/WooCommerce template overrides detected in sample'
                            : implode('; ', array_slice($hits, 0, 3)),
                    ];
                    break;

                case 'Responsive issues':
                    $hits = $findings['responsive'];
                    if (! empty($hits)) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'fail',
                            'detail' => implode('; ', $hits),
                        ];
                    } else {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'manual',
                            'detail' => 'Viewport meta OK — real breakpoints need browser / BrowserStack',
                        ];
                    }
                    break;

                case 'Header/footer missing':
                    $hits = $findings['headerfoot'];
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'Header/footer landmarks detected'
                            : implode('; ', $hits),
                    ];
                    break;

                default:
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'manual', 'detail' => 'Unhandled',
                    ];
                    break;
            }
        }

        return $coverage;
    }

    /**
     * Child PHP templates that also exist in parent.
     *
     * @param string $child_dir  Child path.
     * @param string $parent_dir Parent path.
     * @param int    $limit      Max files.
     * @return array<string, array{parent_newer:bool}>
     */
    private function find_template_overrides(string $child_dir, string $parent_dir, int $limit): array {
        $out   = [];
        $files = ['header.php', 'footer.php', 'index.php', 'single.php', 'page.php', 'functions.php', 'sidebar.php'];
        foreach ($files as $rel) {
            $c = $child_dir . $rel;
            $p = $parent_dir . $rel;
            if (! is_readable($c) || ! is_readable($p)) {
                continue;
            }
            if ($rel === 'functions.php') {
                // functions.php in child is expected — skip outdated compare.
                continue;
            }
            $out[ $rel ] = [
                'parent_newer' => filemtime($p) > filemtime($c) + DAY_IN_SECONDS,
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param int $limit Max messages.
     * @return array<int, string>
     */
    private function woocommerce_outdated_templates(int $limit): array {
        $msgs = [];
        // WC stores overrides status when Status page runs; scan common paths.
        $child = trailingslashit(get_stylesheet_directory()) . 'woocommerce/';
        if (! is_dir($child)) {
            return [];
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($child, \FilesystemIterator::SKIP_DOTS)
        );
        $n = 0;
        foreach ($iterator as $file) {
            if ($n >= 15) {
                break;
            }
            if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $n++;
            $contents = @file_get_contents($file->getPathname(), false, null, 0, 4000);
            if ($contents === false) {
                continue;
            }
            if (! preg_match('/@version\s+([\d.]+)/', $contents, $vm)) {
                continue;
            }
            $override_ver = $vm[1];
            // Find corresponding core template version — best effort via WC path.
            $rel = ltrim(str_replace($child, '', $file->getPathname()), '/\\');
            $core = WP_PLUGIN_DIR . '/woocommerce/templates/' . $rel;
            if (! is_readable($core)) {
                continue;
            }
            $core_contents = @file_get_contents($core, false, null, 0, 4000);
            if ($core_contents === false || ! preg_match('/@version\s+([\d.]+)/', $core_contents, $cm)) {
                continue;
            }
            if (version_compare($override_ver, $cm[1], '<')) {
                $msgs[] = 'WooCommerce override ' . $rel . ' @version ' . $override_ver . ' < core ' . $cm[1];
            }
            if (count($msgs) >= $limit) {
                break;
            }
        }
        return $msgs;
    }

    private function abs_url(string $src): ?string {
        $src = html_entity_decode($src, ENT_QUOTES);
        if (strpos($src, '//') === 0) {
            $src = (is_ssl() ? 'https:' : 'http:') . $src;
        } elseif (strpos($src, '/') === 0) {
            $src = home_url($src);
        }
        return preg_match('#^https?://#i', $src) ? $src : null;
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
