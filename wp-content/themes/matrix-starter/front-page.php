<?php
/**
 * Front page — PACE hero + flexi blocks (no breadcrumbs / empty content wrapper).
 *
 * @package Matrix_Starter
 */

get_header();
?>
<main id="main-content" class="site-main w-full overflow-hidden">
    <?php load_hero_templates(); ?>
    <?php load_flexible_content_templates(); ?>
</main>
<?php
get_footer();
