<?php
/**
 * Seed all Maynooth DZ projects from design/website/project-data.js + project.js.
 * DemoHouse keeps its special content; others use category story/CTA templates.
 * Map pin % coords are seeded by scripts/seed-map-page.php (run after this).
 *
 * Run: wp eval-file wp-content/themes/matrix-starter/scripts/seed-all-projects.php
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

        $candidates = [$theme_dz . '/' . $relative, $design . '/' . $relative];
        $path       = '';
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
}

$category_defs = [
    'energy'                       => ['name' => 'Energy', 'color' => '#a47b13'],
    'retrofit'                     => ['name' => 'Retrofit', 'color' => '#d9684f'],
    'transport'                    => ['name' => 'Transport', 'color' => '#02a59f'],
    'biodiversity-resilience'      => ['name' => 'Biodiversity & Resilience', 'color' => '#1b853f'],
    'community-action'             => ['name' => 'Community action', 'color' => '#8562a5'],
    'public-realm'                 => ['name' => 'Public realm', 'color' => '#258d78'],
    'education-awareness'          => ['name' => 'Education awareness', 'color' => '#f3b63e'],
    'sustainable-practices'        => ['name' => 'Sustainable Practices', 'color' => '#006168'],
    'circular-economy'             => ['name' => 'Circular economy', 'color' => '#ad9d50'],
    'water-nature-based-solutions' => ['name' => 'Water & Nature-based Solutions', 'color' => '#43c6c6'],
];

foreach ($category_defs as $slug => $meta) {
    $term = term_exists($slug, 'project_category');
    if (!$term) {
        $term = wp_insert_term($meta['name'], 'project_category', ['slug' => $slug]);
    }
    if (is_wp_error($term)) {
        WP_CLI::warning($term->get_error_message());
        continue;
    }
    $term_id = (int) (is_array($term) ? $term['term_id'] : $term);
    // Keep names with raw & (avoid &amp; stored in DB).
    global $wpdb;
    $wpdb->update($wpdb->terms, ['name' => $meta['name']], ['term_id' => $term_id]);
    clean_term_cache($term_id, 'project_category');
    update_field('category_color', $meta['color'], 'project_category_' . $term_id);
}

$copy_by_original = [
    'Retrofit' => [
        'why'    => 'Warmer homes and lower energy demand start with understanding what a retrofit involves. This project brings the choices into focus: insulation, ventilation, heating and the practical steps involved in improving an existing home.',
        'more'   => 'Sharing the experience helps other households ask better questions, compare their options and plan improvements that suit their home.',
        'focus'  => 'Home energy',
        'cta'    => 'Thinking about your own home?',
        'sub'    => 'Explore the retrofit pathway, step by step.',
        'button' => 'Explore home retrofit',
        'url'    => home_url('/take-action/retrofit-your-home/'),
    ],
    'Transport' => [
        'why'    => 'Making everyday journeys easier on foot or by bicycle can help reduce the need for short car trips. This project explores how local routes can connect people with schools, shops, green spaces and the town centre.',
        'more'   => 'The emphasis is on everyday usefulness: comfortable connections, clear information and opportunities for people to try a different way of travelling.',
        'focus'  => 'Active travel',
        'cta'    => 'Ready to try a different journey?',
        'sub'    => 'Find practical ways to walk, cycle and travel sustainably.',
        'button' => 'Explore sustainable travel',
        'url'    => home_url('/#action'),
    ],
    'Biodiversity' => [
        'why'    => 'Green spaces can do more for wildlife when they are connected and cared for with nature in mind. This project explores opportunities for pollinator-friendly planting, better habitats and local participation.',
        'more'   => 'Small changes can create places where residents learn about nature, share growing skills and take part in looking after their surroundings.',
        'focus'  => 'Habitats & nature',
        'cta'    => 'Make room for nature.',
        'sub'    => 'Discover ways to take part in local climate action.',
        'button' => 'Find your next action',
        'url'    => home_url('/#action'),
    ],
    'Energy' => [
        'why'    => 'Understanding where energy is used is a useful first step towards using less of it. This project explores a practical opportunity for cleaner energy and shared learning in Maynooth.',
        'more'   => 'The focus is on helping people ask informed questions about energy demand, suitable technology and the steps needed before making an investment.',
        'focus'  => 'Clean energy',
        'cta'    => 'Small changes, lasting habits.',
        'sub'    => 'Explore everyday ways to save energy at home.',
        'button' => 'Explore energy actions',
        'url'    => home_url('/#action'),
    ],
    'Community' => [
        'why'    => 'Climate action becomes more approachable when people can learn and take part together. This project creates a starting point for sharing skills, meeting neighbours and trying practical ideas.',
        'more'   => 'Community-led activities can help useful ideas travel further, from repairing everyday items to growing food and reducing waste.',
        'focus'  => 'Local participation',
        'cta'    => 'Bring your community together.',
        'sub'    => 'Find an action you can share with neighbours, friends or a local group.',
        'button' => 'Explore community actions',
        'url'    => home_url('/#action'),
    ],
    'Public realm' => [
        'why'    => 'The spaces between buildings shape how a town feels and how people move through it. This project explores how greener public spaces can make everyday life more comfortable while supporting climate action.',
        'more'   => 'Planting, shade and spaces to pause can be considered together with access, maintenance and the needs of the people who use the town.',
        'focus'  => 'Greener shared spaces',
        'cta'    => 'Help shape a greener Maynooth.',
        'sub'    => 'Explore practical ways to get involved in your community.',
        'button' => 'Find your next action',
        'url'    => home_url('/#action'),
    ],
];

$category_aliases = [
    'Biodiversity' => 'Biodiversity & Resilience',
    'Community'    => 'Community action',
];

$category_to_slug = [
    'Energy'                         => 'energy',
    'Retrofit'                       => 'retrofit',
    'Transport'                      => 'transport',
    'Biodiversity & Resilience'      => 'biodiversity-resilience',
    'Community action'               => 'community-action',
    'Public realm'                   => 'public-realm',
    'Education awareness'            => 'education-awareness',
    'Sustainable Practices'          => 'sustainable-practices',
    'Circular economy'               => 'circular-economy',
    'Water & Nature-based Solutions' => 'water-nature-based-solutions',
];

// [title, originalCategory, status, image, description]
$rows = [
    ['DemoHouse Retrofit', 'Retrofit', 'Complete', 'retrofit.webp', 'A completed home retrofit demonstrating the journey to an A-rating. The demonstration house is now closed.'],
    ['Royal Canal Greenway Links', 'Transport', 'Underway', 'canal.webp', 'Safer walking and cycling connections from the Greenway into town.'],
    ['Harbour Field for Pollinators', 'Biodiversity', 'Planned', 'pollinators.webp', 'Turning the Harbour Field into a haven for bees and wildflowers.'],
    ['Solar on the Community Hall', 'Energy', 'Underway', 'town-centre.webp', 'A sample rooftop solar project to help a shared community building use cleaner energy.'],
    ['Picnic in the Park', 'Community', 'Complete', 'community.webp', 'A sample neighbourhood gathering to share food, ideas and practical climate actions.'],
    ['Main Street Greening', 'Public realm', 'Planned', 'town-centre.webp', 'A sample approach to bringing more trees, shade and planting into the town centre.'],
    ['Warm Homes, Lower Bills', 'Retrofit', 'Underway', 'retrofit.webp', 'A sample insulation programme helping neighbours explore warmer, more efficient homes.'],
    ['Cycle to School Together', 'Transport', 'Planned', 'maynooth.webp', 'A sample cycle-bus initiative supporting safer journeys to school with local volunteers.'],
    ['Canal-side Nature Corridors', 'Biodiversity', 'Underway', 'canal-nature.webp', 'A sample habitat project linking waterside planting and wildlife-friendly green spaces.'],
    ['Shared Solar for Neighbours', 'Energy', 'Planned', 'retrofit.webp', 'A sample group-buy scheme making it easier to learn about solar panels and installation.'],
    ['Maynooth Repair Café', 'Community', 'Underway', 'community.webp', 'A sample monthly meetup where neighbours share skills and give everyday items a second life.'],
    ['Greener Town Square', 'Public realm', 'Underway', 'maynooth.webp', 'A sample public-space project combining seating, shade and rain-friendly planting.'],
    ['Open Doors: Retrofit Stories', 'Retrofit', 'Complete', 'town-centre.webp', 'A sample open-home event sharing what local households learned from their energy upgrades.'],
    ['Walk the Last Kilometre', 'Transport', 'Complete', 'canal.webp', 'A sample walking initiative helping residents discover pleasant routes for everyday journeys.'],
    ['Community Orchard', 'Biodiversity', 'Planned', 'pollinators.webp', 'A sample orchard with native planting, seasonal fruit and opportunities to learn together.'],
    ['Community Energy Check-in', 'Energy', 'Complete', 'maynooth.webp', 'A sample energy-awareness programme sharing practical ways to reduce unnecessary energy use.'],
    ['Climate Skills Swap', 'Community', 'Planned', 'community.webp', 'A sample series of workshops on growing food, repairing possessions and reducing waste.'],
    ['Rain Gardens for Maynooth', 'Public realm', 'Planned', 'canal-nature.webp', 'A sample planting scheme showing how green spaces can absorb rainwater and support nature.'],
];

$reassigned = [
    'maynooth-repair-caf'         => 'Circular economy',
    'climate-skills-swap'         => 'Education awareness',
    'community-energy-check-in'   => 'Sustainable Practices',
    'rain-gardens-for-maynooth'   => 'Water & Nature-based Solutions',
];

$image_alts = [
    'retrofit.webp'     => 'Illustrative retrofit photograph',
    'canal.webp'        => 'The Royal Canal in Maynooth',
    'pollinators.webp'  => 'Pollinator-friendly planting',
    'town-centre.webp'  => 'Maynooth town centre',
    'community.webp'    => 'Community gathering in Maynooth',
    'maynooth.webp'     => 'A view of Maynooth',
    'canal-nature.webp' => 'Nature along the Royal Canal',
];

$images = [];
foreach (array_unique(array_column($rows, 3)) as $file) {
    $images[$file] = matrix_seed_dz_file($file, $image_alts[$file] ?? pathinfo($file, PATHINFO_FILENAME));
}

$solar_icon  = matrix_seed_dz_file('map-icons/solar-house.svg', 'Solar house');
$ber_icon    = matrix_seed_dz_file('map-icons/ber-house.svg', 'BER house');
$saving_icon = matrix_seed_dz_file('map-icons/saving-energy.svg', 'Saving energy');
$seai_logo   = matrix_seed_dz_file('partner-seai.svg', 'SEAI — Sustainable Energy Authority of Ireland');
$sec_logo    = matrix_seed_dz_file('partner-maynooth-sec.jpg', 'Maynooth Sustainable Energy Community');

$created = 0;
$updated = 0;

foreach ($rows as $i => $row) {
    [$title, $original, $status, $image, $description] = $row;

    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    $display_category = $reassigned[$slug] ?? ($category_aliases[$original] ?? $original);
    $term_slug        = $category_to_slug[$display_category] ?? sanitize_title($display_category);
    $copy             = $copy_by_original[$original] ?? $copy_by_original['Community'];
    $is_demo          = ($slug === 'demohouse-retrofit');
    $image_id         = $images[$image] ?? 0;

    // Match design dates: UTC 2026-09-24 minus i*6 days.
    $ts       = gmmktime(12, 0, 0, 9, 24 - ($i * 6), 2026);
    $post_date = gmdate('Y-m-d H:i:s', $ts);

    $existing = get_posts([
        'post_type'      => 'project',
        'name'           => $slug,
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ]);

    $postarr = [
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_status'  => 'publish',
        'post_type'    => 'project',
        'post_excerpt' => $description,
        'post_date'    => get_date_from_gmt($post_date),
        'post_date_gmt'=> $post_date,
    ];

    if (!empty($existing[0])) {
        $post_id = (int) $existing[0];
        $postarr['ID'] = $post_id;
        wp_update_post($postarr);
        $updated++;
    } else {
        $post_id = wp_insert_post($postarr, true);
        if (is_wp_error($post_id)) {
            WP_CLI::warning($title . ': ' . $post_id->get_error_message());
            continue;
        }
        $created++;
    }

    wp_set_object_terms($post_id, [$term_slug], 'project_category', false);

    if ($image_id) {
        set_post_thumbnail($post_id, $image_id);
    }

    if ($is_demo) {
        $lead = 'A completed Maynooth home retrofit to an A-rating — sharing what the process involved.';
        $why  = '<p>Homes make up 38.5% of Maynooth’s emissions. DemoHouse shows what a deep retrofit looks like from the inside: the choices, the costs, the disruption and what it feels like to live in afterwards.</p>'
            . '<p>The house is a normal semi-detached home, chosen because much of the town’s housing stock looks like it. Sharing the experience helps other households understand what their own next step could be.</p>';
        $fields = [
            'project_eyebrow'          => 'Part of the Maynooth Decarbonising Zone',
            'project_status'           => $status,
            'project_location_label'   => 'Maynooth, County Kildare',
            'project_lead'             => $lead,
            'project_demo_note'        => '',
            'project_why_heading'      => 'Why this project',
            'project_why_body'         => $why,
            'project_media_image'      => $image_id ?: '',
            'project_media_caption'    => 'DemoHouse, Maynooth.',
            'project_story_extra'      => '<p>The demonstration house is now closed and is returning to social housing use. Its location is not displayed to protect residents’ privacy.</p>',
            'project_glance_heading'   => 'At a glance',
            'project_glance_items'     => [
                ['label' => 'BER before → after', 'value' => 'B3 → A2', 'is_pending' => 0],
                ['label' => 'Energy-use reduction', 'value' => 'To be confirmed', 'is_pending' => 1],
                ['label' => 'Grants used', 'value' => 'To be confirmed', 'is_pending' => 1],
            ],
            'project_grant_icons'      => array_values(array_filter([$solar_icon, $ber_icon, $saving_icon])),
            'project_partners_heading' => 'Partners',
            'project_partner_logos'    => array_values(array_filter([$seai_logo, $sec_logo])),
            'project_partners_fallback'=> 'Project partners to be confirmed.',
            'project_enable_summary'   => 1,
            'project_summary_title'    => '',
            'project_summary_meta'     => 'Printable, accessible HTML',
            'project_show_related'     => 1,
            'project_related_heading'  => 'Related projects',
            'project_cta_heading'      => $copy['cta'],
            'project_cta_text'         => $copy['sub'],
            'project_cta_button'       => [
                'title'  => $copy['button'],
                'url'    => $copy['url'],
                'target' => '',
            ],
        ];
    } else {
        $cta_url = ($display_category === 'Retrofit' || $original === 'Retrofit')
            ? home_url('/take-action/retrofit-your-home/')
            : home_url('/#action');

        $fields = [
            'project_eyebrow'          => 'Part of the Maynooth Decarbonising Zone',
            'project_status'           => $status,
            'project_location_label'   => 'Maynooth, County Kildare',
            'project_lead'             => $description,
            'project_demo_note'        => '',
            'project_why_heading'      => 'Why this project',
            'project_why_body'         => '<p>' . esc_html($copy['why']) . '</p><p>' . esc_html($copy['more']) . '</p>',
            'project_media_image'      => $image_id ?: '',
            'project_media_caption'    => 'A view of Maynooth from the project image collection.',
            'project_story_extra'      => '',
            'project_glance_heading'   => 'At a glance',
            'project_glance_items'     => [
                ['label' => 'Project status', 'value' => $status, 'is_pending' => 0],
                ['label' => 'Focus', 'value' => $copy['focus'], 'is_pending' => 0],
                ['label' => 'Location', 'value' => 'Maynooth', 'is_pending' => 0],
            ],
            'project_grant_icons'      => [],
            'project_partners_heading' => 'Partners',
            'project_partner_logos'    => [],
            'project_partners_fallback'=> 'Project partners to be confirmed.',
            'project_enable_summary'   => 1,
            'project_summary_title'    => '',
            'project_summary_meta'     => 'Printable, accessible HTML',
            'project_show_related'     => 1,
            'project_related_heading'  => 'Related projects',
            'project_cta_heading'      => $copy['cta'],
            'project_cta_text'         => $copy['sub'],
            'project_cta_button'       => [
                'title'  => $copy['button'],
                'url'    => $cta_url,
                'target' => '',
            ],
        ];
    }

    foreach ($fields as $key => $value) {
        // Never dump repeater arrays onto the parent meta key — ACF needs a count + row meta.
        if ($key === 'project_glance_items' && is_array($value)) {
            $ok = update_field($key, $value, $post_id);
            if (!$ok || !is_array(get_field($key, $post_id))) {
                update_post_meta($post_id, 'project_glance_items', count($value));
                update_post_meta($post_id, '_project_glance_items', 'field_project_details_project_glance_items');
                foreach ($value as $i => $row) {
                    update_post_meta($post_id, "project_glance_items_{$i}_label", (string) ($row['label'] ?? ''));
                    update_post_meta($post_id, "project_glance_items_{$i}_value", (string) ($row['value'] ?? ''));
                    update_post_meta($post_id, "project_glance_items_{$i}_is_pending", !empty($row['is_pending']) ? 1 : 0);
                    update_post_meta($post_id, "_project_glance_items_{$i}_label", 'field_project_details_project_glance_items_label');
                    update_post_meta($post_id, "_project_glance_items_{$i}_value", 'field_project_details_project_glance_items_value');
                    update_post_meta($post_id, "_project_glance_items_{$i}_is_pending", 'field_project_details_project_glance_items_is_pending');
                }
            }
            continue;
        }

        $ok = update_field($key, $value, $post_id);
        if (!$ok && !is_array($value)) {
            update_post_meta($post_id, $key, $value);
        }
    }

    WP_CLI::log(sprintf('%s → %s (%s)', $title, get_permalink($post_id), $display_category));
}

flush_rewrite_rules(false);

$count = (int) wp_count_posts('project')->publish;
WP_CLI::success("Projects seeded. created={$created} updated={$updated} published={$count}");
