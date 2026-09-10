<?php
/**
 * REST API status endpoint.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

class Rest {
    /**
     * Register REST routes.
     */
    public static function register(): void {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    /**
     * Register route definitions.
     */
    public static function routes(): void {
        register_rest_route('matrix-site-monitor/v1', '/status', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'status'],
            'permission_callback' => [__CLASS__, 'permission'],
        ]);
    }

    /**
     * Permission callback — token auth.
     *
     * @param \WP_REST_Request $request Request object.
     */
    public static function permission(\WP_REST_Request $request) {
        $settings = Settings::get();

        if (empty($settings['enable_rest']) || empty($settings['api_token'])) {
            return new \WP_Error('msm_rest_disabled', __('Status endpoint is disabled.', 'matrix-site-monitor'), ['status' => 403]);
        }

        $provided = $request->get_header('X-MSM-Token');
        if (! $provided) {
            $provided = $request->get_param('token');
        }

        if (! hash_equals((string) $settings['api_token'], (string) $provided)) {
            return new \WP_Error('msm_invalid_token', __('Invalid token.', 'matrix-site-monitor'), ['status' => 401]);
        }

        return true;
    }

    /**
     * Return site monitor status.
     *
     * @return \WP_REST_Response
     */
    public static function status() {
        $light     = Storage::get_tier_result('light');
        $heavy     = Storage::get_tier_result('heavy');
        $synthetic = Storage::get_tier_result('synthetic');

        $overall_ok = true;
        foreach ([$light, $heavy, $synthetic] as $tier) {
            if (! empty($tier) && isset($tier['ok']) && ! $tier['ok']) {
                $overall_ok = false;
                break;
            }
        }

        return rest_ensure_response([
            'site' => [
                'name' => get_bloginfo('name'),
                'url'  => home_url('/'),
            ],
            'overall_ok' => $overall_ok,
            'tiers'      => [
                'light'     => $light,
                'heavy'     => $heavy,
                'synthetic' => $synthetic,
            ],
            'generated_at' => current_time('mysql', true),
            'plugin_version' => MSM_VERSION,
        ]);
    }
}
