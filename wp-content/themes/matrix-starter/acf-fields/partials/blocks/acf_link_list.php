<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$link_list = new FieldsBuilder('link_list', [
    'label' => 'Link list',
]);

$link_list
    ->addTab('Content', ['placement' => 'top'])
        ->addText('heading', ['label' => 'Group heading', 'default_value' => 'Reading & research'])
        ->addRepeater('items', [
            'label' => 'Links',
            'layout' => 'block',
            'button_label' => 'Add link',
        ])
            ->addText('title', ['label' => 'Title', 'required' => 1])
            ->addTextarea('description', ['label' => 'Description', 'rows' => 2])
            ->addUrl('url', ['label' => 'URL', 'required' => 1])
        ->endRepeater()
        ->addWysiwyg('accessibility_note', [
            'label' => 'Accessibility note',
            'instructions' => 'Optional note shown below the list (e.g. accessible format request).',
            'media_upload' => 0,
            'toolbar' => 'basic',
        ]);

return $link_list;
