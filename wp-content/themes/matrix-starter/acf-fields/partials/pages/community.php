<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Community page — design/website/community.html
 * Template: templates/page-community.php
 */

$community = new FieldsBuilder('community_page', [
    'title' => 'Community page',
]);

$community
    ->setLocation('page_template', '==', 'templates/page-community.php')

    ->addTab('Hero', ['placement' => 'top'])
        ->addText('cm_tag_label', [
            'label'         => 'Tag label',
            'default_value' => 'Together in Maynooth',
        ])
        ->addImage('cm_tag_icon', [
            'label'         => 'Tag icon',
            'return_format' => 'array',
            'preview_size'  => 'thumbnail',
        ])
        ->addText('cm_title', [
            'label'         => 'Title',
            'default_value' => 'Community',
        ])
        ->addTextarea('cm_intro', [
            'label'         => 'Intro',
            'rows'          => 2,
            'new_lines'     => '',
            'default_value' => 'What Maynooth’s groups, schools and clubs are doing — and how yours can join in.',
        ])

    ->addTab('Updates')
        ->addText('cm_updates_heading', [
            'label'         => 'Heading',
            'default_value' => 'Latest updates',
        ])
        ->addNumber('cm_updates_count', [
            'label'         => 'Number of recent posts',
            'default_value' => 6,
            'min'           => 1,
            'max'           => 24,
        ])

    ->addTab('Events')
        ->addText('cm_events_heading', [
            'label'         => 'Heading',
            'default_value' => 'Events',
        ])
        ->addText('cm_events_empty_title', [
            'label'         => 'Empty title',
            'default_value' => 'No upcoming events just yet',
        ])
        ->addTextarea('cm_events_empty_text', [
            'label'         => 'Empty text',
            'rows'          => 2,
            'new_lines'     => '',
            'default_value' => 'Follow the updates — new events will appear here as groups come on board.',
        ])

    ->addTab('Join')
        ->addText('cm_join_heading', [
            'label'         => 'Heading',
            'default_value' => 'Your group can publish here',
        ])
        ->addRepeater('cm_join_steps', [
            'label'        => 'Steps',
            'layout'       => 'table',
            'button_label' => 'Add step',
            'max'          => 6,
        ])
            ->addText('title', ['label' => 'Title'])
            ->addTextarea('text', ['label' => 'Text', 'rows' => 2, 'new_lines' => ''])
        ->endRepeater()
        ->addText('cm_join_button_label', [
            'label'         => 'Form submit label',
            'default_value' => 'Register your group’s interest',
        ])

    ->addTab('Newsletter')
        ->addText('cm_newsletter_heading', [
            'label'         => 'Heading',
            'default_value' => 'Stay in the loop',
        ])
        ->addTextarea('cm_newsletter_intro', [
            'label'         => 'Intro',
            'rows'          => 2,
            'new_lines'     => '',
            'default_value' => 'Zone news and community events, straight to your inbox.',
        ]);

return $community;
