<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$take_action_cards = new FieldsBuilder('take_action_cards', [
    'label' => 'Take action cards',
]);

$take_action_cards
    ->addTab('Content', ['placement' => 'top'])
        ->addText('heading', ['label' => 'Heading', 'default_value' => 'Take action'])
        ->addText('section_id', ['label' => 'Section ID', 'default_value' => 'action'])
        ->addRepeater('cards', [
            'label' => 'Cards',
            'layout' => 'block',
            'button_label' => 'Add card',
        ])
            ->addSelect('color', [
                'label' => 'Colour',
                'choices' => [
                    'sand' => 'Sand',
                    'lime' => 'Lime',
                    'olive' => 'Olive',
                    'ochre' => 'Ochre',
                    'sage' => 'Sage',
                    'wheat' => 'Wheat',
                ],
                'default_value' => 'sand',
            ])
            ->addImage('icon', ['label' => 'Icon', 'return_format' => 'array'])
            ->addText('title', ['label' => 'Title', 'required' => 1])
            ->addText('subtitle', ['label' => 'Subtitle'])
            ->addLink('link', ['label' => 'Link'])
        ->endRepeater();

return $take_action_cards;
