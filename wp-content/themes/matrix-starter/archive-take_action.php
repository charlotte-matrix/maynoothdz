<?php
/**
 * Take Action archive — projects directory pattern without category filters.
 *
 * @package Matrix_Starter
 */

get_header();

$settings = matrix_dz_take_action_archive_settings();
?>
<main id="main-content" class="site-main projects-directory take-action-directory">
    <section class="directory-intro" aria-labelledby="directory-title">
        <div class="container">
            <nav class="breadcrumbs" aria-label="<?php echo esc_attr__('Breadcrumb', 'matrix-starter'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'matrix-starter'); ?></a>
                <span aria-hidden="true">/</span>
                <span aria-current="page"><?php esc_html_e('Take Action', 'matrix-starter'); ?></span>
            </nav>

            <h1 id="directory-title"><?php echo esc_html($settings['title']); ?></h1>

            <?php if ($settings['intro'] !== '') : ?>
                <p class="directory-description"><?php echo esc_html($settings['intro']); ?></p>
            <?php endif; ?>

            <form class="directory-tools" role="search" id="action-search">
                <div class="search-field">
                    <span aria-hidden="true">⌕</span>
                    <label class="sr-only" for="search-actions"><?php echo esc_html($settings['search_placeholder']); ?></label>
                    <input
                        type="search"
                        id="search-actions"
                        name="q"
                        placeholder="<?php echo esc_attr($settings['search_placeholder']); ?>"
                        autocomplete="off"
                    >
                    <button type="button" id="clear-search" aria-label="<?php echo esc_attr__('Clear search', 'matrix-starter'); ?>" hidden>×</button>
                </div>
                <div class="sort-field">
                    <label class="sr-only" for="action-sort"><?php esc_html_e('Sort actions', 'matrix-starter'); ?></label>
                    <select id="action-sort" name="sort">
                        <option value="az"><?php esc_html_e('Name: A–Z', 'matrix-starter'); ?></option>
                        <option value="newest"><?php esc_html_e('Newest first', 'matrix-starter'); ?></option>
                        <option value="oldest"><?php esc_html_e('Oldest first', 'matrix-starter'); ?></option>
                    </select>
                </div>
                <div class="view-switch" role="group" aria-label="<?php echo esc_attr__('Display actions as', 'matrix-starter'); ?>">
                    <button type="button" data-view="cards" aria-pressed="true"><?php esc_html_e('Cards', 'matrix-starter'); ?></button>
                    <button type="button" data-view="list" aria-pressed="false"><?php esc_html_e('List', 'matrix-starter'); ?></button>
                </div>
            </form>

            <div class="filter-row take-action-count-row">
                <p id="result-count" role="status" aria-live="polite" aria-atomic="true"></p>
            </div>
        </div>
    </section>

    <section class="directory-results container" aria-label="<?php echo esc_attr__('Take action results', 'matrix-starter'); ?>">
        <?php if ($settings['sample_note'] !== '') : ?>
            <p class="sample-note"><?php echo esc_html($settings['sample_note']); ?></p>
        <?php endif; ?>

        <div id="action-results" class="project-results"></div>

        <div class="empty-results" id="empty-results" hidden>
            <div class="empty-symbol" aria-hidden="true">⌕</div>
            <div>
                <h2 id="empty-title"><?php esc_html_e('No actions found', 'matrix-starter'); ?></h2>
                <p id="empty-copy"><?php esc_html_e('Try another search, or browse all pathways from the home page.', 'matrix-starter'); ?></p>
            </div>
            <div class="empty-buttons">
                <button class="button outline" id="reset-search" type="button"><?php esc_html_e('Clear search', 'matrix-starter'); ?></button>
                <a class="button" href="<?php echo esc_url(home_url('/#action')); ?>"><?php esc_html_e('See featured actions', 'matrix-starter'); ?></a>
            </div>
        </div>

        <nav class="project-pagination" id="action-pagination" aria-label="<?php echo esc_attr__('Action pages', 'matrix-starter'); ?>"></nav>

        <noscript>
            <p><?php esc_html_e('Please enable JavaScript to browse the Take Action directory.', 'matrix-starter'); ?></p>
        </noscript>
    </section>
</main>
<?php
get_footer();
