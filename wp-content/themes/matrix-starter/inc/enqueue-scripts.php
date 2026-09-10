<?php
/**
 * inc/enqueue-scripts.php
 */

function matrix_is_wc_flow_page(): bool {
  if (! function_exists('is_woocommerce')) return false;
  return is_cart()
      || is_checkout()
      || is_account_page()
      || is_wc_endpoint_url('order-received')
      || is_wc_endpoint_url('order-pay')
      || is_wc_endpoint_url('add-payment-method')
      || is_wc_endpoint_url('view-order');
}

/**
 * ACF option values may be arrays (label/value), nested arrays, or objects.
 * Normalize to a plain UTF-8 string for CAPTCHA keys and inline JSON.
 */
function matrix_acf_scalar_string( $raw ): string {
  if ( is_string( $raw ) ) {
    return trim( $raw );
  }
  if ( is_int( $raw ) || is_float( $raw ) ) {
    return trim( (string) $raw );
  }
  if ( is_bool( $raw ) ) {
    return $raw ? '1' : '';
  }
  if ( is_array( $raw ) ) {
    if ( array_key_exists( 'value', $raw ) ) {
      return matrix_acf_scalar_string( $raw['value'] );
    }
    if ( array_key_exists( 'key', $raw ) ) {
      return matrix_acf_scalar_string( $raw['key'] );
    }
    if ( array_key_exists( 'sitekey', $raw ) ) {
      return matrix_acf_scalar_string( $raw['sitekey'] );
    }
    $first = reset( $raw );
    if ( false !== $first ) {
      return matrix_acf_scalar_string( $first );
    }
    return '';
  }
  if ( is_object( $raw ) && method_exists( $raw, '__toString' ) ) {
    try {
      return trim( (string) $raw );
    } catch ( \Throwable $e ) {
      return '';
    }
  }
  return '';
}

/**
 * Enqueue theme assets + optional libs
 */
function matrix_starter_enqueue_scripts() {
  $theme_version = get_option('theme_css_version', '1.0');

  // Ensure jQuery is present early
  wp_enqueue_script('jquery');

  // Dev/prod asset base
  $is_dev = defined('WP_ENV') && WP_ENV === 'development';
  $base   = get_template_directory_uri();

  $app_js  = $is_dev ? '/wp-content/themes/matrix-starter/dist/app.js'  : $base . '/dist/app.js';
  $app_css = $is_dev ? '/wp-content/themes/matrix-starter/dist/app.css' : $base . '/dist/app.css';

  // Main bundle (footer)
  wp_enqueue_script('matrix-starter', $app_js, ['jquery'], '1.0.0', true);
  wp_enqueue_style('matrix-starter', $app_css, [], $theme_version);

  // Alpine + Intersect
  wp_enqueue_script('alpine-intersect','https://cdn.jsdelivr.net/npm/@alpinejs/intersect@3.x.x/dist/cdn.min.js',[],null,true);
  wp_enqueue_script('alpine','https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js',['alpine-intersect'],null,true);
  wp_add_inline_script('alpine', "document.addEventListener('alpine:init',()=>{ if(window.Alpine&&window.AlpineIntersect) Alpine.plugin(window.AlpineIntersect); });");
  wp_add_inline_style('matrix-starter', '[x-cloak]{display:none !important;}');
  wp_add_inline_style('matrix-starter', '
    .sr-person-card__imgwrap{height:320px;}
    @media (max-width:575px){.sr-person-card__imgwrap{height:250px;}}
    @media (max-width:320px){.sr-people-grid{grid-template-columns:1fr !important;}}
    .sr-person-card__img{object-fit:cover;}
  ');

  // PACE stat counters (IntersectionObserver — no Alpine dependency)
  $pace_counters_js = get_template_directory() . '/assets/js/pace-counters.js';
  if (file_exists($pace_counters_js)) {
    wp_enqueue_script(
      'pace-counters',
      $base . '/assets/js/pace-counters.js',
      [],
      (string) filemtime($pace_counters_js),
      true
    );
  }

  // Theme forms helper
  $forms_js_path = get_template_directory() . '/inc/forms/js/forms.js';
  wp_enqueue_script(
    'theme-forms',
    $base . '/inc/forms/js/forms.js',
    ['jquery'],
    file_exists($forms_js_path) ? filemtime($forms_js_path) : null,
    true
  );

  // ---- CAPTCHA provider switch (Google reCAPTCHA v3 / Cloudflare Turnstile) ----
  $provider     = (function_exists('get_field') ? (get_field('captcha_provider', 'option') ?: 'none') : 'none');
  $provider     = matrix_acf_scalar_string( $provider ) ?: 'none';
  $recaptchaKey = matrix_acf_scalar_string( function_exists('get_field') ? get_field('recaptcha_site_key', 'option') : '' );
  $turnstileKey = matrix_acf_scalar_string( function_exists('get_field') ? get_field('turnstile_site_key', 'option') : '' );

  // Normalize provider value to lowercase for consistency
  $provider = strtolower( $provider );

// Pass provider + keys to the forms helper (BEFORE it runs). wp_json_encode ensures valid JS literals.
wp_add_inline_script(
  'theme-forms',
  'window.themeFormsCaptchaProvider = ' . wp_json_encode( $provider, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';
   window.themeFormsRecaptchaV3      = ' . wp_json_encode( $recaptchaKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';
   window.themeFormsTurnstileSiteKey = ' . wp_json_encode( $turnstileKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';',
  'before'
);
  // Load provider-specific API
  if ($provider === 'recaptcha_v3' && $recaptchaKey) {
    wp_enqueue_script('recaptcha', "https://www.google.com/recaptcha/api.js?render={$recaptchaKey}", [], null, true);
  }

  if ($provider === 'turnstile' && $turnstileKey) {
    wp_enqueue_script('turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
  }

  // Register optional third-parties
  wp_register_style('font-awesome','https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',[],null);

  wp_register_script('flowbite','https://unpkg.com/flowbite@1.6.5/dist/flowbite.min.js',['alpine'],'1.6.5',true);
  wp_register_style('slick-css','https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css',[],'1.8.1');
  wp_register_script('slick-js','https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js',['jquery'],'1.8.1',true);
  wp_register_script('headroom','https://cdnjs.cloudflare.com/ajax/libs/headroom/0.12.0/headroom.min.js',[],'0.12.0',true);
  wp_register_style('nice-select-css','https://cdn.jsdelivr.net/npm/jquery-nice-select@1.1.0/css/nice-select.css',[],'1.1.0');
  wp_register_script('nice-select-js','https://cdn.jsdelivr.net/npm/jquery-nice-select@1.1.0/js/jquery.nice-select.min.js',['jquery'],'1.1.0',true);

  // Leaflet
  wp_register_style('leaflet','https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',[],'1.9.4');
  wp_register_script('leaflet','https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',[],'1.9.4',true);

  // Conditionally enqueue based on Theme Options
  $enabled_scripts = function_exists('get_field') ? get_field('enabled_scripts', 'option') : [];
  if (is_array($enabled_scripts)) {
    if (in_array('font_awesome', $enabled_scripts, true)) wp_enqueue_style('font-awesome');
    if (in_array('hamburger_css', $enabled_scripts, true)) wp_enqueue_style('hamburgers-css');
    if (in_array('flowbite',      $enabled_scripts, true)) wp_enqueue_script('flowbite');
    if (in_array('slick',         $enabled_scripts, true)) { wp_enqueue_style('slick-css'); wp_enqueue_script('slick-js'); }
    if (in_array('headroom',      $enabled_scripts, true) && ! matrix_is_wc_flow_page()) {
      wp_enqueue_script('headroom');
      wp_add_inline_script('headroom', "
        document.addEventListener('DOMContentLoaded', function() {
          var header = document.querySelector('#site-nav');
          if (!header || typeof Headroom === 'undefined') return;
          header.classList.add('fixed','top-0','left-0','w-full','z-50','transition-transform','duration-300','ease-in-out','translate-y-0');
          var headroom = new Headroom(header, { tolerance: 5, offset: 100 });
          headroom.onPin   = function(){ header.classList.remove('-translate-y-full'); header.classList.add('translate-y-0'); };
          headroom.onUnpin = function(){ header.classList.remove('translate-y-0'); header.classList.add('-translate-y-full'); };
          headroom.init();
        });
      ");
    }
    if (in_array('leaflet', $enabled_scripts, true)) { wp_enqueue_style('leaflet'); wp_enqueue_script('leaflet'); }

    // Optional: force-load Turnstile globally (even if provider not selected)
    if (in_array('cloudflare_turnstile', $enabled_scripts, true) && !wp_script_is('turnstile','enqueued')) {
      wp_enqueue_script('turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
    }
  }

  // Contact/get-involved form custom select UI.
  // Load only on likely form pages to avoid unnecessary JS/CSS on every page.
  $should_load_nice_select = false;
  if (is_singular()) {
    $post_id = get_queried_object_id();
    if ($post_id) {
      $content = (string) get_post_field('post_content', $post_id);
      $content_lower = strtolower($content);
      $should_load_nice_select =
        has_block('acf/contact-form-001', $post_id)
        || has_block('acf/get-involved-form-001', $post_id)
        || has_block('acf/contact_form_001', $post_id)
        || has_block('acf/get_involved_form_001', $post_id)
        || str_contains($content_lower, 'contact_form_001')
        || str_contains($content_lower, 'get_involved_form_001')
        || str_contains($content_lower, 'data-contact-structured');
    }
  }
  if (!$should_load_nice_select) {
    $request_uri = strtolower((string) ($_SERVER['REQUEST_URI'] ?? ''));
    $should_load_nice_select =
      str_contains($request_uri, '/get-involved')
      || str_contains($request_uri, '/contact');
  }
  if ($should_load_nice_select) {
    wp_enqueue_style('nice-select-css');
    wp_enqueue_script('nice-select-js');
  }


  // Woo fragments
  if (class_exists('WooCommerce')) {
    wp_enqueue_script('wc-cart-fragments');
  }

  /**
   * Defer only non-critical scripts (NOT on checkout; NEVER WP packages)
   */
  add_filter('script_loader_tag', function ($tag, $handle) {
    if (is_admin()) return $tag;

    // Zero risk on checkout: don't touch script tags
    if (function_exists('is_checkout') && is_checkout()) {
      return $tag;
    }

    // Never defer these handles
    $no_defer = [
      // WP packages used by Woo + inline “*-js-after” snippets
      'wp-i18n','wp-hooks','wp-element','wp-components','wp-compose','wp-data',
      'wp-keycodes','wp-html-entities','wp-is-shallow-equal','wp-private-apis',
      'wp-priority-queue','wp-url','wp-api-fetch',

      // Foundations some packages rely on
      'react','react-dom','lodash','moment',

      // Theme + Woo essentials
      'jquery','jquery-core','jquery-migrate',
      'matrix-starter','theme-forms','matrix-newsletter',
      'wc-cart-fragments','woocommerce',
      'recaptcha','turnstile',
      'alpine-intersect','alpine',
      'wc-checkout','wc-country-select','wc-address-i18n',
      'selectWoo','jquery-blockui','jquery-payment',
      'wc-add-to-cart-variation','wc-credit-card-form',
      'wc-password-strength-meter',
    ];

    if (in_array($handle, $no_defer, true)) {
      return $tag;
    }

    return (strpos($tag, ' src=') !== false) ? str_replace(' src', ' defer src', $tag) : $tag;
  }, 10, 2);
}
add_action('wp_enqueue_scripts', 'matrix_starter_enqueue_scripts', 20);

/**
 * Keep jQuery in header group so it prints early if needed
 */
add_action('wp_enqueue_scripts', function () {
  if (!is_admin()) {
    wp_scripts()->add_data('jquery', 'group', 0);
    wp_scripts()->add_data('jquery-core', 'group', 0);
    wp_scripts()->add_data('jquery-migrate', 'group', 0);
  }
}, 5);

/**
 * 1) Always load core WP packages in the header (group 0)
 */
add_action('wp_enqueue_scripts', function () {
  $wp_pkgs = [
    'wp-i18n','wp-hooks','wp-element','wp-components','wp-compose','wp-data',
    'wp-keycodes','wp-html-entities','wp-is-shallow-equal','wp-private-apis',
    'wp-priority-queue','wp-url','wp-api-fetch',
    // common deps
    'react','react-dom','lodash','moment',
  ];
  $scripts = wp_scripts();
  if (!$scripts) return;
  foreach ($wp_pkgs as $h) {
    $scripts->add_data($h, 'group', 0);
  }
}, 1);

/**
 * 2) Never async/defer WP packages or /wp-includes/js/dist/* (global, very late)
 */
add_filter('script_loader_tag', function ($tag, $handle) {
  if (is_admin()) return $tag;

  $critical = [
    'wp-i18n','wp-hooks','wp-element','wp-components','wp-compose','wp-data',
    'wp-keycodes','wp-html-entities','wp-is-shallow-equal','wp-private-apis',
    'wp-priority-queue','wp-url','wp-api-fetch',
    'react','react-dom','lodash','moment',
  ];

  $is_core_pkg = in_array($handle, $critical, true)
              || (strpos($tag, '/wp-includes/js/dist/') !== false);

  if ($is_core_pkg) {
    // strip both async and defer regardless of who added them
    $tag = preg_replace('/\sdefer(=("|\').*?\2)?/i', '', $tag);
    $tag = preg_replace('/\sasync(=("|\').*?\2)?/i', '', $tag);
  }

  return $tag;
}, 9999, 2);

/**
 * 3) On checkout, remove ANY async/defer that slipped through
 */
add_filter('script_loader_tag', function ($tag) {
  if (function_exists('is_checkout') && is_checkout()) {
    $tag = preg_replace('/\sdefer(=("|\').*?\2)?/i', '', $tag);
    $tag = preg_replace('/\sasync(=("|\').*?\2)?/i', '', $tag);
  }
  return $tag;
}, 10000);

/**
 * 4) (Optional) Ask optimizers to back off on checkout
 */
add_action('template_redirect', function () {
  if (! function_exists('is_checkout') || ! is_checkout()) return;

  // Autoptimize
  if (function_exists('autoptimize_do_cache')) {
    add_filter('autoptimize_filter_js_defer', '__return_false', 99);
    add_filter('autoptimize_filter_js_async', '__return_false', 99);
    add_filter('autoptimize_filter_js_deferthis', '__return_empty_array', 99);
  }

  // LiteSpeed Cache
  if (defined('LSCWP_V')) {
    add_filter('litespeed_optimize_js_defer', '__return_false', 99);
    add_filter('litespeed_optimize_js_delayed', '__return_false', 99);
  }

  // WP Rocket etc.
  if (function_exists('rocket_is_plugin_active')) {
    add_filter('rocket_delay_js', '__return_false', 99);
    add_filter('rocket_minify_js', '__return_false', 99);
    add_filter('rocket_defer_js', '__return_false', 99);
  }
});
