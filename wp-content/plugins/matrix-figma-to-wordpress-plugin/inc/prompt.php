<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Build agent prompt for one or more sections.
 *
 * @param array<int,array<string,mixed>> $sections
 * @param array<string,mixed>            $options
 * @return string
 */
function matrix_db_build_prompt($sections, $options = array()) {
    $overwrite = ! empty($options['overwrite']);
    $is_redo   = ! empty($options['is_redo']);

    $lines   = array();
    $lines[] = 'You are building NEW theme sections for Matrix Starter (WordPress + ACF Builder + Tailwind) from Figma designs.';
    $lines[] = '';
    $lines[] = 'Use the matrix-starter MCP server and Figma MCP when available. Read AGENTS.md and theme://structure before coding.';
    $lines[] = '';
    $lines[] = 'CRITICAL (headless local runs):';
    $lines[] = '- Each section includes a pre-fetched "## Figma design context" block — that is the PRIMARY source of truth.';
    $lines[] = '- When a section lists pre-imported Figma assets (roles like decoration_primary), use those attachments — do not recreate SVG paths.';
    $lines[] = '- Do NOT read or copy from other files in `.cursor/figma-to-wordpress-jobs/` (job-*.md, job-*.log).';
    $lines[] = '- Do NOT assume this section matches a sibling frame (e.g. Child Safeguarding vs About us).';
    $lines[] = '- Implement the exact fileKey + nodeId listed for THIS section only.';
    $lines[] = '';
    $lines[] = 'For EACH section below:';
    $lines[] = '1. Read Section type and use the correct drop-in paths (not always flexi).';
    $lines[] = '2. Read the pre-fetched Figma design context for this section; then Figma MCP get_design_context(fileKey, nodeId) if MCP is available.';
    $lines[] = '3. When tablet/mobile/hover Figma frames are listed, fetch each via get_design_context and implement responsive + hover styles (Tailwind max-* and hover:/group-hover:).';
    $lines[] = '3. find_library_component / get_library_component — use library search hint; reference only, do not wholesale-copy.';
    $lines[] = '4. For flexi/form_block: scaffold_flexi_block { layout, label, source? } then adapt to the design.';
    $lines[] = '5. Implement only canonical drop-in files for that type.';
    $lines[] = '6. For flexi sections: preflight_flexi_block { layout }.';
    $lines[] = '7. Do NOT edit functions.php, add partials, or create loader scripts.';
    if ($overwrite) {
        $lines[] = '8. Overwrite existing files for the layout slug when improving a redo.';
    }
    $lines[] = '';
    $lines[] = 'After all sections: theme_build if Tailwind classes changed.';
    $lines[] = 'When seeding /flexi/, the plugin can auto-import Figma images if a Figma token is configured (or if figma_assets URLs are stored on the section).';
    $lines[] = 'After local file changes, use WP Admin → job → Resync on /flexi/ to refresh seeded content on the review page.';
    $lines[] = '';
    $lines = array_merge($lines, matrix_db_prompt_flexi_standards());
    $lines[] = '';
    if ($is_redo) {
        $lines[] = '## REDO';
        $lines[] = 'Previous implementation did not match the design or theme conventions. Overwrite the layout files completely.';
        $lines[] = 'Re-read Figma via get_design_context and fix: spacing, typography, heading_tag options (h1–h6, p, span), images/logos, and accessibility.';
        $lines[] = 'Checklist: omit hidden Figma layers; use imported SVG decorations (not hand-drawn paths); match Implementation tokens exactly; do not guess opacity.';
        $lines[] = 'Do not leave partial fixes — treat this as a fresh implementation on the same layout slug.';
        $lines[] = '';
    }

    $n = 0;
    foreach ($sections as $section) {
        $n++;
        $type   = matrix_db_get_section_type($section['section_type'] ?? 'flexi');
        $layout = $section['layout'] ?? '';
        $lines[] = '---';
        $lines[] = 'Section ' . $n . ': ' . $layout;
        $lines[] = 'Label: ' . ($section['label'] ?? '');
        $lines[] = 'Type: ' . ($type['label'] ?? 'Flexi');
        $lines[] = 'Drop-in: ' . ($type['drop_in'] ?? '');
        $lines[] = 'Library search hint: ' . ($type['library_hint'] ?? '');
        if (! empty($section['figma_url'])) {
            $lines[] = 'Implement this design from Figma. ' . $section['figma_url'];
        }
        if (! empty($section['figma_file']) && ! empty($section['figma_node'])) {
            $lines[] = 'Figma MCP: fileKey=' . $section['figma_file'] . ' nodeId=' . $section['figma_node'];
        }
        if (! empty($section['instructions'])) {
            $lines[] = 'Instructions: ' . $section['instructions'];
        }
        $lines = array_merge($lines, matrix_db_section_ai_context_lines($section));
        $lines[] = '';
        $lines = array_merge($lines, matrix_db_figma_design_brief_prompt_lines($section));
        $lines = array_merge($lines, matrix_db_figma_view_brief_prompt_lines($section));
        $lines[] = '---';
    }

    return implode("\n", $lines);
}

/**
 * @param int   $job_id
 * @param array<int,string>|null $section_ids
 * @param array<string,mixed>    $options
 * @return string
 */
function matrix_db_build_job_prompt($job_id, $section_ids = null, $options = array()) {
    $sections = matrix_db_get_sections($job_id);
    if ($section_ids !== null) {
        $sections = array_values(array_filter($sections, function ($s) use ($section_ids) {
            return in_array($s['id'] ?? '', $section_ids, true);
        }));
    }
    return matrix_db_build_prompt($sections, $options);
}
