<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Queue a section for redo.
 *
 * @param int    $job_id
 * @param string $section_id
 * @param string $extra_instructions
 * @param bool   $overwrite
 */
function matrix_db_queue_section_redo($job_id, $section_id, $extra_instructions = '', $overwrite = true) {
    $sections = matrix_db_get_sections($job_id);
    $index    = matrix_db_find_section_index($job_id, $section_id);
    if ($index === null) {
        return;
    }

    $section = $sections[ $index ];
    $attempts = isset($section['attempts']) && is_array($section['attempts']) ? $section['attempts'] : array();
    $attempts[] = array(
        'generation' => (int) ($section['generation'] ?? 1),
        'agent_id'   => $section['agent_id'] ?? '',
        'pr_url'     => $section['pr_url'] ?? '',
        'at'         => gmdate('c'),
    );

    $instructions = $section['instructions'] ?? '';
    if ($extra_instructions !== '') {
        $instructions .= ($instructions !== '' ? "\n\n" : '') . 'Redo note: ' . $extra_instructions;
    }

    $sections[ $index ] = array_merge(
        $section,
        array(
            'status'       => 'redo_queued',
            'generation'   => (int) ($section['generation'] ?? 1) + 1,
            'instructions' => $instructions,
            'attempts'     => $attempts,
            'qc_approved'  => false,
            'errors'       => '',
        )
    );
    matrix_db_save_sections($job_id, $sections);
}

/**
 * Dispatch redo for one or more sections.
 *
 * @param int          $job_id
 * @param array<int,string> $section_ids
 * @param string       $runtime
 * @param string       $extra_instructions
 * @param bool         $overwrite
 * @return array<string,mixed>|WP_Error
 */
function matrix_db_dispatch_redo($job_id, $section_ids, $runtime = 'cloud', $extra_instructions = '', $overwrite = true) {
    foreach ($section_ids as $section_id) {
        matrix_db_queue_section_redo($job_id, $section_id, $extra_instructions, $overwrite);
    }

    return matrix_db_dispatch_job(
        $job_id,
        $runtime,
        $section_ids,
        array(
            'overwrite' => $overwrite,
            'is_redo'   => true,
        )
    );
}
