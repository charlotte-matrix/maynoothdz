<?php
// Theme setup: fail hard on frontend if ACF missing (but allow admin to activate it)
if (! function_exists('get_field') && ! is_admin()) {
    wp_die(
        'The ACF plugin is not active. This theme depends on it. Please activate it.',
        'Plugin Missing',
        array('response' => 500)
    );
}

/**
 * Core theme supports & menus
 */
function matrix_starter_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));
    add_theme_support('align-wide');
    add_theme_support('editor-styles');
    add_theme_support('responsive-embeds');

    add_image_size('hero-small',   768,  500, true);
    add_image_size('hero-medium',  1024, 600, true);
    add_image_size('hero-large',   1280, 800, true);
    add_image_size('hero-xlarge',  1600, 900, true);
    add_image_size('hero-xxlarge', 1920,1080, true);

    register_nav_menus(array(
        'primary'       => esc_html__('Primary Menu', 'matrix-starter'),
        // Footer columns (match the new footer layout)
        'footer_one'    => esc_html__('Footer — Project', 'matrix-starter'),
        'footer_two'    => esc_html__("Footer — What's New", 'matrix-starter'),
        'footer_three'  => esc_html__('Footer — Resources Hub', 'matrix-starter'),
        'footer_four'   => esc_html__('Footer — Extra (optional)', 'matrix-starter'),

        // Bottom legal menu
        'copyright'     => esc_html__('Footer Legal Menu', 'matrix-starter'),
        ));
}
add_action('after_setup_theme', 'matrix_starter_setup');

/**
 * Menu link attributes (theme_location is the slug)
 */
add_filter('nav_menu_link_attributes', function ($atts, $item, $args) {
    if (!empty($args->theme_location) && $args->theme_location === 'footer_one') {
        $atts['class'] = trim(($atts['class'] ?? '') . ' block hover:underline focus:outline-none focus:ring-2 focus:ring-[#0A1119]');
    }
    return $atts;
}, 10, 3);

/**
 * Composer (if present)
 */
if (file_exists(get_template_directory() . '/vendor/autoload.php')) {
    require_once get_template_directory() . '/vendor/autoload.php';
} else {
    error_log('Composer autoload file not found.');
}

/**
 * Includes that can load immediately (no ACF calls at include time)
 */
require_once get_template_directory() . '/inc/enqueue-fonts.php';
require_once get_template_directory() . '/inc/enqueue-scripts.php';
require_once get_template_directory() . '/inc/archive-hero-functions.php';
require_once get_template_directory() . '/inc/hero-functions.php';
require_once get_template_directory() . '/inc/flexible-content-functions.php';
require_once get_template_directory() . '/inc/autoload-helpers-setup.php';

/**
 * CPTs & taxonomies
 * These files typically add their own add_action('init', ...) which must be
 * registered BEFORE WP hits 'init', so require them now.
 */
require_once get_template_directory() . '/inc/cpts/init.php';

/**
 * ACF-dependent files — run them on acf/init (fires at/after init),
 * so ACF translations (textdomain 'acf') won’t be loaded too early.
 */
add_action('acf/init', function () {
    require_once get_template_directory() . '/inc/acf-flexi-validation.php';
    require_once get_template_directory() . '/inc/autoload-acf-fields.php';
    require_once get_template_directory() . '/inc/autoload-acf-groups.php';
    require_once get_template_directory() . '/inc/theme-options.php';
}, 5);

/**
 * Other theme includes
 */
require_once get_template_directory() . '/inc/login-customizations.php';
require_once get_template_directory() . '/inc/pagination.php';
require_once get_template_directory() . '/inc/woocommerce.php';

/**
 * Tailwind cache-busting on ACF options save
 */
function handle_tailwind_config_update() {
    update_option('theme_css_version', time());

    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }

    $css_path = get_template_directory() . '/assets/css/app.css';
    if (file_exists($css_path)) {
        touch($css_path);
        $temp_path = get_template_directory() . '/assets/css/.temp';
        file_put_contents($temp_path, '');
        usleep(100000);
        if (file_exists($temp_path)) {
            unlink($temp_path);
        }
        error_log('Tailwind config updated and rebuild triggered');
    } else {
        error_log('CSS file not found at: ' . $css_path);
    }
}
add_action('acf/save_post', function ($post_id) {
    if ($post_id === 'options') {
        handle_tailwind_config_update();
    }
}, 30);

/**
 * Excerpt more
 */
function custom_excerpt_more($more) {
    return '';
}
add_filter('excerpt_more', 'custom_excerpt_more');

/**
 * Extra image sizes & labels
 */
function my_custom_image_sizes() {
    add_image_size('hero-image',     1600, 900, true);
    add_image_size('hero-thumbnail',  800, 450, true);
}
add_action('after_setup_theme', 'my_custom_image_sizes');

function my_custom_image_size_names($sizes) {
    return array_merge($sizes, array(
        'hero-image'     => __('Hero Image'),
        'hero-thumbnail' => __('Hero Thumbnail'),
    ));
}
add_filter('image_size_names_choose', 'my_custom_image_size_names');

/**
 * SVG support
 */
function add_svg_support($file_types) {
    $file_types['svg'] = 'image/svg+xml';
    return $file_types;
}
add_filter('upload_mimes', 'add_svg_support');

function svg_mime_check($data, $file, $filename) {
    if (isset($data['ext']) && $data['ext'] === 'svg') {
        if ($data['type'] !== 'image/svg+xml') {
            $data['ext'] = $data['type'] = false;
        }
    }
    return $data;
}
add_filter('wp_check_filetype_and_ext', 'svg_mime_check', 10, 3);

/**
 * Global frontend search: include all searchable public post types.
 */
function matrix_global_site_search_query($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
        return;
    }

    $post_types = get_post_types([
        'public' => true,
        'exclude_from_search' => false,
    ], 'names');

    unset($post_types['attachment'], $post_types['nav_menu_item']);
    if (!empty($post_types)) {
        $query->set('post_type', array_values($post_types));
    }
}
add_action('pre_get_posts', 'matrix_global_site_search_query', 20);


if (!function_exists('matrix_normalize_search_title')) {
    function matrix_normalize_search_title(string $raw_title): string {
        // Some titles are double-encoded (e.g. &amp;#8217;), so decode twice.
        $decoded = wp_specialchars_decode($raw_title, ENT_QUOTES);
        $decoded = wp_specialchars_decode($decoded, ENT_QUOTES);
        return wp_strip_all_tags($decoded);
    }
}

/**
 * Header live search endpoint.
 */
function matrix_live_site_search() {
    $term = isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '';
    if (mb_strlen($term) < 2) {
        wp_send_json_success(['items' => []]);
    }

    $post_types = get_post_types([
        'public' => true,
        'exclude_from_search' => false,
    ], 'names');
    unset($post_types['attachment'], $post_types['nav_menu_item']);

    $query = new WP_Query([
        'post_type'              => array_values($post_types),
        'post_status'            => 'publish',
        'posts_per_page'         => 8,
        's'                      => $term,
        'no_found_rows'          => true,
        'ignore_sticky_posts'    => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);

    $items = [];
    $seen_urls = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $raw_title = (string) get_the_title();
            $normalized_title = matrix_normalize_search_title($raw_title);
            $url = (string) get_permalink();
            if ($url === '' || isset($seen_urls[$url])) {
                continue;
            }
            $seen_urls[$url] = true;
            $items[] = [
                'title' => $normalized_title,
                'url'   => $url,
                'type'  => get_post_type_object(get_post_type())->labels->singular_name ?? get_post_type(),
            ];
        }
        wp_reset_postdata();
    }

    // Include PDF attachments in live search results (useful for policies/docs).
    $pdf_query = new WP_Query([
        'post_type'              => 'attachment',
        'post_status'            => 'inherit',
        'post_mime_type'         => 'application/pdf',
        'posts_per_page'         => 8,
        's'                      => $term,
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);

    $allowed_pdf_url = matrix_allowed_search_pdf_url();
    if ($pdf_query->have_posts()) {
        while ($pdf_query->have_posts()) {
            $pdf_query->the_post();
            $pdf_url = (string) wp_get_attachment_url(get_the_ID());
            if ($pdf_url === '' || $pdf_url !== $allowed_pdf_url || isset($seen_urls[$pdf_url])) {
                continue;
            }
            $seen_urls[$pdf_url] = true;
            $raw_title = (string) get_the_title();
            $normalized_title = matrix_normalize_search_title($raw_title);
            $items[] = [
                'title' => $normalized_title,
                'url'   => $pdf_url,
                'type'  => 'PDF',
            ];
        }
        wp_reset_postdata();
    }

    if (count($items) > 8) {
        $items = array_slice($items, 0, 8);
    }

    wp_send_json_success(['items' => $items]);
}
add_action('wp_ajax_matrix_live_site_search', 'matrix_live_site_search');
add_action('wp_ajax_nopriv_matrix_live_site_search', 'matrix_live_site_search');

/**
 * CF7 style tag tweak
 */
function add_type_attribute($tag, $handle, $src) {
    if ('contact-form-7' === $handle) {
        $tag = str_replace("rel='stylesheet'", "rel='stylesheet' type='text/css'", $tag);
    }
    return $tag;
}
add_filter('style_loader_tag', 'add_type_attribute', 10, 3);

/**
 * 404 template part loader
 */
function template_part_404() {
    $template_dir = get_template_directory() . '/template-parts/404';
    if (!is_dir($template_dir)) {
        return;
    }
    $files = scandir($template_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            get_template_part('template-parts/404/' . pathinfo($file, PATHINFO_FILENAME));
            return;
        }
    }
}

/**
 * Blog template part loader
 */
function template_part_blog() {
    $template_dir = get_template_directory() . '/template-parts/blog/';
    if (!is_dir($template_dir)) {
        return;
    }
    $files = scandir($template_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            get_template_part('template-parts/blog/' . pathinfo($file, PATHINFO_FILENAME));
            return;
        }
    }
}

/**
 * Forms
 */
require get_template_directory() . '/inc/forms/class-theme-forms.php';

/**
 * Template builder (your original timing kept)
 */
add_action('init', function () {
    require_once get_template_directory() . '/inc/template-builder/default-builder.php';
}, 20);

/**
 * TEAM POST FIELDS
 */
// Load ACF Team fields (safe include)
add_action('after_setup_theme', function () {
    $path = trailingslashit( get_stylesheet_directory() ) . 'acf-fields/register-team-fields.php';
    if ( file_exists( $path ) ) {
        require_once $path;
    } else {
        // Optional: log instead of fatal error
        error_log('[ACF] Missing file: ' . $path);
    }
});

/**
 * Debug marker to confirm Theme_Forms is loaded
 */
add_action('wp_footer', function () {
    if (class_exists('Theme_Forms')) {
        echo '<!-- Theme_Forms loaded -->';
    }
});

/**
 * Enqueue jQuery UI draggable early
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('jquery-ui-draggable');
}, 1);

/**
 * Build ACF Select choices from menus
 */
function mytheme_acf_menu_item_choices(): array {
    $cache_key = 'mytheme_nav_menu_item_choices';
    $choices = get_transient($cache_key);
    if (is_array($choices)) {
        return $choices;
    }

    $choices = [];
    $menus = wp_get_nav_menus();

    if (!empty($menus)) {
        foreach ($menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id, ['update_post_term_cache' => false]);
            if (empty($items)) {
                continue;
            }

            $by_id = [];
            foreach ($items as $it) {
                $by_id[$it->ID] = $it;
            }

            foreach ($items as $it) {
                $crumbs = [$it->title];
                $p = $it->menu_item_parent;
                while (!empty($p) && isset($by_id[$p])) {
                    array_unshift($crumbs, $by_id[$p]->title);
                    $p = $by_id[$p]->menu_item_parent;
                }
                $label = $menu->name . ' › ' . implode(' › ', $crumbs);
                $choices[$it->ID] = $label;
            }
        }
    }

    set_transient($cache_key, $choices, HOUR_IN_SECONDS * 12);
    return $choices;
}

add_action('wp_update_nav_menu', function () {
    delete_transient('mytheme_nav_menu_item_choices');
});

add_filter('acf/load_field/name=menu_item', function ($field) {
    $field['choices'] = mytheme_acf_menu_item_choices();
    return $field;
});

// Suppress Gravity Forms license demands
add_action( 'admin_head', function() {
    echo '<style>[data-js="setup-wizard"] { display: none !important; }</style>';
});

/* ALLOW VCARDS */
// Allow .vcf uploads site-wide
add_filter('upload_mimes', function ($mimes) {
    // Common vCard MIME types
    $mimes['vcf']   = 'text/x-vcard';
    $mimes['vcard'] = 'text/x-vcard';
    // Some servers report vCards as text/vcard or application/vcard
    $mimes['vct']   = 'text/vcard';
    return $mimes;
});

// Help WP validate .vcf correctly (prevents false negatives)
add_filter('wp_check_filetype_and_ext', function ($wp_check, $file, $filename, $mimes, $real_mime = '' ) {
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if (in_array(strtolower($ext), ['vcf','vcard'], true)) {
        $wp_check['ext']  = 'vcf';
        // Prefer text/x-vcard; some setups use text/vcard or application/vcard
        $wp_check['type'] = 'text/x-vcard';
        $wp_check['proper_filename'] = $filename;
    }
    return $wp_check;
}, 10, 5);


add_filter('safe_style_css', function ($styles) {
    $extra_styles = [
        'display',
        'gap',
        'row-gap',
        'column-gap',
        'white-space',
        'align-items',
        'justify-content',
        'justify-self',
        'align-self',
        'flex',
        'flex-grow',
        'flex-shrink',
        'flex-basis',
        'flex-wrap',
        'flex-direction',
    ];

    return array_unique(array_merge($styles, $extra_styles));
});

/**
 * Determine if a category term is part of media categories.
 */
if (!function_exists('matrix_is_media_category_term')) {
    function matrix_is_media_category_term($term): bool {
        if (!($term instanceof WP_Term) || $term->taxonomy !== 'category') {
            return false;
        }

        // Backward-compatible support for direct press-releases usage.
        if ((string) $term->slug === 'press-releases') {
            return true;
        }

        if ((string) $term->slug === 'in-the-media') {
            return true;
        }

        $media_parent = get_category_by_slug('in-the-media');
        if (!$media_parent instanceof WP_Term) {
            return false;
        }

        $ancestors = get_ancestors((int) $term->term_id, 'category', 'taxonomy');
        $ancestors = array_map('intval', is_array($ancestors) ? $ancestors : []);
        return in_array((int) $media_parent->term_id, $ancestors, true);
    }
}

/**
 * Determine if post has any media category.
 */
if (!function_exists('matrix_post_has_media_category')) {
    function matrix_post_has_media_category(int $post_id): bool {
        if ($post_id <= 0) {
            return false;
        }

        $terms = get_the_terms($post_id, 'category');
        if (empty($terms) || is_wp_error($terms)) {
            return false;
        }

        foreach ($terms as $term) {
            if (matrix_is_media_category_term($term)) {
                return true;
            }
        }

        return false;
    }
}

/**
 * SEO rule for link-out media posts:
 * Default to noindex when post is in media categories and points externally,
 * unless explicitly overridden by post_force_index.
 */
if (!function_exists('matrix_should_noindex_post')) {
    function matrix_should_noindex_post(int $post_id): bool {
        if ($post_id <= 0 || get_post_type($post_id) !== 'post') {
            return false;
        }

        if (!function_exists('get_field')) {
            return false;
        }

        $force_index = get_field('post_force_index', $post_id);
        $force_index = ($force_index === 1 || $force_index === '1' || $force_index === true);
        if ($force_index) {
            return false;
        }

        $external_source_link = get_field('post_external_source_link', $post_id);
        $has_external_source_url = is_array($external_source_link) && !empty($external_source_link['url']);

        $open_external_source = get_field('post_listing_open_external_source', $post_id);
        $open_external_source = ($open_external_source === 1 || $open_external_source === '1' || $open_external_source === true);

        $is_media_post = matrix_post_has_media_category($post_id);

        return $has_external_source_url && ($is_media_post || $open_external_source);
    }
}

add_filter('wp_robots', function (array $robots): array {
    if (!is_singular('post')) {
        return $robots;
    }

    $post_id = (int) get_queried_object_id();
    if (!$post_id || !matrix_should_noindex_post($post_id)) {
        return $robots;
    }

    $robots['noindex'] = true;
    $robots['nofollow'] = false;
    return $robots;
}, 20);

add_filter('wpseo_robots', function ($robots) {
    if (!is_singular('post')) {
        return $robots;
    }

    $post_id = (int) get_queried_object_id();
    if (!$post_id || !matrix_should_noindex_post($post_id)) {
        return $robots;
    }

    return 'noindex,follow';
}, 20);

add_filter('rank_math/frontend/robots', function ($robots) {
    if (!is_singular('post')) {
        return $robots;
    }

    $post_id = (int) get_queried_object_id();
    if (!$post_id || !matrix_should_noindex_post($post_id)) {
        return $robots;
    }

    return 'noindex,follow';
}, 20);

/**
 * Frontend accessibility baseline fixes applied site-wide.
 */
add_action('wp_footer', function () {
    if (is_admin()) {
        return;
    }
    ?>
    <script>
    (function () {
        function uniqueId(prefix) {
            return prefix + '-' + Math.random().toString(36).slice(2, 10);
        }

        function dedupeElementIds() {
            var seen = new Set();
            var idMap = new Map();
            var nodes = document.querySelectorAll('[id]');

            nodes.forEach(function (node) {
                var id = node.id;
                if (!id) return;
                if (!seen.has(id)) {
                    seen.add(id);
                    return;
                }
                var newId = uniqueId(id);
                node.id = newId;
                idMap.set(id, idMap.get(id) || []);
                idMap.get(id).push(newId);
            });

            if (idMap.size === 0) return;

            var labels = document.querySelectorAll('label[for]');
            labels.forEach(function (label) {
                var target = label.getAttribute('for');
                if (!target || !idMap.has(target)) return;
                var replacementIds = idMap.get(target);
                if (replacementIds && replacementIds.length) {
                    label.setAttribute('for', replacementIds.shift());
                }
            });
        }

        function wireFormLabels() {
            var forms = document.querySelectorAll('form');
            forms.forEach(function (form) {
                var labels = form.querySelectorAll('label:not([for])');
                labels.forEach(function (label) {
                    var control = label.querySelector('input, select, textarea');
                    if (!control) {
                        var sibling = label.nextElementSibling;
                        if (sibling) {
                            control = sibling.matches('input, select, textarea')
                                ? sibling
                                : sibling.querySelector('input, select, textarea');
                        }
                    }

                    if (!control) return;
                    if (!control.id) {
                        var namePart = control.getAttribute('name') || 'field';
                        control.id = uniqueId('a11y-' + namePart.replace(/[^a-z0-9_-]/gi, '-'));
                    }
                    label.setAttribute('for', control.id);
                });
            });
        }

        function fixNewTabLinks() {
            var links = document.querySelectorAll('a[target="_blank"]');
            links.forEach(function (link) {
                var rel = (link.getAttribute('rel') || '').trim();
                var relParts = rel ? rel.split(/\s+/) : [];
                if (!relParts.includes('noopener')) relParts.push('noopener');
                if (!relParts.includes('noreferrer')) relParts.push('noreferrer');
                link.setAttribute('rel', relParts.join(' ').trim());

                var label = (link.getAttribute('aria-label') || '').trim();
                var text = (link.textContent || '').trim();
                var suffix = '(opens in a new tab)';
                if (!label) {
                    label = text ? text + ' ' + suffix : 'Link ' + suffix;
                    link.setAttribute('aria-label', label);
                } else if (label.toLowerCase().indexOf('new tab') === -1) {
                    link.setAttribute('aria-label', label + ' ' + suffix);
                }
            });
        }

        function setIframeTitles() {
            var iframes = document.querySelectorAll('iframe');
            var count = 0;
            iframes.forEach(function (iframe) {
                var title = (iframe.getAttribute('title') || '').trim();
                if (!title) {
                    count += 1;
                    iframe.setAttribute('title', 'Embedded content ' + count);
                }
            });
        }

        function applyAutocompleteHints() {
            var map = {
                'first_name': 'given-name',
                'firstname': 'given-name',
                'last_name': 'family-name',
                'lastname': 'family-name',
                'full_name': 'name',
                'name': 'name',
                'email': 'email',
                'phone': 'tel',
                'mobile': 'tel',
                'address_line_1': 'address-line1',
                'address_line_2': 'address-line2',
                'city': 'address-level2',
                'county': 'address-level1',
                'state': 'address-level1',
                'postcode': 'postal-code',
                'postal_code': 'postal-code',
                'country': 'country-name'
            };
            var controls = document.querySelectorAll('input[name], textarea[name]');
            controls.forEach(function (control) {
                var current = (control.getAttribute('autocomplete') || '').trim();
                if (current) return;
                var rawName = (control.getAttribute('name') || '').toLowerCase();
                var normalized = rawName.replace(/\[\]$/, '');
                if (map[normalized]) {
                    control.setAttribute('autocomplete', map[normalized]);
                }
            });
        }

        function runA11yEnhancements() {
            dedupeElementIds();
            wireFormLabels();
            fixNewTabLinks();
            setIframeTitles();
            applyAutocompleteHints();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', runA11yEnhancements);
        } else {
            runA11yEnhancements();
        }
    })();
    </script>
    <?php
}, 100);