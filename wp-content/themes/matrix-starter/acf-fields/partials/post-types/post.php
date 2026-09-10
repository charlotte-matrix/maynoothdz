<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Post fields — PACE blog listing + singles.
 */

$post_fields = new FieldsBuilder('post_pace', [
    'title' => 'Post (PACE)',
]);

$post_fields
    ->setLocation('post_type', '==', 'post')
    ->addText('post_source_label', [
        'label' => 'Source label',
        'instructions' => 'Shown in listing meta (e.g. YMCA Moldova, PACE Consortium). Defaults to site name.',
    ])
    ->addTrueFalse('post_is_featured', [
        'label' => 'Featured on blog index',
        'instructions' => 'Highlight as the large featured card on the main News index (one recommended).',
        'default_value' => 0,
        'ui' => 1,
    ]);

return $post_fields;
