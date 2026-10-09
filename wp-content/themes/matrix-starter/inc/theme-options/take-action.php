<?php
// File: theme-options/take-action.php

use StoutLogic\AcfBuilder\FieldsBuilder;

$takeActionFields = new FieldsBuilder('take_action_archive_fields');

$takeActionFields
    ->addGroup('take_action_archive', [
        'label'        => 'Take Action directory',
        'instructions' => 'Content for the /take-action/ archive (listing page).',
    ])
        ->addText('title', [
            'label'         => 'Title',
            'default_value' => 'Take action in Maynooth',
        ])
        ->addTextarea('intro', [
            'label'         => 'Intro',
            'rows'          => 3,
            'new_lines'     => '',
            'default_value' => 'Practical pathways to cut carbon at home, on the move, and in the community — pick a theme and follow the steps.',
        ])
        ->addTextarea('sample_note', [
            'label'         => 'Sample note',
            'instructions'  => 'Optional grey note above the results grid. Leave blank to hide.',
            'rows'          => 2,
            'new_lines'     => '',
            'default_value' => '',
        ])
        ->addText('search_placeholder', [
            'label'         => 'Search placeholder',
            'default_value' => 'Search actions by name',
        ])
        ->addNumber('page_size', [
            'label'         => 'Actions per page',
            'default_value' => 9,
            'min'           => 1,
            'max'           => 48,
        ])
    ->endGroup();

return $takeActionFields;
