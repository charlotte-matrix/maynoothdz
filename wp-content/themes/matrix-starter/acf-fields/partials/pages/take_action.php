<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Take Action pathway — design/website/take-action.html.
 * Primary: take_action CPT singles. Legacy: page template still supported.
 */

$take_action = new FieldsBuilder('take_action_page', [
    'title' => 'Take Action pathway',
]);

$take_action
    ->setLocation('post_type', '==', 'take_action')
    ->or('page_template', '==', 'templates/page-take-action.php')

    ->addTab('Hero', ['placement' => 'top'])
        ->addText('ta_tag_label', [
            'label'         => 'Tag label',
            'default_value' => 'Retrofit pathway',
        ])
        ->addSelect('ta_tag_style', [
            'label'         => 'Tag style',
            'choices'       => [
                'retrofit'     => 'Retrofit',
                'transport'    => 'Transport',
                'biodiversity' => 'Biodiversity',
                'energy'       => 'Energy',
                'public-realm' => 'Public realm',
                'community'    => 'Community',
            ],
            'default_value' => 'retrofit',
            'ui'            => 1,
        ])
        ->addImage('ta_tag_icon', [
            'label'         => 'Tag icon',
            'return_format' => 'array',
            'preview_size'  => 'thumbnail',
            'instructions'  => 'Defaults to the home/retrofit icon if empty.',
        ])
        ->addText('ta_title', [
            'label'         => 'Title',
            'default_value' => 'Retrofit your home',
        ])
        ->addTextarea('ta_intro', [
            'label'         => 'Intro',
            'rows'          => 3,
            'new_lines'     => '',
            'default_value' => 'Six steps from “where do I start?” to a warmer home — with guidance on grants, finding contractors and learning from local experience.',
        ])

    ->addTab('Steps')
        ->addRepeater('ta_steps', [
            'label'        => 'Pathway steps',
            'layout'       => 'block',
            'button_label' => 'Add step',
            'min'          => 1,
            'max'          => 8,
        ])
            ->addText('tab_label', [
                'label'    => 'Tab label',
                'required' => 1,
            ])
            ->addText('title', [
                'label'        => 'Step title',
                'instructions' => 'e.g. Step 1 — Understand your home',
                'required'     => 1,
            ])
            ->addRepeater('blocks', [
                'label'        => 'Content blocks',
                'layout'       => 'block',
                'button_label' => 'Add block',
                'instructions' => 'Alternate text and resource links to match the design order.',
            ])
                ->addSelect('type', [
                    'label'         => 'Type',
                    'choices'       => [
                        'text'     => 'Text paragraph',
                        'resource' => 'Resource link',
                    ],
                    'default_value' => 'text',
                    'ui'            => 1,
                ])
                ->addTextarea('text', [
                    'label'     => 'Text',
                    'rows'      => 3,
                    'new_lines' => '',
                ])
                    ->conditional('type', '==', 'text')
                ->addText('resource_label', [
                    'label' => 'Link label',
                ])
                    ->conditional('type', '==', 'resource')
                ->addUrl('resource_url', [
                    'label' => 'Link URL',
                ])
                    ->conditional('type', '==', 'resource')
                ->addSelect('resource_icon', [
                    'label'         => 'Trailing icon',
                    'choices'       => [
                        '↗' => 'External ↗',
                        '→' => 'Internal →',
                    ],
                    'default_value' => '↗',
                ])
                    ->conditional('type', '==', 'resource')
            ->endRepeater()
        ->endRepeater()
        ->addLink('ta_final_cta', [
            'label'         => 'Final step button',
            'instructions'  => 'Shown instead of “Next” on the last step (e.g. Explore retrofit projects).',
            'return_format' => 'array',
        ])

    ->addTab('Case study')
        ->addPostObject('ta_case_project', [
            'label'         => 'Featured project',
            'instructions'  => 'Used for the sidebar card and the nearby photo link. Defaults to DemoHouse Retrofit when seeded.',
            'post_type'     => ['project'],
            'return_format' => 'object',
            'ui'            => 1,
            'allow_null'    => 1,
        ])
        ->addText('ta_case_title', [
            'label'         => 'Aside title',
            'default_value' => 'See it done: DemoHouse',
            'instructions'  => 'Leave blank to use “See it done: {project title}”.',
        ])
        ->addText('ta_case_cta', [
            'label'         => 'Aside CTA',
            'default_value' => 'Read the case study →',
        ])
        ->addImage('ta_case_image', [
            'label'         => 'Aside image override',
            'instructions'  => 'Optional. Falls back to the project’s featured image.',
            'return_format' => 'array',
            'preview_size'  => 'medium',
        ])

    ->addTab('Nearby CTA')
        ->addText('ta_nearby_tag', [
            'label'         => 'Tag label',
            'default_value' => 'Retrofit in Maynooth',
        ])
        ->addText('ta_nearby_heading', [
            'label'         => 'Heading',
            'default_value' => "See retrofit projects\nnear you.",
            'instructions'  => 'Use a line break for two lines.',
        ])
        ->addTextarea('ta_nearby_text', [
            'label'         => 'Text',
            'rows'          => 3,
            'new_lines'     => '',
            'default_value' => 'Explore local projects, see the homes behind the stories and find inspiration for your own next step.',
        ])
        ->addLink('ta_nearby_button', [
            'label'         => 'Map button',
            'return_format' => 'array',
        ])
        ->addText('ta_nearby_photo_label', [
            'label'         => 'Main photo label',
            'default_value' => 'Inside DemoHouse Retrofit',
            'instructions'  => 'Leave blank to use “Inside {project title}”.',
        ])
        ->addImage('ta_nearby_secondary', [
            'label'         => 'Secondary photo',
            'return_format' => 'array',
            'preview_size'  => 'medium',
        ]);

return $take_action;
