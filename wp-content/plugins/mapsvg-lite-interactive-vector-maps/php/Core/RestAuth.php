<?php

namespace MapSVG;

/**
 * WordPress REST cookie auth rejects any request that sends an invalid
 * X-WP-Nonce, even when the route itself is public (__return_true).
 *
 * Front-end pages often ship a cached nonce (page cache / CDN). A visitor
 * then gets 403 rest_cookie_invalid_nonce on filter reloads and the map
 * spinner never stops. Public GET collection/map routes must still work
 * as unauthenticated reads in that case.
 */
class RestAuth
{
    /**
     * Public read-only MapSVG REST route prefixes.
     *
     * @var string[]
     */
    private const PUBLIC_GET_PREFIXES = array(
        '/mapsvg/v1/objects',
        '/mapsvg/v1/regions',
        '/mapsvg/v1/collection',
        '/mapsvg/v1/maps',
    );

    /**
     * Whether a REST route is a public MapSVG GET endpoint.
     *
     * Accepts either a rest_route query var ("/mapsvg/v1/objects/objects_2")
     * or a full request URI ("/wp-json/mapsvg/v1/objects/objects_2?...").
     */
    public static function isPublicReadRoute(string $route): bool
    {
        $path = self::normalizeRoute($route);
        if ($path === '') {
            return false;
        }

        foreach (self::PUBLIC_GET_PREFIXES as $prefix) {
            if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a failed cookie-nonce check should be ignored for this request.
     */
    public static function shouldAllowStaleNonce(string $errorCode, string $method, string $route): bool
    {
        return $errorCode === 'rest_cookie_invalid_nonce'
            && strtoupper($method) === 'GET'
            && self::isPublicReadRoute($route);
    }

    /**
     * rest_authentication_errors callback: treat a stale nonce as "logged out"
     * on public GET routes instead of 403ing the request.
     *
     * @param mixed $result
     * @return mixed
     */
    public static function allowStaleNonceOnPublicGet($result)
    {
        if (!is_wp_error($result)) {
            return $result;
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
        if (!self::shouldAllowStaleNonce($result->get_error_code(), $method, self::currentRestRoute())) {
            return $result;
        }

        wp_set_current_user(0);
        return true;
    }

    private static function currentRestRoute(): string
    {
        if (isset($GLOBALS['wp']) && is_object($GLOBALS['wp']) && isset($GLOBALS['wp']->query_vars['rest_route'])) {
            return (string) $GLOBALS['wp']->query_vars['rest_route'];
        }
        if (isset($_SERVER['REQUEST_URI'])) {
            return (string) $_SERVER['REQUEST_URI'];
        }
        return '';
    }

    private static function normalizeRoute(string $route): string
    {
        if (preg_match('/(?:\?|&)rest_route=([^&]+)/', $route, $matches)) {
            $path = rawurldecode($matches[1]);
        } else {
            $path = function_exists('wp_parse_url')
                ? wp_parse_url($route, PHP_URL_PATH)
                : parse_url($route, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                $path = strtok($route, '?');
            }
        }
        if (!is_string($path) || $path === '') {
            return '';
        }

        $wpJson = '/wp-json';
        if (strpos($path, $wpJson) !== false) {
            $path = substr($path, strpos($path, $wpJson) + strlen($wpJson));
        }

        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }

        return rtrim($path, '/') ?: '/';
    }
}
