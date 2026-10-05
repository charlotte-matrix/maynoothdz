<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Event CPT fields.
 */

$event = new FieldsBuilder('event_details', [
    'title' => 'Event details',
]);

$event
    ->setLocation('post_type', '==', 'event')

    ->addDatePicker('event_date', [
        'label'          => 'Event date',
        'display_format' => 'd F Y',
        'return_format'  => 'Y-m-d',
        'required'       => 1,
    ])
    ->addText('event_time', [
        'label'         => 'Time / schedule',
        'instructions'  => 'e.g. 12:00–16:00 · free, all welcome',
        'default_value' => '',
    ])
    ->addText('event_location', [
        'label' => 'Location',
    ])
    ->addUrl('event_map_url', [
        'label'        => 'Map URL',
        'instructions' => 'Optional link to the map (e.g. /map/?project=…).',
    ]);

return $event;
