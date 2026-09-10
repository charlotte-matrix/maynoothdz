<?php
use StoutLogic\AcfBuilder\FieldsBuilder;

if (function_exists('acf_add_local_field_group')) {
    add_action('acf/init', function () {
        if (!class_exists('WooCommerce')) return;

        $enabled = function_exists('get_field')
            ? (bool) get_field('enable_woocommerce', 'option')
            : (bool) get_option('options_enable_woocommerce');

        if (!$enabled) return;

        $layouts = [];
        foreach (glob(get_theme_file_path('/acf-fields/partials/woo/single-product/*.php')) as $file) {
            $layout = require $file; // must return FieldsBuilder (layout)
            if ($layout instanceof FieldsBuilder) {
                $layouts[] = $layout;
            } else {
                error_log("Woo Flexi: invalid layout returned from {$file}");
            }
        }

        $woo_single = new FieldsBuilder('woo_single_product_blocks', [
            'title' => 'Woo: Single Product Blocks',
        ]);

        $woo_single
            ->setLocation('post_type', '==', 'product')
            ->addFlexibleContent('woo_single_product_blocks', [
                'label'        => 'Single Product Blocks',
                'button_label' => 'Add Block',
            ])
                ->addLayouts($layouts)
            ->endFlexibleContent();

        acf_add_local_field_group($woo_single->build());
    });
}
