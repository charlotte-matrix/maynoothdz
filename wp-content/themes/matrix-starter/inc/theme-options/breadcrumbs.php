<?php
// File: inc/acf/options/theme-options/breadcrumbs.php

use StoutLogic\AcfBuilder\FieldsBuilder;

$breadcrumbsFields = new FieldsBuilder('breadcrumbs_fields');

$breadcrumbsFields
  ->addGroup('breadcrumbs_settings', [
    'label' => 'Breadcrumb Settings',
  ])
  // Toggle for enabling breadcrumbs
  ->addTrueFalse('enable_breadcrumbs', [
    'label'        => 'Enable Breadcrumbs',
    'instructions' => 'Check to enable the breadcrumb navigation.',
    'ui'           => 1,
    'default_value' => 0,
  ]);

return $breadcrumbsFields;
