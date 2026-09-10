<?php
/**
 * Admin view helpers for Matrix Site Monitor.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Admin;

use Matrix_Site_Monitor\Check_Interface;
use Matrix_Site_Monitor\Check_Registry;

defined('ABSPATH') || exit;

class Admin_View {
    /**
     * Normalize a result status key.
     *
     * @param array<string, mixed> $result Result row.
     */
    public static function status_key(array $result): string {
        $status = (string) ($result['status'] ?? '');
        if (in_array($status, ['pass', 'fail', 'skip', 'warn', 'manual', 'ai'], true)) {
            return $status;
        }
        if (! empty($result['skipped'])) {
            return 'skip';
        }
        return ! empty($result['ok']) ? 'pass' : 'fail';
    }

    /**
     * Status badge HTML.
     *
     * @param string $status Status key.
     */
    public static function badge(string $status): string {
        $map = [
            'pass'     => ['PASS', 'msm-badge--pass'],
            'fail'     => ['FAIL', 'msm-badge--fail'],
            'warn'     => ['WARN', 'msm-badge--warn'],
            'skip'     => ['SKIP', 'msm-badge--skip'],
            'manual'   => ['MANUAL', 'msm-badge--manual'],
            'ai'       => ['AI', 'msm-badge--ai'],
            'disabled' => ['OFF', 'msm-badge--muted'],
            'not_run'  => ['—', 'msm-badge--muted'],
        ];
        $label = $map[$status][0] ?? 'FAIL';
        $class = $map[$status][1] ?? 'msm-badge--fail';
        return '<span class="msm-badge ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    /**
     * Checks grouped by category (display order).
     *
     * @param array<int, Check_Interface> $catalog Catalog.
     * @return array<string, array<int, Check_Interface>>
     */
    public static function checks_by_category(array $catalog): array {
        $by = [];
        foreach ($catalog as $check) {
            $by[ $check->category() ][] = $check;
        }
        $ordered = [];
        foreach (Check_Registry::category_order() as $cat) {
            if (! empty($by[ $cat ])) {
                $ordered[ $cat ] = $by[ $cat ];
                unset($by[ $cat ]);
            }
        }
        foreach ($by as $cat => $checks) {
            $ordered[ $cat ] = $checks;
        }
        return $ordered;
    }

    /**
     * Count statuses for a category.
     *
     * @param array<int, Check_Interface>           $checks Checks.
     * @param array<string, array<string, mixed>>   $map    Results map.
     * @param array<int, string>                    $disabled Disabled IDs.
     * @return array{fail:int,warn:int,pass:int,skip:int,other:int}
     */
    public static function category_counts(array $checks, array $map, array $disabled): array {
        $counts = ['fail' => 0, 'warn' => 0, 'pass' => 0, 'skip' => 0, 'other' => 0];
        foreach ($checks as $check) {
            $id = $check->id();
            if (in_array($id, $disabled, true)) {
                $counts['other']++;
                continue;
            }
            if (empty($map[ $id ])) {
                $counts['other']++;
                continue;
            }
            $st = self::status_key($map[ $id ]);
            if (isset($counts[ $st ])) {
                $counts[ $st ]++;
            } else {
                $counts['other']++;
            }
        }
        return $counts;
    }

    /**
     * Coverage rows for a check (from last result only — no empty placeholders).
     *
     * @param string               $check_id Check ID.
     * @param array<string, mixed> $result   Result.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    public static function coverage_rows(string $check_id, array $result): array {
        if (empty($result['coverage']) || ! is_array($result['coverage'])) {
            return [];
        }
        $rows = [];
        foreach ($result['coverage'] as $cov) {
            if (! is_array($cov)) {
                continue;
            }
            $rows[] = [
                'line'   => (string) ($cov['line'] ?? $cov['label'] ?? ''),
                'mode'   => (string) ($cov['mode'] ?? ''),
                'status' => (string) ($cov['status'] ?? 'manual'),
                'detail' => (string) ($cov['detail'] ?? ''),
            ];
        }
        return $rows;
    }

    /**
     * Summarize tier payload.
     *
     * @param array<string, mixed> $payload Tier result.
     * @return array{ok:?bool,time:string,fail:int,total:int}
     */
    public static function tier_summary(array $payload): array {
        if (empty($payload)) {
            return ['ok' => null, 'time' => '', 'fail' => 0, 'total' => 0];
        }
        $fail  = 0;
        $total = 0;
        foreach ($payload['results'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $total++;
            $st = self::status_key($row);
            if ($st === 'fail') {
                $fail++;
            }
        }
        return [
            'ok'    => ! empty($payload['ok']),
            'time'  => (string) ($payload['time'] ?? ''),
            'fail'  => $fail,
            'total' => $total,
        ];
    }

    /**
     * Collect failing checks (and failed coverage lines) for a quick view.
     *
     * @param array<int, Check_Interface>         $catalog  Catalog.
     * @param array<string, array<string, mixed>> $map      Results map.
     * @param array<int, string>                  $disabled Disabled IDs.
     * @return array<int, array{id:string,label:string,category:string,severity:string,message:string,time:string,coverage_fails:array<int,array{line:string,detail:string}>}>
     */
    public static function failing_checks(array $catalog, array $map, array $disabled): array {
        $out = [];
        foreach ($catalog as $check) {
            $id = $check->id();
            if (in_array($id, $disabled, true)) {
                continue;
            }
            $result = $map[ $id ] ?? null;
            if (! is_array($result) || empty($result)) {
                continue;
            }
            if (self::status_key($result) !== 'fail') {
                continue;
            }

            $cov_fails = [];
            foreach (self::coverage_rows($id, $result) as $cov) {
                if (($cov['status'] ?? '') !== 'fail') {
                    continue;
                }
                $cov_fails[] = [
                    'line'   => $cov['line'],
                    'detail' => $cov['detail'],
                ];
            }

            $out[] = [
                'id'             => $id,
                'label'          => $check->label(),
                'category'       => $check->category(),
                'severity'       => $check->severity(),
                'message'        => (string) ($result['message'] ?? ''),
                'time'           => (string) ($result['time'] ?? ''),
                'coverage_fails' => $cov_fails,
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $sev = ['critical' => 0, 'warning' => 1, 'info' => 2];
            $sa  = $sev[ $a['severity'] ] ?? 9;
            $sb  = $sev[ $b['severity'] ] ?? 9;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            return strcasecmp($a['label'], $b['label']);
        });

        return $out;
    }
}
