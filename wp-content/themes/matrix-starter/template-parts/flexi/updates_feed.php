<?php
/**
 * Updates feed — Community posts (recent or selected).
 */

$mode  = (string) (get_sub_field('feed_mode') ?: 'recent');
$empty = get_sub_field('empty_state') ?: [];
$count = (int) (get_sub_field('recent_count') ?: 3);
$ids   = [];

if ($mode === 'selected') {
    $selected = get_sub_field('selected_posts') ?: [];
    if (is_array($selected)) {
        foreach ($selected as $item) {
            if (is_object($item) && isset($item->ID)) {
                $ids[] = (int) $item->ID;
            } elseif (is_numeric($item)) {
                $ids[] = (int) $item;
            }
        }
    }
}

$query = matrix_dz_community_posts_query([
    'count'   => $count,
    'include' => $ids,
]);
?>
<section aria-label="<?php echo esc_attr__('Updates feed', 'matrix-starter'); ?>" class="dz-flexi flex-block flex-updates">
  <div class="container flex-demo">
    <div class="update-feed">
      <?php if ($query->have_posts()) : ?>
        <?php while ($query->have_posts()) :
            $query->the_post();
            $pid    = (int) get_the_ID();
            $author = matrix_dz_community_author_meta($pid);
            $aclass = 'update-author' . ($author['is_council'] ? ' council' : '');
            ?>
            <article class="update-card">
              <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('medium_large', ['class' => 'update-photo']); ?>
              <?php endif; ?>
              <div class="update-content">
                <p class="<?php echo esc_attr($aclass); ?>">
                  <span aria-hidden="true" class="group-avatar"><?php echo esc_html($author['initials']); ?></span>
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
      <?php elseif (!empty($empty['title'])) : ?>
        <div class="events-empty">
          <h3><?php echo esc_html($empty['title']); ?></h3>
          <?php if (!empty($empty['text'])) : ?><p><?php echo esc_html($empty['text']); ?></p><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
