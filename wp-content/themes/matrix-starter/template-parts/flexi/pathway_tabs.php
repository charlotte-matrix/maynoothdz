<?php
$steps = get_sub_field('steps') ?: [];
$aside = get_sub_field('aside') ?: [];
if (!is_array($steps) || $steps === []) { return; }
$uid = wp_unique_id('path-');
?>
<section aria-label="<?php echo esc_attr__('Pathway tabs', 'matrix-starter'); ?>" class="dz-flexi flex-block dz-pathway flex-pathway">
  <div class="container flex-demo">
    <div class="pathway-tabs" role="tablist" aria-label="<?php echo esc_attr__('Pathway steps', 'matrix-starter'); ?>">
      <?php foreach ($steps as $i => $step) :
        $n = $i + 1;
        $label = trim((string) ($step['tab_label'] ?? ''));
        if ($label === '') { continue; }
      ?>
        <button aria-controls="<?php echo esc_attr($uid); ?>-panel-<?php echo esc_attr((string) $n); ?>" aria-selected="<?php echo $n === 1 ? 'true' : 'false'; ?>" data-step="<?php echo esc_attr((string) $n); ?>" id="<?php echo esc_attr($uid); ?>-tab-<?php echo esc_attr((string) $n); ?>" role="tab" tabindex="<?php echo $n === 1 ? '0' : '-1'; ?>" type="button">
          <span class="step-number"><?php echo esc_html((string) $n); ?></span><span><?php echo esc_html($label); ?></span>
        </button>
      <?php endforeach; ?>
    </div>
    <div class="pathway-layout">
      <div class="pathway-steps">
        <?php foreach ($steps as $i => $step) :
          $n = $i + 1;
          $label = trim((string) ($step['tab_label'] ?? ''));
          $title = trim((string) ($step['title'] ?? $label));
          $body = (string) ($step['body'] ?? '');
          $resources = $step['resources'] ?? [];
          if ($label === '') { continue; }
        ?>
          <section class="pathway-step<?php echo $n === 1 ? ' is-open' : ''; ?>" data-section="<?php echo esc_attr((string) $n); ?>">
            <h2 class="accordion-heading">
              <button aria-controls="<?php echo esc_attr($uid); ?>-panel-<?php echo esc_attr((string) $n); ?>" aria-expanded="<?php echo $n === 1 ? 'true' : 'false'; ?>" data-step="<?php echo esc_attr((string) $n); ?>" id="<?php echo esc_attr($uid); ?>-accordion-<?php echo esc_attr((string) $n); ?>" type="button">
                <span class="step-number"><?php echo esc_html((string) $n); ?></span><span><?php echo esc_html($label); ?></span><span aria-hidden="true" class="accordion-chevron">⌄</span>
              </button>
            </h2>
            <div class="step-panel" id="<?php echo esc_attr($uid); ?>-panel-<?php echo esc_attr((string) $n); ?>" role="tabpanel" <?php echo $n === 1 ? '' : 'hidden'; ?> tabindex="0">
              <h2 class="step-title"><?php echo esc_html($title); ?></h2>
              <?php if ($body !== '') : ?><p><?php echo wp_kses_post($body); ?></p><?php endif; ?>
              <?php if (is_array($resources) && $resources) : ?>
                <div class="pathway-resources">
                  <?php foreach ($resources as $res) :
                    if (empty($res['url']) || empty($res['label'])) { continue; }
                  ?>
                    <a href="<?php echo esc_url($res['url']); ?>"><span><?php echo esc_html($res['label']); ?></span><span aria-hidden="true"><?php echo esc_html($res['icon'] ?? '↗'); ?></span></a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <div class="step-navigation">
                <?php if ($n > 1) : ?>
                  <button class="button outline step-previous" data-go="<?php echo esc_attr((string) ($n - 1)); ?>" type="button">← <?php echo esc_html($steps[$i - 1]['tab_label'] ?? __('Previous', 'matrix-starter')); ?></button>
                <?php endif; ?>
                <?php if ($n < count($steps)) : ?>
                  <button class="button step-next" data-go="<?php echo esc_attr((string) ($n + 1)); ?>" type="button"><?php echo esc_html(sprintf(__('Next: %s →', 'matrix-starter'), $steps[$i + 1]['tab_label'] ?? '')); ?></button>
                <?php endif; ?>
              </div>
            </div>
          </section>
        <?php endforeach; ?>
      </div>
      <?php
        $aside_link = matrix_dz_link_attrs($aside['link'] ?? null);
        $aside_image = $aside['image'] ?? null;
        if ($aside_link || (is_array($aside_image) && !empty($aside_image['url']))) :
      ?>
        <aside aria-label="<?php echo esc_attr__('Case study', 'matrix-starter'); ?>" class="pathway-aside">
          <a class="pathway-case" href="<?php echo esc_url($aside_link['url'] ?? '#'); ?>">
            <?php if (is_array($aside_image) && !empty($aside_image['ID'])) {
              echo wp_get_attachment_image((int) $aside_image['ID'], 'large');
            } elseif (is_array($aside_image) && !empty($aside_image['url'])) {
              echo '<img src="' . esc_url($aside_image['url']) . '" alt="' . esc_attr($aside_image['alt'] ?? '') . '">';
            } ?>
            <span>
              <?php if (!empty($aside['title'])) : ?><strong><?php echo esc_html($aside['title']); ?></strong><?php endif; ?>
              <span><?php echo esc_html($aside['cta_label'] ?? __('Read the case study →', 'matrix-starter')); ?></span>
            </span>
          </a>
        </aside>
      <?php endif; ?>
    </div>
  </div>
</section>
