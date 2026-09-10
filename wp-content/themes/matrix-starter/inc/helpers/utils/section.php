<?php

/**
 * Utility Functions (IDs, Custom CSS)
 */

// Generate a Unique Section ID
function get_section_id($prefix = 'section')
{
  return esc_attr($prefix . '-' . uniqid());
}

// Get Custom CSS if Available
function get_custom_css($custom_css)
{
  return !empty($custom_css) ? '<style>' . esc_html($custom_css) . '</style>' : '';
}
