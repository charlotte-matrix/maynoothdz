<?php
/**
 * Rich text — article typography block.
 */

$content = (string) get_sub_field('content');

if (trim(wp_strip_all_tags($content)) === '' && strpos($content, '<img') === false) {
    return;
}

$allowed = wp_kses_allowed_html('post');
$allowed['figure'] = [
    'class' => true,
    'id'    => true,
];
$allowed['figcaption'] = [
    'class' => true,
];
$allowed['img']['class'] = true;
$allowed['img']['loading'] = true;
$allowed['img']['decoding'] = true;
$allowed['img']['width'] = true;
$allowed['img']['height'] = true;
?>
<section aria-label="<?php echo esc_attr__('Rich text', 'matrix-starter'); ?>" class="dz-flexi flex-block flex-rich-text">
    <div class="container flex-demo">
        <div class="article-copy">
            <?php echo wp_kses($content, $allowed); ?>
        </div>
    </div>
</section>
