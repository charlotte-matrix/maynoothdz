<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Resource CPT fields — archive cards (Figma 4:554).
 */

$resource_fields = new FieldsBuilder('resource_pace', [
    'title' => 'Resource (PACE)',
]);

$resource_fields
    ->setLocation('post_type', '==', 'resource')
    ->addText('resource_status_label', [
        'label' => 'Status label',
        'instructions' => 'Shown above the title (e.g. Coming soon). Leave empty to hide.',
        'default_value' => 'Coming soon',
    ])
    ->addText('resource_format', [
        'label' => 'Format',
        'instructions' => 'First segment of meta line (e.g. Module, Workshop).',
        'default_value' => 'Module',
    ])
    ->addText('resource_duration', [
        'label' => 'Duration',
        'instructions' => 'Middle segment (e.g. 90 min, 2 hrs).',
    ])
    ->addText('resource_track', [
        'label' => 'Track / category label',
        'instructions' => 'Last segment (e.g. Train-the-Trainer).',
        'default_value' => 'Train-the-Trainer',
    ])
    ->addText('resource_cta_label', [
        'label' => 'Card link label',
        'default_value' => 'View module →',
    ])
    ->addUrl('resource_external_url', [
        'label' => 'External URL',
        'instructions' => 'Optional. When set, the card links here instead of the single resource page.',
    ]);

return $resource_fields;
