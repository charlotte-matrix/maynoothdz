<?php
get_header();
?>
<main id="main-content" class="site-main">
    <?php
    if (function_exists('load_hero_templates')) {
        load_hero_templates();
    }

    $enable_breadcrumbs = function_exists('get_field') ? get_field('enable_breadcrumbs', 'option') : false;
    $skip_breadcrumbs   = is_page(['contact-us', 'about-us', 'flexi']);
    $breadcrumb_tpl     = locate_template('template-parts/header/breadcrumbs.php');

    if ($enable_breadcrumbs !== false && !$skip_breadcrumbs && $breadcrumb_tpl) {
        get_template_part('template-parts/header/breadcrumbs');
    }

    if (have_posts()) :
        while (have_posts()) :
            the_post();
            if (trim((string) get_the_content()) !== '') :
                $container = function_exists('matrix_content_container_classes')
                    ? matrix_content_container_classes()
                    : 'container';
                if (function_exists('is_checkout') && is_checkout()) {
                    $container = 'max-w-[1095px] mx-auto max-xl:px-5';
                }
                ?>
                <div class="<?php echo esc_attr($container); ?>">
                    <?php get_template_part('template-parts/content/content', 'page'); ?>
                </div>
                <?php
            endif;
        endwhile;
    endif;

    if (function_exists('load_flexible_content_templates')) {
        load_flexible_content_templates();
    }
    ?>
</main>
<?php
get_footer();
