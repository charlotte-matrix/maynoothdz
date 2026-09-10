<?php
use StoutLogic\AcfBuilder\FieldsBuilder;

add_action('acf/init', function () {
    acf_add_options_page([
        'page_title'    => get_bloginfo('name') . ' Theme Options',
        'menu_title'    => 'Theme Options',
        'menu_slug'     => 'theme-options',
        'capability'    => 'edit_theme_options',
        'position'      => 999,
        'autoload'      => true,
        'update_button' => 'Update Options',
    ]);

    $dir = __DIR__ . '/theme-options/';

    require_once $dir . 'admin-dashboard-controls.php';

    $files = glob($dir . '*.php');

    $options = new FieldsBuilder('theme_options_tabs', [
        'style' => 'seamless',
        'title' => 'Theme Options',
    ]);

    $options->setLocation('options_page', '==', 'theme-options');

    foreach ($files as $path) {
        if (basename($path) === 'admin-dashboard-controls.php') {
            continue;
        }

        $fields = require $path;

        if ($fields instanceof \StoutLogic\AcfBuilder\FieldsBuilder) {
            $label = basename($path, '.php');
            $label = $label === '404' ? '404 Page' : ucwords(str_replace(['-', '_'], ' ', $label));

            $options
                ->addTab($label, ['placement' => 'top']) // or 'left' for vertical
                ->addFields($fields->getFields());
        }
    }

    acf_add_local_field_group($options->build());
});
