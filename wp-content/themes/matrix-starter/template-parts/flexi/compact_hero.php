<?php
/**
 * Compact hero — title, intro, optional CTAs + image.
 */

$eyebrow          = trim((string) get_sub_field('eyebrow'));
$title            = (string) get_sub_field('title');
$introduction     = (string) get_sub_field('introduction');
$primary_cta      = get_sub_field('primary_cta');
$secondary_cta    = get_sub_field('secondary_cta');
$image            = get_sub_field('image');
$show_breadcrumbs = (bool) get_sub_field('show_breadcrumbs');

$title_html = matrix_dz_title_html($title);
$has_actions = matrix_dz_link_attrs($primary_cta) || matrix_dz_link_attrs($secondary_cta);

if ($title_html === '' && $introduction === '' && !$image && !$has_actions) {
    return;
}
?>
<section aria-label="<?php echo esc_attr__('Compact hero', 'matrix-starter'); ?>" class="dz-flexi flex-hero-block">
    <div class="flex-hero">
        <div class="container">
            <?php if ($show_breadcrumbs) : ?>
                <?php matrix_dz_render_breadcrumbs(); ?>
            <?php endif; ?>

            <div class="flex-hero-grid">
                <div>
                    <?php if ($eyebrow !== '') : ?>
                        <span class="flex-eyebrow"><?php echo esc_html($eyebrow); ?></span>
                    <?php endif; ?>

                    <?php if ($title_html !== '') : ?>
                        <h1><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h1>
                    <?php endif; ?>

                    <?php if ($introduction !== '') : ?>
                        <p><?php echo wp_kses_post($introduction); ?></p>
                    <?php endif; ?>

                    <?php if ($has_actions) : ?>
                        <div class="flex-actions">
                            <?php matrix_dz_render_button($primary_cta); ?>
                            <?php matrix_dz_render_button($secondary_cta, 'outline'); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (is_array($image) && !empty($image['ID'])) : ?>
                    <?php
                    echo wp_get_attachment_image(
                        (int) $image['ID'],
                        'large',
                        false,
                        [
                            'alt' => $image['alt'] ?? '',
                        ]
                    );
                    ?>
                <?php elseif (is_array($image) && !empty($image['url'])) : ?>
                    <img
                        src="<?php echo esc_url($image['url']); ?>"
                        alt="<?php echo esc_attr($image['alt'] ?? ''); ?>"
                        loading="eager"
                    >
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
