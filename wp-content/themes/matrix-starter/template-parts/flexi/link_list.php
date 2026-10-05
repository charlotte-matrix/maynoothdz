<?php
$heading = trim((string) get_sub_field('heading'));
$items = get_sub_field('items') ?: [];
$note = (string) get_sub_field('accessibility_note');
if (!is_array($items)) {
    $items = [];
}
if ($items === [] && trim(wp_strip_all_tags($note)) === '') {
    return;
}
?>
<section aria-label="<?php echo esc_attr__('Link list', 'matrix-starter'); ?>" class="dz-flexi flex-block flex-link-list">
  <div class="container flex-demo">
    <div class="resources-column">
      <?php if ($items !== []) : ?>
      <section class="resource-group">
        <?php if ($heading !== '') : ?><h2><?php echo esc_html($heading); ?></h2><?php endif; ?>
        <ul class="reading-list">
          <?php foreach ($items as $item) :
            $title = trim((string) ($item['title'] ?? ''));
            $url = (string) ($item['url'] ?? '');
            if ($title === '' || $url === '') { continue; }
          ?>
            <li>
              <a href="<?php echo esc_url($url); ?>">
                <strong><?php echo esc_html($title); ?> <span aria-hidden="true">↗</span></strong>
                <?php if (!empty($item['description'])) : ?><span><?php echo esc_html($item['description']); ?></span><?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>
      <?php if (trim(wp_strip_all_tags($note)) !== '') : ?>
        <p class="resource-accessibility"><?php echo wp_kses_post($note); ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>
