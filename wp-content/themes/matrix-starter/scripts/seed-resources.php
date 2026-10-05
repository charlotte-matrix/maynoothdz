<?php
/**
 * Create/update the Resources page with design flexi content.
 * Run: wp eval-file wp-content/themes/matrix-starter/scripts/seed-resources.php
 */

if (!function_exists('update_field')) {
    WP_CLI::error('ACF is required.');
}

$existing = get_page_by_path('resources');
if ($existing) {
    $post_id = (int) $existing->ID;
    wp_update_post([
        'ID'          => $post_id,
        'post_title'  => 'Resources',
        'post_status' => 'publish',
        'post_name'   => 'resources',
    ]);
} else {
    $post_id = wp_insert_post([
        'post_title'  => 'Resources',
        'post_name'   => 'resources',
        'post_status' => 'publish',
        'post_type'   => 'page',
        'post_content'=> '',
    ], true);
    if (is_wp_error($post_id)) {
        WP_CLI::error($post_id->get_error_message());
    }
}

$take_action = home_url('/take-action/');

$blocks = [
    [
        'acf_fc_layout'    => 'page_heading',
        'title'            => 'Resources',
        'introduction'     => 'The plans, guides and reading behind the Zone — everything in one calm place.',
        'show_breadcrumbs' => 1,
    ],
    [
        'acf_fc_layout' => 'downloads',
        'heading'       => 'Plans & strategies',
        'items'         => [
            [
                'type' => 'PDF',
                'name' => 'Kildare Climate Action Plan 2024–29',
                'meta' => '10.1 MB',
                'file' => null,
                'url'  => 'https://kildarecoco.ie/AllServices/ClimateAction/KildareCountyCouncilClimateActionPlan2024-2029/LocalAuthorityClimateActionPlan20242029.pdf',
            ],
            [
                'type' => 'PLAN',
                'name' => 'Maynooth SEC Energy Master Plan',
                'meta' => 'Request a copy',
                'file' => null,
                'url'  => 'mailto:climateaction@kildarecoco.ie?subject=Maynooth%20SEC%20Energy%20Master%20Plan%20%E2%80%94%20request%20a%20copy',
            ],
            [
                'type' => 'LINK',
                'name' => 'National Climate Action Plan',
                'meta' => 'gov.ie',
                'file' => null,
                'url'  => 'https://www.gov.ie/en/department-of-climate-energy-and-the-environment/publications/climate-action-plan/',
            ],
        ],
        'accessibility_note' => '',
    ],
    [
        'acf_fc_layout' => 'downloads',
        'heading'       => 'Guides & how-tos',
        'items'         => [
            [
                'type' => 'LINK',
                'name' => 'Home retrofit — where to start',
                'meta' => 'seai.ie',
                'file' => null,
                'url'  => 'https://www.seai.ie/plan-your-energy-journey/for-your-home/guide-to-upgrades',
            ],
            [
                'type' => 'PDF',
                'name' => 'Pollinator-friendly gardens (All-Ireland Pollinator Plan)',
                'meta' => 'View PDF',
                'file' => null,
                'url'  => 'https://pollinators.ie/wp-content/uploads/2022/12/Garden-Pollinator-Guidelines-2022-WEB.pdf',
            ],
        ],
        'accessibility_note' => '',
    ],
    [
        'acf_fc_layout' => 'link_list',
        'heading'       => 'Reading & research',
        'items'         => [
            [
                'title'       => 'Decarbonising Zones — national programme overview',
                'description' => 'What Decarbonising Zones are and how they support local climate action.',
                'url'         => 'https://www.gov.ie/en/department-of-climate-energy-and-the-environment/publications/decarbonising-zones/',
            ],
            [
                'title'       => 'Maynooth University’s sustainability research',
                'description' => 'Explore research on sustainability and climate change.',
                'url'         => 'https://www.maynoothuniversity.ie/research/sustainability-and-climate-change',
            ],
        ],
        'accessibility_note' => 'Need an accessible format? <a href="mailto:climateaction@kildarecoco.ie?subject=Accessible%20resource%20request">Contact the Climate Action Office</a>.',
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
    update_post_meta($post_id, 'flexible_content_blocks', $blocks);
    WP_CLI::warning('update_field returned false — wrote raw post meta fallback.');
}

WP_CLI::success(sprintf(
    'Resources page ready: %s (ID %d) — %d flexi blocks',
    get_permalink($post_id),
    $post_id,
    count($blocks)
));
