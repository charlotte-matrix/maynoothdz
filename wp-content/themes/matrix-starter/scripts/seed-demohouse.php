<?php
/**
 * Seed DemoHouse Retrofit project + project categories.
 * Run: wp eval-file wp-content/themes/matrix-starter/scripts/seed-demohouse.php
 */

if (!function_exists('update_field')) {
    WP_CLI::error('ACF is required.');
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 * Sideload a theme/design asset into the media library (idempotent).
 */
function matrix_seed_dz_file(string $relative, string $title = ''): int
{
    $theme_dz = get_template_directory() . '/assets/dz';
    $design   = '/Users/charlottevial/Local Sites/maynoothdz/design/website/assets';

    $candidates = [
        $theme_dz . '/' . $relative,
        $design . '/' . $relative,
    ];
    $path = '';
    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            $path = $candidate;
            break;
        }
    }
    if ($path === '') {
        WP_CLI::warning("Missing asset: {$relative}");
        return 0;
    }

    $filename = basename($path);
    $existing = get_posts([
        'post_type'      => 'attachment',
        'posts_per_page' => 1,
        'post_status'    => 'inherit',
        'fields'         => 'ids',
        'meta_key'       => '_matrix_seed_source',
        'meta_value'     => $filename,
    ]);
    if (!empty($existing[0])) {
        return (int) $existing[0];
    }

    $upload = wp_upload_bits($filename, null, file_get_contents($path));
    if (!empty($upload['error'])) {
        WP_CLI::warning($upload['error']);
        return 0;
    }

    $type = wp_check_filetype($filename);
    $id   = wp_insert_attachment([
        'post_mime_type' => $type['type'] ?: 'image/webp',
        'post_title'     => $title !== '' ? $title : pathinfo($filename, PATHINFO_FILENAME),
        'post_status'    => 'inherit',
    ], $upload['file']);

    if (!$id || is_wp_error($id)) {
        return 0;
    }

    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
    update_post_meta($id, '_matrix_seed_source', $filename);
    update_post_meta($id, '_wp_attachment_image_alt', $title !== '' ? $title : pathinfo($filename, PATHINFO_FILENAME));

    return (int) $id;
}

$categories = [
    'energy'                       => ['name' => 'Energy', 'color' => '#a47b13'],
    'retrofit'                     => ['name' => 'Retrofit', 'color' => '#d9684f'],
    'transport'                    => ['name' => 'Transport', 'color' => '#02a59f'],
    'biodiversity-resilience'      => ['name' => 'Biodiversity & Resilience', 'color' => '#1b853f'],
    'community-action'             => ['name' => 'Community Action', 'color' => '#8562a5'],
    'public-realm'                 => ['name' => 'Public realm', 'color' => '#258d78'],
    'education-awareness'          => ['name' => 'Education & Awareness', 'color' => '#f3b63e'],
    'sustainable-practices'        => ['name' => 'Sustainable Practices', 'color' => '#006168'],
    'circular-economy'             => ['name' => 'Circular economy', 'color' => '#ad9d50'],
    'water-nature-based-solutions' => ['name' => 'Water & Nature-based Solutions', 'color' => '#43c6c6'],
];

foreach ($categories as $slug => $meta) {
    $term = term_exists($slug, 'project_category');
    if (!$term) {
        $term = wp_insert_term($meta['name'], 'project_category', ['slug' => $slug]);
    }
    if (is_wp_error($term)) {
        WP_CLI::warning($term->get_error_message());
        continue;
    }
    $term_id = (int) (is_array($term) ? $term['term_id'] : $term);
    update_field('category_color', $meta['color'], 'project_category_' . $term_id);
}

$retrofit_img = matrix_seed_dz_file('retrofit.webp', 'The Maynooth demonstration house');
$solar_icon   = matrix_seed_dz_file('map-icons/solar-house.svg', 'Solar house');
$ber_icon     = matrix_seed_dz_file('map-icons/ber-house.svg', 'BER house');
$saving_icon  = matrix_seed_dz_file('map-icons/saving-energy.svg', 'Saving energy');
$seai_logo    = matrix_seed_dz_file('partner-seai.svg', 'SEAI — Sustainable Energy Authority of Ireland');
$sec_logo     = matrix_seed_dz_file('partner-maynooth-sec.jpg', 'Maynooth Sustainable Energy Community');

$existing = get_posts([
    'post_type'      => 'project',
    'name'           => 'demohouse-retrofit',
    'posts_per_page' => 1,
    'post_status'    => 'any',
    'fields'         => 'ids',
]);

if (!empty($existing[0])) {
    $post_id = (int) $existing[0];
    wp_update_post([
        'ID'           => $post_id,
        'post_title'   => 'DemoHouse Retrofit',
        'post_status'  => 'publish',
        'post_name'    => 'demohouse-retrofit',
        'post_excerpt' => 'A completed Maynooth home retrofit to an A-rating — sharing what the process involved.',
    ]);
    WP_CLI::log("Updating project #{$post_id}");
} else {
    $post_id = wp_insert_post([
        'post_title'   => 'DemoHouse Retrofit',
        'post_name'    => 'demohouse-retrofit',
        'post_status'  => 'publish',
        'post_type'    => 'project',
        'post_excerpt' => 'A completed Maynooth home retrofit to an A-rating — sharing what the process involved.',
    ], true);
    if (is_wp_error($post_id)) {
        WP_CLI::error($post_id->get_error_message());
    }
    WP_CLI::log("Created project #{$post_id}");
}

wp_set_object_terms($post_id, ['retrofit'], 'project_category', false);

if ($retrofit_img) {
    set_post_thumbnail($post_id, $retrofit_img);
}

$why = '<p>Homes make up 38.5% of Maynooth’s emissions. DemoHouse shows what a deep retrofit looks like from the inside: the choices, the costs, the disruption and what it feels like to live in afterwards.</p>'
    . '<p>The house is a normal semi-detached home, chosen because much of the town’s housing stock looks like it. Sharing the experience helps other households understand what their own next step could be.</p>';

$fields = [
    'project_eyebrow'         => 'Part of the Maynooth Decarbonising Zone',
    'project_status'          => 'Complete',
    'project_location_label'  => 'Maynooth, County Kildare',
    'project_lead'            => 'A completed Maynooth home retrofit to an A-rating — sharing what the process involved.',
    'project_demo_note'       => 'Demonstration case study based on the supplied draft. Figures, map locations and supporting documents await confirmation.',
    'project_why_heading'     => 'Why this project',
    'project_why_body'        => $why,
    'project_media_image'     => $retrofit_img ?: '',
    'project_media_caption'   => 'DemoHouse, Maynooth.',
    'project_story_extra'     => '<p>The demonstration house is now closed and is returning to social housing use. Its location is not displayed to protect residents’ privacy.</p>',
    'project_glance_heading'  => 'At a glance',
    'project_glance_items'    => [
        ['label' => 'BER before → after', 'value' => 'B3 → A2', 'is_pending' => 0],
        ['label' => 'Energy-use reduction', 'value' => 'To be confirmed', 'is_pending' => 1],
        ['label' => 'Grants used', 'value' => 'To be confirmed', 'is_pending' => 1],
    ],
    'project_grant_icons'     => array_values(array_filter([$solar_icon, $ber_icon, $saving_icon])),
    'project_partners_heading'=> 'Partners',
    'project_partner_logos'   => array_values(array_filter([$seai_logo, $sec_logo])),
    'project_partners_fallback'=> 'Project partners to be confirmed.',
    'project_enable_summary'  => 1,
    'project_summary_title'   => '',
    'project_summary_meta'    => 'Demonstration content · printable, accessible HTML',
    'project_show_related'    => 1,
    'project_related_heading' => 'Related projects',
    'project_cta_heading'     => 'Thinking about your own home?',
    'project_cta_text'        => 'Explore the retrofit pathway, step by step.',
    'project_cta_button'      => [
        'title'  => 'Explore home retrofit',
        'url'    => home_url('/take-action/'),
        'target' => '',
    ],
];

foreach ($fields as $key => $value) {
    $ok = update_field($key, $value, $post_id);
    if (!$ok) {
        update_post_meta($post_id, $key, $value);
        WP_CLI::warning("update_field failed for {$key}; wrote post meta directly.");
    }
}

flush_rewrite_rules(false);

$permalink = get_permalink($post_id);
WP_CLI::success("DemoHouse ready: {$permalink}");
WP_CLI::log('Summary download: ' . add_query_arg('download', 'summary', $permalink));
