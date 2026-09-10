<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Batch-seed flexi layouts onto the /flexi/ review page.
 *
 * @param array<int,string> $layouts
 * @param bool              $create_page
 * @return array{success:bool,added:array<int,string>,skipped:array<int,string>,missing:array<int,string>,page_id:int,message:string,imported_images:int}
 */
function matrix_db_seed_flexi_layouts($layouts, $create_page = true) {
    $result = array(
        'success'          => false,
        'added'            => array(),
        'skipped'          => array(),
        'missing'          => array(),
        'page_id'          => 0,
        'message'          => '',
        'imported_images'  => 0,
    );

    if (! function_exists('get_field') || ! function_exists('update_field')) {
        $result['message'] = 'ACF is required to seed flexi review rows.';
        return $result;
    }

    $theme = matrix_db_theme_root();
    $page  = get_page_by_path('flexi', OBJECT, 'page');

    if (! $page && $create_page) {
        $page_id = wp_insert_post(
            array(
                'post_title'   => 'Flexi blocks review',
                'post_name'    => 'flexi',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '',
            ),
            true
        );
        if (is_wp_error($page_id)) {
            $result['message'] = $page_id->get_error_message();
            return $result;
        }
        $page = get_post($page_id);
    }

    if (! $page) {
        $result['message'] = 'Flexi review page not found. Enable create page or add a published page with slug flexi.';
        return $result;
    }

    $result['page_id'] = (int) $page->ID;
    $rows              = get_field('flexible_content_blocks', $page->ID);
    if (! is_array($rows)) {
        $rows = array();
    }

    foreach ($layouts as $layout) {
        $layout = sanitize_key($layout);
        if ($layout === '') {
            continue;
        }

        $acf_file = $theme . '/acf-fields/partials/blocks/acf_' . $layout . '.php';
        $tpl_file = $theme . '/template-parts/flexi/' . $layout . '.php';

        if (! is_file($acf_file) || ! is_file($tpl_file)) {
            $result['missing'][] = $layout;
            continue;
        }

        $exists = false;
        foreach ($rows as $row) {
            if (is_array($row) && ($row['acf_fc_layout'] ?? '') === $layout) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            $result['skipped'][] = $layout;
            continue;
        }

        $rows[]            = array('acf_fc_layout' => $layout);
        $result['added'][] = $layout;
    }

    if (! empty($result['added'])) {
        update_field('flexible_content_blocks', $rows, $page->ID);
    }

    $result['success'] = ! empty($result['added']) || ! empty($result['skipped']);
    $result['message'] = sprintf(
        'Added %d, skipped %d existing, %d missing files.',
        count($result['added']),
        count($result['skipped']),
        count($result['missing'])
    );

    return $result;
}

/**
 * Whether a layout row already exists on the /flexi/ review page.
 *
 * @param string $layout
 * @return bool
 */
function matrix_db_flexi_layout_exists($layout) {
    if (! function_exists('get_field')) {
        return false;
    }

    $page = get_page_by_path('flexi', OBJECT, 'page');
    if (! $page) {
        return false;
    }

    $rows = get_field('flexible_content_blocks', $page->ID);
    if (! is_array($rows)) {
        return false;
    }

    $layout = sanitize_key($layout);
    foreach ($rows as $row) {
        if (is_array($row) && ($row['acf_fc_layout'] ?? '') === $layout) {
            return true;
        }
    }

    return false;
}

/**
 * Seed all flexi sections from a build job (with Figma asset import).
 *
 * @param int                 $job_id
 * @param array<string,mixed> $options {
 *     @type bool              $resync_existing Update rows already on /flexi/ instead of skipping.
 *     @type string|null       $section_id      Limit to one section id.
 *     @type array<int,string>  $section_ids     Limit to multiple section ids (redo / partial seed).
 * }
 * @return array<string,mixed>
 */
function matrix_db_seed_job_flexi($job_id, $options = array()) {
    $resync_existing = ! empty($options['resync_existing']);
    $only_section_id = isset($options['section_id']) && $options['section_id'] !== ''
        ? (string) $options['section_id']
        : null;
    $only_section_ids = isset($options['section_ids']) && is_array($options['section_ids'])
        ? array_values(array_filter(array_map('strval', $options['section_ids'])))
        : array();
    if ($only_section_id !== null) {
        $only_section_ids = array($only_section_id);
    }

    $sections = matrix_db_get_sections($job_id);
    $theme    = matrix_db_theme_root();
    $page     = get_page_by_path('flexi', OBJECT, 'page');

    if (! $page) {
        $bootstrap = matrix_db_seed_flexi_layouts(array(), true);
        $page      = get_post($bootstrap['page_id']);
    }

    if (! $page || ! function_exists('get_field') || ! function_exists('update_field')) {
        return array(
            'success' => false,
            'message' => 'Flexi review page or ACF is not available.',
        );
    }

    $rows     = get_field('flexible_content_blocks', $page->ID);
    $rows     = is_array($rows) ? $rows : array();
    $added    = array();
    $resynced = array();
    $skipped  = array();
    $missing  = array();
    $images   = 0;
    $errors   = array();

    foreach ($sections as $si => $section) {
        $section_id = (string) ($section['id'] ?? '');
        if (! empty($only_section_ids) && ! in_array($section_id, $only_section_ids, true)) {
            continue;
        }

        $type = $section['section_type'] ?? 'flexi';
        if (! in_array($type, array('flexi', 'form_block'), true)) {
            continue;
        }

        $layout = sanitize_key($section['layout'] ?? '');
        if ($layout === '') {
            continue;
        }

        $acf_file = $theme . '/acf-fields/partials/blocks/acf_' . $layout . '.php';
        $tpl_file = $theme . '/template-parts/flexi/' . $layout . '.php';
        if (! is_file($acf_file) || ! is_file($tpl_file)) {
            $missing[] = $layout;
            continue;
        }

        $files = matrix_db_qc_files_exist($section);
        if (! $files['valid']) {
            $missing[] = $layout;
            continue;
        }

        $exists_index = null;
        foreach ($rows as $i => $row) {
            if (is_array($row) && ($row['acf_fc_layout'] ?? '') === $layout) {
                $exists_index = $i;
                break;
            }
        }

        $built    = matrix_db_build_flexi_seed_row($section, (int) $page->ID);
        $seed_row = $built['row'];
        $import   = $built['import'];
        if (! empty($import['errors'])) {
            $errors = array_merge($errors, $import['errors']);
        }
        $images += count($import['ids']);

        if (! empty($import['manifest'])) {
            $sections[ $si ]['figma_assets'] = $import['manifest'];
        }

        if ($exists_index !== null) {
            if (! $resync_existing) {
                $skipped[] = $layout;
                continue;
            }

            $rows[ $exists_index ] = matrix_db_merge_flexi_seed_row($rows[ $exists_index ], $seed_row);
            $resynced[]            = $layout;
            $sections[ $si ]['status'] = 'seeded';
            continue;
        }

        $rows[]  = $seed_row;
        $added[] = $layout;
        $sections[ $si ]['status'] = 'seeded';
    }

    if (! empty($added) || ! empty($resynced)) {
        update_field('flexible_content_blocks', $rows, $page->ID);
    }

    matrix_db_save_sections($job_id, $sections);

    $message = sprintf(
        'Added %d, resynced %d, skipped %d existing, %d missing files. Imported %d images.',
        count($added),
        count($resynced),
        count($skipped),
        count($missing),
        $images
    );
    if (! empty($errors)) {
        $message .= ' Import notes: ' . implode('; ', array_slice($errors, 0, 3));
    }

    return array(
        'success'         => ! empty($added) || ! empty($resynced) || ! empty($skipped),
        'added'           => $added,
        'resynced'        => $resynced,
        'skipped'         => $skipped,
        'missing'         => $missing,
        'page_id'         => (int) $page->ID,
        'imported_images' => $images,
        'message'         => $message,
    );
}
