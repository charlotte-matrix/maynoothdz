<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$newsletter = new FieldsBuilder('newsletter', [
    'label' => 'Newsletter sign-up',
]);

$newsletter
    ->addTab('Content', ['placement' => 'top'])
        ->addText('heading', ['label' => 'Heading', 'default_value' => 'Stay in the loop'])
        ->addTextarea('intro', ['label' => 'Intro', 'rows' => 2, 'default_value' => 'Zone news and community events, straight to your inbox.']);

return $newsletter;
