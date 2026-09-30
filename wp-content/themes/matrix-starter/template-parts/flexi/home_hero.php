<?php
$title = (string) get_sub_field('title');
$introduction = (string) get_sub_field('introduction');
$primary = get_sub_field('primary_cta');
$secondary = get_sub_field('secondary_cta');
$map_image = get_sub_field('map_image');
$stats = get_sub_field('stats') ?: [];
$source_note = (string) get_sub_field('source_note');
$title_html = matrix_dz_title_html($title);
$map_url = is_array($map_image) && !empty($map_image['url']) ? $map_image['url'] : matrix_dz_assets_url('map.webp');
$map_alt = is_array($map_image) ? (string) ($map_image['alt'] ?? '') : 'Illustrated aerial view of Maynooth';
$source_id = 'source-' . wp_unique_id();
?>
<section class="dz-home hero animated-gradient" aria-labelledby="hero-title">
  <div class="hero-row">
    <div class="hero-copy">
      <?php if ($title_html !== '') : ?><h1 id="hero-title"><?php echo $title_html; ?></h1><?php endif; ?>
      <?php if ($introduction !== '') : ?><p><?php echo wp_kses_post($introduction); ?></p><?php endif; ?>
      <div class="hero-actions">
        <?php
        $p = matrix_dz_link_attrs($primary);
        if ($p) :
        ?>
          <a class="button" href="<?php echo esc_url($p['url']); ?>">
            <img src="<?php echo esc_url(matrix_dz_assets_url('map-icon.svg')); ?>" alt="">
            <?php echo esc_html($p['title'] !== '' ? $p['title'] : __('Explore map', 'matrix-starter')); ?>
          </a>
        <?php endif; ?>
        <?php matrix_dz_render_button($secondary, 'outline'); ?>
      </div>
    </div>
    <div class="map-art hero-map" data-map-art>
      <img class="map-base" src="<?php echo esc_url($map_url); ?>" alt="<?php echo esc_attr($map_alt); ?>" fetchpriority="high">
      <div class="map-stickers" aria-hidden="true"></div>
    </div>
  </div>
  <?php if (is_array($stats) && $stats) : ?>
    <div class="stats container">
      <?php foreach ($stats as $stat) :
        $value = trim((string) ($stat['value'] ?? ''));
        if ($value === '') { continue; }
        $animate = !empty($stat['animate']) && is_numeric($value);
        $decimals = (int) ($stat['decimals'] ?? 0);
        $suffix = (string) ($stat['suffix'] ?? '');
        $label = (string) ($stat['label'] ?? '');
        $display = matrix_dz_format_stat_display($value, $animate, $decimals, $suffix);
      ?>
        <div class="stat reveal">
          <?php if ($animate) : ?>
            <strong data-count="<?php echo esc_attr($value); ?>" <?php echo $decimals > 0 ? 'data-decimals="' . esc_attr((string) $decimals) . '"' : ''; ?> <?php echo $suffix !== '' ? 'data-suffix="' . esc_attr($suffix) . '"' : ''; ?>><?php echo esc_html($display); ?></strong>
          <?php else : ?>
            <strong><?php echo esc_html($display); ?></strong>
          <?php endif; ?>
          <?php if ($label !== '') : ?><p><?php echo wp_kses_post($label); ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (trim(wp_strip_all_tags($source_note)) !== '') : ?>
        <p class="source" id="<?php echo esc_attr($source_id); ?>"><?php echo wp_kses_post($source_note); ?></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="stripe bottom" aria-hidden="true"></div>
</section>
