<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Check whether theme files exist for a section.
 *
 * @param array<string,mixed> $section
 * @return array{valid:bool,missing:array<int,string>,paths:array{acf:string,template:string}}
 */
function matrix_db_qc_files_exist($section) {
    $paths   = matrix_db_section_theme_paths($section);
    $theme   = matrix_db_theme_root();
    $missing = array();

    if ($paths['acf'] !== '' && ! is_file($theme . '/' . $paths['acf'])) {
        $missing[] = $paths['acf'];
    }
    if ($paths['template'] !== '' && ! is_file($theme . '/' . $paths['template'])) {
        $missing[] = $paths['template'];
    }

    return array(
        'valid'   => empty($missing),
        'missing' => $missing,
        'paths'   => $paths,
    );
}

/**
 * Run MCP preflight CLI for a flexi layout (Local only).
 *
 * @param string $layout
 * @return array{ran:bool,valid:bool,output:string}
 */
function matrix_db_qc_run_preflight_cli($layout) {
    $theme = matrix_db_theme_root();
    $cli   = $theme . '/mcp-server/dist/cli.js';
    $out   = array(
        'ran'    => false,
        'valid'  => false,
        'output' => '',
    );

    if (! is_file($cli)) {
        $out['output'] = 'MCP CLI not built. Run: cd mcp-server && npm run build';
        return $out;
    }

    $cmd = sprintf(
        'node %s preflight-flexi --layout=%s 2>&1',
        escapeshellarg($cli),
        escapeshellarg($layout)
    );

    $output = array();
    $code   = 0;
    exec($cmd, $output, $code);
    $out['ran']    = true;
    $out['valid']  = ($code === 0);
    $out['output'] = implode("\n", $output);

    return $out;
}

/**
 * Extract hex colours from a design brief string.
 *
 * @param string $brief
 * @return array<int,string>
 */
function matrix_db_qc_extract_brief_hex_colors($brief) {
    if ($brief === '') {
        return array();
    }

    preg_match_all('/#[0-9a-fA-F]{6}\b/', $brief, $matches);
    $colors = array();
    foreach ($matches[0] ?? array() as $hex) {
        $colors[] = strtolower($hex);
    }

    return array_values(array_unique($colors));
}

/**
 * Light fidelity warnings (non-blocking).
 *
 * @param array<string,mixed> $section
 * @return array<int,string>
 */
function matrix_db_qc_fidelity_warnings($section) {
    $warnings = array();
    $files    = matrix_db_qc_files_exist($section);
    if (! $files['valid'] || $files['paths']['template'] === '') {
        return $warnings;
    }

    $theme    = matrix_db_theme_root();
    $template = (string) file_get_contents($theme . '/' . $files['paths']['template']);
    $acf_path = $theme . '/' . $files['paths']['acf'];
    $acf_src  = is_file($acf_path) ? (string) file_get_contents($acf_path) : '';

    $file_key = (string) ($section['figma_file'] ?? '');
    $node_id  = (string) ($section['figma_node'] ?? '');
    if ($file_key === '' && ! empty($section['figma_url'])) {
        $parsed   = matrix_db_parse_figma_url($section['figma_url']);
        $file_key = $parsed['file_key'];
        $node_id  = $parsed['node_id'];
    }

    if ($file_key !== '' && $node_id !== '' && matrix_db_figma_token() !== '') {
        $brief = matrix_db_figma_design_brief($file_key, $node_id);
        if (! is_wp_error($brief)) {
            $colors = matrix_db_qc_extract_brief_hex_colors((string) $brief);
            $missing_colors = array();
            foreach ($colors as $hex) {
                if (stripos($template, $hex) === false && stripos($template, strtoupper($hex)) === false) {
                    $missing_colors[] = $hex;
                }
            }
            if (! empty($missing_colors) && count($missing_colors) >= min(2, count($colors))) {
                $warnings[] = 'Template may be missing brief colours: ' . implode(', ', array_slice($missing_colors, 0, 4));
            }

            if (strpos((string) $brief, 'Excluded (hidden in Figma') !== false) {
                preg_match_all('/Excluded \(hidden in Figma[^:]*:\s*(.*?)(?:\n\n|\z)/s', (string) $brief, $hidden_block);
                if (! empty($hidden_block[1][0])) {
                    preg_match_all('/"([^"]+)"/', $hidden_block[1][0], $hidden_names);
                    foreach ($hidden_names[1] ?? array() as $hidden_name) {
                        if ($hidden_name !== '' && stripos($template, $hidden_name) !== false) {
                            $warnings[] = 'Template references hidden Figma layer name: "' . $hidden_name . '"';
                        }
                    }
                }
            }
        }
    }

    if (strpos($acf_src, "'decoration_primary'") !== false || strpos($acf_src, "'decoration_secondary'") !== false) {
        if (strpos($template, 'matrix_render_attachment_image') === false) {
            $warnings[] = 'Decoration ACF fields exist but template does not use matrix_render_attachment_image().';
        }
        if (preg_match('/<svg[^>]*>[\s\S]{200,}<\/svg>/', $template)) {
            $warnings[] = 'Template contains inline SVG markup — prefer Figma-exported attachments for decorations.';
        }
        if (preg_match('/opacity-(?:\d+|\[[^\]]+\])/', $template) && strpos($template, 'decoration_') !== false) {
            $warnings[] = 'Decoration wrapper uses CSS opacity — verify SVG path opacity is not double-applied.';
        }
    }

    return $warnings;
}

/**
 * QC summary for a section.
 *
 * @param array<string,mixed> $section
 * @return array<string,mixed>
 */
function matrix_db_qc_section_report($section) {
    $files = matrix_db_qc_files_exist($section);
    $report = array(
        'files'     => $files,
        'preflight' => null,
        'warnings'  => matrix_db_qc_fidelity_warnings($section),
        'approved'  => ! empty($section['qc_approved']),
        'flexi_url' => home_url('/flexi/'),
    );

    $type = $section['section_type'] ?? 'flexi';
    if (in_array($type, array('flexi', 'form_block'), true) && ($section['layout'] ?? '') !== '') {
        $report['preflight'] = matrix_db_qc_run_preflight_cli($section['layout']);
    }

    return $report;
}

/**
 * Mark section QC approved or rejected.
 *
 * @param int    $job_id
 * @param string $section_id
 * @param bool   $approved
 */
function matrix_db_qc_set_approval($job_id, $section_id, $approved) {
    matrix_db_update_section(
        $job_id,
        $section_id,
        array(
            'qc_approved' => $approved,
            'status'      => $approved ? 'approved' : 'needs_qc',
        )
    );
}
