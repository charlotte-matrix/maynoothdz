<?php
$partners = get_sub_field('partners') ?: [];
if (!is_array($partners) || $partners === []) {
    return;
}

// Design sizes for the three standard partner logos (kildare / climate / government).
$design_widths = [177, 81, 115];
?>
<section aria-label="<?php echo esc_attr__('Partner strip', 'matrix-starter'); ?>" class="dz-flexi flex-block flex-partners">
  <div class="container flex-demo flex-partners">
    <div class="partners">
      <?php
      $index = 0;
      foreach ($partners as $partner) :
        $logo = $partner['logo'] ?? null;
        if (!is_array($logo)) {
            continue;
        }

        $alt    = (string) ($logo['alt'] ?? $logo['title'] ?? '');
        $width  = (int) ($design_widths[$index] ?? 120);
        $height = 40;
        $index++;

        if (!empty($logo['ID'])) {
            echo wp_get_attachment_image(
                (int) $logo['ID'],
                'full',
                false,
                [
                    'alt'      => $alt,
                    'width'    => $width,
                    'height'   => $height,
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                    'sizes'    => $width . 'px',
                ]
            );
        } elseif (!empty($logo['url'])) {
            printf(
                '<img src="%s" alt="%s" width="%d" height="%d" loading="lazy" decoding="async">',
                esc_url($logo['url']),
                esc_attr($alt),
                $width,
                $height
            );
        }
      endforeach;
      ?>
    </div>
  </div>
</section>
