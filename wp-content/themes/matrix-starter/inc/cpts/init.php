<?php
// Autoload Custom Post Types
foreach (glob(get_template_directory() . '/inc/cpts/post-types/*.php') as $file) {
    require_once $file;
}

// Autoload Custom Taxonomies
foreach (glob(get_template_directory() . '/inc/cpts/taxonomies/*.php') as $file) {
    require_once $file;
}


require __DIR__ . '/post-types/form-entries.php';