<?php
/**
 * General site health from docs/general.md — line coverage.
 *
 * Lightweight homepage + capped inner-page sample (not a full crawl).
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;
use Matrix_Site_Monitor\Fatal_Error_Logger;
use Matrix_Site_Monitor\Settings;
use Matrix_Site_Monitor\Storage;

defined('ABSPATH') || exit;

class Check_General extends Check_Base {
    public function id(): string { return 'general'; }
    public function label(): string { return 'General site health'; }
    public function severity(): string { return 'critical'; }
    public function tier(): string { return 'light'; }

    public function run(): array {
        $start = microtime(true);

        $findings = [
            'wsod'            => [],
            'http_500'        => [],
            'db_error'        => [],
            'critical'        => [],
            'redirect_loop'   => [],
            'maintenance'     => [],
            'inner_fail'      => [],
            'permalinks'      => [],
            'mixed_content'   => [],
            'ssl'             => [],
            'dns'             => [],
            'domain'          => [],
            'php_fatal'       => [],
            'php_display'     => [],
            'missing_images'  => [],
            'permissions'     => [],
            'uploads'         => [],
            'media'           => [],
            'cron'            => [],
            'scheduled'       => [],
        ];

        $home_url = home_url('/');
        $home     = $this->fetch($home_url);
        $home_ok  = $home['ok'];

        if ($home['redirect_loop']) {
            $findings['redirect_loop'][] = 'Home: ' . $home['error'];
        }
        if ($home['code'] === 500) {
            $findings['http_500'][] = 'Home HTTP 500';
        }
        if ($home['code'] >= 500 && $home['code'] !== 500) {
            $findings['http_500'][] = 'Home HTTP ' . $home['code'];
        }
        if ($home_ok && $home['body'] !== '' && strlen(trim(wp_strip_all_tags($home['body']))) < 20) {
            $findings['wsod'][] = 'Homepage returned near-empty body (possible WSOD)';
        }
        if (! $home_ok && $home['code'] === 0 && $home['error'] !== '' && ! $home['redirect_loop']) {
            // Transport failure can be WSOD-ish / down.
            if (stripos($home['error'], 'resolve') !== false || stripos($home['error'], 'Could not resolve') !== false) {
                $findings['domain'][] = $home['error'];
            }
        }
        if ($home['body'] !== '') {
            if (preg_match('/Error establishing a database connection/i', $home['body'])) {
                $findings['db_error'][] = 'Homepage shows database connection error';
            }
            if (preg_match('/There has been a critical error|Fatal error|Parse error/i', $home['body'])) {
                $findings['critical'][] = 'Homepage shows critical/fatal error output';
                $findings['php_fatal'][] = 'Fatal/critical error text in homepage HTML';
            }
            if (preg_match('/Briefly unavailable for scheduled maintenance|site is undergoing maintenance/i', $home['body'])) {
                $findings['maintenance'][] = 'Homepage shows maintenance message';
            }
            if (preg_match('/\b(Warning|Notice|Deprecated)\b\s*:/i', $home['body'])) {
                $findings['php_display'][] = 'PHP Warning/Notice/Deprecated visible in homepage HTML';
            }
            if (is_ssl()) {
                if (preg_match_all('#\b(?:src|href)=["\'](http://[^"\']+)#i', $home['body'], $mm)) {
                    $mixed = [];
                    foreach ($mm[1] as $asset) {
                        if (stripos($asset, 'http://schemas.') !== false || stripos($asset, 'http://www.w3.org') !== false) {
                            continue;
                        }
                        $mixed[] = $asset;
                    }
                    $mixed = array_slice(array_unique($mixed), 0, 5);
                    if ($mixed) {
                        $findings['mixed_content'][] = implode('; ', $mixed);
                    }
                }
            }
        }

        // .maintenance file.
        if (file_exists(ABSPATH . '.maintenance')) {
            $age = time() - (int) filemtime(ABSPATH . '.maintenance');
            $findings['maintenance'][] = sprintf('.maintenance file present (age %d min)', (int) floor($age / 60));
        }

        // Inner pages sample.
        $inner = $this->sample_inner_urls(4);
        $inner_fail_count = 0;
        foreach ($inner as $label => $url) {
            $res = $this->fetch($url);
            if ($res['redirect_loop']) {
                $findings['redirect_loop'][] = $label . ': ' . $res['error'];
            }
            if ($res['code'] === 404) {
                $findings['permalinks'][] = $label . ' → HTTP 404 (' . $url . ')';
            }
            if ($res['code'] >= 500) {
                $findings['http_500'][] = $label . ' → HTTP ' . $res['code'];
                $inner_fail_count++;
            } elseif (! $res['ok']) {
                $inner_fail_count++;
                if (preg_match('/critical error|Fatal error/i', $res['body'])) {
                    $findings['critical'][] = $label . ' critical error in output';
                }
            }
            if (preg_match('/Error establishing a database connection/i', $res['body'])) {
                $findings['db_error'][] = $label . ' DB connection error';
            }
        }
        if ($home_ok && $inner_fail_count > 0) {
            $findings['inner_fail'][] = sprintf(
                'Home OK but %d/%d inner page(s) failed',
                $inner_fail_count,
                count($inner)
            );
        }

        // Direct DB ping (if home somehow still works via cache, still useful).
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->get_var('SELECT 1');
        if (! empty($wpdb->last_error)) {
            $findings['db_error'][] = 'wpdb error: ' . $wpdb->last_error;
        }

        // SSL expiry.
        if (is_ssl()) {
            $host = (string) wp_parse_url($home_url, PHP_URL_HOST);
            $expiry = $this->cert_expiry($host);
            if ($expiry !== null) {
                $days = (int) floor(($expiry - time()) / DAY_IN_SECONDS);
                $warn = (int) (Settings::get()['ssl_warn_days'] ?? 14);
                if ($days < 0) {
                    $findings['ssl'][] = 'Certificate expired';
                } elseif ($days <= $warn) {
                    $findings['ssl'][] = sprintf('Expires in %d day(s)', $days);
                }
            } else {
                $findings['ssl'][] = 'Could not read certificate';
            }
        }

        // DNS / domain resolve.
        $host = (string) wp_parse_url($home_url, PHP_URL_HOST);
        if ($host !== '') {
            $ip = gethostbyname($host);
            if ($ip === $host || $ip === '' || $ip === '0.0.0.0') {
                $findings['dns'][]    = 'gethostbyname failed for ' . $host;
                $findings['domain'][] = 'Domain does not resolve: ' . $host;
            }
        }

        // Stored fatals (ignore WP-CLI eval noise).
        $fatals = Storage::get_fatal_errors();
        $since  = time() - WEEK_IN_SECONDS;
        foreach ($fatals as $error) {
            if (! is_array($error) || Fatal_Error_Logger::is_noise($error)) {
                continue;
            }
            $at = isset($error['occurred_at']) ? strtotime((string) $error['occurred_at']) : 0;
            if ($at >= $since) {
                $findings['php_fatal'][] = (string) ($error['message'] ?? 'fatal');
                break;
            }
        }

        // Public error display: WP_DEBUG_DISPLAY on live QA; php.ini display_errors only off-local.
        if (! $this->is_dev_environment()) {
            $debug          = defined('WP_DEBUG') && WP_DEBUG;
            $debug_display  = defined('WP_DEBUG_DISPLAY') ? (bool) WP_DEBUG_DISPLAY : true;
            $display_errors = (string) ini_get('display_errors');
            $ini_on         = ($display_errors === '1' || strtolower($display_errors) === 'on');
            if ($debug && $debug_display) {
                $findings['php_display'][] = 'WP_DEBUG + WP_DEBUG_DISPLAY enabled';
            }
            if ($ini_on && ! Settings::wp_environment_is_dev()) {
                $findings['php_display'][] = 'PHP display_errors is On';
            }
        }

        // Missing images / media — sample from homepage + one attachment.
        if ($home['body'] !== '') {
            if (preg_match_all('#<img[^>]+src=["\']([^"\']+)#i', $home['body'], $im)) {
                foreach (array_slice(array_unique($im[1]), 0, 5) as $src) {
                    $src = html_entity_decode($src, ENT_QUOTES);
                    if (strpos($src, 'data:') === 0) {
                        continue;
                    }
                    if (strpos($src, '//') === 0) {
                        $src = (is_ssl() ? 'https:' : 'http:') . $src;
                    } elseif (strpos($src, '/') === 0) {
                        $src = home_url($src);
                    }
                    if (! preg_match('#^https?://#i', $src)) {
                        continue;
                    }
                    $code = $this->probe_code($src);
                    if ($code === 404 || ($code !== null && $code >= 500)) {
                        $findings['missing_images'][] = $src . ' (HTTP ' . $code . ')';
                    }
                }
            }
        }
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 3,
            'fields'         => 'ids',
        ]);
        foreach ($attachments as $aid) {
            $url = wp_get_attachment_url((int) $aid);
            if (! $url) {
                $findings['media'][] = 'Attachment ' . $aid . ' has no URL';
                continue;
            }
            $code = $this->probe_code($url);
            if ($code === 404 || ($code !== null && $code >= 500)) {
                $findings['media'][] = $url . ' (HTTP ' . $code . ')';
                $findings['missing_images'][] = $url . ' (HTTP ' . $code . ')';
            }
        }

        // Uploads / permissions.
        $upload = wp_upload_dir();
        if (! empty($upload['error'])) {
            $findings['uploads'][] = (string) $upload['error'];
            $findings['media'][]   = (string) $upload['error'];
        } else {
            $basedir = (string) ($upload['basedir'] ?? '');
            if ($basedir !== '' && ! is_writable($basedir)) {
                $findings['uploads'][]     = 'Uploads directory not writable: ' . $basedir;
                $findings['permissions'][] = 'Uploads dir not writable';
            }
        }
        if (defined('ABSPATH') && ! is_readable(ABSPATH . 'wp-config.php') && file_exists(ABSPATH . 'wp-config.php')) {
            $findings['permissions'][] = 'wp-config.php not readable';
        }

        // Cron.
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $findings['cron'][] = 'DISABLE_WP_CRON is true — ensure system cron hits wp-cron.php';
        }
        $cron = _get_cron_array();
        if (is_array($cron)) {
            $now = time();
            $overdue = 0;
            foreach ($cron as $timestamp => $hooks) {
                if ($timestamp < ($now - DAY_IN_SECONDS)) {
                    $overdue += count((array) $hooks);
                }
            }
            if ($overdue > 0) {
                $findings['cron'][] = $overdue . ' cron event(s) overdue >24h';
            }
        }

        // Scheduled posts stuck.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stuck = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_status = 'future' AND post_date_gmt < %s",
            gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS)
        ));
        if ($stuck > 0) {
            $findings['scheduled'][] = $stuck . ' future post(s) past due (cron may be stuck)';
            if (empty($findings['cron'])) {
                $findings['cron'][] = 'Scheduled posts overdue suggests cron not publishing';
            }
        }

        $coverage = $this->build_coverage($findings);
        $failed   = array_filter($coverage, static function (array $r): bool {
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

        return $this->pass('General site health sample OK.', $start, $extra);
    }

    /**
     * @return array<int, array{line:string,mode:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'White Screen of Death (WSOD)', 'mode' => 'partial'],
            ['line' => 'Internal Server Error (500)', 'mode' => 'automated'],
            ['line' => 'Error Establishing Database Connection', 'mode' => 'automated'],
            ['line' => 'Critical Error on Website', 'mode' => 'automated'],
            ['line' => 'Infinite redirect loop', 'mode' => 'automated'],
            ['line' => 'Site stuck in maintenance mode', 'mode' => 'automated'],
            ['line' => 'Homepage loads but inner pages fail', 'mode' => 'automated'],
            ['line' => 'Broken permalinks (404s)', 'mode' => 'automated'],
            ['line' => 'Mixed content after SSL migration', 'mode' => 'automated'],
            ['line' => 'SSL certificate expired', 'mode' => 'automated'],
            ['line' => 'DNS misconfiguration', 'mode' => 'partial'],
            ['line' => 'Domain not resolving', 'mode' => 'partial'],
            ['line' => 'PHP fatal errors', 'mode' => 'automated'],
            ['line' => 'PHP warnings/notices displayed publicly', 'mode' => 'automated'],
            ['line' => 'Missing images after migration', 'mode' => 'automated'],
            ['line' => 'Incorrect file permissions', 'mode' => 'partial'],
            ['line' => 'Uploads failing', 'mode' => 'partial'],
            ['line' => 'Media library broken', 'mode' => 'partial'],
            ['line' => 'Cron jobs not running', 'mode' => 'automated'],
            ['line' => 'Scheduled posts not publishing', 'mode' => 'automated'],
        ];
    }

    /**
     * @param array<string, array<int, string>> $findings Findings.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function build_coverage(array $findings): array {
        $map = [
            'White Screen of Death (WSOD)'              => 'wsod',
            'Internal Server Error (500)'               => 'http_500',
            'Error Establishing Database Connection'    => 'db_error',
            'Critical Error on Website'                 => 'critical',
            'Infinite redirect loop'                    => 'redirect_loop',
            'Site stuck in maintenance mode'            => 'maintenance',
            'Homepage loads but inner pages fail'       => 'inner_fail',
            'Broken permalinks (404s)'                  => 'permalinks',
            'Mixed content after SSL migration'         => 'mixed_content',
            'SSL certificate expired'                   => 'ssl',
            'DNS misconfiguration'                      => 'dns',
            'Domain not resolving'                      => 'domain',
            'PHP fatal errors'                          => 'php_fatal',
            'PHP warnings/notices displayed publicly'   => 'php_display',
            'Missing images after migration'            => 'missing_images',
            'Incorrect file permissions'                => 'permissions',
            'Uploads failing'                           => 'uploads',
            'Media library broken'                      => 'media',
            'Cron jobs not running'                     => 'cron',
            'Scheduled posts not publishing'            => 'scheduled',
        ];

        $pass_detail = [
            'wsod'           => 'Homepage body not empty',
            'http_500'       => 'No HTTP 5xx in sample',
            'db_error'       => 'No DB connection error signal',
            'critical'       => 'No critical error output in sample',
            'redirect_loop'  => 'No redirect loop detected',
            'maintenance'    => 'Not in maintenance mode',
            'inner_fail'     => 'Inner page sample OK (or home also failing)',
            'permalinks'     => 'No unexpected 404s in sample',
            'mixed_content'  => is_ssl() ? 'No http:// assets on homepage' : 'Site not on HTTPS — N/A',
            'ssl'            => is_ssl() ? 'Certificate not expired / within warn window' : 'Site not on HTTPS — N/A',
            'dns'            => 'Hostname resolves',
            'domain'         => 'Domain resolves',
            'php_fatal'      => 'No recent stored fatals / fatal HTML',
            'php_display'    => Settings::wp_environment_is_dev() ? 'Skipped php.ini display_errors on local' : 'Errors not displayed publicly',
            'missing_images' => 'Sample images OK',
            'permissions'    => 'Uploads writable / no obvious permission issue',
            'uploads'        => 'Upload dir OK',
            'media'          => 'Sample attachments reachable',
            'cron'           => 'Cron not overdue',
            'scheduled'      => 'No overdue future posts',
        ];

        // Soft lines that should not fail overall when SSL N/A.
        $soft_skip_when_empty = [];
        if (! is_ssl()) {
            $soft_skip_when_empty = ['mixed_content', 'ssl'];
        }
        if ($this->is_local()) {
            // Local DNS to .local often fails gethostbyname oddly — still report if found.
        }

        $coverage = [];
        foreach (self::doc_lines() as $row) {
            $line = $row['line'];
            $mode = $row['mode'];
            $key  = $map[$line] ?? '';
            $hits = $key !== '' ? ($findings[$key] ?? []) : [];

            if ($key !== '' && in_array($key, $soft_skip_when_empty, true) && empty($hits)) {
                $coverage[] = [
                    'line'   => $line,
                    'mode'   => $mode,
                    'status' => 'skip',
                    'detail' => $pass_detail[$key] ?? 'N/A',
                ];
                continue;
            }

            // DISABLE_WP_CRON alone is warning-ish: fail only if also overdue events or stuck posts.
            if ($key === 'cron' && count($hits) === 1 && strpos($hits[0], 'DISABLE_WP_CRON') !== false) {
                $coverage[] = [
                    'line'   => $line,
                    'mode'   => $mode,
                    'status' => 'skip',
                    'detail' => $hits[0] . ' (OK if system cron is configured)',
                ];
                continue;
            }

            if ($key === 'php_display' && Settings::wp_environment_is_dev() && empty($hits)) {
                $coverage[] = [
                    'line'   => $line,
                    'mode'   => $mode,
                    'status' => 'skip',
                    'detail' => 'Local environment — php.ini display_errors often On',
                ];
                continue;
            }

            $coverage[] = [
                'line'   => $line,
                'mode'   => $mode,
                'status' => empty($hits) ? 'pass' : 'fail',
                'detail' => empty($hits)
                    ? ($pass_detail[$key] ?? 'OK')
                    : implode('; ', array_slice($hits, 0, 3)),
            ];
        }

        return $coverage;
    }

    /**
     * @param string $url URL.
     * @return array{ok:bool,code:int,body:string,error:string,redirect_loop:bool}
     */
    private function fetch(string $url): array {
        $res = wp_remote_get($url, [
            'timeout'     => 15,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);

        if (is_wp_error($res)) {
            $msg = $res->get_error_message();
            $loop = (stripos($msg, 'redirect') !== false || stripos($msg, 'too many') !== false);
            return [
                'ok'            => false,
                'code'          => 0,
                'body'          => '',
                'error'         => $msg,
                'redirect_loop' => $loop,
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);
        $ok   = ($code >= 200 && $code < 400);

        return [
            'ok'            => $ok,
            'code'          => $code,
            'body'          => $body,
            'error'         => '',
            'redirect_loop' => false,
        ];
    }

    /**
     * @param int $limit Max URLs.
     * @return array<string, string>
     */
    private function sample_inner_urls(int $limit): array {
        $urls = [];
        if ((int) get_option('page_for_posts') > 0) {
            $link = get_permalink((int) get_option('page_for_posts'));
            if ($link) {
                $urls['blog'] = $link;
            }
        }
        $ids = get_posts([
            'post_type'      => ['page', 'post'],
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);
        $i = 0;
        foreach ($ids as $id) {
            $link = get_permalink((int) $id);
            if (! $link || $link === home_url('/') || $link === home_url('')) {
                continue;
            }
            $urls['inner_' . (++$i)] = $link;
            if (count($urls) >= $limit) {
                break;
            }
        }
        return $urls;
    }

    private function probe_code(string $url): ?int {
        $args = [
            'timeout'     => 8,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ];
        $res = wp_remote_head($url, $args);
        if (! is_wp_error($res)) {
            $code = (int) wp_remote_retrieve_response_code($res);
            if ($code !== 405 && $code !== 501) {
                return $code;
            }
        }
        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) {
            return null;
        }
        return (int) wp_remote_retrieve_response_code($res);
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

    private function is_local(): bool {
        return $this->is_dev_environment();
    }
}
