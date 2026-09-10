<?php
get_header();
$enable_breadcrumbs = get_field('enable_breadcrumbs', 'option'); // Returns true/false
?>
<main id="main-content" class="overflow-hidden w-full site-main">
    <?php load_hero_templates(); ?>


    <?php
    $enable_breadcrumbs = get_field('enable_breadcrumbs', 'option');
    $skip_breadcrumbs   = is_page(['contact-us', 'about-us']);

    if ($enable_breadcrumbs !== false && !$skip_breadcrumbs) :
        get_template_part('template-parts/header/breadcrumbs');
    endif;
    ?>

    <?php
    if (have_posts()) :
        while (have_posts()) : the_post();
            if (trim(get_the_content()) != '') : ?>
                <div class="<?php echo esc_attr(function_exists('is_checkout') && is_checkout() ? 'max-w-[1095px] mx-auto max-xl:px-5' : matrix_content_container_classes()); ?>">
                    <?php
                    get_template_part('template-parts/content/content', 'page');
                    ?>
                </div>
    <?php endif;
        endwhile;
    else :
        echo '<p>No content found</p>';
    endif;
    ?>

    <?php load_flexible_content_templates(); ?>
</main>

<?php
get_footer();
?>