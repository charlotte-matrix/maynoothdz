<?php
$text_content = get_sub_field('text_content');

$padding_classes = [];
if (have_rows('padding_settings')) {
  while (have_rows('padding_settings')) {
    the_row();
    $screen = get_sub_field('screen_size');
    $pt = get_sub_field('padding_top');
    $pb = get_sub_field('padding_bottom');
    $padding_classes[] = "{$screen}:pt-[{$pt}rem]";
    $padding_classes[] = "{$screen}:pb-[{$pb}rem]";
  }
}
?>

<section class="flex overflow-hidden relative bg-white font-montserrat">
  <div class="<?php echo esc_attr(matrix_pace_content_container_classes()); ?>">
    <div class="pace-prose wp_editor">
      <div class="entry-content">
        <?php if ($text_content) : ?>
          <?php echo wp_kses_post($text_content); ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

