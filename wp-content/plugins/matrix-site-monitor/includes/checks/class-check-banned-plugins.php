<?php
/**
 * Banned / denylisted plugins — fail if present on disk (active or not).
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;
use Matrix_Site_Monitor\Settings;

defined('ABSPATH') || exit;

class Check_Banned_Plugins extends Check_Base {
    public function id(): string {
        return 'banned_plugins';
    }

    public function label(): string {
        return 'Banned plugins';
    }

    public function severity(): string {
        return 'critical';
    }

    public function tier(): string {
        return 'light';
    }

    public function run(): array {
        $start = microtime(true);

        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $banned  = self::denylist();
        $all     = get_plugins();
        $active  = (array) get_option('active_plugins', []);
        $network = is_multisite() ? array_keys((array) get_site_option('active_sitewide_plugins', [])) : [];
        $hits    = [];

        foreach ($all as $file => $data) {
            $slug = dirname($file);
            if ($slug === '.' || $slug === '') {
                $slug = basename($file, '.php');
            }
            $name = (string) ($data['Name'] ?? '');
            $match = self::match_rule($banned, $file, $slug, $name);
            if ($match === null) {
                continue;
            }
            $state = 'installed';
            if (in_array($file, $active, true) || in_array($file, $network, true)) {
                $state = 'active';
            }
            $hits[] = sprintf('%s (%s) — matched "%s" [%s]', $name !== '' ? $name : $file, $file, $match, $state);
        }

        if ($hits) {
            return $this->fail(
                'Banned plugin(s) found: ' . implode('; ', array_slice($hits, 0, 8)) . (count($hits) > 8 ? '…' : ''),
                $start,
                ['banned_hits' => $hits, 'denylist' => $banned]
            );
        }

        return $this->pass(
            sprintf('No banned plugins present (%d rule(s) checked).', count($banned)),
            $start,
            ['denylist' => $banned]
        );
    }

    /**
     * Default denylist (slugs / substrings). File Manager family is always included.
     *
     * @return array<int, string>
     */
    public static function default_denylist(): array {
        return [
            'wp-file-manager',
            'file-manager',
            'file-manager-advanced',
            'filemanager',
            'wp-filemanager',
            'elfinder',
            'library-filemanager',
            'responsive-filemanager',
            'file-manager-pro',
            'fileorganizer',
        ];
    }

    /**
     * Merged denylist: defaults + settings + filter.
     *
     * @return array<int, string>
     */
    public static function denylist(): array {
        $settings = Settings::get();
        $custom   = Settings::parse_csv_list((string) ($settings['banned_plugins'] ?? ''));
        $merged   = array_merge(self::default_denylist(), $custom);
        $merged   = array_values(array_unique(array_filter(array_map('strtolower', array_map('trim', $merged)))));

        /**
         * Filter banned plugin rules (slug, plugin file path, or name substring).
         *
         * @param array<int, string> $merged Rules.
         */
        return (array) apply_filters('msm_banned_plugins', $merged);
    }

    /**
     * @param array<int, string> $rules Rules.
     * @param string             $file  Plugin basename path.
     * @param string             $slug  Directory slug.
     * @param string             $name  Plugin name.
     * @return string|null Matched rule or null.
     */
    private static function match_rule(array $rules, string $file, string $slug, string $name): ?string {
        $file_l = strtolower($file);
        $slug_l = strtolower($slug);
        $name_l = strtolower($name);

        foreach ($rules as $rule) {
            $rule = strtolower(trim($rule));
            if ($rule === '') {
                continue;
            }
            // Exact plugin file.
            if (strpos($rule, '/') !== false || substr($rule, -4) === '.php') {
                if ($file_l === $rule || strpos($file_l, $rule) !== false) {
                    return $rule;
                }
                continue;
            }
            // Slug / folder name.
            if ($slug_l === $rule || strpos($slug_l, $rule) !== false) {
                return $rule;
            }
            // Name contains (e.g. "File Manager").
            if ($name_l !== '' && strpos($name_l, $rule) !== false) {
                return $rule;
            }
        }

        return null;
    }
}
