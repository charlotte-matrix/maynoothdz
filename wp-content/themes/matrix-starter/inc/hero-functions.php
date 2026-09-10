<?php
// File: inc/hero-functions.php

/**
 * Get Available Hero Layouts
 * 
 * Returns an array of available hero layout names based on template files
 */
function get_available_hero_layouts()
{
  $hero_path = get_template_directory() . '/template-parts/hero/';
  $files = glob($hero_path . '*.php');

  return array_map(function ($file) {
    return basename($file, '.php');
  }, $files);
}

/**
 * Validate Hero Layout
 * 
 * Ensures that ACF field definitions have corresponding template files
 */
function validate_hero_layout($layout_name)
{
  $available_layouts = get_available_hero_layouts();
  if (!in_array($layout_name, $available_layouts)) {
    error_log("Warning: ACF hero layout '{$layout_name}' has no corresponding template file");
    return false;
  }
  return true;
}

/**
 * Load Hero Templates
 * 
 * Automatically loads hero templates based on available files in the hero directory
 */
function load_hero_templates($post_id = null)
{
  // If no post_id is provided, use the current page's ID
  if (!$post_id) {
    $post_id = is_home() ? get_option('page_for_posts') : get_the_ID();
  }

  // Debugging: Log which page ID is being used
  error_log("Loading Hero Templates for Post ID: " . $post_id);

  if ($post_id && have_rows('hero_content_blocks', $post_id)) {
    while (have_rows('hero_content_blocks', $post_id)) : the_row();
      $layout = get_row_layout();

      // Check for template file
      $template_path = get_template_directory() . '/template-parts/hero/' . $layout . '.php';
      if (file_exists($template_path)) {
        get_template_part('template-parts/hero/' . $layout);
      } else {
        error_log("Missing hero template file: {$layout}.php");
      }
    endwhile;
  } else {
    error_log("No ACF Hero Blocks found for Post ID: " . $post_id);
  }
}

/**
 * Normalize ACF true/false sub field with a default when unset (e.g. legacy rows).
 */
function matrix_hero_acf_bool($value, $default = false)
{
  if ($value === null || $value === '') {
    return $default;
  }
  return (int) $value === 1;
}

/**
 * Extract a YouTube video ID from common URL formats.
 */
function matrix_hero_parse_youtube_id($url)
{
  $url = trim((string) $url);
  if ($url === '') {
    return '';
  }
  if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([a-zA-Z0-9_-]{11})~', $url, $m)) {
    return $m[1];
  }
  return '';
}

/**
 * Extract a Vimeo video ID from common URL formats.
 */
function matrix_hero_parse_vimeo_id($url)
{
  $url = trim((string) $url);
  if ($url === '') {
    return '';
  }
  if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
    return $m[1];
  }
  return '';
}

/**
 * Build a YouTube embed URL with query flags.
 */
function matrix_hero_youtube_embed_url($video_id, array $opts = [])
{
  $video_id = (string) $video_id;
  if ($video_id === '') {
    return '';
  }
  $defaults = [
    'autoplay' => false,
    'mute' => false,
    'loop' => false,
    'controls' => true,
    'playsinline' => true,
  ];
  $opts = array_merge($defaults, $opts);
  $params = [];
  if (!empty($opts['autoplay'])) {
    $params['autoplay'] = '1';
  }
  if (!empty($opts['mute'])) {
    $params['mute'] = '1';
  }
  if (!empty($opts['loop'])) {
    $params['loop'] = '1';
    $params['playlist'] = $video_id;
  }
  $params['controls'] = !empty($opts['controls']) ? '1' : '0';
  if (!empty($opts['playsinline'])) {
    $params['playsinline'] = '1';
  }
  $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
  return 'https://www.youtube.com/embed/' . rawurlencode($video_id) . ($query !== '' ? '?' . $query : '');
}

/**
 * Build a Vimeo player URL with query flags.
 */
function matrix_hero_vimeo_embed_url($video_id, array $opts = [])
{
  $video_id = (string) $video_id;
  if ($video_id === '') {
    return '';
  }
  $defaults = [
    'autoplay' => false,
    'mute' => false,
    'loop' => false,
    'controls' => true,
    'playsinline' => true,
  ];
  $opts = array_merge($defaults, $opts);
  $params = [];
  if (!empty($opts['autoplay'])) {
    $params['autoplay'] = '1';
  }
  if (!empty($opts['mute'])) {
    $params['muted'] = '1';
  }
  if (!empty($opts['loop'])) {
    $params['loop'] = '1';
  }
  $params['controls'] = !empty($opts['controls']) ? '1' : '0';
  if (!empty($opts['playsinline'])) {
    $params['playsinline'] = '1';
  }
  $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
  return 'https://player.vimeo.com/video/' . rawurlencode($video_id) . ($query !== '' ? '?' . $query : '');
}
