<?php
/**
 * CTA banner — heading + single action.
 */

$heading = trim((string) get_sub_field('heading'));
$button  = get_sub_field('button');

if ($heading === '' && !matrix_dz_link_attrs($button)) {
    return;
}
?>
<section aria-label="<?php echo esc_attr__('Call to action', 'matrix-starter'); ?>" class="dz-flexi flex-cta">
    <div class="container flex-demo">
        <section class="resources-cta">
            <?php if ($heading !== '') : ?>
                <h2><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <?php matrix_dz_render_button($button); ?>
        </section>
    </div>
</section>
