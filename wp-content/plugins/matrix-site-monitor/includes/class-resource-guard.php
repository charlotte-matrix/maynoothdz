<?php
/**
 * Resource guard — locking and runtime limits.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

class Resource_Guard {
    private const LOCK_KEY = 'msm_run_lock';
    private const LOCK_TTL = 300;

    /**
     * Attempt to acquire a run lock.
     *
     * @param string $tier Tier being run.
     */
    public static function acquire_lock(string $tier): bool {
        $existing = get_transient(self::LOCK_KEY);
        if ($existing !== false) {
            return false;
        }

        set_transient(self::LOCK_KEY, [
            'tier' => $tier,
            'time' => time(),
        ], self::LOCK_TTL);

        return true;
    }

    /**
     * Release the run lock.
     */
    public static function release_lock(): void {
        delete_transient(self::LOCK_KEY);
    }

    /**
     * Whether a lock is currently held.
     */
    public static function is_locked(): bool {
        return get_transient(self::LOCK_KEY) !== false;
    }

    /**
     * Prepare the runtime environment for a check suite.
     *
     * @param string $tier Tier being run.
     */
    public static function prepare_runtime(string $tier): void {
        $limits = [
            'light'     => 30,
            'heavy'     => 120,
            'synthetic' => 120,
        ];

        $limit = $limits[$tier] ?? 60;
        if (function_exists('set_time_limit')) {
            @set_time_limit($limit);
        }
    }

    /**
     * Whether synthetic tier should be skipped because light tier failed critically.
     */
    public static function should_skip_synthetic(): bool {
        $settings = Settings::get();
        if (empty($settings['wc_skip_if_light_failed'])) {
            return false;
        }

        $light = Storage::get_tier_result('light');
        if (empty($light['results']) || ! is_array($light['results'])) {
            return false;
        }

        foreach ($light['results'] as $result) {
            if (empty($result['ok']) && ($result['severity'] ?? '') === 'critical') {
                return true;
            }
        }

        return false;
    }
}
