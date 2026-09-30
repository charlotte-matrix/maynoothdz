<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$community_split = new FieldsBuilder('community_split', [
    'label' => 'Community split',
]);

$community_split
    ->addTab('Content', ['placement' => 'top'])
        ->addText('section_id', ['label' => 'Section ID', 'default_value' => 'community'])
        ->addGroup('community', ['label' => 'Community card'])
            ->addText('heading', ['label' => 'Heading', 'default_value' => 'From the community'])
            ->addImage('image', ['label' => 'Image', 'return_format' => 'array'])
            ->addTextarea('text', ['label' => 'Text', 'rows' => 3])
            ->addLink('button', ['label' => 'Button'])
        ->endGroup()
        ->addGroup('map', ['label' => 'Map teaser card'])
            ->addText('heading', ['label' => 'Heading', 'default_value' => 'Wander the map'])
            ->addText('subtext', ['label' => 'Subtext', 'default_value' => 'see what Maynooth is already doing.'])
            ->addImage('map_image', ['label' => 'Map image', 'return_format' => 'array'])
            ->addLink('button', ['label' => 'Button'])
        ->endGroup();

return $community_split;
