<?php
// File: theme-options/blog.php

use StoutLogic\AcfBuilder\FieldsBuilder;

$blogFields = new FieldsBuilder('blog_fields');

$blogFields
  ->addGroup('pace_blog_settings', [
    'label' => 'PACE — News index',
    'instructions' => 'Blog home and category archives (Figma 3:1233 / 3:1412).',
  ])
    ->addText('hero_kicker', [
      'label' => 'Hero kicker',
      'default_value' => "WHAT'S NEW",
    ])
    ->addText('hero_title', [
      'label' => 'Hero title (blog home)',
      'default_value' => 'News, events & media',
    ])
    ->addTextarea('hero_intro', [
      'label' => 'Hero intro (blog home)',
      'rows' => 3,
      'default_value' => 'Latest milestones, updates, and announcements from across the consortium.',
    ])
    ->addColorPicker('hero_background', [
      'label' => 'Hero background',
      'default_value' => '#003b65',
    ])
    ->addSelect('decoration_style', [
      'label' => 'Hero decoration',
      'choices' => [
        'yellow_stacked' => 'Yellow stacked (Figma 3:1306)',
        'default_grey'   => 'Default grey',
        'blue_stacked'   => 'Blue stacked',
        'yellow_wave'    => 'Yellow wave',
      ],
      'default_value' => 'yellow_stacked',
      'ui' => 1,
    ])
    ->addNumber('posts_per_page', [
      'label' => 'Posts per page (grid)',
      'instructions' => 'Featured post is separate on the blog home.',
      'default_value' => 5,
      'min' => 1,
      'max' => 24,
    ])
    ->addText('read_more_label', [
      'label' => 'Read more label',
      'default_value' => 'Read more →',
    ])
    ->addText('featured_label', [
      'label' => 'Featured badge',
      'default_value' => 'FEATURED',
    ])
    ->addText('category_slugs', [
      'label' => 'Category tab slugs',
      'instructions' => 'Comma-separated slugs for tab navigation (order preserved).',
      'default_value' => 'news,success-stories,press-releases,events',
    ])
  ->endGroup();

return $blogFields;
