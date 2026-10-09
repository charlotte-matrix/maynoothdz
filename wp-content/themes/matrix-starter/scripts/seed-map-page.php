<?php
/**
 * Create/update the Map page and seed project pin coordinates.
 *
 * Run: wp eval-file wp-content/themes/matrix-starter/scripts/seed-map-page.php
 */

if (!function_exists('update_field')) {
    WP_CLI::error('ACF is required.');
}

/**
 * Pin % on maynooth-map.svg (viewBox 5063×2848).
 * Landmark-anchored where a clear match exists; otherwise design illustrative coords.
 *
 * @var array<string, array{show:bool,x?:float,y?:float}>
 */
$pins = [
    'demohouse-retrofit'             => ['show' => false],
    'royal-canal-greenway-links'     => ['show' => true, 'x' => 34.5, 'y' => 45.0],
    'harbour-field-for-pollinators'  => ['show' => true, 'x' => 40.1, 'y' => 41.3],
    'solar-on-the-community-hall'    => ['show' => true, 'x' => 39.9, 'y' => 41.5],
    'picnic-in-the-park'             => ['show' => true, 'x' => 44.9, 'y' => 37.0],
    'main-street-greening'           => ['show' => true, 'x' => 37.9, 'y' => 33.6],
    'warm-homes-lower-bills'         => ['show' => true, 'x' => 67.4, 'y' => 52.8],
    'cycle-to-school-together'       => ['show' => true, 'x' => 37.5, 'y' => 12.0],
    'canal-side-nature-corridors'    => ['show' => true, 'x' => 33.4, 'y' => 48.0],
    'shared-solar-for-neighbours'    => ['show' => true, 'x' => 43.7, 'y' => 29.5],
    'maynooth-repair-caf'            => ['show' => true, 'x' => 37.5, 'y' => 70.6],
    'greener-town-square'            => ['show' => true, 'x' => 35.9, 'y' => 37.3],
    'open-doors-retrofit-stories'    => ['show' => true, 'x' => 70.5, 'y' => 74.2],
    'walk-the-last-kilometre'        => ['show' => true, 'x' => 39.5, 'y' => 45.6],
    'community-orchard'              => ['show' => true, 'x' => 45.0, 'y' => 36.5],
    'community-energy-check-in'      => ['show' => true, 'x' => 35.0, 'y' => 60.2],
    'climate-skills-swap'            => ['show' => true, 'x' => 65.8, 'y' => 26.8],
    'rain-gardens-for-maynooth'      => ['show' => true, 'x' => 56.3, 'y' => 78.8],
];

$updated = 0;
foreach ($pins as $slug => $pin) {
    $posts = get_posts([
        'post_type'      => 'project',
        'name'           => $slug,
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ]);
    if (empty($posts[0])) {
        // Repair Café slug may include é → caf-e variants.
        if ($slug === 'maynooth-repair-caf') {
            $posts = get_posts([
                'post_type'      => 'project',
                's'              => 'Maynooth Repair',
                'posts_per_page' => 5,
                'post_status'    => 'any',
                'fields'         => 'ids',
            ]);
            $posts = array_values(array_filter($posts, static function ($id) {
                return str_contains((string) get_post_field('post_name', $id), 'repair');
            }));
        }
    }
    if (empty($posts[0])) {
        WP_CLI::warning("Project not found: {$slug}");
        continue;
    }
    $id = (int) $posts[0];
    update_field('project_show_on_map', !empty($pin['show']) ? 1 : 0, $id);
    if (!empty($pin['show'])) {
        update_field('project_map_x', (float) $pin['x'], $id);
        update_field('project_map_y', (float) $pin['y'], $id);
    } else {
        update_field('project_map_x', '', $id);
        update_field('project_map_y', '', $id);
    }
    $updated++;
    WP_CLI::log(sprintf(
        '%s → show=%s x=%s y=%s',
        get_the_title($id),
        !empty($pin['show']) ? 'yes' : 'no',
        $pin['x'] ?? '—',
        $pin['y'] ?? '—'
    ));
}

$page = get_page_by_path('map');
$template = 'templates/page-map.php';
if ($page instanceof WP_Post) {
    $page_id = (int) $page->ID;
    wp_update_post([
        'ID'           => $page_id,
        'post_title'   => 'Explore the Maynooth Decarbonising Zone',
        'post_name'    => 'map',
        'post_status'  => 'publish',
        'post_excerpt' => 'Filter by theme, choose a pin and discover local climate action.',
    ]);
} else {
    $page_id = (int) wp_insert_post([
        'post_title'   => 'Explore the Maynooth Decarbonising Zone',
        'post_name'    => 'map',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_excerpt' => 'Filter by theme, choose a pin and discover local climate action.',
    ], true);
    if (is_wp_error($page_id) || !$page_id) {
        WP_CLI::error('Could not create Map page.');
    }
}

update_post_meta($page_id, '_wp_page_template', $template);
flush_rewrite_rules(false);

WP_CLI::success(sprintf(
    'Map page ready: %s (pins updated: %d)',
    get_permalink($page_id),
    $updated
));
