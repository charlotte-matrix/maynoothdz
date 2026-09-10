<?php
/**
 * Front-end JavaScript heuristics from docs/JavaScript.md.
 *
 * No headless browser — script 404s, jQuery duplication, AJAX endpoint,
 * and markup signals only. Interactive failures stay manual.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Javascript extends Check_Base {
    public function id(): string { return 'javascript'; }
    public function label(): string { return 'JavaScript (front-end sample)'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);
        $url   = home_url('/');

        $res = wp_remote_get($url, [
            'timeout'     => 20,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);

        if (is_wp_error($res)) {
            return $this->fail('Could not fetch homepage: ' . $res->get_error_message(), $start, [
                'coverage' => $this->static_coverage('fail', $res->get_error_message()),
            ]);
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code < 200 || $code >= 400) {
            return $this->fail('Homepage returned HTTP ' . $code . '.', $start, [
                'coverage' => $this->static_coverage('fail', 'HTTP ' . $code),
            ]);
        }

        $html = (string) wp_remote_retrieve_body($res);
        if ($html === '') {
            return $this->fail('Homepage returned empty HTML.', $start, [
                'coverage' => $this->static_coverage('fail', 'Empty HTML'),
            ]);
        }

        $findings = [
            'console'      => [],
            'jquery'       => [],
            'ajax'         => [],
            'spinners'     => [],
            'menus'        => [],
            'sliders'      => [],
            'modals'       => [],
            'autocomplete' => [],
        ];

        // Broken script URLs ≈ console errors (404 / 5xx).
        $scripts = $this->extract_script_srcs($html);
        foreach (array_slice($scripts, 0, 12) as $src) {
            $scode = $this->probe_code($src);
            if ($scode === 404 || ($scode !== null && $scode >= 500)) {
                $findings['console'][] = $src . ' (HTTP ' . $scode . ')';
            }
        }

        // Inline error dumps sometimes appear in HTML.
        if (preg_match('/Uncaught\s+(?:TypeError|ReferenceError|SyntaxError)/i', $html)) {
            $findings['console'][] = 'Uncaught JS error text found in homepage HTML';
        }

        // jQuery conflicts — multiple jQuery core loads.
        $jquery_hits = 0;
        foreach ($scripts as $src) {
            if (preg_match('#/(?:jquery(?:\.min)?(?:-\d[\d.]*)?|jquery\.js)#i', $src)
                && ! preg_match('#jquery[-.]migrate|jquery-ui#i', $src)
            ) {
                $jquery_hits++;
            }
        }
        // Also count script tags that load jquery from WP includes path twice.
        if (preg_match_all('#<script[^>]+src=["\'][^"\']*jquery[^"\']*["\']#i', $html, $jm)) {
            $core = 0;
            foreach ($jm[0] as $tag) {
                if (preg_match('#jquery[-.]migrate|jquery-ui|jquery\.ui#i', $tag)) {
                    continue;
                }
                if (preg_match('#jquery#i', $tag)) {
                    $core++;
                }
            }
            if ($core > $jquery_hits) {
                $jquery_hits = $core;
            }
        }
        if ($jquery_hits > 1) {
            $findings['jquery'][] = $jquery_hits . ' jQuery core script tags detected (conflict risk)';
        }
        // jQuery after other scripts that need it is hard to detect; check for $ before jquery load is manual.

        // AJAX — admin-ajax.php should respond (400/0 for unknown action is OK; 500 is not).
        $ajax_url = admin_url('admin-ajax.php');
        $ajax     = wp_remote_post($ajax_url, [
            'timeout'   => 10,
            'sslverify' => false,
            'body'      => [
                'action' => 'msm_heartbeat_probe_' . wp_generate_password(4, false),
            ],
            'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        if (is_wp_error($ajax)) {
            $findings['ajax'][] = 'admin-ajax.php request failed: ' . $ajax->get_error_message();
        } else {
            $acode = (int) wp_remote_retrieve_response_code($ajax);
            $abody = (string) wp_remote_retrieve_body($ajax);
            if ($acode >= 500) {
                $findings['ajax'][] = 'admin-ajax.php returned HTTP ' . $acode;
            }
            if (preg_match('/Fatal error|critical error/i', $abody)) {
                $findings['ajax'][] = 'admin-ajax.php returned fatal/critical error output';
            }
        }
        // REST API root.
        $rest = wp_remote_get(rest_url(), [
            'timeout'   => 10,
            'sslverify' => false,
            'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        if (! is_wp_error($rest)) {
            $rcode = (int) wp_remote_retrieve_response_code($rest);
            if ($rcode >= 500) {
                $findings['ajax'][] = 'REST API root returned HTTP ' . $rcode;
            }
        }

        // Infinite loading spinners — markup heuristic only (many spinners = possible stuck UI).
        $spinner_count = 0;
        if (preg_match_all('#class=["\'][^"\']*\b(?:spinner|loading|is-loading|animate-spin|loader)\b#i', $html, $sm)) {
            $spinner_count = count($sm[0]);
        }
        // Don't fail on spinner classes alone — themes use them. Manual unless absurd.
        if ($spinner_count > 15) {
            $findings['spinners'][] = $spinner_count . ' loading/spinner class markers on homepage (review manually)';
        }

        // Broken menus — primary nav empty / no links.
        $has_nav = (bool) preg_match('#<(?:nav|div)[^>]*(?:role=["\']navigation["\']|class=["\'][^"\']*menu)#i', $html);
        $menu_links = 0;
        if (preg_match_all('#<(?:nav|ul)[^>]*class=["\'][^"\']*(?:menu|nav)[^"\']*["\'][^>]*>(.*?)</(?:nav|ul)>#is', $html, $nm)) {
            foreach ($nm[1] as $block) {
                $menu_links += preg_match_all('#<a\s[^>]*href=#i', $block);
            }
        }
        $locations = get_nav_menu_locations();
        $assigned  = is_array($locations) ? array_filter($locations) : [];
        if (empty($assigned)) {
            $findings['menus'][] = 'No menu assigned to a theme location';
        } elseif ($has_nav && $menu_links === 0) {
            $findings['menus'][] = 'Navigation markup found but no menu links in sample';
        }

        // Broken sliders — library present but script 404 already in console; or empty slider track.
        $slider_libs = [];
        foreach ($scripts as $src) {
            if (preg_match('#(slick|swiper|owl\.carousel|flexslider|splide|glide)#i', $src)) {
                $slider_libs[] = $src;
            }
        }
        if ($slider_libs && preg_match('#class=["\'][^"\']*(?:slick|swiper|owl-carousel|flexslider|splide)#i', $html)) {
            // Markup + lib OK unless scripts already failed.
            foreach ($slider_libs as $src) {
                foreach ($findings['console'] as $c) {
                    if (strpos($c, $src) !== false) {
                        $findings['sliders'][] = 'Slider library failed to load: ' . $src;
                    }
                }
            }
        } elseif (preg_match('#class=["\'][^"\']*(?:slick|swiper|owl-carousel|flexslider)#i', $html)
            && empty($slider_libs)
        ) {
            $findings['sliders'][] = 'Slider markup present but no slider library script detected';
        }

        // Modals — markup without bootstrap/jquery/micromodal scripts is weak; keep manual unless script 404.
        if (preg_match('#(?:data-toggle=["\']modal["\']|data-bs-toggle=["\']modal["\']|aria-modal)#i', $html)) {
            foreach ($findings['console'] as $c) {
                if (preg_match('#(bootstrap|modal|micromodal|fancybox|lity)#i', $c)) {
                    $findings['modals'][] = 'Modal-related script failed: ' . $c;
                }
            }
        }

        // Search autocomplete — search form + REST search or Suggest.
        $has_search = (bool) preg_match('#<form[^>]*(?:role=["\']search["\']|class=["\'][^"\']*search)#i', $html)
            || (bool) preg_match('#name=["\']s["\']#i', $html);
        if ($has_search) {
            $search_rest = wp_remote_get(rest_url('wp/v2/search?search=a&per_page=1'), [
                'timeout'   => 10,
                'sslverify' => false,
                'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
            ]);
            if (is_wp_error($search_rest)) {
                $findings['autocomplete'][] = 'REST search failed: ' . $search_rest->get_error_message();
            } else {
                $scode = (int) wp_remote_retrieve_response_code($search_rest);
                if ($scode >= 500) {
                    $findings['autocomplete'][] = 'REST /wp/v2/search returned HTTP ' . $scode;
                } elseif ($scode === 404) {
                    $findings['autocomplete'][] = 'REST search endpoint not available (HTTP 404)';
                }
            }
        }

        $coverage = $this->build_coverage($findings, [
            'has_search'   => $has_search,
            'slider_libs'  => ! empty($slider_libs),
            'spinner_n'    => $spinner_count,
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
            sprintf('JS sample OK (%d script URLs probed). Interactive items need browser review.', count($scripts)),
            $start,
            $extra
        );
    }

    /**
     * @return array<int, array{line:string,mode:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'Console errors', 'mode' => 'partial'],
            ['line' => 'jQuery conflicts', 'mode' => 'partial'],
            ['line' => 'AJAX failures', 'mode' => 'automated'],
            ['line' => 'Infinite loading spinners', 'mode' => 'manual'],
            ['line' => 'Broken menus', 'mode' => 'partial'],
            ['line' => 'Broken sliders', 'mode' => 'partial'],
            ['line' => 'Modals not opening', 'mode' => 'manual'],
            ['line' => 'Search autocomplete broken', 'mode' => 'partial'],
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
                case 'Console errors':
                    $hits = $findings['console'];
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'No broken script URLs / uncaught error text in sample (runtime console still needs browser)'
                            : implode('; ', array_slice($hits, 0, 3)),
                    ];
                    break;

                case 'jQuery conflicts':
                    $hits = $findings['jquery'];
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'No duplicate jQuery core scripts detected'
                            : implode('; ', $hits),
                    ];
                    break;

                case 'AJAX failures':
                    $hits = $findings['ajax'];
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'admin-ajax.php / REST root responded without 5xx'
                            : implode('; ', $hits),
                    ];
                    break;

                case 'Infinite loading spinners':
                    $hits = $findings['spinners'];
                    if (! empty($hits)) {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => 'skip',
                            'detail' => implode('; ', $hits),
                        ];
                    } else {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => 'manual',
                            'detail' => 'Needs browser — spinner CSS classes alone are not a failure (' . (int) ($meta['spinner_n'] ?? 0) . ' found)',
                        ];
                    }
                    break;

                case 'Broken menus':
                    $hits = $findings['menus'];
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'Menu location assigned / nav links present in sample'
                            : implode('; ', $hits),
                    ];
                    break;

                case 'Broken sliders':
                    $hits = $findings['sliders'];
                    if (empty($hits) && empty($meta['slider_libs'])) {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => 'skip',
                            'detail' => 'No common slider library detected on homepage',
                        ];
                    } else {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => empty($hits) ? 'pass' : 'fail',
                            'detail' => empty($hits)
                                ? 'Slider assets present and scripts load (behaviour still needs browser)'
                                : implode('; ', $hits),
                        ];
                    }
                    break;

                case 'Modals not opening':
                    $hits = $findings['modals'];
                    if (! empty($hits)) {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => 'fail',
                            'detail' => implode('; ', $hits),
                        ];
                    } else {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => 'manual',
                            'detail' => 'Needs browser click test (markup/script 404s checked when present)',
                        ];
                    }
                    break;

                case 'Search autocomplete broken':
                    $hits = $findings['autocomplete'];
                    if (empty($meta['has_search'])) {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => 'skip',
                            'detail' => 'No search form detected on homepage',
                        ];
                    } elseif (! empty($hits)) {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => 'fail',
                            'detail' => implode('; ', $hits),
                        ];
                    } else {
                        $coverage[] = [
                            'line'   => $line,
                            'mode'   => $mode,
                            'status' => 'pass',
                            'detail' => 'Search form + REST search endpoint OK (UI autocomplete still needs browser)',
                        ];
                    }
                    break;

                default:
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => 'manual',
                        'detail' => 'Unhandled',
                    ];
                    break;
            }
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
                'status' => ($row['mode'] === 'manual') ? 'manual' : $status,
                'detail' => $detail,
            ];
        }
        return $rows;
    }

    /**
     * @param string $html HTML.
     * @return array<int, string>
     */
    private function extract_script_srcs(string $html): array {
        $out = [];
        if (! preg_match_all('#<script[^>]+src=["\']([^"\']+)["\']#i', $html, $m)) {
            return [];
        }
        foreach ($m[1] as $src) {
            $src = html_entity_decode($src, ENT_QUOTES);
            if (strpos($src, '//') === 0) {
                $src = (is_ssl() ? 'https:' : 'http:') . $src;
            } elseif (strpos($src, '/') === 0) {
                $src = home_url($src);
            }
            if (preg_match('#^https?://#i', $src)) {
                $out[] = $src;
            }
        }
        return array_values(array_unique($out));
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
