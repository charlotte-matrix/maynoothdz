<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$compact_hero = new FieldsBuilder('compact_hero', [
    'label' => 'Compact hero',
]);

$compact_hero
    ->addTab('Content', ['placement' => 'top'])
        ->addText('eyebrow', [
            'label' => 'Eyebrow',
            'instructions' => 'Optional small label above the title.',
        ])
        ->addTextarea('title', [
            'label' => 'Title',
            'instructions' => 'Use line breaks for stacked title lines.',
            'rows' => 2,
            'new_lines' => '',
        ])
        ->addTextarea('introduction', [
            'label' => 'Introduction',
            'rows' => 3,
            'new_lines' => 'br',
        ])
        ->addLink('primary_cta', [
            'label' => 'Primary action',
            'instructions' => 'Optional. Solid button.',
        ])
        ->addLink('secondary_cta', [
            'label' => 'Secondary action',
            'instructions' => 'Optional. Outline button.',
        ])
        ->addImage('image', [
            'label' => 'Image',
            'return_format' => 'array',
            'preview_size' => 'medium',
        ])
        ->addTrueFalse('show_breadcrumbs', [
            'label' => 'Show breadcrumbs',
            'instructions' => 'Display Home / current page above the title.',
            'ui' => 1,
            'default_value' => 1,
        ]);

return $compact_hero;
