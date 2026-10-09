<?php
/**
 * Maynooth DZ site header / primary navigation.
 */

$home_url   = home_url('/');
$assets     = get_template_directory_uri() . '/assets/dz';
$logo_dark  = $assets . '/logo.svg';
$logo_light = $assets . '/logo-white.svg';

$fallback_links = [
    [
        'title' => 'About',
        'url'   => home_url('/#about'),
    ],
    [
        'title' => 'Map',
        'url'   => home_url('/map/'),
        'class' => 'nav-map',
    ],
    [
        'title' => 'Projects',
        'url'   => home_url('/projects/'),
    ],
    [
        'title' => 'Take Action',
        'url'   => home_url('/take-action/'),
    ],
    [
        'title' => 'Community',
        'url'   => home_url('/community/'),
    ],
    [
        'title' => 'Contact',
        'url'   => '#contact',
    ],
];

/**
 * Whether a top-level nav URL should show as the current section.
 */
$matrix_dz_nav_is_current = static function (string $url): bool {
    if ($url === '' || str_starts_with($url, '#')) {
        return false;
    }

    $home_path = wp_parse_url(home_url('/'), PHP_URL_PATH);
    $home_path = is_string($home_path) ? untrailingslashit($home_path) : '';

    $normalize = static function (string $raw) use ($home_path): string {
        $path = wp_parse_url($raw, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        if ($home_path !== '' && str_starts_with($path, $home_path)) {
            $path = substr($path, strlen($home_path)) ?: '/';
        }
        $path = '/' . ltrim($path, '/');
        return $path === '/' ? '/' : untrailingslashit($path);
    };

    $path = $normalize($url);

    // Projects section: archive + every project single.
    if ($path === '/projects') {
        return is_post_type_archive('project') || is_singular('project');
    }

    // Take Action section: directory + pathway singles (+ legacy page template).
    if ($path === '/take-action') {
        return is_post_type_archive('take_action')
            || is_singular('take_action')
            || is_page_template('templates/page-take-action.php');
    }

    // Community section: page + community-category posts.
    if ($path === '/community') {
        return is_page('community')
            || is_page_template('templates/page-community.php')
            || (is_singular('post') && function_exists('matrix_dz_is_community_post') && matrix_dz_is_community_post());
    }

    // Hash links on the homepage (e.g. /#about) — highlight only on the front page.
    $fragment = wp_parse_url($url, PHP_URL_FRAGMENT);
    if (is_string($fragment) && $fragment !== '') {
        return is_front_page();
    }

    if (is_front_page()) {
        $current = '/';
    } elseif (is_singular()) {
        $permalink = get_permalink();
        $current   = is_string($permalink) ? $normalize($permalink) : '/';
    } else {
        global $wp;
        $current = isset($wp->request) && is_string($wp->request) && $wp->request !== ''
            ? '/' . untrailingslashit($wp->request)
            : '/';
    }

    return $path === $current;
};

$menu_links = [];
if (has_nav_menu('primary')) {
    $locations = get_nav_menu_locations();
    $menu_id   = $locations['primary'] ?? 0;
    $items     = $menu_id ? wp_get_nav_menu_items($menu_id) : false;

    if (is_array($items)) {
        foreach ($items as $item) {
            if ((int) $item->menu_item_parent !== 0) {
                continue;
            }
            $classes = is_array($item->classes) ? array_filter($item->classes) : [];
            if (stripos($item->title, 'map') !== false) {
                $classes[] = 'nav-map';
            }
            $menu_links[] = [
                'title' => $item->title,
                'url'   => $item->url,
                'class' => implode(' ', $classes),
            ];
        }
    }
}

if ($menu_links === []) {
    $menu_links = $fallback_links;
}
?>
<header class="site-header">
    <div aria-hidden="true" class="brand-bar"></div>
    <div class="nav-wrap container">
        <a aria-label="<?php echo esc_attr__('DZ Action Maynooth home', 'matrix-starter'); ?>" class="logo" href="<?php echo esc_url($home_url); ?>">
            <img class="logo-dark" alt="<?php echo esc_attr__('DZ Action — Decarbonising Zone Maynooth', 'matrix-starter'); ?>" height="40" src="<?php echo esc_url($logo_dark); ?>" width="190">
            <img class="logo-light" alt="" height="40" src="<?php echo esc_url($logo_light); ?>" width="191" hidden>
        </a>
        <button aria-controls="navigation" aria-expanded="false" aria-label="<?php echo esc_attr__('Open menu', 'matrix-starter'); ?>" class="menu-toggle" type="button">
            <span></span><span></span><span></span>
        </button>
        <nav aria-label="<?php echo esc_attr__('Main navigation', 'matrix-starter'); ?>" id="navigation">
            <?php foreach ($menu_links as $link) :
                $classes    = trim((string) ($link['class'] ?? ''));
                $is_current = $matrix_dz_nav_is_current((string) ($link['url'] ?? ''));
                ?>
                <a
                    href="<?php echo esc_url($link['url']); ?>"
                    <?php echo $classes !== '' ? ' class="' . esc_attr($classes) . '"' : ''; ?>
                    <?php echo $is_current ? ' aria-current="page"' : ''; ?>
                ><?php echo esc_html($link['title']); ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
