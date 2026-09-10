<?php
/**
 * Maintenance checks from docs/Maintenance.md — line coverage.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Maintenance extends Check_Base {
    public function id(): string { return 'maintenance'; }
    public function label(): string { return 'Maintenance health'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);

        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (! function_exists('wp_get_update_data')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }
        if (! function_exists('wp_get_themes')) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }

        // Refresh update transients (same as update_availability).
        wp_update_plugins();
        wp_update_themes();

        $findings = [
            'core'         => [],
            'plugins'      => [],
            'themes'       => [],
            'php_compat'   => [],
            'deprecated'   => [],
            'backup'       => [],
            'restore'      => [],
            'unused_plugs' => [],
            'unused_themes'=> [],
            'orphaned'     => [],
            'logs'         => [],
        ];

        $data   = wp_get_update_data();
        $counts = $data['counts'] ?? [];
        if ((int) ($counts['wordpress'] ?? 0) > 0) {
            $findings['core'][] = 'WordPress core update available';
        }

        $plugin_updates = get_site_transient('update_plugins');
        if (is_object($plugin_updates) && ! empty($plugin_updates->response) && is_array($plugin_updates->response)) {
            $n = count($plugin_updates->response);
            $names = [];
            foreach (array_slice($plugin_updates->response, 0, 5, true) as $file => $upd) {
                $names[] = isset($upd->slug) ? (string) $upd->slug : (string) $file;
            }
            $findings['plugins'][] = $n . ' plugin update(s): ' . implode(', ', $names) . ($n > 5 ? '…' : '');
        }

        $theme_updates = get_site_transient('update_themes');
        if (is_object($theme_updates) && ! empty($theme_updates->response) && is_array($theme_updates->response)) {
            $n = count($theme_updates->response);
            $names = array_slice(array_keys($theme_updates->response), 0, 5);
            $findings['themes'][] = $n . ' theme update(s): ' . implode(', ', $names) . ($n > 5 ? '…' : '');
        }

        // PHP compatibility — site PHP vs plugin requires_php from update API.
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $findings['php_compat'][] = 'Site PHP ' . PHP_VERSION . ' is below 8.0';
        }
        if (is_object($plugin_updates) && ! empty($plugin_updates->response)) {
            foreach ($plugin_updates->response as $file => $upd) {
                $req = '';
                if (is_object($upd) && ! empty($upd->requires_php)) {
                    $req = (string) $upd->requires_php;
                }
                if ($req !== '' && version_compare(PHP_VERSION, $req, '<')) {
                    $findings['php_compat'][] = ($upd->slug ?? $file) . ' requires PHP ' . $req;
                }
            }
        }
        // Active plugins declaring Requires PHP in header higher than runtime.
        foreach (get_plugins() as $file => $hdr) {
            if (! is_plugin_active($file)) {
                continue;
            }
            $req = (string) ($hdr['RequiresPHP'] ?? '');
            if ($req !== '' && version_compare(PHP_VERSION, $req, '<')) {
                $findings['php_compat'][] = ($hdr['Name'] ?? $file) . ' requires PHP ' . $req;
            }
        }

        // Deprecated functions — debug.log sample + public HTML (soft).
        $log = $this->debug_log_path();
        if ($log && is_readable($log)) {
            $tail = $this->tail_file($log, 64 * 1024);
            if ($tail !== '' && preg_match('/\bDeprecated\b/i', $tail)) {
                $findings['deprecated'][] = 'Deprecated messages found in debug.log (recent tail)';
            }
        }

        // Backup not completed / restore failed (UpdraftPlus).
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $has_updraft = is_plugin_active('updraftplus/updraftplus.php');
        if ($has_updraft) {
            $last = get_option('updraft_last_backup');
            if (! is_array($last) || empty($last['backup_time'])) {
                $findings['backup'][] = 'UpdraftPlus has no recorded backup';
            } else {
                $age_days = (time() - (int) $last['backup_time']) / DAY_IN_SECONDS;
                if ($age_days > 7) {
                    $findings['backup'][] = sprintf('Last backup was %.1f days ago', $age_days);
                }
                if (! empty($last['error']) || ! empty($last['errors'])) {
                    $findings['backup'][] = 'Last backup reported errors';
                }
            }
            // Restore signals (best-effort).
            $restore = get_option('updraft_last_restore');
            if (is_array($restore) && ! empty($restore['error'])) {
                $findings['restore'][] = 'UpdraftPlus last restore reported an error';
            }
        }

        // Unused plugins.
        $all      = get_plugins();
        $active   = (array) get_option('active_plugins', []);
        $inactive = [];
        foreach ($all as $file => $hdr) {
            if (! in_array($file, $active, true)) {
                // Network-active on multisite counts as used.
                if (is_multisite() && is_plugin_active_for_network($file)) {
                    continue;
                }
                $inactive[] = (string) ($hdr['Name'] ?? $file);
            }
        }
        if (count($inactive) >= 5) {
            $findings['unused_plugs'][] = count($inactive) . ' inactive: ' . implode(', ', array_slice($inactive, 0, 5));
        }

        // Unused themes (not stylesheet, not template, not parent of active).
        $themes    = wp_get_themes();
        $stylesheet = get_stylesheet();
        $template   = get_template();
        $unused_t   = [];
        foreach ($themes as $slug => $theme) {
            if ($slug === $stylesheet || $slug === $template) {
                continue;
            }
            // Keep parent of child theme.
            if ($stylesheet !== $template && $slug === $template) {
                continue;
            }
            $unused_t[] = $theme->get('Name') ?: $slug;
        }
        // Default Twenty* themes often kept — only flag if many extras.
        if (count($unused_t) >= 4) {
            $findings['unused_themes'][] = count($unused_t) . ' unused themes: ' . implode(', ', array_slice($unused_t, 0, 5));
        }

        // Orphaned uploads — sample files in current month uploads not in attachment posts.
        $orphan = $this->sample_orphaned_uploads(15);
        if (! empty($orphan)) {
            $findings['orphaned'][] = count($orphan) . ' sample file(s) not in media library: ' . implode(', ', array_slice($orphan, 0, 3));
        }

        // Large log files.
        foreach ($this->candidate_log_files() as $path) {
            if (! is_readable($path)) {
                continue;
            }
            $size = @filesize($path);
            if ($size !== false && $size > 25 * 1024 * 1024) {
                $findings['logs'][] = basename($path) . ' is ' . size_format((int) $size);
            }
        }

        $coverage = $this->build_coverage($findings, [
            'updraft'   => $has_updraft,
            'inactive_n'=> count($inactive),
            'unused_t_n'=> count($unused_t),
        ]);

        $failed = array_filter($coverage, static function (array $r): bool {
            return ($r['status'] ?? '') === 'fail';
        });

        $extra = ['coverage' => $coverage];

        if ($failed) {
            $msgs = [];
            foreach (array_slice(array_values($failed), 0, 8) as $row) {
                $msgs[] = $row['line'] . (! empty($row['detail']) ? ': ' . $row['detail'] : '');
            }
            return $this->fail(implode('; ', $msgs) . '.', $start, $extra);
        }

        return $this->pass('Maintenance sample OK.', $start, $extra);
    }

    /**
     * @return array<int, array{line:string,mode:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'WordPress core outdated', 'mode' => 'automated'],
            ['line' => 'Plugin updates available', 'mode' => 'automated'],
            ['line' => 'Theme updates available', 'mode' => 'automated'],
            ['line' => 'PHP compatibility issues', 'mode' => 'automated'],
            ['line' => 'Deprecated functions', 'mode' => 'partial'],
            ['line' => 'Backup not completed', 'mode' => 'partial'],
            ['line' => 'Backup restore failed', 'mode' => 'partial'],
            ['line' => 'Unused plugins', 'mode' => 'automated'],
            ['line' => 'Unused themes', 'mode' => 'automated'],
            ['line' => 'Orphaned uploads', 'mode' => 'partial'],
            ['line' => 'Large log files', 'mode' => 'automated'],
        ];
    }

    /**
     * @param array<string, array<int, string>> $findings Findings.
     * @param array<string, mixed>              $meta     Context.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function build_coverage(array $findings, array $meta): array {
        $map = [
            'WordPress core outdated'   => 'core',
            'Plugin updates available'  => 'plugins',
            'Theme updates available'   => 'themes',
            'PHP compatibility issues'  => 'php_compat',
            'Deprecated functions'      => 'deprecated',
            'Backup not completed'      => 'backup',
            'Backup restore failed'     => 'restore',
            'Unused plugins'            => 'unused_plugs',
            'Unused themes'             => 'unused_themes',
            'Orphaned uploads'          => 'orphaned',
            'Large log files'           => 'logs',
        ];

        $pass = [
            'core'          => 'WordPress core up to date',
            'plugins'       => 'No plugin updates pending',
            'themes'        => 'No theme updates pending',
            'php_compat'    => 'No PHP requirement mismatches detected',
            'deprecated'    => 'No Deprecated lines in debug.log tail',
            'backup'        => 'Recent backup recorded',
            'restore'       => 'No restore error recorded',
            'unused_plugs'  => 'Inactive plugins under threshold (<5)',
            'unused_themes' => 'Extra themes under threshold (<4)',
            'orphaned'      => 'No orphaned files in upload sample',
            'logs'          => 'No log files over 25MB',
        ];

        $coverage = [];
        foreach (self::doc_lines() as $row) {
            $line = $row['line'];
            $mode = $row['mode'];
            $key  = $map[$line] ?? '';
            $hits = $key !== '' ? ($findings[$key] ?? []) : [];

            if ($key === 'backup' && empty($meta['updraft']) && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'skip',
                    'detail' => 'No UpdraftPlus detected',
                ];
                continue;
            }

            if ($key === 'restore') {
                if (empty($meta['updraft'])) {
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'skip',
                        'detail' => 'No UpdraftPlus detected',
                    ];
                    continue;
                }
                if (empty($hits)) {
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'manual',
                        'detail' => 'No restore error option found — verify restores manually after a drill',
                    ];
                    continue;
                }
            }

            if ($key === 'deprecated' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'skip',
                    'detail' => 'No debug.log Deprecated hits (enable WP_DEBUG_LOG for better signal)',
                ];
                continue;
            }

            if ($key === 'unused_plugs' && empty($hits) && (int) ($meta['inactive_n'] ?? 0) > 0) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => (int) $meta['inactive_n'] . ' inactive (under threshold of 5)',
                ];
                continue;
            }

            if ($key === 'unused_themes' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => (int) ($meta['unused_t_n'] ?? 0) . ' extra theme(s) (threshold 4)',
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

    private function debug_log_path(): ?string {
        if (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== '' && WP_DEBUG_LOG !== '1') {
            return WP_DEBUG_LOG;
        }
        $default = WP_CONTENT_DIR . '/debug.log';
        return file_exists($default) ? $default : null;
    }

    /**
     * @return array<int, string>
     */
    private function candidate_log_files(): array {
        $paths = [
            WP_CONTENT_DIR . '/debug.log',
            ABSPATH . 'debug.log',
            ABSPATH . 'error_log',
            WP_CONTENT_DIR . '/error_log',
        ];
        if (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== '' && WP_DEBUG_LOG !== '1') {
            $paths[] = WP_DEBUG_LOG;
        }
        return array_values(array_unique($paths));
    }

    private function tail_file(string $path, int $bytes): string {
        $size = @filesize($path);
        if ($size === false || $size === 0) {
            return '';
        }
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return '';
        }
        $start = max(0, $size - $bytes);
        fseek($fh, $start);
        $data = (string) fread($fh, $bytes);
        fclose($fh);
        return $data;
    }

    /**
     * Sample upload files missing from the media library.
     *
     * @param int $limit Max files to scan.
     * @return array<int, string> Basenames.
     */
    private function sample_orphaned_uploads(int $limit): array {
        global $wpdb;
        $upload = wp_upload_dir();
        if (! empty($upload['error']) || empty($upload['basedir'])) {
            return [];
        }
        $dir = trailingslashit($upload['basedir']) . gmdate('Y/m');
        if (! is_dir($dir)) {
            // Fall back to basedir listing (non-recursive, few files).
            $dir = (string) $upload['basedir'];
        }
        $files = @scandir($dir);
        if (! is_array($files)) {
            return [];
        }

        $orphans = [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            if (! is_file($path)) {
                continue;
            }
            // Skip thumbnails.
            if (preg_match('/-\d+x\d+\.(jpe?g|png|gif|webp)$/i', $file)) {
                continue;
            }
            if (! preg_match('/\.(jpe?g|png|gif|webp|pdf|mp4|zip)$/i', $file)) {
                continue;
            }

            $like = '%' . $wpdb->esc_like($file) . '%';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $found = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s",
                $like
            ));
            if ($found === 0) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $found = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
                    $like
                ));
            }
            if ($found === 0) {
                $orphans[] = $file;
            }
            if (count($orphans) >= 5) {
                break;
            }
            if (--$limit <= 0) {
                break;
            }
        }

        return $orphans;
    }
}
