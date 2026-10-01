<?php
$updates = get_sub_field('updates') ?: [];
$empty = get_sub_field('empty_state') ?: [];
if (!is_array($updates)) { $updates = []; }
?>
<section aria-label="<?php echo esc_attr__('Updates feed', 'matrix-starter'); ?>" class="dz-flexi flex-block flex-updates">
  <div class="container flex-demo">
    <div class="community-columns">
      <?php foreach ($updates as $update) :
        $title = trim((string) ($update['title'] ?? ''));
        if ($title === '') { continue; }
        $link = matrix_dz_link_attrs($update['link'] ?? null);
        $image = $update['image'] ?? null;
        $date = (string) ($update['date'] ?? '');
        $date_label = $date !== '' ? date_i18n('j F Y', strtotime($date)) : '';
      ?>
        <article class="update-card">
          <?php if (is_array($image) && !empty($image['ID'])) :
            echo wp_get_attachment_image((int) $image['ID'], 'medium_large', false, ['class' => 'update-photo']);
          elseif (is_array($image) && !empty($image['url'])) : ?>
            <img class="update-photo" src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt'] ?? ''); ?>">
          <?php endif; ?>
          <div class="update-content">
            <?php if (!empty($update['author'])) : ?>
              <p class="update-author"><span aria-hidden="true" class="group-avatar"><?php echo esc_html($update['avatar_initials'] ?? ''); ?></span><?php echo esc_html($update['author']); ?></p>
            <?php endif; ?>
            <h3>
              <?php if ($link) : ?>
                <a class="update-open" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($title); ?></a>
              <?php else : ?>
                <?php echo esc_html($title); ?>
              <?php endif; ?>
            </h3>
            <?php if (!empty($update['excerpt'])) : ?><p class="update-excerpt"><?php echo esc_html($update['excerpt']); ?></p><?php endif; ?>
            <?php if ($date_label !== '') : ?><time datetime="<?php echo esc_attr($date); ?>"><?php echo esc_html($date_label); ?></time><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>

      <?php if (!empty($empty['title'])) : ?>
        <div class="events-empty">
          <h3><?php echo esc_html($empty['title']); ?></h3>
          <?php if (!empty($empty['text'])) : ?><p><?php echo esc_html($empty['text']); ?></p><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
