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

    ->addTab('Listing', ['placement' => 'top'])
        ->addText('post_source_label', [
            'label' => 'Source label',
            'instructions' => 'Shown in listing meta (e.g. YMCA Moldova, PACE Consortium). Defaults to site name.',
        ])
        ->addTrueFalse('post_is_featured', [
            'label' => 'Featured on blog index',
            'instructions' => 'Highlight as the large featured card on the main News index (one recommended).',
            'default_value' => 0,
            'ui' => 1,
        ])

    ->addTab('Community article')
        ->addText('community_byline_name', [
            'label'        => 'Written by (name)',
            'instructions' => 'Optional. Shown under the article body (e.g. Sarah Kelly). Defaults to the post author display name.',
        ])
        ->addText('community_byline_group', [
            'label'        => 'Written by (group)',
            'instructions' => 'Optional second line. Defaults to the author display name / group.',
        ])
        ->addPostObject('community_linked_event', [
            'label'         => 'Linked event',
            'instructions'  => 'Optional. Shows event details in the article sidebar.',
            'post_type'     => ['event'],
            'return_format' => 'object',
            'allow_null'    => 1,
            'ui'            => 1,
        ]);

return $post_fields;
