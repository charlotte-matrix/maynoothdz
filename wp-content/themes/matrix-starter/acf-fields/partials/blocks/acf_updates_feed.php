<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$updates_feed = new FieldsBuilder('updates_feed', [
    'label' => 'Updates feed',
]);

$updates_feed
    ->addTab('Content', ['placement' => 'top'])
        ->addSelect('feed_mode', [
            'label'         => 'Source',
            'choices'       => [
                'recent'   => 'Most recent Community posts',
                'selected' => 'Selected posts',
            ],
            'default_value' => 'recent',
            'ui'            => 1,
        ])
        ->addNumber('recent_count', [
            'label'         => 'Number of posts',
            'default_value' => 3,
            'min'           => 1,
            'max'           => 12,
        ])
            ->conditional('feed_mode', '==', 'recent')
        ->addRelationship('selected_posts', [
            'label'         => 'Selected posts',
            'post_type'     => ['post'],
            'filters'       => ['search', 'taxonomy'],
            'return_format' => 'id',
            'min'           => 0,
            'max'           => 12,
        ])
            ->conditional('feed_mode', '==', 'selected')
        ->addGroup('empty_state', ['label' => 'Empty state'])
            ->addText('title', ['label' => 'Title', 'default_value' => 'Community updates are coming'])
            ->addTextarea('text', ['label' => 'Text', 'rows' => 2, 'default_value' => 'Hear from your group, in its own voice — new stories will appear as groups publish.'])
        ->endGroup();

return $updates_feed;
