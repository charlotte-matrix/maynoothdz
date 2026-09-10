<?php
// File: inc/flexible-content-functions.php

/**
 * Load Flexible Content Templates
 * 
 * Automatically loads flexible content templates based on the layout name
 */
function load_flexible_content_templates($post_id = null)
{
  // If no post_id is provided, use the current page's ID
  if (!$post_id) {
    $post_id = is_home() ? get_option('page_for_posts') : get_the_ID();
  }

  // Debugging: Log which page ID is being used
  error_log("Loading Flexible Content for Post ID: " . $post_id);

  if ($post_id && have_rows('flexible_content_blocks', $post_id)) {
    while (have_rows('flexible_content_blocks', $post_id)) : the_row();
      $layout = get_row_layout();
      $template_path = get_template_directory() . '/template-parts/flexi/' . $layout . '.php';

      if (file_exists($template_path)) {
        get_template_part('template-parts/flexi/' . $layout);
      } else {
        error_log("Missing flexible content template file: {$layout}.php");
      }
    endwhile;
  } else {
    error_log("No ACF Flexible Content Blocks found for Post ID: " . $post_id);
  }
}
/**
 * Get Available Flexible Content Layouts
 * 
 * Returns an array of available layout names based on template files
 */
function get_available_flexi_layouts()
{
  $flexi_path = get_template_directory() . '/template-parts/flexi/';
  $files = glob($flexi_path . '*.php');

  return array_map(function ($file) {
    return basename($file, '.php');
  }, $files);
}

/**
 * Validate Flexible Content Layout
 * 
 * Ensures that ACF field definitions have corresponding template files
 */
function validate_flexi_layout($layout_name)
{
  $available_layouts = get_available_flexi_layouts();
  if (!in_array($layout_name, $available_layouts)) {
    error_log("Warning: ACF flexible content layout '{$layout_name}' has no corresponding template file");
    return false;
  }
  return true;
}

function force_hero_as_first_block($value, $post_id, $field)
{
  if ($field['name'] === 'flexible_content_layout') {
    $hero_block = [];
    $other_blocks = [];

    foreach ($value as $block) {
      if ($block['acf_fc_layout'] === 'hero_001') {
        $hero_block = $block;
      } else {
        $other_blocks[] = $block;
      }
    }

    // Always place hero first
    if (!empty($hero_block)) {
      array_unshift($other_blocks, $hero_block);
    }

    return $other_blocks;
  }
  return $value;
}
add_filter('acf/update_value/name=flexible_content_layout', 'force_hero_as_first_block', 10, 3);

function apply_acf_to_blog_page($query)
{
  if (!is_admin() && $query->is_home() && $query->is_main_query()) {
    $query->set('page_id', get_option('page_for_posts'));
  }
}
add_action('pre_get_posts', 'apply_acf_to_blog_page');
