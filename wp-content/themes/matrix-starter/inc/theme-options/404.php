<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$not_found = new FieldsBuilder('not_found', [
  'label' => '404 Page',
]);

$not_found
  ->addGroup('not_found_settings', [
    'label' => '404 Page Settings',
  ])
  ->addTab('Content', ['placement' => 'top'])
  ->addText('hero_title', [
    'label' => 'Hero Title',
    'default_value' => 'Page not found',
  ])
  ->addWysiwyg('hero_text', [
    'label' => 'Hero Text',
    'default_value' => 'The page you are looking for may have been moved, renamed, or no longer exists.',
  ])
  ->addImage('hero_image', [
    'label' => 'Hero Image',
    'instructions' => 'Image shown on the left side of the 404 layout.',
    'return_format' => 'array',
    'preview_size' => 'medium',
    'library' => 'all',
  ])
  ->addRepeater('links', [
    'label' => 'Helpful Links',
    'layout' => 'table',
    'button_label' => 'Add Link',
  ])
  ->addLink('link_data', [
    'label' => 'Link',
    'return_format' => 'array',
    'default_value' => [
      'url' => home_url('/'),
      'title' => 'Go to Homepage',
      'target' => '_self',
    ],
  ])
  ->endRepeater()
  ->addTab('Design')
  ->addColorPicker('background_color', [
    'label' => 'Background Color',
    'default_value' => '#f1f5f8',
    'instructions' => 'Legacy field — the PACE template uses the standard listing background (#f1f5f8).',
  ])
  ->addColorPicker('text_color', [
    'label' => 'Text Color',
    'default_value' => '#003b65',
    'instructions' => 'Legacy field — typography colours are set by the PACE template.',
  ])
  ->addTab('Options', ['placement' => 'left'])
  ->addTrueFalse('enable_custom_404', [
    'label' => 'Enable Custom 404 Page',
    'instructions' => 'Check to enable a custom 404 page.',
    'ui' => 1,
    'default_value' => 1,
  ])
  ->addColorPicker('error_text_color', [
    'label' => 'Error Text Color',
    'default_value' => '#d9534f',
    'return_format' => 'string',
  ])
  ->addColorPicker('error_background_color', [
    'label' => 'Error Background Color',
    'default_value' => '#f8d7da',
    'return_format' => 'string',
  ])
  ->addAccordion('not_found_options_end')->endpoint();

return $not_found;
