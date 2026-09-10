<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @param string $url
 * @return bool
 */
function matrix_db_is_figma_mcp_asset_url($url) {
    return is_string($url) && strpos($url, 'figma.com/api/mcp/asset/') !== false;
}

/**
 * Discover asset manifest for a section (stored meta or Figma API).
 *
 * @param array<string,mixed> $section
 * @return array<int,array<string,mixed>>|WP_Error
 */
function matrix_db_section_asset_manifest($section) {
    $stored = $section['figma_assets'] ?? array();
    if (is_array($stored) && ! empty($stored)) {
        $has_manifest = false;
        foreach ($stored as $item) {
            if (is_array($item) && (! empty($item['role']) || ! empty($item['node_id']))) {
                $has_manifest = true;
                break;
            }
        }
        if ($has_manifest) {
            return $stored;
        }
    }

    $file = (string) ($section['figma_file'] ?? '');
    $node = (string) ($section['figma_node'] ?? '');
    if ($file === '' && ! empty($section['figma_url'])) {
        $parsed = matrix_db_parse_figma_url($section['figma_url']);
        $file   = $parsed['file_key'];
        $node   = $parsed['node_id'];
    }

    if ($file !== '' && $node !== '' && matrix_db_figma_token() !== '') {
        return matrix_db_figma_discover_asset_manifest($file, $node);
    }

    return array();
}

/**
 * Collect asset URLs for a section (stored meta, MCP URLs, or Figma API).
 *
 * @param array<string,mixed> $section
 * @return array<int,string>|WP_Error
 */
function matrix_db_section_asset_urls($section) {
    $manifest = matrix_db_section_asset_manifest($section);
    if (is_wp_error($manifest)) {
        return $manifest;
    }

    if (! empty($manifest)) {
        $urls = array();
        foreach ($manifest as $item) {
            if (is_string($item) && $item !== '') {
                $urls[] = $item;
                continue;
            }
            if (is_array($item) && ! empty($item['url'])) {
                $urls[] = (string) $item['url'];
            }
        }
        if (! empty($urls)) {
            return array_values(array_unique($urls));
        }
    }

    return array();
}

/**
 * Detect extension from response bytes / URL.
 *
 * @param string $bytes
 * @param string $url
 * @return string
 */
function matrix_db_guess_image_extension($bytes, $url) {
    if (strncmp($bytes, '<svg', 4) === 0 || strpos($bytes, '<svg') !== false) {
        return 'svg';
    }
    if (strncmp($bytes, "\x89PNG", 4) === 0) {
        return 'png';
    }
    if (strncmp($bytes, "\xFF\xD8\xFF", 3) === 0) {
        return 'jpg';
    }
    if (stripos($url, '.svg') !== false) {
        return 'svg';
    }
    return 'png';
}

/**
 * Sideload a remote image (PNG/JPG/SVG) into the media library.
 *
 * @param string $url
 * @param int    $parent_post_id
 * @param string $filename_base
 * @param string $alt
 * @return int|WP_Error Attachment ID.
 */
function matrix_db_sideload_figma_asset($url, $parent_post_id = 0, $filename_base = 'figma-asset', $alt = '') {
    if (! function_exists('download_url') || ! function_exists('media_handle_sideload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $url = esc_url_raw($url);
    if ($url === '') {
        return new WP_Error('invalid_url', 'Empty asset URL.');
    }

    $tmp = download_url($url, 30);
    if (is_wp_error($tmp)) {
        return $tmp;
    }

    $bytes = (string) file_get_contents($tmp);
    $ext   = matrix_db_guess_image_extension($bytes, $url);
    $name  = sanitize_file_name($filename_base . '.' . $ext);

    $file_array = array(
        'name'     => $name,
        'tmp_name' => $tmp,
    );

    $attachment_id = media_handle_sideload($file_array, $parent_post_id);
    if (is_wp_error($attachment_id)) {
        @unlink($tmp);
        return $attachment_id;
    }

    if ($alt !== '') {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
    }

    return (int) $attachment_id;
}

/**
 * Import all section assets and return attachment IDs + manifest.
 *
 * @param array<string,mixed> $section
 * @param int                 $parent_post_id
 * @return array{ids:array<int,int>,manifest:array<int,array<string,mixed>>,errors:array<int,string>}
 */
function matrix_db_import_section_assets($section, $parent_post_id = 0) {
    $result = array(
        'ids'      => array(),
        'manifest' => array(),
        'errors'   => array(),
    );

    $manifest = matrix_db_section_asset_manifest($section);
    if (is_wp_error($manifest)) {
        $result['errors'][] = $manifest->get_error_message();
        return $result;
    }

    $layout = sanitize_key($section['layout'] ?? 'asset');

    if (empty($manifest)) {
        $urls = matrix_db_section_asset_urls($section);
        if (is_wp_error($urls)) {
            $result['errors'][] = $urls->get_error_message();
            return $result;
        }
        foreach ($urls as $index => $url) {
            $manifest[] = array(
                'url'  => $url,
                'role' => 'asset',
                'name' => sprintf('%s %d', $layout, $index + 1),
            );
        }
    }

    foreach ($manifest as $index => $item) {
        if (! is_array($item)) {
            continue;
        }

        if (! empty($item['attachment_id']) && (int) $item['attachment_id'] > 0) {
            $attachment_id = (int) $item['attachment_id'];
            $result['ids'][] = $attachment_id;
            $item['attachment_id'] = $attachment_id;
            $result['manifest'][] = $item;
            continue;
        }

        $url = (string) ($item['url'] ?? '');
        if ($url === '') {
            continue;
        }

        $role = sanitize_key((string) ($item['role'] ?? 'asset'));
        $name = sanitize_file_name((string) ($item['name'] ?? ($role !== '' ? $role : 'asset-' . ($index + 1))));
        $base = $layout . '-' . ($name !== '' ? $name : ($index + 1));

        $id = matrix_db_sideload_figma_asset(
            $url,
            $parent_post_id,
            $base,
            sprintf('%s %s', $section['label'] ?? $layout, $item['name'] ?? $role)
        );
        if (is_wp_error($id)) {
            $result['errors'][] = $id->get_error_message();
            continue;
        }

        $result['ids'][] = $id;
        $item['attachment_id'] = $id;
        $result['manifest'][]  = $item;
    }

    return $result;
}

/**
 * Map imported manifest entries onto flexi seed row fields by role.
 *
 * @param array<string,mixed>              $row
 * @param array<int,array<string,mixed>>   $manifest
 * @return array<string,mixed>
 */
function matrix_db_apply_asset_manifest_to_row($row, $manifest) {
    if (empty($manifest)) {
        return $row;
    }

    $logos = array();
    foreach ($manifest as $item) {
        if (! is_array($item)) {
            continue;
        }
        $attachment_id = (int) ($item['attachment_id'] ?? 0);
        if ($attachment_id <= 0) {
            continue;
        }

        $role = (string) ($item['role'] ?? '');
        switch ($role) {
            case 'decoration_primary':
                if (empty($row['decoration_primary'])) {
                    $row['decoration_primary'] = $attachment_id;
                }
                break;
            case 'decoration_secondary':
                if (empty($row['decoration_secondary'])) {
                    $row['decoration_secondary'] = $attachment_id;
                }
                break;
            case 'image':
                if (empty($row['image'])) {
                    $row['image'] = $attachment_id;
                }
                break;
            case 'logo':
                $logos[] = array('logo_image' => $attachment_id);
                break;
            default:
                break;
        }
    }

    if (! empty($logos) && empty($row['partner_logos'])) {
        $row['partner_logos'] = $logos;
    }

    return $row;
}

/**
 * Repeater field keys that must be replaced (not shallow-merged) on resync.
 *
 * @return array<int,string>
 */
function matrix_db_flexi_repeater_keys() {
    return apply_filters('matrix_db_flexi_repeater_keys', array('partner_logos', 'key_points'));
}

/**
 * Merge an existing flexi row with freshly seeded values (repeaters replaced).
 *
 * @param array<string,mixed> $existing
 * @param array<string,mixed> $seed_row
 * @return array<string,mixed>
 */
function matrix_db_merge_flexi_seed_row($existing, $seed_row) {
    $merged = array_merge($existing, $seed_row);
    foreach (matrix_db_flexi_repeater_keys() as $key) {
        if (array_key_exists($key, $seed_row) && is_array($seed_row[ $key ])) {
            $merged[ $key ] = $seed_row[ $key ];
        }
    }

    return $merged;
}

/**
 * Default key_points rows for stats / counter flexi blocks.
 *
 * @return array<int,array<string,mixed>>
 */
function matrix_db_default_key_points_rows() {
    return array(
        array(
            'stat_value'       => '95%',
            'animate_counter'  => 1,
            'counter_value'    => 95,
            'counter_format'   => 'number',
            'counter_prefix'   => '',
            'counter_suffix'   => '%',
            'title'            => 'Key point text',
            'title_tag'        => 'h3',
            'copy'             => 'Lorem ipsum dolor sit ametsed do eiusmod tempor incididunt',
        ),
        array(
            'stat_value'       => '75k',
            'animate_counter'  => 1,
            'counter_value'    => 75000,
            'counter_format'   => 'compact',
            'counter_prefix'   => '',
            'counter_suffix'   => '',
            'title'            => 'Key point text',
            'title_tag'        => 'h3',
            'copy'             => 'Lorem ipsum dolor sit ametsed do eiusmod tempor incididunt',
        ),
        array(
            'stat_value'       => '455',
            'animate_counter'  => 1,
            'counter_value'    => 455,
            'counter_format'   => 'number',
            'counter_prefix'   => '',
            'counter_suffix'   => '',
            'title'            => 'Key point text',
            'title_tag'        => 'h3',
            'copy'             => 'Lorem ipsum dolor sit ametsed do eiusmod tempor incididunt',
        ),
    );
}

/**
 * Build a flexi seed row with defaults + imported assets.
 *
 * @param array<string,mixed> $section
 * @param int                 $page_id
 * @return array{row:array<string,mixed>,import:array{ids:array<int,int>,manifest:array<int,array<string,mixed>>,errors:array<int,string>}}
 */
function matrix_db_build_flexi_seed_row($section, $page_id) {
    $layout = sanitize_key($section['layout'] ?? '');
    $row    = array(
        'acf_fc_layout' => $layout,
    );

    $import = matrix_db_import_section_assets($section, $page_id);
    $ids    = $import['ids'];

    $row = apply_filters('matrix_db_flexi_seed_row', $row, $section, $ids, $page_id, $import['manifest']);

    $row = matrix_db_apply_asset_manifest_to_row($row, $import['manifest']);

    if (! empty($ids) && empty($row['partner_logos'])) {
        $row['partner_logos'] = array();
        foreach ($ids as $id) {
            $row['partner_logos'][] = array(
                'logo_image' => $id,
            );
        }
    }

    $acf_path = matrix_db_theme_root() . '/acf-fields/partials/blocks/acf_' . $layout . '.php';
    if (is_file($acf_path) && strpos((string) file_get_contents($acf_path), "'key_points'") !== false && empty($row['key_points'])) {
        $row['key_points'] = matrix_db_default_key_points_rows();
    }

    if (empty($row['heading_text']) && ! empty($section['label'])) {
        $row['heading_text'] = $section['label'];
    }

    if (empty($row['heading_tag'])) {
        $row['heading_tag'] = 'p';
    }

    return array(
        'row'    => $row,
        'import' => $import,
    );
}

/**
 * Default seed mapping for partners-style flexi blocks.
 *
 * @param array<string,mixed>            $row
 * @param array<string,mixed>            $section
 * @param array<int,int>                 $attachment_ids
 * @param int                            $page_id
 * @param array<int,array<string,mixed>> $manifest
 * @return array<string,mixed>
 */
function matrix_db_default_flexi_seed_row($row, $section, $attachment_ids, $page_id, $manifest = array()) {
    unset($page_id, $section);

    if (($row['acf_fc_layout'] ?? '') === 'test') {
        if (empty($row['heading_text'])) {
            $row['heading_text'] = 'Committed to quality care, human rights, and innovation';
        }
        $row['heading_tag'] = $row['heading_tag'] ?? 'p';
    }

    if (in_array($row['acf_fc_layout'] ?? '', array('test2', 'test3', 'tester'), true) && empty($row['key_points'])) {
        $row['key_points'] = matrix_db_default_key_points_rows();
        $row['background_color'] = $row['background_color'] ?? '#024b79';
    }

  // Decoration mapping handled by matrix_db_apply_asset_manifest_to_row; legacy fallback:
    if (in_array($row['acf_fc_layout'] ?? '', array('test2', 'test3', 'tester'), true)) {
        if (empty($row['decoration_primary']) && ! empty($attachment_ids[0])) {
            $row['decoration_primary'] = $attachment_ids[0];
        }
        if (empty($row['decoration_secondary']) && ! empty($attachment_ids[1])) {
            $row['decoration_secondary'] = $attachment_ids[1];
        }
    }

    if (($row['acf_fc_layout'] ?? '') === 'reversible_content') {
        if (empty($row['heading'])) {
            $row['heading'] = 'About us';
        }
        if (empty($row['body'])) {
            $row['body'] = '<p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>';
        }
        if (empty($row['primary_button'])) {
            $row['primary_button'] = array(
                'title'  => 'About us',
                'url'    => '#',
                'target' => '',
            );
        }
        if (empty($row['secondary_button'])) {
            $row['secondary_button'] = array(
                'title'  => 'Careers',
                'url'    => '#',
                'target' => '',
            );
        }
        if (! empty($attachment_ids[0]) && empty($row['image'])) {
            $row['image'] = $attachment_ids[0];
        }
    }

    if (in_array($row['acf_fc_layout'] ?? '', array('content', 'reversible_content'), true) && ! empty($attachment_ids[0]) && empty($row['image'])) {
        $row['image'] = $attachment_ids[0];
    }

    return $row;
}
add_filter('matrix_db_flexi_seed_row', 'matrix_db_default_flexi_seed_row', 10, 5);
