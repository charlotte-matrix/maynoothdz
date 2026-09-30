<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$contact = new FieldsBuilder('contact', [
    'label' => 'Contact block',
]);

$contact
    ->addTab('Content', ['placement' => 'top'])
        ->addText('heading', ['label' => 'Heading', 'default_value' => 'Climate Action Office'])
        ->addTextarea('address', ['label' => 'Address', 'rows' => 3, 'new_lines' => 'br'])
        ->addEmail('email', ['label' => 'Email', 'default_value' => 'climateaction@kildarecoco.ie']);

return $contact;
