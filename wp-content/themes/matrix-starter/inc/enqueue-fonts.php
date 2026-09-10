<?php
// File: inc/enqueue-fonts.php
/**
 * Enqueue Google Fonts — keep in sync with assets/css/app.css @import.
 * Families: Public Sans (theme), Montserrat (PACE headings/CTAs), Comfortaa (PACE body).
 */
function matrix_starter_google_fonts_url() {
  return 'https://fonts.googleapis.com/css2?family=Comfortaa:wght@400;700&family=Montserrat:ital,wght@0,400;0,600;0,700;0,800;1,400&family=Public+Sans:ital,wght@0,100..900;1,100..900&display=swap';
}

function matrix_starter_enqueue_fonts() {
  wp_enqueue_style(
    'matrix-google-fonts',
    matrix_starter_google_fonts_url(),
    [],
    null
  );
}
add_action('wp_enqueue_scripts', 'matrix_starter_enqueue_fonts', 5);
/**
 * Optional: Resource hints for Google Fonts
 */
function matrix_starter_resource_hints( $hints, $relation_type ) {
  if ( 'preconnect' === $relation_type ) {
    $hints[] = 'https://fonts.googleapis.com';
    $hints[] = 'https://fonts.gstatic.com';
  }
  return $hints;
}
add_filter( 'wp_resource_hints', 'matrix_starter_resource_hints', 10, 2 );