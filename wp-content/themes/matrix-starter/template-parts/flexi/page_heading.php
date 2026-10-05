<?php
/**
 * Page heading — breadcrumbs + title + intro (Resources-style).
 */

$title            = trim((string) get_sub_field('title'));
$introduction     = (string) get_sub_field('introduction');
$show_breadcrumbs = (bool) get_sub_field('show_breadcrumbs');

if ($title === '' && trim(wp_strip_all_tags($introduction)) === '') {
    return;
}
?>
<section aria-label="<?php echo esc_attr__('Page heading', 'matrix-starter'); ?>" class="dz-flexi flex-block flex-page-heading">
    <div class="container resources-content resources-heading-block">
        <?php if ($show_breadcrumbs) : ?>
            <?php matrix_dz_render_breadcrumbs(); ?>
        <?php endif; ?>

        <header class="resources-heading">
            <?php if ($title !== '') : ?>
                <h1><?php echo esc_html($title); ?></h1>
            <?php endif; ?>
            <?php if (trim(wp_strip_all_tags($introduction)) !== '') : ?>
                <p><?php echo wp_kses_post($introduction); ?></p>
            <?php endif; ?>
        </header>
    </div>
</section>
