<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$partner_strip = new FieldsBuilder('partner_strip', [
    'label' => 'Partner strip',
]);

$partner_strip
    ->addTab('Content', ['placement' => 'top'])
        ->addRepeater('partners', [
            'label' => 'Partner logos',
            'layout' => 'table',
            'button_label' => 'Add logo',
        ])
            ->addImage('logo', ['label' => 'Logo', 'return_format' => 'array', 'required' => 1])
        ->endRepeater();

return $partner_strip;
