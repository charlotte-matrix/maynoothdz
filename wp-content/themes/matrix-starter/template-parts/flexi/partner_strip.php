<?php
$partners = get_sub_field('partners') ?: [];
if (!is_array($partners) || $partners === []) { return; }
?>
<section aria-label="<?php echo esc_attr__('Partner strip', 'matrix-starter'); ?>" class="dz-flexi flex-partners">
  <div class="container flex-demo flex-partners">
    <div class="partners">
      <?php foreach ($partners as $partner) :
        $logo = $partner['logo'] ?? null;
        if (!is_array($logo)) { continue; }
        if (!empty($logo['ID'])) {
          echo wp_get_attachment_image((int) $logo['ID'], 'medium', false, ['height' => 40]);
        } elseif (!empty($logo['url'])) {
          echo '<img src="' . esc_url($logo['url']) . '" alt="' . esc_attr($logo['alt'] ?? '') . '" height="40">';
        }
      endforeach; ?>
    </div>
  </div>
</section>
