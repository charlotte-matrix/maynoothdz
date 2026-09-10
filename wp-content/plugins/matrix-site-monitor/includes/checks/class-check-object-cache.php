<?php
/**
 * Object cache / Redis availability.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Object_Cache extends Check_Base {
    public function id(): string { return 'object_cache'; }
    public function label(): string { return 'Object cache'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);

        $dropin = WP_CONTENT_DIR . '/object-cache.php';
        if (! file_exists($dropin)) {
            return $this->skip('No object-cache.php drop-in — persistent object cache not configured.', $start);
        }

        if (! wp_using_ext_object_cache()) {
            return $this->fail('object-cache.php exists but external object cache is not active.', $start);
        }

        $key = 'msm_object_cache_probe_' . wp_generate_password(6, false);
        wp_cache_set($key, 'ok', 'msm', 60);
        $value = wp_cache_get($key, 'msm');
        wp_cache_delete($key, 'msm');

        if ($value !== 'ok') {
            return $this->fail('Object cache set/get probe failed.', $start);
        }

        return $this->pass('External object cache is active and responding.', $start);
    }
}
