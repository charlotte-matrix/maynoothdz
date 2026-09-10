<?php
/**
 * inc/woocommerce.php
 * - Admin notice when Woo toggle is ON but WooCommerce is missing
 * - Gates all Woo customizations behind the theme option + plugin active
 * - Loads single-product builder hooks
 * - Disables default Woo styles (optional blocks dequeue)
 * - Declares theme support for Woo gallery features
 * - Applies option-driven removals for shop/single/cart/checkout
 * - Injects page hero for Shop/Product/Cart (respects per-post `hide_hero` toggle)
 * - Safe checkout tweaks without breaking Woo JS
 */

// 1) Admin notice: toggle ON but Woo not active
add_action('admin_notices', function () {
    if (
        function_exists('get_field') &&
        get_field('enable_woocommerce', 'option') &&
        ! class_exists('WooCommerce') &&
        current_user_can('install_plugins')
    ) {
        $install_url = esc_url(wp_nonce_url(
            self_admin_url('update.php?action=install-plugin&plugin=woocommerce'),
            'install-plugin_woocommerce'
        ));
        echo '<div class="notice notice-warning is-dismissible"><p>';
        printf(
            esc_html__('WooCommerce support is enabled in Theme Options, but WooCommerce isn’t active. %s.', 'matrix-starter'),
            '<a href="' . $install_url . '">' . esc_html__('Install & Activate WooCommerce', 'matrix-starter') . '</a>'
        );
        echo '</p></div>';
    }
});

// 2) Bail early if disabled or Woo missing
if (! function_exists('get_field') || ! get_field('enable_woocommerce', 'option') || ! class_exists('WooCommerce')) {
    return;
}

// 2.5) Load builder hooks (removals + render)
$hooks = get_theme_file_path('/inc/woocommerce-single-builder.php');
if (file_exists($hooks)) {
    require_once $hooks;
} else {
    error_log('Missing builder hooks file: ' . $hooks);
}

// 3) Disable Woo styles (optional; keep scripts intact)
add_filter('woocommerce_enqueue_styles', '__return_empty_array');
add_action('wp_enqueue_scripts', function () {
    // Remove Woo Blocks CSS if you’re not using Blocks templates
    wp_dequeue_style('wc-blocks-style');
    wp_dequeue_style('wc-blocks-vendors-style');
}, 100);

// 4) Theme support
add_action('after_setup_theme', function () {
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
});

// 5) Option-driven removals via template_redirect
add_action('template_redirect', function () {
    if (! function_exists('get_field') || ! class_exists('WooCommerce')) return;

    // Shop
    if (is_shop()) {
        if (get_field('hide_shop_breadcrumbs', 'option')) remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
        if (get_field('hide_result_count', 'option')) remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
        if (get_field('hide_catalog_ordering', 'option')) remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
        if (get_field('hide_shop_sidebar', 'option')) remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);
    }

    // Single Product
    if (is_product()) {
        if (get_field('hide_product_sidebar', 'option')) remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);
        if (get_field('disable_reviews', 'option')) {
            add_filter('comments_open', '__return_false', 20, 2);
            remove_action('woocommerce_after_single_product_summary', 'comments_template', 50);
        }
        if (get_field('hide_product_breadcrumbs', 'option')) remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
        if (get_field('hide_related_products', 'option')) remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
        if (get_field('hide_upsells', 'option')) remove_action('woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15);
    }

    // Cart
    if (is_cart() && get_field('hide_cart_cross_sells', 'option')) {
        remove_action('woocommerce_cart_collaterals', 'woocommerce_cross_sell_display');
    }
    if (is_checkout()) {
        if (get_field('hide_checkout_coupon_form', 'option')) remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
        if (get_field('hide_checkout_login_form', 'option')) remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10);
    }
});

// 7) AJAX cart count fragment
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    $count = (function_exists('WC') && WC()->cart) ? (int) WC()->cart->get_cart_contents_count() : 0;

    ob_start(); ?>
    <span
      class="woocommerce-cart-count <?php echo $count > 0 ? 'inline-flex justify-center items-center py-1 font-bold leading-none text-white rounded-full lg:px-2 lg:ml-2 bg-primary' : 'hidden'; ?>"
      aria-live="polite"
      aria-atomic="true"
    >
      <?php echo $count > 0 ? esc_html($count) : ''; ?>
    </span>
    <?php

    $fragments['.woocommerce-cart-count'] = ob_get_clean();
    return $fragments;
});

// 8) WooCommerce cart fragments (front-end only)
add_action('wp_enqueue_scripts', function () {
    if (class_exists('WooCommerce')) {
        wp_enqueue_script('wc-cart-fragments');
    }
}, 20);

// 9) Keep Woo checkout essentials non-deferred (safety net)
add_filter('script_loader_tag', function ($tag, $handle) {
    if (is_admin()) return $tag;

    $no_defer = [
        'jquery','jquery-core','jquery-migrate',
        'wc-checkout','woocommerce',
        'wc-country-select','wc-address-i18n',
        'selectWoo','jquery-blockui','jquery-payment',
        'wc-add-to-cart-variation','wc-password-strength-meter',
        'wc-credit-card-form','wc-cart-fragments'
    ];
    if (in_array($handle, $no_defer, true)) return $tag;

    return (strpos($tag, ' src=') !== false) ? str_replace(' src', ' defer src', $tag) : $tag;
}, 10, 2);

// 10) Add placeholders to checkout fields
add_filter('woocommerce_checkout_fields', function ($fields) {
    foreach ($fields as $section => &$section_fields) {
        foreach ($section_fields as $key => &$field) {
            if (! isset($field['placeholder']) && isset($field['label'])) {
                $field['placeholder'] = $field['label'];
            }
        }
    }
    return $fields;
});

// Shop hero
add_action('woocommerce_before_main_content', 'matrix_add_shop_hero_section', 5);
function matrix_add_shop_hero_section() {
    if (!is_shop()) return;
    if (function_exists('get_field') && get_field('hide_shop_hero', 'option')) return;

    $shop_id = wc_get_page_id('shop');
    if ($shop_id && get_post_status($shop_id)) {
        if (function_exists('get_field') && get_field('hide_hero', $shop_id)) return;

        global $post;
        $original_post = $post;
        $post = get_post($shop_id);
        setup_postdata($post);

        get_template_part('template-parts/page/hero');

        wp_reset_postdata();
        $post = $original_post;
    }
}

// Single product hero
add_action('woocommerce_before_single_product', 'matrix_add_product_hero_section', 5);
function matrix_add_product_hero_section() {
    if (!is_product()) return;
    if (function_exists('get_field') && get_field('hide_product_hero', 'option')) return;

    $product_id = get_the_ID();
    if (function_exists('get_field') && get_field('hide_hero', $product_id)) return;

    setup_postdata(get_post($product_id));
    get_template_part('template-parts/page/hero');
    wp_reset_postdata();
}

// Sidebar for filters in Shop
add_action('widgets_init', function() {
  register_sidebar([
    'name'          => __('Shop Filters', 'matrix-starter'),
    'id'            => 'shop-filters',
    'description'   => __('Widgets in this area will show in the shop filters sidebar.', 'matrix-starter'),
    'before_widget' => '<div id="%1$s" class="widget %2$s">',
    'after_widget'  => '</div>',
    'before_title'  => '<h3 class="mb-2 text-base font-bold leading-4 text-zinc-900">',
    'after_title'   => '</h3>',
  ]);
});

// Products per page
add_filter('loop_shop_per_page', function($cols) { return 15; }, 20);

// Remove default wrappers (we use theme wrappers)
remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
remove_action('woocommerce_after_main_content',  'woocommerce_output_content_wrapper_end', 10);

// Cart hero
add_action('woocommerce_before_main_content', 'matrix_add_cart_hero_section', 5);
function matrix_add_cart_hero_section() {
    if (!is_cart()) return;
    if (function_exists('get_field') && get_field('hide_cart_hero', 'option')) return;

    $cart_id = wc_get_page_id('cart');
    if ($cart_id && get_post_status($cart_id)) {
        if (function_exists('get_field') && get_field('hide_hero', $cart_id)) return;

        global $post;
        $original_post = $post;
        $post = get_post($cart_id);
        setup_postdata($post);

        get_template_part('template-parts/page/hero');

        wp_reset_postdata();
        $post = $original_post;
    }
}

// Force 3 columns on shop/archive pages
add_filter('loop_shop_columns', function ($cols) { return 3; }, 999);

// Positive scoping with wrapper
add_filter('body_class', function ($classes) {
  if (! function_exists('is_woocommerce') || (! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page())) {
    $classes[] = 'is-not-woo';
  }
  return $classes;
});

/* =========================
 * SAFE CHECKOUT TWEAKS (no form restructuring)
 * ========================= */

// Wrap the whole checkout area (outside the <form>)
add_action('woocommerce_before_checkout_form', function () {
  if (!is_checkout()) return;
  echo '<div class="px-6 mx-auto max-w-[1140px]">';
  echo '  <h1 class="text-[2.125rem] font-semibold text-[#0A1119]">Checkout</h1>';
  echo '  <div class="mt-6">'; // keep structure simple; do columns in CSS
}, 0);

add_action('woocommerce_after_checkout_form', function () {
  if (!is_checkout()) return;
  echo '  </div>'; // .mt-6
  echo '</div>';    // container
}, 0);

// Make sure Woo notices render (errors/validation/etc.)
add_action('woocommerce_before_checkout_form', 'woocommerce_output_all_notices', 5);

// Shipping heading
add_action('woocommerce_before_checkout_shipping_form', function () {
  echo '<h2 class="mb-4 text-xl font-medium text-[#0A1119]">Shipping details</h2>';
}, 5);

// Order review heading (wording)
add_filter('woocommerce_order_review_heading', fn() => __('Order summary', 'woocommerce'));

// Checkout body class for CSS scoping
add_filter('body_class', function ($classes) {
  if (function_exists('is_checkout') && is_checkout()) $classes[] = 'tw-checkout';
  return $classes;
});

// Order summary thumbnails
add_filter('woocommerce_cart_item_name', function ($name, $cart_item, $cart_key) {
  if (!is_checkout()) return $name;
  $thumb = apply_filters('woocommerce_cart_item_thumbnail', $cart_item['data']->get_image('thumbnail'), $cart_item, $cart_key);
  return '<div class="flex gap-3 items-start">'.
           '<div class="overflow-hidden w-14 h-14 rounded border border-gray-200 shrink-0">'.$thumb.'</div>'.
           '<div>'.$name.'</div>'.
         '</div>';
}, 10, 3);

/* --- Shipping toggle defaults --- */

// Default to "Deliver to a different address" unchecked
add_filter('pre_option_woocommerce_ship_to_destination', function ($val) {
  return is_checkout() ? 'billing' : $val;
}, 99);

add_filter('woocommerce_ship_to_different_address_checked', function ($checked) {
  if (isset($_POST['ship_to_different_address'])) {
    return (bool) $_POST['ship_to_different_address'];
  }
  return false;
}, 999);

// Optional: rename the checkbox label
add_filter('gettext', function ($translated, $text, $domain) {
  if ($domain === 'woocommerce' && $text === 'Ship to a different address?') {
    return __('Deliver to a different address', 'matrix-starter');
  }
  return $translated;
}, 10, 3);

// Hide order notes block if you don’t want it
add_filter('woocommerce_enable_order_notes_field', '__return_false', 99);

/* --- HTML5 attributes & required class (does NOT replace Woo validation) --- */

add_filter('woocommerce_form_field_args', function ($args) {
  if (!empty($args['required'])) {
    $args['class'][] = 'validate-required';
  }
  return $args;
}, 10);

add_filter('woocommerce_checkout_fields', function ($fields) {

  // Billing
  foreach (['billing_first_name','billing_last_name','billing_address_1','billing_city','billing_postcode','billing_email','billing_phone'] as $k) {
    if (isset($fields['billing'][$k])) {
      $fields['billing'][$k]['custom_attributes']['required'] = 'required';
    }
  }
  if (isset($fields['billing']['billing_email'])) {
    $fields['billing']['billing_email']['type'] = 'email';
  }
  if (isset($fields['billing']['billing_phone'])) {
    $fields['billing']['billing_phone']['type'] = 'tel';
    $fields['billing']['billing_phone']['custom_attributes']['pattern'] = '[0-9+\-\s()]+';
  }

  // Shipping (when shown)
  foreach (['shipping_first_name','shipping_last_name','shipping_address_1','shipping_city','shipping_postcode'] as $k) {
    if (isset($fields['shipping'][$k])) {
      $fields['shipping'][$k]['custom_attributes']['required'] = 'required';
    }
  }

  return $fields;
});

// Keep first+last on one line, push email below (runs late so it beats Stripe/gateways)
add_filter('woocommerce_checkout_fields', function ($fields) {
    if (!isset($fields['billing'])) return $fields;

    // ---- First name
    if (isset($fields['billing']['billing_first_name'])) {
        $fields['billing']['billing_first_name']['priority'] = 10;
        $classes = $fields['billing']['billing_first_name']['class'] ?? [];
        if (!in_array('form-row-first', $classes, true)) $classes[] = 'form-row-first';
        if (!in_array('validate-required', $classes, true)) $classes[] = 'validate-required';
        $fields['billing']['billing_first_name']['class'] = $classes;
    }

    // ---- Last name
    if (isset($fields['billing']['billing_last_name'])) {
        $fields['billing']['billing_last_name']['priority'] = 20;
        $classes = $fields['billing']['billing_last_name']['class'] ?? [];
        if (!in_array('form-row-last', $classes, true)) $classes[] = 'form-row-last';
        if (!in_array('validate-required', $classes, true)) $classes[] = 'validate-required';
        $fields['billing']['billing_last_name']['class'] = $classes;
    }

    // ---- Email (Stripe often sets priority=1 + custom class)
    if (isset($fields['billing']['billing_email'])) {
        $fields['billing']['billing_email']['priority'] = 30; // after names
        $classes = $fields['billing']['billing_email']['class'] ?? [];
        if (!in_array('form-row-wide', $classes, true)) $classes[] = 'form-row-wide';
        $fields['billing']['billing_email']['class'] = $classes;
    }

    // (optional) Phone under email
    if (isset($fields['billing']['billing_phone'])) {
        $fields['billing']['billing_phone']['priority'] = 40;
        $classes = $fields['billing']['billing_phone']['class'] ?? [];
        if (!in_array('form-row-wide', $classes, true)) $classes[] = 'form-row-wide';
        $fields['billing']['billing_phone']['class'] = $classes;
    }

    return $fields;
}, 999); // late = override gateway mutations


add_action('wp_enqueue_scripts', function () {
  if (!is_checkout()) return;

  wp_add_inline_style('matrix-starter', <<<CSS
/* Matrix checkout row layout */
.tw-checkout .woocommerce-billing-fields__field-wrapper,
.tw-checkout .woocommerce-shipping-fields__field-wrapper,
.tw-checkout .woocommerce-additional-fields__field-wrapper {
  display: flex; flex-wrap: wrap; gap: 1rem;
}
.tw-checkout .form-row-first,
.tw-checkout .form-row-last { flex: 1 1 12rem; min-width: 12rem; max-width: 50%; }
.tw-checkout .form-row-wide   { flex: 0 0 100%; }
CSS);
}, 50);


/* --- Optional UX: disable Place Order until visible required fields look filled --- */
add_action('wp_footer', function () {
  if (!is_checkout()) return; ?>
  <script>
  jQuery(function($){
    var $form = $('form.checkout');
    var $btn  = $('#place_order');

    function requiredVisibleFilled(){
      var ok = true;
      $form.find('.validate-required:visible').each(function(){
        var $input = $(this).find('input, select, textarea').first();
        if (!$input.length) return;
        var val = ($input.is('select') ? $input.val() : $.trim($input.val()));
        if (!val) ok = false;
      });
      return ok;
    }

    function toggle(){
      $btn.prop('disabled', !requiredVisibleFilled());
    }

    toggle();
    $form.on('keyup change', 'input,select,textarea', toggle);
    $(document.body).on('updated_checkout', toggle);
  });
  </script>
<?php }, 100);

// ORDER RECEIVED
/**
 * Move "Order summary" heading inside #order_review, above the table.
 * - Print our heading at priority 5 (before Woo's table at priority 10)
 * - Hide Woo's default outside heading
 */

// 1) Output heading inside #order_review (right above the table)
add_action('woocommerce_checkout_order_review', function () {
    echo '<h3 class="mb-4 text-lg font-semibold tw-order-heading text-[#0A1119]">Order summary</h3>';
}, 5);

// 2) Hide Woo’s default heading that sits outside #order_review
add_filter('woocommerce_order_review_heading', '__return_empty_string'); // removes the text

add_filter('body_class', function ($classes) {
  if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
    $classes[] = 'tw-thankyou';
  }
  return $classes;
});


// CHECKOUT SHIPPING 
// TEMP for styling; remove after
add_filter('woocommerce_cart_needs_shipping', '__return_true');
add_filter('woocommerce_cart_needs_shipping_address', '__return_true');
add_filter('woocommerce_ship_to_different_address_checked', '__return_false'); // default unchecked


// MY ACCOUNT 
add_filter('body_class', function (array $classes) {
  if (function_exists('is_account_page') && is_account_page()) {
    $classes[] = 'tw-myaccount';
  }
  return $classes;
});

add_filter('body_class', function (array $classes) {
  if (function_exists('is_account_page') && is_account_page()) {
    $classes[] = 'tw-myaccount';
    if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('edit-address')) {
      $classes[] = 'tw-account-edit';
    }
  }
  return $classes;
});

add_filter('body_class', function (array $classes) {
  if (function_exists('is_account_page') && is_account_page() && !is_user_logged_in()) {
    $classes[] = 'tw-auth';
  }
  return $classes;
});

add_action('after_setup_theme', function () {

  // Helper: get colours from ACF with preset handling
  $enforce = (bool) get_field('woo_email_enforce', 'option');
  if (! $enforce) return;

  $preset   = get_field('woo_email_preset', 'option') ?: 'brand_red';
  $base     = get_field('woo_email_base_color', 'option') ?: '#ED1C24';
  $bg       = get_field('woo_email_background_color', 'option') ?: '#ffffff';
  $body_bg  = get_field('woo_email_body_background_color', 'option') ?: '#ffffff';
  $text     = get_field('woo_email_text_color', 'option') ?: '#101828';

  // Presets override manual unless "custom"
  if ($preset === 'brand_red') {
    $base    = '#ED1C24';
    $bg      = '#ffffff';
    $body_bg = '#ffffff';
    $text    = '#101828';
  } elseif ($preset === 'black') {
    $base    = '#ED1C24';   // keep brand buttons red; change to #000 if you want black buttons
    $bg      = '#000000';
    $body_bg = '#000000';
    $text    = '#ffffff';
  } // else 'custom' uses pickers

  // 1) Force Woo option values (these feed Woo’s templater + inliner)
  add_filter('pre_option_woocommerce_email_base_color',          fn() => $base);
  add_filter('pre_option_woocommerce_email_background_color',    fn() => $bg);
  add_filter('pre_option_woocommerce_email_body_background_color', fn() => $body_bg);
  add_filter('pre_option_woocommerce_email_text_color',          fn() => $text);

  // 2) Extra CSS for hover states, links and headings
  add_filter('woocommerce_email_styles', function ($css) use ($base, $text) {
    // lighten/darken brand a touch for hover (simple fallback)
    $brandDark = '#D00008';
    $textOnBrand = '#ffffff';

    $custom = "
      /* --- Matrix Woo Email Branding --- */
      a, a:link, a:visited {
        color: {$base} !important;
        text-decoration: none;
      }
      a:hover, a:focus {
        color: {$brandDark} !important;
        text-decoration: underline;
      }
      h1, h2, h3, h4 {
        color: {$text} !important;
        margin-top: 0;
      }

      /* Buttons */
      .button, a.button, a.button:link, a.button:visited,
      .woocommerce-button, a.woocommerce-button {
        background: {$base} !important;
        border-color: {$base} !important;
        color: {$textOnBrand} !important;
        text-decoration: none !important;
        border-radius: 4px !important;
      }
      .button:hover, a.button:hover,
      .woocommerce-button:hover, a.woocommerce-button:hover {
        background: {$brandDark} !important;
        border-color: {$brandDark} !important;
        color: {$textOnBrand} !important;
      }

      /* Footer / credits */
      #template_footer #credit {
        opacity: .8 !important;
      }
    ";
    return $css . "\n/* Matrix overrides */\n" . $custom;
  });

});
