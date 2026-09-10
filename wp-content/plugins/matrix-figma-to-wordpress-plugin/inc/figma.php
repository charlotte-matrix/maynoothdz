<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Strip Figma "Copy example prompt" wrapper text and return a bare figma.com URL.
 *
 * Handles e.g.:
 * - Implement this design from Figma.\n@https://figma.com/...
 * - Implement this design from Figma. Implement this design from Figma.\n@
 *
 * @param string $text
 * @return string
 */
function matrix_db_clean_figma_paste($text) {
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }

    // Normalize line breaks and stray @ prefixes from Figma "Copy example prompt".
    $text = preg_replace("/\r\n?/", "\n", $text);
    $text = preg_replace('/(?:\s*Implement this design from Figma\.?\s*)+/i', ' ', $text);
    $text = preg_replace('/^@+/m', '', $text);
    $text = trim($text);

    if (preg_match('#https?://[^\s\]\)\"\'<>]*figma\.com/[^\s\]\)\"\'<>]+#i', $text, $match)) {
        return rtrim($match[0], '.,;)]\'"');
    }

    return $text;
}

/**
 * Parse a Figma URL (or "Copy example prompt" paste) into file key and node id.
 *
 * @param string $url
 * @return array{url:string,file_key:string,node_id:string}
 */
function matrix_db_parse_figma_url($url) {
    $out = array(
        'url'       => '',
        'file_key'  => '',
        'node_id'   => '',
    );

    $url = matrix_db_clean_figma_paste($url);
    if ($url === '' || strpos($url, 'figma.com') === false) {
        return $out;
    }

    $out['url'] = esc_url_raw($url);
    $parsed     = wp_parse_url($url);

    if (! empty($parsed['path']) &&
        preg_match('#/(?:design|file|make|board|slides|proto)/([A-Za-z0-9]+)#', $parsed['path'], $m)) {
        $out['file_key'] = $m[1];
    }
    if (! empty($parsed['query'])) {
        parse_str($parsed['query'], $q);
        if (! empty($q['node-id'])) {
            $out['node_id'] = str_replace('-', ':', sanitize_text_field($q['node-id']));
        }
    }

    return $out;
}

/**
 * Suggest layout slug from human label.
 *
 * @param string $label
 * @return string
 */
function matrix_db_suggest_layout_slug($label) {
    $slug = sanitize_title($label);
    $slug = str_replace('-', '_', $slug);
    $slug = preg_replace('/[^a-z0-9_]/', '', $slug);
    if ($slug === '' || ! preg_match('/^[a-z]/', $slug)) {
        $slug = 'section_' . wp_generate_password(6, false, false);
    }
    return $slug;
}
