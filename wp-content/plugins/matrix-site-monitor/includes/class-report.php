<?php
/**
 * HTML triage report for download from WP admin.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

use Matrix_Site_Monitor\Admin\Admin_View;

defined('ABSPATH') || exit;

class Report {
    /**
     * Build a download URL (nonce included).
     */
    public static function download_url(): string {
        return wp_nonce_url(
            admin_url('admin-post.php?action=msm_download_report'),
            'msm_download_report'
        );
    }

    /**
     * Stream the HTML report as a file download and exit.
     */
    public static function stream_download(): void {
        $map = Storage::get_check_results();
        if (! is_array($map)) {
            $map = [];
        }

        $html     = self::build_html($map);
        $site     = sanitize_title(wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        if ($site === '') {
            $site = 'site';
        }
        $filename = sprintf('msm-report-%s-%s.html', $site, gmdate('Y-m-d-His'));

        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) strlen($html));

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped in build_html.
        echo $html;
        exit;
    }

    /**
     * Build HTML report from the rolling check-results map.
     *
     * @param array<string, array<string, mixed>> $map Results map.
     */
    public static function build_html(array $map): string {
        $fails = [];
        $warns = [];
        $passes = [];
        $skips = [];

        foreach ($map as $id => $row) {
            if (! is_array($row)) {
                continue;
            }
            $row['id'] = (string) ($row['id'] ?? $id);
            $st        = Admin_View::status_key($row);
            if ($st === 'fail') {
                $fails[] = $row;
            } elseif ($st === 'warn') {
                $warns[] = $row;
            } elseif ($st === 'skip') {
                $skips[] = $row;
            } elseif ($st === 'pass') {
                $passes[] = $row;
            } else {
                // Treat unknown / manual / ai as skips for the brief lists.
                $skips[] = $row;
            }
        }

        usort($fails, [self::class, 'sort_by_severity']);
        usort($warns, [self::class, 'sort_by_severity']);
        usort($passes, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });
        usort($skips, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        $site_name = esc_html(wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        $site_url  = esc_url(home_url('/'));
        $admin_url = esc_url(admin_url('tools.php?page=matrix-site-monitor&tab=failures'));
        $generated = esc_html(wp_date('Y-m-d H:i:s T'));

        $fail_n  = count($fails);
        $warn_n  = count($warns);
        $pass_n  = count($passes);
        $skip_n  = count($skips);
        $total_n = $fail_n + $warn_n + $pass_n + $skip_n;

        $fail_rows = self::render_detail_rows($fails, true);
        $warn_rows = self::render_detail_rows($warns, false);
        $pass_list = self::render_brief_list($passes);
        $skip_list = self::render_brief_list($skips);

        $empty_note = '';
        if ($total_n === 0) {
            $empty_note = '<p class="muted">No checks have been run yet. Run <code>wp msm run</code> (or a tier from Overview), then download again.</p>';
        }

        $warn_section = '';
        if ($warn_n > 0) {
            $warn_section = <<<HTML
<section>
  <h2>Warnings ({$warn_n})</h2>
  <table>
    <thead><tr><th>Check</th><th>Severity</th><th>Detail</th><th>When</th></tr></thead>
    <tbody>{$warn_rows}</tbody>
  </table>
</section>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Site Monitor Report — {$site_name}</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;color:#1e293b;line-height:1.45;max-width:960px;margin:24px auto;padding:0 16px;background:#fff;}
h1{font-size:1.5rem;margin:0 0 4px;}
h2{font-size:1.15rem;margin:28px 0 10px;border-bottom:1px solid #e2e8f0;padding-bottom:6px;}
.meta{color:#64748b;font-size:0.9rem;margin:0 0 16px;}
.meta a{color:#2563eb;}
.summary{display:flex;flex-wrap:wrap;gap:10px;margin:16px 0 8px;}
.pill{display:inline-block;padding:6px 12px;border-radius:6px;font-weight:600;font-size:0.9rem;background:#f1f5f9;}
.pill-fail{background:#fee2e2;color:#991b1b;}
.pill-warn{background:#fef3c7;color:#92400e;}
.pill-pass{background:#dcfce7;color:#166534;}
.pill-skip{background:#e2e8f0;color:#475569;}
table{width:100%;border-collapse:collapse;font-size:0.9rem;}
th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #e2e8f0;vertical-align:top;}
th{background:#f8fafc;font-size:0.8rem;text-transform:uppercase;letter-spacing:.03em;color:#64748b;}
.fail-row{background:#fff7f7;}
.msg{margin:0 0 4px;}
.cov{margin:6px 0 0;padding-left:18px;color:#7f1d1d;font-size:0.85rem;}
.brief{columns:2;column-gap:24px;margin:0;padding-left:18px;}
.brief li{break-inside:avoid;margin:0 0 4px;}
.muted{color:#64748b;}
.foot{margin-top:32px;font-size:0.8rem;color:#94a3b8;}
code{font-size:0.85em;background:#f1f5f9;padding:1px 4px;border-radius:3px;}
.badge{display:inline-block;font-size:0.7rem;font-weight:700;padding:2px 6px;border-radius:4px;background:#e2e8f0;}
.badge-critical{background:#fecaca;color:#991b1b;}
.badge-warning{background:#fde68a;color:#92400e;}
.badge-info{background:#e2e8f0;color:#475569;}
@media print{body{margin:0;max-width:none;}.pill{border:1px solid #ccc;}}
</style>
</head>
<body>
<header>
  <h1>Site Monitor Report</h1>
  <p class="meta">
    <strong>{$site_name}</strong> · <a href="{$site_url}">{$site_url}</a><br>
    Generated {$generated} · Internal triage · <a href="{$admin_url}">Open in WP admin</a>
  </p>
</header>

<section>
  <h2>Summary</h2>
  <div class="summary">
    <span class="pill pill-fail">{$fail_n} fail</span>
    <span class="pill pill-warn">{$warn_n} warn</span>
    <span class="pill pill-pass">{$pass_n} pass</span>
    <span class="pill pill-skip">{$skip_n} skip</span>
    <span class="pill">{$total_n} total</span>
  </div>
  {$empty_note}
</section>

<section>
  <h2>Failures ({$fail_n})</h2>
HTML
            . ($fail_n === 0
                ? '<p class="muted">No failing checks.</p>'
                : '<table><thead><tr><th>Check</th><th>Severity</th><th>Detail</th><th>When</th></tr></thead><tbody>' . $fail_rows . '</tbody></table>')
            . <<<HTML
</section>
{$warn_section}
<section>
  <h2>Passes ({$pass_n})</h2>
HTML
            . ($pass_n === 0 ? '<p class="muted">None.</p>' : $pass_list)
            . <<<HTML
</section>
<section>
  <h2>Skipped / other ({$skip_n})</h2>
HTML
            . ($skip_n === 0 ? '<p class="muted">None.</p>' : $skip_list)
            . <<<HTML
</section>
<p class="foot">Matrix Site Monitor · report from last-known check results (may span multiple run times).</p>
</body>
</html>
HTML;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private static function sort_by_severity(array $a, array $b): int {
        $sev = ['critical' => 0, 'warning' => 1, 'info' => 2];
        $sa  = $sev[ (string) ($a['severity'] ?? '') ] ?? 9;
        $sb  = $sev[ (string) ($b['severity'] ?? '') ] ?? 9;
        if ($sa !== $sb) {
            return $sa <=> $sb;
        }
        return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function render_detail_rows(array $rows, bool $fail_style): string {
        $html = '';
        foreach ($rows as $row) {
            $label    = esc_html((string) ($row['label'] ?? $row['id'] ?? ''));
            $id       = esc_html((string) ($row['id'] ?? ''));
            $category = esc_html((string) ($row['category'] ?? ''));
            $severity = (string) ($row['severity'] ?? 'warning');
            $sev_class = 'badge-' . preg_replace('/[^a-z]/', '', strtolower($severity));
            $sev_label = esc_html(strtoupper($severity));
            $message = esc_html((string) ($row['message'] ?? ''));
            $time    = esc_html((string) ($row['time'] ?? '—'));
            $source  = esc_html((string) ($row['source'] ?? ''));
            $when    = $time;
            if ($source !== '') {
                $when .= '<br><span class="muted">' . $source . '</span>';
            }

            $cov_html = '';
            $cov_fail = [];
            foreach (Admin_View::coverage_rows((string) ($row['id'] ?? ''), $row) as $cov) {
                if (($cov['status'] ?? '') === 'fail') {
                    $cov_fail[] = $cov;
                }
            }
            if ($cov_fail) {
                $cov_html = '<ul class="cov">';
                foreach ($cov_fail as $cov) {
                    $line   = esc_html($cov['line']);
                    $detail = esc_html($cov['detail']);
                    $cov_html .= '<li><strong>' . $line . '</strong>'
                        . ($detail !== '' ? ' — ' . $detail : '')
                        . '</li>';
                }
                $cov_html .= '</ul>';
            }

            $tr_class = $fail_style ? ' class="fail-row"' : '';
            $html    .= "<tr{$tr_class}>"
                . '<td><strong>' . $label . '</strong><br><span class="muted">' . $category
                . ($id !== '' ? ' · <code>' . $id . '</code>' : '')
                . '</span></td>'
                . '<td><span class="badge ' . esc_attr($sev_class) . '">' . $sev_label . '</span></td>'
                . '<td><p class="msg">' . $message . '</p>' . $cov_html . '</td>'
                . '<td>' . $when . '</td>'
                . '</tr>';
        }
        return $html;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function render_brief_list(array $rows): string {
        $html = '<ul class="brief">';
        foreach ($rows as $row) {
            $label    = esc_html((string) ($row['label'] ?? $row['id'] ?? ''));
            $category = esc_html((string) ($row['category'] ?? ''));
            $html    .= '<li><strong>' . $label . '</strong>'
                . ($category !== '' ? ' <span class="muted">(' . $category . ')</span>' : '')
                . '</li>';
        }
        $html .= '</ul>';
        return $html;
    }
}
