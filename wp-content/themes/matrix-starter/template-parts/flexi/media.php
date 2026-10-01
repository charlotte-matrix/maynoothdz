<?php
$items = get_sub_field('items') ?: [];
if (!is_array($items) || $items === []) { return; }
?>
<section aria-label="<?php echo esc_attr__('Media', 'matrix-starter'); ?>" class="dz-flexi flex-block flex-media">
  <div class="container flex-demo">
    <div class="flex-media-grid">
      <?php foreach ($items as $item) :
        $type = (string) ($item['type'] ?? 'image');
        $caption = (string) ($item['caption'] ?? '');
      ?>
        <figure class="flex-figure">
          <?php if ($type === 'video') : ?>
            <div class="flex-video">
              <span aria-hidden="true" class="flex-play">▶</span>
              <?php if (!empty($item['video_title'])) : ?><h3><?php echo esc_html($item['video_title']); ?></h3><?php endif; ?>
              <p data-video-status>External videos load only after you give consent.</p>
              <button class="button" data-video-consent type="button">Allow video</button>
            </div>
          <?php else :
            $image = $item['image'] ?? null;
            if (is_array($image) && !empty($image['ID'])) {
              echo wp_get_attachment_image((int) $image['ID'], 'large', false, ['loading' => 'lazy']);
            } elseif (is_array($image) && !empty($image['url'])) {
              echo '<img src="' . esc_url($image['url']) . '" alt="' . esc_attr($image['alt'] ?? '') . '" loading="lazy">';
            }
          endif; ?>
          <?php if (trim(wp_strip_all_tags($caption)) !== '') : ?><figcaption><?php echo esc_html($caption); ?></figcaption><?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
