<?php
// File: theme-options/projects.php

use StoutLogic\AcfBuilder\FieldsBuilder;

$projectsFields = new FieldsBuilder('projects_archive_fields');

$projectsFields
    ->addGroup('projects_archive', [
        'label'        => 'Projects directory',
        'instructions' => 'Content for the /projects/ archive (listing page).',
    ])
        ->addText('title', [
            'label'         => 'Title',
            'default_value' => 'Local projects & case studies',
        ])
        ->addTextarea('intro', [
            'label'         => 'Intro',
            'rows'          => 3,
            'new_lines'     => '',
            'default_value' => 'Discover the projects helping Maynooth take climate action — and the people and places making it happen.',
        ])
        ->addTextarea('sample_note', [
            'label'         => 'Sample note',
            'instructions'  => 'Small grey note above the results grid. Leave blank to hide.',
            'rows'          => 2,
            'new_lines'     => '',
            'default_value' => 'Demonstration directory · Includes sample projects and illustrative photos.',
        ])
        ->addText('search_placeholder', [
            'label'         => 'Search placeholder',
            'default_value' => 'Search projects by name',
        ])
        ->addUrl('map_url', [
            'label'         => 'Map URL (empty state)',
            'instructions'  => 'Used by the “See the map” button when no results match.',
            'default_value' => '/map/',
        ])
        ->addNumber('page_size', [
            'label'         => 'Projects per page',
            'default_value' => 9,
            'min'           => 1,
            'max'           => 48,
        ])
    ->endGroup();

return $projectsFields;
