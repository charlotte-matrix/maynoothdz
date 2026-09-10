<?php
/**
 * Tiered WP-Cron scheduler.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

class Scheduler {
    /**
     * Register cron hooks.
     */
    public static function register_hooks(): void {
        add_action(MSM_CRON_LIGHT, [__CLASS__, 'run_light']);
        add_action(MSM_CRON_HEAVY, [__CLASS__, 'run_heavy']);
        add_action(MSM_CRON_SYNTHETIC, [__CLASS__, 'run_synthetic']);
        add_action(MSM_CRON_DIGEST, [__CLASS__, 'run_digest']);
        add_filter('cron_schedules', [__CLASS__, 'add_schedules']);
    }

    /**
     * Add custom cron intervals.
     *
     * @param array<string, array<string, int|string>> $schedules Existing schedules.
     * @return array<string, array<string, int|string>>
     */
    public static function add_schedules(array $schedules): array {
        // Avoid translating before init (WP 6.7+ just-in-time textdomain notice).
        $display = did_action('init')
            ? __('Every 4 Hours (MSM)', 'matrix-site-monitor')
            : 'Every 4 Hours (MSM)';

        $schedules['msm_four_hours'] = [
            'interval' => 4 * HOUR_IN_SECONDS,
            'display'  => $display,
        ];
        return $schedules;
    }

    /**
     * Sync all cron events.
     */
    public static function sync_all(): void {
        self::schedule_recurring(MSM_CRON_LIGHT, 'hourly');
        self::schedule_daily_at(MSM_CRON_HEAVY, (string) Settings::get()['heavy_schedule_time']);

        $settings = Settings::get();
        $interval = ($settings['synthetic_interval'] ?? 'hourly') === 'four_hours' ? 'msm_four_hours' : 'hourly';
        if (class_exists('WooCommerce') && ! empty($settings['wc_enabled'])) {
            self::schedule_recurring(MSM_CRON_SYNTHETIC, $interval);
        } else {
            wp_clear_scheduled_hook(MSM_CRON_SYNTHETIC);
        }

        if (! empty($settings['digest_enabled'])) {
            self::schedule_weekly_digest();
        } else {
            wp_clear_scheduled_hook(MSM_CRON_DIGEST);
        }
    }

    /**
     * Clear all cron events.
     */
    public static function clear_all(): void {
        wp_clear_scheduled_hook(MSM_CRON_LIGHT);
        wp_clear_scheduled_hook(MSM_CRON_HEAVY);
        wp_clear_scheduled_hook(MSM_CRON_SYNTHETIC);
        wp_clear_scheduled_hook(MSM_CRON_DIGEST);
    }

    /**
     * Schedule a recurring event if not already scheduled.
     *
     * @param string $hook     Cron hook.
     * @param string $schedule Schedule name.
     */
    private static function schedule_recurring(string $hook, string $schedule): void {
        if (! wp_next_scheduled($hook)) {
            wp_schedule_event(time() + 300, $schedule, $hook);
        }
    }

    /**
     * Schedule a daily event at a specific time.
     *
     * @param string $hook Cron hook.
     * @param string $time HH:MM format.
     */
    private static function schedule_daily_at(string $hook, string $time): void {
        wp_clear_scheduled_hook($hook);
        wp_schedule_event(self::next_timestamp_for_time($time), 'daily', $hook);
    }

    /**
     * Schedule weekly digest.
     */
    private static function schedule_weekly_digest(): void {
        $settings = Settings::get();
        $day      = strtolower((string) ($settings['digest_day'] ?? 'monday'));
        $time     = (string) ($settings['digest_time'] ?? '09:00');

        wp_clear_scheduled_hook(MSM_CRON_DIGEST);
        wp_schedule_event(self::next_weekly_timestamp($day, $time), 'weekly', MSM_CRON_DIGEST);
    }

    /**
     * Get next daily timestamp for HH:MM.
     *
     * @param string $time HH:MM.
     */
    private static function next_timestamp_for_time(string $time): int {
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $now      = new \DateTimeImmutable('now', $timezone);
        $parts    = explode(':', $time);
        $hour     = (int) ($parts[0] ?? 3);
        $minute   = (int) ($parts[1] ?? 0);
        $scheduled = $now->setTime($hour, $minute, 0);

        if ($now >= $scheduled) {
            $scheduled = $scheduled->modify('+1 day');
        }

        return $scheduled->getTimestamp();
    }

    /**
     * Get next weekly timestamp.
     *
     * @param string $day  Day name.
     * @param string $time HH:MM.
     */
    private static function next_weekly_timestamp(string $day, string $time): int {
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $now      = new \DateTimeImmutable('now', $timezone);
        $parts    = explode(':', $time);
        $hour     = (int) ($parts[0] ?? 9);
        $minute   = (int) ($parts[1] ?? 0);

        $target = $now->modify('next ' . $day)->setTime($hour, $minute, 0);
        if ($target <= $now) {
            $target = $target->modify('+1 week');
        }

        return $target->getTimestamp();
    }

    /** @return void */
    public static function run_light(): void {
        (new Check_Runner())->run_tier('light');
    }

    /** @return void */
    public static function run_heavy(): void {
        (new Check_Runner())->run_tier('heavy');
    }

    /** @return void */
    public static function run_synthetic(): void {
        (new Check_Runner())->run_tier('synthetic');
    }

    /** @return void */
    public static function run_digest(): void {
        Notifier::send_digest();
    }
}
