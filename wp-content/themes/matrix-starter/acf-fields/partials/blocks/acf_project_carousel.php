<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$project_carousel = new FieldsBuilder('project_carousel', [
    'label' => 'Project carousel',
]);

$project_carousel
    ->addTab('Content', ['placement' => 'top'])
        ->addText('heading', ['label' => 'Heading', 'default_value' => 'Featured projects'])
        ->addLink('browse_link', ['label' => 'Browse all link'])
        ->addRepeater('cards', [
            'label' => 'Cards',
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
            ->addText('status', ['label' => 'Status'])
            ->addText('title', ['label' => 'Title', 'required' => 1])
            ->addLink('link', ['label' => 'Link'])
            ->addTextarea('excerpt', ['label' => 'Excerpt', 'rows' => 2])
        ->endRepeater();

return $project_carousel;
