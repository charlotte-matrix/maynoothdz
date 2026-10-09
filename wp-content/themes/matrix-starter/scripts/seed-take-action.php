<?php
/**
 * Seed Take Action CPT archive + pathway singles.
 * Migrates the legacy /take-action/ page into retrofit-your-home.
 *
 * Run: wp eval-file wp-content/themes/matrix-starter/scripts/seed-take-action.php
 */

if (!function_exists('update_field')) {
    WP_CLI::error('ACF is required.');
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

if (!function_exists('matrix_seed_dz_file')) {
    function matrix_seed_dz_file(string $relative, string $title = ''): int
    {
        $theme_dz = get_template_directory() . '/assets/dz';
        $design   = '/Users/charlottevial/Local Sites/maynoothdz/design/website/assets';
        $path     = '';
        foreach ([$theme_dz . '/' . $relative, $design . '/' . $relative] as $candidate) {
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
        return (int) $id;
    }
}

if (!function_exists('matrix_seed_take_action_post')) {
    function matrix_seed_take_action_post(string $slug, string $title, int $menu_order = 0): int
    {
        $existing = get_posts([
            'post_type'      => 'take_action',
            'name'           => $slug,
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ]);
        if (!empty($existing[0])) {
            $id = (int) $existing[0];
            wp_update_post([
                'ID'          => $id,
                'post_title'  => $title,
                'post_status' => 'publish',
                'post_name'   => $slug,
                'menu_order'  => $menu_order,
            ]);
            return $id;
        }

        $id = wp_insert_post([
            'post_title'  => $title,
            'post_name'   => $slug,
            'post_status' => 'publish',
            'post_type'   => 'take_action',
            'menu_order'  => $menu_order,
        ], true);

        if (is_wp_error($id)) {
            WP_CLI::error($id->get_error_message());
        }

        return (int) $id;
    }
}

$home_icon   = matrix_seed_dz_file('home-icon.svg', 'Home icon');
$travel_icon = matrix_seed_dz_file('travel-action-icon.svg', 'Travel icon');
$leaf_icon   = matrix_seed_dz_file('leaf-action-icon.svg', 'Leaf icon');
$energy_icon = matrix_seed_dz_file('energy-icon.svg', 'Energy icon');
$town_img    = matrix_seed_dz_file('town-centre.webp', 'Buildings in Maynooth town centre');
$canal_img   = matrix_seed_dz_file('canal.webp', 'The Royal Canal in Maynooth');
$community_img = matrix_seed_dz_file('community.webp', 'Community gathering in Maynooth');
$maynooth_img  = matrix_seed_dz_file('maynooth.webp', 'A view of Maynooth');
$retrofit_img  = matrix_seed_dz_file('retrofit.webp', 'Illustrative retrofit photograph');

$demo = get_posts([
    'post_type'      => 'project',
    'name'           => 'demohouse-retrofit',
    'posts_per_page' => 1,
    'post_status'    => 'publish',
]);
$demo_post = $demo[0] ?? null;
$demo_url  = $demo_post ? get_permalink($demo_post) : home_url('/projects/demohouse-retrofit/');
$map_url   = home_url('/map/?category=Retrofit');
$projects_retrofit = home_url('/projects/?category=Retrofit');

$text = static function (string $t): array {
    return ['type' => 'text', 'text' => $t, 'resource_label' => '', 'resource_url' => '', 'resource_icon' => '↗'];
};
$resource = static function (string $label, string $url, string $icon = '↗'): array {
    return ['type' => 'resource', 'text' => '', 'resource_label' => $label, 'resource_url' => $url, 'resource_icon' => $icon];
};

$steps = [
    [
        'tab_label' => 'Understand your home',
        'title'     => 'Step 1 — Understand your home',
        'blocks'    => [
            $text('Start with how your home feels and performs. Every household has different priorities. It can help to write down which spaces you use most, when the house feels comfortable and what you would most like to improve.'),
            $resource('Explore home energy upgrades', 'https://www.seai.ie/homeenergyupgrades'),
            $text('Gather your energy bills, note cold rooms or draughts, and review any existing BER report. A home energy assessment can help you understand the options.'),
            $resource('Find an energy professional', 'https://www.seai.ie/grants/find-a-registered-professional'),
            $text('Keep your notes together so you can return to them as you work through the next steps. Include what matters most to everyone living in the home, and any questions you would like to discuss at an assessment.'),
        ],
    ],
    [
        'tab_label' => 'Plan & prioritise',
        'title'     => 'Step 2 — Plan & prioritise',
        'blocks'    => [
            $text('Bring your priorities together: comfort, running costs and the work your home needs.'),
            $resource('How a One Stop Shop can help', 'https://www.seai.ie/grants/home-energy-grants/one-stop-shop'),
            $text('Discuss the sequence of improvements with a qualified professional, including how insulation, ventilation and heating fit together.'),
            $resource('See it done: DemoHouse', $demo_url, '→'),
            $text('Use the local example as a starting point for your questions. Your own priorities can be recorded alongside the plan and revisited as the project develops.'),
        ],
    ],
    [
        'tab_label' => 'Grants & finance',
        'title'     => 'Step 3 — Grants & finance',
        'blocks'    => [
            $text('Explore the support available for your planned upgrades.'),
            $resource('SEAI grant options explained', 'https://www.seai.ie/homeenergyupgrades'),
            $text('Compare the SEAI upgrade routes and ask a registered One Stop Shop which options may suit your home. Check the current eligibility, application steps and financing terms before committing.'),
            $resource('Home Energy Upgrade Loan Scheme', 'https://www.sbci.gov.ie/products/home-energy-upgrade-loan-scheme'),
            $text('Keep the information you collect with your project notes so you can refer back to it when comparing options.'),
        ],
    ],
    [
        'tab_label' => 'Choose contractors',
        'title'     => 'Step 4 — Choose contractors',
        'blocks'    => [
            $text('Compare written proposals against the same scope of work.'),
            $resource('Find registered contractors & professionals', 'https://www.seai.ie/grants/find-a-registered-professional'),
            $text('Ask about relevant experience, registration, timelines, warranties and who will coordinate the project. Make sure responsibilities and costs are clear before you agree.'),
            $resource('Find a registered One Stop Shop', 'https://www.seai.ie/grants/find-a-registered-professional/one-stop-shop-providers'),
            $text('Keep the proposals together and make a note of any questions you still want to ask before choosing your next step.'),
        ],
    ],
    [
        'tab_label' => 'The works',
        'title'     => 'Step 5 — The works',
        'blocks'    => [
            $text('Agree how the work will be organised and what disruption to expect.'),
            $resource('What to expect from a One Stop Shop', 'https://www.seai.ie/grants/home-energy-grants/one-stop-shop/multiple-energy-upgrades'),
            $text('Keep the scope, schedule and contact details together, and raise questions with your project coordinator as the work progresses. Keep records of approvals and any agreed changes.'),
            $resource('Revisit the DemoHouse case study', $demo_url, '→'),
            $text('Local experiences can help you think about the questions to ask while planning the work in your own home.'),
        ],
    ],
    [
        'tab_label' => 'Live & measure',
        'title'     => 'Step 6 — Live & measure',
        'blocks'    => [
            $text('Take time to understand your upgraded home and its controls.'),
            $resource('Explore more retrofit projects', $projects_retrofit, '→'),
            $text('Keep the handover documents, ask questions about maintenance and compare energy use over time, allowing for weather and changes in how you use the home.'),
            $resource('Share your experience with the Climate Action Office', 'mailto:climateaction@kildarecoco.ie?subject=My%20Maynooth%20retrofit%20experience', '→'),
            $text('Return to your original notes and reflect on what has changed. Your experience can also help other households thinking about their next steps.'),
        ],
    ],
];

// Free /take-action/ for the CPT archive.
$legacy = get_page_by_path('take-action');
if ($legacy instanceof WP_Post) {
    wp_update_post([
        'ID'          => (int) $legacy->ID,
        'post_name'   => 'take-action-legacy-page',
        'post_status' => 'draft',
    ]);
    WP_CLI::log('Retired legacy Take Action page #' . $legacy->ID);
}

$retrofit_id = matrix_seed_take_action_post('retrofit-your-home', 'Retrofit your home', 1);
if ($retrofit_img) {
    set_post_thumbnail($retrofit_id, $retrofit_img);
}

$retrofit_fields = [
    'ta_tag_label'          => 'Retrofit pathway',
    'ta_tag_style'          => 'retrofit',
    'ta_tag_icon'           => $home_icon ?: '',
    'ta_title'              => 'Retrofit your home',
    'ta_intro'              => 'Six steps from “where do I start?” to a warmer home — with guidance on grants, finding contractors and learning from local experience.',
    'ta_steps'              => $steps,
    'ta_final_cta'          => [
        'title'  => 'Explore retrofit projects →',
        'url'    => $map_url,
        'target' => '',
    ],
    'ta_case_project'       => $demo_post ? (int) $demo_post->ID : '',
    'ta_case_title'         => 'See it done: DemoHouse',
    'ta_case_cta'           => 'Read the case study →',
    'ta_case_image'         => '',
    'ta_nearby_tag'         => 'Retrofit in Maynooth',
    'ta_nearby_heading'     => "See retrofit projects\nnear you.",
    'ta_nearby_text'        => 'Explore local projects, see the homes behind the stories and find inspiration for your own next step.',
    'ta_nearby_button'      => [
        'title'  => 'View on the map',
        'url'    => $map_url,
        'target' => '',
    ],
    'ta_nearby_photo_label' => 'Inside DemoHouse Retrofit',
    'ta_nearby_secondary'   => $town_img ?: '',
];

foreach ($retrofit_fields as $key => $value) {
    $ok = update_field($key, $value, $retrofit_id);
    if (!$ok) {
        update_post_meta($retrofit_id, $key, $value);
        WP_CLI::warning("update_field failed for {$key}; wrote post meta.");
    }
}
WP_CLI::log('Retrofit pathway: ' . get_permalink($retrofit_id));

$stubs = [
    [
        'slug'    => 'travel-sustainably',
        'title'   => 'Travel sustainably',
        'order'   => 2,
        'style'   => 'transport',
        'label'   => 'Travel pathway',
        'icon'    => $travel_icon,
        'image'   => $canal_img,
        'intro'   => 'Practical ways to walk, cycle and take everyday journeys with a lighter footprint around Maynooth.',
    ],
    [
        'slug'    => 'start-a-community-project',
        'title'   => 'Start a community project',
        'order'   => 3,
        'style'   => 'community',
        'label'   => 'Community pathway',
        'icon'    => $leaf_icon,
        'image'   => $community_img,
        'intro'   => 'Ideas and first steps for neighbours who want to organise local climate action together.',
    ],
    [
        'slug'    => 'business-and-school-actions',
        'title'   => 'Business and school actions',
        'order'   => 4,
        'style'   => 'energy',
        'label'   => 'Organisation pathway',
        'icon'    => $energy_icon,
        'image'   => $maynooth_img,
        'intro'   => 'Guidance for workplaces and schools looking to cut energy use and inspire climate action.',
    ],
];

foreach ($stubs as $stub) {
    $id = matrix_seed_take_action_post($stub['slug'], $stub['title'], (int) $stub['order']);
    if (!empty($stub['image'])) {
        set_post_thumbnail($id, (int) $stub['image']);
    }
    $fields = [
        'ta_tag_label' => $stub['label'],
        'ta_tag_style' => $stub['style'],
        'ta_tag_icon'  => $stub['icon'] ?: '',
        'ta_title'     => $stub['title'],
        'ta_intro'     => $stub['intro'],
        'ta_steps'     => [],
    ];
    foreach ($fields as $key => $value) {
        update_field($key, $value, $id);
    }
    WP_CLI::log($stub['title'] . ': ' . get_permalink($id));
}

flush_rewrite_rules(false);

WP_CLI::success('Take Action directory: ' . (get_post_type_archive_link('take_action') ?: home_url('/take-action/')));
