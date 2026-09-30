<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$home_hero = new FieldsBuilder('home_hero', [
    'label' => 'Home hero',
]);

$home_hero
    ->addTab('Content', ['placement' => 'top'])
        ->addTextarea('title', ['label' => 'Title', 'rows' => 3, 'new_lines' => ''])
        ->addTextarea('introduction', ['label' => 'Introduction', 'rows' => 3, 'new_lines' => 'br'])
        ->addLink('primary_cta', ['label' => 'Primary CTA'])
        ->addLink('secondary_cta', ['label' => 'Secondary CTA'])
        ->addImage('map_image', ['label' => 'Map image', 'return_format' => 'array'])
        ->addRepeater('stats', [
            'label' => 'Stats',
            'layout' => 'block',
            'button_label' => 'Add stat',
            'max' => 6,
        ])
            ->addText('value', ['label' => 'Value', 'required' => 1])
            ->addTrueFalse('animate', ['label' => 'Animate', 'ui' => 1, 'default_value' => 1])
            ->addNumber('decimals', ['label' => 'Decimals', 'min' => 0, 'max' => 3, 'default_value' => 0])
            ->addText('suffix', ['label' => 'Suffix'])
            ->addTextarea('label', ['label' => 'Label', 'rows' => 2, 'new_lines' => 'br'])
        ->endRepeater()
        ->addTextarea('source_note', ['label' => 'Source note', 'rows' => 2, 'new_lines' => 'br']);

return $home_hero;
