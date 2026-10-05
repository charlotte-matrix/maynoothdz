<?php
/**
 * Create/update the Take Action page with design retrofit pathway content.
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

$home_icon = matrix_seed_dz_file('home-icon.svg', 'Home icon');
$town_img  = matrix_seed_dz_file('town-centre.webp', 'Buildings in Maynooth town centre');

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

$existing = get_page_by_path('take-action');
if ($existing) {
    $post_id = (int) $existing->ID;
    wp_update_post([
        'ID'          => $post_id,
        'post_title'  => 'Take Action',
        'post_status' => 'publish',
        'post_name'   => 'take-action',
    ]);
    WP_CLI::log("Updating page #{$post_id}");
} else {
    $post_id = wp_insert_post([
        'post_title'   => 'Take Action',
        'post_name'    => 'take-action',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => '',
    ], true);
    if (is_wp_error($post_id)) {
        WP_CLI::error($post_id->get_error_message());
    }
    WP_CLI::log("Created page #{$post_id}");
}

update_post_meta($post_id, '_wp_page_template', 'templates/page-take-action.php');

$fields = [
    'ta_tag_label'   => 'Retrofit pathway',
    'ta_tag_style'   => 'retrofit',
    'ta_tag_icon'    => $home_icon ?: '',
    'ta_title'       => 'Retrofit your home',
    'ta_intro'       => 'Six steps from “where do I start?” to a warmer home — with guidance on grants, finding contractors and learning from local experience.',
    'ta_steps'       => $steps,
    'ta_final_cta'   => [
        'title'  => 'Explore retrofit projects →',
        'url'    => $map_url,
        'target' => '',
    ],
    'ta_case_project'=> $demo_post ? (int) $demo_post->ID : '',
    'ta_case_title'  => 'See it done: DemoHouse',
    'ta_case_cta'    => 'Read the case study →',
    'ta_case_image'  => '',
    'ta_nearby_tag'  => 'Retrofit in Maynooth',
    'ta_nearby_heading' => "See retrofit projects\nnear you.",
    'ta_nearby_text' => 'Explore local projects, see the homes behind the stories and find inspiration for your own next step.',
    'ta_nearby_button' => [
        'title'  => 'View on the map',
        'url'    => $map_url,
        'target' => '',
    ],
    'ta_nearby_photo_label' => 'Inside DemoHouse Retrofit',
    'ta_nearby_secondary'   => $town_img ?: '',
];

foreach ($fields as $key => $value) {
    $ok = update_field($key, $value, $post_id);
    if (!$ok) {
        update_post_meta($post_id, $key, $value);
        WP_CLI::warning("update_field failed for {$key}; wrote post meta.");
    }
}

WP_CLI::success('Take Action ready: ' . get_permalink($post_id));
WP_CLI::log('Template: ' . get_page_template_slug($post_id));
WP_CLI::log('Case study project: ' . ($demo_post ? $demo_post->post_title : 'none'));
