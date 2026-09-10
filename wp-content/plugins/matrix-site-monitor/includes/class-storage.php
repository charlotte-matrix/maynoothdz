<?php
/**
 * Storage for check results and fatal errors.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

class Storage {
    private const FATAL_MAX = 50;

    /**
     * Ensure storage structures exist.
     */
    public static function maybe_upgrade(): void {
        if (! get_option(MSM_FATAL_ERRORS_OPTION)) {
            update_option(MSM_FATAL_ERRORS_OPTION, [], false);
        }
        if (! get_option(MSM_CHECK_RESULTS_OPTION)) {
            update_option(MSM_CHECK_RESULTS_OPTION, [], false);
        }
    }

    /**
     * Save a tier run result.
     *
     * @param string               $tier    Tier name.
     * @param array<string, mixed> $payload Result payload.
     */
    public static function save_tier_result(string $tier, array $payload): void {
        $option = self::tier_option($tier);
        if ($option !== '') {
            update_option($option, $payload, false);
        }
        update_option(MSM_LAST_RESULT_OPTION, $payload, false);
        self::merge_check_results($payload['results'] ?? [], (string) ($payload['time'] ?? current_time('mysql')), $tier);
    }

    /**
     * Merge individual check results into the rolling results map.
     *
     * @param array<int, array<string, mixed>> $results Check results.
     * @param string                           $time    Timestamp.
     * @param string                           $source  Tier or single source label.
     */
    public static function merge_check_results(array $results, string $time, string $source = ''): void {
        $map = self::get_check_results();

        foreach ($results as $result) {
            if (! is_array($result) || empty($result['id'])) {
                continue;
            }
            $id = (string) $result['id'];
            $map[$id] = array_merge($result, [
                'time'   => $time,
                'source' => $source,
            ]);
        }

        update_option(MSM_CHECK_RESULTS_OPTION, $map, false);
    }

    /**
     * Get last known result for every check ID.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_check_results(): array {
        $data = get_option(MSM_CHECK_RESULTS_OPTION, []);
        return is_array($data) ? $data : [];
    }

    /**
     * Get last known result for one check.
     *
     * @param string $check_id Check ID.
     * @return array<string, mixed>
     */
    public static function get_check_result(string $check_id): array {
        $map = self::get_check_results();
        return isset($map[$check_id]) && is_array($map[$check_id]) ? $map[$check_id] : [];
    }

    /**
     * Get option key for a tier.
     *
     * @param string $tier Tier name.
     */
    public static function tier_option(string $tier): string {
        switch ($tier) {
            case 'light':
                return MSM_LAST_LIGHT_OPTION;
            case 'heavy':
                return MSM_LAST_HEAVY_OPTION;
            case 'synthetic':
                return MSM_LAST_SYNTHETIC_OPTION;
            default:
                return '';
        }
    }

    /**
     * Get last result for a tier.
     *
     * @param string $tier Tier name.
     * @return array<string, mixed>
     */
    public static function get_tier_result(string $tier): array {
        $option = self::tier_option($tier);
        if ($option === '') {
            return [];
        }
        $data = get_option($option, []);
        return is_array($data) ? $data : [];
    }

    /**
     * Get the most recent combined result.
     *
     * @return array<string, mixed>
     */
    public static function get_last_result(): array {
        $data = get_option(MSM_LAST_RESULT_OPTION, []);
        return is_array($data) ? $data : [];
    }

    /**
     * Log a fatal PHP error.
     *
     * @param array<string, mixed> $entry Error entry.
     */
    public static function log_fatal_error(array $entry): void {
        $errors = get_option(MSM_FATAL_ERRORS_OPTION, []);
        if (! is_array($errors)) {
            $errors = [];
        }

        array_unshift($errors, $entry);
        $errors = array_slice($errors, 0, self::FATAL_MAX);
        update_option(MSM_FATAL_ERRORS_OPTION, $errors, false);
    }

    /**
     * Get logged fatal errors.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_fatal_errors(): array {
        $errors = get_option(MSM_FATAL_ERRORS_OPTION, []);
        return is_array($errors) ? $errors : [];
    }

    /**
     * Clear fatal errors older than a timestamp.
     *
     * @param int $since Unix timestamp.
     */
    public static function clear_fatal_errors_since(int $since): void {
        $errors = self::get_fatal_errors();
        $keep   = [];

        foreach ($errors as $error) {
            $at = isset($error['occurred_at']) ? strtotime((string) $error['occurred_at']) : 0;
            if ($at >= $since) {
                $keep[] = $error;
            }
        }

        update_option(MSM_FATAL_ERRORS_OPTION, $keep, false);
    }

    /**
     * Format bytes for display.
     *
     * @param float $bytes    Byte count.
     * @param int   $decimals Decimal places.
     */
    public static function format_bytes(float $bytes, int $decimals = 1): string {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / pow(1024, $power), $decimals) . ' ' . $units[$power];
    }
}
