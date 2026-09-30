<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$quote = new FieldsBuilder('quote', [
    'label' => 'Quote / highlight',
]);

$quote
    ->addTab('Content', ['placement' => 'top'])
        ->addTextarea('quote_text', [
            'label' => 'Quote',
            'rows' => 3,
            'new_lines' => 'br',
            'required' => 1,
        ])
        ->addText('attribution', [
            'label' => 'Attribution',
            'instructions' => 'e.g. Organiser · Maynooth Tidy Towns',
        ]);

return $quote;
