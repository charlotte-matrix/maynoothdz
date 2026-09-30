<?php
$cards = get_sub_field('cards') ?: [];
$empty = get_sub_field('empty_state') ?: [];
if (!is_array($cards)) { $cards = []; }
?>
<section aria-label="<?php echo esc_attr__('Project card grid', 'matrix-starter'); ?>" class="dz-flexi flex-project-cards">
  <div class="container flex-demo">
    <div class="flex-card-grid">
      <?php foreach ($cards as $card) :
        $image = $card['image'] ?? null;
        $tag = (string) ($card['tag'] ?? 'retrofit');
        $status = trim((string) ($card['status'] ?? ''));
        $title = trim((string) ($card['title'] ?? ''));
        $excerpt = (string) ($card['excerpt'] ?? '');
        $link = matrix_dz_link_attrs($card['link'] ?? null);
        if ($title === '') { continue; }
      ?>
        <article class="project-card">
          <?php if (is_array($image) && !empty($image['ID'])) : ?>
            <div class="card-image"><?php echo wp_get_attachment_image((int) $image['ID'], 'large', false, ['loading' => 'lazy']); ?></div>
          <?php elseif (is_array($image) && !empty($image['url'])) : ?>
            <div class="card-image"><img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt'] ?? ''); ?>" loading="lazy"></div>
          <?php endif; ?>
          <div class="card-content">
            <div class="card-labels">
              <span class="tag <?php echo esc_attr($tag); ?>"><img alt="" src="<?php echo esc_url(matrix_dz_tag_icon($tag)); ?>"><?php echo esc_html(matrix_dz_tag_label($tag)); ?></span>
              <?php if ($status !== '') : ?><span class="status"><?php echo esc_html($status); ?></span><?php endif; ?>
            </div>
            <h3>
              <?php if ($link) : ?>
                <a class="project-link" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($title); ?></a>
              <?php else : ?>
                <?php echo esc_html($title); ?>
              <?php endif; ?>
            </h3>
            <?php if ($excerpt !== '') : ?><p><?php echo esc_html($excerpt); ?></p><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>

      <?php
        $empty_title = trim((string) ($empty['title'] ?? ''));
        if ($empty_title !== '') :
      ?>
        <article class="flex-empty">
          <?php if (!empty($empty['eyebrow'])) : ?><span class="flex-eyebrow"><?php echo esc_html($empty['eyebrow']); ?></span><?php endif; ?>
          <h3><?php echo esc_html($empty_title); ?></h3>
          <?php if (!empty($empty['text'])) : ?><p><?php echo esc_html($empty['text']); ?></p><?php endif; ?>
        </article>
      <?php endif; ?>
    </div>
  </div>
</section>
