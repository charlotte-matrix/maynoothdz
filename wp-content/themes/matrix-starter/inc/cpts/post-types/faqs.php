<?php
/**
 * Plugin Name: CPT – FAQs
 */

if (!defined('ABSPATH')) exit;

add_action('init', function () {

    /* CPT: faq */
    register_extended_post_type(
        'faq',
        [
            'menu_icon'       => 'dashicons-editor-help',
            'supports'        => ['title','editor','revisions'],
            'public'          => true,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'show_in_rest'    => true,
            'has_archive'     => true,
            'rewrite'         => ['slug' => 'faqs', 'with_front' => false],
            'menu_position'   => 20,
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ],
        [
            'singular' => 'FAQ',
            'plural'   => 'FAQs',
            'slug'     => 'faqs',
        ]
    );

});