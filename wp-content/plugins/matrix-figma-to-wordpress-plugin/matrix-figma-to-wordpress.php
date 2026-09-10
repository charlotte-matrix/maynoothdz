<?php
/**
 * Plugin Name: Matrix Figma to WordPress
 * Plugin URI: https://github.com/Matrix-Internet/matrix-figma-to-wordpress-plugin
 * Description: Turn Figma designs into Matrix Starter theme sections using Cursor Cloud Agents or Local Cursor. Batch Figma links, section-type helpers, /flexi/ review seeding, redo, and QC checks.
 * Version: 0.2.1
 * Author: Matrix
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: matrix-figma-to-wordpress
 */

if (! defined('ABSPATH')) {
    exit;
}

define('MATRIX_DB_VERSION', '0.2.1');
define('MATRIX_DB_FILE', __FILE__);
define('MATRIX_DB_DIR', plugin_dir_path(__FILE__));
define('MATRIX_DB_URL', plugin_dir_url(__FILE__));
define('MATRIX_DB_CAP', 'manage_figma_to_wordpress');
define('MATRIX_DB_CPT', 'matrix_build_job');
define('MATRIX_DB_AGENT_CRON', 'matrix_db_agent_poll_event');

require_once MATRIX_DB_DIR . 'inc/cpt.php';
require_once MATRIX_DB_DIR . 'inc/figma.php';
require_once MATRIX_DB_DIR . 'inc/figma-api.php';
require_once MATRIX_DB_DIR . 'inc/figma-design-brief.php';
require_once MATRIX_DB_DIR . 'inc/figma-assets.php';
require_once MATRIX_DB_DIR . 'inc/section-types.php';
require_once MATRIX_DB_DIR . 'inc/section-features.php';
require_once MATRIX_DB_DIR . 'inc/section-form.php';
require_once MATRIX_DB_DIR . 'inc/jobs.php';
require_once MATRIX_DB_DIR . 'inc/prompt-standards.php';
require_once MATRIX_DB_DIR . 'inc/prompt.php';
require_once MATRIX_DB_DIR . 'inc/agent.php';
require_once MATRIX_DB_DIR . 'inc/local-runner.php';
require_once MATRIX_DB_DIR . 'inc/seed-flexi.php';
require_once MATRIX_DB_DIR . 'inc/redo.php';
require_once MATRIX_DB_DIR . 'inc/qc-checks.php';
require_once MATRIX_DB_DIR . 'inc/settings.php';
require_once MATRIX_DB_DIR . 'inc/admin-ui.php';

/**
 * Activation: register CPT, grant capability, schedule cron.
 */
function matrix_db_activate() {
    matrix_db_register_cpt();

    foreach (array('administrator', 'editor') as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            if (! $role->has_cap(MATRIX_DB_CAP)) {
                $role->add_cap(MATRIX_DB_CAP);
            }
            // Legacy cap from earlier plugin name.
            if (! $role->has_cap('manage_design_build')) {
                $role->add_cap('manage_design_build');
            }
        }
    }

    if (function_exists('matrix_db_agent_ensure_cron')) {
        matrix_db_agent_ensure_cron();
    }

    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'matrix_db_activate');

/**
 * Deactivation: clear cron.
 */
function matrix_db_deactivate() {
    wp_clear_scheduled_hook(MATRIX_DB_AGENT_CRON);
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'matrix_db_deactivate');

/**
 * @return bool
 */
function matrix_db_user_can_manage() {
    return is_user_logged_in() && (current_user_can(MATRIX_DB_CAP) || current_user_can('manage_design_build'));
}
