<?php
/**
 * Post share row.
 *
 * @package Matrix_Starter
 *
 * @var array<string, string> $urls
 * @var string                $position top|bottom
 */

if (!defined('ABSPATH')) {
    exit;
}

$urls     = is_array($args['urls'] ?? null) ? $args['urls'] : [];
$position = (string) ($args['position'] ?? 'top');

if ($urls === []) {
    $post_id = get_the_ID();
    if ($post_id && function_exists('matrix_post_share_urls')) {
        $urls = matrix_post_share_urls((int) $post_id);
    }
}

if ($urls === []) {
    return;
}

$wrap_class = $position === 'bottom'
    ? 'post-share post-share--bottom mt-12 border-t border-[#ecf0f4] pt-10 md:mt-16 md:pt-12'
    : 'post-share post-share--top mb-8 md:mb-10';

$networks = [
    'facebook' => __('Share on Facebook', 'matrix-starter'),
    'linkedin' => __('Share on LinkedIn', 'matrix-starter'),
    'bluesky'  => __('Share on Bluesky', 'matrix-starter'),
];
?>
<div class="<?php echo esc_attr($wrap_class); ?>" role="group" aria-label="<?php esc_attr_e('Share this article', 'matrix-starter'); ?>">
    <div class="flex flex-wrap items-center gap-4">
        <span class="text-sm font-semibold text-[#003b65]">
            <?php esc_html_e('Share', 'matrix-starter'); ?>
        </span>
        <div class="flex flex-wrap items-center gap-3">
            <?php foreach ($networks as $key => $label) :
                if (empty($urls[$key])) {
                    continue;
                }
                ?>
                <a
                    href="<?php echo esc_url($urls[$key]); ?>"
                    class="<?php echo esc_attr(matrix_btn_classes('share')); ?> inline-flex items-center justify-center"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="<?php echo esc_attr($label); ?>"
                >
                    <?php
                    if (function_exists('matrix_share_icon_svg')) {
                        echo matrix_share_icon_svg($key); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                    ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
