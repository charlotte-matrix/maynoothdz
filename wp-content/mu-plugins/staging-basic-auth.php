<?php
/**
 * Plugin Name: Staging HTTP Basic Auth
 * Description: Password-protects matrix-test staging only (not local or production).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether this request is on Matrix staging hosting.
 */
function matrix_dz_is_matrix_staging_host(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?: $host;

    return $host !== '' && str_contains($host, 'matrix-test.com');
}

add_action('plugins_loaded', static function (): void {
    // Never block WP-CLI / cron PHP CLI.
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        return;
    }
    if (defined('WP_CLI') && WP_CLI) {
        return;
    }

    // Only hosted staging — leave .local and live alone.
    if (!matrix_dz_is_matrix_staging_host()) {
        return;
    }

    $user = 'matrix';
    $pass = 'matrix';

    $given_user = (string) ($_SERVER['PHP_AUTH_USER'] ?? '');
    $given_pass = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');

    // Some PHP-FPM / CGI setups put Basic Auth here instead.
    if ($given_user === '' && $given_pass === '' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = (string) $_SERVER['HTTP_AUTHORIZATION'];
        if (stripos($header, 'basic ') === 0) {
            $decoded = base64_decode(substr($header, 6), true);
            if (is_string($decoded) && str_contains($decoded, ':')) {
                [$given_user, $given_pass] = explode(':', $decoded, 2);
            }
        }
    }

    if (hash_equals($user, $given_user) && hash_equals($pass, $given_pass)) {
        return;
    }

    header('WWW-Authenticate: Basic realm="Maynooth DZ Staging"');
    header('HTTP/1.1 401 Unauthorized');
    header('Cache-Control: no-store');
    echo 'Authentication required.';
    exit;
}, 0);
