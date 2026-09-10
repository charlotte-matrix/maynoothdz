<?php

if (! defined('ABSPATH')) {
    exit;
}

const MATRIX_DB_SECTIONS_META = '_matrix_db_sections';
const MATRIX_DB_RUNTIME_META  = '_matrix_db_runtime';
const MATRIX_DB_JOB_STATUS    = '_matrix_db_job_status';
const MATRIX_DB_AGENT_ID      = '_matrix_db_agent_id';
const MATRIX_DB_AGENT_URL     = '_matrix_db_agent_url';
const MATRIX_DB_PR_URL        = '_matrix_db_pr_url';
const MATRIX_DB_PROMPT_META   = '_matrix_db_prompt';

/**
 * Default section row shape.
 *
 * @return array<string,mixed>
 */
function matrix_db_default_section() {
    return array(
        'id'           => '',
        'figma_url'    => '',
        'figma_file'   => '',
        'figma_node'   => '',
        'label'        => '',
        'section_type' => 'flexi',
        'layout'       => '',
        'instructions' => '',
        'features'     => array(),
        'feature_notes'=> '',
        'figma_views'  => array(
            'tablet' => array('figma_url' => '', 'figma_file' => '', 'figma_node' => ''),
            'mobile' => array('figma_url' => '', 'figma_file' => '', 'figma_node' => ''),
            'hover'  => array('figma_url' => '', 'figma_file' => '', 'figma_node' => ''),
        ),
        'breakpoints'  => matrix_db_default_breakpoints(),
        'status'       => 'queued',
        'generation'   => 1,
        'agent_id'     => '',
        'pr_url'       => '',
        'errors'       => '',
        'qc_approved'  => false,
        'qc_score'     => null,
        'attempts'     => array(),
        'figma_assets' => array(),
    );
}

/**
 * @param int $job_id
 * @return array<int,array<string,mixed>>
 */
function matrix_db_get_sections($job_id) {
    $raw = get_post_meta($job_id, MATRIX_DB_SECTIONS_META, true);
    if (! is_array($raw)) {
        return array();
    }
    return $raw;
}

/**
 * @param int $job_id
 * @param array<int,array<string,mixed>> $sections
 */
function matrix_db_save_sections($job_id, $sections) {
    update_post_meta($job_id, MATRIX_DB_SECTIONS_META, array_values($sections));
}

/**
 * @param int $job_id
 * @param string $section_id
 * @return int|null
 */
function matrix_db_find_section_index($job_id, $section_id) {
    $sections = matrix_db_get_sections($job_id);
    foreach ($sections as $i => $section) {
        if (($section['id'] ?? '') === $section_id) {
            return $i;
        }
    }
    return null;
}

/**
 * @param int $job_id
 * @param string $section_id
 * @param array<string,mixed> $patch
 */
function matrix_db_update_section($job_id, $section_id, $patch) {
    $sections = matrix_db_get_sections($job_id);
    $index    = matrix_db_find_section_index($job_id, $section_id);
    if ($index === null) {
        return;
    }
    $sections[ $index ] = array_merge($sections[ $index ], $patch);
    matrix_db_save_sections($job_id, $sections);
}

/**
 * Normalize posted rows into section array.
 *
 * @param array<int,array<string,string>> $rows
 * @return array<int,array<string,mixed>>
 */
function matrix_db_normalize_sections_from_post($rows) {
    $out = array();
    foreach ($rows as $row) {
        $figma = matrix_db_parse_figma_url($row['figma_url'] ?? '');
        $label = sanitize_text_field($row['label'] ?? '');
        if ($label === '' && $figma['url'] === '') {
            continue;
        }
        $layout = sanitize_key($row['layout'] ?? '');
        if ($layout === '' && $label !== '') {
            $layout = matrix_db_suggest_layout_slug($label);
        }
        $section_type = sanitize_key($row['section_type'] ?? 'flexi');
        if ($section_type === '') {
            $section_type = 'flexi';
        }

        $features = array();
        if (! empty($row['features']) && is_array($row['features'])) {
            $registry = matrix_db_section_feature_registry();
            foreach ($row['features'] as $feature_key) {
                $feature_key = sanitize_key($feature_key);
                if (isset($registry[ $feature_key ])) {
                    $features[] = $feature_key;
                }
            }
        }

        $figma_views = matrix_db_section_figma_views(
            array(
                'figma_views' => is_array($row['figma_views'] ?? null) ? $row['figma_views'] : array(),
            )
        );

        $breakpoints = matrix_db_section_breakpoints(
            array(
                'breakpoints' => is_array($row['breakpoints'] ?? null) ? $row['breakpoints'] : array(),
            )
        );

        $feature_notes = sanitize_textarea_field($row['feature_notes'] ?? '');
        $legacy_notes  = sanitize_textarea_field($row['instructions'] ?? '');
        if ($feature_notes === '' && $legacy_notes !== '') {
            $feature_notes = $legacy_notes;
        }

        $out[] = array_merge(
            matrix_db_default_section(),
            array(
                'id'           => wp_generate_uuid4(),
                'figma_url'    => $figma['url'],
                'figma_file'   => $figma['file_key'],
                'figma_node'   => $figma['node_id'],
                'label'        => $label,
                'section_type' => $section_type,
                'layout'       => $layout,
                'instructions' => $feature_notes,
                'feature_notes'=> $feature_notes,
                'features'     => $features,
                'figma_views'  => $figma_views,
                'breakpoints'  => $breakpoints,
                'status'       => 'queued',
            )
        );
    }
    return $out;
}

/**
 * Human-readable name for one section (flexi layout label or slug).
 *
 * @param array<string,mixed> $section
 * @return string
 */
function matrix_db_section_display_name($section) {
    $label = trim((string) ($section['label'] ?? ''));
    if ($label !== '') {
        return $label;
    }

    $layout = sanitize_key((string) ($section['layout'] ?? ''));
    if ($layout === '') {
        return __('Section', 'matrix-figma-to-wordpress');
    }

    return ucwords(str_replace('_', ' ', $layout));
}

/**
 * Job post title from section layout names (not a generic timestamp).
 *
 * @param array<int,array<string,mixed>> $sections
 * @return string
 */
function matrix_db_build_job_title($sections) {
    $names = array();
    foreach ($sections as $section) {
        $name = matrix_db_section_display_name($section);
        if ($name !== '') {
            $names[] = $name;
        }
    }
    $names = array_values(array_unique($names));

    if (count($names) === 0) {
        return 'Build ' . gmdate('Y-m-d H:i');
    }
    if (count($names) === 1) {
        return $names[0];
    }
    if (count($names) <= 3) {
        return implode(', ', $names);
    }

    return $names[0] . ', ' . $names[1] . ' +' . (count($names) - 2) . ' more';
}

/**
 * @param int $job_id
 * @return string
 */
function matrix_db_job_status($job_id) {
    return (string) get_post_meta($job_id, MATRIX_DB_JOB_STATUS, true);
}

/**
 * @param int $job_id
 * @param string $status
 */
function matrix_db_set_job_status($job_id, $status) {
    update_post_meta($job_id, MATRIX_DB_JOB_STATUS, $status);
}

/**
 * Human-readable job status label.
 *
 * @param string $status
 * @return string
 */
function matrix_db_job_status_label($status) {
    $status = $status !== '' ? $status : 'new';
    $labels = array(
        'new'              => 'New',
        'generating'       => 'Generating (Cloud)',
        'generating_local' => 'Generating (Local)',
        'queued_local'     => 'Queued (Local)',
        'redo_queued'      => 'Redo queued',
        'done'             => 'Done',
        'failed'           => 'Failed (Cloud)',
        'local_failed'     => 'Failed (Local)',
    );

    return $labels[ $status ] ?? ucwords(str_replace('_', ' ', $status));
}

/**
 * CSS modifier class for a job status badge.
 *
 * @param string $status
 * @return string
 */
function matrix_db_job_status_class($status) {
    $status = $status !== '' ? $status : 'new';
    $map    = array(
        'new'              => 'is-new',
        'generating'       => 'is-generating',
        'generating_local' => 'is-generating-local',
        'queued_local'     => 'is-queued',
        'redo_queued'      => 'is-redo',
        'done'             => 'is-done',
        'failed'           => 'is-failed',
        'local_failed'     => 'is-failed',
    );

    return 'matrix-db-status-' . ($map[ $status ] ?? 'is-new');
}

/**
 * Colored status badge HTML for admin lists.
 *
 * @param int $job_id
 * @return string
 */
function matrix_db_job_status_badge($job_id) {
    $status = matrix_db_job_status($job_id);
    if ($status === '') {
        $status = 'new';
    }

    return sprintf(
        '<span class="matrix-db-status %s">%s</span>',
        esc_attr(matrix_db_job_status_class($status)),
        esc_html(matrix_db_job_status_label($status))
    );
}

/**
 * Flexi layouts from completed sections.
 *
 * @param int $job_id
 * @return array<int,string>
 */
function matrix_db_flexi_layouts_for_job($job_id) {
    $layouts = array();
    foreach (matrix_db_get_sections($job_id) as $section) {
        $type = $section['section_type'] ?? 'flexi';
        if (! in_array($type, array('flexi', 'form_block'), true)) {
            continue;
        }
        $layout = $section['layout'] ?? '';
        if ($layout === '') {
            continue;
        }
        $files = matrix_db_qc_files_exist($section);
        if ($files['valid']) {
            $layouts[] = $layout;
        }
    }
    return array_values(array_unique($layouts));
}
