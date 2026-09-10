<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$category_archive = new FieldsBuilder('category_archive');

$category_archive
    ->setLocation('taxonomy', '==', 'category')
    ->addImage('archive_hero_svg', [
        'label' => 'Archive Hero SVG',
        'instructions' => 'Optional. Upload a category-specific hero SVG/image for archive pages. Leave empty to use the default from Theme Options.',
        'return_format' => 'id',
        'preview_size' => 'medium',
        'library' => 'all',
        'mime_types' => 'svg,png,jpg,jpeg,webp',
    ])
    ->addTextarea('archive_hero_description', [
        'label' => 'Archive Hero Description Override',
        'instructions' => 'Optional. Leave empty to use the standard category description.',
        'rows' => 4,
        'new_lines' => 'br',
    ])
    ->addColorPicker('category_chip_bg_color', [
        'label' => 'Category Chip Background Color',
        'instructions' => 'Optional. Used for category tags/chips in listings. Leave empty to use theme defaults.',
    ])
    ->addColorPicker('category_chip_text_color', [
        'label' => 'Category Chip Text Color',
        'instructions' => 'Optional. Used for category tags/chips in listings. Leave empty to use theme defaults.',
    ]);

return $category_archive;
