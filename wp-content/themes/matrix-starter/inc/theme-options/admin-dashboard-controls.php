<?php

if (!defined('ABSPATH')) {
    exit;
}

function matrix_get_admin_dashboard_controls(): array
{
    if (!function_exists('get_field')) {
        return [];
    }
    $settings = get_field('admin_dashboard_controls', 'option');
    return is_array($settings) ? $settings : [];
}

function matrix_admin_control_enabled(string $key): bool
{
    $settings = matrix_get_admin_dashboard_controls();
    return !empty($settings[$key]);
}

function matrix_should_hide_comments_admin_ui(): bool
{
    return matrix_admin_control_enabled('hide_comments_menu') || matrix_admin_control_enabled('disable_comments_sitewide');
}

add_action('admin_menu', function () {
    if (matrix_should_hide_comments_admin_ui()) {
        remove_menu_page('edit-comments.php');
    }

    if (matrix_admin_control_enabled('hide_acf_menu')) {
        remove_menu_page('edit.php?post_type=acf-field-group');
        remove_menu_page('acf-settings-tools');
    }
}, 999);

add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!($wp_admin_bar instanceof WP_Admin_Bar)) {
        return;
    }

    if (matrix_should_hide_comments_admin_ui()) {
        $wp_admin_bar->remove_node('comments');
    }
}, 999);

add_action('wp_dashboard_setup', function () {
    if (matrix_should_hide_comments_admin_ui()) {
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    }
}, 999);

add_action('init', function () {
    if (!matrix_admin_control_enabled('disable_comments_sitewide')) {
        return;
    }

    $post_types = get_post_types([], 'names');
    foreach ($post_types as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
        }
        if (post_type_supports($post_type, 'trackbacks')) {
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
}, 20);

add_filter('comments_open', function ($open) {
    if (matrix_admin_control_enabled('disable_comments_sitewide')) {
        return false;
    }
    return $open;
}, 20);

add_filter('pings_open', function ($open) {
    if (matrix_admin_control_enabled('disable_comments_sitewide')) {
        return false;
    }
    return $open;
}, 20);

add_filter('comments_array', function ($comments) {
    if (matrix_admin_control_enabled('disable_comments_sitewide')) {
        return [];
    }
    return $comments;
}, 20);

add_action('acf/save_post', function ($post_id) {
    if ($post_id !== 'options' || !function_exists('get_field') || !function_exists('update_field')) {
        return;
    }

    $settings = matrix_get_admin_dashboard_controls();
    if (empty($settings['delete_all_comments_on_save'])) {
        return;
    }

    $deleted_count = 0;
    while (true) {
        $comment_ids = get_comments([
            'status' => 'all',
            'number' => 500,
            'fields' => 'ids',
            'orderby' => 'comment_ID',
            'order' => 'ASC',
        ]);

        if (empty($comment_ids)) {
            break;
        }

        foreach ($comment_ids as $comment_id) {
            if (wp_delete_comment((int) $comment_id, true)) {
                $deleted_count++;
            }
        }
    }

    $settings['delete_all_comments_on_save'] = 0;
    update_field('admin_dashboard_controls', $settings, 'option');
    set_transient('matrix_admin_controls_notice', sprintf('Deleted %d comments.', $deleted_count), 60);
}, 40);

add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    $msg = get_transient('matrix_admin_controls_notice');
    if (!$msg) {
        return;
    }
    delete_transient('matrix_admin_controls_notice');
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
});
