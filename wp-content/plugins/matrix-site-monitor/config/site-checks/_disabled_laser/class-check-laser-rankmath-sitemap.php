<?php
/**
 * Site check: Rank Math sitemap (Laser Centre).
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Site_Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Laser_Rankmath_Sitemap extends Check_Base {
    public function id(): string { return 'laser_rankmath_sitemap'; }
    public function label(): string { return 'Site: Rank Math sitemap'; }
    public function category(): string { return 'Site'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);

        if (! Laser_Helpers::any_plugin_active([
            'seo-by-rank-math/rank-math.php',
            'seo-by-rank-math-pro/rank-math-pro.php',
        ])) {
            return $this->fail('Rank Math SEO is not active.', $start);
        }

        $candidates = [
            '/sitemap_index.xml',
            '/sitemap.xml',
            '/rank-math-sitemap.xml',
        ];
        foreach ($candidates as $path) {
            $res = Laser_Helpers::fetch(home_url($path), 12);
            if (! $res['ok']) {
                continue;
            }
            $body = strtolower($res['body']);
            if (strpos($body, '<sitemapindex') !== false || strpos($body, '<urlset') !== false) {
                return $this->pass('Rank Math active; sitemap OK at ' . $path . '.', $start);
            }
        }

        return $this->fail('Rank Math active but no XML sitemap found at common paths.', $start);
    }
}

add_filter('msm_registered_checks', static function (array $checks): array {
    $checks[] = new Check_Laser_Rankmath_Sitemap();
    return $checks;
});
