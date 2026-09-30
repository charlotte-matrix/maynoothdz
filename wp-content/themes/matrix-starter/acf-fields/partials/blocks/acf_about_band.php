<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$about_band = new FieldsBuilder('about_band', [
    'label' => 'About band',
]);

$about_band
    ->addTab('Content', ['placement' => 'top'])
        ->addText('section_id', ['label' => 'Section ID', 'default_value' => 'about'])
        ->addText('heading', ['label' => 'Heading', 'required' => 1])
        ->addTextarea('body', ['label' => 'Body', 'rows' => 4, 'new_lines' => 'br'])
        ->addLink('button', ['label' => 'Button'])
        ->addImage('image', ['label' => 'Photo', 'return_format' => 'array'])
        ->addTrueFalse('show_watermark', ['label' => 'Show brand watermark', 'ui' => 1, 'default_value' => 1]);

return $about_band;
