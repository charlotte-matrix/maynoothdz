<?php
/**
 * Community article (posts in the Community category).
 * Loaded from single-post.php.
 *
 * @package Matrix_Starter
 */

get_header();

while (have_posts()) :
    the_post();
    $post_id = (int) get_the_ID();
    $author  = matrix_dz_community_author_meta($post_id);
    $byline_name  = (string) get_field('community_byline_name', $post_id);
    $byline_group = (string) get_field('community_byline_group', $post_id);
    if ($byline_name === '') {
        $user = get_userdata((int) get_post_field('post_author', $post_id));
        $byline_name = $user ? (string) ($user->first_name ?: $user->display_name) : $author['name'];
        if ($user && $user->first_name && $user->last_name) {
            $byline_name = trim($user->first_name . ' ' . $user->last_name);
        }
    }
    if ($byline_group === '') {
        $byline_group = $author['name'];
    }
    $byline_initials = matrix_dz_initials_from_name($byline_name !== '' ? $byline_name : $author['name']);

    $linked = get_field('community_linked_event', $post_id);
    $event  = ($linked instanceof WP_Post) ? $linked : null;

    $related = matrix_dz_community_posts_query(['count' => 4]);
    $related_posts = array_values(array_filter($related->posts, static fn($p) => (int) $p->ID !== $post_id));
    $related_posts = array_slice($related_posts, 0, 1);

    $author_class = 'update-author' . ($author['is_council'] ? ' council' : '');
    ?>
<main id="main-content" class="site-main community-article">
    <section class="community-hero article-hero">
        <div class="container">
            <nav class="breadcrumbs" aria-label="<?php echo esc_attr__('Breadcrumb', 'matrix-starter'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'matrix-starter'); ?></a>
                <span aria-hidden="true">/</span>
                <a href="<?php echo esc_url(home_url('/community/')); ?>"><?php esc_html_e('Community', 'matrix-starter'); ?></a>
                <span aria-hidden="true">/</span>
                <span aria-current="page"><?php the_title(); ?></span>
            </nav>
            <p class="<?php echo esc_attr($author_class); ?>">
                <span class="group-avatar" aria-hidden="true"><?php echo esc_html($author['initials']); ?></span>
                <?php echo esc_html($author['name']); ?>
            </p>
            <h1><?php the_title(); ?></h1>
            <p class="article-date"><?php esc_html_e('Published', 'matrix-starter'); ?> <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('j F Y')); ?></time></p>
        </div>
    </section>

    <div class="container article-layout">
        <article class="article-body">
            <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('large', ['class' => 'article-photo']); ?>
            <?php endif; ?>
            <div class="article-copy">
                <?php the_content(); ?>
            </div>
            <div class="article-author">
                <span class="group-avatar" aria-hidden="true"><?php echo esc_html($byline_initials); ?></span>
                <div>
                    <strong><?php echo esc_html(sprintf(/* translators: %s: writer name */ __('Written by %s', 'matrix-starter'), $byline_name)); ?></strong>
                    <span><?php echo esc_html($byline_group); ?></span>
                </div>
            </div>
        </article>

        <aside class="article-sidebar" aria-label="<?php echo esc_attr__('Event and community updates', 'matrix-starter'); ?>">
            <?php if ($event) :
                $eid  = (int) $event->ID;
                $date = (string) get_field('event_date', $eid);
                $time = (string) get_field('event_time', $eid);
                $loc  = (string) get_field('event_location', $eid);
                $map  = (string) get_field('event_map_url', $eid);
                $ts   = $date !== '' ? strtotime($date) : false;
                $month = $ts ? strtoupper(date_i18n('M', $ts)) : '';
                $day   = $ts ? date_i18n('j', $ts) : '';
                ?>
                <section class="article-event">
                    <h2><?php esc_html_e('Event details', 'matrix-starter'); ?></h2>
                    <div class="event-summary">
                        <?php if ($month !== '') : ?>
                            <div class="event-date" aria-label="<?php echo esc_attr($ts ? date_i18n('j F Y', $ts) : ''); ?>">
                                <span><?php echo esc_html($month); ?></span>
                                <strong><?php echo esc_html($day); ?></strong>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h3><?php echo esc_html($loc !== '' ? $loc : get_the_title($eid)); ?></h3>
                            <?php if ($time !== '') : ?><p><?php echo esc_html($time); ?></p><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($map !== '') : ?>
                        <a class="event-map" href="<?php echo esc_url($map); ?>" aria-label="<?php echo esc_attr__('See location on the map', 'matrix-starter'); ?>">
                            <img src="<?php echo esc_url(matrix_dz_assets_url('map.webp')); ?>" alt="" width="800" height="500" loading="lazy">
                        </a>
                        <a class="event-map-link" href="<?php echo esc_url($map); ?>"><?php esc_html_e('See it on the map ↗', 'matrix-starter'); ?></a>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="community-newsletter" aria-labelledby="newsletter-title">
                <div>
                    <h2 id="newsletter-title"><?php esc_html_e('Stay in the loop', 'matrix-starter'); ?></h2>
                    <p><?php esc_html_e('Zone news and community events, straight to your inbox.', 'matrix-starter'); ?></p>
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
                            <input id="article-newsletter-consent" name="consent" required type="checkbox">
                            <label for="article-newsletter-consent">
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
        </aside>
    </div>

    <div class="container article-bottom">
        <?php if ($related_posts !== []) : ?>
            <section aria-labelledby="related-title">
                <h2 id="related-title"><?php esc_html_e('More from the community', 'matrix-starter'); ?></h2>
                <div class="related-updates">
                    <?php foreach ($related_posts as $rel) :
                        $rid = (int) $rel->ID;
                        $ra  = matrix_dz_community_author_meta($rid);
                        ?>
                        <a href="<?php echo esc_url(get_permalink($rid)); ?>">
                            <span class="group-avatar"><?php echo esc_html($ra['initials']); ?></span>
                            <div>
                                <h3><?php echo esc_html(get_the_title($rid)); ?></h3>
                                <p><?php echo esc_html($ra['name'] . ' · ' . get_the_date('j F Y', $rid)); ?></p>
                            </div>
                            <span aria-hidden="true">↗</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="article-join">
            <h2><?php esc_html_e('Your group could be publishing here too.', 'matrix-starter'); ?></h2>
            <a class="button" href="<?php echo esc_url(wp_registration_url()); ?>"><?php esc_html_e('Register your group’s interest', 'matrix-starter'); ?></a>
        </section>
    </div>
</main>
    <?php
endwhile;

get_footer();
