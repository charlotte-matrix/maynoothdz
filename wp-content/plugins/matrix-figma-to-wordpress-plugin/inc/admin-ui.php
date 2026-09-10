<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Admin menus.
 */
function matrix_db_admin_menu() {
    $jobs_list = 'edit.php?post_type=' . MATRIX_DB_CPT;

    add_menu_page(
        'Figma to WordPress',
        'Figma to WordPress',
        MATRIX_DB_CAP,
        $jobs_list,
        '',
        'dashicons-art',
        58
    );
    add_submenu_page(
        $jobs_list,
        'New build',
        'New build',
        MATRIX_DB_CAP,
        'matrix-figma-to-wordpress-new',
        'matrix_db_render_new_build_page'
    );
    add_submenu_page(
        $jobs_list,
        'Settings',
        'Settings',
        MATRIX_DB_CAP,
        'matrix-figma-to-wordpress-settings',
        'matrix_db_render_settings_page'
    );
}
add_action('admin_menu', 'matrix_db_admin_menu');

/**
 * Rename the auto-added parent submenu item to "All jobs".
 */
function matrix_db_admin_menu_submenu_label() {
    global $submenu;
    $slug = 'edit.php?post_type=' . MATRIX_DB_CPT;
    if (isset($submenu[ $slug ][0][0])) {
        $submenu[ $slug ][0][0] = 'All jobs';
    }
}
add_action('admin_menu', 'matrix_db_admin_menu_submenu_label', 999);

/**
 * Point "Add New" on the jobs list to the batch build form.
 */
function matrix_db_job_list_add_new_url() {
    global $typenow;
    if ($typenow !== MATRIX_DB_CPT) {
        return;
    }
    ?>
    <script>
    (function () {
      var link = document.querySelector('.page-title-action');
      if (link) {
        link.href = <?php echo wp_json_encode(admin_url('admin.php?page=matrix-figma-to-wordpress-new')); ?>;
        link.textContent = 'New build';
      }
    })();
    </script>
    <?php
}
add_action('admin_footer-edit.php', 'matrix_db_job_list_add_new_url');

/**
 * Enqueue admin assets on plugin screens.
 *
 * @param string $hook
 */
function matrix_db_admin_assets($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (! $screen) {
        return;
    }
    $is_ours = strpos($hook, 'matrix-figma-to-wordpress') !== false
        || ($screen->post_type ?? '') === MATRIX_DB_CPT
        || $hook === 'edit-' . MATRIX_DB_CPT;
    if (! $is_ours) {
        return;
    }
    wp_enqueue_style('matrix-db-admin', MATRIX_DB_URL . 'assets/admin.css', array(), MATRIX_DB_VERSION);
    wp_enqueue_script('matrix-db-admin', MATRIX_DB_URL . 'assets/admin.js', array('jquery'), MATRIX_DB_VERSION, true);
    wp_localize_script('matrix-db-admin', 'matrixDbAdmin', array(
        'sectionTypes' => matrix_db_section_types(),
        'features'     => matrix_db_section_feature_registry(),
        'breakpoints'  => matrix_db_breakpoint_presets(),
        'ajaxUrl'      => admin_url('admin-ajax.php'),
        'statusNonce'  => wp_create_nonce('matrix_db_local_status'),
        'jobId'        => ($screen->base ?? '') === 'post' && ($screen->post_type ?? '') === MATRIX_DB_CPT
            ? (int) get_the_ID()
            : 0,
    ));
}
add_action('admin_enqueue_scripts', 'matrix_db_admin_assets');

/**
 * Admin notices.
 */
function matrix_db_admin_notices() {
    if (! empty($_GET['matrix_db_notice'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(wp_unslash($_GET['matrix_db_notice'])) . '</p></div>';
    }
}
add_action('admin_notices', 'matrix_db_admin_notices');

/**
 * New build form.
 */
function matrix_db_render_new_build_page() {
    if (! matrix_db_user_can_manage()) {
        wp_die('Forbidden');
    }
    ?>
    <div class="wrap matrix-db-wrap">
        <h1>New Figma build</h1>
        <p>Paste Figma links or use Figma’s <strong>Copy example prompt</strong> — the wrapper text and <code>@</code> are stripped automatically.</p>
        <div class="notice notice-info inline" style="margin: 1em 0; padding: 1em;">
            <p><strong>Generate (Cloud)</strong> — dispatches a Cursor Cloud Agent (needs API key in Settings). Files appear on a PR branch.</p>
            <p><strong>Generate (Local)</strong> — runs the Cursor agent on this machine from the dashboard (needs API key + Cursor CLI in Settings). When finished, builds Tailwind, seeds/resyncs <code>/flexi/</code>, and marks the job complete.</p>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="matrix-db-build-form">
            <?php wp_nonce_field('matrix_db_create_job'); ?>
            <input type="hidden" name="action" value="matrix_db_create_job" />

            <div id="matrix-db-sections" class="matrix-db-sections">
                <?php matrix_db_render_section_card(0); ?>
            </div>

            <p class="matrix-db-form-actions">
                <button type="button" class="button" id="matrix-db-add-section">+ Add section</button>
            </p>
            <p class="submit">
                <button type="submit" name="runtime" value="cloud" class="button button-primary button-hero">Generate (Cloud)</button>
                <button type="submit" name="runtime" value="local" class="button button-secondary button-hero">Generate (Local)</button>
            </p>
        </form>

        <template id="matrix-db-section-template"><?php ob_start(); matrix_db_render_section_card(99999); echo str_replace('sections[99999]', 'sections[{{INDEX}}]', ob_get_clean()); ?></template>
    </div>
    <?php
}

/**
 * Job detail metabox on edit screen.
 */
function matrix_db_job_metaboxes() {
    add_meta_box(
        'matrix_db_job_sections',
        'Sections & actions',
        'matrix_db_render_job_metabox',
        MATRIX_DB_CPT,
        'normal',
        'high'
    );
    add_meta_box(
        'matrix_db_job_qc',
        'QC',
        'matrix_db_render_qc_metabox',
        MATRIX_DB_CPT,
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'matrix_db_job_metaboxes');

/**
 * @param WP_Post $post
 */
function matrix_db_render_job_metabox($post) {
    $sections = matrix_db_get_sections($post->ID);
    $status   = matrix_db_job_status($post->ID);
    $agent    = get_post_meta($post->ID, MATRIX_DB_AGENT_URL, true);
    $pr       = get_post_meta($post->ID, MATRIX_DB_PR_URL, true);
    $prompt   = get_post_meta($post->ID, MATRIX_DB_PROMPT_META, true);
    $prompt_path = matrix_db_local_prompt_path($post->ID);
    $prompt_file_exists = is_file($prompt_path);
    ?>
    <p><strong>Job status:</strong> <?php echo wp_kses_post(matrix_db_job_status_badge($post->ID)); ?>
        <?php if ($agent) : ?>
            — <a href="<?php echo esc_url($agent); ?>" target="_blank">Agent</a>
        <?php endif; ?>
        <?php if ($pr) : ?>
            — <a href="<?php echo esc_url($pr); ?>" target="_blank">PR</a>
        <?php endif; ?>
    </p>

    <?php if ($status === 'generating_local') : ?>
        <?php $local_progress = matrix_db_local_runner_progress($post->ID); ?>
        <div class="notice notice-info inline matrix-db-local-progress" style="margin: 0 0 1em; padding: 1em;" data-job-id="<?php echo (int) $post->ID; ?>">
            <p><strong>Local agent running…</strong> <span class="matrix-db-elapsed"><?php echo esc_html(sprintf('%d:%02d elapsed', intdiv((int) $local_progress['elapsed'], 60), (int) $local_progress['elapsed'] % 60)); ?></span>
                <?php if (! empty($local_progress['running'])) : ?> · PID <?php echo (int) $local_progress['pid']; ?><?php endif; ?>
            </p>
            <p class="matrix-db-hint description"><?php echo esc_html($local_progress['hint']); ?></p>
            <ul class="matrix-db-file-progress" style="margin-left: 1.2em; list-style: disc;">
                <?php foreach ($local_progress['sections'] as $row) : ?>
                    <li><code><?php echo esc_html($row['layout']); ?></code>
                        <?php echo $row['acf'] && $row['template'] ? '✓ files ready' : '… waiting for files'; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (! empty($local_progress['files_ready'])) : ?>
                <p>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=matrix_db_force_local_complete&job=' . $post->ID), 'matrix_db_job_action')); ?>">Finish &amp; seed /flexi/ now</a>
                    <span class="description">Use if theme files exist but the job is stuck finishing.</span>
                </p>
            <?php endif; ?>
            <p class="description">Log: <code><?php echo esc_html(matrix_db_local_job_log_path($post->ID)); ?></code> (<span class="matrix-db-log-bytes"><?php echo (int) $local_progress['log_bytes']; ?></span> bytes)</p>
            <pre class="matrix-db-local-log" style="max-height: 280px; overflow: auto; background: #1e1e1e; color: #d4d4d4; padding: 12px; font-size: 12px;"><?php echo esc_html(matrix_db_local_runner_log_tail($post->ID)); ?></pre>
        </div>
    <?php endif; ?>

    <?php if ($status === 'queued_local') : ?>
        <div class="notice notice-warning inline" style="margin: 0 0 1em; padding: 1em;">
            <p><strong>Local queue — manual fallback</strong></p>
            <p>Auto-run did not start (check API key, Cursor CLI, and Settings → Local auto-run).</p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=matrix_db_run_local_agent&job=' . $post->ID), 'matrix_db_job_action')); ?>">Run local agent now</a>
            </p>
            <?php if ($prompt_file_exists) : ?>
                <p><code><?php echo esc_html($prompt_path); ?></code></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($status === 'local_failed') : ?>
        <div class="notice notice-error inline" style="margin: 0 0 1em; padding: 1em;">
            <p><strong>Local build failed</strong> — <?php echo esc_html((string) get_post_meta($post->ID, '_matrix_db_local_finish_message', true)); ?></p>
            <p><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=matrix_db_run_local_agent&job=' . $post->ID), 'matrix_db_job_action')); ?>">Retry local agent</a></p>
            <pre class="matrix-db-local-log" style="max-height: 200px; overflow: auto;"><?php echo esc_html(matrix_db_local_runner_log_tail($post->ID)); ?></pre>
        </div>
    <?php endif; ?>

    <?php if ($status === 'done' && $pr) : ?>
        <div class="notice notice-info inline" style="margin: 0 0 1em; padding: 1em;">
            <p><strong>Cloud build finished</strong> — merge the PR and pull the theme into this Local site so PHP/ACF files update here. Then click <strong>Resync on /flexi/</strong> to refresh review-page content and re-import images.</p>
        </div>
    <?php endif; ?>

    <p class="description" style="margin-bottom: 1em;">
        <strong>Generate (Local)</strong> runs the Cursor agent headlessly, then seeds <code>/flexi/</code> automatically.
        <strong>Cloud redo</strong> updates GitHub only until you pull the theme.
    </p>

    <?php if ($status === 'queued_local' && $prompt) : ?>
        <p><textarea class="large-text code" rows="8" readonly><?php echo esc_textarea($prompt); ?></textarea></p>
        <p>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=matrix_db_mark_local_done&job=' . $post->ID), 'matrix_db_job_action')); ?>">Mark local build complete</a>
        </p>
    <?php endif; ?>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Layout</th>
                <th>Type</th>
                <th>Status</th>
                <th>Figma</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sections as $section) : ?>
                <tr>
                    <td><code><?php echo esc_html($section['layout']); ?></code><br><small><?php echo esc_html($section['label']); ?></small></td>
                    <td><?php echo esc_html(matrix_db_get_section_type($section['section_type'] ?? 'flexi')['label']); ?></td>
                    <td><?php echo esc_html($section['status']); ?> (gen <?php echo (int) ($section['generation'] ?? 1); ?>)</td>
                    <td><?php if (! empty($section['figma_url'])) : ?><a href="<?php echo esc_url($section['figma_url']); ?>" target="_blank">open</a><?php endif; ?></td>
                    <td>
                        <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=matrix_db_import_assets&job=' . $post->ID . '&section=' . rawurlencode($section['id'])), 'matrix_db_job_action')); ?>">Import images</a>
                        <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=matrix_db_redo_section_local&job=' . $post->ID . '&section=' . rawurlencode($section['id'])), 'matrix_db_job_action')); ?>">Redo (Local)</a>
                        <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=matrix_db_redo_section&job=' . $post->ID . '&section=' . rawurlencode($section['id'])), 'matrix_db_job_action')); ?>">Redo (Cloud)</a>
                        <?php if (matrix_db_flexi_layout_exists($section['layout'] ?? '')) : ?>
                            <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=matrix_db_resync_flexi&job=' . $post->ID . '&section=' . rawurlencode($section['id'])), 'matrix_db_job_action')); ?>">Resync on /flexi/</a>
                        <?php endif; ?>
                        <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=matrix_db_qc_approve&job=' . $post->ID . '&section=' . rawurlencode($section['id'])), 'matrix_db_job_action')); ?>"<?php echo matrix_db_qc_files_exist($section)['valid'] ? '' : ' title="Files missing — run the build first"'; ?>>Approve</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top:1em;">
        <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=matrix_db_seed_flexi&job=' . $post->ID), 'matrix_db_job_action')); ?>">Add flexi sections to /flexi/</a>
        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=matrix_db_seed_flexi&job=' . $post->ID . '&resync=1'), 'matrix_db_job_action')); ?>">Resync all on /flexi/</a>
        <span class="description">Imports Figma images when a token is set. Resync updates existing rows (heading, logos) without duplicating blocks.</span>
        <a class="button" href="<?php echo esc_url(home_url('/flexi/')); ?>" target="_blank">View /flexi/</a>
    </p>
    <?php
}

/**
 * @param WP_Post $post
 */
function matrix_db_render_qc_metabox($post) {
    $sections = matrix_db_get_sections($post->ID);
    echo '<p>Phase 1: file checks + optional MCP preflight (Local).</p><ul>';
    foreach ($sections as $section) {
        $report = matrix_db_qc_section_report($section);
        $ok     = $report['files']['valid'] ? '✓' : '✗';
        echo '<li>' . esc_html($section['layout']) . ' files ' . esc_html($ok);
        if ($report['preflight']) {
            echo $report['preflight']['valid'] ? ' · preflight ✓' : ' · preflight ✗';
        }
        if ($report['approved']) {
            echo ' · approved';
        }
        if (! empty($report['warnings'])) {
            echo '<br><small style="color:#b45309;">';
            echo esc_html(implode(' ', $report['warnings']));
            echo '</small>';
        }
        echo '</li>';
    }
    echo '</ul>';
    echo '<p><small>Phase 3 roadmap: Figma visual diff, axe auto-run, QC score.</small></p>';
}

/**
 * Create job from new build form.
 */
function matrix_db_handle_create_job() {
    if (! matrix_db_user_can_manage()) {
        wp_die('Forbidden');
    }
    check_admin_referer('matrix_db_create_job');

    $rows = isset($_POST['sections']) && is_array($_POST['sections']) ? $_POST['sections'] : array();
    $sections = matrix_db_normalize_sections_from_post($rows);
    if (empty($sections)) {
        matrix_db_redirect_notice(admin_url('admin.php?page=matrix-figma-to-wordpress-new'), 'Add at least one section with a Figma link or label.');
    }

    $job_id = wp_insert_post(array(
        'post_type'   => MATRIX_DB_CPT,
        'post_status' => 'publish',
        'post_title'  => matrix_db_build_job_title($sections),
    ));
    if (is_wp_error($job_id) || ! $job_id) {
        matrix_db_redirect_notice(admin_url('admin.php?page=matrix-figma-to-wordpress-new'), 'Could not create job.');
    }

    matrix_db_save_sections($job_id, $sections);
    matrix_db_set_job_status($job_id, 'new');

    $runtime = isset($_POST['runtime']) && $_POST['runtime'] === 'local' ? 'local' : 'cloud';
    $res     = matrix_db_dispatch_job($job_id, $runtime);

    if (is_wp_error($res)) {
        matrix_db_redirect_notice(get_edit_post_link($job_id, 'url'), $res->get_error_message());
    }

    $msg = $runtime === 'local'
        ? (! empty($res['auto'])
            ? 'Local agent started. Open the job to watch progress — /flexi/ will be seeded when it finishes.'
            : 'Job queued for manual local build. Open the job and click Run local agent now.')
        : 'Job dispatched to Cursor Cloud Agent.';
    $url = get_edit_post_link($job_id, 'url');
    if ($runtime === 'local' && $url && ! empty($res['auto'])) {
        $url = add_query_arg('matrix_db_local', '1', $url);
    }
    matrix_db_redirect_notice($url, $msg);
}
add_action('admin_post_matrix_db_create_job', 'matrix_db_handle_create_job');

/**
 * Generic job action guard.
 *
 * @return array{job_id:int,section_id:string}
 */
function matrix_db_job_action_params() {
    if (! matrix_db_user_can_manage()) {
        wp_die('Forbidden');
    }
    check_admin_referer('matrix_db_job_action');
    return array(
        'job_id'     => isset($_GET['job']) ? absint($_GET['job']) : 0,
        'section_id' => isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '',
    );
}

function matrix_db_handle_seed_flexi() {
    $p = matrix_db_job_action_params();
    $resync = ! empty($_GET['resync']);
    $seed = matrix_db_seed_job_flexi(
        $p['job_id'],
        array(
            'resync_existing' => $resync,
            'section_id'      => $p['section_id'] !== '' ? $p['section_id'] : null,
        )
    );
    matrix_db_redirect_notice(get_edit_post_link($p['job_id'], 'url'), $seed['message']);
}
add_action('admin_post_matrix_db_seed_flexi', 'matrix_db_handle_seed_flexi');

function matrix_db_handle_resync_flexi() {
    $p = matrix_db_job_action_params();
    if ($p['section_id'] === '') {
        matrix_db_redirect_notice(get_edit_post_link($p['job_id'], 'url'), 'Missing section.');
    }
    $seed = matrix_db_seed_job_flexi(
        $p['job_id'],
        array(
            'resync_existing' => true,
            'section_id'      => $p['section_id'],
        )
    );
    matrix_db_redirect_notice(get_edit_post_link($p['job_id'], 'url'), $seed['message']);
}
add_action('admin_post_matrix_db_resync_flexi', 'matrix_db_handle_resync_flexi');

function matrix_db_handle_redo_section() {
    $p = matrix_db_job_action_params();
    if ($p['section_id'] === '') {
        matrix_db_redirect_notice(get_edit_post_link($p['job_id'], 'url'), 'Missing section.');
    }
    $res = matrix_db_dispatch_redo($p['job_id'], array($p['section_id']), 'cloud', '', true);
    $msg = is_wp_error($res)
        ? $res->get_error_message()
        : 'Redo dispatched to Cloud Agent. After the PR merges, pull the theme locally then Resync on /flexi/.';
    matrix_db_redirect_notice(get_edit_post_link($p['job_id'], 'url'), $msg);
}
add_action('admin_post_matrix_db_redo_section', 'matrix_db_handle_redo_section');

function matrix_db_handle_redo_section_local() {
    $p = matrix_db_job_action_params();
    if ($p['section_id'] === '') {
        matrix_db_redirect_notice(get_edit_post_link($p['job_id'], 'url'), 'Missing section.');
    }
    $res = matrix_db_dispatch_redo($p['job_id'], array($p['section_id']), 'local', '', true);
    $msg = is_wp_error($res)
        ? $res->get_error_message()
        : 'Redo started locally. When the agent finishes, /flexi/ will be resynced automatically.';
    matrix_db_redirect_notice(get_edit_post_link($p['job_id'], 'url'), $msg);
}
add_action('admin_post_matrix_db_redo_section_local', 'matrix_db_handle_redo_section_local');

function matrix_db_handle_import_assets() {
    $p = matrix_db_job_action_params();
    $sections = matrix_db_get_sections($p['job_id']);
    $index = matrix_db_find_section_index($p['job_id'], $p['section_id']);
    if ($index === null) {
        matrix_db_redirect_notice(get_edit_post_link($p['job_id'], 'url'), 'Section not found.');
    }

    $page = get_page_by_path('flexi', OBJECT, 'page');
    $page_id = $page ? (int) $page->ID : 0;
    $import = matrix_db_import_section_assets($sections[ $index ], $page_id);
    if (! empty($import['manifest'])) {
        $sections[ $index ]['figma_assets'] = $import['manifest'];
        matrix_db_save_sections($p['job_id'], $sections);
    } elseif (! empty($import['ids'])) {
        $sections[ $index ]['figma_assets'] = array_map(
            function ($id) {
                return array('attachment_id' => $id);
            },
            $import['ids']
        );
        matrix_db_save_sections($p['job_id'], $sections);
    }

    $msg = empty($import['ids'])
        ? 'No images imported. Add a Figma token in Settings or store MCP asset URLs on the section.'
        : sprintf('Imported %d image(s).', count($import['ids']));
    if (! empty($import['errors'])) {
        $msg .= ' ' . implode('; ', array_slice($import['errors'], 0, 2));
    }
    matrix_db_redirect_notice(get_edit_post_link($p['job_id'], 'url'), $msg);
}
add_action('admin_post_matrix_db_import_assets', 'matrix_db_handle_import_assets');

function matrix_db_handle_qc_approve() {
    $p = matrix_db_job_action_params();
    $sections = matrix_db_get_sections($p['job_id']);
    $index = matrix_db_find_section_index($p['job_id'], $p['section_id']);
    if ($index === null) {
        matrix_db_redirect_notice(get_edit_post_link($p['job_id'], 'url'), 'Section not found.');
    }
    $section = $sections[ $index ];
    $files = matrix_db_qc_files_exist($section);
    if (! $files['valid']) {
        matrix_db_redirect_notice(
            get_edit_post_link($p['job_id'], 'url'),
            'Cannot approve — theme files are missing. Run the build in Cursor first.'
        );
    }
    matrix_db_qc_set_approval($p['job_id'], $p['section_id'], true);
    matrix_db_redirect_notice(get_edit_post_link($p['job_id'], 'url'), 'Section marked approved.');
}
add_action('admin_post_matrix_db_qc_approve', 'matrix_db_handle_qc_approve');

function matrix_db_handle_mark_local_done() {
    $p = matrix_db_job_action_params();
    matrix_db_set_job_status($p['job_id'], 'done');
    $sections = matrix_db_get_sections($p['job_id']);
    foreach ($sections as $i => $section) {
        if (($section['status'] ?? '') === 'queued_local') {
            $sections[ $i ]['status'] = 'done';
        }
    }
    matrix_db_save_sections($p['job_id'], $sections);
    matrix_db_redirect_notice(get_edit_post_link($p['job_id'], 'url'), 'Marked local build complete. Resync on /flexi/ to refresh review content (or Add if not seeded yet).');
}
add_action('admin_post_matrix_db_mark_local_done', 'matrix_db_handle_mark_local_done');

function matrix_db_handle_run_local_agent() {
    $p = matrix_db_job_action_params();
    $res = matrix_db_local_runner_start($p['job_id']);
    $msg = is_wp_error($res) ? $res->get_error_message() : 'Local Cursor agent started.';
    matrix_db_redirect_notice(get_edit_post_link($p['job_id'], 'url'), $msg);
}
add_action('admin_post_matrix_db_run_local_agent', 'matrix_db_handle_run_local_agent');

function matrix_db_handle_force_local_complete() {
    $p = matrix_db_job_action_params();
    $result = matrix_db_local_runner_finish_pipeline($p['job_id']);
    matrix_db_redirect_notice(get_edit_post_link($p['job_id'], 'url'), $result['message']);
}
add_action('admin_post_matrix_db_force_local_complete', 'matrix_db_handle_force_local_complete');

/**
 * List table columns for jobs.
 *
 * @param array<string,string> $columns
 * @return array<string,string>
 */
function matrix_db_job_columns($columns) {
    return array(
        'cb'            => $columns['cb'] ?? '',
        'title'         => 'Job',
        'matrix_db_status' => 'Status',
        'matrix_db_sections' => 'Sections',
        'date'          => $columns['date'] ?? 'Date',
    );
}
add_filter('manage_' . MATRIX_DB_CPT . '_posts_columns', 'matrix_db_job_columns');

/**
 * @param string $column
 * @param int    $post_id
 */
function matrix_db_job_column_render($column, $post_id) {
    if ($column === 'matrix_db_status') {
        echo wp_kses_post(matrix_db_job_status_badge($post_id));
    }
    if ($column === 'matrix_db_sections') {
        echo count(matrix_db_get_sections($post_id));
    }
}
add_action('manage_' . MATRIX_DB_CPT . '_posts_custom_column', 'matrix_db_job_column_render', 10, 2);
