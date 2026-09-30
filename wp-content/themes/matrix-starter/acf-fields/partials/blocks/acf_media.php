<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$media = new FieldsBuilder('media', [
    'label' => 'Media',
]);

$media
    ->addTab('Content', ['placement' => 'top'])
        ->addRepeater('items', [
            'label' => 'Media items',
            'layout' => 'block',
            'button_label' => 'Add item',
            'max' => 2,
        ])
            ->addSelect('type', [
                'label' => 'Type',
                'choices' => [
                    'image' => 'Image',
                    'video' => 'Video placeholder',
                ],
                'default_value' => 'image',
            ])
            ->addImage('image', ['label' => 'Image', 'return_format' => 'array'])
                ->conditional('type', '==', 'image')
            ->addText('video_title', ['label' => 'Video title'])
                ->conditional('type', '==', 'video')
            ->addUrl('video_url', ['label' => 'Video URL (optional)'])
                ->conditional('type', '==', 'video')
            ->addTextarea('caption', ['label' => 'Caption', 'rows' => 2])
        ->endRepeater();

return $media;
