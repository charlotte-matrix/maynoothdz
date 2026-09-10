<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
    <style>[x-cloak]{ display:none !important; }</style>

</head>
<body <?php body_class(); ?>>

    <?php wp_body_open(); ?>
    <a class="skip-link" href="#main-content"><?php esc_html_e('Skip to content', 'matrix-starter'); ?></a>
    <style>
        .skip-link {
            position: absolute;
            top: -9999px;
            left: 1rem;
            z-index: 100000;
            padding: 0.75rem 1rem;
            border-radius: 9999px;
            background: #00628f;
            color: #ffffff;
            font-weight: 700;
            text-decoration: none;
        }

        .skip-link:focus,
        .skip-link:focus-visible {
            top: 1rem;
            outline: 3px solid #1c959b;
            outline-offset: 2px;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var mainEl = document.getElementById('main-content') || document.querySelector('main');
            if (!mainEl) return;
            if (!mainEl.id) mainEl.id = 'main-content';

            var skipLink = document.querySelector('.skip-link');
            if (!skipLink) return;
            skipLink.addEventListener('click', function () {
                mainEl.setAttribute('tabindex', '-1');
                mainEl.focus();
            });
        });
    </script>
    <header class="relative">
        <?php
        if ( function_exists( 'matrix_donations_is_donation_flow' ) && matrix_donations_is_donation_flow() ) {
            do_action( 'matrix_donations_header' );
        } else {
            get_template_part( 'template-parts/header/navbar' );
        }
        ?>
    </header>