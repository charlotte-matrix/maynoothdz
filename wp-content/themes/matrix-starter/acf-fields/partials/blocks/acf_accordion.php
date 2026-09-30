<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$accordion = new FieldsBuilder('accordion', [
    'label' => 'Accordion',
]);

$accordion
    ->addTab('Content', ['placement' => 'top'])
        ->addRepeater('items', [
            'label' => 'Items',
            'layout' => 'block',
            'button_label' => 'Add item',
            'min' => 1,
        ])
            ->addText('summary', [
                'label' => 'Question / summary',
                'required' => 1,
            ])
            ->addWysiwyg('body', [
                'label' => 'Answer',
                'media_upload' => 0,
                'toolbar' => 'basic',
                'delay' => 0,
            ])
            ->addTrueFalse('open_by_default', [
                'label' => 'Open by default',
                'ui' => 1,
                'default_value' => 0,
            ])
        ->endRepeater();

return $accordion;
