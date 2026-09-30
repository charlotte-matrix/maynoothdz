<?php
$section_id = sanitize_title((string) get_sub_field('section_id')) ?: 'community';
$community = get_sub_field('community') ?: [];
$map = get_sub_field('map') ?: [];
$c_heading = trim((string) ($community['heading'] ?? ''));
$c_text = (string) ($community['text'] ?? '');
$c_image = $community['image'] ?? null;
$c_button = $community['button'] ?? null;
$m_heading = trim((string) ($map['heading'] ?? ''));
$m_subtext = trim((string) ($map['subtext'] ?? ''));
$m_image = $map['map_image'] ?? null;
$m_button = matrix_dz_link_attrs($map['button'] ?? null);
$map_url = is_array($m_image) && !empty($m_image['url']) ? $m_image['url'] : matrix_dz_assets_url('map.webp');
?>
<section class="dz-home community section container" id="<?php echo esc_attr($section_id); ?>" aria-label="<?php echo esc_attr($c_heading !== '' ? $c_heading : __('From the community', 'matrix-starter')); ?>">
  <?php if ($c_heading !== '') : ?><h2 class="mobile-heading"><?php echo esc_html($c_heading); ?></h2><?php endif; ?>
  <div class="community-card reveal">
    <?php if ($c_heading !== '') : ?><h2><?php echo esc_html($c_heading); ?></h2><?php endif; ?>
    <?php if (is_array($c_image) && !empty($c_image['ID'])) {
      echo wp_get_attachment_image((int) $c_image['ID'], 'large', false, ['class' => 'community-photo', 'loading' => 'lazy']);
    } elseif (is_array($c_image) && !empty($c_image['url'])) {
      echo '<img class="community-photo" src="' . esc_url($c_image['url']) . '" alt="' . esc_attr($c_image['alt'] ?? '') . '" loading="lazy">';
    } ?>
    <?php if ($c_text !== '') : ?><p><?php echo esc_html($c_text); ?></p><?php endif; ?>
    <?php matrix_dz_render_button($c_button, 'outline'); ?>
  </div>
  <div class="wander-card animated-gradient reveal" id="map">
    <div class="wander-heading">
      <?php if ($m_heading !== '') : ?><h2><?php echo esc_html($m_heading); ?></h2><?php endif; ?>
      <?php if ($m_subtext !== '') : ?><p><?php echo esc_html($m_subtext); ?></p><?php endif; ?>
    </div>
    <a class="map-art map-preview" href="<?php echo esc_url($m_button['url'] ?? home_url('/map/')); ?>" data-map-art aria-label="<?php echo esc_attr__('Explore the interactive map of Maynooth', 'matrix-starter'); ?>">
      <img class="map-base" src="<?php echo esc_url($map_url); ?>" alt="<?php echo esc_attr(is_array($m_image) ? ($m_image['alt'] ?? '') : ''); ?>" loading="lazy">
      <span class="map-stickers" aria-hidden="true"></span>
    </a>
    <?php if ($m_button) : ?>
      <a href="<?php echo esc_url($m_button['url']); ?>" class="button">
        <img src="<?php echo esc_url(matrix_dz_assets_url('map-icon.svg')); ?>" width="16" height="16" alt="">
        <?php echo esc_html($m_button['title'] !== '' ? $m_button['title'] : __('Explore the map', 'matrix-starter')); ?>
      </a>
    <?php endif; ?>
  </div>
</section>
