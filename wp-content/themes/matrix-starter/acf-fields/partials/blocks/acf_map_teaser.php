<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$map_teaser = new FieldsBuilder('map_teaser', [
    'label' => 'Map teaser',
]);

$map_teaser
    ->addTab('Content', ['placement' => 'top'])
        ->addText('heading', ['label' => 'Heading', 'default_value' => 'Wander the map'])
        ->addText('subtext', ['label' => 'Subtext', 'default_value' => 'see what Maynooth is already doing.'])
        ->addImage('map_image', ['label' => 'Map image', 'return_format' => 'array'])
        ->addLink('button', ['label' => 'Button']);

return $map_teaser;
