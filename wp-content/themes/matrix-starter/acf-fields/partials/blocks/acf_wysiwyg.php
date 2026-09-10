<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$wysiwyg = new FieldsBuilder('wysiwyg', [
    'label' => 'General WYSIWYG Section',
]);

$wysiwyg
  ->addTab('Content', ['placement' => 'top'])
    ->addWysiwyg('text_content', [
        'label' => 'Content',
        'instructions' => 'Enter the content using headings, paragraphs, and inline formatting as needed.',
        'wrapper' => ['class' => 'relative'],
        'media_upload' => 0,
        'toolbar' => 'full',
    ])

  ->addTab('Layout')
    ->addRepeater('padding_settings', [
        'label' => 'Padding Settings',
        'instructions' => 'Customize padding for different screen sizes.',
        'button_label' => 'Add Screen Size Padding',
    ])
      ->addSelect('screen_size', [
          'label' => 'Screen Size',
          'choices' => [
              'xxs' => 'xxs',
              'xs' => 'xs',
              'mob' => 'mob',
              'sm' => 'sm',
              'md' => 'md',
              'lg' => 'lg',
              'xl' => 'xl',
              'xxl' => 'xxl',
              'ultrawide' => 'ultrawide',
          ],
      ])
      ->addNumber('padding_top', [
          'label' => 'Padding Top',
          'min' => 0,
          'max' => 20,
          'step' => 0.1,
          'append' => 'rem',
      ])
      ->addNumber('padding_bottom', [
          'label' => 'Padding Bottom',
          'min' => 0,
          'max' => 20,
          'step' => 0.1,
          'append' => 'rem',
      ])
    ->endRepeater();

return $wysiwyg;
