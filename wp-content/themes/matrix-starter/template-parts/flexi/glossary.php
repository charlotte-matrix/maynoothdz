<?php
$term = trim((string) get_sub_field('term'));
$definition = (string) get_sub_field('definition');
$anchor = sanitize_title((string) get_sub_field('anchor_id'));
if ($anchor === '') { $anchor = sanitize_title($term); }
$link = matrix_dz_link_attrs(get_sub_field('link'));
if ($term === '') { return; }
?>
<section aria-label="<?php echo esc_attr__('Glossary', 'matrix-starter'); ?>" class="dz-flexi flex-block flex-glossary-block">
  <div class="container flex-demo">
    <aside class="flex-glossary flex-narrow" id="<?php echo esc_attr($anchor); ?>">
      <h2><?php echo esc_html($term); ?></h2>
      <?php if ($definition !== '') : ?><p><?php echo wp_kses_post($definition); ?></p><?php endif; ?>
      <?php if ($link) : ?>
        <a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['title'] !== '' ? $link['title'] : __('Learn more →', 'matrix-starter')); ?></a>
      <?php endif; ?>
    </aside>
  </div>
</section>
