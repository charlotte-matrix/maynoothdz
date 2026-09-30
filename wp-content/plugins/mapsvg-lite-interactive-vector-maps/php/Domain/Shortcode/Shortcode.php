<?php

namespace MapSVG;

class Shortcode
{

  /**
   * Extracts the shortcode name from a shortcode string.
   * Example: [my_shortcode attr="value"] => my_shortcode
   *
   * @param string $shortcode
   * @return string|null
   */
  public static function getName(string $shortcode): ?string
  {
    if (preg_match('/\\[\\s*([a-zA-Z0-9_\\-]+)\\b/', $shortcode, $matches)) {
      return $matches[1];
    }
    return null;
  }
}
