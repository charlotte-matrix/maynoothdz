<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Resource category archive hero overrides (Figma 4:554 / 4:718).
 */

$resource_category_archive = new FieldsBuilder('resource_category_archive');

$resource_category_archive
    ->setLocation('taxonomy', '==', 'resource_category')
    ->addText('archive_kicker', [
        'label' => 'Hero kicker',
        'instructions' => 'Uppercase label above the title (e.g. FOR YOUTH WORKERS).',
    ])
    ->addText('archive_title', [
        'label' => 'Hero title',
        'instructions' => 'Overrides the category name when set.',
    ])
    ->addTextarea('archive_intro', [
        'label' => 'Hero intro',
        'rows' => 3,
    ]);

return $resource_category_archive;
