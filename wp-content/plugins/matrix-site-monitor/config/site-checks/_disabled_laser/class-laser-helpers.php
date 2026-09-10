<?php
/**
 * Shared helpers for Laser Centre site checks.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Site_Checks;

defined('ABSPATH') || exit;

final class Laser_Helpers {
    public const TEST_EMAIL = 'msm-selftest@example.invalid';
    public const TEST_NAME  = 'MSM Selftest';
    public const TEST_PHONE = '0100000000';

    /**
     * @return array{ok:bool,code:int,body:string,error:string}
     */
    public static function ensure_plugin_functions(): void {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    /**
     * @param array<int, string> $plugins Plugin files.
     */
    public static function any_plugin_active(array $plugins): bool {
        self::ensure_plugin_functions();
        foreach ($plugins as $file) {
            if (is_plugin_active($file)) {
                return true;
            }
        }
        return false;
    }

    public static function is_local_host(): bool {
        return \Matrix_Site_Monitor\Settings::is_dev_profile();
    }

    /**
     * @return array{ok:bool,code:int,body:string,error:string}
     */
    public static function fetch(string $url, int $timeout = 15): array {
        $res = wp_remote_get($url, [
            'timeout'     => $timeout,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);
        if (is_wp_error($res)) {
            return ['ok' => false, 'code' => 0, 'body' => '', 'error' => $res->get_error_message()];
        }
        $code  = (int) wp_remote_retrieve_response_code($res);
        $body  = (string) wp_remote_retrieve_body($res);
        $fatal = (bool) preg_match('/critical error|Fatal error|There has been a critical error/i', $body);
        return [
            'ok'    => $code < 400 && ! $fatal,
            'code'  => $code,
            'body'  => $body,
            'error' => $fatal ? 'Critical/fatal error in HTML' : ($code >= 400 ? 'HTTP ' . $code : ''),
        ];
    }

    public static function page_url(string $path): string {
        $path = '/' . ltrim($path, '/');
        if (substr($path, -1) !== '/') {
            $path .= '/';
        }
        return home_url($path);
    }

    /**
     * Confirm a published page exists and contains the live theme form.
     *
     * @param array<int, string> $needles Strings that must appear in HTML.
     * @return array{ok:bool,url:string,message:string}
     */
    public static function assert_form_on_page(string $path, array $needles): array {
        $url = self::page_url($path);
        $res = self::fetch($url);
        if (! $res['ok']) {
            return [
                'ok'      => false,
                'url'     => $url,
                'message' => 'Page failed: ' . ($res['error'] ?: 'HTTP ' . $res['code']) . ' (' . $url . ')',
            ];
        }

        $missing = [];
        foreach ($needles as $needle) {
            if (stripos($res['body'], $needle) === false) {
                $missing[] = $needle;
            }
        }
        if ($missing) {
            return [
                'ok'      => false,
                'url'     => $url,
                'message' => 'Form markup missing on ' . $url . ': ' . implode(', ', array_slice($missing, 0, 4)),
            ];
        }

        return [
            'ok'      => true,
            'url'     => $url,
            'message' => 'Form present on ' . $url,
        ];
    }

    /**
     * Run Theme_Forms persist path, verify the entry, then force-delete it.
     *
     * @param array<string, mixed> $fields Field map.
     * @return array{ok:bool,message:string,entry_id:int}
     */
    public static function fire_and_delete(string $type, array $fields): array {
        if (! class_exists('Theme_Forms')) {
            return [
                'ok'       => false,
                'message'  => 'Theme_Forms is not available.',
                'entry_id' => 0,
            ];
        }

        \Theme_Forms::purge_monitor_selftests();

        $created = \Theme_Forms::run_monitor_selftest($type, $fields);
        $entry_id = (int) ($created['entry_id'] ?? 0);
        if (empty($created['ok']) || $entry_id < 1) {
            return [
                'ok'       => false,
                'message'  => (string) ($created['message'] ?? 'Self-test did not create an entry.'),
                'entry_id' => $entry_id,
            ];
        }

        $post = get_post($entry_id);
        $email = (string) get_post_meta($entry_id, 'email', true);
        $ok_saved = $post instanceof \WP_Post && $email === self::TEST_EMAIL;

        $deleted = \Theme_Forms::delete_monitor_selftest($entry_id);
        \Theme_Forms::purge_monitor_selftests();

        if (! $ok_saved) {
            return [
                'ok'       => false,
                'message'  => 'Test entry #' . $entry_id . ' was created but fields did not save.',
                'entry_id' => $entry_id,
            ];
        }
        if (! $deleted || get_post($entry_id)) {
            return [
                'ok'       => false,
                'message'  => 'Test entry #' . $entry_id . ' fired but could not be deleted.',
                'entry_id' => $entry_id,
            ];
        }

        return [
            'ok'       => true,
            'message'  => 'Test ' . $type . ' #' . $entry_id . ' created, verified, and deleted.',
            'entry_id' => $entry_id,
        ];
    }
}
