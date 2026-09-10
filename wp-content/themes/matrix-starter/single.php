<?php
/**
 * Singular template — ACF hero when set, otherwise a simple title header.
 *
 * @package Matrix_Starter
 */

get_header();
?>
<main id="main-content" class="site-main w-full overflow-hidden bg-white">
<?php if (have_posts()) : ?>
    <?php while (have_posts()) : the_post(); ?>
        <?php
        $post_id   = (int) get_the_ID();
        $post_type = get_post_type($post_id);

        $has_hero = function_exists('have_rows') && have_rows('hero_content_blocks', $post_id);

        if ($has_hero && function_exists('load_hero_templates')) {
            load_hero_templates($post_id);
        } else {
            get_template_part('template-parts/single/header', null, [
                'post_id' => $post_id,
            ]);
        }

        $share_urls = function_exists('matrix_post_share_urls')
            ? matrix_post_share_urls($post_id)
            : [];
        ?>

        <div class="<?php echo esc_attr(matrix_content_container_classes()); ?>">
            <div class="<?php echo esc_attr(matrix_content_single_inner_classes()); ?>">
                <?php if ($share_urls !== []) : ?>
                    <?php
                    get_template_part('template-parts/single/share', null, [
                        'urls'     => $share_urls,
                        'position' => 'top',
                    ]);
                    ?>
                <?php endif; ?>

                <article <?php post_class(matrix_content_article_classes()); ?> id="post-<?php echo esc_attr((string) $post_id); ?>">
                    <?php the_content(); ?>
                </article>

                <?php if ($share_urls !== []) : ?>
                    <?php
                    get_template_part('template-parts/single/share', null, [
                        'urls'     => $share_urls,
                        'position' => 'bottom',
                    ]);
                    ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($post_type === 'post') : ?>
            <?php get_template_part('template-parts/single/related-posts'); ?>

            <?php if (locate_template('template-parts/flexi/newsletter_001.php') !== '') : ?>
                <?php get_template_part('template-parts/flexi/newsletter_001'); ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php load_flexible_content_templates($post_id); ?>
    <?php endwhile; ?>
<?php else : ?>
    <section class="py-16">
        <div class="mx-auto w-full max-w-[1280px] px-5 text-center">
            <p><?php esc_html_e('No content found.', 'matrix-starter'); ?></p>
        </div>
    </section>
<?php endif; ?>
</main>
<?php
get_footer();
