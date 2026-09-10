<?php
/**
 * Site check: critical Laser Centre URLs.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Site_Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Laser_Critical_Urls extends Check_Base {
    public function id(): string {
        return 'laser_critical_urls';
    }

    public function label(): string {
        return 'Site: critical URLs';
    }

    public function category(): string {
        return 'Site';
    }

    public function severity(): string {
        return 'critical';
    }

    public function tier(): string {
        return 'light';
    }

    /**
     * Paths that matter for Laser Centre QA.
     *
     * @return array<string, string> label => path
     */
    private function paths(): array {
        $paths = [
            'home'    => '/',
            'contact' => '/contact-us/',
            'booking' => '/book-appointment/',
        ];

        /**
         * Filter critical URL paths.
         *
         * @param array<string, string> $paths Label => path.
         */
        return (array) apply_filters('msm_laser_critical_paths', $paths);
    }

    public function run(): array {
        $start = microtime(true);
        $fails = [];

        foreach ($this->paths() as $label => $path) {
            $res = Laser_Helpers::fetch(home_url($path));
            if (! $res['ok']) {
                $fails[] = $label . ': ' . ($res['error'] ?: 'HTTP ' . $res['code']) . ' (' . home_url($path) . ')';
            }
        }

        if ($fails) {
            return $this->fail(implode('; ', array_slice($fails, 0, 6)), $start, ['details' => $fails]);
        }

        return $this->pass('Critical URLs OK (' . count($this->paths()) . ').', $start);
    }
}

add_filter('msm_registered_checks', static function (array $checks): array {
    $checks[] = new Check_Laser_Critical_Urls();
    return $checks;
});
