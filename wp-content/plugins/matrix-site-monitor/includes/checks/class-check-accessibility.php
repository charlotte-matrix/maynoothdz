<?php
/**
 * Accessibility HTML sampler — homepage checks from docs/Accessibility.md.
 *
 * Opt-in: enable in Site Monitor settings, then run alone to verify.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Accessibility extends Check_Base {
    public function id(): string { return 'accessibility'; }
    public function label(): string { return 'Accessibility (HTML sample)'; }
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
                'coverage' => $this->manual_coverage('Could not fetch page'),
            ]);
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code < 200 || $code >= 400) {
            return $this->fail('Homepage returned HTTP ' . $code . '.', $start, [
                'coverage' => $this->manual_coverage('HTTP ' . $code),
            ]);
        }

        $html = (string) wp_remote_retrieve_body($res);
        if ($html === '') {
            return $this->fail('Homepage returned empty HTML.', $start, [
                'coverage' => $this->manual_coverage('Empty HTML'),
            ]);
        }

        $issues   = $this->analyze($html);
        $issues   = apply_filters('msm_accessibility_issues', $issues, $html, $url);
        $coverage = $this->build_coverage($issues);

        $failed = array_filter($coverage, static function (array $row): bool {
            return ($row['status'] ?? '') === 'fail';
        });

        $extra = ['coverage' => $coverage];

        if ($failed) {
            $msgs = array_map(static function (array $row): string {
                return $row['line'] . (! empty($row['detail']) ? ' — ' . $row['detail'] : '');
            }, array_slice(array_values($failed), 0, 8));
            $suffix = count($failed) > 8 ? ' (+' . (count($failed) - 8) . ' more)' : '';
            return $this->fail(implode('; ', $msgs) . $suffix . '.', $start, $extra);
        }

        $auto = count(array_filter($coverage, static function (array $r): bool {
            return ($r['mode'] ?? '') === 'automated' || ($r['mode'] ?? '') === 'partial';
        }));
        $manual = count($coverage) - $auto;

        return $this->pass(
            sprintf('Accessibility: %d automated/partial checks passed; %d lines need manual/browser review.', $auto, $manual),
            $start,
            $extra
        );
    }

    /**
     * Every Accessibility.md issue line with status.
     *
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'Missing alt text', 'mode' => 'automated'],
            ['line' => 'Duplicate alt text', 'mode' => 'automated'],
            ['line' => 'Decorative images announced unnecessarily', 'mode' => 'partial'],
            ['line' => 'Skipped heading levels', 'mode' => 'automated'],
            ['line' => 'Multiple H1s', 'mode' => 'automated'],
            ['line' => 'Missing H1', 'mode' => 'automated'],
            ['line' => 'Empty headings', 'mode' => 'automated'],
            ['line' => 'Missing labels', 'mode' => 'automated'],
            ['line' => 'Missing error messages', 'mode' => 'manual'],
            ['line' => 'Placeholder used instead of labels', 'mode' => 'automated'],
            ['line' => 'Required fields not announced', 'mode' => 'manual'],
            ['line' => 'Keyboard trap', 'mode' => 'manual'],
            ['line' => 'Low contrast', 'mode' => 'manual'],
            ['line' => 'Colour-only indicators', 'mode' => 'manual'],
            ['line' => 'Focus outline removed', 'mode' => 'partial'],
            ['line' => 'Cannot navigate via keyboard', 'mode' => 'manual'],
            ['line' => 'Incorrect tab order', 'mode' => 'manual'],
            ['line' => 'Hidden focus', 'mode' => 'manual'],
            ['line' => 'Invalid ARIA', 'mode' => 'manual'],
            ['line' => 'Missing ARIA labels', 'mode' => 'partial'],
            ['line' => 'Duplicate IDs', 'mode' => 'automated'],
            ['line' => 'Broken landmarks', 'mode' => 'manual'],
            ['line' => 'Missing language attribute', 'mode' => 'automated'],
            ['line' => 'Missing page title', 'mode' => 'automated'],
            ['line' => 'Empty links', 'mode' => 'automated'],
            ['line' => 'Empty buttons', 'mode' => 'automated'],
            ['line' => 'Broken skip links', 'mode' => 'automated'],
            ['line' => 'Tables without headers', 'mode' => 'automated'],
            ['line' => 'Videos without captions', 'mode' => 'automated'],
        ];
    }

    /**
     * Map analyzer findings onto every Accessibility.md line.
     *
     * @param array<int, string> $issues Analyzer messages.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function build_coverage(array $issues): array {
        $blob = implode(' | ', $issues);
        $coverage = [];

        foreach (self::doc_lines() as $row) {
            $line = $row['line'];
            $mode = $row['mode'];
            $detail = '';
            $status = 'pass';

            if ($mode === 'manual') {
                $status = 'manual';
                $detail = 'Not automated — needs browser / AI review';
            } else {
                $match = $this->match_issue($line, $issues, $blob);
                if ($match !== null) {
                    $status = 'fail';
                    $detail = $match;
                } elseif ($mode === 'partial') {
                    $status = 'pass';
                    $detail = 'Partial heuristic only';
                }
            }

            $coverage[] = [
                'line'   => $line,
                'mode'   => $mode,
                'status' => $status,
                'detail' => $detail,
            ];
        }

        return $coverage;
    }

    /**
     * @param string             $line   Doc line.
     * @param array<int, string> $issues Issues.
     * @param string             $blob   Joined issues.
     */
    private function match_issue(string $line, array $issues, string $blob): ?string {
        $patterns = [
            'Missing alt text'                         => 'missing alt',
            'Duplicate alt text'                       => 'Duplicate alt',
            'Decorative images announced unnecessarily'=> null, // partial — no hard fail unless we add later
            'Skipped heading levels'                   => 'Skipped heading',
            'Multiple H1s'                             => 'Multiple H1',
            'Missing H1'                               => 'Missing H1',
            'Empty headings'                           => 'Empty heading',
            'Missing labels'                           => 'missing label',
            'Placeholder used instead of labels'       => 'placeholder instead of label',
            'Focus outline removed'                    => 'focus outlines',
            'Missing ARIA labels'                      => 'missing label', // overlaps form labels
            'Duplicate IDs'                            => 'Duplicate ID',
            'Missing language attribute'               => 'language attribute',
            'Missing page title'                       => 'page title',
            'Empty links'                              => 'Empty link',
            'Empty buttons'                            => 'Empty button',
            'Broken skip links'                        => 'Broken skip link',
            'Tables without headers'                   => 'Table without headers',
            'Videos without captions'                  => 'Video without captions',
        ];

        $needle = $patterns[$line] ?? null;
        if ($needle === null) {
            return null;
        }

        foreach ($issues as $issue) {
            if (stripos($issue, $needle) !== false) {
                return $issue;
            }
        }

        return null;
    }

    /**
     * Coverage rows when the page cannot be analyzed.
     *
     * @param string $reason Reason.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function manual_coverage(string $reason): array {
        $rows = [];
        foreach (self::doc_lines() as $row) {
            $rows[] = [
                'line'   => $row['line'],
                'mode'   => $row['mode'],
                'status' => $row['mode'] === 'manual' ? 'manual' : 'fail',
                'detail' => $reason,
            ];
        }
        return $rows;
    }

    /**
     * Analyze HTML for Accessibility.md issues that are safely automatable.
     *
     * @param string $html Page HTML.
     * @return array<int, string>
     */
    private function analyze(string $html): array {
        $issues = [];
        $prev   = libxml_use_internal_errors(true);
        $dom    = new \DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (! $loaded) {
            return ['Could not parse HTML for accessibility checks'];
        }

        $xpath = new \DOMXPath($dom);

        // Missing language attribute
        $html_el = $dom->getElementsByTagName('html')->item(0);
        if ($html_el instanceof \DOMElement) {
            $lang = trim($html_el->getAttribute('lang') . ' ' . $html_el->getAttribute('xml:lang'));
            if ($lang === '') {
                $issues[] = 'Missing language attribute on <html>';
            }
        }

        // Missing page title
        $title = $dom->getElementsByTagName('title')->item(0);
        if (! $title || trim($title->textContent) === '') {
            $issues[] = 'Missing page title';
        }

        // Images: missing alt / duplicate alt
        $alts = [];
        foreach ($xpath->query('//img') as $img) {
            if (! $img instanceof \DOMElement) {
                continue;
            }
            if ($img->hasAttribute('role') && strtolower($img->getAttribute('role')) === 'presentation') {
                continue;
            }
            if (! $img->hasAttribute('alt')) {
                $src = $img->getAttribute('src') ?: '(no src)';
                $issues[] = 'Image missing alt: ' . $this->short($src);
                continue;
            }
            $alt = trim($img->getAttribute('alt'));
            if ($alt !== '') {
                $alts[$alt] = ($alts[$alt] ?? 0) + 1;
            }
        }
        foreach ($alts as $alt => $count) {
            if ($count >= 3 && strlen($alt) > 2) {
                $issues[] = 'Duplicate alt text ×' . $count . ': "' . $this->short($alt, 40) . '"';
            }
        }

        // Headings: missing H1, multiple H1s, empty, skipped levels
        $h1s = $xpath->query('//h1');
        if ($h1s && $h1s->length === 0) {
            $issues[] = 'Missing H1';
        } elseif ($h1s && $h1s->length > 1) {
            $issues[] = 'Multiple H1s (' . $h1s->length . ')';
        }

        $levels = [];
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
            foreach ($xpath->query('//' . $tag) as $heading) {
                if (! $heading instanceof \DOMElement) {
                    continue;
                }
                $text = trim(preg_replace('/\s+/', ' ', $heading->textContent) ?? '');
                if ($text === '') {
                    $issues[] = 'Empty heading <' . $tag . '>';
                }
                $levels[] = (int) substr($tag, 1);
            }
        }
        $prev_level = 0;
        foreach ($levels as $level) {
            if ($prev_level > 0 && $level > $prev_level + 1) {
                $issues[] = 'Skipped heading level (H' . $prev_level . ' → H' . $level . ')';
                break;
            }
            $prev_level = $level;
        }

        // Forms: inputs missing labels
        foreach ($xpath->query('//input|//select|//textarea') as $field) {
            if (! $field instanceof \DOMElement) {
                continue;
            }
            $type = strtolower($field->getAttribute('type') ?: 'text');
            if (in_array($type, ['hidden', 'submit', 'button', 'reset', 'image'], true)) {
                continue;
            }
            if ($this->has_accessible_name($field, $xpath)) {
                // Placeholder used instead of a real label
                if ($field->hasAttribute('placeholder') && ! $this->has_visible_label($field, $xpath)) {
                    $issues[] = 'Form field uses placeholder instead of label (' . $this->field_hint($field) . ')';
                }
                continue;
            }
            $issues[] = 'Form field missing label (' . $this->field_hint($field) . ')';
        }

        // Empty links / empty buttons
        foreach ($xpath->query('//a[@href]') as $a) {
            if (! $a instanceof \DOMElement) {
                continue;
            }
            if ($this->is_empty_control($a, $xpath)) {
                $issues[] = 'Empty link: ' . $this->short($a->getAttribute('href'));
            }
        }
        foreach ($xpath->query('//button') as $btn) {
            if (! $btn instanceof \DOMElement) {
                continue;
            }
            if ($this->is_empty_control($btn, $xpath)) {
                $issues[] = 'Empty button';
            }
        }

        // Duplicate IDs
        $ids = [];
        foreach ($xpath->query('//*[@id]') as $el) {
            if (! $el instanceof \DOMElement) {
                continue;
            }
            $id = $el->getAttribute('id');
            if ($id === '') {
                continue;
            }
            $ids[$id] = ($ids[$id] ?? 0) + 1;
        }
        foreach ($ids as $id => $count) {
            if ($count > 1) {
                $issues[] = 'Duplicate ID: #' . $this->short($id, 40) . ' (×' . $count . ')';
            }
        }

        // Broken skip links
        foreach ($xpath->query('//a[contains(translate(@href,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"#")]') as $a) {
            if (! $a instanceof \DOMElement) {
                continue;
            }
            $text = strtolower(trim($a->textContent));
            $href = $a->getAttribute('href');
            if ($text === '' || (strpos($text, 'skip') === false && strpos($text, 'content') === false)) {
                continue;
            }
            if (preg_match('/#([A-Za-z][\w\-:.]*)/', $href, $m)) {
                $target = $m[1];
                if (! isset($ids[$target])) {
                    $issues[] = 'Broken skip link: target #' . $target . ' not found';
                }
            }
        }

        // Tables without headers
        foreach ($xpath->query('//table') as $table) {
            if (! $table instanceof \DOMElement) {
                continue;
            }
            $ths = $xpath->query('.//th', $table);
            if (! $ths || $ths->length === 0) {
                $issues[] = 'Table without headers (<th>)';
            }
        }

        // Videos without captions/tracks
        foreach ($xpath->query('//video') as $video) {
            if (! $video instanceof \DOMElement) {
                continue;
            }
            $tracks = $xpath->query('.//track[@kind="captions" or @kind="subtitles"]', $video);
            if (! $tracks || $tracks->length === 0) {
                $issues[] = 'Video without captions track';
            }
        }

        // Focus outline removed (common anti-pattern in CSS)
        if (preg_match('/outline\s*:\s*none|outline\s*:\s*0\b/i', $html) && ! preg_match('/:focus[^{]*\{[^}]*outline\s*:\s*[^0n]/i', $html)) {
            $issues[] = 'CSS may remove focus outlines (outline:none/0)';
        }

        // Deduplicate while preserving order
        return array_values(array_unique($issues));
    }

    /**
     * Whether a control has an accessible name.
     *
     * @param \DOMElement $field Field element.
     * @param \DOMXPath   $xpath XPath.
     */
    private function has_accessible_name(\DOMElement $field, \DOMXPath $xpath): bool {
        if ($field->hasAttribute('aria-label') && trim($field->getAttribute('aria-label')) !== '') {
            return true;
        }
        if ($field->hasAttribute('aria-labelledby') && trim($field->getAttribute('aria-labelledby')) !== '') {
            return true;
        }
        if ($field->hasAttribute('title') && trim($field->getAttribute('title')) !== '') {
            return true;
        }
        return $this->has_visible_label($field, $xpath);
    }

    /**
     * Whether a field has a matching <label for="...">.
     *
     * @param \DOMElement $field Field element.
     * @param \DOMXPath   $xpath XPath.
     */
    private function has_visible_label(\DOMElement $field, \DOMXPath $xpath): bool {
        $id = $field->getAttribute('id');
        if ($id !== '') {
            $labels = $xpath->query('//label[@for="' . $this->xpath_escape($id) . '"]');
            if ($labels && $labels->length > 0) {
                return true;
            }
        }
        // Wrapped in label
        $parent = $field->parentNode;
        while ($parent instanceof \DOMElement) {
            if (strtolower($parent->tagName) === 'label') {
                return true;
            }
            $parent = $parent->parentNode;
        }
        return false;
    }

    /**
     * Whether a link/button has no accessible name.
     *
     * @param \DOMElement $el    Element.
     * @param \DOMXPath   $xpath XPath.
     */
    private function is_empty_control(\DOMElement $el, \DOMXPath $xpath): bool {
        if ($el->hasAttribute('aria-label') && trim($el->getAttribute('aria-label')) !== '') {
            return false;
        }
        if ($el->hasAttribute('aria-labelledby') && trim($el->getAttribute('aria-labelledby')) !== '') {
            return false;
        }
        if ($el->hasAttribute('title') && trim($el->getAttribute('title')) !== '') {
            return false;
        }
        $text = trim(preg_replace('/\s+/', ' ', $el->textContent) ?? '');
        if ($text !== '') {
            return false;
        }
        // Has meaningful image alt inside
        foreach ($xpath->query('.//img[@alt]', $el) as $img) {
            if ($img instanceof \DOMElement && trim($img->getAttribute('alt')) !== '') {
                return false;
            }
        }
        // SVG with title
        foreach ($xpath->query('.//svg//title', $el) as $title) {
            if (trim($title->textContent) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param \DOMElement $field Field.
     */
    private function field_hint(\DOMElement $field): string {
        foreach (['name', 'id', 'type', 'placeholder'] as $attr) {
            $val = $field->getAttribute($attr);
            if ($val !== '') {
                return $attr . '=' . $this->short($val, 30);
            }
        }
        return $field->tagName;
    }

    /**
     * @param string $value Value.
     * @param int    $max   Max length.
     */
    private function short(string $value, int $max = 60): string {
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        if (strlen($value) <= $max) {
            return $value;
        }
        return substr($value, 0, $max - 1) . '…';
    }

    /**
     * Escape a string for use in an XPath attribute equality check.
     *
     * @param string $value Raw value.
     */
    private function xpath_escape(string $value): string {
        if (strpos($value, '"') === false) {
            return $value;
        }
        return htmlspecialchars($value, ENT_QUOTES);
    }
}
