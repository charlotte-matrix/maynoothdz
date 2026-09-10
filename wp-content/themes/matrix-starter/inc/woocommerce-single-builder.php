<?php
add_action('wp', function () {
    if (!is_product()) return;
    if (!have_rows('woo_single_product_blocks', get_the_ID())) return;
    remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
    remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50);
});

function load_woo_single_product_templates($post_id = null)
{
    if (!$post_id) $post_id = get_the_ID();

    if ($post_id && have_rows('woo_single_product_blocks', $post_id)) {
        while (have_rows('woo_single_product_blocks', $post_id)) : the_row();
            $layout = get_row_layout(); // e.g. 'woo_single_main'
            $template = 'template-parts/woo/single/' . $layout . '.php';
            $path = get_theme_file_path('/' . $template);

            if (file_exists($path)) {
                get_template_part('template-parts/woo/single/' . $layout);
            } else {
                error_log("Woo Flexi: missing template for layout {$layout} at {$template}");
            }
        endwhile;
    }
}

function mytheme_render_single_product_builder()
{
    if (have_rows('woo_single_product_blocks', get_the_ID())) {
        load_woo_single_product_templates(get_the_ID());
    }
}
