<?php
/**
 * CPT – Event (community calendar)
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    if (!function_exists('register_extended_post_type')) {
        return;
    }

    register_extended_post_type(
        'event',
        [
            'menu_icon'       => 'dashicons-calendar-alt',
            'supports'        => ['title', 'editor', 'thumbnail', 'author', 'revisions', 'excerpt'],
            'public'          => true,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'show_in_rest'    => true,
            'has_archive'     => false,
            'rewrite'         => ['slug' => 'events', 'with_front' => false],
            'menu_position'   => 22,
            // Share post caps so Contributors can create/edit drafts (not publish).
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'admin_cols'      => [
                'featured_image' => [
                    'title'          => 'Image',
                    'featured_image' => 'thumbnail',
                ],
                'event_date' => [
                    'title'    => 'Date',
                    'meta_key' => 'event_date',
                ],
                'author' => [
                    'title'      => 'Author',
                    'post_field' => 'post_author',
                ],
            ],
        ],
        [
            'singular' => 'Event',
            'plural'   => 'Events',
            'slug'     => 'events',
        ]
    );
});
