<?php
/**
 * Seed Community page, category, sample authors/posts, and DemoHouse-linked content.
 * wp eval-file wp-content/themes/matrix-starter/scripts/seed-community.php
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
            WP_CLI::warning("Missing: {$relative}");
            return 0;
        }
        $filename = basename($path);
        $existing = get_posts([
            'post_type' => 'attachment', 'posts_per_page' => 1, 'post_status' => 'inherit',
            'fields' => 'ids', 'meta_key' => '_matrix_seed_source', 'meta_value' => $filename,
        ]);
        if (!empty($existing[0])) {
            return (int) $existing[0];
        }
        $upload = wp_upload_bits($filename, null, file_get_contents($path));
        if (!empty($upload['error'])) {
            return 0;
        }
        $type = wp_check_filetype($filename);
        $id = wp_insert_attachment([
            'post_mime_type' => $type['type'] ?: 'image/webp',
            'post_title' => $title ?: pathinfo($filename, PATHINFO_FILENAME),
            'post_status' => 'inherit',
        ], $upload['file']);
        if (!$id || is_wp_error($id)) {
            return 0;
        }
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
        update_post_meta($id, '_matrix_seed_source', $filename);
        return (int) $id;
    }
}

$cat_id = matrix_dz_ensure_community_category();
WP_CLI::log("Community category #{$cat_id}");

$community_img = matrix_seed_dz_file('community.webp', 'Local community members sharing plants');
$map_img       = matrix_seed_dz_file('map.webp', 'Illustrated view of Maynooth');
$icon_img      = matrix_seed_dz_file('community-icon.svg', 'Community icon');

/** Ensure approved seed authors. */
function matrix_seed_community_user(string $login, string $email, string $display, string $group, string $first): int
{
    $user = get_user_by('login', $login);
    if ($user) {
        $id = (int) $user->ID;
    } else {
        $id = wp_insert_user([
            'user_login'   => $login,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password(20),
            'display_name' => $display,
            'first_name'   => $first,
            'role'         => 'contributor',
        ]);
        if (is_wp_error($id)) {
            WP_CLI::warning($id->get_error_message());
            return 0;
        }
    }
    update_user_meta($id, 'community_group_name', $group);
    update_user_meta($id, 'community_account_approved', '1');
    $u = new WP_User($id);
    $u->set_role('contributor');
    return (int) $id;
}

$tidy_id = matrix_seed_community_user(
    'maynooth-tidy-towns',
    'tidy-towns-demo@maynoothdz.local',
    'Maynooth Tidy Towns',
    'Maynooth Tidy Towns',
    'Sarah'
);
$cao_id = matrix_seed_community_user(
    'climate-action-office',
    'cao-demo@maynoothdz.local',
    'Climate Action Office',
    'Climate Action Office',
    'Climate'
);

$picnic_content = <<<'HTML'
<p class="article-intro">Food, music and the first public conversation about what the Decarbonising Zone could mean for the town.</p>
<h2>A shared space for new ideas</h2>
<p>A picnic is a simple way to bring people together. At Harbour Field, neighbours had a chance to meet, share food and talk about the small changes that could make Maynooth a greener place to live.</p>
<p>From growing more locally to making everyday journeys on foot or by bike, the conversation started with things people could do together.</p>
<h3>Climate action starts close to home</h3>
<p>You do not need to have all the answers to get involved. Local groups can connect people, share practical experience and turn an idea into a community project.</p>
<p>Explore the <a href="/projects/?category=Community%20action">community projects</a> already taking shape, or visit the <a href="/projects/demohouse-retrofit/">DemoHouse retrofit project</a> to discover ways to make homes more comfortable and energy efficient.</p>
<h4>Keep the conversation going</h4>
<p>Tell your neighbours about the Decarbonising Zone, bring an idea to your local group or share what you have learned. Every conversation can be a starting point.</p>
<blockquote><p>“We just wanted to get people talking — 300 showed up.”</p><cite>Organiser · Maynooth Tidy Towns</cite></blockquote>
HTML;

$map_content = <<<'HTML'
<p class="article-intro">Find projects by theme, explore the places behind them and choose your own next step.</p>
<h2>Local action, all in one place</h2>
<p>The project map brings together themes including retrofit, transport, biodiversity, energy, community and public spaces. It offers a starting point for discovering how local action can help shape the Decarbonising Zone.</p>
<h3>Find a project that interests you</h3>
<p>Choose a theme, select a pin and open the project to find out more. You can also browse the <a href="/projects/">project directory</a> and filter the results by category or status.</p>
<h3>Explore the demonstration</h3>
<p>The current map contains 18 demonstration projects. Sample projects and illustrative pin locations show how the directory and map work together.</p>
<p><a href="/map/">Explore the map ↗</a></p>
HTML;

function matrix_seed_community_post(string $slug, string $title, string $excerpt, string $content, int $author, int $thumb, string $date_gmt, array $meta = []): int
{
    $existing = get_posts(['post_type' => 'post', 'name' => $slug, 'post_status' => 'any', 'posts_per_page' => 1]);
    $data = [
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_status'  => 'publish',
        'post_type'    => 'post',
        'post_author'  => $author,
        'post_excerpt' => $excerpt,
        'post_content' => $content,
        'post_date'    => get_date_from_gmt($date_gmt),
        'post_date_gmt'=> $date_gmt,
    ];
    if ($existing) {
        $id = (int) $existing[0]->ID;
        $data['ID'] = $id;
        wp_update_post($data);
    } else {
        $id = (int) wp_insert_post($data, true);
        if (is_wp_error($id) || !$id) {
            return 0;
        }
    }
    wp_set_post_categories($id, [matrix_dz_ensure_community_category()]);
    if ($thumb) {
        set_post_thumbnail($id, $thumb);
    }
    foreach ($meta as $k => $v) {
        update_field($k, $v, $id);
    }
    return $id;
}

// Event for picnic
$event_existing = get_posts(['post_type' => 'event', 'name' => 'picnic-in-the-park', 'post_status' => 'any', 'posts_per_page' => 1]);
if ($event_existing) {
    $event_id = (int) $event_existing[0]->ID;
    wp_update_post(['ID' => $event_id, 'post_title' => 'Picnic in the Park', 'post_status' => 'publish', 'post_author' => $tidy_id ?: 1]);
} else {
    $event_id = (int) wp_insert_post([
        'post_title'  => 'Picnic in the Park',
        'post_name'   => 'picnic-in-the-park',
        'post_status' => 'publish',
        'post_type'   => 'event',
        'post_author' => $tidy_id ?: 1,
    ], true);
}
if ($event_id && !is_wp_error($event_id)) {
    update_field('event_date', '2026-08-14', $event_id);
    update_field('event_time', '12:00–16:00 · free, all welcome', $event_id);
    update_field('event_location', 'Harbour Field, Maynooth', $event_id);
    update_field('event_map_url', home_url('/map/?project=picnic-in-the-park'), $event_id);
}

$picnic_id = matrix_seed_community_post(
    'picnic-in-the-park-brought-300-neighbours-together',
    'Picnic in the Park brought 300 neighbours together',
    'Food, music and a first conversation about what the Decarbonising Zone could mean for the town.',
    $picnic_content,
    $tidy_id ?: 1,
    $community_img,
    '2026-08-14 12:00:00',
    [
        'community_byline_name'  => 'Sarah Kelly',
        'community_byline_group' => 'Maynooth Tidy Towns',
        'community_linked_event' => $event_id ?: '',
    ]
);

$map_post_id = matrix_seed_community_post(
    'explore-the-zones-projects-on-the-map',
    'Explore the Zone’s projects on the map',
    'Find projects by theme, explore the places behind them and choose your own next step.',
    $map_content,
    $cao_id ?: 1,
    $map_img,
    '2026-09-02 10:00:00',
    [
        'community_byline_name'  => 'the Climate Action Office',
        'community_byline_group' => 'Kildare County Council',
    ]
);

WP_CLI::log("Picnic post #{$picnic_id}");
WP_CLI::log("Map post #{$map_post_id}");
WP_CLI::log("Event #{$event_id}");

// Community page
$existing = get_page_by_path('community');
if ($existing) {
    $page_id = (int) $existing->ID;
    wp_update_post(['ID' => $page_id, 'post_title' => 'Community', 'post_status' => 'publish', 'post_name' => 'community']);
} else {
    $page_id = (int) wp_insert_post([
        'post_title' => 'Community',
        'post_name' => 'community',
        'post_status' => 'publish',
        'post_type' => 'page',
    ], true);
}
update_post_meta($page_id, '_wp_page_template', 'templates/page-community.php');

$page_fields = [
    'cm_tag_label' => 'Together in Maynooth',
    'cm_tag_icon'  => $icon_img ?: '',
    'cm_title'     => 'Community',
    'cm_intro'     => 'What Maynooth’s groups, schools and clubs are doing — and how yours can join in.',
    'cm_updates_heading' => 'Latest updates',
    'cm_updates_count'   => 6,
    'cm_events_heading'  => 'Events',
    'cm_events_empty_title' => 'No upcoming events just yet',
    'cm_events_empty_text'  => 'Follow the updates — new events will appear here as groups come on board.',
    'cm_join_heading' => 'Your group can publish here',
    'cm_join_steps' => [
        ['title' => 'Register interest', 'text' => 'Tell the Climate Action Office about your group.'],
        ['title' => 'Council approves', 'text' => 'A quick check, then your group gets its page.'],
        ['title' => 'Draft, review, publish', 'text' => 'Share updates and events in your own voice.'],
    ],
    'cm_join_button_label' => 'Register your group’s interest',
    'cm_newsletter_heading' => 'Stay in the loop',
    'cm_newsletter_intro' => 'Zone news and community events, straight to your inbox.',
];
foreach ($page_fields as $k => $v) {
    update_field($k, $v, $page_id);
}

// Past event shows empty upcoming — picnic is Aug 2026; today is Oct 2026 so it's past.
// Seed one upcoming event for listing demo? Design shows empty. Keep empty for upcoming.
// Update picnic event date to past is fine - empty state matches design.

flush_rewrite_rules(false);

WP_CLI::success('Community ready: ' . get_permalink($page_id));
WP_CLI::log('Article: ' . get_permalink($picnic_id));
