<?php
/**
 * Check runner — executes suites and stores results.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

class Check_Runner {
    /**
     * Run all checks for a tier.
     *
     * @param string $tier light, heavy, or synthetic.
     * @param bool   $force Skip lock acquisition.
     * @return array<string, mixed>
     */
    public function run_tier(string $tier, bool $force = false): array {
        if (! $force && ! Resource_Guard::acquire_lock($tier)) {
            return [
                'time'    => current_time('mysql'),
                'tier'    => $tier,
                'ok'      => false,
                'skipped' => true,
                'message' => 'Another check run is already in progress.',
                'results' => [],
            ];
        }

        if ($tier === 'synthetic' && Resource_Guard::should_skip_synthetic()) {
            Resource_Guard::release_lock();
            return [
                'time'    => current_time('mysql'),
                'tier'    => $tier,
                'ok'      => true,
                'skipped' => true,
                'message' => 'Skipped because a critical light-tier check recently failed.',
                'results' => [],
            ];
        }

        Resource_Guard::prepare_runtime($tier);

        if ($tier === 'synthetic' && class_exists('WooCommerce')) {
            Mailguard::set_suppressing(true);
        }

        $checks  = Check_Registry::for_tier($tier);
        $results = [];

        foreach ($checks as $check) {
            $start = microtime(true);
            try {
                $results[] = $check->run();
            } catch (\Throwable $e) {
                $results[] = [
                    'id'       => $check->id(),
                    'label'    => $check->label(),
                    'status'   => 'fail',
                    'ok'       => false,
                    'skipped'  => false,
                    'severity' => 'critical',
                    'message'  => 'Fatal: ' . $e->getMessage(),
                    'duration' => round(microtime(true) - $start, 3),
                ];
            }
        }

        if ($tier === 'synthetic' && class_exists('WooCommerce')) {
            Checks_Woocommerce\Wc_Test_Factory::purge_orphans();
            Mailguard::set_suppressing(false);
        }

        $ok = array_reduce($results, static function (bool $carry, array $r): bool {
            $status = (string) ($r['status'] ?? '');
            if ($status === 'skip') {
                return $carry;
            }
            // Only critical failures fail the overall tier.
            // Warning/info still show as FAIL rows for visibility.
            if ($status === 'fail' && ($r['severity'] ?? '') === 'critical') {
                return false;
            }
            if ($status === '' && empty($r['ok']) && ($r['severity'] ?? '') === 'critical') {
                return false;
            }
            return $carry;
        }, true);

        $payload = [
            'time'    => current_time('mysql'),
            'tier'    => $tier,
            'ok'      => $ok,
            'results' => $results,
        ];

        Storage::save_tier_result($tier, $payload);
        Notifier::after_tier_run($payload);

        Resource_Guard::release_lock();

        return $payload;
    }

    /**
     * Run a single check by ID (for testing one at a time).
     *
     * @param string $check_id Check identifier.
     * @return array<string, mixed>
     */
    public function run_check(string $check_id): array {
        $check = Check_Registry::get($check_id);
        if (! $check) {
            return [
                'time'    => current_time('mysql'),
                'ok'      => false,
                'message' => 'Unknown check: ' . $check_id,
                'results' => [],
            ];
        }

        if (! Resource_Guard::acquire_lock('single:' . $check_id)) {
            return [
                'time'    => current_time('mysql'),
                'ok'      => false,
                'skipped' => true,
                'message' => 'Another check run is already in progress.',
                'results' => [],
            ];
        }

        Resource_Guard::prepare_runtime($check->tier());

        if ($check->tier() === 'synthetic' && class_exists('WooCommerce')) {
            Mailguard::set_suppressing(true);
        }

        $start = microtime(true);
        try {
            $result = $check->run();
        } catch (\Throwable $e) {
            $result = [
                'id'       => $check->id(),
                'label'    => $check->label(),
                'status'   => 'fail',
                'ok'       => false,
                'skipped'  => false,
                'severity' => 'critical',
                'message'  => 'Fatal: ' . $e->getMessage(),
                'duration' => round(microtime(true) - $start, 3),
            ];
        }

        if ($check->tier() === 'synthetic' && class_exists('WooCommerce')) {
            Checks_Woocommerce\Wc_Test_Factory::purge_orphans();
            Mailguard::set_suppressing(false);
        }

        Resource_Guard::release_lock();

        $status = (string) ($result['status'] ?? '');
        $payload = [
            'time'    => current_time('mysql'),
            'tier'    => 'single:' . $check_id,
            'ok'      => $status !== 'fail',
            'results' => [$result],
        ];

        // Keep last single-run visible without overwriting tier history.
        update_option('msm_last_single_result', $payload, false);
        Storage::merge_check_results([$result], $payload['time'], 'single');

        return $payload;
    }

    /**
     * Run all tiers sequentially.
     *
     * @return array<string, array<string, mixed>>
     */
    public function run_all(): array {
        return [
            'light'     => $this->run_tier('light', true),
            'heavy'     => $this->run_tier('heavy', true),
            'synthetic' => $this->run_tier('synthetic', true),
        ];
    }

    /**
     * Run a filtered set of checks (CLI primary path — avoids PHP timeouts).
     *
     * @param array{
     *   tier?: string,
     *   category?: string,
     *   check?: string,
     *   exclude?: array<int, string>,
     *   force?: bool
     * } $args Filter args.
     * @return array<string, mixed>
     */
    public function run_filtered(array $args): array {
        $tier     = sanitize_key((string) ($args['tier'] ?? ''));
        $category = (string) ($args['category'] ?? '');
        $check_id = sanitize_key((string) ($args['check'] ?? ''));
        $exclude  = array_map('sanitize_key', (array) ($args['exclude'] ?? []));
        $force    = ! empty($args['force']);

        if ($check_id !== '') {
            return $this->run_check($check_id);
        }

        // Plain tier run (stores tier history) when no extra filters.
        if ($category === '' && empty($exclude)) {
            if ($tier === 'all' || $tier === '') {
                $all = $this->run_all();
                $results = [];
                $ok      = true;
                foreach ($all as $payload) {
                    foreach ($payload['results'] ?? [] as $row) {
                        $results[] = $row;
                    }
                    if (empty($payload['ok'])) {
                        $ok = false;
                    }
                }
                return [
                    'time'    => current_time('mysql'),
                    'tier'    => 'all',
                    'ok'      => $ok,
                    'results' => $results,
                    'summary' => $this->summarize_results($results),
                    'tiers'   => $all,
                ];
            }
            return $this->run_tier($tier, $force);
        }

        $checks = Check_Registry::all();
        if ($tier !== '' && $tier !== 'all') {
            $checks = array_values(array_filter($checks, static function ($c) use ($tier) {
                return $c->tier() === $tier;
            }));
        }

        if ($category !== '') {
            $cat_lc = strtolower($category);
            $checks = array_values(array_filter($checks, static function ($c) use ($cat_lc) {
                return strtolower($c->category()) === $cat_lc;
            }));
        }

        if ($exclude) {
            $checks = array_values(array_filter($checks, static function ($c) use ($exclude) {
                return ! in_array($c->id(), $exclude, true);
            }));
        }

        if (empty($checks)) {
            return [
                'time'    => current_time('mysql'),
                'ok'      => false,
                'message' => 'No checks matched filters.',
                'results' => [],
                'summary' => ['pass' => 0, 'fail' => 0, 'skip' => 0, 'warn' => 0],
            ];
        }

        if (! $force && ! Resource_Guard::acquire_lock('filtered')) {
            return [
                'time'    => current_time('mysql'),
                'ok'      => false,
                'skipped' => true,
                'message' => 'Another check run is already in progress.',
                'results' => [],
                'summary' => ['pass' => 0, 'fail' => 0, 'skip' => 0, 'warn' => 0],
            ];
        }

        Resource_Guard::prepare_runtime('heavy');
        $results = [];
        $has_synthetic = false;

        foreach ($checks as $check) {
            if ($check->tier() === 'synthetic') {
                $has_synthetic = true;
            }
        }

        if ($has_synthetic && class_exists('WooCommerce')) {
            Mailguard::set_suppressing(true);
        }

        foreach ($checks as $check) {
            $start = microtime(true);
            try {
                $results[] = $check->run();
            } catch (\Throwable $e) {
                $results[] = [
                    'id'       => $check->id(),
                    'label'    => $check->label(),
                    'status'   => 'fail',
                    'ok'       => false,
                    'skipped'  => false,
                    'severity' => 'critical',
                    'message'  => 'Fatal: ' . $e->getMessage(),
                    'duration' => round(microtime(true) - $start, 3),
                ];
            }
        }

        if ($has_synthetic && class_exists('WooCommerce')) {
            Checks_Woocommerce\Wc_Test_Factory::purge_orphans();
            Mailguard::set_suppressing(false);
        }

        Resource_Guard::release_lock();

        $summary = $this->summarize_results($results);
        $ok      = true;
        foreach ($results as $r) {
            $status = (string) ($r['status'] ?? '');
            if ($status === 'skip' || ! empty($r['skipped'])) {
                continue;
            }
            if (($status === 'fail' || ($status === '' && empty($r['ok']))) && ($r['severity'] ?? '') === 'critical') {
                $ok = false;
                break;
            }
        }

        $payload = [
            'time'    => current_time('mysql'),
            'tier'    => 'filtered',
            'ok'      => $ok,
            'results' => $results,
            'summary' => $summary,
        ];

        Storage::merge_check_results($results, $payload['time'], 'filtered');

        return $payload;
    }

    /**
     * Summarize result rows.
     *
     * @param array<int, array<string, mixed>> $results Results.
     * @return array{pass:int,fail:int,skip:int,warn:int}
     */
    private function summarize_results(array $results): array {
        $summary = ['pass' => 0, 'fail' => 0, 'skip' => 0, 'warn' => 0];
        foreach ($results as $r) {
            $status = (string) ($r['status'] ?? '');
            if ($status === 'skip' || ! empty($r['skipped'])) {
                $summary['skip']++;
            } elseif ($status === 'fail' || ($status === '' && empty($r['ok']))) {
                $summary['fail']++;
            } elseif ($status === 'warn') {
                $summary['warn']++;
            } else {
                $summary['pass']++;
            }
        }
        return $summary;
    }
}
