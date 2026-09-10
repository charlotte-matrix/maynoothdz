<?php
// File: theme-options/back_to_top.php

use StoutLogic\AcfBuilder\FieldsBuilder;

$backToTopFields = new FieldsBuilder('back_to_top_fields');

$backToTopFields
  ->addGroup('back_to_top_settings', [
    'label' => 'Back to Top Button Settings',
  ])
  // Enable Back to Top Button
  ->addTrueFalse('enable_back_to_top', [
    'label'        => 'Enable Back to Top Button',
    'instructions' => 'Check to enable the back-to-top button.',
    'ui'           => 1,
    'default_value' => 1,
  ])
  // Background Color
  ->addColorPicker('button_bg_color', [
    'label' => 'Button Background Color',
    'default_value' => '#025A70',
    'return_format' => 'string',
  ])
  // Hover Background Color
  ->addColorPicker('button_hover_bg_color', [
    'label' => 'Hover Background Color',
    'default_value' => '#02485A',
    'return_format' => 'string',
  ])
  ->addAccordion('back_to_top_settings_end')->endpoint();

return $backToTopFields;
