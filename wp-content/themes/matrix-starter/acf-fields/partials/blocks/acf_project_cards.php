<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$project_cards = new FieldsBuilder('project_cards', [
    'label' => 'Project card grid',
]);

$project_cards
    ->addTab('Content', ['placement' => 'top'])
        ->addRepeater('cards', [
            'label' => 'Project cards',
            'layout' => 'block',
            'button_label' => 'Add card',
        ])
            ->addImage('image', ['label' => 'Image', 'return_format' => 'array'])
            ->addSelect('tag', [
                'label' => 'Tag',
                'choices' => [
                    'retrofit' => 'Retrofit',
                    'transport' => 'Transport',
                    'biodiversity' => 'Biodiversity',
                    'nature' => 'Nature',
                    'public-realm' => 'Public realm',
                ],
                'default_value' => 'retrofit',
            ])
            ->addText('status', ['label' => 'Status', 'default_value' => 'Underway'])
            ->addText('title', ['label' => 'Title', 'required' => 1])
            ->addLink('link', ['label' => 'Link'])
            ->addTextarea('excerpt', ['label' => 'Excerpt', 'rows' => 2])
        ->endRepeater()
        ->addGroup('empty_state', ['label' => 'Empty state card'])
            ->addText('eyebrow', ['label' => 'Eyebrow', 'default_value' => 'Room to grow'])
            ->addText('title', ['label' => 'Title', 'default_value' => 'More projects coming'])
            ->addTextarea('text', ['label' => 'Text', 'rows' => 2, 'default_value' => 'New projects will appear here as they start.'])
        ->endGroup();

return $project_cards;
