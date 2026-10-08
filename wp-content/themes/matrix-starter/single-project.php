<?php
/**
 * Single Project — design project.html (DemoHouse layout, no map).
 *
 * @package Matrix_Starter
 */

get_header();

while (have_posts()) :
    the_post();

    $post_id  = (int) get_the_ID();
    $term     = matrix_dz_project_primary_category($post_id);
    $style    = matrix_dz_project_category_style($term);

    $eyebrow  = (string) (get_field('project_eyebrow', $post_id) ?: __('Part of the Maynooth Decarbonising Zone', 'matrix-starter'));
    $status   = (string) (get_field('project_status', $post_id) ?: '');
    $location = (string) (get_field('project_location_label', $post_id) ?: '');
    $lead     = (string) (get_field('project_lead', $post_id) ?: get_the_excerpt($post_id));

    $demo_note = (string) get_field('project_demo_note', $post_id);
    $why_h     = (string) (get_field('project_why_heading', $post_id) ?: __('Why this project', 'matrix-starter'));
    $why_body  = (string) get_field('project_why_body', $post_id);
    $media     = get_field('project_media_image', $post_id);
    $caption   = (string) get_field('project_media_caption', $post_id);
    $extra     = (string) get_field('project_story_extra', $post_id);

    $glance_h  = (string) (get_field('project_glance_heading', $post_id) ?: __('At a glance', 'matrix-starter'));
    $glance    = matrix_dz_project_glance_items($post_id);
    $grants    = get_field('project_grant_icons', $post_id);

    $partners_h = (string) (get_field('project_partners_heading', $post_id) ?: __('Partners', 'matrix-starter'));
    $logos      = get_field('project_partner_logos', $post_id);
    $partners_fb = (string) (get_field('project_partners_fallback', $post_id) ?: __('Project partners to be confirmed.', 'matrix-starter'));

    $enable_summary = (bool) get_field('project_enable_summary', $post_id);
    $summary_title  = (string) get_field('project_summary_title', $post_id);
    $summary_meta   = (string) (get_field('project_summary_meta', $post_id) ?: __('Demonstration content · printable, accessible HTML', 'matrix-starter'));
    if ($summary_title === '') {
        $summary_title = sprintf(
            /* translators: %s: project title */
            __('%s — project summary', 'matrix-starter'),
            get_the_title()
        );
    }

    $show_related = get_field('project_show_related', $post_id);
    if ($show_related === null) {
        $show_related = true;
    }
    $related_h = (string) (get_field('project_related_heading', $post_id) ?: __('Related projects', 'matrix-starter'));

    $cta_h   = (string) get_field('project_cta_heading', $post_id);
    $cta_t   = (string) get_field('project_cta_text', $post_id);
    $cta_btn = get_field('project_cta_button', $post_id);

    $projects_url = home_url('/projects/');
    if (isset($_GET['return']) && is_string($_GET['return']) && $_GET['return'] !== '') {
        $return = wp_unslash($_GET['return']);
        // Only allow relative query strings from the directory (?q=&category=…).
        if (preg_match('/^\?[\w\-&=%.+]*$/', $return)) {
            $projects_url .= $return;
        }
    }

    $related = [];
    if ($show_related) {
        $related_q = [
            'post_type'      => 'project',
            'posts_per_page' => 3,
            'post__not_in'   => [$post_id],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        if ($term) {
            $related_q['tax_query'] = [[
                'taxonomy' => 'project_category',
                'field'    => 'term_id',
                'terms'    => [$term->term_id],
            ]];
        }
        $related = get_posts($related_q);
        if (count($related) < 3) {
            $more = get_posts([
                'post_type'      => 'project',
                'posts_per_page' => 3 - count($related),
                'post__not_in'   => array_merge([$post_id], wp_list_pluck($related, 'ID')),
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);
            $related = array_merge($related, $more);
        }
    }
    ?>
<main id="main-content" class="site-main project-detail">
    <section class="detail-hero">
        <div class="container">
            <nav class="breadcrumbs" aria-label="<?php echo esc_attr__('Breadcrumb', 'matrix-starter'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'matrix-starter'); ?></a>
                <span aria-hidden="true">/</span>
                <a href="<?php echo esc_url($projects_url); ?>"><?php esc_html_e('Projects', 'matrix-starter'); ?></a>
                <span aria-hidden="true">/</span>
                <span aria-current="page"><?php the_title(); ?></span>
            </nav>

            <div class="detail-heading-grid detail-heading-no-map">
                <div>
                    <?php if ($eyebrow !== '') : ?>
                        <p class="eyebrow"><?php echo esc_html($eyebrow); ?></p>
                    <?php endif; ?>

                    <div class="detail-labels">
                        <span class="directory-category" style="--category:<?php echo esc_attr($style['color']); ?>">
                            <img src="<?php echo esc_url($style['icon_url']); ?>" alt="" width="16" height="16">
                            <?php echo esc_html($style['label']); ?>
                        </span>
                        <?php if ($status !== '') : ?>
                            <span class="status"><?php echo esc_html($status); ?></span>
                        <?php endif; ?>
                    </div>

                    <h1><?php the_title(); ?></h1>

                    <?php if ($location !== '') : ?>
                        <p class="project-location"><?php echo esc_html($location); ?></p>
                    <?php endif; ?>

                    <?php if ($lead !== '') : ?>
                        <p class="detail-lead"><?php echo esc_html($lead); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <div class="container detail-body">
        <?php if ($demo_note !== '') : ?>
            <p class="detail-demo-note"><?php echo esc_html($demo_note); ?></p>
        <?php endif; ?>

        <div class="detail-layout">
            <article class="detail-story">
                <h2><?php echo esc_html($why_h); ?></h2>
                <?php if ($why_body !== '') : ?>
                    <?php echo wp_kses_post($why_body); ?>
                <?php endif; ?>

                <?php if (is_array($media) && !empty($media['ID'])) : ?>
                    <figure class="detail-media">
                        <?php
                        echo wp_get_attachment_image(
                            (int) $media['ID'],
                            'large',
                            false,
                            [
                                'alt' => $media['alt'] !== '' ? $media['alt'] : get_the_title(),
                            ]
                        );
                        ?>
                        <?php if ($caption !== '') : ?>
                            <figcaption><?php echo esc_html($caption); ?></figcaption>
                        <?php endif; ?>
                    </figure>
                <?php elseif (has_post_thumbnail()) : ?>
                    <figure class="detail-media">
                        <?php the_post_thumbnail('large'); ?>
                        <?php if ($caption !== '') : ?>
                            <figcaption><?php echo esc_html($caption); ?></figcaption>
                        <?php endif; ?>
                    </figure>
                <?php endif; ?>

                <?php if ($extra !== '') : ?>
                    <?php echo wp_kses_post($extra); ?>
                <?php endif; ?>

                <?php if ($enable_summary) : ?>
                    <section class="project-documents" aria-labelledby="documents-title">
                        <h2 id="documents-title"><?php esc_html_e('Documents', 'matrix-starter'); ?></h2>
                        <a
                            class="document-download"
                            href="<?php echo esc_url(matrix_dz_project_summary_url($post_id)); ?>"
                            download="<?php echo esc_attr(sanitize_file_name(get_post_field('post_name', $post_id) . '-summary.html')); ?>"
                        >
                            <span class="document-type">HTML</span>
                            <span>
                                <?php echo esc_html($summary_title); ?>
                                <small><?php echo esc_html($summary_meta); ?></small>
                            </span>
                            <span aria-hidden="true">↓</span>
                        </a>
                    </section>
                <?php endif; ?>
            </article>

            <aside class="detail-sidebar" aria-label="<?php echo esc_attr__('Project information', 'matrix-starter'); ?>">
                <?php if (is_array($glance) && $glance !== []) : ?>
                    <section class="glance-card">
                        <h2><?php echo esc_html($glance_h); ?></h2>
                        <dl>
                            <?php foreach ($glance as $item) :
                                $label = (string) ($item['label'] ?? '');
                                $value = (string) ($item['value'] ?? '');
                                if ($label === '' || $value === '') {
                                    continue;
                                }
                                $pending = !empty($item['is_pending']);
                                ?>
                                <div>
                                    <dt><?php echo esc_html($label); ?></dt>
                                    <dd<?php echo $pending ? ' class="pending-figure"' : ''; ?>><?php echo esc_html($value); ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                        <?php if (is_array($grants) && $grants !== []) : ?>
                            <div class="grant-icons" aria-label="<?php echo esc_attr__('Illustrative home energy measures', 'matrix-starter'); ?>">
                                <?php foreach ($grants as $icon) :
                                    if (!is_array($icon) || empty($icon['ID'])) {
                                        continue;
                                    }
                                    echo wp_get_attachment_image(
                                        (int) $icon['ID'],
                                        'thumbnail',
                                        false,
                                        [
                                            'alt'   => $icon['alt'] !== '' ? $icon['alt'] : ($icon['title'] ?? ''),
                                            'title' => $icon['title'] ?? '',
                                        ]
                                    );
                                endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <section class="partners-card">
                    <h2><?php echo esc_html($partners_h); ?></h2>
                    <?php if (is_array($logos) && $logos !== []) : ?>
                        <div class="project-partners">
                            <?php foreach ($logos as $logo) :
                                if (!is_array($logo) || empty($logo['ID'])) {
                                    continue;
                                }
                                echo wp_get_attachment_image(
                                    (int) $logo['ID'],
                                    'medium',
                                    false,
                                    [
                                        'alt' => $logo['alt'] !== '' ? $logo['alt'] : ($logo['title'] ?? ''),
                                    ]
                                );
                            endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p><?php echo esc_html($partners_fb); ?></p>
                    <?php endif; ?>
                </section>
            </aside>
        </div>

        <?php if ($related !== []) : ?>
            <section class="related-projects" aria-labelledby="related-title">
                <h2 id="related-title"><?php echo esc_html($related_h); ?></h2>
                <div class="related-grid">
                    <?php foreach ($related as $rel) :
                        $rid   = (int) $rel->ID;
                        $rterm = matrix_dz_project_primary_category($rid);
                        $rstyle = matrix_dz_project_category_style($rterm);
                        $thumb = get_the_post_thumbnail_url($rid, 'thumbnail');
                        ?>
                        <a href="<?php echo esc_url(get_permalink($rid)); ?>" class="related-card">
                            <?php if ($thumb) : ?>
                                <img class="related-photo" src="<?php echo esc_url($thumb); ?>" alt="" loading="lazy" width="64" height="64">
                            <?php endif; ?>
                            <span>
                                <span class="directory-category" style="--category:<?php echo esc_attr($rstyle['color']); ?>">
                                    <img src="<?php echo esc_url($rstyle['icon_url']); ?>" alt="" width="12" height="12">
                                    <?php echo esc_html($rstyle['label']); ?>
                                </span>
                                <strong><?php echo esc_html(get_the_title($rid)); ?></strong>
                            </span>
                            <span class="related-arrow" aria-hidden="true">↗</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($cta_h !== '' || $cta_t !== '' || (is_array($cta_btn) && !empty($cta_btn['url']))) : ?>
            <section class="detail-cta">
                <div>
                    <?php if ($cta_h !== '') : ?>
                        <h2><?php echo esc_html($cta_h); ?></h2>
                    <?php endif; ?>
                    <?php if ($cta_t !== '') : ?>
                        <p><?php echo esc_html($cta_t); ?></p>
                    <?php endif; ?>
                </div>
                <?php if (is_array($cta_btn) && !empty($cta_btn['url'])) : ?>
                    <a
                        class="button"
                        href="<?php echo esc_url($cta_btn['url']); ?>"
                        <?php echo !empty($cta_btn['target']) ? ' target="' . esc_attr($cta_btn['target']) . '" rel="noopener"' : ''; ?>
                    ><?php echo esc_html($cta_btn['title'] !== '' ? $cta_btn['title'] : __('Learn more', 'matrix-starter')); ?></a>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>
    <?php
endwhile;

get_footer();
