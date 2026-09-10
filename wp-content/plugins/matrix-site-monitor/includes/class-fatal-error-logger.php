<?php
/**
 * Fatal error capture on shutdown.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

class Fatal_Error_Logger {
    /** @var string */
    private static $memory_reserve = '';

    /**
     * Register shutdown handler.
     */
    public function register_hooks(): void {
        if (self::$memory_reserve === '') {
            self::$memory_reserve = str_repeat('x', 262144);
        }
        register_shutdown_function([$this, 'capture_shutdown']);
    }

    /**
     * Capture fatal errors on shutdown.
     */
    public function capture_shutdown(): void {
        self::$memory_reserve = '';

        $error = error_get_last();
        if (! is_array($error) || ! $this->is_fatal((int) ($error['type'] ?? 0))) {
            return;
        }

        $entry = [
            'occurred_at' => current_time('mysql', true),
            'type'        => (int) ($error['type'] ?? 0),
            'message'     => (string) ($error['message'] ?? ''),
            'file'        => (string) ($error['file'] ?? ''),
            'line'        => (int) ($error['line'] ?? 0),
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI'])) : '',
            'is_admin'    => function_exists('is_admin') && is_admin() ? 1 : 0,
        ];

        if (self::is_noise($entry)) {
            return;
        }

        Storage::log_fatal_error($entry);
    }

    /**
     * WP-CLI `eval` fatals are operator mistakes, not site crashes.
     *
     * @param array<string, mixed> $error Stored or last-error payload.
     */
    public static function is_noise(array $error): bool {
        $hay = (string) ($error['file'] ?? '') . "\n" . (string) ($error['message'] ?? '');
        if (strpos($hay, 'Eval_Command.php') !== false) {
            return true;
        }
        return strpos($hay, "eval()'d code") !== false;
    }

    /**
     * Drop stored WP-CLI eval fatals so they cannot fail health for a week.
     */
    public static function prune_stored_noise(): void {
        $errors = Storage::get_fatal_errors();
        $keep   = [];
        foreach ($errors as $error) {
            if (is_array($error) && ! self::is_noise($error)) {
                $keep[] = $error;
            }
        }
        if (count($keep) !== count($errors)) {
            update_option(MSM_FATAL_ERRORS_OPTION, $keep, false);
        }
    }

    /**
     * Whether error type is fatal.
     *
     * @param int $type PHP error type constant.
     */
    private function is_fatal(int $type): bool {
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (defined('E_RECOVERABLE_ERROR')) {
            $fatal[] = E_RECOVERABLE_ERROR;
        }
        return in_array($type, $fatal, true);
    }
}
