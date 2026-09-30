<?php
/**
 * Rich text — article typography block.
 */

$content = (string) get_sub_field('content');

if (trim(wp_strip_all_tags($content)) === '' && strpos($content, '<img') === false) {
    return;
}
?>
<section aria-label="<?php echo esc_attr__('Rich text', 'matrix-starter'); ?>" class="dz-flexi flex-rich-text">
    <div class="container flex-demo">
        <div class="article-copy">
            <?php echo wp_kses_post($content); ?>
        </div>
    </div>
</section>
