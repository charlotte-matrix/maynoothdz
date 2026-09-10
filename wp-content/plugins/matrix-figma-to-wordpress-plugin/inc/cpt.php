<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Register build job CPT.
 */
function matrix_db_register_cpt() {
    register_post_type(
        MATRIX_DB_CPT,
        array(
            'labels'              => array(
                'name'          => 'Build jobs',
                'singular_name' => 'Build job',
                'add_new'       => 'New build',
                'add_new_item'  => 'Create build job',
                'edit_item'     => 'View build job',
            ),
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'supports'            => array('title'),
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'has_archive'         => false,
            'rewrite'             => false,
        )
    );
}
add_action('init', 'matrix_db_register_cpt');
