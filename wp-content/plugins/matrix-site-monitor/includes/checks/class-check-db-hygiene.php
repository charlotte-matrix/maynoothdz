<?php
/**
 * Database hygiene from docs/Database.md — line coverage.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Db_Hygiene extends Check_Base {
    public function id(): string { return 'db_hygiene'; }
    public function label(): string { return 'Database hygiene'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        global $wpdb;
        $start = microtime(true);

        $findings = [
            'corrupt'      => [],
            'missing'      => [],
            'transients'   => null,
            'orphan_meta'  => [],
            'spam'         => null,
            'revisions'    => null,
            'db_upgrade'   => [],
        ];

        $core_tables = $this->core_tables();

        // Missing tables.
        foreach ($core_tables as $label => $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                $findings['missing'][] = $label . ' (' . $table . ')';
            }
        }

        // Corrupt tables — CHECK TABLE on present core tables only (capped).
        if (empty($findings['missing'])) {
            foreach (array_slice(array_values($core_tables), 0, 6) as $table) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $rows = $wpdb->get_results('CHECK TABLE `' . str_replace('`', '``', $table) . '`', ARRAY_A);
                if (! is_array($rows) || empty($rows)) {
                    continue;
                }
                foreach ($rows as $row) {
                    $msg = strtolower((string) ($row['Msg_text'] ?? ''));
                    if ($msg !== '' && $msg !== 'ok' && strpos($msg, 'table is already up to date') === false) {
                        $findings['corrupt'][] = $table . ': ' . ($row['Msg_text'] ?? 'error');
                    }
                }
            }
        }

        // Spam comments.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $spam = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
        $findings['spam'] = $spam;

        // Revisions.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $revisions = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
            'revision'
        ));
        $findings['revisions'] = $revisions;

        // Transients (count in options — large backlog signal).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $transients = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%' OR option_name LIKE '\\_site\\_transient\\_%'"
        );
        $findings['transients'] = $transients;

        // Orphaned metadata (sample counts — not full cleanup).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $orphan_postmeta = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL"
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $orphan_usermeta = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->users} u ON u.ID = um.user_id WHERE u.ID IS NULL"
        );
        if ($orphan_postmeta > 50) {
            $findings['orphan_meta'][] = $orphan_postmeta . ' orphaned postmeta rows';
        }
        if ($orphan_usermeta > 20) {
            $findings['orphan_meta'][] = $orphan_usermeta . ' orphaned usermeta rows';
        }

        // Failed database upgrades — site db_version behind WordPress expected.
        global $wp_db_version;
        $site_db = (int) get_option('db_version');
        $expected = (int) $wp_db_version;
        if ($expected > 0 && $site_db > 0 && $site_db < $expected) {
            $findings['db_upgrade'][] = sprintf(
                'db_version %d is behind WordPress expected %d — visit /wp-admin/upgrade.php',
                $site_db,
                $expected
            );
        }

        $coverage = $this->build_coverage($findings);
        $failed   = array_filter($coverage, static function (array $r): bool {
            return ($r['status'] ?? '') === 'fail';
        });

        $extra = [
            'coverage' => $coverage,
            'counts'   => [
                'spam'       => $spam,
                'revisions'  => $revisions,
                'transients' => $transients,
                'orphan_pm'  => $orphan_postmeta,
                'orphan_um'  => $orphan_usermeta,
                'db_version' => $site_db,
                'wp_db'      => $expected,
            ],
        ];

        if ($failed) {
            $msgs = [];
            foreach (array_slice(array_values($failed), 0, 6) as $row) {
                $msgs[] = $row['line'] . (! empty($row['detail']) ? ': ' . $row['detail'] : '');
            }
            return $this->fail(implode('; ', $msgs) . '.', $start, $extra);
        }

        return $this->pass(
            sprintf(
                'DB OK (spam %d, revisions %d, transients %d, db_version %d).',
                $spam,
                $revisions,
                $transients,
                $site_db
            ),
            $start,
            $extra
        );
    }

    /**
     * Every Database.md issue line.
     *
     * @return array<int, array{line:string,mode:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'Corrupt tables', 'mode' => 'automated'],
            ['line' => 'Missing tables', 'mode' => 'automated'],
            ['line' => 'Large transients', 'mode' => 'automated'],
            ['line' => 'Orphaned metadata', 'mode' => 'automated'],
            ['line' => 'Spam comments', 'mode' => 'automated'],
            ['line' => 'Excessive revisions', 'mode' => 'automated'],
            ['line' => 'Failed database upgrades', 'mode' => 'automated'],
        ];
    }

    /**
     * Core WordPress tables to verify.
     *
     * @return array<string, string> Label => table name.
     */
    private function core_tables(): array {
        global $wpdb;
        return [
            'posts'              => $wpdb->posts,
            'postmeta'           => $wpdb->postmeta,
            'comments'           => $wpdb->comments,
            'commentmeta'        => $wpdb->commentmeta,
            'options'            => $wpdb->options,
            'users'              => $wpdb->users,
            'usermeta'           => $wpdb->usermeta,
            'terms'              => $wpdb->terms,
            'term_taxonomy'      => $wpdb->term_taxonomy,
            'term_relationships' => $wpdb->term_relationships,
        ];
    }

    /**
     * @param array<string, mixed> $findings Findings.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function build_coverage(array $findings): array {
        $thresholds = [
            'spam'       => 100,
            'revisions'  => 500,
            'transients' => 1000,
        ];

        $coverage = [];
        foreach (self::doc_lines() as $row) {
            $line = $row['line'];
            $mode = $row['mode'];

            switch ($line) {
                case 'Corrupt tables':
                    $hits = $findings['corrupt'] ?? [];
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits) ? 'CHECK TABLE OK on core sample' : implode('; ', array_slice($hits, 0, 3)),
                    ];
                    break;

                case 'Missing tables':
                    $hits = $findings['missing'] ?? [];
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits) ? 'Core WP tables present' : implode('; ', $hits),
                    ];
                    break;

                case 'Large transients':
                    $n = (int) ($findings['transients'] ?? 0);
                    $ok = $n <= $thresholds['transients'];
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => $ok ? 'pass' : 'fail',
                        'detail' => $n . ' transient option rows (threshold ' . $thresholds['transients'] . ')',
                    ];
                    break;

                case 'Orphaned metadata':
                    $hits = $findings['orphan_meta'] ?? [];
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'Postmeta/usermeta orphan counts within thresholds'
                            : implode('; ', $hits),
                    ];
                    break;

                case 'Spam comments':
                    $n = (int) ($findings['spam'] ?? 0);
                    $ok = $n <= $thresholds['spam'];
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => $ok ? 'pass' : 'fail',
                        'detail' => $n . ' spam comments (threshold ' . $thresholds['spam'] . ')',
                    ];
                    break;

                case 'Excessive revisions':
                    $n = (int) ($findings['revisions'] ?? 0);
                    $ok = $n <= $thresholds['revisions'];
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => $ok ? 'pass' : 'fail',
                        'detail' => $n . ' revisions (threshold ' . $thresholds['revisions'] . ')',
                    ];
                    break;

                case 'Failed database upgrades':
                    $hits = $findings['db_upgrade'] ?? [];
                    $coverage[] = [
                        'line'   => $line,
                        'mode'   => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'db_version matches WordPress schema'
                            : implode('; ', $hits),
                    ];
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
}
