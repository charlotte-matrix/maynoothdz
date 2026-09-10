<?php
/**
 * About page main content (Figma 3:567 desktop, 3:732 mobile).
 * template-parts/page/about.php
 *
 * @package Matrix_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = get_the_ID();

$banner_caption         = (string) get_field('banner_caption', $post_id);
$challenge_heading      = (string) get_field('challenge_heading', $post_id);
$challenge_body         = (string) get_field('challenge_body', $post_id);
$approach_heading       = (string) get_field('approach_heading', $post_id);
$approach_body          = (string) get_field('approach_body', $post_id);
$inline_caption         = (string) get_field('inline_caption', $post_id);
$values_heading         = (string) get_field('values_heading', $post_id);
$core_values            = get_field('core_values', $post_id) ?: [];
$glance_heading         = (string) get_field('glance_heading', $post_id);
$glance_items           = get_field('glance_items', $post_id) ?: [];
$values_card_background = get_field('values_card_background', $post_id) ?: '#fffae8';
$section_background     = get_field('section_background', $post_id) ?: '#ffffff';

$banner_id   = matrix_pace_about_image_id(
    'banner_image',
    'assets/images/about/banner-classroom.jpg',
    'PACE About — classroom banner',
    'matrix_pace_media_about_banner',
    $banner_caption !== '' ? $banner_caption : __('PACE peer-learning session', 'matrix-starter')
);
$inline_id   = matrix_pace_about_image_id(
    'inline_image',
    'assets/images/about/inline-peer-study.jpg',
    'PACE About — students studying',
    'matrix_pace_media_about_inline',
    $inline_caption !== '' ? $inline_caption : __('Students in Resources Hub module', 'matrix-starter')
);
$portrait_id = matrix_pace_about_image_id(
    'portrait_image',
    'assets/images/about/aside-portrait.jpg',
    'PACE About — portrait',
    'matrix_pace_media_about_portrait',
    __('PACE programme participant', 'matrix-starter')
);

$section_id = 'pace-about-' . wp_generate_uuid4();
?>

<article
    id="<?php echo esc_attr($section_id); ?>"
    class="pace-about font-montserrat"
    style="background-color: <?php echo esc_attr($section_background); ?>;"
>
    <?php if ($banner_id > 0) : ?>
        <figure class="pace-about-banner relative h-[240px] w-full overflow-hidden lg:h-[360px]">
            <?php
            echo wp_get_attachment_image(
                $banner_id,
                'hero-xlarge',
                false,
                [
                    'class' => 'h-full w-full object-cover object-[center_35%]',
                    'alt'   => $banner_caption !== '' ? esc_attr($banner_caption) : '',
                ]
            );
            ?>
            <?php if ($banner_caption !== '') : ?>
                <figcaption class="absolute bottom-6 left-5 max-w-[calc(100%-2.5rem)] rounded-md bg-[rgba(0,59,101,0.8)] px-3 py-2 font-mono text-[12px] leading-[18px] tracking-[0.04em] text-white backdrop-blur-[2px] lg:left-6">
                    <?php echo esc_html($banner_caption); ?>
                </figcaption>
            <?php endif; ?>
        </figure>
    <?php endif; ?>

    <div class="mx-auto w-full max-w-[1280px] px-5 py-10 lg:px-10 lg:py-16 xl:px-[120px]">
        <div class="pace-about-layout grid grid-cols-1 gap-10 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)] lg:gap-16">
            <div class="pace-about-main flex flex-col gap-[11px]">
                <?php if ($challenge_heading !== '' || $challenge_body !== '') : ?>
                    <section class="pace-about-section">
                        <?php if ($challenge_heading !== '') : ?>
                            <h2 class="text-[26px] font-bold leading-[31.2px] text-[#003b65]">
                                <?php echo esc_html($challenge_heading); ?>
                            </h2>
                        <?php endif; ?>
                        <?php if ($challenge_body !== '') : ?>
                            <div class="pace-about-body mt-1 font-comfortaa text-[16px] leading-[26.4px] text-[#1d1d1d]">
                                <?php echo wp_kses_post($challenge_body); ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if ($approach_heading !== '' || $approach_body !== '') : ?>
                    <section class="pace-about-section pt-5">
                        <?php if ($approach_heading !== '') : ?>
                            <h2 class="text-[26px] font-bold leading-[31.2px] text-[#003b65]">
                                <?php echo esc_html($approach_heading); ?>
                            </h2>
                        <?php endif; ?>
                        <?php if ($approach_body !== '') : ?>
                            <div class="pace-about-body mt-1 font-comfortaa text-[16px] leading-[26.4px] text-[#1d1d1d]">
                                <?php echo wp_kses_post($approach_body); ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if ($inline_id > 0) : ?>
                    <figure class="pt-5">
                        <div class="aspect-video w-full overflow-hidden rounded-2xl">
                            <?php
                            echo wp_get_attachment_image(
                                $inline_id,
                                'large',
                                false,
                                [
                                    'class' => 'h-full w-full object-cover',
                                    'alt'   => $inline_caption !== '' ? esc_attr($inline_caption) : '',
                                ]
                            );
                            ?>
                        </div>
                        <?php if ($inline_caption !== '') : ?>
                            <figcaption class="mt-2.5 pl-1 font-comfortaa text-[13px] leading-[19.5px] text-[#757575]">
                                <?php echo esc_html($inline_caption); ?>
                            </figcaption>
                        <?php endif; ?>
                    </figure>
                <?php endif; ?>

                <?php if ($values_heading !== '' || !empty($core_values)) : ?>
                    <section class="pt-5">
                        <?php if ($values_heading !== '') : ?>
                            <h2 class="text-[26px] font-bold leading-[31.2px] text-[#003b65]">
                                <?php echo esc_html($values_heading); ?>
                            </h2>
                        <?php endif; ?>
                        <?php if (!empty($core_values) && is_array($core_values)) : ?>
                            <ul class="mt-2.5 flex flex-col gap-2.5" role="list">
                                <?php foreach ($core_values as $row) : ?>
                                    <?php
                                    $label = trim((string) ($row['label'] ?? ''));
                                    $desc  = trim((string) ($row['description'] ?? ''));
                                    if ($label === '' && $desc === '') {
                                        continue;
                                    }
                                    ?>
                                    <li
                                        class="rounded-xl px-[18px] py-3.5 font-comfortaa text-[16px] leading-[24.8px] text-[#1d1d1d]"
                                        style="background-color: <?php echo esc_attr($values_card_background); ?>;"
                                    >
                                        <?php if ($label !== '') : ?>
                                            <strong class="font-bold"><?php echo esc_html($label); ?></strong>
                                        <?php endif; ?>
                                        <?php if ($desc !== '') : ?>
                                            <span><?php echo $label !== '' ? ' — ' : ''; ?><?php echo esc_html($desc); ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </div>

            <div class="pace-about-sidebar flex flex-col gap-4 lg:sticky lg:top-24 lg:self-start">
                <?php if ($portrait_id > 0) : ?>
                    <figure class="overflow-hidden rounded-2xl shadow-[0px_14px_36px_0px_rgba(0,59,101,0.14)]">
                        <?php
                        echo wp_get_attachment_image(
                            $portrait_id,
                            'large',
                            false,
                            [
                                'class' => 'h-auto min-h-[197px] w-full object-cover lg:min-h-[320px]',
                                'alt'   => '',
                            ]
                        );
                        ?>
                    </figure>
                <?php endif; ?>

                <?php if ($glance_heading !== '' || !empty($glance_items)) : ?>
                    <div class="rounded-2xl bg-white p-6 shadow-[0px_6px_9px_0px_rgba(0,59,101,0.08)]">
                        <?php if ($glance_heading !== '') : ?>
                            <p class="text-[13px] font-semibold uppercase leading-[19.5px] tracking-[0.14em] text-[#0a60a0]">
                                <?php echo esc_html($glance_heading); ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($glance_items) && is_array($glance_items)) : ?>
                            <dl class="mt-4 flex flex-col">
                                <?php foreach ($glance_items as $index => $item) : ?>
                                    <?php
                                    $term_label = trim((string) ($item['term_label'] ?? ''));
                                    $term_value = trim((string) ($item['term_value'] ?? ''));
                                    if ($term_label === '' && $term_value === '') {
                                        continue;
                                    }
                                    ?>
                                    <div class="<?php echo $index > 0 ? 'pt-2' : ''; ?>">
                                        <?php if ($term_label !== '') : ?>
                                            <dt class="text-[12px] font-semibold uppercase leading-[18px] tracking-[0.08em] text-[#757575]">
                                                <?php echo esc_html($term_label); ?>
                                            </dt>
                                        <?php endif; ?>
                                        <?php if ($term_value !== '') : ?>
                                            <dd class="text-[15px] font-bold leading-[22.5px] text-[#003b65]">
                                                <?php echo esc_html($term_value); ?>
                                            </dd>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</article>

<style>
#<?php echo esc_attr($section_id); ?> .pace-about-body p {
    margin: 0 0 0.65em;
}
#<?php echo esc_attr($section_id); ?> .pace-about-body p:last-child {
    margin-bottom: 0;
}
@media (min-width: 1024px) {
    #<?php echo esc_attr($section_id); ?> .pace-about-layout {
        grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
        column-gap: 64px;
    }
}
</style>
