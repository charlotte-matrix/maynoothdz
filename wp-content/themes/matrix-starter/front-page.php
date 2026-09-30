<?php
/**
 * Front page — Maynooth DZ flexible content blocks only.
 *
 * @package Matrix_Starter
 */

get_header();
?>
<main id="main-content" class="site-main">
    <?php
    if (function_exists('load_flexible_content_templates')) {
        load_flexible_content_templates();
    }
    ?>
</main>
<?php
get_footer();
