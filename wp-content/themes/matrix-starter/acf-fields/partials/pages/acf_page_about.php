<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * About page content — Figma 3:567 desktop, 3:732 mobile.
 * Used with templates/page-about.php
 */

$page_about = new FieldsBuilder('page_about_content', [
    'title' => 'About page content',
]);

$page_about
    ->setLocation('page_template', '==', 'templates/page-about.php')

    ->addTab('Banner', ['placement' => 'left'])
        ->addImage('banner_image', [
            'label' => 'Banner image',
            'return_format' => 'id',
            'preview_size' => 'medium',
        ])
        ->addText('banner_caption', [
            'label' => 'Banner caption',
            'default_value' => '',
        ])

    ->addTab('Content', ['placement' => 'left'])
        ->addText('challenge_heading', [
            'label' => 'Challenge heading',
            'default_value' => 'The Challenge',
        ])
        ->addWysiwyg('challenge_body', [
            'label' => 'Challenge body',
            'toolbar' => 'basic',
            'media_upload' => 0,
            'tabs' => 'visual',
        ])
        ->addText('approach_heading', [
            'label' => 'Approach heading',
            'default_value' => 'Our Approach',
        ])
        ->addWysiwyg('approach_body', [
            'label' => 'Approach body',
            'toolbar' => 'basic',
            'media_upload' => 0,
            'tabs' => 'visual',
        ])
        ->addImage('inline_image', [
            'label' => 'Inline image',
            'return_format' => 'id',
            'preview_size' => 'medium',
        ])
        ->addText('inline_caption', [
            'label' => 'Inline image caption',
        ])
        ->addText('values_heading', [
            'label' => 'Core values heading',
            'default_value' => 'Our Core Values',
        ])
        ->addRepeater('core_values', [
            'label' => 'Core values',
            'layout' => 'block',
            'button_label' => 'Add value',
            'min' => 0,
        ])
            ->addText('label', ['label' => 'Value name'])
            ->addTextarea('description', [
                'label' => 'Description',
                'rows' => 2,
            ])
        ->endRepeater()
        ->addColorPicker('values_card_background', [
            'label' => 'Values card background',
            'default_value' => '#fffae8',
        ])
        ->addColorPicker('section_background', [
            'label' => 'Section background',
            'default_value' => '#ffffff',
        ])

    ->addTab('Sidebar', ['placement' => 'left'])
        ->addImage('portrait_image', [
            'label' => 'Portrait image',
            'return_format' => 'id',
            'preview_size' => 'medium',
        ])
        ->addText('glance_heading', [
            'label' => 'At a glance heading',
            'default_value' => 'AT A GLANCE',
        ])
        ->addRepeater('glance_items', [
            'label' => 'At a glance items',
            'layout' => 'table',
            'button_label' => 'Add row',
            'min' => 0,
        ])
            ->addText('term_label', ['label' => 'Label'])
            ->addText('term_value', ['label' => 'Value'])
        ->endRepeater();

return $page_about;
