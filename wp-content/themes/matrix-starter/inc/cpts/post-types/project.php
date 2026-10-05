<?php
/**
 * CPT – Project
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    if (!function_exists('register_extended_post_type')) {
        return;
    }

    register_extended_post_type(
        'project',
        [
            'menu_icon'     => 'dashicons-location-alt',
            'supports'      => ['title', 'thumbnail', 'revisions', 'excerpt'],
            'public'        => true,
            'show_ui'       => true,
            'show_in_menu'  => true,
            'show_in_rest'  => true,
            'has_archive'   => true,
            'rewrite'       => ['slug' => 'projects', 'with_front' => false],
            'menu_position' => 21,
            'capability_type' => 'post',
            'map_meta_cap'  => true,
            'admin_cols'    => [
                'featured_image' => [
                    'title'          => 'Image',
                    'featured_image' => 'thumbnail',
                ],
                'project_category' => [
                    'taxonomy' => 'project_category',
                ],
                'status' => [
                    'title'    => 'Status',
                    'meta_key' => 'project_status',
                ],
            ],
        ],
        [
            'singular' => 'Project',
            'plural'   => 'Projects',
            'slug'     => 'projects',
        ]
    );
});
