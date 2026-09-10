<?php
/**
 * Check registry — discovers and filters available checks.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

class Check_Registry {
    /**
     * Build the full check list (ignores disabled setting — for admin UI).
     *
     * @return array<int, Check_Interface>
     */
    public static function catalog(): array {
        $checks = [
            new Checks\Check_Http_Smoke(),
            new Checks\Check_General(),
            new Checks\Check_Maintenance_Mode(),
            new Checks\Check_Debug_Display(),
            new Checks\Check_Cron_Health(),
            new Checks\Check_Site_Search(),
            new Checks\Check_Fatal_Errors(),
            new Checks\Check_Admin_Anomalies(),
            new Checks\Check_Disk_Space(),
            new Checks\Check_Hosting(),
            new Checks\Check_Php_Wp_Versions(),
            new Checks\Check_Php_Memory(),
            new Checks\Check_Update_Availability(),
            new Checks\Check_Maintenance(),
            new Checks\Check_Security_Advisories(),
            new Checks\Check_Security(),
            new Checks\Check_Ssl_Expiry(),
            new Checks\Check_File_Editor(),
            new Checks\Check_Xmlrpc(),
            new Checks\Check_Object_Cache(),
            new Checks\Check_Backup_Status(),
            new Checks\Check_Db_Hygiene(),
            new Checks\Check_Inactive_Plugins(),
            new Checks\Check_Banned_Plugins(),
            new Checks\Check_Plugins(),
            new Checks\Check_Analytics_Tags(),
            new Checks\Check_Accessibility(),
            new Checks\Check_Content(),
            new Checks\Check_Forms(),
            new Checks\Check_Javascript(),
            new Checks\Check_Performance(),
            new Checks\Check_Seo(),
            new Checks\Check_Theme(),
            new Checks\Check_Browser(),
            new Checks\Check_Preflight(),
            new Checks\Check_Golive(),
        ];

        if (class_exists('WooCommerce')) {
            $checks = array_merge($checks, self::woocommerce_checks());
        }

        $checks = apply_filters('msm_registered_checks', $checks);

        return array_values(array_filter($checks, static function ($check) {
            return $check instanceof Check_Interface;
        }));
    }

    /**
     * Get all registered checks (respects disabled setting).
     *
     * @return array<int, Check_Interface>
     */
    public static function all(): array {
        $wc_enabled = ! empty(Settings::get()['wc_enabled']);

        return array_values(array_filter(self::catalog(), static function (Check_Interface $check) use ($wc_enabled) {
            if (Settings::is_check_disabled($check->id())) {
                return false;
            }
            if ($check->tier() === 'synthetic' && ! $wc_enabled) {
                return false;
            }
            return true;
        }));
    }

    /**
     * Get a single check by ID (even if disabled, for manual test runs).
     *
     * @param string $id Check ID.
     */
    public static function get(string $id): ?Check_Interface {
        foreach (self::catalog() as $check) {
            if ($check->id() === $id) {
                return $check;
            }
        }
        return null;
    }

    /**
     * Core checks that stay on by default for new installs.
     *
     * @return array<int, string>
     */
    public static function default_enabled_ids(): array {
        return [
            'http_smoke',
            'cron_health',
            'fatal_errors',
            'admin_anomalies',
            'banned_plugins',
            'disk_space',
            'php_wp_versions',
            'update_availability',
            'ssl_expiry',
            'backup_status',
            'wc_pages',
            'wc_cart_checkout',
            'wc_coupon',
            'wc_order_lifecycle',
        ];
    }

    /**
     * Get checks for a specific tier.
     *
     * @param string $tier light, heavy, or synthetic.
     * @return array<int, Check_Interface>
     */
    public static function for_tier(string $tier): array {
        return array_values(array_filter(self::all(), static function (Check_Interface $check) use ($tier) {
            return $check->tier() === $tier;
        }));
    }

    /**
     * Docs category for a check ID.
     *
     * @param string $check_id Check ID.
     */
    public static function category_for(string $check_id): string {
        $map = [
            'http_smoke'           => 'General',
            'general'              => 'General',
            'maintenance_mode'     => 'General',
            'debug_display'        => 'General',
            'cron_health'          => 'General',
            'fatal_errors'         => 'General',
            'site_search'          => 'Logic',
            'admin_anomalies'      => 'Security',
            'disk_space'           => 'Hosting',
            'hosting'              => 'Hosting',
            'php_wp_versions'      => 'Hosting',
            'php_memory'           => 'Hosting',
            'object_cache'         => 'Hosting',
            'ssl_expiry'           => 'Hosting',
            'backup_status'        => 'Hosting',
            'update_availability'  => 'Maintenance',
            'maintenance'          => 'Maintenance',
            'inactive_plugins'     => 'Maintenance',
            'banned_plugins'       => 'Plugin',
            'plugins'              => 'Plugin',
            'security_advisories'  => 'Security',
            'security'             => 'Security',
            'file_editor'          => 'Security',
            'xmlrpc'               => 'Security',
            'db_hygiene'           => 'Database',
            'analytics_tags'       => 'Analytics',
            'accessibility'        => 'Accessibility',
            'content'              => 'Content',
            'forms'                => 'Forms',
            'javascript'           => 'JavaScript',
            'performance'          => 'Performance',
            'seo'                  => 'SEO',
            'theme'                => 'Theme',
            'browser'              => 'Browser',
            'preflight'            => 'Preflight',
            'golive'               => 'GoLive',
            'woocommerce'          => 'WooCommerce',
            'wc_pages'             => 'WooCommerce',
            'wc_cart_checkout'     => 'WooCommerce',
            'wc_coupon'            => 'WooCommerce',
            'wc_order_lifecycle'   => 'WooCommerce',
            'wc_gateways'          => 'WooCommerce',
            'wc_failed_orders'     => 'WooCommerce',
        ];

        $map = apply_filters('msm_check_categories', $map);
        return $map[$check_id] ?? 'General';
    }

    /**
     * Preferred category display order (matches docs).
     *
     * @return array<int, string>
     */
    public static function category_order(): array {
        return [
            'General',
            'Hosting',
            'Security',
            'Maintenance',
            'Plugin',
            'Database',
            'Logic',
            'WooCommerce',
            'Analytics',
            'Accessibility',
            'Content',
            'Forms',
            'JavaScript',
            'Performance',
            'SEO',
            'Theme',
            'Browser',
            'Preflight',
            'GoLive',
            'Site',
        ];
    }

    /**
     * WooCommerce check pack.
     *
     * @return array<int, Check_Interface>
     */
    private static function woocommerce_checks(): array {
        return [
            new Checks_Woocommerce\Check_Wc_Pages(),
            new Checks_Woocommerce\Check_Wc_Cart_Checkout(),
            new Checks_Woocommerce\Check_Wc_Coupon(),
            new Checks_Woocommerce\Check_Wc_Order_Lifecycle(),
            new Checks_Woocommerce\Check_Wc_Gateways(),
            new Checks_Woocommerce\Check_Wc_Failed_Orders(),
            new Checks_Woocommerce\Check_Woocommerce(),
        ];
    }
}
