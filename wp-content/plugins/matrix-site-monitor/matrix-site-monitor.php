<?php
/**
 * Plugin Name: Matrix Site Monitor
 * Description: Proactive health checks for managed WordPress sites — baseline monitoring, optional WooCommerce synthetic journeys, multi-recipient alerts, and orchestrator-ready status API.
 * Version:     1.0.5
 * Author:      Matrix Internet
 * Author URI:  https://www.matrixinternet.ie/
 * License:     GPL-2.0-or-later
 * Text Domain: matrix-site-monitor
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Matrix_Site_Monitor
 */

defined('ABSPATH') || exit;

define('MSM_VERSION', '1.0.5');
define('MSM_FILE', __FILE__);
define('MSM_DIR', plugin_dir_path(__FILE__));
define('MSM_URL', plugin_dir_url(__FILE__));

/** Order meta flag for synthetic WooCommerce test orders. */
define('MSM_SELFTEST_META', '_msm_selftest');

/** Cron hooks. */
define('MSM_CRON_LIGHT', 'msm_run_light_checks');
define('MSM_CRON_HEAVY', 'msm_run_heavy_checks');
define('MSM_CRON_SYNTHETIC', 'msm_run_synthetic_checks');
define('MSM_CRON_DIGEST', 'msm_send_digest');

/** Options. */
define('MSM_SETTINGS_OPTION', 'msm_settings');
define('MSM_LAST_RESULT_OPTION', 'msm_last_result');
define('MSM_LAST_LIGHT_OPTION', 'msm_last_light_result');
define('MSM_LAST_HEAVY_OPTION', 'msm_last_heavy_result');
define('MSM_LAST_SYNTHETIC_OPTION', 'msm_last_synthetic_result');
define('MSM_ADMIN_BASELINE_OPTION', 'msm_admin_baseline');
define('MSM_FATAL_ERRORS_OPTION', 'msm_fatal_errors');
define('MSM_CHECK_RESULTS_OPTION', 'msm_check_results');
define('MSM_ALERT_STATE_OPTION', 'msm_alert_state');

require_once MSM_DIR . 'includes/class-autoloader.php';

Matrix_Site_Monitor\Autoloader::register();

register_activation_hook(__FILE__, ['Matrix_Site_Monitor\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['Matrix_Site_Monitor\Activator', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    Matrix_Site_Monitor\Plugin::instance()->boot();
}, 20);
