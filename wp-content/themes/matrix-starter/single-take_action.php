<?php
/**
 * Take Action pathway single — design/website/take-action.html
 *
 * @package Matrix_Starter
 */

get_header();

while (have_posts()) :
    the_post();

    $post_id = (int) get_the_ID();

    $tag_label = (string) (get_field('ta_tag_label', $post_id) ?: __('Retrofit pathway', 'matrix-starter'));
    $tag_style = (string) (get_field('ta_tag_style', $post_id) ?: 'retrofit');
    $tag_icon  = get_field('ta_tag_icon', $post_id);
    $title     = (string) (get_field('ta_title', $post_id) ?: get_the_title($post_id));
    $intro     = (string) get_field('ta_intro', $post_id);
    $steps     = get_field('ta_steps', $post_id);
    $final_cta = get_field('ta_final_cta', $post_id);

    $case_project = get_field('ta_case_project', $post_id);
    $case_title   = (string) get_field('ta_case_title', $post_id);
    $case_cta     = (string) (get_field('ta_case_cta', $post_id) ?: __('Read the case study →', 'matrix-starter'));
    $case_image   = get_field('ta_case_image', $post_id);

    $nearby_tag     = (string) (get_field('ta_nearby_tag', $post_id) ?: __('Retrofit in Maynooth', 'matrix-starter'));
    $nearby_heading = (string) get_field('ta_nearby_heading', $post_id);
    $nearby_text    = (string) get_field('ta_nearby_text', $post_id);
    $nearby_button  = get_field('ta_nearby_button', $post_id);
    $nearby_label   = (string) get_field('ta_nearby_photo_label', $post_id);
    $nearby_secondary = get_field('ta_nearby_secondary', $post_id);

    if (!is_array($steps)) {
        $steps = [];
    }
    $steps = array_values(array_filter($steps, static function ($step) {
        return is_array($step) && trim((string) ($step['tab_label'] ?? '')) !== '';
    }));
    $step_count = count($steps);

    $case_post = ($case_project instanceof WP_Post) ? $case_project : null;
    $case_id   = $case_post ? (int) $case_post->ID : 0;
    $case_url  = $case_id ? get_permalink($case_id) : '';
    $case_name = $case_id ? get_the_title($case_id) : '';

    if ($case_title === '' && $case_name !== '') {
        $case_title = sprintf(
            /* translators: %s: project title */
            __('See it done: %s', 'matrix-starter'),
            $case_name
        );
    }

    $case_img_id = 0;
    if (is_array($case_image) && !empty($case_image['ID'])) {
        $case_img_id = (int) $case_image['ID'];
    } elseif ($case_id) {
        $case_img_id = (int) get_post_thumbnail_id($case_id);
    }

    $tag_icon_url = '';
    if (is_array($tag_icon) && !empty($tag_icon['url'])) {
        $tag_icon_url = $tag_icon['url'];
    } else {
        $tag_icon_url = matrix_dz_assets_url('home-icon.svg');
    }

    if ($nearby_label === '' && $case_name !== '') {
        $nearby_label = sprintf(
            /* translators: %s: project title */
            __('Inside %s', 'matrix-starter'),
            $case_name
        );
    }

    $nearby_heading_html = $nearby_heading !== ''
        ? nl2br(esc_html($nearby_heading))
        : '';
    ?>
<main id="main-content" class="site-main take-action-pathway">
    <section class="pathway-hero">
        <div class="container">
            <nav class="breadcrumbs" aria-label="<?php echo esc_attr__('Breadcrumb', 'matrix-starter'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'matrix-starter'); ?></a>
                <span aria-hidden="true">/</span>
                <a href="<?php echo esc_url(get_post_type_archive_link('take_action') ?: home_url('/take-action/')); ?>"><?php esc_html_e('Take Action', 'matrix-starter'); ?></a>
                <span aria-hidden="true">/</span>
                <span aria-current="page"><?php echo esc_html($title); ?></span>
            </nav>

            <?php if ($tag_label !== '') : ?>
                <span class="tag <?php echo esc_attr($tag_style); ?>">
                    <?php if ($tag_icon_url !== '') : ?>
                        <img src="<?php echo esc_url($tag_icon_url); ?>" alt="" width="16" height="16">
                    <?php endif; ?>
                    <?php echo esc_html($tag_label); ?>
                </span>
            <?php endif; ?>

            <h1><?php echo esc_html($title); ?></h1>

            <?php if ($intro !== '') : ?>
                <p><?php echo esc_html($intro); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($step_count > 0) : ?>
        <section class="pathway-section container" aria-label="<?php echo esc_attr__('Home retrofit pathway', 'matrix-starter'); ?>">
            <div class="pathway-tabs" role="tablist" aria-label="<?php echo esc_attr__('Retrofit steps', 'matrix-starter'); ?>">
                <?php foreach ($steps as $i => $step) :
                    $n = $i + 1;
                    $label = (string) $step['tab_label'];
                    ?>
                    <button
                        type="button"
                        role="tab"
                        id="step-tab-<?php echo esc_attr((string) $n); ?>"
                        aria-controls="step-panel-<?php echo esc_attr((string) $n); ?>"
                        aria-selected="<?php echo $n === 1 ? 'true' : 'false'; ?>"
                        tabindex="<?php echo $n === 1 ? '0' : '-1'; ?>"
                        data-step="<?php echo esc_attr((string) $n); ?>"
                    >
                        <span class="step-number"><?php echo esc_html((string) $n); ?></span>
                        <span><?php echo esc_html($label); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="pathway-layout">
                <div class="pathway-steps">
                    <?php foreach ($steps as $i => $step) :
                        $n     = $i + 1;
                        $label = (string) $step['tab_label'];
                        $stitle = (string) ($step['title'] ?? $label);
                        $blocks = is_array($step['blocks'] ?? null) ? $step['blocks'] : [];
                        ?>
                        <section class="pathway-step<?php echo $n === 1 ? ' is-open' : ''; ?>" data-section="<?php echo esc_attr((string) $n); ?>">
                            <h2 class="accordion-heading">
                                <button
                                    type="button"
                                    id="step-accordion-<?php echo esc_attr((string) $n); ?>"
                                    aria-expanded="<?php echo $n === 1 ? 'true' : 'false'; ?>"
                                    aria-controls="step-panel-<?php echo esc_attr((string) $n); ?>"
                                    data-step="<?php echo esc_attr((string) $n); ?>"
                                >
                                    <span class="step-number"><?php echo esc_html((string) $n); ?></span>
                                    <span><?php echo esc_html($label); ?></span>
                                    <span class="accordion-chevron" aria-hidden="true">⌄</span>
                                </button>
                            </h2>

                            <div
                                class="step-panel"
                                id="step-panel-<?php echo esc_attr((string) $n); ?>"
                                role="tabpanel"
                                aria-labelledby="step-tab-<?php echo esc_attr((string) $n); ?>"
                                tabindex="0"
                                <?php echo $n === 1 ? '' : 'hidden'; ?>
                            >
                                <h2 class="step-title"><?php echo esc_html($stitle); ?></h2>
                                <div class="step-content">
                                    <?php foreach ($blocks as $block) :
                                        if (!is_array($block)) {
                                            continue;
                                        }
                                        $type = (string) ($block['type'] ?? 'text');
                                        if ($type === 'resource') {
                                            $r_label = trim((string) ($block['resource_label'] ?? ''));
                                            $r_url   = trim((string) ($block['resource_url'] ?? ''));
                                            $r_icon  = (string) ($block['resource_icon'] ?? '↗');
                                            if ($r_label === '' || $r_url === '') {
                                                continue;
                                            }
                                            ?>
                                            <div class="pathway-resources">
                                                <a href="<?php echo esc_url($r_url); ?>">
                                                    <span><?php echo esc_html($r_label); ?></span>
                                                    <span aria-hidden="true"><?php echo esc_html($r_icon); ?></span>
                                                </a>
                                            </div>
                                            <?php
                                        } else {
                                            $text = trim((string) ($block['text'] ?? ''));
                                            if ($text === '') {
                                                continue;
                                            }
                                            ?>
                                            <p><?php echo esc_html($text); ?></p>
                                            <?php
                                        }
                                    endforeach; ?>
                                </div>

                                <div class="step-navigation">
                                    <?php if ($n > 1) : ?>
                                        <button
                                            type="button"
                                            class="button outline step-previous"
                                            data-go="<?php echo esc_attr((string) ($n - 1)); ?>"
                                        >← <?php echo esc_html((string) $steps[$i - 1]['tab_label']); ?></button>
                                    <?php endif; ?>

                                    <?php if ($n < $step_count) : ?>
                                        <button
                                            type="button"
                                            class="button step-next"
                                            data-go="<?php echo esc_attr((string) ($n + 1)); ?>"
                                        ><?php
                                            echo esc_html(
                                                sprintf(
                                                    /* translators: %s: next step tab label */
                                                    __('Next: %s →', 'matrix-starter'),
                                                    (string) $steps[$i + 1]['tab_label']
                                                )
                                            );
                                        ?></button>
                                    <?php elseif (is_array($final_cta) && !empty($final_cta['url'])) : ?>
                                        <a
                                            class="button"
                                            href="<?php echo esc_url($final_cta['url']); ?>"
                                            <?php echo !empty($final_cta['target']) ? ' target="' . esc_attr($final_cta['target']) . '" rel="noopener"' : ''; ?>
                                        ><?php echo esc_html($final_cta['title'] !== '' ? $final_cta['title'] : __('Explore projects →', 'matrix-starter')); ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>

                <?php if ($case_url !== '' || $case_img_id) : ?>
                    <aside class="pathway-aside" aria-label="<?php echo esc_attr__('A local retrofit example', 'matrix-starter'); ?>">
                        <a class="pathway-case" href="<?php echo esc_url($case_url !== '' ? $case_url : '#'); ?>">
                            <?php
                            if ($case_img_id) {
                                echo wp_get_attachment_image($case_img_id, 'large', false, [
                                    'alt' => $case_name !== '' ? $case_name : '',
                                ]);
                            }
                            ?>
                            <span>
                                <?php if ($case_title !== '') : ?>
                                    <strong><?php echo esc_html($case_title); ?></strong>
                                <?php endif; ?>
                                <span><?php echo esc_html($case_cta); ?></span>
                            </span>
                        </a>
                    </aside>
                <?php endif; ?>
            </div>

            <?php if ($nearby_heading !== '' || $nearby_text !== '' || (is_array($nearby_button) && !empty($nearby_button['url']))) : ?>
                <section class="pathway-map-cta" aria-labelledby="nearby-retrofit-title">
                    <div class="nearby-copy">
                        <?php if ($nearby_tag !== '') : ?>
                            <span class="tag <?php echo esc_attr($tag_style); ?>">
                                <?php if ($tag_icon_url !== '') : ?>
                                    <img src="<?php echo esc_url($tag_icon_url); ?>" alt="" width="16" height="16">
                                <?php endif; ?>
                                <?php echo esc_html($nearby_tag); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($nearby_heading_html !== '') : ?>
                            <h2 id="nearby-retrofit-title"><?php echo $nearby_heading_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped + nl2br ?></h2>
                        <?php endif; ?>

                        <?php if ($nearby_text !== '') : ?>
                            <p><?php echo esc_html($nearby_text); ?></p>
                        <?php endif; ?>

                        <?php if (is_array($nearby_button) && !empty($nearby_button['url'])) : ?>
                            <a
                                class="button"
                                href="<?php echo esc_url($nearby_button['url']); ?>"
                                <?php echo !empty($nearby_button['target']) ? ' target="' . esc_attr($nearby_button['target']) . '" rel="noopener"' : ''; ?>
                            >
                                <img src="<?php echo esc_url(matrix_dz_assets_url('map-icon.svg')); ?>" alt="" width="20" height="20">
                                <?php echo esc_html($nearby_button['title'] !== '' ? $nearby_button['title'] : __('View on the map', 'matrix-starter')); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="nearby-photos">
                        <?php if ($case_url !== '' && $case_img_id) : ?>
                            <a class="nearby-main-photo" href="<?php echo esc_url($case_url); ?>">
                                <?php
                                echo wp_get_attachment_image($case_img_id, 'large', false, [
                                    'alt'     => $case_name !== '' ? $case_name : '',
                                    'loading' => 'lazy',
                                ]);
                                ?>
                                <?php if ($nearby_label !== '') : ?>
                                    <span><?php echo esc_html($nearby_label); ?> <span aria-hidden="true">↗</span></span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>

                        <?php if (is_array($nearby_secondary) && !empty($nearby_secondary['ID'])) : ?>
                            <?php
                            echo wp_get_attachment_image(
                                (int) $nearby_secondary['ID'],
                                'medium',
                                false,
                                [
                                    'class'   => 'nearby-small-photo',
                                    'loading' => 'lazy',
                                    'alt'     => $nearby_secondary['alt'] !== '' ? $nearby_secondary['alt'] : '',
                                ]
                            );
                            ?>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <noscript>
                <p><?php esc_html_e('All pathway steps are shown below when JavaScript is unavailable.', 'matrix-starter'); ?></p>
            </noscript>
        </section>
    <?php else : ?>
        <section class="pathway-section container">
            <p class="directory-description"><?php esc_html_e('This pathway is being prepared. Check back soon for step-by-step guidance, or explore another action from the directory.', 'matrix-starter'); ?></p>
            <p><a class="button" href="<?php echo esc_url(get_post_type_archive_link('take_action') ?: home_url('/take-action/')); ?>"><?php esc_html_e('Browse all actions', 'matrix-starter'); ?></a></p>
        </section>
    <?php endif; ?>
</main>
    <?php
endwhile;

get_footer();
