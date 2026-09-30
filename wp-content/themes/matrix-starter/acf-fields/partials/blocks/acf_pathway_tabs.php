<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$pathway_tabs = new FieldsBuilder('pathway_tabs', [
    'label' => 'Pathway tabs',
]);

$pathway_tabs
    ->addTab('Content', ['placement' => 'top'])
        ->addRepeater('steps', [
            'label' => 'Steps',
            'layout' => 'block',
            'button_label' => 'Add step',
            'min' => 1,
            'max' => 8,
        ])
            ->addText('tab_label', ['label' => 'Tab label', 'required' => 1])
            ->addText('title', ['label' => 'Step title', 'required' => 1])
            ->addTextarea('body', ['label' => 'Body', 'rows' => 4, 'new_lines' => 'br'])
            ->addRepeater('resources', [
                'label' => 'Resource links',
                'layout' => 'table',
                'button_label' => 'Add link',
            ])
                ->addText('label', ['label' => 'Label'])
                ->addUrl('url', ['label' => 'URL'])
                ->addSelect('icon', [
                    'label' => 'Trailing icon',
                    'choices' => ['↗' => 'External ↗', '→' => 'Internal →'],
                    'default_value' => '↗',
                ])
            ->endRepeater()
        ->endRepeater()
        ->addGroup('aside', ['label' => 'Aside case study'])
            ->addImage('image', ['label' => 'Image', 'return_format' => 'array'])
            ->addText('title', ['label' => 'Title'])
            ->addText('cta_label', ['label' => 'CTA label', 'default_value' => 'Read the case study →'])
            ->addLink('link', ['label' => 'Link'])
        ->endGroup();

return $pathway_tabs;
