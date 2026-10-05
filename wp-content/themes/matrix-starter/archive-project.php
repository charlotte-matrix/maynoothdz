<?php
/**
 * Project archive — design/website/projects.html directory.
 *
 * @package Matrix_Starter
 */

get_header();

$settings = matrix_dz_projects_archive_settings();
?>
<main id="main-content" class="site-main projects-directory">
    <section class="directory-intro" aria-labelledby="directory-title">
        <div class="container">
            <nav class="breadcrumbs" aria-label="<?php echo esc_attr__('Breadcrumb', 'matrix-starter'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'matrix-starter'); ?></a>
                <span aria-hidden="true">/</span>
                <span aria-current="page"><?php echo esc_html($settings['title']); ?></span>
            </nav>

            <h1 id="directory-title"><?php echo esc_html($settings['title']); ?></h1>

            <?php if ($settings['intro'] !== '') : ?>
                <p class="directory-description"><?php echo esc_html($settings['intro']); ?></p>
            <?php endif; ?>

            <form class="directory-tools" role="search" id="project-search">
                <div class="search-field">
                    <span aria-hidden="true">⌕</span>
                    <label class="sr-only" for="search-projects"><?php echo esc_html($settings['search_placeholder']); ?></label>
                    <input
                        type="search"
                        id="search-projects"
                        name="q"
                        placeholder="<?php echo esc_attr($settings['search_placeholder']); ?>"
                        autocomplete="off"
                    >
                    <button type="button" id="clear-search" aria-label="<?php echo esc_attr__('Clear search', 'matrix-starter'); ?>" hidden>×</button>
                </div>
                <div class="sort-field">
                    <label class="sr-only" for="project-sort"><?php esc_html_e('Sort projects', 'matrix-starter'); ?></label>
                    <select id="project-sort" name="sort">
                        <option value="newest"><?php esc_html_e('Newest first', 'matrix-starter'); ?></option>
                        <option value="oldest"><?php esc_html_e('Oldest first', 'matrix-starter'); ?></option>
                        <option value="az"><?php esc_html_e('Name: A–Z', 'matrix-starter'); ?></option>
                    </select>
                </div>
                <div class="view-switch" role="group" aria-label="<?php echo esc_attr__('Display projects as', 'matrix-starter'); ?>">
                    <button type="button" data-view="cards" aria-pressed="true"><?php esc_html_e('Cards', 'matrix-starter'); ?></button>
                    <button type="button" data-view="list" aria-pressed="false"><?php esc_html_e('List', 'matrix-starter'); ?></button>
                </div>
            </form>

            <div class="filter-row">
                <button
                    type="button"
                    class="filter-toggle"
                    id="filter-toggle"
                    aria-expanded="false"
                    aria-controls="category-filters"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h18v3l-7 7v6l-4-2v-4L3 7Z"/></svg>
                    <span><?php esc_html_e('Filter by category', 'matrix-starter'); ?></span>
                    <span id="active-filter-count" hidden>1</span>
                </button>
                <p id="result-count" role="status" aria-live="polite" aria-atomic="true"></p>
            </div>

            <div
                class="category-filters"
                id="category-filters"
                role="group"
                aria-label="<?php echo esc_attr__('Filter by category', 'matrix-starter'); ?>"
                hidden
            ></div>
        </div>
    </section>

    <section class="directory-results container" aria-label="<?php echo esc_attr__('Project results', 'matrix-starter'); ?>">
        <?php if ($settings['sample_note'] !== '') : ?>
            <p class="sample-note"><?php echo esc_html($settings['sample_note']); ?></p>
        <?php endif; ?>

        <div id="project-results" class="project-results"></div>

        <div class="empty-results" id="empty-results" hidden>
            <div class="empty-symbol" aria-hidden="true">⌕</div>
            <div>
                <h2 id="empty-title"><?php esc_html_e('No projects found', 'matrix-starter'); ?></h2>
                <p id="empty-copy"><?php esc_html_e('Try another search or clear your filters — or explore Maynooth on the map.', 'matrix-starter'); ?></p>
            </div>
            <div class="empty-buttons">
                <button class="button outline" id="reset-filters" type="button"><?php esc_html_e('Clear filters', 'matrix-starter'); ?></button>
                <a class="button" id="empty-map-link" href="<?php echo esc_url($settings['map_url']); ?>"><?php esc_html_e('See the map', 'matrix-starter'); ?></a>
            </div>
        </div>

        <nav class="project-pagination" id="project-pagination" aria-label="<?php echo esc_attr__('Project pages', 'matrix-starter'); ?>"></nav>

        <noscript>
            <p><?php esc_html_e('Please enable JavaScript to browse and filter the project directory.', 'matrix-starter'); ?></p>
        </noscript>
    </section>
</main>
<?php
get_footer();
