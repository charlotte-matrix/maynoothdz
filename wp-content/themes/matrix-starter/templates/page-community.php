<?php
/**
 * Template Name: Community
 * Template Post Type: page
 *
 * @package Matrix_Starter
 */

get_header();

while (have_posts()) :
    the_post();
    $post_id = (int) get_the_ID();

    $tag_label = (string) (get_field('cm_tag_label', $post_id) ?: __('Together in Maynooth', 'matrix-starter'));
    $tag_icon  = get_field('cm_tag_icon', $post_id);
    $title     = (string) (get_field('cm_title', $post_id) ?: get_the_title($post_id));
    $intro     = (string) get_field('cm_intro', $post_id);

    $updates_h = (string) (get_field('cm_updates_heading', $post_id) ?: __('Latest updates', 'matrix-starter'));
    $updates_n = (int) (get_field('cm_updates_count', $post_id) ?: 6);

    $events_h  = (string) (get_field('cm_events_heading', $post_id) ?: __('Events', 'matrix-starter'));
    $empty_t   = (string) (get_field('cm_events_empty_title', $post_id) ?: __('No upcoming events just yet', 'matrix-starter'));
    $empty_p   = (string) get_field('cm_events_empty_text', $post_id);

    $join_h    = (string) (get_field('cm_join_heading', $post_id) ?: __('Your group can publish here', 'matrix-starter'));
    $join_steps = get_field('cm_join_steps', $post_id);
    $join_btn  = (string) (get_field('cm_join_button_label', $post_id) ?: __('Register your group’s interest', 'matrix-starter'));

    $nl_h = (string) (get_field('cm_newsletter_heading', $post_id) ?: __('Stay in the loop', 'matrix-starter'));
    $nl_p = (string) get_field('cm_newsletter_intro', $post_id);

    $tag_icon_url = (is_array($tag_icon) && !empty($tag_icon['url']))
        ? $tag_icon['url']
        : matrix_dz_assets_url('community-icon.svg');

    if (!is_array($join_steps) || $join_steps === []) {
        $join_steps = [
            ['title' => __('Register interest', 'matrix-starter'), 'text' => __('Tell the Climate Action Office about your group.', 'matrix-starter')],
            ['title' => __('Council approves', 'matrix-starter'), 'text' => __('A quick check, then your group gets its page.', 'matrix-starter')],
            ['title' => __('Draft, review, publish', 'matrix-starter'), 'text' => __('Share updates and events in your own voice.', 'matrix-starter')],
        ];
    }

    $updates = matrix_dz_community_posts_query(['count' => $updates_n]);
    $events  = matrix_dz_upcoming_events(6);

    $register_url = wp_registration_url();
    ?>
<main id="main-content" class="site-main community-page-main">
    <section class="community-hero">
        <div class="container">
            <nav class="breadcrumbs" aria-label="<?php echo esc_attr__('Breadcrumb', 'matrix-starter'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'matrix-starter'); ?></a>
                <span aria-hidden="true">/</span>
                <span aria-current="page"><?php echo esc_html($title); ?></span>
            </nav>
            <?php if ($tag_label !== '') : ?>
                <span class="tag community-chip">
                    <img src="<?php echo esc_url($tag_icon_url); ?>" alt="" width="16" height="16">
                    <?php echo esc_html($tag_label); ?>
                </span>
            <?php endif; ?>
            <h1><?php echo esc_html($title); ?></h1>
            <?php if ($intro !== '') : ?>
                <p><?php echo esc_html($intro); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <div class="container community-content">
        <div class="community-columns">
            <section id="updates" aria-labelledby="updates-title">
                <div class="community-section-heading">
                    <h2 id="updates-title"><?php echo esc_html($updates_h); ?></h2>
                </div>
                <div class="update-feed">
                    <?php if ($updates->have_posts()) : ?>
                        <?php while ($updates->have_posts()) :
                            $updates->the_post();
                            $pid    = (int) get_the_ID();
                            $author = matrix_dz_community_author_meta($pid);
                            $classes = 'update-author' . ($author['is_council'] ? ' council' : '');
                            ?>
                            <article class="update-card">
                                <?php if (has_post_thumbnail()) : ?>
                                    <?php the_post_thumbnail('medium_large', ['class' => 'update-photo']); ?>
                                <?php endif; ?>
                                <div class="update-content">
                                    <p class="<?php echo esc_attr($classes); ?>">
                                        <span class="group-avatar" aria-hidden="true"><?php echo esc_html($author['initials']); ?></span>
                                        <?php echo esc_html($author['name']); ?>
                                    </p>
                                    <h3>
                                        <a class="update-open" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                    </h3>
                                    <?php if (has_excerpt() || get_the_excerpt()) : ?>
                                        <p class="update-excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
                                    <?php endif; ?>
                                    <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('j F Y')); ?></time>
                                </div>
                            </article>
                        <?php endwhile;
                        wp_reset_postdata(); ?>
                    <?php else : ?>
                        <div class="events-empty">
                            <h3><?php esc_html_e('Community updates are coming', 'matrix-starter'); ?></h3>
                            <p><?php esc_html_e('Hear from your group, in its own voice — new stories will appear as groups publish.', 'matrix-starter'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section id="events" aria-labelledby="events-title">
                <div class="community-section-heading">
                    <h2 id="events-title"><?php echo esc_html($events_h); ?></h2>
                </div>
                <?php if ($events !== []) : ?>
                    <div class="events-list">
                        <?php foreach ($events as $event) :
                            $eid   = (int) $event->ID;
                            $date  = (string) get_field('event_date', $eid);
                            $time  = (string) get_field('event_time', $eid);
                            $loc   = (string) get_field('event_location', $eid);
                            $ts    = $date !== '' ? strtotime($date) : false;
                            $month = $ts ? strtoupper(date_i18n('M', $ts)) : '';
                            $day   = $ts ? date_i18n('j', $ts) : '';
                            $meta  = array_filter([$time, $loc]);
                            ?>
                            <article class="flex-event">
                                <div class="event-summary">
                                    <?php if ($month !== '') : ?>
                                        <div class="event-date" aria-label="<?php echo esc_attr($ts ? date_i18n('j F Y', $ts) : ''); ?>">
                                            <span><?php echo esc_html($month); ?></span>
                                            <strong><?php echo esc_html($day); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h3><a href="<?php echo esc_url(get_permalink($eid)); ?>"><?php echo esc_html(get_the_title($eid)); ?></a></h3>
                                        <?php if ($meta !== []) : ?>
                                            <p><?php echo esc_html(implode(' · ', $meta)); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="events-empty">
                        <span class="event-symbol" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6M17 2v6M3 11h18M8 15h3M8 18h6"/></svg>
                        </span>
                        <h3><?php echo esc_html($empty_t); ?></h3>
                        <?php if ($empty_p !== '') : ?>
                            <p><?php echo esc_html($empty_p); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <section class="group-publishing" id="join" aria-labelledby="group-publishing-title">
            <h2 id="group-publishing-title"><?php echo esc_html($join_h); ?></h2>
            <ol class="publishing-steps">
                <?php foreach ($join_steps as $i => $step) :
                    $st = trim((string) ($step['title'] ?? ''));
                    $sx = trim((string) ($step['text'] ?? ''));
                    if ($st === '') {
                        continue;
                    }
                    ?>
                    <li>
                        <span class="publishing-number" aria-hidden="true"><?php echo esc_html((string) ($i + 1)); ?></span>
                        <div>
                            <h3><?php echo esc_html($st); ?></h3>
                            <?php if ($sx !== '') : ?><p><?php echo esc_html($sx); ?></p><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>

            <a class="button" href="<?php echo esc_url($register_url); ?>"><?php echo esc_html($join_btn); ?></a>
        </section>

        <section class="community-newsletter" aria-labelledby="newsletter-title">
            <div>
                <h2 id="newsletter-title"><?php echo esc_html($nl_h); ?></h2>
                <?php if ($nl_p !== '') : ?><p><?php echo esc_html($nl_p); ?></p><?php endif; ?>
            </div>
            <div>
                <form data-dz-newsletter>
                    <div class="newsletter-fields">
                        <label>
                            <span class="newsletter-label"><?php esc_html_e('Full name', 'matrix-starter'); ?></span>
                            <input autocomplete="name" maxlength="120" name="name" placeholder="e.g. Alex Murphy" required type="text">
                        </label>
                        <label>
                            <span class="newsletter-label"><?php esc_html_e('Email', 'matrix-starter'); ?></span>
                            <input autocomplete="email" maxlength="254" name="email" placeholder="e.g. alex@example.com" required type="email">
                        </label>
                        <button class="button" type="submit"><?php esc_html_e('Subscribe', 'matrix-starter'); ?></button>
                    </div>
                    <div class="newsletter-consent">
                        <input id="newsletter-consent" name="consent" required type="checkbox">
                        <label for="newsletter-consent">
                            <?php esc_html_e('I agree to the DZ', 'matrix-starter'); ?>
                            <button class="inline-policy" data-info="privacy" type="button"><?php esc_html_e('privacy policy', 'matrix-starter'); ?></button>
                            <?php esc_html_e('and', 'matrix-starter'); ?>
                            <button class="inline-policy" data-info="cookies" type="button"><?php esc_html_e('cookie policy', 'matrix-starter'); ?></button>.
                        </label>
                    </div>
                </form>
                <div aria-live="polite" hidden data-newsletter-feedback role="status">
                    <p><?php esc_html_e('Your request is ready. Send the email to ask for community updates; your subscription is not yet confirmed.', 'matrix-starter'); ?></p>
                    <a class="button small" data-newsletter-email href="#"><?php esc_html_e('Send email request ↗', 'matrix-starter'); ?></a>
                </div>
            </div>
        </section>
    </div>
</main>
    <?php
endwhile;

get_footer();
