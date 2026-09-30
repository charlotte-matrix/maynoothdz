<?php
$heading = trim((string) get_sub_field('heading'));
$browse = matrix_dz_link_attrs(get_sub_field('browse_link'));
$cards = get_sub_field('cards') ?: [];
if (!is_array($cards) || $cards === []) { return; }
$track_id = 'project-track-' . wp_unique_id();
?>
<section class="dz-home projects section" id="projects" aria-labelledby="projects-title">
  <div class="section-heading container reveal">
    <?php if ($heading !== '') : ?><h2 id="projects-title"><?php echo esc_html($heading); ?></h2><?php endif; ?>
    <?php if ($browse) : ?>
      <a class="text-link" href="<?php echo esc_url($browse['url']); ?>"><?php echo esc_html($browse['title'] !== '' ? $browse['title'] : __('Browse all projects →', 'matrix-starter')); ?></a>
    <?php endif; ?>
  </div>
  <div class="carousel project-carousel reveal" data-carousel="projects">
    <div class="track" id="<?php echo esc_attr($track_id); ?>" tabindex="0" role="region" aria-label="<?php echo esc_attr__('Featured projects, scroll horizontally', 'matrix-starter'); ?>">
      <?php foreach ($cards as $card) :
        $title = trim((string) ($card['title'] ?? ''));
        if ($title === '') { continue; }
        $tag = (string) ($card['tag'] ?? 'retrofit');
        $link = matrix_dz_link_attrs($card['link'] ?? null);
        $image = $card['image'] ?? null;
      ?>
        <article class="project-card">
          <?php if (is_array($image) && !empty($image['ID'])) : ?>
            <div class="card-image"><?php echo wp_get_attachment_image((int) $image['ID'], 'large', false, ['loading' => 'lazy']); ?></div>
          <?php elseif (is_array($image) && !empty($image['url'])) : ?>
            <div class="card-image"><img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt'] ?? ''); ?>" loading="lazy"></div>
          <?php endif; ?>
          <div class="card-content">
            <div class="card-labels">
              <span class="tag <?php echo esc_attr($tag); ?>"><img src="<?php echo esc_url(matrix_dz_tag_icon($tag)); ?>" alt=""><?php echo esc_html(matrix_dz_tag_label($tag)); ?></span>
              <?php if (!empty($card['status'])) : ?><span class="status"><?php echo esc_html($card['status']); ?></span><?php endif; ?>
            </div>
            <h3>
              <?php if ($link) : ?><a class="project-link" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($title); ?></a>
              <?php else : ?><?php echo esc_html($title); ?><?php endif; ?>
            </h3>
            <?php if (!empty($card['excerpt'])) : ?><p><?php echo esc_html($card['excerpt']); ?></p><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <div class="carousel-controls container">
      <div class="scroll-progress" aria-hidden="true"><span></span></div>
      <button class="arrow-button previous" aria-label="<?php echo esc_attr__('Previous projects', 'matrix-starter'); ?>" aria-controls="<?php echo esc_attr($track_id); ?>" type="button"><img src="<?php echo esc_url(matrix_dz_assets_url('previous.svg')); ?>" alt="" width="32" height="32"></button>
      <button class="arrow-button next" aria-label="<?php echo esc_attr__('Next projects', 'matrix-starter'); ?>" aria-controls="<?php echo esc_attr($track_id); ?>" type="button"><img src="<?php echo esc_url(matrix_dz_assets_url('next.svg')); ?>" alt="" width="32" height="32"></button>
    </div>
  </div>
</section>
