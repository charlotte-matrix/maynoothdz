<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$rich_text = new FieldsBuilder('rich_text', [
    'label' => 'Rich text',
]);

$rich_text
    ->addTab('Content', ['placement' => 'top'])
        ->addWysiwyg('content', [
            'label' => 'Content',
            'instructions' => 'Headings, paragraphs, lists, links, and images with captions.',
            'media_upload' => 1,
            'toolbar' => 'full',
            'delay' => 0,
        ]);

return $rich_text;
