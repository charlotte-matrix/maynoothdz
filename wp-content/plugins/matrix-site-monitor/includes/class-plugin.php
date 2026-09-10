<?php
/**
 * Main plugin bootstrap.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

use Matrix_Site_Monitor\Admin\Admin;

defined('ABSPATH') || exit;

class Plugin {
    /** @var self|null */
    private static $instance = null;

    /** @var bool */
    private $booted = false;

    /**
     * Singleton instance.
     */
    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Boot the plugin.
     */
    public function boot(): void {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        Storage::maybe_upgrade();
        Fatal_Error_Logger::prune_stored_noise();
        Settings::maybe_seed_opt_in_checks();
        Scheduler::register_hooks();
        // Defer cron sync until init so cron_schedules translations are not loaded early (WP 6.7+).
        add_action('init', [Scheduler::class, 'sync_all'], 20);

        (new Fatal_Error_Logger())->register_hooks();

        if (is_admin()) {
            (new Admin())->register_hooks();
        }

        if (class_exists('WooCommerce')) {
            Mailguard::init();
        }

        Rest::register();

        if (defined('WP_CLI') && WP_CLI) {
            require_once MSM_DIR . 'includes/class-cli.php';
            \WP_CLI::add_command('msm', 'Matrix_Site_Monitor\\CLI');
        }

        $this->maybe_load_site_profile();
    }

    /**
     * Load optional per-site profile config.
     */
    private function maybe_load_site_profile(): void {
        $profile = MSM_DIR . 'config/site-profile.php';
        if (is_readable($profile)) {
            require_once $profile;
        }

        $this->maybe_load_site_checks();
    }

    /**
     * Load optional per-site check classes from config/site-checks/.
     */
    private function maybe_load_site_checks(): void {
        $dir = MSM_DIR . 'config/site-checks';
        if (! is_dir($dir)) {
            return;
        }

        $helper_files = glob($dir . '/class-*-helpers.php') ?: [];
        sort($helper_files);
        foreach ($helper_files as $helpers) {
            if (is_readable($helpers)) {
                require_once $helpers;
            }
        }

        $files = glob($dir . '/class-check-*.php') ?: [];
        sort($files);
        foreach ($files as $file) {
            if (is_readable($file)) {
                require_once $file;
            }
        }
    }
}
