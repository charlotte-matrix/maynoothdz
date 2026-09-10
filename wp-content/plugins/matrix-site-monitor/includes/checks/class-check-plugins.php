<?php
/**
 * Plugin health from docs/Plugin.md — line coverage.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;
use Matrix_Site_Monitor\Storage;

defined('ABSPATH') || exit;

class Check_Plugins extends Check_Base {
    public function id(): string { return 'plugins'; }
    public function label(): string { return 'Plugin health'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);

        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (! function_exists('wp_update_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }

        wp_update_plugins();

        global $wp_version;
        $all    = get_plugins();
        $active = [];
        foreach ($all as $file => $data) {
            if (is_plugin_active($file)) {
                $active[$file] = $data;
            }
        }

        $findings = [
            'conflicts'    => [],
            'js_conflicts' => [],
            'fatals'       => [],
            'deprecated'   => [],
            'incompatible' => [],
            'abandoned'    => [],
            'licence'      => [],
            'update_fail'  => [],
            'autoupdate'   => [],
        ];

        // Known conflict groups (2+ active in a group → conflict risk).
        $groups = [
            'Page cache' => [
                'wp-super-cache/wp-cache.php',
                'w3-total-cache/w3-total-cache.php',
                'wp-rocket/wp-rocket.php',
                'litespeed-cache/litespeed-cache.php',
                'cache-enabler/cache-enabler.php',
                'comet-cache/comet-cache.php',
            ],
            'SEO' => [
                'wordpress-seo/wp-seo.php',
                'seo-by-rank-math/rank-math.php',
                'all-in-one-seo-pack/all_in_one_seo_pack.php',
                'the-seo-framework/the-seo-framework.php',
                'wp-seopress/seopress.php',
            ],
            'Security suite' => [
                'wordfence/wordfence.php',
                'sucuri-scanner/sucuri.php',
                'better-wp-security/better-wp-security.php',
                'all-in-one-wp-security-and-firewall/wp-security.php',
            ],
            'Page builder' => [
                'elementor/elementor.php',
                'js_composer/js_composer.php',
                'beaver-builder-lite-version/fl-builder.php',
                'divi-builder/divi-builder.php',
            ],
        ];
        $groups = apply_filters('msm_plugin_conflict_groups', $groups);
        foreach ($groups as $label => $files) {
            $hit = [];
            foreach ($files as $file) {
                if (isset($active[$file]) || is_plugin_active($file)) {
                    $hit[] = $all[$file]['Name'] ?? $file;
                }
            }
            if (count($hit) >= 2) {
                $findings['conflicts'][] = $label . ': ' . implode(' + ', $hit);
            }
        }

        // JS conflicts — multiple jQuery on homepage (same heuristic as javascript check).
        $home = wp_remote_get(home_url('/'), [
            'timeout'   => 15,
            'sslverify' => false,
            'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        if (! is_wp_error($home)) {
            $html = (string) wp_remote_retrieve_body($home);
            $jquery = 0;
            if (preg_match_all('#<script[^>]+src=["\']([^"\']+)["\']#i', $html, $sm)) {
                foreach ($sm[1] as $src) {
                    if (preg_match('#jquery[-.]migrate|jquery-ui|jquery\.ui#i', $src)) {
                        continue;
                    }
                    if (preg_match('#/(?:jquery(?:\.min)?(?:-\d[\d.]*)?|jquery\.js)#i', $src)) {
                        $jquery++;
                    }
                }
            }
            if ($jquery > 1) {
                $findings['js_conflicts'][] = $jquery . ' jQuery core scripts on homepage';
            }
        }

        // Fatal errors after update — stored fatals mentioning plugins/ or recent fatals.
        $fatals = Storage::get_fatal_errors();
        $since  = time() - WEEK_IN_SECONDS;
        foreach ($fatals as $error) {
            $at = isset($error['occurred_at']) ? strtotime((string) $error['occurred_at']) : 0;
            if ($at < $since) {
                continue;
            }
            $msg = (string) ($error['message'] ?? '');
            $file = (string) ($error['file'] ?? '');
            if (stripos($file, 'wp-content/plugins/') !== false || stripos($msg, 'plugins/') !== false) {
                $findings['fatals'][] = wp_basename($file) . ': ' . wp_html_excerpt($msg, 80);
            }
        }
        $findings['fatals'] = array_slice($findings['fatals'], 0, 5);

        // Deprecated in debug.log mentioning plugins.
        $log = $this->debug_log_path();
        if ($log && is_readable($log)) {
            $tail = $this->tail_file($log, 64 * 1024);
            if ($tail !== '' && preg_match('/Deprecated.*(wp-content\/plugins\/[^\s:]+)/i', $tail, $m)) {
                $findings['deprecated'][] = 'Deprecated in debug.log: ' . $m[1];
            } elseif ($tail !== '' && preg_match('/\bDeprecated\b/i', $tail)) {
                $findings['deprecated'][] = 'Deprecated messages in debug.log (may include plugins)';
            }
        }

        // Incompatible versions — RequiresPHP / RequiresWP / Tested up to.
        foreach ($active as $file => $data) {
            $name = (string) ($data['Name'] ?? $file);
            $req_php = (string) ($data['RequiresPHP'] ?? '');
            $req_wp  = (string) ($data['RequiresWP'] ?? '');
            $tested  = trim((string) ($data['Tested'] ?? ''));
            if ($req_php !== '' && version_compare(PHP_VERSION, $req_php, '<')) {
                $findings['incompatible'][] = $name . ' needs PHP ' . $req_php;
            }
            if ($req_wp !== '' && version_compare($wp_version, $req_wp, '<')) {
                $findings['incompatible'][] = $name . ' needs WP ' . $req_wp;
            }
            if ($tested !== '' && version_compare($tested, $wp_version, '<')) {
                // Soft: not tested ≠ incompatible — collect separately as skip-level unless far behind.
                $major_tested = (float) $tested;
                $major_wp     = (float) $wp_version;
                if ($major_wp - $major_tested >= 1.0) {
                    $findings['incompatible'][] = $name . ' tested only up to WP ' . $tested;
                }
            }
        }
        $findings['incompatible'] = array_slice($findings['incompatible'], 0, 6);

        // Abandoned — last_updated older than 2 years from update transient / API sample.
        $updates = get_site_transient('update_plugins');
        if (! is_object($updates)) {
            $updates = (object) ['response' => [], 'no_update' => []];
        }
        $checked = [];
        foreach ([(array) ($updates->response ?? []), (array) ($updates->no_update ?? [])] as $bucket) {
            foreach ($bucket as $file => $info) {
                if (! is_plugin_active((string) $file)) {
                    continue;
                }
                $last = '';
                if (is_object($info) && ! empty($info->last_updated)) {
                    $last = (string) $info->last_updated;
                } elseif (is_array($info) && ! empty($info['last_updated'])) {
                    $last = (string) $info['last_updated'];
                }
                if ($last === '') {
                    continue;
                }
                $ts = strtotime($last);
                if ($ts && $ts < (time() - 2 * YEAR_IN_SECONDS)) {
                    $name = $all[$file]['Name'] ?? $file;
                    $findings['abandoned'][] = $name . ' last updated ' . gmdate('Y-m-d', $ts);
                    $checked[$file] = true;
                }
            }
        }
        // Sample up to 5 active .org plugins without last_updated via plugins_api (capped).
        $sample = 0;
        foreach ($active as $file => $data) {
            if ($sample >= 5) {
                break;
            }
            if (isset($checked[$file])) {
                continue;
            }
            $slug = dirname($file);
            if ($slug === '.' || strpos($file, '/') === false) {
                continue;
            }
            // Skip obvious premium (Update URI / no wp.org).
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $file, false, false);
            $update_uri  = (string) ($plugin_data['UpdateURI'] ?? '');
            if ($update_uri !== '' && stripos($update_uri, 'wordpress.org') === false) {
                continue;
            }
            if (! function_exists('plugins_api')) {
                require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            }
            $api = plugins_api('plugin_information', [
                'slug'   => $slug,
                'fields' => [
                    'sections' => false,
                    'versions' => false,
                ],
            ]);
            $sample++;
            if (is_wp_error($api)) {
                // closed / not found can mean abandoned or premium.
                if ($api->get_error_code() === 'plugins_api_failed') {
                    continue;
                }
                continue;
            }
            if (! empty($api->last_updated)) {
                $ts = strtotime((string) $api->last_updated);
                if ($ts && $ts < (time() - 2 * YEAR_IN_SECONDS)) {
                    $findings['abandoned'][] = ($data['Name'] ?? $slug) . ' last updated ' . gmdate('Y-m-d', $ts);
                }
            }
        }
        $findings['abandoned'] = array_slice(array_unique($findings['abandoned']), 0, 5);

        // Missing licence — known premium option keys (best-effort).
        $licence_checks = apply_filters('msm_plugin_licence_checks', [
            [
                'plugin'  => 'elementor-pro/elementor-pro.php',
                'option'  => 'elementor_pro_license_key',
                'label'   => 'Elementor Pro',
            ],
            [
                'plugin'  => 'advanced-custom-fields-pro/acf.php',
                'option'  => 'acf_pro_license',
                'label'   => 'ACF Pro',
            ],
            [
                'plugin' => 'gravityforms/gravityforms.php',
                'option' => 'rg_gforms_key',
                'label'  => 'Gravity Forms',
            ],
        ]);
        foreach ($licence_checks as $check) {
            $plugin = (string) ($check['plugin'] ?? '');
            if ($plugin === '' || ! is_plugin_active($plugin)) {
                continue;
            }
            $label = (string) ($check['label'] ?? $plugin);
            if (! empty($check['callback']) && is_callable($check['callback'])) {
                // Callback returns true if licensed OK.
                if (! call_user_func($check['callback'])) {
                    $findings['licence'][] = $label . ' may be missing a valid licence';
                }
                continue;
            }
            $opt = (string) ($check['option'] ?? '');
            if ($opt === '') {
                continue;
            }
            $val = get_option($opt);
            if ($val === false || $val === '' || $val === null) {
                $findings['licence'][] = $label . ' licence key empty/missing';
            }
        }

        // Update failures — auto-update failures / empty packages.
        $failed = get_site_option('auto_plugin_update_failed', []);
        if (is_array($failed) && ! empty($failed)) {
            foreach (array_slice($failed, 0, 5, true) as $file => $info) {
                unset($info);
                $findings['update_fail'][] = ($all[$file]['Name'] ?? $file) . ' auto-update failed';
            }
        }
        if (! empty($updates->response) && is_array($updates->response)) {
            foreach ($updates->response as $file => $upd) {
                if (is_object($upd) && empty($upd->package) && ! empty($upd->id)) {
                    $findings['update_fail'][] = ($all[$file]['Name'] ?? $file) . ' update has no package URL (often licence)';
                }
            }
        }
        $findings['update_fail'] = array_slice(array_unique($findings['update_fail']), 0, 5);

        // Auto-updates disabled.
        if (defined('AUTOMATIC_UPDATER_DISABLED') && AUTOMATIC_UPDATER_DISABLED) {
            $findings['autoupdate'][] = 'AUTOMATIC_UPDATER_DISABLED is true';
        }
        if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) {
            $findings['autoupdate'][] = 'DISALLOW_FILE_MODS is true (blocks updates)';
        }
        $pending = ! empty($updates->response) ? count((array) $updates->response) : 0;
        $auto_on = count((array) get_site_option('auto_update_plugins', []));
        if ($pending >= 5 && $auto_on === 0 && empty($findings['autoupdate'])) {
            $findings['autoupdate'][] = $pending . ' plugin updates pending and no plugins opted into auto-updates';
        }

        $coverage = $this->build_coverage($findings, [
            'pending' => $pending,
            'auto_on' => $auto_on,
        ]);

        $failed_rows = array_filter($coverage, static function (array $r): bool {
            return ($r['status'] ?? '') === 'fail';
        });

        $extra = ['coverage' => $coverage];

        if ($failed_rows) {
            $msgs = [];
            foreach (array_slice(array_values($failed_rows), 0, 8) as $row) {
                $msgs[] = $row['line'] . (! empty($row['detail']) ? ': ' . $row['detail'] : '');
            }
            return $this->fail(implode('; ', $msgs) . '.', $start, $extra);
        }

        return $this->pass(
            sprintf('Plugin sample OK (%d active).', count($active)),
            $start,
            $extra
        );
    }

    /**
     * @return array<int, array{line:string,mode:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'Plugin conflicts', 'mode' => 'partial'],
            ['line' => 'JavaScript conflicts', 'mode' => 'partial'],
            ['line' => 'Fatal errors after update', 'mode' => 'partial'],
            ['line' => 'Deprecated PHP warnings', 'mode' => 'partial'],
            ['line' => 'Incompatible versions', 'mode' => 'automated'],
            ['line' => 'Plugin abandoned', 'mode' => 'partial'],
            ['line' => 'Missing licence', 'mode' => 'partial'],
            ['line' => 'Update failures', 'mode' => 'partial'],
            ['line' => 'Auto-updates disabled', 'mode' => 'automated'],
        ];
    }

    /**
     * @param array<string, array<int, string>> $findings Findings.
     * @param array<string, mixed>              $meta     Context.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function build_coverage(array $findings, array $meta): array {
        $map = [
            'Plugin conflicts'           => 'conflicts',
            'JavaScript conflicts'       => 'js_conflicts',
            'Fatal errors after update'  => 'fatals',
            'Deprecated PHP warnings'    => 'deprecated',
            'Incompatible versions'      => 'incompatible',
            'Plugin abandoned'           => 'abandoned',
            'Missing licence'            => 'licence',
            'Update failures'            => 'update_fail',
            'Auto-updates disabled'      => 'autoupdate',
        ];

        $pass = [
            'conflicts'    => 'No known conflict groups with 2+ plugins active',
            'js_conflicts' => 'No duplicate jQuery cores on homepage',
            'fatals'       => 'No recent plugin-related fatals stored',
            'deprecated'   => 'No Deprecated hits in debug.log tail',
            'incompatible' => 'RequiresPHP/WP look compatible',
            'abandoned'    => 'No active plugins detected as 2+ years stale',
            'licence'      => 'Known premium plugins have licence keys (or none active)',
            'update_fail'  => 'No recorded auto-update failures / empty packages',
            'autoupdate'   => 'Auto-updates not hard-disabled',
        ];

        $coverage = [];
        foreach (self::doc_lines() as $row) {
            $line = $row['line'];
            $mode = $row['mode'];
            $key  = $map[$line] ?? '';
            $hits = $key !== '' ? ($findings[$key] ?? []) : [];

            if ($key === 'deprecated' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'skip',
                    'detail' => 'No debug.log Deprecated signal (enable WP_DEBUG_LOG for better coverage)',
                ];
                continue;
            }

            if ($key === 'fatals' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => $pass['fatals'],
                ];
                continue;
            }

            if ($key === 'autoupdate' && ! empty($hits)) {
                // Only hard-fail constants; soft pending message is skip.
                $hard = array_filter($hits, static function (string $h): bool {
                    return strpos($h, 'AUTOMATIC_UPDATER_DISABLED') !== false
                        || strpos($h, 'DISALLOW_FILE_MODS') !== false;
                });
                if ($hard) {
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'fail',
                        'detail' => implode('; ', $hard),
                    ];
                } else {
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'skip',
                        'detail' => implode('; ', $hits) . ' (optional policy)',
                    ];
                }
                continue;
            }

            if ($key === 'licence' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => 'No missing licences among known checks (extend via msm_plugin_licence_checks)',
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

    private function tail_file(string $path, int $bytes): string {
        $size = @filesize($path);
        if ($size === false || $size === 0) {
            return '';
        }
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return '';
        }
        fseek($fh, max(0, $size - $bytes));
        $data = (string) fread($fh, $bytes);
        fclose($fh);
        return $data;
    }
}
