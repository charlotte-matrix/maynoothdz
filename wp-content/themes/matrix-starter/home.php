<?php
/**
 * Posts index template (blog home) — PACE News index (Figma 3:1233 / 3:1412).
 */

get_header();
?>
<main id="main-content" class="site-main w-full overflow-hidden">
<?php
get_template_part('template-parts/hero/subhero', null, matrix_pace_blog_subhero_args());
get_template_part('template-parts/blog/pace-listing');
get_template_part('template-parts/flexi/newsletter_001');
?>
</main>
<?php
get_footer();
