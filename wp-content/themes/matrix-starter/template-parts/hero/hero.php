<?php
/**
 * PACE homepage hero — Figma 3:5 / 3:268
 *
 * @package Matrix_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

$section_id   = 'hero-' . wp_generate_uuid4();
$title_id     = $section_id . '-title';

$kicker          = (string) get_sub_field('kicker');
$title_raw       = (string) get_sub_field('title');
$heading_tag     = (string) (get_sub_field('heading_tag') ?: 'h1');
$description     = (string) get_sub_field('description');
$primary_cta     = get_sub_field('primary_cta');
$secondary_cta   = get_sub_field('secondary_cta');
$media_image     = (int) get_sub_field('media_image');
$show_arrow      = (bool) get_sub_field('show_primary_arrow');
$bg_color        = (string) (get_sub_field('background_color') ?: '#003b65');
$padding_rows    = get_sub_field('padding_settings');

$padding_classes = matrix_pace_flexi_padding_classes(is_array($padding_rows) ? $padding_rows : null, [
    'pt-16',
    'pb-[4.85rem]',
    'lg:pt-24',
    'lg:pb-28',
]);

$title_html = matrix_pace_flexi_heading_html($title_raw, $heading_tag);

if (!in_array($heading_tag, ['h1', 'h2'], true)) {
    $heading_tag = 'h1';
}
?>
<section
    id="<?php echo esc_attr($section_id); ?>"
    class="relative overflow-hidden font-montserrat  max-lg:pb-[4.5rem] <?php echo esc_attr(implode(' ', $padding_classes)); ?>"
    style="background-color: <?php echo esc_attr($bg_color); ?>;"
    aria-labelledby="<?php echo esc_attr($title_id); ?>"
>
    <div
        class="absolute inset-0 pointer-events-none"
        style="background: linear-gradient(90deg, #003b65 0%, #003b65 38%, rgba(0,59,101,0.55) 58%, rgba(0,59,101,0) 78%);"
        aria-hidden="true"
    ></div>

    <div class="relative z-[2] mx-auto grid w-full max-w-[1280px] grid-cols-1 items-center gap-12 px-5 lg:grid-cols-2 lg:gap-12 lg:px-10 xl:px-[120px]">
        <div class="flex max-w-[520px] flex-col gap-4">
            <?php if ($kicker !== '') : ?>
                <p class="text-[13px] font-semibold uppercase tracking-[0.14em] text-[#f4bd0b]">
                    <?php echo esc_html($kicker); ?>
                </p>
            <?php endif; ?>

            <?php if ($title_html !== '') : ?>
                <<?php echo esc_attr($heading_tag); ?>
                    id="<?php echo esc_attr($title_id); ?>"
                    class="font-extrabold text-white text-[40px] leading-[53px] tracking-[-0.4px] lg:text-[68px] lg:leading-[72px] lg:tracking-[-0.68px] [&_span]:font-extrabold [&_span]:text-[#f4bd0b]"
                >
                    <?php echo $title_html; ?>
                </<?php echo esc_attr($heading_tag); ?>>
            <?php endif; ?>

            <?php if ($description !== '') : ?>
                <div class="font-comfortaa text-[18px] leading-[27.9px] text-white/85">
                    <?php echo wp_kses_post($description); ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($primary_cta) || is_array($secondary_cta)) : ?>
                <div class="flex flex-col gap-3 pt-3 tab:flex-row tab:flex-wrap tab:items-center tab:gap-3">
                    <?php if (is_array($primary_cta) && !empty($primary_cta['url'])) : ?>
                        <a
                            href="<?php echo esc_url($primary_cta['url']); ?>"
                            target="<?php echo esc_attr($primary_cta['target'] ?? '_self'); ?>"
                            class="<?php echo esc_attr(matrix_btn_classes('primary', ['full_mobile' => true])); ?> hero-cta gap-2.5"
                        >
                            <?php echo esc_html($primary_cta['title'] ?: 'Explore the Resources Hub'); ?>
                            <?php if ($show_arrow) : ?>
                                <span aria-hidden="true">→</span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <?php if (is_array($secondary_cta) && !empty($secondary_cta['url'])) : ?>
                        <a
                            href="<?php echo esc_url($secondary_cta['url']); ?>"
                            target="<?php echo esc_attr($secondary_cta['target'] ?? '_self'); ?>"
                            class="<?php echo esc_attr(matrix_btn_classes('secondary', ['full_mobile' => true])); ?> hero-cta"
                        >
                            <?php echo esc_html($secondary_cta['title'] ?: 'Read our latest news'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($media_image > 0) : ?>
            <div class="relative hidden overflow-hidden rounded-[20px] lg:block lg:justify-self-end">
                <?php
                echo wp_get_attachment_image($media_image, 'large', false, [
                    'class' => 'aspect-[4/5] w-full rounded-[20px] object-cover lg:aspect-auto lg:min-h-[520px]',
                    'alt'   => '',
                ]);
                ?>
            </div>
        <?php endif; ?>
    </div>
</section>
