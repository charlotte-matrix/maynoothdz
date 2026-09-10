<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$admin_controls = new FieldsBuilder('admin_controls', [
    'label' => 'Admin Controls',
]);

$admin_controls
    ->addGroup('admin_dashboard_controls', [
        'label' => 'Dashboard Controls',
    ])
        ->addTrueFalse('hide_comments_menu', [
            'label' => 'Hide Comments from WP Admin',
            'instructions' => 'Hides Comments menu and comment shortcuts for admins/editors.',
            'ui' => 1,
            'default_value' => 0,
        ])
        ->addTrueFalse('hide_acf_menu', [
            'label' => 'Hide ACF from WP Admin',
            'instructions' => 'Hides the ACF field groups menu in dashboard.',
            'ui' => 1,
            'default_value' => 0,
        ])
        ->addTrueFalse('disable_comments_sitewide', [
            'label' => 'Disable Comments Sitewide',
            'instructions' => 'Closes comments/pings on frontend and removes comment support from post types.',
            'ui' => 1,
            'default_value' => 0,
        ])
        ->addTrueFalse('delete_all_comments_on_save', [
            'label' => 'Delete All Comments (one-time)',
            'instructions' => 'Turn ON and save options to permanently delete all comments. This resets to OFF automatically after running.',
            'ui' => 1,
            'default_value' => 0,
        ])
    ->endGroup();

return $admin_controls;
