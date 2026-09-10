<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$navigationFields = new FieldsBuilder('navigation_settings');

$navigationFields
    ->addGroup('navigation_settings_start', [
        'label' => 'Navigation Settings',
    ])
        ->addText('phone_number', [
            'label' => 'Phone Number',
            'instructions' => 'Enter the phone number to display in the header (e.g., +353 1 283 2967)',
            'placeholder' => '+353 1 283 2967',
        ])
        ->addLink('contact_button', [
            'label' => 'Header CTA button',
            'instructions' => 'Yellow pill on the right (Figma: “Resources Hub”). Used in desktop header and mobile menu.',
        ])
    ->addAccordion('navigation_settings_end')->endpoint();

return $navigationFields;
