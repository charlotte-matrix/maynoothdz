<?php
/**
 * Partners archive — lists partner posts (Figma 3:890 card grid).
 */

get_header();

require_once get_template_directory() . '/inc/helpers/partners.php';
?>
<main id="main-content" class="site-main w-full overflow-hidden bg-white font-montserrat">
    <?php
    $enable_breadcrumbs = get_field('enable_breadcrumbs', 'option');
    if ($enable_breadcrumbs !== false) {
        get_template_part('template-parts/header/breadcrumbs');
    }
    ?>

    <header class="bg-[#003b65] text-white">
        <div class="mx-auto w-full max-w-[1280px] px-5 py-12 lg:px-10 lg:py-16 xl:px-[120px]">
            <p class="text-[13px] font-semibold uppercase tracking-[0.14em] text-[#f4bd0b]"><?php esc_html_e('Our partners', 'matrix-starter'); ?></p>
            <h1 class="mt-3 text-[36px] font-extrabold leading-tight lg:text-[56px]"><?php esc_html_e('PACE Consortium', 'matrix-starter'); ?></h1>
        </div>
    </header>

    <div class="mx-auto w-full max-w-[1280px] px-5 py-10 lg:px-10 lg:py-16 xl:px-[120px]">
        <?php if (have_posts()) : ?>
            <ul class="grid grid-cols-1 gap-[54px] lg:grid-cols-2 lg:gap-5" role="list">
                <?php
                while (have_posts()) :
                    the_post();
                    $card = matrix_partners_card_from_post(get_the_ID());
                    if ($card === null) {
                        continue;
                    }
                    ?>
                    <li role="listitem">
                        <?php
                        get_template_part(
                            'template-parts/partners/card-link',
                            null,
                            [
                                'card'     => $card,
                                'title_id' => 'partner-archive-' . get_the_ID(),
                            ]
                        );
                        ?>
                    </li>
                <?php endwhile; ?>
            </ul>
            <?php the_posts_pagination(); ?>
        <?php else : ?>
            <p class="font-comfortaa text-[#1d1d1d]"><?php esc_html_e('No partners found.', 'matrix-starter'); ?></p>
        <?php endif; ?>
    </div>
</main>
<?php
get_footer();
