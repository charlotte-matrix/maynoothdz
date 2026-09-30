<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#203129">
    <?php wp_head(); ?>
</head>
<body <?php body_class('dz-site'); ?>>

    <?php wp_body_open(); ?>
    <a class="skip-link" href="#main-content"><?php esc_html_e('Skip to content', 'matrix-starter'); ?></a>
    <?php
    if (function_exists('matrix_donations_is_donation_flow') && matrix_donations_is_donation_flow()) {
        do_action('matrix_donations_header');
    } else {
        get_template_part('template-parts/header/navbar');
    }
    ?>
