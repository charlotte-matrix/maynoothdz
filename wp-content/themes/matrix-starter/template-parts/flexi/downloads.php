<?php
$heading = trim((string) get_sub_field('heading'));
$items = get_sub_field('items') ?: [];
$note = (string) get_sub_field('accessibility_note');
if (!is_array($items)) { $items = []; }
?>
<section aria-label="<?php echo esc_attr__('Downloads list', 'matrix-starter'); ?>" class="dz-flexi flex-block flex-downloads">
  <div class="container flex-demo">
    <div class="resources-column">
      <section class="resource-group">
        <?php if ($heading !== '') : ?><h2><?php echo esc_html($heading); ?></h2><?php endif; ?>
        <ul>
          <?php foreach ($items as $item) :
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') { continue; }
            $file = $item['file'] ?? null;
            $url = '';
            if (is_array($file) && !empty($file['url'])) { $url = $file['url']; }
            elseif (!empty($item['url'])) { $url = $item['url']; }
            if ($url === '') { continue; }
          ?>
            <li>
              <a class="resource-row" href="<?php echo esc_url($url); ?>">
                <span class="resource-type"><?php echo esc_html($item['type'] ?? 'LINK'); ?></span>
                <span class="resource-name"><?php echo esc_html($name); ?></span>
                <?php if (!empty($item['meta'])) : ?><span class="resource-meta"><?php echo esc_html($item['meta']); ?> <span aria-hidden="true">↗</span></span><?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php if (trim(wp_strip_all_tags($note)) !== '') : ?>
        <p class="resource-accessibility"><?php echo wp_kses_post($note); ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>
