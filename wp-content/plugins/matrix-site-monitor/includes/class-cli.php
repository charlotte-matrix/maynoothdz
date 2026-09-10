<?php
/**
 * WP-CLI commands for Matrix Site Monitor.
 *
 * CLI is the primary way to run checks (avoids PHP/web timeouts).
 * Admin UI remains optional for viewing results and light one-off tests.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

class CLI {
    /**
     * Run health checks (preferred over admin for heavy / all suites).
     *
     * ## OPTIONS
     *
     * [--tier=<tier>]
     * : Tier to run: light, heavy, synthetic, or all. Default: light.
     *
     * [--check=<id>]
     * : Run a single check by ID (e.g. preflight, seo, wc_gateways).
     *
     * [--category=<category>]
     * : Run all checks in a docs category (e.g. Preflight, SEO, Security).
     *
     * [--group=<group>]
     * : Alias of --category for MGPC familiarity.
     *
     * [--exclude=<ids>]
     * : Comma-separated check IDs to skip.
     *
     * [--profile=<profile>]
     * : QA profile for this run: auto, development, or live.
     *   live applies production rules (file editor, debug display, Redis, XML-RPC).
     *   development skips those. auto follows WP_ENVIRONMENT_TYPE (or the saved setting).
     *
     * [--format=<format>]
     * : table|json (default: table)
     *
     * [--coverage]
     * : When a check returns coverage rows (e.g. preflight), print them too.
     *
     * ## EXAMPLES
     *
     *     wp msm run
     *     wp msm run --tier=heavy
     *     wp msm run --tier=all
     *     wp msm run --check=preflight
     *     wp msm run --check=preflight --coverage
     *     wp msm run --category=Preflight
     *     wp msm run --tier=heavy --exclude=browser,golive
     *     wp msm run --profile=live --check=golive --coverage
     *     wp msm preflight --profile=live
     *     wp msm run --format=json
     *
     * @param array<int, string>    $args       Positional args.
     * @param array<string, string> $assoc_args Named args.
     */
    public function run(array $args, array $assoc_args): void {
        $runner   = new Check_Runner();
        $check_id = $assoc_args['check'] ?? '';
        $category = $assoc_args['category'] ?? ($assoc_args['group'] ?? '');
        $exclude  = [];
        if (! empty($assoc_args['exclude'])) {
            $exclude = array_filter(array_map('trim', explode(',', (string) $assoc_args['exclude'])));
        }
        $format   = $assoc_args['format'] ?? 'table';
        $coverage = isset($assoc_args['coverage']);

        if (! empty($assoc_args['profile'])) {
            Settings::set_qa_profile_override((string) $assoc_args['profile']);
        }

        // Default tier=light only when no check/category filter (CLI primary path).
        if ($check_id !== '') {
            $tier = '';
        } elseif ($category !== '') {
            $tier = $assoc_args['tier'] ?? 'all';
        } else {
            $tier = $assoc_args['tier'] ?? 'light';
        }

        $payload = $runner->run_filtered([
            'tier'     => $tier,
            'check'    => $check_id,
            'category' => $category,
            'exclude'  => $exclude,
            'force'    => true,
        ]);

        if ('json' === $format) {
            \WP_CLI::line(wp_json_encode($payload, JSON_PRETTY_PRINT));
            return;
        }

        $items = [];
        foreach ($payload['results'] ?? [] as $result) {
            $items[] = [
                'id'       => $result['id'] ?? '',
                'category' => $result['category'] ?? '',
                'status'   => self::result_status($result),
                'message'  => self::truncate((string) ($result['message'] ?? ''), 120),
                'duration' => isset($result['duration']) ? (string) $result['duration'] . 's' : '',
            ];
        }

        if ($items) {
            \WP_CLI\Utils\format_items('table', $items, ['id', 'category', 'status', 'message', 'duration']);
        } else {
            \WP_CLI::warning($payload['message'] ?? 'No results.');
        }

        if ($coverage) {
            foreach ($payload['results'] ?? [] as $result) {
                if (empty($result['coverage']) || ! is_array($result['coverage'])) {
                    continue;
                }
                \WP_CLI::line('');
                \WP_CLI::line('Coverage: ' . ($result['id'] ?? ''));
                $cov_items = [];
                foreach ($result['coverage'] as $row) {
                    $cov_items[] = [
                        'line'   => self::truncate((string) ($row['line'] ?? $row['label'] ?? ''), 70),
                        'status' => (string) ($row['status'] ?? ''),
                        'detail' => self::truncate((string) ($row['detail'] ?? ''), 90),
                    ];
                }
                \WP_CLI\Utils\format_items('table', $cov_items, ['line', 'status', 'detail']);
            }
        }

        $summary = $payload['summary'] ?? null;
        if (! is_array($summary)) {
            $summary = [
                'pass' => 0,
                'fail' => 0,
                'skip' => 0,
                'warn' => 0,
            ];
            foreach ($payload['results'] ?? [] as $result) {
                $st = self::result_status($result);
                if (isset($summary[ $st ])) {
                    $summary[ $st ]++;
                } else {
                    $summary['pass']++;
                }
            }
        }

        $msg = sprintf(
            'Summary: pass=%d fail=%d warn=%d skip=%d',
            (int) ($summary['pass'] ?? 0),
            (int) ($summary['fail'] ?? 0),
            (int) ($summary['warn'] ?? 0),
            (int) ($summary['skip'] ?? 0)
        );

        if (! empty($payload['ok'])) {
            \WP_CLI::success($msg);
        } else {
            \WP_CLI::warning($msg);
        }
    }

    /**
     * List registered checks.
     *
     * ## OPTIONS
     *
     * [--tier=<tier>]
     * : Filter by tier: light, heavy, synthetic.
     *
     * [--category=<category>]
     * : Filter by category.
     *
     * [--format=<format>]
     * : table|json (default: table)
     *
     * ## EXAMPLES
     *
     *     wp msm list
     *     wp msm list --tier=heavy
     *     wp msm list --category=Preflight
     *
     * @param array<int, string>    $args       Positional args.
     * @param array<string, string> $assoc_args Named args.
     */
    public function list_(array $args, array $assoc_args): void {
        // WP-CLI maps `list` to list_ when method would collide with reserved words.
        $this->list_checks($args, $assoc_args);
    }

    /**
     * @param array<int, string>    $args       Args.
     * @param array<string, string> $assoc_args Assoc.
     */
    public function list_checks(array $args, array $assoc_args): void {
        $tier     = $assoc_args['tier'] ?? '';
        $category = $assoc_args['category'] ?? ($assoc_args['group'] ?? '');
        $format   = $assoc_args['format'] ?? 'table';
        $items    = [];

        foreach (Check_Registry::catalog() as $check) {
            if ($tier !== '' && $check->tier() !== $tier) {
                continue;
            }
            if ($category !== '' && strcasecmp($check->category(), $category) !== 0) {
                continue;
            }
            $disabled = Settings::is_check_disabled($check->id());
            $items[]  = [
                'id'       => $check->id(),
                'label'    => $check->label(),
                'tier'     => $check->tier(),
                'category' => $check->category(),
                'severity'=> $check->severity(),
                'enabled'  => $disabled ? 'no' : 'yes',
            ];
        }

        if ('json' === $format) {
            \WP_CLI::line(wp_json_encode($items, JSON_PRETTY_PRINT));
            return;
        }

        \WP_CLI\Utils\format_items('table', $items, ['id', 'label', 'tier', 'category', 'severity', 'enabled']);
        \WP_CLI::success(sprintf('%d check(s).', count($items)));
    }

    /**
     * Show last check status.
     *
     * ## OPTIONS
     *
     * [--tier=<tier>]
     * : Tier to show: light, heavy, synthetic. Default: light.
     *
     * [--format=<format>]
     * : table|json (default: table)
     *
     * @param array<int, string>    $args       Positional args.
     * @param array<string, string> $assoc_args Named args.
     */
    public function status(array $args, array $assoc_args): void {
        $tier    = $assoc_args['tier'] ?? 'light';
        $format  = $assoc_args['format'] ?? 'table';
        $payload = Storage::get_tier_result($tier);

        if (empty($payload)) {
            \WP_CLI::warning('No results for tier: ' . $tier);
            return;
        }

        if ('json' === $format) {
            \WP_CLI::line(wp_json_encode($payload, JSON_PRETTY_PRINT));
            return;
        }

        $status = ! empty($payload['ok']) ? 'PASS' : 'FAIL';
        \WP_CLI::line(strtoupper($tier) . ': ' . $status . ' @ ' . ($payload['time'] ?? 'unknown'));

        $items = [];
        foreach ($payload['results'] ?? [] as $result) {
            $items[] = [
                'id'     => $result['id'] ?? '',
                'status' => self::result_status($result),
                'label'  => $result['label'] ?? '',
            ];
        }
        if ($items) {
            \WP_CLI\Utils\format_items('table', $items, ['id', 'status', 'label']);
        }
    }

    /**
     * Run the MGPC-parity preflight suite (alias of --check=preflight).
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : table|json (default: table)
     *
     * [--coverage]
     * : Print each preflight sub-check row.
     *
     * [--profile=<profile>]
     * : QA profile for this run: auto, development, or live.
     *
     * ## EXAMPLES
     *
     *     wp msm preflight
     *     wp msm preflight --coverage
     *     wp msm preflight --profile=live
     *     wp msm preflight --format=json
     *
     * @param array<int, string>    $args       Positional args.
     * @param array<string, string> $assoc_args Named args.
     */
    public function preflight(array $args, array $assoc_args): void {
        $assoc_args['check'] = 'preflight';
        if (! isset($assoc_args['coverage'])) {
            $assoc_args['coverage'] = true;
        }
        $this->run($args, $assoc_args);
    }

    /**
     * Sync legacy property meta from property_data block values (MGPC helper).
     *
     * Only relevant on sites with a `property` post type.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Show what would be updated without writing changes.
     *
     * [--limit=<limit>]
     * : Number of properties to scan (default: 300).
     *
     * [--format=<format>]
     * : table|json (default: table)
     *
     * @param array<int, string>    $args       Positional args.
     * @param array<string, string> $assoc_args Named args.
     */
    public function sync_property_meta(array $args, array $assoc_args): void {
        $result = Checks\Check_Preflight::sync_property_meta_from_property_data([
            'dry_run' => isset($assoc_args['dry-run']),
            'limit'   => isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 300,
        ]);

        $format = $assoc_args['format'] ?? 'table';
        if ('json' === $format) {
            \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
            return;
        }

        $items = [];
        foreach ((array) ($result['details'] ?? []) as $row) {
            $items[] = [
                'property_id' => $row['property_id'] ?? '',
                'title'       => $row['title'] ?? '',
                'changes'     => wp_json_encode($row['changes'] ?? []),
            ];
        }

        if (empty($items)) {
            \WP_CLI::success($result['message'] ?? 'No property meta updates required.');
            return;
        }

        \WP_CLI\Utils\format_items('table', $items, ['property_id', 'title', 'changes']);
        \WP_CLI::success(
            sprintf(
                '%s completed: synced %d properties (scanned %d).',
                ! empty($result['dry_run']) ? 'Dry-run' : 'Sync',
                (int) ($result['properties_synced'] ?? 0),
                (int) ($result['properties_seen'] ?? 0)
            )
        );
    }

    /**
     * @param array<string, mixed> $result Result row.
     */
    private static function result_status(array $result): string {
        $status = (string) ($result['status'] ?? '');
        if ($status === '' && ! empty($result['skipped'])) {
            return 'skip';
        }
        if ($status === '') {
            return ! empty($result['ok']) ? 'pass' : 'fail';
        }
        return $status;
    }

    /**
     * Icon for a result row.
     *
     * @param array<string, mixed> $result Check result.
     */
    private static function result_icon(array $result): string {
        switch (self::result_status($result)) {
            case 'pass':
                return '✓';
            case 'skip':
            case 'manual':
                return '–';
            case 'warn':
                return '!';
            case 'fail':
                return '✗';
            default:
                return '?';
        }
    }

    private static function truncate(string $text, int $len): string {
        if (strlen($text) <= $len) {
            return $text;
        }
        return substr($text, 0, $len - 1) . '…';
    }
}
