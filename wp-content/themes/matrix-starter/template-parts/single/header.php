<?php
/**
 * Fallback singular header when no ACF hero blocks are assigned.
 *
 * @package Matrix_Starter
 *
 * @var int $post_id
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id   = (int) ($args['post_id'] ?? get_the_ID());
$post_type = get_post_type($post_id);

if (!$post_id || !$post_type) {
    return;
}

$post_type_obj = get_post_type_object($post_type);
$kicker        = ($post_type_obj && !empty($post_type_obj->labels->singular_name))
    ? (string) $post_type_obj->labels->singular_name
    : '';

$title   = get_the_title($post_id);
$excerpt = trim((string) get_the_excerpt($post_id));
?>
<section class="border-b border-[#ecf0f4] bg-white py-10 md:py-14">
    <div class="mx-auto w-full max-w-[1280px] px-5 md:px-10">
        <div class="mx-auto flex max-w-[720px] flex-col gap-3">
            <?php if ($kicker !== '' && $post_type !== 'post') : ?>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#4a5b6d]">
                    <?php echo esc_html($kicker); ?>
                </p>
            <?php endif; ?>

            <h1 class="text-3xl font-bold leading-tight text-[#1d1d1d] md:text-4xl">
                <?php echo esc_html($title); ?>
            </h1>

            <?php if ($excerpt !== '') : ?>
                <p class="text-lg leading-relaxed text-[#4a5b6d]">
                    <?php echo esc_html($excerpt); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>
