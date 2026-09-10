<?php
// File: inc/theme-options/footer.php

use StoutLogic\AcfBuilder\FieldsBuilder;

$fields = new FieldsBuilder('footer');

$fields
    ->addAccordion('footer_branding_acc', [
        'label' => 'Branding',
        'open'  => 1,
    ])
        ->addImage('footer_logo', [
            'label' => 'Footer logo',
            'instructions' => 'PACE logo for footer (recommended ~77×36px). Falls back to site logo if empty.',
            'return_format' => 'id',
            'preview_size' => 'medium',
        ])
        ->addTextarea('footer_tagline', [
            'label' => 'Tagline',
            'instructions' => 'Shown below the logo in yellow. Use a line break for two lines on desktop.',
            'rows' => 2,
            'new_lines' => 'br',
            'default_value' => "Supporting Youth.\nStrengthening Ukraine.",
        ])
        ->addImage('footer_eu_logo', [
            'label' => 'EU co-funded logo',
            'instructions' => '“Co-funded by the European Union” badge below the tagline (desktop and mobile).',
            'return_format' => 'id',
            'preview_size' => 'medium',
        ])
    ->addAccordion('footer_branding_acc_end')->endpoint()

    ->addAccordion('footer_columns_acc', [
        'label' => 'Columns',
    ])
        ->addText('footer_col1_heading', [
            'label' => 'Column 1 heading',
            'default_value' => 'PROJECT',
            'instructions' => 'Menu: Footer — Project (footer_one)',
        ])
        ->addText('footer_col2_heading', [
            'label' => 'Column 2 heading',
            'default_value' => "WHAT'S NEW",
            'instructions' => 'Menu: Footer — What\'s New (footer_two)',
        ])
        ->addText('footer_col3_heading', [
            'label' => 'Column 3 heading',
            'default_value' => 'RESOURCES HUB',
            'instructions' => 'Menu: Footer — Resources Hub (footer_three)',
        ])
        ->addText('footer_col4_heading', [
            'label' => 'Column 4 heading',
            'default_value' => 'CONTACT',
        ])
        ->addTaxonomy('footer_whats_new_categories', [
            'label' => "What's New categories",
            'instructions' => 'Post categories for the footer What\'s New column. Re-save or run wp pace-footer setup --force to rebuild the menu.',
            'taxonomy' => 'category',
            'field_type' => 'multi_select',
            'return_format' => 'id',
            'add_term' => 0,
            'save_terms' => 0,
            'load_terms' => 0,
            'allow_null' => 1,
        ])
        ->addTaxonomy('footer_resources_categories', [
            'label' => 'Resources Hub categories',
            'instructions' => 'Post categories for the Resources Hub column.',
            'taxonomy' => 'category',
            'field_type' => 'multi_select',
            'return_format' => 'id',
            'add_term' => 0,
            'save_terms' => 0,
            'load_terms' => 0,
            'allow_null' => 1,
        ])
        ->addEmail('footer_contact_email', [
            'label' => 'Contact email',
            'default_value' => 'info@pace-project.eu',
        ])
        ->addRepeater('footer_social_links', [
            'label' => 'Social links',
            'instructions' => 'Circular social buttons in the Contact column.',
            'button_label' => 'Add social link',
            'layout' => 'row',
            'min' => 0,
            'max' => 6,
            'collapsed' => 'label',
        ])
            ->addText('label', [
                'label' => 'Label',
                'instructions' => 'Accessibility label (e.g. LinkedIn).',
                'default_value' => 'LinkedIn',
            ])
            ->addSelect('icon', [
                'label' => 'Icon',
                'choices' => [
                    'linkedin'  => 'LinkedIn',
                    'facebook'  => 'Facebook',
                    'instagram' => 'Instagram',
                ],
                'default_value' => 'linkedin',
                'return_format' => 'value',
            ])
            ->addLink('link', [
                'label' => 'Profile link',
                'return_format' => 'array',
                'required' => 1,
            ])
        ->endRepeater()
    ->addAccordion('footer_columns_acc_end')->endpoint()

    ->addAccordion('footer_legal_acc', [
        'label' => 'Legal & funding',
    ])
        ->addTextarea('footer_disclaimer', [
            'label' => 'EU funding disclaimer',
            'rows' => 5,
            'new_lines' => 'wpautop',
            'default_value' => 'Funded by the European Union. Views and opinions expressed are however those of the author(s) only and do not necessarily reflect those of the European Union or the European Education and Culture Executive Agency (EACEA). Neither the European Union nor EACEA can be held responsible for them.',
        ])
        ->addText('footer_project_number', [
            'label' => 'Project number',
            'default_value' => 'Project Number: 2025-1-HR01-KA220-SCH-000360813',
        ])
        ->addText('footer_copyright_left', [
            'label' => 'Copyright text',
            'instructions' => 'Use {year} for the current year.',
            'default_value' => '© {year} PACE Project Consortium',
        ])
        ->addLink('footer_privacy_link', [
            'label' => 'Privacy policy link',
            'instructions' => 'Leave empty to use links from the “PACE Footer — Legal” menu (copyright location).',
            'return_format' => 'array',
        ])
        ->addLink('footer_cookie_link', [
            'label' => 'Cookie policy link',
            'instructions' => 'Leave empty to use links from the “PACE Footer — Legal” menu (copyright location).',
            'return_format' => 'array',
        ])
        ->addText('footer_credit_prefix', [
            'label' => 'Design credit prefix',
            'default_value' => 'Designed & developed by',
        ])
        ->addLink('footer_credit_link', [
            'label' => 'Design credit link',
            'instructions' => 'Typically “Matrix Internet”.',
            'return_format' => 'array',
        ])
    ->addAccordion('footer_legal_acc_end')->endpoint()

    ->addAccordion('footer_colors_acc', [
        'label' => 'Colors',
    ])
        ->addColorPicker('footer_main_bg', [
            'label' => 'Main background',
            'default_value' => '#003b65',
            'instructions' => 'PACE navy behind the link columns.',
        ])
        ->addColorPicker('footer_disclaimer_bg', [
            'label' => 'Disclaimer bar overlay (optional)',
            'instructions' => 'Leave empty for Figma default: black at 18% on navy (rgba(0,0,0,0.18)).',
        ])
        ->addColorPicker('footer_bottom_bg', [
            'label' => 'Copyright bar overlay (optional)',
            'instructions' => 'Leave empty for Figma default: black at 30% on navy (rgba(0,0,0,0.3)).',
        ])
    ->addAccordion('footer_colors_acc_end')->endpoint();

return $fields;
