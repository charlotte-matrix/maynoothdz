<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$downloads = new FieldsBuilder('downloads', [
    'label' => 'Downloads list',
]);

$downloads
    ->addTab('Content', ['placement' => 'top'])
        ->addText('heading', ['label' => 'Group heading', 'default_value' => 'Plans & strategies'])
        ->addRepeater('items', [
            'label' => 'Items',
            'layout' => 'block',
            'button_label' => 'Add download',
        ])
            ->addText('type', ['label' => 'Type label', 'default_value' => 'PDF'])
            ->addText('name', ['label' => 'Name', 'required' => 1])
            ->addText('meta', ['label' => 'Meta', 'instructions' => 'e.g. 10.1 MB or Request a copy'])
            ->addFile('file', ['label' => 'File upload', 'return_format' => 'array'])
            ->addText('url', [
                'label' => 'External URL',
                'instructions' => 'Used if no file is uploaded. Accepts https:// or mailto: links.',
            ])
        ->endRepeater()
        ->addWysiwyg('accessibility_note', [
            'label' => 'Accessibility note',
            'media_upload' => 0,
            'toolbar' => 'basic',
        ]);

return $downloads;
