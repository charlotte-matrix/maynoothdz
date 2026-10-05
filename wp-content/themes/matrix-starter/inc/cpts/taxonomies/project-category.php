<?php
/**
 * Taxonomy – Project Category (map / directory filters)
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    if (!function_exists('register_extended_taxonomy')) {
        return;
    }

    register_extended_taxonomy(
        'project_category',
        'project',
        [
            'public'       => true,
            'show_ui'      => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'rewrite'      => ['slug' => 'project-category', 'with_front' => false],
            'meta_box'     => 'simple',
        ],
        [
            'singular' => 'Project category',
            'plural'   => 'Project categories',
            'slug'     => 'project-category',
        ]
    );
});
