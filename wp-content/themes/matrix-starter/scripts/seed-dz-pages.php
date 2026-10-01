<?php
/**
 * Seed Flexi catalogue page + Home front page with design content.
 * wp eval-file wp-content/themes/matrix-starter/scripts/seed-dz-pages.php
 */

if (!function_exists('update_field')) {
    WP_CLI::error('ACF is required.');
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$design = '/Users/charlottevial/Local Sites/maynoothdz/design/website/assets';
$theme_dz = get_template_directory() . '/assets/dz';

function matrix_seed_img(string $filename, string $title = ''): int
{
    $theme_dz = get_template_directory() . '/assets/dz';
    $design   = '/Users/charlottevial/Local Sites/maynoothdz/design/website/assets';
    $path = file_exists($theme_dz . '/' . $filename) ? $theme_dz . '/' . $filename : $design . '/' . $filename;
    if (!file_exists($path)) {
        WP_CLI::warning("Missing image: {$filename}");
        return 0;
    }

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
    $id = wp_insert_attachment([
        'post_mime_type' => $type['type'] ?? 'image/webp',
        'post_title'     => $title !== '' ? $title : pathinfo($filename, PATHINFO_FILENAME),
        'post_status'    => 'inherit',
    ], $upload['file']);

    if (!$id || is_wp_error($id)) {
        return 0;
    }

    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
    update_post_meta($id, '_matrix_seed_source', $filename);
    return (int) $id;
}

$img = [
    'canal'        => matrix_seed_img('canal.webp', 'Royal Canal Maynooth'),
    'canal_nature' => matrix_seed_img('canal-nature.webp', 'Canal nature'),
    'retrofit'     => matrix_seed_img('retrofit.webp', 'DemoHouse retrofit'),
    'community'    => matrix_seed_img('community.webp', 'Community'),
    'map'          => matrix_seed_img('map.webp', 'Maynooth map'),
    'pollinators'  => matrix_seed_img('pollinators.webp', 'Pollinators'),
    'maynooth'     => matrix_seed_img('maynooth.webp', 'Maynooth town'),
    'town'         => matrix_seed_img('town-centre.webp', 'Town centre'),
    'kildare'      => matrix_seed_img('kildare.webp', 'Kildare County Council'),
    'climate'      => matrix_seed_img('climate.webp', 'Climate action'),
    'government'   => matrix_seed_img('government.png', 'Government of Ireland'),
    'home_action'  => matrix_seed_img('home-action-icon.svg', 'Home action icon'),
    'travel_action'=> matrix_seed_img('travel-action-icon.svg', 'Travel action icon'),
    'leaf_action'  => matrix_seed_img('leaf-action-icon.svg', 'Leaf action icon'),
];

$home_url = home_url('/');
$projects = home_url('/projects/');
$take = home_url('/take-action/');
$map = home_url('/map/');
$community = home_url('/community/');
$resources = home_url('/resources/');

$link = static function (string $title, string $url): array {
    return ['title' => $title, 'url' => $url, 'target' => ''];
};

// ——— Flexi page (all catalogue blocks) ———
$flexi_blocks = [
    [
        'acf_fc_layout' => 'compact_hero',
        'eyebrow' => 'Internal component library',
        'title' => "Small actions.\nA shared future.",
        'introduction' => "Flexible building blocks for Maynooth’s stories, projects and practical next steps.",
        'primary_cta' => $link('Take action', $take),
        'secondary_cta' => $link('Explore projects →', $projects),
        'image' => $img['canal'] ?: '',
        'show_breadcrumbs' => 1,
    ],
    [
        'acf_fc_layout' => 'rich_text',
        'content' => '<h2>A greener Maynooth, together</h2><p>The Zone brings local people, places and projects together. Discover what is happening nearby and find <a href="' . esc_url($projects) . '">a project that interests you</a>.</p><h3>Start with your everyday</h3><p>There are many ways to take part, whether you have a few minutes or a bigger idea.</p><ul><li>Explore walking and cycling routes around town.</li><li>Connect with a local community group.</li></ul><ol><li>Choose an area you would like to learn about.</li><li>Explore the projects and practical next steps.</li></ol><figure class="flex-figure"><img class="article-photo" src="' . esc_url(wp_get_attachment_image_url((int) $img['canal_nature'], 'large') ?: '') . '" alt="Trees and waterside paths along the Royal Canal" loading="lazy" width="1600" height="1067"><figcaption>Space for nature, and for everyday journeys along the Royal Canal.</figcaption></figure>',
    ],
    [
        'acf_fc_layout' => 'stats_strip',
        'stats' => [
            ['value' => '73635', 'animate' => 1, 'decimals' => 0, 'suffix' => '', 'label' => "tonnes CO₂e - the town's emissions baseline"],
            ['value' => '75.2', 'animate' => 1, 'decimals' => 1, 'suffix' => '%', 'label' => 'of emissions come from homes (38.5%) and transport (36.7%)'],
            ['value' => '2023', 'animate' => 0, 'decimals' => 0, 'suffix' => '', 'label' => "designated Kildare's Pathfinder Decarbonising Zone *"],
        ],
        'source_note' => '* Source: Maynooth SEC Energy Master Plan',
    ],
    [
        'acf_fc_layout' => 'pathway_tabs',
        'steps' => [
            [
                'tab_label' => 'Understand your home',
                'title' => 'Step 1 — Understand your home',
                'body' => 'Start with how your home feels and performs. Gather your energy bills, note cold rooms or draughts, and review any existing BER report.',
                'resources' => [
                    ['label' => 'Explore home energy upgrades', 'url' => 'https://www.seai.ie/homeenergyupgrades', 'icon' => '↗'],
                    ['label' => 'Find an energy professional', 'url' => 'https://www.seai.ie/grants/find-a-registered-professional', 'icon' => '↗'],
                ],
            ],
            [
                'tab_label' => 'Plan & prioritise',
                'title' => 'Step 2 — Plan & prioritise',
                'body' => 'Bring your priorities together: comfort, running costs and the work your home needs.',
                'resources' => [
                    ['label' => 'How a One Stop Shop can help', 'url' => 'https://www.seai.ie/grants/home-energy-grants/one-stop-shop', 'icon' => '↗'],
                ],
            ],
            [
                'tab_label' => 'Grants & finance',
                'title' => 'Step 3 — Grants & finance',
                'body' => 'Explore the support available for your planned upgrades and compare SEAI upgrade routes.',
                'resources' => [
                    ['label' => 'SEAI grant options explained', 'url' => 'https://www.seai.ie/homeenergyupgrades', 'icon' => '↗'],
                ],
            ],
        ],
        'aside' => [
            'image' => $img['retrofit'] ?: '',
            'title' => 'See it done: DemoHouse',
            'cta_label' => 'Read the case study →',
            'link' => $link('DemoHouse', $projects),
        ],
    ],
    [
        'acf_fc_layout' => 'project_cards',
        'cards' => [
            [
                'image' => $img['retrofit'] ?: '',
                'tag' => 'retrofit',
                'status' => 'Complete',
                'title' => 'DemoHouse Retrofit',
                'link' => $link('DemoHouse Retrofit', $projects),
                'excerpt' => 'A Maynooth home upgraded to A-rating - open for the town to learn from.',
            ],
            [
                'image' => $img['canal'] ?: '',
                'tag' => 'transport',
                'status' => 'Underway',
                'title' => 'Royal Canal Greenway Links',
                'link' => $link('Royal Canal', $projects),
                'excerpt' => 'Safer walking and cycling connections from the Greenway into town.',
            ],
        ],
        'empty_state' => [
            'eyebrow' => 'Room to grow',
            'title' => 'More projects coming',
            'text' => 'New projects will appear here as they start.',
        ],
    ],
    [
        'acf_fc_layout' => 'media',
        'items' => [
            [
                'type' => 'image',
                'image' => $img['community'] ?: '',
                'caption' => 'People and local ideas at the heart of the Zone.',
            ],
            [
                'type' => 'video',
                'video_title' => 'A local story, in motion',
                'caption' => 'Video block demonstration — a video URL has not yet been supplied.',
            ],
        ],
    ],
    [
        'acf_fc_layout' => 'quote',
        'quote_text' => 'We just wanted to get people talking — 300 showed up.',
        'attribution' => 'Organiser · Maynooth Tidy Towns',
    ],
    [
        'acf_fc_layout' => 'map_teaser',
        'heading' => 'Wander the map',
        'subtext' => 'see what Maynooth is already doing.',
        'map_image' => $img['map'] ?: '',
        'button' => $link('Explore the map', $map),
    ],
    [
        'acf_fc_layout' => 'accordion',
        'items' => [
            [
                'summary' => 'What is a Decarbonising Zone?',
                'body' => '<p>A place where local climate action comes together — from homes and transport to nature and community projects.</p><p><a href="' . esc_url($home_url . '#about') . '">Learn about the Zone →</a></p>',
                'open_by_default' => 1,
            ],
            [
                'summary' => 'How can I get involved?',
                'body' => '<p>Explore the practical steps on our <a href="' . esc_url($take) . '">Take Action page</a> or connect with the <a href="' . esc_url($community) . '">local community</a>.</p>',
                'open_by_default' => 0,
            ],
            [
                'summary' => 'Where can I find local projects?',
                'body' => '<p>Browse the <a href="' . esc_url($projects) . '">project directory</a> or explore their locations on the <a href="' . esc_url($map) . '">interactive map</a>.</p>',
                'open_by_default' => 0,
            ],
        ],
    ],
    [
        'acf_fc_layout' => 'downloads',
        'heading' => 'Plans & strategies',
        'items' => [
            [
                'type' => 'PDF',
                'name' => 'Kildare Climate Action Plan 2024–29',
                'meta' => '10.1 MB',
                'url' => 'https://kildarecoco.ie/AllServices/ClimateAction/KildareCountyCouncilClimateActionPlan2024-2029/LocalAuthorityClimateActionPlan20242029.pdf',
            ],
            [
                'type' => 'PLAN',
                'name' => 'Maynooth SEC Energy Master Plan',
                'meta' => 'Request a copy',
                'url' => 'mailto:climateaction@kildarecoco.ie?subject=Maynooth%20SEC%20Energy%20Master%20Plan',
            ],
            [
                'type' => 'LINK',
                'name' => 'National Climate Action Plan',
                'meta' => 'gov.ie',
                'url' => 'https://www.gov.ie/en/department-of-climate-energy-and-the-environment/publications/climate-action-plan/',
            ],
        ],
        'accessibility_note' => 'Need an accessible format? <a href="mailto:climateaction@kildarecoco.ie?subject=Accessible%20resource%20request">Contact the Climate Action Office</a>.',
    ],
    [
        'acf_fc_layout' => 'link_list',
        'heading' => 'Reading & research',
        'items' => [
            [
                'title' => 'Decarbonising Zones — national programme overview',
                'description' => 'What Decarbonising Zones are and how they support local climate action.',
                'url' => 'https://www.gov.ie/en/department-of-climate-energy-and-the-environment/publications/decarbonising-zones/',
            ],
            [
                'title' => 'Maynooth University’s sustainability research',
                'description' => 'Explore research on sustainability and climate change.',
                'url' => 'https://www.maynoothuniversity.ie/research/sustainability-and-climate-change',
            ],
        ],
    ],
    [
        'acf_fc_layout' => 'updates_feed',
        'updates' => [
            [
                'image' => $img['community'] ?: '',
                'author' => 'Maynooth Tidy Towns',
                'avatar_initials' => 'MT',
                'title' => 'Picnic in the Park brought 300 neighbours together',
                'link' => $link('Picnic', $community),
                'excerpt' => 'Food, music and a first conversation about what the Decarbonising Zone could mean for the town.',
                'date' => '2026-08-14',
            ],
        ],
        'empty_state' => [
            'title' => 'Community updates are coming',
            'text' => 'Hear from your group, in its own voice — new stories will appear as groups publish.',
        ],
    ],
    [
        'acf_fc_layout' => 'event_item',
        'title' => 'Picnic in the Park',
        'date' => '2026-08-14',
        'location' => 'Harbour Field, Maynooth',
        'link' => $link('Read the story →', $community),
    ],
    [
        'acf_fc_layout' => 'steps_list',
        'steps' => [
            ['title' => 'Register interest', 'description' => 'Tell the Climate Action Office about your group.'],
            ['title' => 'Council approves', 'description' => 'A quick check, then your group gets its page.'],
            ['title' => 'Draft, review, publish', 'description' => 'Share updates and events in your own voice.'],
        ],
    ],
    [
        'acf_fc_layout' => 'glossary',
        'term' => 'Retrofit',
        'anchor_id' => 'retrofit',
        'definition' => 'Upgrading an existing building — for example its insulation, windows or heating — to improve how it performs and feels.',
        'link' => $link('Explore the retrofit pathway →', $take),
    ],
    [
        'acf_fc_layout' => 'partner_strip',
        'partners' => array_values(array_filter([
            $img['kildare'] ? ['logo' => $img['kildare']] : null,
            $img['climate'] ? ['logo' => $img['climate']] : null,
            $img['government'] ? ['logo' => $img['government']] : null,
        ])),
    ],
    [
        'acf_fc_layout' => 'contact',
        'heading' => 'Climate Action Office',
        'address' => "Kildare County Council\nÁras Chill Dara, Naas, Co. Kildare",
        'email' => 'climateaction@kildarecoco.ie',
    ],
    [
        'acf_fc_layout' => 'newsletter',
        'heading' => 'Stay in the loop',
        'intro' => 'Zone news and community events, straight to your inbox.',
    ],
    [
        'acf_fc_layout' => 'cta_banner',
        'heading' => 'Ready to do something with all this?',
        'button' => $link('Take action', $take),
    ],
];

function matrix_seed_page(string $slug, string $title): int
{
    $existing = get_page_by_path($slug);
    if ($existing) {
        wp_update_post(['ID' => $existing->ID, 'post_title' => $title, 'post_status' => 'publish']);
        return (int) $existing->ID;
    }
    $id = wp_insert_post([
        'post_title'  => $title,
        'post_name'   => $slug,
        'post_status' => 'publish',
        'post_type'   => 'page',
        'post_content'=> '',
    ], true);
    if (is_wp_error($id)) {
        WP_CLI::error($id->get_error_message());
    }
    return (int) $id;
}

$flexi_id = matrix_seed_page('flexi', 'Flexi');
update_field('hero_content_blocks', [], $flexi_id);
$ok = update_field('flexible_content_blocks', $flexi_blocks, $flexi_id);
WP_CLI::log('Flexi page #' . $flexi_id . ' (' . count($flexi_blocks) . ' blocks) update_field=' . ($ok ? 'ok' : 'false'));

// ——— Home page ———
$home_blocks = [
    [
        'acf_fc_layout' => 'home_hero',
        'title' => 'Discover local climate action in Maynooth — and how you can be part of it.',
        'introduction' => 'Real projects, real places, real people — explore what the town is already doing, then find your own next step.',
        'primary_cta' => $link('Explore map', $map),
        'secondary_cta' => $link('What is the DZ?', $home_url . '#about'),
        'map_image' => $img['map'] ?: '',
        'stats' => [
            ['value' => '73635', 'animate' => 1, 'decimals' => 0, 'suffix' => '', 'label' => "tonnes CO₂e - the town's emissions baseline"],
            ['value' => '75.2', 'animate' => 1, 'decimals' => 1, 'suffix' => '%', 'label' => 'of emissions come from homes (38.5%) and transport (36.7%)'],
            ['value' => '2023', 'animate' => 0, 'decimals' => 0, 'suffix' => '', 'label' => "designated Kildare's Pathfinder Decarbonising Zone *"],
        ],
        'source_note' => '* Source: Maynooth SEC Energy Master Plan',
    ],
    [
        'acf_fc_layout' => 'project_carousel',
        'heading' => 'Featured projects',
        'browse_link' => $link('Browse all projects →', $projects),
        'cards' => [
            ['image' => $img['retrofit'] ?: '', 'tag' => 'retrofit', 'status' => 'Complete', 'title' => 'DemoHouse Retrofit', 'link' => $link('DemoHouse', $projects), 'excerpt' => 'A Maynooth home upgraded to A-rating - open for the town to learn from.'],
            ['image' => $img['canal'] ?: '', 'tag' => 'transport', 'status' => 'Underway', 'title' => 'Royal Canal Greenway Links', 'link' => $link('Canal', $projects), 'excerpt' => 'Safer walking and cycling connections from the Greenway into town.'],
            ['image' => $img['pollinators'] ?: '', 'tag' => 'biodiversity', 'status' => 'Planned', 'title' => 'Harbour Field for Pollinators', 'link' => $link('Harbour Field', $projects), 'excerpt' => 'Turning the Harbour Field into a haven for bees and wildflowers.'],
            ['image' => $img['maynooth'] ?: '', 'tag' => 'transport', 'status' => 'Underway', 'title' => 'Maynooth', 'link' => $link('Maynooth', $projects), 'excerpt' => 'Safer walking and cycling connections from the Greenway into town.'],
            ['image' => $img['town'] ?: '', 'tag' => 'public-realm', 'status' => 'Example', 'title' => 'Greener town centre', 'link' => $link('Town centre', $projects), 'excerpt' => 'An example of how greener streets could support everyday life in Maynooth.'],
            ['image' => $img['canal_nature'] ?: '', 'tag' => 'nature', 'status' => 'Example', 'title' => 'Royal Canal nature trail', 'link' => $link('Nature trail', $projects), 'excerpt' => 'An example of connecting waterside walks with nature and local learning.'],
        ],
    ],
    [
        'acf_fc_layout' => 'take_action_cards',
        'heading' => 'Take action',
        'section_id' => 'action',
        'cards' => [
            ['color' => 'sand', 'icon' => $img['home_action'] ?: '', 'title' => 'Retrofit your home', 'subtitle' => 'A 6-step pathway', 'link' => $link('Retrofit', $take)],
            ['color' => 'lime', 'icon' => $img['travel_action'] ?: '', 'title' => 'Travel sustainably', 'subtitle' => 'A 4-step pathway', 'link' => $link('Travel', 'mailto:climateaction@kildarecoco.ie?subject=Travel%20sustainably')],
            ['color' => 'olive', 'icon' => $img['leaf_action'] ?: '', 'title' => 'Grow a community garden', 'subtitle' => 'An example pathway', 'link' => $link('Garden', 'mailto:climateaction@kildarecoco.ie?subject=Maynooth%20climate%20action')],
            ['color' => 'ochre', 'icon' => $img['home_action'] ?: '', 'title' => 'Save energy every day', 'subtitle' => 'Small changes at home', 'link' => $link('Energy', 'mailto:climateaction@kildarecoco.ie?subject=Maynooth%20climate%20action')],
            ['color' => 'sage', 'icon' => $img['leaf_action'] ?: '', 'title' => 'Repair, share & reuse', 'subtitle' => 'Make more of what you have', 'link' => $link('Reuse', 'mailto:climateaction@kildarecoco.ie?subject=Maynooth%20climate%20action')],
            ['color' => 'wheat', 'icon' => $img['leaf_action'] ?: '', 'title' => 'See all actions', 'subtitle' => '', 'link' => $link('All actions', $take)],
        ],
    ],
    [
        'acf_fc_layout' => 'about_band',
        'section_id' => 'about',
        'heading' => 'What is the Decarbonising Zone?',
        'body' => "Maynooth is one of Ireland's Decarbonising Zones — a whole town working out, in the open, how to cut its emissions. The Zone brings the Council, the university, local groups and residents together around visible projects you can walk past, join in with, or copy at home.",
        'button' => $link('About the Zone', '#contact'),
        'image' => $img['maynooth'] ?: '',
        'show_watermark' => 1,
    ],
    [
        'acf_fc_layout' => 'community_split',
        'section_id' => 'community',
        'community' => [
            'heading' => 'From the community',
            'image' => $img['community'] ?: '',
            'text' => 'Discover local stories, find community events and see how your group can take part.',
            'button' => $link('Explore community', $community),
        ],
        'map' => [
            'heading' => 'Wander the map',
            'subtext' => 'see what Maynooth is already doing.',
            'map_image' => $img['map'] ?: '',
            'button' => $link('Explore the map', $map),
        ],
    ],
];

$home_id = matrix_seed_page('home', 'Home');
update_field('hero_content_blocks', [], $home_id);
$ok2 = update_field('flexible_content_blocks', $home_blocks, $home_id);
WP_CLI::log('Home page #' . $home_id . ' (' . count($home_blocks) . ' blocks) update_field=' . ($ok2 ? 'ok' : 'false'));

update_option('show_on_front', 'page');
update_option('page_on_front', $home_id);

WP_CLI::success('Front page: ' . get_permalink($home_id));
WP_CLI::success('Flexi page: ' . get_permalink($flexi_id));
