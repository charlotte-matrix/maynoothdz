<?php
$heading = trim((string) get_sub_field('heading'));
$subtext = trim((string) get_sub_field('subtext'));
$map_image = get_sub_field('map_image');
$button = get_sub_field('button');
$map_url = is_array($map_image) && !empty($map_image['url']) ? $map_image['url'] : matrix_dz_assets_url('map.webp');
$map_alt = is_array($map_image) ? (string) ($map_image['alt'] ?? '') : 'Illustrated map of Maynooth';
$link = matrix_dz_link_attrs($button);
?>
<section aria-label="<?php echo esc_attr__('Map teaser', 'matrix-starter'); ?>" class="dz-flexi flex-map-teaser">
  <div class="container flex-demo">
    <div class="flex-map-example">
      <div class="wander-card animated-gradient reveal">
        <div class="wander-heading">
          <?php if ($heading !== '') : ?><h2><?php echo esc_html($heading); ?></h2><?php endif; ?>
          <?php if ($subtext !== '') : ?><p><?php echo esc_html($subtext); ?></p><?php endif; ?>
        </div>
        <a class="map-art map-preview" href="<?php echo esc_url($link['url'] ?? home_url('/map/')); ?>" aria-label="<?php echo esc_attr__('Explore the interactive map of Maynooth', 'matrix-starter'); ?>">
          <img class="map-base" src="<?php echo esc_url($map_url); ?>" alt="<?php echo esc_attr($map_alt); ?>" loading="lazy">
          <span class="map-stickers" aria-hidden="true"></span>
        </a>
        <?php if ($link) : ?>
          <a class="button" href="<?php echo esc_url($link['url']); ?>">
            <img alt="" height="16" src="<?php echo esc_url(matrix_dz_assets_url('map-icon.svg')); ?>" width="16">
            <?php echo esc_html($link['title'] !== '' ? $link['title'] : __('Explore the map', 'matrix-starter')); ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
