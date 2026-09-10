<?php
/**
 * Sub-page hero — PACE (Figma 3:881 desktop, 3:1070 mobile)
 * template-parts/hero/subhero.php
 *
 * Supports ACF hero_content_blocks rows and direct $args (archives, singles).
 */

if (!defined('ABSPATH')) {
    exit;
}

$args = is_array($args ?? null) ? $args : [];
$sf   = static function (string $key, $default = null) use ($args) {
    if (array_key_exists($key, $args)) {
        return $args[$key];
    }
    $val = get_sub_field($key);
    return ($val !== null && $val !== '') ? $val : $default;
};

$section_id       = 'subhero-' . wp_generate_uuid4();
$title_id         = $section_id . '-title';
$desc_id          = $section_id . '-desc';

$kicker            = $sf('kicker', '');
$title             = $sf('title', '');
if ($title === null || $title === '') {
    $title = $sf('heading', '');
}
$heading_tag       = $sf('heading_tag', 'h1');
$description       = $sf('description', '');
if ($description === null || $description === '') {
    $description = $sf('content', '');
}
$background_color  = $sf('background_color', '#003b65');
$decoration_style  = $sf('decoration_style', 'default_grey');
$decoration_color  = (string) $sf('decoration_color', '');
$custom_decoration = (int) $sf('custom_decoration', 0);

$legacy_image = (int) $sf('image', 0);
$legacy_presentation = (string) $sf('image_presentation', '');
if ($custom_decoration <= 0 && $legacy_image > 0 && $legacy_presentation === 'full_height_right_svg') {
    $decoration_style  = 'custom';
    $custom_decoration = $legacy_image;
}

$use_white_text = matrix_pace_subhero_is_dark_background((string) $background_color);
$explicit_white = array_key_exists('use_white_text', $args)
    ? $args['use_white_text']
    : get_sub_field('use_white_text');
if ($explicit_white === 1 || $explicit_white === true || $explicit_white === '1') {
    $use_white_text = true;
} elseif ($explicit_white === 0 || $explicit_white === '0') {
    $use_white_text = false;
}

$allowed_heading_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span'];
if (!in_array($heading_tag, $allowed_heading_tags, true)) {
    $heading_tag = 'h1';
}

$allowed_title_tags = [
    'br'     => [],
    'strong' => [],
    'em'     => [],
    'span'   => ['class' => true],
];

$title_html = '';
if (!empty($title)) {
    $title_clean = preg_replace('#</?p[^>]*>#i', '', (string) $title);
    $title_clean = preg_replace('#</?div[^>]*>#i', '', (string) $title_clean);
    $title_clean = preg_replace('#</?h[1-6][^>]*>#i', '', (string) $title_clean);
    $title_html = trim((string) wp_kses($title_clean, $allowed_title_tags));
}

if (is_singular()) {
    $heading_text = trim(wp_strip_all_tags($title_html));
    $page_title   = trim(wp_strip_all_tags((string) get_the_title()));
    $site_name    = trim(wp_strip_all_tags((string) get_bloginfo('name')));

    if ($heading_text !== '' && $page_title !== '') {
        $heading_parts = preg_split('/\s*[-–|]\s*/u', $heading_text);
        $looks_like_document_title = is_array($heading_parts)
            && count($heading_parts) >= 2
            && strcasecmp(trim((string) $heading_parts[0]), $site_name) === 0
            && strcasecmp(trim((string) end($heading_parts)), $page_title) === 0;

        if ($looks_like_document_title) {
            $title_html = esc_html($page_title);
        }
    }

    if ($title_html === '') {
        $title_html = esc_html($page_title);
    }
}

$padding_classes = [];
if (have_rows('padding_settings')) {
    while (have_rows('padding_settings')) {
        the_row();
        $screen_size    = get_sub_field('screen_size');
        $padding_top    = get_sub_field('padding_top');
        $padding_bottom = get_sub_field('padding_bottom');
        if ($screen_size && $padding_top !== '' && $padding_top !== null) {
            $padding_classes[] = "{$screen_size}:pt-[{$padding_top}rem]";
        }
        if ($screen_size && $padding_bottom !== '' && $padding_bottom !== null) {
            $padding_classes[] = "{$screen_size}:pb-[{$padding_bottom}rem]";
        }
    }
} elseif (!empty($args['padding_settings']) && is_array($args['padding_settings'])) {
    foreach ($args['padding_settings'] as $row) {
        $screen_size    = $row['screen_size'] ?? '';
        $padding_top    = $row['padding_top'] ?? null;
        $padding_bottom = $row['padding_bottom'] ?? null;
        if ($screen_size && $padding_top !== '' && $padding_top !== null) {
            $padding_classes[] = "{$screen_size}:pt-[{$padding_top}rem]";
        }
        if ($screen_size && $padding_bottom !== '' && $padding_bottom !== null) {
            $padding_classes[] = "{$screen_size}:pb-[{$padding_bottom}rem]";
        }
    }
}

if ($padding_classes === []) {
    $padding_classes = ['py-12', 'lg:py-[72px]'];
} else {
    // ACF rows often use mob:/lg: only — mob: starts at 575px, leaving smaller viewports unpadded.
    // Floor at py-12 (3rem) below lg; desktop keeps lg: overrides from the CMS.
    $lg_padding = array_values(array_filter(
        $padding_classes,
        static fn(string $class): bool => str_starts_with($class, 'lg:')
    ));
    $padding_classes = array_merge(['py-12'], $lg_padding);
}

$section_extra = trim((string) $sf('section_extra_classes', ''));

$decoration_fill = matrix_pace_subhero_resolve_decoration_fill(
    (string) $decoration_style,
    $decoration_color
);

$show_decoration = $decoration_style !== 'none'
    && ($decoration_style !== 'custom' || $custom_decoration > 0);

$kicker_class = matrix_pace_subhero_kicker_class((string) $background_color, $use_white_text);
$title_class = $use_white_text
    ? 'break-words text-[36px] font-extrabold leading-[39.6px] tracking-[-0.36px] text-white lg:text-[56px] lg:leading-[61.6px] lg:tracking-[-0.56px]'
    : 'break-words text-[36px] font-extrabold leading-[39.6px] tracking-[-0.36px] text-[#003b65] lg:text-[56px] lg:leading-[61.6px] lg:tracking-[-0.56px]';
$desc_style_id = $section_id . '-desc-style';

$section_classes = trim(
    'relative overflow-hidden font-montserrat '
    . ($use_white_text ? 'pace-subhero--light-text ' : '')
    . $section_extra . ' '
    . implode(' ', $padding_classes)
);
?>
<section
    id="<?php echo esc_attr($section_id); ?>"
    class="<?php echo esc_attr($section_classes); ?>"
    style="background-color: <?php echo esc_attr($background_color); ?>;"
    data-disable-nav-offset="true"
    <?php echo $title_html !== '' ? 'aria-labelledby="' . esc_attr($title_id) . '"' : 'role="region"'; ?>
>
    <?php if ($show_decoration) : ?>
        <?php matrix_pace_subhero_render_decoration((string) $decoration_style, $decoration_fill, $custom_decoration); ?>
    <?php endif; ?>

    <div class="relative z-[2] mx-auto w-full max-w-[1280px] px-5 lg:px-10 xl:px-[120px]">
        <div class="flex max-w-[720px] flex-col gap-3 lg:gap-3">
            <?php if (!empty($kicker)) : ?>
                <p class="<?php echo esc_attr($kicker_class); ?>">
                    <?php echo esc_html($kicker); ?>
                </p>
            <?php endif; ?>

            <?php if ($title_html !== '') : ?>
                <<?php echo esc_attr($heading_tag); ?>
                    id="<?php echo esc_attr($title_id); ?>"
                    class="<?php echo esc_attr($title_class); ?>"
                >
                    <?php echo $title_html; ?>
                </<?php echo esc_attr($heading_tag); ?>>
            <?php endif; ?>

            <?php if (!empty($description)) : ?>
                <div
                    id="<?php echo esc_attr($desc_id); ?>"
                    class="subhero-desc wp_editor max-w-[640px]"
                >
                    <?php echo wp_kses_post($description); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
#<?php echo esc_attr($section_id); ?> .subhero-desc,
#<?php echo esc_attr($section_id); ?> .subhero-desc p {
    font-family: var(--font-comfortaa, Comfortaa, sans-serif);
    font-size: 18px;
    font-weight: 400;
    line-height: 27px;
    <?php if ($use_white_text) : ?>
    color: #ffffff;
    <?php else : ?>
    color: #4a5b6d;
    <?php endif; ?>
}
#<?php echo esc_attr($section_id); ?> .subhero-desc p {
    margin: 0 0 0.25em;
}
#<?php echo esc_attr($section_id); ?> .subhero-desc p:last-child {
    margin-bottom: 0;
}
<?php if ($use_white_text) : ?>
#<?php echo esc_attr($section_id); ?>.pace-subhero--light-text h1,
#<?php echo esc_attr($section_id); ?>.pace-subhero--light-text h1 *,
#<?php echo esc_attr($section_id); ?>.pace-subhero--light-text h2,
#<?php echo esc_attr($section_id); ?>.pace-subhero--light-text h2 * {
    color: #fff !important;
}
<?php endif; ?>
</style>
