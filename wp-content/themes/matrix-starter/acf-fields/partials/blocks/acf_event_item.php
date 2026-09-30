<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$event_item = new FieldsBuilder('event_item', [
    'label' => 'Event item',
]);

$event_item
    ->addTab('Content', ['placement' => 'top'])
        ->addText('title', ['label' => 'Title', 'required' => 1])
        ->addDatePicker('date', ['label' => 'Date', 'display_format' => 'd F Y', 'return_format' => 'Y-m-d'])
        ->addText('location', ['label' => 'Location'])
        ->addLink('link', ['label' => 'Link']);

return $event_item;
