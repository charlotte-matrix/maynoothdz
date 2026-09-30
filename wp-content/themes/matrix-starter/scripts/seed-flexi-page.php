<?php
/**
 * Create/update the Flexi review page with sample ACF flexible blocks.
 * Run via: wp eval-file scripts/seed-flexi-page.php
 */

if (!function_exists('update_field')) {
    WP_CLI::error('ACF is not available.');
}

$theme_uri  = get_template_directory_uri();
$design_img = '/Users/charlottevial/Local Sites/maynoothdz/design/website/assets/canal.webp';
$design_nature = '/Users/charlottevial/Local Sites/maynoothdz/design/website/assets/canal-nature.webp';

/**
 * Sideload a local image into the media library if needed.
 */
function matrix_seed_attachment_from_path(string $path, string $title): int
{
    if (!file_exists($path)) {
        return 0;
    }

    $filename = basename($path);
    $existing = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => 1,
        'meta_key'       => '_matrix_seed_source',
        'meta_value'     => $filename,
        'fields'         => 'ids',
    ]);

    if (!empty($existing[0])) {
        return (int) $existing[0];
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $upload = wp_upload_bits($filename, null, file_get_contents($path));
    if (!empty($upload['error'])) {
        WP_CLI::warning("Could not upload {$filename}: {$upload['error']}");
        return 0;
    }

    $filetype = wp_check_filetype($filename, null);
    $attach_id = wp_insert_attachment([
        'post_mime_type' => $filetype['type'] ?? 'image/webp',
        'post_title'     => $title,
        'post_content'   => '',
        'post_status'    => 'inherit',
    ], $upload['file']);

    if (is_wp_error($attach_id) || !$attach_id) {
        return 0;
    }

    $meta = wp_generate_attachment_metadata($attach_id, $upload['file']);
    wp_update_attachment_metadata($attach_id, $meta);
    update_post_meta($attach_id, '_matrix_seed_source', $filename);
    update_post_meta($attach_id, '_wp_attachment_image_alt', $title);

    return (int) $attach_id;
}

$hero_image_id = matrix_seed_attachment_from_path(
    $design_img,
    'The Royal Canal and its green waterside paths in Maynooth'
);
$nature_image_id = matrix_seed_attachment_from_path(
    $design_nature,
    'Trees and waterside paths along the Royal Canal'
);

$existing = get_page_by_path('flexi');
if ($existing) {
    $post_id = (int) $existing->ID;
    wp_update_post([
        'ID'          => $post_id,
        'post_title'  => 'Flexi',
        'post_status' => 'publish',
        'post_name'   => 'flexi',
    ]);
    WP_CLI::log("Updating existing page #{$post_id}");
} else {
    $post_id = wp_insert_post([
        'post_title'   => 'Flexi',
        'post_name'    => 'flexi',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => '',
    ], true);

    if (is_wp_error($post_id)) {
        WP_CLI::error($post_id->get_error_message());
    }
    WP_CLI::log("Created page #{$post_id}");
}

// Keep the separate Hero Blocks empty so Compact hero is the opener.
update_field('hero_content_blocks', [], $post_id);

$take_action = home_url('/take-action/');
$projects    = home_url('/projects/');
$about       = home_url('/#about');
$community   = home_url('/community/');
$map         = home_url('/map/');

$nature_html = '';
if ($nature_image_id) {
    $nature_html = wp_get_attachment_image(
        $nature_image_id,
        'large',
        false,
        [
            'class'   => 'article-photo',
            'loading' => 'lazy',
        ]
    );
    $nature_html = '<figure class="flex-figure">' . $nature_html
        . '<figcaption>Space for nature, and for everyday journeys along the Royal Canal.</figcaption></figure>';
}

$blocks = [
    [
        'acf_fc_layout'    => 'compact_hero',
        'eyebrow'          => 'Internal component library',
        'title'            => "Small actions.\nA shared future.",
        'introduction'     => "Flexible building blocks for Maynooth’s stories, projects and practical next steps.",
        'primary_cta'      => [
            'title'  => 'Take action',
            'url'    => $take_action,
            'target' => '',
        ],
        'secondary_cta'    => [
            'title'  => 'Explore projects →',
            'url'    => $projects,
            'target' => '',
        ],
        'image'            => $hero_image_id ?: '',
        'show_breadcrumbs' => 1,
    ],
    [
        'acf_fc_layout' => 'rich_text',
        'content'       => '<h2>A greener Maynooth, together</h2>'
            . '<p>The Zone brings local people, places and projects together. Discover what is happening nearby and find <a href="' . esc_url($projects) . '">a project that interests you</a>.</p>'
            . '<h3>Start with your everyday</h3>'
            . '<p>There are many ways to take part, whether you have a few minutes or a bigger idea.</p>'
            . '<ul><li>Explore walking and cycling routes around town.</li><li>Connect with a local community group.</li></ul>'
            . '<ol><li>Choose an area you would like to learn about.</li><li>Explore the projects and practical next steps.</li></ol>'
            . $nature_html,
    ],
    [
        'acf_fc_layout' => 'stats_strip',
        'stats'         => [
            [
                'value'    => '73635',
                'animate'  => 1,
                'decimals' => 0,
                'suffix'   => '',
                'label'    => "tonnes CO₂e - the town's emissions baseline",
            ],
            [
                'value'    => '75.2',
                'animate'  => 1,
                'decimals' => 1,
                'suffix'   => '%',
                'label'    => 'of emissions come from homes (38.5%) and transport (36.7%)',
            ],
            [
                'value'    => '2023',
                'animate'  => 0,
                'decimals' => 0,
                'suffix'   => '',
                'label'    => "designated Kildare's Pathfinder Decarbonising Zone <a href=\"#source\">*</a>",
            ],
        ],
        'source_note'   => '* Source: Maynooth SEC Energy Master Plan',
    ],
    [
        'acf_fc_layout' => 'quote',
        'quote_text'    => 'We just wanted to get people talking — 300 showed up.',
        'attribution'   => 'Organiser · Maynooth Tidy Towns',
    ],
    [
        'acf_fc_layout' => 'accordion',
        'items'         => [
            [
                'summary'         => 'What is a Decarbonising Zone?',
                'body'            => '<p>A place where local climate action comes together — from homes and transport to nature and community projects.</p><p><a href="' . esc_url($about) . '">Learn about the Zone →</a></p>',
                'open_by_default' => 1,
            ],
            [
                'summary'         => 'How can I get involved?',
                'body'            => '<p>Explore the practical steps on our <a href="' . esc_url($take_action) . '">Take Action page</a> or connect with the <a href="' . esc_url($community) . '">local community</a>.</p>',
                'open_by_default' => 0,
            ],
            [
                'summary'         => 'Where can I find local projects?',
                'body'            => '<p>Browse the <a href="' . esc_url($projects) . '">project directory</a> or explore their locations on the <a href="' . esc_url($map) . '">interactive map</a>.</p>',
                'open_by_default' => 0,
            ],
        ],
    ],
    [
        'acf_fc_layout' => 'cta_banner',
        'heading'       => 'Ready to do something with all this?',
        'button'        => [
            'title'  => 'Take action',
            'url'    => $take_action,
            'target' => '',
        ],
    ],
];

$ok = update_field('flexible_content_blocks', $blocks, $post_id);
if (!$ok) {
    // Fallback: store raw meta if field key resolution fails on first run.
    update_post_meta($post_id, 'flexible_content_blocks', $blocks);
    WP_CLI::warning('update_field returned false; wrote post meta directly. Re-save the page in admin if layouts look empty.');
}

$permalink = get_permalink($post_id);
WP_CLI::success("Flexi page ready: {$permalink}");
WP_CLI::log('Hero image ID: ' . ($hero_image_id ?: 'none'));
WP_CLI::log('Blocks: compact_hero, rich_text, stats_strip, quote, accordion, cta_banner');
