<?php
/**
 * Author Section – Design-matched, with Team featured image > Gravatar fallback
 */

if (!defined('ABSPATH')) exit;

$author_id      = (int) get_post_field('post_author', get_the_ID());
$author_name    = get_the_author_meta('display_name', $author_id);
$post_permalink = get_permalink();
$post_title     = get_the_title();

// Attempt to find Team post where Title === Author Display Name
$team_post      = null;
$team_link      = '';
$profile_img    = ''; // final URL we will render

$team_q = new WP_Query([
    'post_type'           => 'team',
    'posts_per_page'      => 1,
    'post_status'         => 'publish',
    'title'               => $author_name, // exact match param
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
]);

if ($team_q->have_posts()) {
    $team_q->the_post();
    $team_post = get_post(get_the_ID());
    $team_link = get_permalink($team_post->ID);

    // Try team featured image first
    $thumb_id = get_post_thumbnail_id($team_post->ID);
    if ($thumb_id) {
        $profile_img = wp_get_attachment_image_url($thumb_id, 'medium_large');
    }
}
wp_reset_postdata();

// Fallbacks
if (empty($team_link)) {
    $team_link = get_author_posts_url($author_id);
}
if (empty($profile_img)) {
    // If no team featured image (or no team match), use author avatar/gravatar
    $profile_img = get_avatar_url($author_id, ['size' => 200]);
}

// Last update date
$modified_ts   = get_post_modified_time('U', true);
$datetime_attr = gmdate('Y-m-d', $modified_ts);
$human_date    = date_i18n('j F Y', $modified_ts);

// Share URLs
$share_url   = rawurlencode($post_permalink);
$share_title = rawurlencode(wp_strip_all_tags($post_title));

$share_facebook = 'https://www.facebook.com/sharer/sharer.php?u=' . $share_url;
$share_linkedin = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $share_url;
$share_bluesky  = 'https://bsky.app/intent/compose?text=' . $share_title . '%20' . $share_url;
?>

<section class="flex overflow-hidden relative bg-[#ededed]">
  <div class="flex flex-col items-center pt-5 pb-5 mx-auto w-full max-w-container max-lg:px-5">
    <div class="flex flex-wrap gap-10 justify-between items-center w-full text-black">
      <figure class="flex gap-8 items-center min-w-60 max-md:max-w-full">
        <a href="<?php echo esc_url($team_link); ?>" class="block" aria-label="<?php echo esc_attr($author_name); ?>">
          <img
            src="<?php echo esc_url($profile_img); ?>"
            alt="<?php echo esc_attr('Profile photo of ' . $author_name); ?>"
            class="object-cover shrink-0 aspect-square rounded-full w-[140px] h-[140px]"
            loading="lazy"
            decoding="async"
          />
        </a>
        <div class="flex flex-col justify-center min-w-60">
          <span class="text-2xl font-semibold leading-none text-black text-seconary">
            <a href="<?php echo esc_url($team_link); ?>" class="hover:opacity-80">
              <?php echo esc_html(sprintf('by %s', $author_name)); ?>
            </a>
          </span>
          <time class="mt-2 text-lg leading-none text-black" datetime="<?php echo esc_attr($datetime_attr); ?>">
            <?php echo esc_html(sprintf(__('Last update: %s', 'matrix-starter'), $human_date)); ?>
          </time>
        </div>
      </figure>

      <div class="flex gap-4 items-center text-lg leading-none min-w-60" role="group" aria-label="<?php esc_attr_e('Share this post', 'matrix-starter'); ?>">
        <span class="text-black"><?php esc_html_e('Share this post', 'matrix-starter'); ?></span>

        <a
          href="<?php echo esc_url($share_facebook); ?>"
          class="flex justify-center items-center w-8 h-8 bg-white rounded-full transition-opacity hover:opacity-80 shrink-0 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-transparent btn"
          aria-label="<?php esc_attr_e('Share on Facebook', 'matrix-starter'); ?>"
          target="_blank" rel="noopener"
        >
          <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M17.6055 17.3246H19.8992L20.8167 13.7924H17.6055V12.0262C17.6055 11.1167 17.6055 10.2601 19.4405 10.2601H20.8167V7.293C20.5176 7.25503 19.3882 7.16937 18.1954 7.16937C15.7045 7.16937 13.9356 8.63261 13.9356 11.3198V13.7924H11.1832V17.3246H13.9356V24.8307H17.6055V17.3246Z" fill="#00628F"></path></svg>
        </a>

        <a
          href="<?php echo esc_url($share_linkedin); ?>"
          class="flex justify-center items-center w-8 h-8 bg-white rounded-full transition-opacity hover:opacity-80 shrink-0 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-transparent btn"
          aria-label="<?php esc_attr_e('Share on LinkedIn', 'matrix-starter'); ?>"
          target="_blank" rel="noopener"
        >
          <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M11.99 9.60071C11.9898 10.025 11.8215 10.4319 11.5223 10.7318C11.223 11.0317 10.8172 11.2 10.3942 11.1998C9.9712 11.1996 9.5656 11.0308 9.26664 10.7307C8.96767 10.4305 8.79984 10.0234 8.80005 9.59911C8.80026 9.17479 8.9685 8.76793 9.26777 8.46804C9.56703 8.16815 9.9728 7.99979 10.3958 8C10.8188 8.00021 11.2244 8.16898 11.5234 8.46917C11.8223 8.76936 11.9902 9.17639 11.99 9.60071ZM12.0378 12.3846H8.8479V22.4H12.0378V12.3846ZM17.0779 12.3846H13.9039V22.4H17.046V17.1443C17.046 14.2165 20.85 13.9445 20.85 17.1443V22.4H24V16.0564C24 11.1206 18.3698 11.3046 17.046 13.7285L17.0779 12.3846Z" fill="#00628F"></path></svg>
        </a>

        <a
          href="<?php echo esc_url($share_bluesky); ?>"
          class="flex justify-center items-center w-8 h-8 bg-white rounded-full transition-opacity hover:opacity-80 shrink-0 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-transparent btn"
          aria-label="<?php esc_attr_e('Share on Bluesky', 'matrix-starter'); ?>"
          target="_blank" rel="noopener"
        >
          <svg width="16" height="14" viewBox="0 0 16 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M3.46829 0.94239C5.30251 2.31114 7.2757 5.08593 7.99995 6.57504C8.72421 5.08593 10.6975 2.31114 12.5317 0.94239C13.8555 -0.0449892 16 -0.809181 16 1.62217C16 2.10758 15.7199 5.70132 15.5555 6.28489C14.9845 8.31295 12.9032 8.83028 11.052 8.51726C14.288 9.06455 15.1111 10.8772 13.3333 12.69C9.95702 16.133 8.48052 11.8262 8.10248 10.7227C7.99345 10.405 8.00902 10.3977 7.89745 10.7227C7.51939 11.8262 6.04301 16.133 2.66676 12.69C0.888939 10.8772 1.71203 9.06455 4.94798 8.51726C3.09676 8.83028 1.01547 8.31295 0.44447 6.28489C0.280063 5.70132 0 2.10758 0 1.62217C0 -0.809181 2.14479 -0.0449892 3.46829 0.94239Z" fill="#00628F"></path></svg>
        </a>
      </div>
    </div>
  </div>
</section>
