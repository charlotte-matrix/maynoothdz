<?php
/**
 * Template Name: Map
 * Template Post Type: page
 *
 * Interactive project map — design/website/map.html
 *
 * @package Matrix_Starter
 */

get_header();

$map_data      = function_exists('matrix_dz_projects_map_data') ? matrix_dz_projects_map_data() : [];
$map_image     = (string) ($map_data['mapImage'] ?? matrix_dz_assets_url('maynooth-map.webp'));
$projects_url  = (string) ($map_data['projectsUrl'] ?? home_url('/projects/'));
$page_title    = get_the_title() ?: __('Explore the Maynooth Decarbonising Zone', 'matrix-starter');
$intro         = (string) get_the_excerpt();
if ($intro === '') {
    $intro = __('Filter by theme, choose a pin and discover local climate action.', 'matrix-starter');
}
?>
<main id="main-content" class="site-main">
    <section class="map-intro">
        <div class="container">
            <nav class="breadcrumbs" aria-label="<?php echo esc_attr__('Breadcrumb', 'matrix-starter'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'matrix-starter'); ?></a>
                <span aria-hidden="true">/</span>
                <span aria-current="page"><?php esc_html_e('Map', 'matrix-starter'); ?></span>
            </nav>

            <div class="map-heading">
                <div>
                    <h1><?php echo esc_html($page_title); ?></h1>
                    <p><?php echo esc_html($intro); ?></p>
                </div>
                <a class="directory-shortcut" href="<?php echo esc_url($projects_url); ?>">
                    <span><?php esc_html_e('Prefer a list?', 'matrix-starter'); ?></span>
                    <strong><?php esc_html_e('Browse the project directory →', 'matrix-starter'); ?></strong>
                </a>
            </div>

            <div class="map-filter-row">
                <button
                    type="button"
                    class="filter-toggle"
                    id="map-filter-toggle"
                    aria-expanded="false"
                    aria-controls="map-category-filters"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h18v3l-7 7v6l-4-2v-4L3 7Z"/></svg>
                    <span><?php esc_html_e('Filter by category', 'matrix-starter'); ?></span>
                    <span class="map-active-filter" hidden>1</span>
                </button>
                <p id="map-count" role="status" aria-live="polite"></p>
            </div>
            <div class="category-filters" id="map-category-filters" role="group" aria-label="<?php echo esc_attr__('Filter by category', 'matrix-starter'); ?>" hidden></div>
        </div>
    </section>

    <section class="interactive-map" aria-label="<?php echo esc_attr__('Interactive project map', 'matrix-starter'); ?>">
        <div
            class="map-surface"
            id="map-surface"
            tabindex="0"
            aria-label="<?php echo esc_attr__('Maynooth map. Use arrow keys to pan, plus and minus to zoom, or Tab to explore project pins.', 'matrix-starter'); ?>"
        >
            <div class="map-stage" id="map-stage">
                <img
                    class="town-map-image"
                    src="<?php echo esc_url($map_image); ?>"
                    alt="<?php echo esc_attr__('Illustrated aerial map of Maynooth', 'matrix-starter'); ?>"
                    width="3600"
                    height="2025"
                    draggable="false"
                    decoding="async"
                    fetchpriority="high"
                >
                <div id="project-pins"></div>
            </div>
        </div>
        <div class="map-navigation" role="group" aria-label="<?php echo esc_attr__('Map navigation', 'matrix-starter'); ?>">
            <button type="button" id="map-zoom-in" aria-label="<?php echo esc_attr__('Zoom in', 'matrix-starter'); ?>">+</button>
            <button type="button" id="map-zoom-out" aria-label="<?php echo esc_attr__('Zoom out', 'matrix-starter'); ?>">−</button>
            <button type="button" id="map-reset" aria-label="<?php echo esc_attr__('Reset map view', 'matrix-starter'); ?>">↺</button>
            <output id="map-zoom-level" aria-label="<?php echo esc_attr__('Zoom level', 'matrix-starter'); ?>">100%</output>
        </div>
        <p class="map-hint"><?php esc_html_e('Drag to explore · Zoom with + / −', 'matrix-starter'); ?></p>
        <aside id="map-project-card" class="map-project-card" aria-label="<?php echo esc_attr__('Selected project', 'matrix-starter'); ?>" hidden></aside>
        <noscript>
            <img class="map-fallback" src="<?php echo esc_url($map_image); ?>" alt="<?php echo esc_attr__('Illustrated map of Maynooth', 'matrix-starter'); ?>">
            <p class="map-nojs">
                <?php
                echo wp_kses(
                    sprintf(
                        /* translators: %s: projects directory URL */
                        __('Enable JavaScript to explore the pins, or <a href="%s">browse the project directory</a>.', 'matrix-starter'),
                        esc_url($projects_url)
                    ),
                    ['a' => ['href' => true]]
                );
                ?>
            </p>
        </noscript>
    </section>

    <section class="map-key-section container" aria-labelledby="map-key-title">
        <div class="map-key">
            <h2 id="map-key-title"><?php esc_html_e('Map legend', 'matrix-starter'); ?></h2>
            <div id="map-legend"></div>
            <p><?php esc_html_e('Select a pin to discover its project. Filter by category to explore a theme.', 'matrix-starter'); ?></p>
        </div>
        <p class="map-demo-note"><?php esc_html_e('Demonstration map · Pin locations are illustrative and await confirmation.', 'matrix-starter'); ?></p>
    </section>
</main>
<?php
get_footer();
