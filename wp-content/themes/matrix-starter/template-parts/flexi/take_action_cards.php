<?php
$heading = trim((string) get_sub_field('heading'));
$section_id = sanitize_title((string) get_sub_field('section_id')) ?: 'action';
$cards = get_sub_field('cards') ?: [];
if (!is_array($cards) || $cards === []) { return; }
?>
<section class="dz-home take-action section" id="<?php echo esc_attr($section_id); ?>" aria-labelledby="action-title">
  <div class="action-layout">
    <?php if ($heading !== '') : ?><h2 id="action-title" class="container reveal"><?php echo esc_html($heading); ?></h2><?php endif; ?>
    <div class="action-grid container reveal">
      <div id="action-track" role="region" aria-label="<?php echo esc_attr__('Take action pathways', 'matrix-starter'); ?>">
        <?php foreach ($cards as $card) :
          $title = trim((string) ($card['title'] ?? ''));
          if ($title === '') { continue; }
          $link = matrix_dz_link_attrs($card['link'] ?? null);
          $color = (string) ($card['color'] ?? 'sand');
          $icon = $card['icon'] ?? null;
          $href = $link['url'] ?? '#';
        ?>
          <a class="action-card <?php echo esc_attr($color); ?>" href="<?php echo esc_url($href); ?>">
            <span class="action-icon">
              <?php if (is_array($icon) && !empty($icon['ID'])) {
                echo wp_get_attachment_image((int) $icon['ID'], 'thumbnail', false, ['width' => 36, 'height' => 36]);
              } elseif (is_array($icon) && !empty($icon['url'])) {
                echo '<img src="' . esc_url($icon['url']) . '" width="36" height="36" alt="">';
              } else {
                echo '<img src="' . esc_url(matrix_dz_assets_url('leaf-action-icon.svg')) . '" width="36" height="36" alt="">';
              } ?>
            </span>
            <span>
              <strong><?php echo esc_html($title); ?></strong>
              <?php if (!empty($card['subtitle'])) : ?><span><?php echo esc_html($card['subtitle']); ?></span><?php endif; ?>
            </span>
            <img class="action-arrow" src="<?php echo esc_url(matrix_dz_assets_url('arrow.svg')); ?>" width="32" height="36" alt="">
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
