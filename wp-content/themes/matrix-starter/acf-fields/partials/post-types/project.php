<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Project single fields — maps to design/website/project.html (DemoHouse layout, no map).
 */

$project = new FieldsBuilder('project_details', [
    'title' => 'Project details',
]);

$project
    ->setLocation('post_type', '==', 'project')

    ->addTab('Hero', ['placement' => 'top'])
        ->addText('project_eyebrow', [
            'label'         => 'Eyebrow',
            'default_value' => 'Part of the Maynooth Decarbonising Zone',
        ])
        ->addSelect('project_status', [
            'label'         => 'Status',
            'choices'       => [
                'Complete' => 'Complete',
                'Underway' => 'Underway',
                'Planned'  => 'Planned',
                'Example'  => 'Example',
            ],
            'default_value' => 'Complete',
            'allow_null'    => 0,
            'ui'            => 1,
        ])
        ->addText('project_location_label', [
            'label'         => 'Location label',
            'default_value' => 'Maynooth, County Kildare',
        ])
        ->addTextarea('project_lead', [
            'label'     => 'Lead / short description',
            'rows'      => 3,
            'new_lines' => '',
        ])

    ->addTab('Map')
        ->addTrueFalse('project_show_on_map', [
            'label'         => 'Show on map',
            'instructions'  => 'When off, this project is hidden from the interactive map (e.g. DemoHouse privacy).',
            'ui'            => 1,
            'default_value' => 1,
        ])
        ->addNumber('project_map_x', [
            'label'         => 'Map pin X (%)',
            'instructions'  => 'Horizontal position on the town map, 0–100. Pins use % of the map image so they stay aligned when the map resizes.',
            'min'           => 0,
            'max'           => 100,
            'step'          => 0.1,
            'prepend'       => '%',
        ])
            ->conditional('project_show_on_map', '==', '1')
        ->addNumber('project_map_y', [
            'label'         => 'Map pin Y (%)',
            'instructions'  => 'Vertical position on the town map, 0–100 (from the top).',
            'min'           => 0,
            'max'           => 100,
            'step'          => 0.1,
            'prepend'       => '%',
        ])
            ->conditional('project_show_on_map', '==', '1')

    ->addTab('Story')
        ->addTextarea('project_demo_note', [
            'label'        => 'Top note (optional)',
            'instructions' => 'Small grey note above the story (e.g. demonstration disclaimer).',
            'rows'         => 2,
            'new_lines'    => '',
        ])
        ->addText('project_why_heading', [
            'label'         => 'Story heading',
            'default_value' => 'Why this project',
        ])
        ->addWysiwyg('project_why_body', [
            'label'        => 'Story body',
            'instructions' => 'Main narrative paragraphs.',
            'tabs'         => 'all',
            'toolbar'      => 'basic',
            'media_upload' => 0,
            'delay'        => 0,
        ])
        ->addImage('project_media_image', [
            'label'         => 'Story image',
            'return_format' => 'array',
            'preview_size'  => 'medium',
        ])
        ->addText('project_media_caption', [
            'label' => 'Story image caption',
        ])
        ->addWysiwyg('project_story_extra', [
            'label'        => 'Extra story note (optional)',
            'instructions' => 'e.g. privacy note under the image.',
            'tabs'         => 'all',
            'toolbar'      => 'basic',
            'media_upload' => 0,
            'delay'        => 0,
        ])

    ->addTab('At a glance')
        ->addText('project_glance_heading', [
            'label'         => 'Glance heading',
            'default_value' => 'At a glance',
        ])
        ->addRepeater('project_glance_items', [
            'label'        => 'Glance items',
            'layout'       => 'table',
            'button_label' => 'Add item',
        ])
            ->addText('label', ['label' => 'Label', 'required' => 1])
            ->addText('value', ['label' => 'Value', 'required' => 1])
            ->addTrueFalse('is_pending', [
                'label'         => 'Pending style',
                'instructions'  => 'Use smaller “to be confirmed” styling.',
                'ui'            => 1,
                'default_value' => 0,
            ])
        ->endRepeater()
        ->addGallery('project_grant_icons', [
            'label'        => 'Grant / measure icons (optional)',
            'instructions' => 'Small icons under the glance card (e.g. solar, BER, saving energy).',
            'return_format'=> 'array',
            'preview_size' => 'thumbnail',
        ])

    ->addTab('Partners')
        ->addText('project_partners_heading', [
            'label'         => 'Partners heading',
            'default_value' => 'Partners',
        ])
        ->addGallery('project_partner_logos', [
            'label'         => 'Partner logos',
            'return_format' => 'array',
            'preview_size'  => 'medium',
        ])
        ->addTextarea('project_partners_fallback', [
            'label'        => 'Fallback text',
            'instructions' => 'Shown when no logos are set.',
            'rows'         => 2,
            'new_lines'    => '',
            'default_value'=> 'Project partners to be confirmed.',
        ])

    ->addTab('Documents')
        ->addTrueFalse('project_enable_summary', [
            'label'         => 'Enable HTML summary download',
            'instructions'  => 'Generates a printable HTML summary from this project’s content.',
            'ui'            => 1,
            'default_value' => 1,
        ])
        ->addText('project_summary_title', [
            'label'         => 'Download title',
            'default_value' => '',
            'instructions'  => 'Defaults to “{Project title} — project summary”.',
        ])
        ->addText('project_summary_meta', [
            'label'         => 'Download meta line',
            'default_value' => 'Demonstration content · printable, accessible HTML',
        ])

    ->addTab('Related & CTA')
        ->addTrueFalse('project_show_related', [
            'label'         => 'Show related projects',
            'ui'            => 1,
            'default_value' => 1,
        ])
        ->addText('project_related_heading', [
            'label'         => 'Related heading',
            'default_value' => 'Related projects',
        ])
        ->addText('project_cta_heading', [
            'label' => 'CTA heading',
        ])
        ->addTextarea('project_cta_text', [
            'label'     => 'CTA text',
            'rows'      => 2,
            'new_lines' => '',
        ])
        ->addLink('project_cta_button', [
            'label'         => 'CTA button',
            'return_format' => 'array',
        ]);

return $project;
