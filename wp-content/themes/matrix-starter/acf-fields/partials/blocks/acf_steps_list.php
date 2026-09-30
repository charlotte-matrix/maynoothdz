<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$steps_list = new FieldsBuilder('steps_list', [
    'label' => 'Steps / numbered list',
]);

$steps_list
    ->addTab('Content', ['placement' => 'top'])
        ->addRepeater('steps', [
            'label' => 'Steps',
            'layout' => 'block',
            'button_label' => 'Add step',
        ])
            ->addText('title', ['label' => 'Title', 'required' => 1])
            ->addTextarea('description', ['label' => 'Description', 'rows' => 2])
        ->endRepeater();

return $steps_list;
