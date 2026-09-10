<?php
/**
 * Related posts (same post type, prefers shared categories).
 *
 * @package Matrix_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

$args = wp_parse_args($args ?? [], [
    'heading' => __('Related posts', 'matrix-starter'),
    'limit'   => 3,
    'orderby' => 'date',
    'order'   => 'DESC',
]);

$heading = is_string($args['heading']) ? $args['heading'] : __('Related posts', 'matrix-starter');
$limit   = max(1, (int) $args['limit']);
$orderby = in_array($args['orderby'], ['date', 'modified', 'rand', 'title'], true) ? $args['orderby'] : 'date';
$order   = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';

$current_id = get_the_ID();
if (!$current_id) {
    return;
}

$cat_ids = [];
$cats    = get_the_terms($current_id, 'category');
if (!empty($cats) && !is_wp_error($cats)) {
    foreach ($cats as $c) {
        $cat_ids[] = (int) $c->term_id;
    }
}

$q_args = [
    'post_type'           => 'post',
    'posts_per_page'      => $limit,
    'post_status'         => 'publish',
    'orderby'             => $orderby,
    'order'               => $order,
    'post__not_in'        => [$current_id],
    'ignore_sticky_posts' => true,
];

if ($cat_ids !== []) {
    $q_args['category__in'] = $cat_ids;
}

$q = new WP_Query($q_args);

if (!$q->have_posts() && $cat_ids !== []) {
    unset($q_args['category__in']);
    $q = new WP_Query($q_args);
}

if (!$q->have_posts()) {
    return;
}

$read_more_label = __('Read more', 'matrix-starter');
$section_id      = 'related-posts-' . wp_generate_uuid4();
?>
<section
    id="<?php echo esc_attr($section_id); ?>"
    class="related-posts border-t border-[#ecf0f4] bg-[#f8f9fa]"
    aria-labelledby="<?php echo esc_attr($section_id); ?>-heading"
>
    <div class="mx-auto max-w-[1280px] px-5 py-12 md:px-10 md:py-16">
        <?php if ($heading !== '') : ?>
            <h2
                id="<?php echo esc_attr($section_id); ?>-heading"
                class="text-2xl font-bold text-[#1d1d1d] md:text-3xl"
            >
                <?php echo esc_html($heading); ?>
            </h2>
        <?php endif; ?>

        <ul class="mt-8 grid list-none grid-cols-1 gap-6 p-0 md:mt-10 md:grid-cols-2 lg:grid-cols-3">
            <?php
            while ($q->have_posts()) :
                $q->the_post();
                $related_id = get_the_ID();
                ?>
                <li>
                    <article class="flex h-full flex-col rounded-lg border border-[#ecf0f4] bg-white p-5 shadow-sm">
                        <h3 class="text-lg font-semibold leading-snug">
                            <a class="text-[#003b65] hover:underline" href="<?php the_permalink(); ?>">
                                <?php the_title(); ?>
                            </a>
                        </h3>
                        <?php if (has_excerpt($related_id)) : ?>
                            <p class="mt-2 flex-1 text-sm leading-relaxed text-[#4a5b6d]">
                                <?php echo esc_html(get_the_excerpt($related_id)); ?>
                            </p>
                        <?php endif; ?>
                        <p class="mt-4">
                            <a class="text-sm font-semibold text-[#0a60a0] hover:underline" href="<?php the_permalink(); ?>">
                                <?php echo esc_html($read_more_label); ?>
                                <span aria-hidden="true">→</span>
                            </a>
                        </p>
                    </article>
                </li>
            <?php
            endwhile;
            wp_reset_postdata();
            ?>
        </ul>
    </div>
</section>
