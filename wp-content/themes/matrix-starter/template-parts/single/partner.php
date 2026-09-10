<?php
/**
 * Single Partner — Figma 5:3115 desktop, 5:3298 mobile.
 * template-parts/single/partner.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = get_the_ID();
$title   = get_the_title();

$kicker              = matrix_partner_detail_kicker($post_id);
$hero_subtitle       = matrix_partner_hero_subtitle($post_id);
$lead                = trim((string) get_field('partner_lead', $post_id));
$country             = trim((string) get_field('partner_country', $post_id));
$based_in            = trim((string) get_field('partner_based_in', $post_id));
$sidebar_role        = matrix_partner_sidebar_role($post_id);
$website             = get_field('partner_website', $post_id);
$website_label       = matrix_partner_website_label($post_id, is_array($website) ? $website : null);
$back_label          = trim((string) get_field('back_to_partners_label', $post_id));
$back_url            = matrix_partners_archive_url();
$logo_id             = (int) get_field('partner_logo', $post_id);
if ($logo_id <= 0) {
    $logo_id = (int) get_post_thumbnail_id($post_id);
}
$other_partners      = matrix_partner_other_partners($post_id);

if ($back_label === '') {
    $back_label = '← Back to all partners';
}

$section_id = 'partner-single-' . wp_generate_uuid4();
$title_id   = $section_id . '-title';
$has_body   = trim((string) get_the_content()) !== '';
?>

<article class="pace-partner-single font-montserrat">
    <header class="bg-[#003b65] text-white">
        <div class="mx-auto w-full max-w-[1280px] px-5 py-12 lg:px-10 lg:py-[72px] xl:px-[120px]">
            <div class="flex max-w-[720px] flex-col gap-3">
                <p class="text-[13px] font-semibold uppercase leading-[19.5px] tracking-[0.14em] text-[#f4bd0b]">
                    <?php echo esc_html($kicker); ?>
                </p>
                <h1
                    id="<?php echo esc_attr($title_id); ?>"
                    class="text-[36px] font-extrabold leading-[39.6px] tracking-[-0.36px] lg:text-[56px] lg:leading-[61.6px] lg:tracking-[-0.56px]"
                >
                    <?php echo esc_html($title); ?>
                </h1>
                <?php if ($hero_subtitle !== '') : ?>
                    <p class="font-comfortaa text-[18px] font-normal leading-[27px] text-white/85">
                        <?php echo esc_html($hero_subtitle); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="mx-auto w-full max-w-[1280px] px-5 py-8 lg:px-10 lg:py-16 xl:px-[120px]">
        <div class="grid grid-cols-1 gap-14 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)] lg:gap-14">
            <div class="flex flex-col gap-3">
                <?php if ($lead !== '') : ?>
                    <div class="border-l-[3px] border-solid border-[#f4bd0b] py-1 pl-4 font-comfortaa text-[16px] leading-[26.4px] text-[#1d1d1d]">
                        <?php echo wp_kses_post($lead); ?>
                    </div>
                <?php endif; ?>

                <?php if ($has_body) : ?>
                    <div class="pace-partner-body font-comfortaa text-[16px] leading-[26.4px] text-[#1d1d1d]">
                        <?php the_content(); ?>
                    </div>
                <?php endif; ?>

                <p class="pt-5">
                    <a
                        href="<?php echo esc_url($back_url); ?>"
                        class="btn inline-flex items-center justify-center rounded-[999px] border border-solid border-[#0a60a0] px-[22px] py-[13px] text-[14px] font-semibold leading-[14px] text-[#0a60a0] transition hover:bg-[#0a60a0]/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0a60a0]"
                    >
                        <?php echo esc_html($back_label); ?>
                    </a>
                </p>
            </div>

            <aside class="flex flex-col gap-[18px] lg:sticky lg:top-8 lg:self-start">
                <div class="rounded-2xl bg-[#fffae8] p-6">
                    <?php if ($logo_id > 0) : ?>
                        <div class="mb-3.5 flex h-[54px] w-[105px] items-center justify-center overflow-hidden rounded-[11px]">
                            <?php
                            echo wp_get_attachment_image(
                                $logo_id,
                                'medium',
                                false,
                                [
                                    'class' => 'max-h-[54px] max-w-[105px] object-contain object-left',
                                    'alt'   => esc_attr($title),
                                ]
                            );
                            ?>
                        </div>
                    <?php endif; ?>

                    <dl class="flex flex-col gap-2.5">
                        <?php if ($country !== '') : ?>
                            <div>
                                <dt class="text-[11px] font-semibold uppercase leading-[16.5px] tracking-[0.12em] text-[#757575]"><?php esc_html_e('Country', 'matrix-starter'); ?></dt>
                                <dd class="text-[15px] font-bold leading-[22.5px] text-[#003b65]"><?php echo esc_html($country); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if ($based_in !== '') : ?>
                            <div>
                                <dt class="text-[11px] font-semibold uppercase leading-[16.5px] tracking-[0.12em] text-[#757575]"><?php esc_html_e('Based in', 'matrix-starter'); ?></dt>
                                <dd class="text-[15px] font-bold leading-[22.5px] text-[#003b65]"><?php echo esc_html($based_in); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if ($sidebar_role !== '') : ?>
                            <div>
                                <dt class="text-[11px] font-semibold uppercase leading-[16.5px] tracking-[0.12em] text-[#757575]"><?php esc_html_e('Role', 'matrix-starter'); ?></dt>
                                <dd class="text-[15px] font-bold leading-[22.5px] text-[#003b65]"><?php echo esc_html($sidebar_role); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (is_array($website) && !empty($website['url'])) : ?>
                            <div>
                                <dt class="text-[11px] font-semibold uppercase leading-[16.5px] tracking-[0.12em] text-[#757575]"><?php esc_html_e('Website', 'matrix-starter'); ?></dt>
                                <dd class="text-[15px] font-bold leading-[22.5px]">
                                    <a
                                        href="<?php echo esc_url($website['url']); ?>"
                                        target="<?php echo esc_attr($website['target'] ?? '_blank'); ?>"
                                        rel="noopener noreferrer"
                                        class="text-[#0a60a0] underline decoration-[rgba(10,96,160,0.35)] underline-offset-2"
                                    >
                                        <?php
                                        echo esc_html($website_label !== '' ? $website_label . ' ↗' : ($website['title'] ?? $website['url']));
                                        ?>
                                    </a>
                                </dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>

                <?php if ($other_partners !== []) : ?>
                    <nav
                        class="rounded-2xl border border-solid border-[#ecf0f4] bg-white p-[23px]"
                        aria-label="<?php esc_attr_e('Other partners', 'matrix-starter'); ?>"
                    >
                        <p class="text-[13px] font-semibold uppercase leading-[19.5px] tracking-[0.14em] text-[#0a60a0]">
                            <?php esc_html_e('Other partners', 'matrix-starter'); ?>
                        </p>
                        <ul class="mt-0 divide-y divide-dashed divide-[#e1e7ec] border-0">
                            <?php foreach ($other_partners as $item) : ?>
                                <li>
                                    <a
                                        href="<?php echo esc_url($item['url']); ?>"
                                        class="flex items-center gap-2.5 py-2.5 text-[14px] font-semibold leading-[21px] text-[#003b65] transition hover:text-[#0a60a0] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0a60a0]"
                                    >
                                        <?php if ($item['logo_id'] > 0) : ?>
                                            <span class="flex h-[13px] w-5 shrink-0 items-center justify-center overflow-hidden">
                                                <?php
                                                echo wp_get_attachment_image(
                                                    (int) $item['logo_id'],
                                                    'thumbnail',
                                                    false,
                                                    [
                                                        'class' => 'max-h-[13px] max-w-5 object-contain',
                                                        'alt'   => '',
                                                    ]
                                                );
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                        <span><?php echo esc_html($item['title']); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</article>

<style>
.pace-partner-body p {
    margin: 0 0 0.65em;
}
.pace-partner-body p:last-child {
    margin-bottom: 0;
}
</style>
