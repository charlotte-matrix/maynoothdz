<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Partner CPT fields.
 */

$partner_fields = new FieldsBuilder('partner_details', [
    'title' => 'Partner details',
]);

$partner_fields
    ->setLocation('post_type', '==', 'partner')

    ->addTab('Listing', ['label' => 'Listing (cards)'])
        ->addImage('partner_logo', [
            'label' => 'Logo',
            'instructions' => 'Shown on partner cards and the single-page sidebar.',
            'return_format' => 'id',
            'preview_size' => 'medium',
            'library' => 'all',
        ])
        ->addText('partner_role', [
            'label' => 'Role line (cards)',
            'instructions' => 'Short line on grid cards (e.g. Project Coordinator · Moldova).',
        ])
        ->addTextarea('partner_description', [
            'label' => 'Card description',
            'instructions' => 'Summary for Partners 002 cards.',
            'rows' => 4,
            'new_lines' => 'br',
        ])

    ->addTab('Single page', ['label' => 'Single page'])
        ->addText('partner_detail_region', [
            'label' => 'Detail region label',
            'instructions' => 'Used in hero kicker: PARTNER DETAIL · {region} (e.g. Moldova).',
        ])
        ->addText('partner_hero_subtitle', [
            'label' => 'Hero subtitle',
            'instructions' => 'Line under the title on the single page (e.g. Project Coordinator).',
        ])
        ->addTextarea('partner_lead', [
            'label' => 'Lead paragraph',
            'instructions' => 'Highlighted intro with yellow left border.',
            'rows' => 4,
            'new_lines' => 'br',
        ])
        ->addText('partner_country', [
            'label' => 'Country',
            'instructions' => 'Sidebar info card — COUNTRY.',
        ])
        ->addText('partner_based_in', [
            'label' => 'Based in',
            'instructions' => 'Sidebar info card — BASED IN (city).',
        ])
        ->addText('partner_sidebar_role', [
            'label' => 'Sidebar role',
            'instructions' => 'Sidebar info card — ROLE. Leave empty to use Hero subtitle.',
        ])
        ->addLink('partner_website', [
            'label' => 'External website (optional)',
            'instructions' => 'Opens in a new tab on partner cards and the single-page sidebar. Leave empty to link cards to this partner’s page.',
            'return_format' => 'array',
        ])
        ->addText('partner_website_label', [
            'label' => 'Website link label',
            'instructions' => 'Display text (e.g. ymca.md). Leave empty to derive from URL.',
        ])
        ->addText('back_to_partners_label', [
            'label' => 'Back button label',
            'default_value' => '← Back to all partners',
        ]);

return $partner_fields;
