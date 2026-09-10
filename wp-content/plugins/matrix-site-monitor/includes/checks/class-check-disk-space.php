<?php
/**
 * Disk space check.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;
use Matrix_Site_Monitor\Settings;
use Matrix_Site_Monitor\Storage;

defined('ABSPATH') || exit;

class Check_Disk_Space extends Check_Base {
    public function id(): string { return 'disk_space'; }
    public function label(): string { return 'Disk space'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start    = microtime(true);
        $snapshot = self::get_snapshot(ABSPATH);
        $settings = Settings::get();

        if ((float) ($snapshot['total_bytes'] ?? 0) <= 0) {
            return $this->skip('Disk space could not be measured on this host.', $start);
        }

        if (self::is_below_threshold($snapshot, $settings)) {
            $free_pct = round((float) $snapshot['free_percent'], 1);
            $free_gb  = Storage::format_bytes((float) $snapshot['free_bytes']);
            return $this->result(
                false,
                sprintf('Low disk space: %s free (%s%%).', $free_gb, $free_pct),
                $start
            );
        }

        $free_pct = round((float) $snapshot['free_percent'], 1);
        return $this->result(true, sprintf('%s%% free disk space.', $free_pct), $start);
    }

    /**
     * Get disk space snapshot.
     *
     * @param string $path Path to check.
     * @return array<string, float|string>
     */
    public static function get_snapshot(string $path): array {
        $path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false || $total <= 0) {
            return ['path' => $path, 'total_bytes' => 0, 'free_bytes' => 0, 'free_percent' => 0];
        }

        $used = $total - $free;
        return [
            'path'         => $path,
            'total_bytes'  => (float) $total,
            'free_bytes'   => (float) $free,
            'used_bytes'   => (float) $used,
            'free_percent' => ($free / $total) * 100,
            'used_percent' => ($used / $total) * 100,
        ];
    }

    /**
     * Whether free space is below configured threshold.
     *
     * @param array<string, mixed> $snapshot Disk snapshot.
     * @param array<string, mixed> $settings Plugin settings.
     */
    public static function is_below_threshold(array $snapshot, array $settings): bool {
        $mode = (string) ($settings['disk_threshold_mode'] ?? 'percent');

        if ($mode === 'gb') {
            $gb = (float) ($settings['disk_threshold_gb'] ?? 5);
            $threshold = $gb * (defined('GB_IN_BYTES') ? GB_IN_BYTES : 1073741824);
            return (float) ($snapshot['free_bytes'] ?? 0) < $threshold;
        }

        $percent = (float) ($settings['disk_threshold_percent'] ?? 15);
        return (float) ($snapshot['free_percent'] ?? 0) < $percent;
    }
}
