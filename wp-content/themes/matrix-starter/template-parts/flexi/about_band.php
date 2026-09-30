<?php
$section_id = sanitize_title((string) get_sub_field('section_id')) ?: 'about';
$heading = trim((string) get_sub_field('heading'));
$body = (string) get_sub_field('body');
$button = get_sub_field('button');
$image = get_sub_field('image');
$show_watermark = (bool) get_sub_field('show_watermark');
if ($heading === '') { return; }
?>
<section class="dz-home about section" id="<?php echo esc_attr($section_id); ?>" aria-labelledby="about-title">
  <div class="stripe top" aria-hidden="true"></div>
  <?php if ($show_watermark) : ?>
    <img class="brand-watermark" src="<?php echo esc_url(matrix_dz_assets_url('brand-watermark.svg')); ?>" width="546" height="367" alt="" aria-hidden="true">
  <?php endif; ?>
  <div class="about-grid container">
    <div class="about-copy reveal">
      <h2 id="about-title"><?php echo esc_html($heading); ?></h2>
      <?php if ($body !== '') : ?><p><?php echo wp_kses_post($body); ?></p><?php endif; ?>
      <?php matrix_dz_render_button($button, 'small'); ?>
    </div>
    <?php if (is_array($image) && (!empty($image['ID']) || !empty($image['url']))) : ?>
      <div class="about-photo reveal">
        <?php if (!empty($image['ID'])) {
          echo wp_get_attachment_image((int) $image['ID'], 'large', false, ['loading' => 'lazy']);
        } else {
          echo '<img src="' . esc_url($image['url']) . '" alt="' . esc_attr($image['alt'] ?? '') . '" loading="lazy">';
        } ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="stripe bottom" aria-hidden="true"></div>
</section>
