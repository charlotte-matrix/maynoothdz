<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$updates_feed = new FieldsBuilder('updates_feed', [
    'label' => 'Updates feed',
]);

$updates_feed
    ->addTab('Content', ['placement' => 'top'])
        ->addRepeater('updates', [
            'label' => 'Updates',
            'layout' => 'block',
            'button_label' => 'Add update',
        ])
            ->addImage('image', ['label' => 'Image', 'return_format' => 'array'])
            ->addText('author', ['label' => 'Author / group'])
            ->addText('avatar_initials', ['label' => 'Avatar initials', 'default_value' => 'MT'])
            ->addText('title', ['label' => 'Title', 'required' => 1])
            ->addLink('link', ['label' => 'Link'])
            ->addTextarea('excerpt', ['label' => 'Excerpt', 'rows' => 3])
            ->addDatePicker('date', ['label' => 'Date', 'display_format' => 'd F Y', 'return_format' => 'Y-m-d'])
        ->endRepeater()
        ->addGroup('empty_state', ['label' => 'Empty state'])
            ->addText('title', ['label' => 'Title', 'default_value' => 'Community updates are coming'])
            ->addTextarea('text', ['label' => 'Text', 'rows' => 2, 'default_value' => 'Hear from your group, in its own voice — new stories will appear as groups publish.'])
        ->endGroup();

return $updates_feed;
