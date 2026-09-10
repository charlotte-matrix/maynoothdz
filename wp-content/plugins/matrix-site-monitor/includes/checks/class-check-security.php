<?php
/**
 * Security checks from docs/Security.md — line coverage.
 *
 * Heuristics only — not a substitute for Wordfence / host malware scans.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;
use Matrix_Site_Monitor\Settings;

defined('ABSPATH') || exit;

class Check_Security extends Check_Base {
    public function id(): string { return 'security'; }
    public function label(): string { return 'Security sample'; }
    public function severity(): string { return 'critical'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);

        $findings = [
            'malware'      => [],
            'injected_js'  => [],
            'admins'       => [],
            'file_changes' => [],
            'core_mod'     => [],
            'plugin_vuln'  => [],
            'theme_vuln'   => [],
            'xmlrpc'       => [],
            'bruteforce'   => [],
            'login_flood'  => [],
            'file_editor'  => [],
            'weak_pass'    => [],
            'dir_listing'  => [],
        ];

        $home = wp_remote_get(home_url('/'), [
            'timeout'     => 20,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        $html = '';
        if (! is_wp_error($home) && (int) wp_remote_retrieve_response_code($home) < 400) {
            $html = (string) wp_remote_retrieve_body($home);
        }

        // Malware / injected JS heuristics on homepage HTML.
        if ($html !== '') {
            if (preg_match('/eval\s*\(\s*base64_decode|gzinflate\s*\(\s*base64_decode|String\.fromCharCode\s*\(/i', $html)) {
                $findings['malware'][]     = 'Suspicious obfuscation pattern in homepage HTML';
                $findings['injected_js'][] = 'Obfuscated JS pattern (eval/base64/fromCharCode)';
            }
            if (preg_match('#<script[^>]*>[^<]*(?:document\.write\s*\(|atob\s*\()#i', $html)) {
                $findings['injected_js'][] = 'Suspicious inline document.write/atob script';
            }
            // Scripts from unexpected IP hosts.
            if (preg_match_all('#<script[^>]+src=["\'](https?://[^"\']+)#i', $html, $sm)) {
                foreach (array_slice($sm[1], 0, 30) as $src) {
                    $sh = (string) wp_parse_url($src, PHP_URL_HOST);
                    if ($sh !== '' && preg_match('/^\d+\.\d+\.\d+\.\d+$/', $sh)) {
                        $findings['injected_js'][] = 'Script loaded from raw IP: ' . $src;
                    }
                }
            }
        }

        // PHP in uploads (common malware drop).
        $upload = wp_upload_dir();
        if (empty($upload['error']) && ! empty($upload['basedir'])) {
            $php_hits = $this->find_php_in_uploads((string) $upload['basedir'], 3);
            foreach ($php_hits as $path) {
                $findings['malware'][] = 'PHP file in uploads: ' . $path;
            }
        }

        // Suspicious admins — new vs baseline + risky logins.
        $baseline = get_option(MSM_ADMIN_BASELINE_OPTION, []);
        $current  = $this->admin_ids();
        if (is_array($baseline) && ! empty($baseline)) {
            $new = array_diff($current, array_map('intval', $baseline));
            foreach ($new as $uid) {
                $user = get_userdata((int) $uid);
                if ($user) {
                    $findings['admins'][] = 'New admin: ' . $user->user_login . ' (#' . $uid . ')';
                }
            }
        }
        foreach ($current as $uid) {
            $user = get_userdata((int) $uid);
            if (! $user) {
                continue;
            }
            $login = strtolower((string) $user->user_login);
            if (in_array($login, ['admin', 'administrator', 'adm', 'root', 'test'], true)) {
                $findings['admins'][] = 'Risky admin login name: ' . $user->user_login;
            }
        }
        $findings['admins'] = array_slice(array_unique($findings['admins']), 0, 5);

        // Core files modified — checksum sample.
        $core_issues = $this->core_checksum_issues();
        foreach ($core_issues as $issue) {
            $findings['core_mod'][]     = $issue;
            $findings['file_changes'][] = $issue;
        }

        // Plugin / theme vulnerabilities (compatibility + optional WPScan token).
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        global $wp_version;
        foreach (get_plugins() as $file => $data) {
            if (! is_plugin_active($file)) {
                continue;
            }
            $tested = trim((string) ($data['Tested'] ?? ''));
            if ($tested !== '' && version_compare($tested, $wp_version, '<')) {
                $major_gap = (float) $wp_version - (float) $tested;
                if ($major_gap >= 0.5) {
                    $findings['plugin_vuln'][] = ($data['Name'] ?? $file) . ' not tested for WP ' . $wp_version;
                }
            }
        }
        foreach ($this->wpscan_plugin_issues() as $issue) {
            $findings['plugin_vuln'][] = $issue;
        }
        $findings['plugin_vuln'] = array_slice(array_unique($findings['plugin_vuln']), 0, 6);

        $themes = wp_get_themes();
        $active_stylesheet = get_stylesheet();
        $active_template   = get_template();
        foreach ([$active_stylesheet, $active_template] as $slug) {
            if (! isset($themes[ $slug ])) {
                continue;
            }
            $theme  = $themes[ $slug ];
            $tested = trim((string) $theme->get('Tested up to'));
            if ($tested !== '' && version_compare($tested, $wp_version, '<')) {
                $findings['theme_vuln'][] = $theme->get('Name') . ' not tested for WP ' . $wp_version;
            }
        }
        $findings['theme_vuln'] = array_slice(array_unique($findings['theme_vuln']), 0, 4);

        // XML-RPC.
        if (! $this->is_local()) {
            $xml = $this->probe_xmlrpc();
            if ($xml === 'open') {
                $findings['xmlrpc'][] = 'xmlrpc.php is publicly reachable and responding';
            }
        }

        // Brute force / login flood — Limit Login Attempts signals + filter.
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $lla = get_option('limit_login_lockouts_total');
        if (is_numeric($lla) && (int) $lla > 100) {
            $findings['bruteforce'][]  = 'Limit Login lockouts total: ' . (int) $lla;
            $findings['login_flood'][] = 'Elevated lockout count (' . (int) $lla . ')';
        }
        $lla_recent = get_option('limit_login_retries_valid');
        if (is_array($lla_recent) && count($lla_recent) > 20) {
            $findings['login_flood'][] = count($lla_recent) . ' IPs with recent login retries';
        }
        $bf = apply_filters('msm_security_bruteforce_issue', null);
        if (is_string($bf) && $bf !== '') {
            $findings['bruteforce'][]  = $bf;
            $findings['login_flood'][] = $bf;
        }

        // File editor.
        if (! $this->is_local()) {
            if (! defined('DISALLOW_FILE_EDIT') || ! DISALLOW_FILE_EDIT) {
                $findings['file_editor'][] = 'DISALLOW_FILE_EDIT is not enabled';
            }
        }

        // Weak passwords — cannot crack hashes; policy signals only.
        $weak = apply_filters('msm_security_weak_password_issue', null);
        if (is_string($weak) && $weak !== '') {
            $findings['weak_pass'][] = $weak;
        }
        // Application: users with user_pass length weird — skip. Flag if any admin email is example.com.
        foreach ($current as $uid) {
            $user = get_userdata((int) $uid);
            if ($user && preg_match('/@(example\.com|test\.com|localhost)$/i', (string) $user->user_email)) {
                $findings['weak_pass'][] = 'Admin ' . $user->user_login . ' has placeholder email';
            }
        }

        // Directory listing.
        foreach ([
            content_url('/'),
            ! empty($upload['baseurl']) ? trailingslashit($upload['baseurl']) : '',
        ] as $list_url) {
            if ($list_url === '') {
                continue;
            }
            $res = wp_remote_get($list_url, [
                'timeout'   => 10,
                'sslverify' => false,
                'headers'   => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
            ]);
            if (is_wp_error($res)) {
                continue;
            }
            $code = (int) wp_remote_retrieve_response_code($res);
            $body = (string) wp_remote_retrieve_body($res);
            if ($code >= 200 && $code < 400 && preg_match('/<title>\s*Index of|Directory listing for|\[To Parent Directory\]/i', $body)) {
                $findings['dir_listing'][] = $list_url . ' appears to allow directory listing';
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

        return $this->pass('Security sample OK (heuristic — not a full malware scan).', $start, $extra);
    }

    /**
     * @return array<int, array{line:string,mode:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'Malware detected', 'mode' => 'partial'],
            ['line' => 'Injected JavaScript', 'mode' => 'partial'],
            ['line' => 'Suspicious admin users', 'mode' => 'automated'],
            ['line' => 'File changes', 'mode' => 'partial'],
            ['line' => 'Core files modified', 'mode' => 'automated'],
            ['line' => 'Plugin vulnerabilities', 'mode' => 'partial'],
            ['line' => 'Theme vulnerabilities', 'mode' => 'partial'],
            ['line' => 'XML-RPC attacks', 'mode' => 'automated'],
            ['line' => 'Brute force attempts', 'mode' => 'partial'],
            ['line' => 'Login attempts excessive', 'mode' => 'partial'],
            ['line' => 'File editor enabled', 'mode' => 'automated'],
            ['line' => 'Weak passwords', 'mode' => 'manual'],
            ['line' => 'Directory listing enabled', 'mode' => 'automated'],
        ];
    }

    /**
     * @param array<string, array<int, string>> $findings Findings.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function build_coverage(array $findings): array {
        $map = [
            'Malware detected'            => 'malware',
            'Injected JavaScript'         => 'injected_js',
            'Suspicious admin users'      => 'admins',
            'File changes'                => 'file_changes',
            'Core files modified'         => 'core_mod',
            'Plugin vulnerabilities'      => 'plugin_vuln',
            'Theme vulnerabilities'       => 'theme_vuln',
            'XML-RPC attacks'             => 'xmlrpc',
            'Brute force attempts'        => 'bruteforce',
            'Login attempts excessive'    => 'login_flood',
            'File editor enabled'         => 'file_editor',
            'Weak passwords'              => 'weak_pass',
            'Directory listing enabled'   => 'dir_listing',
        ];

        $pass = [
            'malware'      => 'No PHP-in-uploads / obfuscation heuristics on homepage',
            'injected_js'  => 'No obvious injected JS patterns in homepage sample',
            'admins'       => 'No new/risky administrator accounts detected',
            'file_changes' => 'Core checksum sample OK',
            'core_mod'     => 'Sampled core files match checksums',
            'plugin_vuln'  => 'No plugin vuln/outdated-tested signals (WPScan optional)',
            'theme_vuln'   => 'Active theme Tested up to looks OK',
            'xmlrpc'       => $this->is_local() ? 'Skipped on local' : 'XML-RPC not openly responding',
            'bruteforce'   => 'No elevated lockout / filter signals',
            'login_flood'  => 'No excessive login retry signal',
            'file_editor'  => $this->is_local() ? 'Skipped on local' : 'DISALLOW_FILE_EDIT enabled',
            'dir_listing'  => 'No directory listing on wp-content/uploads sample',
        ];

        $coverage = [];
        foreach (self::doc_lines() as $row) {
            $line = $row['line'];
            $mode = $row['mode'];
            $key  = $map[$line] ?? '';
            $hits = $key !== '' ? ($findings[$key] ?? []) : [];

            if ($key === 'xmlrpc' && $this->is_local()) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'skip',
                    'detail' => 'Skipped on local/development',
                ];
                continue;
            }
            if ($key === 'file_editor' && $this->is_local()) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'skip',
                    'detail' => 'Skipped on local/development',
                ];
                continue;
            }

            if ($mode === 'manual' && empty($hits)) {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'manual',
                    'detail' => 'Cannot audit password strength from hashes — use policy/2FA (msm_security_weak_password_issue)',
                ];
                continue;
            }

            if (in_array($key, ['bruteforce', 'login_flood'], true) && empty($hits)) {
                if ($this->aios_login_lockdown_enabled()) {
                    $coverage[] = [
                        'line' => $line, 'mode' => $mode, 'status' => 'pass',
                        'detail' => 'All-In-One Security login lockout is enabled',
                    ];
                    continue;
                }
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'skip',
                    'detail' => 'No Limit Login / AIOS lockout signal (install a login-protection plugin or use msm_security_bruteforce_issue)',
                ];
                continue;
            }

            if ($key === 'plugin_vuln' && empty($hits) && trim((string) (Settings::get()['wpscan_api_token'] ?? '')) === '') {
                $coverage[] = [
                    'line' => $line, 'mode' => $mode, 'status' => 'pass',
                    'detail' => 'No Tested-up-to gaps; add WPScan API token for CVE coverage',
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

    /**
     * All-In-One Security login lockout is on.
     */
    private function aios_login_lockdown_enabled(): bool {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (! is_plugin_active('all-in-one-wp-security-and-firewall/wp-security.php')) {
            return false;
        }
        $cfg = get_option('aio_wp_security_configs', []);
        return is_array($cfg) && (string) ($cfg['aiowps_enable_login_lockdown'] ?? '') === '1';
    }

    /**
     * @return array<int, int>
     */
    private function admin_ids(): array {
        $users = get_users(['role' => 'administrator', 'fields' => 'ID']);
        return array_map('intval', (array) $users);
    }

    /**
     * @param string $basedir Uploads basedir.
     * @param int    $limit   Max results.
     * @return array<int, string>
     */
    private function find_php_in_uploads(string $basedir, int $limit): array {
        $hits = [];
        $basedir = trailingslashit($basedir);
        if (! is_dir($basedir)) {
            return [];
        }
        // Non-recursive month folder + basedir only (resource light).
        $dirs = [$basedir, $basedir . gmdate('Y/m')];
        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $files = @scandir($dir);
            if (! is_array($files)) {
                continue;
            }
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (! preg_match('/\.php$/i', $file)) {
                    continue;
                }
                $hits[] = str_replace(ABSPATH, '', trailingslashit($dir) . $file);
                if (count($hits) >= $limit) {
                    return $hits;
                }
            }
        }
        return $hits;
    }

    /**
     * @return array<int, string>
     */
    private function core_checksum_issues(): array {
        global $wp_version;
        if (! function_exists('get_core_checksums')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }
        if (! function_exists('get_core_checksums')) {
            return [];
        }

        $locale    = function_exists('get_locale') ? get_locale() : 'en_US';
        $checksums = get_core_checksums($wp_version, $locale);
        if (! is_array($checksums) || empty($checksums)) {
            $checksums = get_core_checksums($wp_version, 'en_US');
        }
        if (! is_array($checksums) || empty($checksums)) {
            return [];
        }

        $sample = [
            'wp-login.php',
            'wp-includes/version.php',
            'wp-includes/functions.php',
            'wp-settings.php',
            'wp-load.php',
            'xmlrpc.php',
        ];

        $issues = [];
        foreach ($sample as $rel) {
            if (empty($checksums[ $rel ])) {
                continue;
            }
            $path = ABSPATH . $rel;
            if (! is_readable($path)) {
                $issues[] = $rel . ' missing/unreadable';
                continue;
            }
            $hash = md5_file($path);
            if ($hash && ! hash_equals((string) $checksums[ $rel ], $hash)) {
                $issues[] = $rel . ' checksum mismatch';
            }
        }
        return $issues;
    }

    /**
     * @return 'open'|'closed'|'unknown'
     */
    private function probe_xmlrpc(): string {
        $url = site_url('xmlrpc.php');
        $res = wp_remote_post($url, [
            'timeout'   => 10,
            'sslverify' => false,
            'headers'   => ['Content-Type' => 'text/xml'],
            'body'      => '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>',
        ]);
        if (is_wp_error($res)) {
            return 'closed';
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);
        if (in_array($code, [401, 403, 405], true)) {
            return 'closed';
        }
        if ($code >= 200 && $code < 300 && strpos($body, 'methodResponse') !== false) {
            return 'open';
        }
        return 'unknown';
    }

    /**
     * @return array<int, string>
     */
    private function wpscan_plugin_issues(): array {
        $token = trim((string) (Settings::get()['wpscan_api_token'] ?? ''));
        if ($token === '') {
            return [];
        }
        $cache_key = 'msm_security_wpscan_' . md5($token);
        $cached    = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $issues = [];
        $n      = 0;
        foreach (get_plugins() as $plugin_file => $plugin_data) {
            if (! is_plugin_active($plugin_file) || $n >= 8) {
                continue;
            }
            $slug = dirname($plugin_file);
            if ($slug === '.' || strpos($plugin_file, '/') === false) {
                continue;
            }
            $n++;
            $response = wp_remote_get(
                'https://wpscan.com/api/v3/plugins/' . rawurlencode($slug),
                [
                    'timeout' => 12,
                    'headers' => ['Authorization' => 'Token token=' . $token],
                ]
            );
            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
                continue;
            }
            $body = json_decode((string) wp_remote_retrieve_body($response), true);
            if (! is_array($body) || empty($body[ $slug ]['vulnerabilities'])) {
                continue;
            }
            $count = count((array) $body[ $slug ]['vulnerabilities']);
            $issues[] = ($plugin_data['Name'] ?? $slug) . ' — ' . $count . ' WPScan vuln(s)';
        }
        set_transient($cache_key, $issues, DAY_IN_SECONDS);
        return $issues;
    }

    private function is_local(): bool {
        return $this->is_dev_environment();
    }
}
