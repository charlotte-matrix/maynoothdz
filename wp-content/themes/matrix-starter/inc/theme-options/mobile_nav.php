<?php
// File: inc/acf/options/theme-options/mobile_nav.php

use StoutLogic\AcfBuilder\FieldsBuilder;

$fields = new FieldsBuilder('mobile_nav');

$fields
  ->addTrueFalse('enable_hamburger', [
    'label'        => 'Enable Hamburger Menu',
    'instructions' => 'Toggle to enable or disable the hamburger menu in mobile navigation.',
    'ui'           => 1,
    'default_value' => 1, // Enabled by default
  ])
  ->addSelect('hamburger_style', [
    'label'        => 'Hamburger Style',
    'instructions' => 'Select the hamburger menu style to use.',
    'choices'      => [
      'hamburger--3dx'          => '3DX',
      'hamburger--3dx-r'        => '3DX Reverse',
      'hamburger--3dy'          => '3DY',
      'hamburger--3dy-r'        => '3DY Reverse',
      'hamburger--3dxy'         => '3DXY',
      'hamburger--3dxy-r'       => '3DXY Reverse',
      'hamburger--arrow'        => 'Arrow',
      'hamburger--arrow-r'      => 'Arrow Reverse',
      'hamburger--arrowalt'     => 'Arrow Alternative',
      'hamburger--arrowalt-r'   => 'Arrow Alternative Reverse',
      'hamburger--arrowturn'    => 'Arrow Turn',
      'hamburger--arrowturn-r'  => 'Arrow Turn Reverse',
      'hamburger--boring'       => 'Boring',
      'hamburger--collapse'     => 'Collapse',
      'hamburger--collapse-r'   => 'Collapse Reverse',
      'hamburger--elastic'      => 'Elastic',
      'hamburger--elastic-r'    => 'Elastic Reverse',
      'hamburger--emphatic'     => 'Emphatic',
      'hamburger--emphatic-r'   => 'Emphatic Reverse',
      'hamburger--minus'        => 'Minus',
      'hamburger--slider'       => 'Slider',
      'hamburger--slider-r'     => 'Slider Reverse',
      'hamburger--spin'         => 'Spin',
      'hamburger--spin-r'       => 'Spin Reverse',
      'hamburger--spring'       => 'Spring',
      'hamburger--spring-r'     => 'Spring Reverse',
      'hamburger--stand'        => 'Stand',
      'hamburger--stand-r'      => 'Stand Reverse',
      'hamburger--squeeze'      => 'Squeeze',
      'hamburger--vortex'       => 'Vortex',
      'hamburger--vortex-r'     => 'Vortex Reverse',
    ],
    'default_value' => 'hamburger--spin', // Default style
    'allow_null'    => 0,
    'ui'            => 1,
    'return_format' => 'value',
    'conditional_logic' => [
      [
        [
          'field' => 'enable_hamburger',
          'operator' => '==',
          'value' => '1',
        ]
      ]
    ],
  ])
  ->addSelect('mobile_menu_effect', [
    'label'        => 'Mobile Menu Open Effect',
    'instructions' => 'Choose the animation effect for the mobile menu.',
    'choices'      => [
      'slide_up'    => 'Slide Up',
      'slide_left'  => 'Slide In from Left',
      'slide_right' => 'Slide In from Right',
      'fullscreen'  => 'Full Screen',
    ],
    'default_value' => 'slide_up',
    'layout'        => 'vertical',
    'conditional_logic' => [
      [
        [
          'field' => 'enable_hamburger',
          'operator' => '==',
          'value' => '1',
        ]
      ]
    ],
  ])
  ->addNumber('mobile_menu_width', [
    'label'       => 'Mobile Menu Width (%)',
    'instructions' => 'Enter width as a percentage (e.g., 80 for 80%).',
    'default_value' => 100,
    'append'        => '%',
    'conditional_logic' => [
      [
        [
          'field' => 'mobile_menu_effect',
          'operator' => '!=',
          'value' => 'fullscreen',
        ],
        [
          'field' => 'enable_hamburger',
          'operator' => '==',
          'value' => '1',
        ]
      ],
    ],
  ])
  ->addColorPicker('mobile_menu_background', [
    'label'       => 'Mobile Menu Background Color',
    'default_value' => '#FFFFFF',
    'conditional_logic' => [
      [
        [
          'field' => 'enable_hamburger',
          'operator' => '==',
          'value' => '1',
        ]
      ]
    ],
  ]);

return $fields;
