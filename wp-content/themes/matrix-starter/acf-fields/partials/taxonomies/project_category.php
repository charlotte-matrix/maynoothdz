<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Project category term meta — colour + icon for chips.
 */

$term = new FieldsBuilder('project_category_meta', [
    'title' => 'Category display',
]);

$term
    ->setLocation('taxonomy', '==', 'project_category')
    ->addColorPicker('category_color', [
        'label'         => 'Chip colour',
        'default_value' => '#203129',
    ])
    ->addImage('category_icon', [
        'label'         => 'Chip icon',
        'return_format' => 'array',
        'preview_size'  => 'thumbnail',
        'instructions'  => 'Optional. Falls back to theme default icons by slug.',
    ]);

return $term;
