<?php
/**
 * Hosting health from docs/Hosting.md — line coverage.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;
use Matrix_Site_Monitor\Settings;
use Matrix_Site_Monitor\Storage;

defined('ABSPATH') || exit;

class Check_Hosting extends Check_Base {
    public function id(): string { return 'hosting'; }
    public function label(): string { return 'Hosting health'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        global $wpdb;
        $start    = microtime(true);
        $settings = Settings::get();

        $findings = [
            'disk'         => [],
            'php_memory'   => [],
            'php_version'  => [],
            'mysql'        => [],
            'redis'        => [],
            'object_cache' => [],
            'opcache'      => [],
            'cron'         => [],
            'email'        => [],
            'ssl'          => [],
            'domain'       => [],
            'backup'       => [],
        ];

        // Disk nearly full.
        $snapshot = Check_Disk_Space::get_snapshot(ABSPATH);
        if ((float) ($snapshot['total_bytes'] ?? 0) <= 0) {
            // Leave empty → skip in coverage.
        } elseif (Check_Disk_Space::is_below_threshold($snapshot, $settings)) {
            $findings['disk'][] = sprintf(
                '%s free (%s%%)',
                Storage::format_bytes((float) $snapshot['free_bytes']),
                round((float) $snapshot['free_percent'], 1)
            );
        }

        // PHP memory exhausted / limit low.
        $limit = (string) ini_get('memory_limit');
        $bytes = $this->to_bytes($limit);
        $peak  = memory_get_peak_usage(true);
        if ($bytes > 0 && $bytes < 128 * 1024 * 1024) {
            $findings['php_memory'][] = 'memory_limit is ' . $limit . ' (below 128M)';
        }
        if ($bytes > 0 && $peak > ($bytes * 0.9)) {
            $findings['php_memory'][] = sprintf(
                'Peak usage %s is >90%% of limit %s (exhaustion risk)',
                size_format($peak),
                $limit
            );
        }

        // PHP version outdated.
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $findings['php_version'][] = 'PHP ' . PHP_VERSION . ' is below 8.0 (EOL risk)';
        } elseif (version_compare(PHP_VERSION, '8.1', '<')) {
            $findings['php_version'][] = 'PHP ' . PHP_VERSION . ' is below 8.1 (consider upgrading)';
        }

        // MySQL down.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ping = $wpdb->get_var('SELECT 1');
        if ($ping !== '1' && $ping !== 1) {
            $findings['mysql'][] = 'SELECT 1 failed' . (! empty($wpdb->last_error) ? ': ' . $wpdb->last_error : '');
        } elseif (! empty($wpdb->last_error)) {
            $findings['mysql'][] = $wpdb->last_error;
        }

        // Redis unavailable / object cache failing.
        $dropin = WP_CONTENT_DIR . '/object-cache.php';
        $expect_cache = file_exists($dropin) || defined('WP_REDIS_HOST') || defined('WP_CACHE_KEY_SALT');
        if (defined('WP_REDIS_HOST') || class_exists('Redis', false) || extension_loaded('redis')) {
            $redis_result = $this->probe_redis();
            if ($redis_result !== null && $redis_result !== true) {
                $findings['redis'][] = is_string($redis_result) ? $redis_result : 'Redis probe failed';
            }
        }
        if (file_exists($dropin)) {
            if (! wp_using_ext_object_cache()) {
                $findings['object_cache'][] = 'object-cache.php exists but external object cache is not active';
            } else {
                $key = 'msm_hosting_oc_' . wp_generate_password(6, false);
                wp_cache_set($key, 'ok', 'msm', 60);
                $value = wp_cache_get($key, 'msm');
                wp_cache_delete($key, 'msm');
                if ($value !== 'ok') {
                    $findings['object_cache'][] = 'Object cache set/get probe failed';
                    $findings['redis'][]         = 'Object cache probe failed (may be Redis/Memcached)';
                }
            }
        } elseif ($expect_cache && ! file_exists($dropin)) {
            $findings['object_cache'][] = 'Cache constants set but no object-cache.php drop-in';
        }

        // OPCache disabled.
        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            if ($status === false || empty($status['opcache_enabled'])) {
                $findings['opcache'][] = 'OPCache is disabled';
            }
        } elseif (function_exists('ini_get') && ini_get('opcache.enable') === '0') {
            $findings['opcache'][] = 'opcache.enable is 0';
        } else {
            // Extension missing — soft skip via empty + mode partial.
            $findings['opcache'] = []; // leave empty; coverage marks skip if no extension
        }
        $opcache_available = function_exists('opcache_get_status')
            || (function_exists('ini_get') && ini_get('opcache.enable') !== false && ini_get('opcache.enable') !== '');

        // Cron disabled.
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $findings['cron'][] = 'DISABLE_WP_CRON is true';
        }
        $cron = _get_cron_array();
        if (is_array($cron)) {
            $now     = time();
            $overdue = 0;
            foreach ($cron as $timestamp => $hooks) {
                if ($timestamp < ($now - DAY_IN_SECONDS)) {
                    $overdue += count((array) $hooks);
                }
            }
            if ($overdue > 0) {
                $findings['cron'][] = $overdue . ' overdue cron event(s)';
            }
        }

        // Email server unavailable — no send; SMTP / socket heuristic.
        if (! $this->smtp_plugin_active()) {
            $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
            if (preg_match('/localhost|\\.local$|\\.test$/i', $host)) {
                $findings['email'][] = 'No SMTP plugin on local host — mail() often fails';
            }
            // Soft: try connecting to common SMTP on localhost only if filter allows — skip by default.
        }

        // SSL expiring.
        if (is_ssl()) {
            $host   = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
            $expiry = $this->cert_expiry($host);
            if ($expiry === null) {
                $findings['ssl'][] = 'Could not read SSL certificate';
            } else {
                $days = (int) floor(($expiry - time()) / DAY_IN_SECONDS);
                $warn = (int) ($settings['ssl_warn_days'] ?? 14);
                if ($days < 0) {
                    $findings['ssl'][] = 'Certificate expired';
                } elseif ($days <= $warn) {
                    $findings['ssl'][] = sprintf('Expires in %d day(s)', $days);
                }
            }
        }

        // Domain expiry — WHOIS not reliable in PHP without deps; manual unless filter provides.
        $domain_detail = apply_filters('msm_hosting_domain_expiry', null);
        if (is_string($domain_detail) && $domain_detail !== '') {
            $findings['domain'][] = $domain_detail;
        }

        // Backup failures.
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (is_plugin_active('updraftplus/updraftplus.php')) {
            $last = get_option('updraft_last_backup');
            if (! is_array($last) || empty($last['backup_time'])) {
                $findings['backup'][] = 'UpdraftPlus has no recorded backup';
            } else {
                $age_days = (time() - (int) $last['backup_time']) / DAY_IN_SECONDS;
                if ($age_days > 7) {
                    $findings['backup'][] = sprintf('Last UpdraftPlus backup was %.1f days ago', $age_days);
                }
            }
        }

        $coverage = $this->build_coverage($findings, [
            'disk_measurable'    => (float) ($snapshot['total_bytes'] ?? 0) > 0,
            'opcache_available'  => $opcache_available,
            'object_cache_dropin'=> file_exists($dropin),
            'smtp'               => $this->smtp_plugin_active(),
            'ssl'                => is_ssl(),
            'backup_plugin'      => is_plugin_active('updraftplus/updraftplus.php'),
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

        return $this->pass('Hosting health sample OK.', $start, $extra);
    }

    /**
     * @return array<int, array{line:string,mode:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'Disk nearly full', 'mode' => 'automated'],
            ['line' => 'PHP memory exhausted', 'mode' => 'automated'],
            ['line' => 'PHP version outdated', 'mode' => 'automated'],
            ['line' => 'MySQL down', 'mode' => 'automated'],
            ['line' => 'Redis unavailable', 'mode' => 'partial'],
            ['line' => 'Object cache failing', 'mode' => 'automated'],
            ['line' => 'OPCache disabled', 'mode' => 'automated'],
            ['line' => 'Cron disabled', 'mode' => 'automated'],
            ['line' => 'Email server unavailable', 'mode' => 'partial'],
            ['line' => 'SSL expiring', 'mode' => 'automated'],
            ['line' => 'Domain expiry approaching', 'mode' => 'manual'],
            ['line' => 'Backup failures', 'mode' => 'partial'],
        ];
    }

    /**
     * @param array<string, array<int, string>> $findings Findings.
     * @param array<string, mixed>              $meta     Context flags.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function build_coverage(array $findings, array $meta): array {
        $coverage = [];
        foreach (self::doc_lines() as $row) {
            $line = $row['line'];
            $mode = $row['mode'];

            switch ($line) {
                case 'Disk nearly full':
                    if (empty($meta['disk_measurable'])) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'skip',
                            'detail' => 'Disk space could not be measured on this host',
                        ];
                        break;
                    }
                    $hits = $findings['disk'];
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits) ? 'Free space above threshold' : implode('; ', $hits),
                    ];
                    break;

                case 'PHP memory exhausted':
                    $hits = $findings['php_memory'];
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits)
                            ? 'memory_limit OK (peak under 90%)'
                            : implode('; ', $hits),
                    ];
                    break;

                case 'PHP version outdated':
                    $hits = $findings['php_version'];
                    // Soft-fail only for <8.0; 8.0–8.1 is skip/info.
                    $hard = array_filter($hits, static function (string $h): bool {
                        return strpos($h, 'below 8.0') !== false;
                    });
                    if ($hard) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'fail',
                            'detail' => implode('; ', $hits),
                        ];
                    } elseif ($hits) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'skip',
                            'detail' => implode('; ', $hits),
                        ];
                    } else {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'pass',
                            'detail' => 'PHP ' . PHP_VERSION,
                        ];
                    }
                    break;

                case 'MySQL down':
                    $hits = $findings['mysql'];
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits) ? 'SELECT 1 OK' : implode('; ', $hits),
                    ];
                    break;

                case 'Redis unavailable':
                    $hits = $findings['redis'];
                    if (empty($hits) && ! defined('WP_REDIS_HOST') && ! extension_loaded('redis')) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'skip',
                            'detail' => 'Redis not configured (no WP_REDIS_HOST / extension)',
                        ];
                    } else {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode,
                            'status' => empty($hits) ? 'pass' : 'fail',
                            'detail' => empty($hits) ? 'Redis probe OK or not required' : implode('; ', $hits),
                        ];
                    }
                    break;

                case 'Object cache failing':
                    $hits = $findings['object_cache'];
                    if (empty($meta['object_cache_dropin']) && empty($hits)) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'skip',
                            'detail' => 'No object-cache.php drop-in',
                        ];
                    } else {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode,
                            'status' => empty($hits) ? 'pass' : 'fail',
                            'detail' => empty($hits) ? 'Object cache active and responding' : implode('; ', $hits),
                        ];
                    }
                    break;

                case 'OPCache disabled':
                    if (empty($meta['opcache_available'])) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'skip',
                            'detail' => 'OPCache extension not available in this SAPI',
                        ];
                        break;
                    }
                    $hits = $findings['opcache'];
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits) ? 'OPCache enabled' : implode('; ', $hits),
                    ];
                    break;

                case 'Cron disabled':
                    $hits = $findings['cron'];
                    // DISABLE_WP_CRON alone → skip; overdue → fail.
                    $only_flag = count($hits) === 1 && strpos($hits[0], 'DISABLE_WP_CRON') !== false;
                    if ($only_flag) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'skip',
                            'detail' => $hits[0] . ' (OK if system cron is configured)',
                        ];
                    } else {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode,
                            'status' => empty($hits) ? 'pass' : 'fail',
                            'detail' => empty($hits) ? 'WP-Cron not disabled / not overdue' : implode('; ', $hits),
                        ];
                    }
                    break;

                case 'Email server unavailable':
                    $hits = $findings['email'];
                    if (! empty($hits)) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'fail',
                            'detail' => implode('; ', $hits),
                        ];
                    } elseif (! empty($meta['smtp'])) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'pass',
                            'detail' => 'SMTP plugin detected (delivery not verified with a test send)',
                        ];
                    } else {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'skip',
                            'detail' => 'No SMTP plugin detected — verify delivery manually',
                        ];
                    }
                    break;

                case 'SSL expiring':
                    if (empty($meta['ssl'])) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'skip',
                            'detail' => 'Site not served over HTTPS',
                        ];
                        break;
                    }
                    $hits = $findings['ssl'];
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode,
                        'status' => empty($hits) ? 'pass' : 'fail',
                        'detail' => empty($hits) ? 'Certificate within warn window' : implode('; ', $hits),
                    ];
                    break;

                case 'Domain expiry approaching':
                    $hits = $findings['domain'];
                    if (! empty($hits)) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'fail',
                            'detail' => implode('; ', $hits),
                        ];
                    } else {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'manual',
                            'detail' => 'Use registrar / orchestrator WHOIS (or msm_hosting_domain_expiry filter)',
                        ];
                    }
                    break;

                case 'Backup failures':
                    $hits = $findings['backup'];
                    if (empty($meta['backup_plugin']) && empty($hits)) {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode, 'status' => 'skip',
                            'detail' => 'No supported backup plugin (UpdraftPlus) detected',
                        ];
                    } else {
                        $coverage[] = [
                            'line' => $line, 'mode' => $mode,
                            'status' => empty($hits) ? 'pass' : 'fail',
                            'detail' => empty($hits) ? 'Recent UpdraftPlus backup recorded' : implode('; ', $hits),
                        ];
                    }
                    break;

                default:
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'manual', 'detail' => 'Unhandled',
                    ];
                    break;
            }
        }

        return $coverage;
    }

    /**
     * @return true|string|null true OK, string error, null not applicable.
     */
    private function probe_redis() {
        if (! defined('WP_REDIS_HOST')) {
            return null;
        }
        if (! class_exists('Redis', false) && ! extension_loaded('redis')) {
            return 'WP_REDIS_HOST set but Redis PHP extension missing';
        }
        if (! class_exists('Redis', false)) {
            return 'Redis class unavailable';
        }
        try {
            $redis = new \Redis();
            $host  = (string) WP_REDIS_HOST;
            $port  = defined('WP_REDIS_PORT') ? (int) WP_REDIS_PORT : 6379;
            $ok    = @$redis->connect($host, $port, 1.5);
            if (! $ok) {
                return 'Cannot connect to Redis at ' . $host . ':' . $port;
            }
            $pong = $redis->ping();
            $redis->close();
            if ($pong !== true && $pong !== '+PONG' && $pong !== 'PONG') {
                return 'Redis PING failed';
            }
            return true;
        } catch (\Throwable $e) {
            return 'Redis error: ' . $e->getMessage();
        }
    }

    private function smtp_plugin_active(): bool {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $candidates = [
            'wp-mail-smtp/wp_mail_smtp.php',
            'wp-mail-smtp-pro/wp_mail_smtp.php',
            'easy-wp-smtp/easy-wp-smtp.php',
            'post-smtp/postman-smtp.php',
            'fluent-smtp/fluent-smtp.php',
        ];
        foreach ($candidates as $plugin) {
            if (is_plugin_active($plugin)) {
                return true;
            }
        }
        return (bool) apply_filters('msm_forms_smtp_detected', false);
    }

    private function cert_expiry(string $host): ?int {
        if ($host === '') {
            return null;
        }
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer'       => false,
                'verify_peer_name'  => false,
            ],
        ]);
        $stream = @stream_socket_client(
            'ssl://' . $host . ':443',
            $errno,
            $errstr,
            8,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (! $stream) {
            return null;
        }
        $params = stream_context_get_params($stream);
        fclose($stream);
        if (empty($params['options']['ssl']['peer_certificate'])) {
            return null;
        }
        $info = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        return isset($info['validTo_time_t']) ? (int) $info['validTo_time_t'] : null;
    }

    private function to_bytes(string $value): int {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $unit = strtolower(substr($value, -1));
        $num  = (int) $value;
        switch ($unit) {
            case 'g':
                return $num * 1024 * 1024 * 1024;
            case 'm':
                return $num * 1024 * 1024;
            case 'k':
                return $num * 1024;
            default:
                return (int) $value;
        }
    }
}
