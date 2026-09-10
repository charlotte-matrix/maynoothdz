<?php
/**
 * Browser / device coverage from docs/Browser.md.
 *
 * PHP cannot render Safari/Firefox/iOS — these lines are AI / BrowserStack / manual.
 * Wire results via msm_browser_ai_coverage (or stored msm_browser_ai_last option).
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Browser extends Check_Base {
    public function id(): string { return 'browser'; }
    public function label(): string { return 'Browser / device (AI)'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);
        $url   = home_url('/');

        $preflight = [
            'mobile'  => [],
            'tablet'  => [],
            'safari'  => [],
            'firefox' => [],
            'chrome'  => [],
            'ios'     => [],
            'android' => [],
        ];

        // Lightweight HTML signals only (not a substitute for real browsers).
        $res = wp_remote_get($url, [
            'timeout'     => 20,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);

        $html = '';
        if (! is_wp_error($res) && (int) wp_remote_retrieve_response_code($res) < 400) {
            $html = (string) wp_remote_retrieve_body($res);
        }

        if ($html !== '') {
            if (! preg_match('#<meta[^>]+name=["\']viewport["\']#i', $html)) {
                $preflight['mobile'][] = 'Missing viewport meta (mobile layout risk)';
                $preflight['tablet'][] = 'Missing viewport meta (tablet layout risk)';
            }
            // Hover-only nav without touch fallback is a common iOS/Android menu failure signal.
            if (preg_match('#:hover\s*\{[^}]*display\s*:\s*block#i', $html)
                && ! preg_match('#(?:touchstart|pointerdown|aria-expanded)#i', $html)
            ) {
                // CSS in HTML is rare; skip — would need stylesheet fetch.
            }
            if (preg_match('#navigator\.userAgent[^;]+Chrome#i', $html)
                && ! preg_match('#Firefox|Safari#i', $html)
            ) {
                $preflight['chrome'][] = 'Script appears to key off Chrome userAgent only (review)';
            }
        } else {
            $preflight['mobile'][] = 'Could not fetch homepage for preflight';
        }

        /**
         * AI / BrowserStack / external review results.
         *
         * Return an array of rows keyed by doc line, or a list of:
         * [ 'line' => string, 'status' => 'pass'|'fail'|'ai'|'manual'|'skip', 'detail' => string ]
         *
         * Example (site-profile or mu-plugin):
         *
         * add_filter('msm_browser_ai_coverage', function ($rows, $url) {
         *     // Call Gemini/Claude/BrowserStack, then return coverage rows.
         *     return [
         *         ['line' => 'Mobile layout broken', 'status' => 'pass', 'detail' => 'AI: OK at 390px'],
         *         ['line' => 'Safari-specific issues', 'status' => 'fail', 'detail' => 'Sticky header overlaps'],
         *     ];
         * }, 10, 2);
         *
         * @param array<int, array{line?:string,status?:string,detail?:string}> $rows Existing.
         * @param string                                                         $url  Homepage URL.
         */
        $ai_rows = apply_filters('msm_browser_ai_coverage', [], $url);
        if (! is_array($ai_rows) || empty($ai_rows)) {
            $stored = get_option('msm_browser_ai_last', []);
            if (is_array($stored) && ! empty($stored['coverage']) && is_array($stored['coverage'])) {
                $ai_rows = $stored['coverage'];
            }
        }

        $ai_by_line = [];
        foreach ($ai_rows as $row) {
            if (! is_array($row) || empty($row['line'])) {
                continue;
            }
            $ai_by_line[ (string) $row['line'] ] = $row;
        }

        $coverage = [];
        foreach (self::doc_lines() as $doc) {
            $line = $doc['line'];
            $mode = $doc['mode'];
            $key  = $doc['key'];

            if (isset($ai_by_line[ $line ])) {
                $row = $ai_by_line[ $line ];
                $status = (string) ($row['status'] ?? 'ai');
                if (! in_array($status, ['pass', 'fail', 'skip', 'manual', 'ai'], true)) {
                    $status = 'ai';
                }
                $coverage[] = [
                    'line'   => $line,
                    'mode'   => 'ai',
                    'status' => $status,
                    'detail' => (string) ($row['detail'] ?? 'From msm_browser_ai_coverage / msm_browser_ai_last'),
                ];
                continue;
            }

            $hits = $preflight[ $key ] ?? [];
            if (! empty($hits)) {
                $coverage[] = [
                    'line'   => $line,
                    'mode'   => 'partial',
                    'status' => 'fail',
                    'detail' => implode('; ', $hits) . ' — confirm with AI/BrowserStack',
                ];
                continue;
            }

            $coverage[] = [
                'line'   => $line,
                'mode'   => $mode,
                'status' => 'ai',
                'detail' => self::ai_hint($line),
            ];
        }

        $failed = array_filter($coverage, static function (array $r): bool {
            return ($r['status'] ?? '') === 'fail';
        });

        $ai_pending = array_filter($coverage, static function (array $r): bool {
            return ($r['status'] ?? '') === 'ai';
        });

        $extra = [
            'coverage' => $coverage,
            'ai'       => [
                'provided' => ! empty($ai_by_line),
                'pending'  => count($ai_pending),
                'filter'   => 'msm_browser_ai_coverage',
                'option'   => 'msm_browser_ai_last',
            ],
        ];

        if ($failed) {
            $msgs = [];
            foreach (array_slice(array_values($failed), 0, 6) as $row) {
                $msgs[] = $row['line'] . (! empty($row['detail']) ? ': ' . $row['detail'] : '');
            }
            return $this->fail(implode('; ', $msgs) . '.', $start, $extra);
        }

        if (! empty($ai_by_line)) {
            return $this->pass(
                sprintf('Browser review loaded (%d AI row(s)).', count($ai_by_line)),
                $start,
                $extra
            );
        }

        // No AI yet — skip overall so digests aren't noisy; lines still show as AI pending.
        return $this->skip(
            sprintf(
                'Browser visuals need AI/BrowserStack (%d lines pending). Hook msm_browser_ai_coverage or save msm_browser_ai_last.',
                count($ai_pending)
            ),
            $start,
            $extra
        );
    }

    /**
     * @return array<int, array{line:string,mode:string,key:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'Mobile layout broken', 'mode' => 'ai', 'key' => 'mobile'],
            ['line' => 'Tablet layout broken', 'mode' => 'ai', 'key' => 'tablet'],
            ['line' => 'Safari-specific issues', 'mode' => 'ai', 'key' => 'safari'],
            ['line' => 'Firefox rendering issues', 'mode' => 'ai', 'key' => 'firefox'],
            ['line' => 'Chrome-only functionality', 'mode' => 'ai', 'key' => 'chrome'],
            ['line' => 'iOS menu problems', 'mode' => 'ai', 'key' => 'ios'],
            ['line' => 'Android touch issues', 'mode' => 'ai', 'key' => 'android'],
        ];
    }

    private static function ai_hint(string $line): string {
        $hints = [
            'Mobile layout broken'       => 'Capture ~390px screenshot → vision model / BrowserStack',
            'Tablet layout broken'       => 'Capture ~768px screenshot → vision model / BrowserStack',
            'Safari-specific issues'     => 'Safari (macOS/iOS) session or BrowserStack Safari',
            'Firefox rendering issues'   => 'Firefox session or BrowserStack Firefox',
            'Chrome-only functionality'  => 'Compare Chrome vs Safari/Firefox behaviour',
            'iOS menu problems'          => 'iPhone Safari — open/close nav, sticky header',
            'Android touch issues'       => 'Android Chrome — tap targets, scroll, menu',
        ];
        return $hints[ $line ] ?? 'Needs AI vision or real-device browser test';
    }
}
