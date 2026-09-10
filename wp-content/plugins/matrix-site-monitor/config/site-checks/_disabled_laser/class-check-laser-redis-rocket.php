<?php
/**
 * Site check: Redis object cache + WP Rocket expected on production (Laser Centre).
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Site_Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Laser_Redis_Rocket extends Check_Base {
    public function id(): string { return 'laser_redis_rocket'; }
    public function label(): string { return 'Site: Redis + WP Rocket'; }
    public function category(): string { return 'Site'; }
    public function severity(): string { return 'info'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);

        if (Laser_Helpers::is_local_host()) {
            return $this->skip('Development QA profile — Redis/WP Rocket skipped. Use live profile for go-live.', $start);
        }

        $issues = [];

        $rocket = false;
        foreach ((array) get_option('active_plugins', []) as $file) {
            if (strpos((string) $file, 'wp-rocket/') === 0) {
                $rocket = true;
                break;
            }
        }
        if (! $rocket) {
            $issues[] = 'WP Rocket is not active';
        }

        $redis_plugin = false;
        foreach ((array) get_option('active_plugins', []) as $file) {
            if (strpos((string) $file, 'redis-cache') !== false) {
                $redis_plugin = true;
                break;
            }
        }
        $dropin = file_exists(WP_CONTENT_DIR . '/object-cache.php');
        if (! $redis_plugin && ! $dropin) {
            $issues[] = 'Redis Cache plugin not active and no object-cache.php drop-in';
        } elseif ($redis_plugin && ! $dropin) {
            $issues[] = 'Redis Cache plugin active but object-cache.php drop-in missing';
        }

        if ($issues) {
            return $this->fail(implode('; ', $issues) . '.', $start);
        }

        return $this->pass('WP Rocket active; object cache drop-in present.', $start);
    }
}

add_filter('msm_registered_checks', static function (array $checks): array {
    $checks[] = new Check_Laser_Redis_Rocket();
    return $checks;
});
