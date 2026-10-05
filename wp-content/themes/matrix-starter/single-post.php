<?php
/**
 * Single post template — community articles use the DZ article design.
 *
 * @package Matrix_Starter
 */

if (have_posts()) {
    the_post();
    rewind_posts();
    if (matrix_dz_is_community_post((int) get_the_ID())) {
        include get_template_directory() . '/templates/single-community.php';
        return;
    }
}

// Fall back to the generic singular template.
include get_template_directory() . '/single.php';
