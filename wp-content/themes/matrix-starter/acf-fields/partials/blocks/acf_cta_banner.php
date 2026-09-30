<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$cta_banner = new FieldsBuilder('cta_banner', [
    'label' => 'CTA banner',
]);

$cta_banner
    ->addTab('Content', ['placement' => 'top'])
        ->addText('heading', [
            'label' => 'Heading',
            'required' => 1,
        ])
        ->addLink('button', [
            'label' => 'Button',
            'required' => 1,
        ]);

return $cta_banner;
