<?php
/**
 * CPT – Take Action pathways
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    if (!function_exists('register_extended_post_type')) {
        return;
    }

    register_extended_post_type(
        'take_action',
        [
            'menu_icon'       => 'dashicons-hammer',
            'supports'        => ['title', 'thumbnail', 'revisions', 'excerpt', 'page-attributes'],
            'public'          => true,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'show_in_rest'    => true,
            'has_archive'     => true,
            'rewrite'         => ['slug' => 'take-action', 'with_front' => false],
            'menu_position'   => 22,
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'admin_cols'      => [
                'featured_image' => [
                    'title'          => 'Image',
                    'featured_image' => 'thumbnail',
                ],
                'theme' => [
                    'title'    => 'Theme',
                    'meta_key' => 'ta_tag_label',
                ],
            ],
        ],
        [
            'singular' => 'Take Action',
            'plural'   => 'Take Actions',
            'slug'     => 'take-action',
        ]
    );
});
