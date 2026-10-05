<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$page_heading = new FieldsBuilder('page_heading', [
    'label' => 'Page heading',
]);

$page_heading
    ->addTab('Content', ['placement' => 'top'])
        ->addText('title', [
            'label' => 'Title',
            'required' => 1,
            'default_value' => 'Resources',
        ])
        ->addTextarea('introduction', [
            'label' => 'Introduction',
            'rows' => 3,
            'new_lines' => 'br',
        ])
        ->addTrueFalse('show_breadcrumbs', [
            'label' => 'Show breadcrumbs',
            'instructions' => 'Display Home / current page above the title.',
            'ui' => 1,
            'default_value' => 1,
        ]);

return $page_heading;
