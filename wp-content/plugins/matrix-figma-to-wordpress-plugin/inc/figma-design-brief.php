<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Convert Figma 0–1 RGB(A) to #rrggbb.
 *
 * @param array<string,mixed> $color
 * @return string
 */
function matrix_db_figma_rgb_to_hex($color) {
    if (! is_array($color)) {
        return '';
    }

    $r = (int) round(((float) ($color['r'] ?? 0)) * 255);
    $g = (int) round(((float) ($color['g'] ?? 0)) * 255);
    $b = (int) round(((float) ($color['b'] ?? 0)) * 255);

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/**
 * @param array<string,mixed> $node
 * @return string
 */
function matrix_db_figma_node_fill_color($node) {
    foreach ($node['fills'] ?? array() as $fill) {
        if (! is_array($fill) || ($fill['visible'] ?? true) === false) {
            continue;
        }
        if (($fill['type'] ?? '') === 'SOLID' && ! empty($fill['color'])) {
            return matrix_db_figma_rgb_to_hex($fill['color']);
        }
        if (($fill['type'] ?? '') === 'GRADIENT_LINEAR') {
            return 'gradient-linear';
        }
    }

    return '';
}

/**
 * @param array<string,mixed> $node
 * @return string
 */
function matrix_db_figma_node_size_line($node) {
    $box = $node['absoluteBoundingBox'] ?? null;
    if (! is_array($box)) {
        return '';
    }

    $w = isset($box['width']) ? round((float) $box['width']) : 0;
    $h = isset($box['height']) ? round((float) $box['height']) : 0;
    if ($w <= 0 && $h <= 0) {
        return '';
    }

    return $w . '×' . $h . 'px';
}

/**
 * @param array<string,mixed> $node
 * @return string
 */
function matrix_db_figma_node_layout_line($node) {
    $parts = array();
    $mode  = (string) ($node['layoutMode'] ?? '');
    if ($mode !== '' && $mode !== 'NONE') {
        $parts[] = 'layout=' . strtolower($mode);
    }
    if (isset($node['itemSpacing'])) {
        $parts[] = 'gap=' . (int) $node['itemSpacing'] . 'px';
    }
    foreach (array('paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft') as $key) {
        if (isset($node[ $key ]) && (int) $node[ $key ] > 0) {
            $parts[] = str_replace('padding', 'pad', $key) . '=' . (int) $node[ $key ];
        }
    }

    return implode(', ', $parts);
}

/**
 * Opacity token for Tailwind (arbitrary when needed).
 *
 * @param float $opacity
 * @return string
 */
function matrix_db_figma_opacity_token($opacity) {
    $opacity = round($opacity, 4);
    if ($opacity >= 0.99) {
        return '';
    }

    $map = array(
        0.03 => 'opacity-[0.03]',
        0.05 => 'opacity-5',
        0.1  => 'opacity-10',
        0.2  => 'opacity-20',
        0.3  => 'opacity-30',
        0.4  => 'opacity-40',
        0.5  => 'opacity-50',
        0.6  => 'opacity-60',
        0.7  => 'opacity-70',
        0.8  => 'opacity-80',
        0.9  => 'opacity-90',
    );

    foreach ($map as $value => $token) {
        if (abs($opacity - $value) < 0.005) {
            return $token;
        }
    }

    return 'opacity-[' . rtrim(rtrim(sprintf('%.4f', $opacity), '0'), '.') . ']';
}

/**
 * @param array<string,mixed> $node
 * @param array<string,mixed>|null $parent_box
 * @return array<int,string>
 */
function matrix_db_figma_node_detail_lines($node, $parent_box = null) {
    $lines = array();

    $opacity = matrix_db_figma_node_opacity($node);
    if ($opacity < 0.99) {
        $token = matrix_db_figma_opacity_token($opacity);
        $lines[] = 'opacity=' . $opacity . ($token !== '' ? ' → ' . $token : '');
    }

    if (($node['layoutPositioning'] ?? '') === 'ABSOLUTE') {
        $box = $node['absoluteBoundingBox'] ?? null;
        if (is_array($box)) {
            $x = isset($box['x']) ? (int) round((float) $box['x']) : 0;
            $y = isset($box['y']) ? (int) round((float) $box['y']) : 0;
            $rel = '';
            if (is_array($parent_box)) {
                $rel_x = $x - (int) round((float) ($parent_box['x'] ?? 0));
                $rel_y = $y - (int) round((float) ($parent_box['y'] ?? 0));
                $rel   = ' (relative to parent: x=' . $rel_x . ', y=' . $rel_y . ')';
            }
            $lines[] = 'position=absolute at x=' . $x . ', y=' . $y . $rel;
        }
    }

    if (isset($node['cornerRadius']) && (float) $node['cornerRadius'] > 0) {
        $lines[] = 'radius=' . (int) round((float) $node['cornerRadius']) . 'px';
    }

    foreach ($node['effects'] ?? array() as $effect) {
        if (! is_array($effect) || ($effect['visible'] ?? true) === false) {
            continue;
        }
        if (($effect['type'] ?? '') === 'DROP_SHADOW') {
            $offset = $effect['offset'] ?? array();
            $color  = ! empty($effect['color']) ? matrix_db_figma_rgb_to_hex($effect['color']) : '';
            $lines[] = 'shadow: offset ' . (int) ($offset['x'] ?? 0) . '×' . (int) ($offset['y'] ?? 0)
                . ' blur ' . (int) ($effect['radius'] ?? 0) . ($color !== '' ? ' ' . $color : '');
        }
    }

    return $lines;
}

/**
 * @param array<string,mixed> $node
 * @return string
 */
function matrix_db_figma_text_style_line($node) {
    $style = $node['style'] ?? array();
    if (! is_array($style)) {
        $style = array();
    }

    $parts = array();
    if (! empty($style['fontFamily'])) {
        $parts[] = (string) $style['fontFamily'];
    }
    if (! empty($style['fontWeight'])) {
        $parts[] = 'weight ' . $style['fontWeight'];
    }
    if (! empty($style['fontSize'])) {
        $parts[] = (int) round((float) $style['fontSize']) . 'px';
    }
    if (! empty($style['lineHeightPx'])) {
        $parts[] = 'line-height ' . (int) round((float) $style['lineHeightPx']) . 'px';
    }
    if (! empty($style['letterSpacing'])) {
        $parts[] = 'letter-spacing ' . round((float) $style['letterSpacing'], 2) . 'px';
    }
    if (! empty($style['textAlignHorizontal'])) {
        $parts[] = 'align ' . strtolower((string) $style['textAlignHorizontal']);
    }
    if (! empty($style['italic'])) {
        $parts[] = 'italic';
    }
    if (! empty($style['textCase']) && $style['textCase'] !== 'ORIGINAL') {
        $parts[] = strtolower((string) $style['textCase']);
    }

    $color = matrix_db_figma_node_fill_color($node);
    if ($color !== '') {
        $parts[] = $color;
    }

    return implode(' / ', $parts);
}

/**
 * Collect names of hidden nodes for exclusion list.
 *
 * @param array<string,mixed> $node
 * @param bool                $ancestors_visible
 * @return array<int,string>
 */
function matrix_db_figma_collect_hidden_node_names($node, $ancestors_visible = true) {
    if (! is_array($node)) {
        return array();
    }

    $names  = array();
    $visible = matrix_db_figma_node_is_visible($node, $ancestors_visible);
    if (! $visible) {
        $name = trim((string) ($node['name'] ?? ''));
        if ($name !== '') {
            $names[] = $name;
        }
        return $names;
    }

    foreach ($node['children'] ?? array() as $child) {
        $names = array_merge($names, matrix_db_figma_collect_hidden_node_names($child, $visible));
    }

    return $names;
}

/**
 * @param array<string,mixed> $node
 * @param int                 $depth
 * @param int                 $max_depth
 * @param bool                $ancestors_visible
 * @param array<string,mixed>|null $parent_box
 * @return array<int,string>
 */
function matrix_db_figma_design_brief_walk($node, $depth = 0, $max_depth = 10, $ancestors_visible = true, $parent_box = null) {
    if (! is_array($node) || $depth > $max_depth) {
        return array();
    }

    if (! matrix_db_figma_node_is_visible($node, $ancestors_visible)) {
        return array();
    }

    $lines   = array();
    $indent  = str_repeat('  ', $depth);
    $type    = (string) ($node['type'] ?? 'NODE');
    $name    = (string) ($node['name'] ?? '');
    $size    = matrix_db_figma_node_size_line($node);
    $layout  = matrix_db_figma_node_layout_line($node);
    $meta    = array_filter(array($type, $name !== '' ? '"' . $name . '"' : '', $size, $layout));
    $lines[] = $indent . '- ' . implode(' | ', $meta);

    foreach (matrix_db_figma_node_detail_lines($node, $parent_box) as $detail) {
        $lines[] = $indent . '  ' . $detail;
    }

    if ($type === 'TEXT' && isset($node['characters']) && $node['characters'] !== '') {
        $text = trim((string) $node['characters']);
        $style = matrix_db_figma_text_style_line($node);
        $lines[] = $indent . '  text: "' . str_replace(array("\n", '"'), array(' ', "'"), $text) . '"'
            . ($style !== '' ? ' (' . $style . ')' : '');
    }

    if (in_array($type, array('RECTANGLE', 'FRAME', 'VECTOR', 'ELLIPSE'), true)) {
        $fill = matrix_db_figma_node_fill_color($node);
        if ($fill !== '') {
            $lines[] = $indent . '  fill: ' . $fill;
        }
    }

    $box = $node['absoluteBoundingBox'] ?? null;
    foreach ($node['children'] ?? array() as $child) {
        $lines = array_merge(
            $lines,
            matrix_db_figma_design_brief_walk($child, $depth + 1, $max_depth, true, is_array($box) ? $box : $parent_box)
        );
    }

    return $lines;
}

/**
 * Collect candidate image node IDs (largest visible visual nodes).
 *
 * @param array<string,mixed> $node
 * @return array<int,string>
 */
function matrix_db_figma_collect_image_node_ids($node) {
    $candidates = array();

    $walk = function ($current, $depth = 0, $ancestors_visible = true) use (&$walk, &$candidates) {
        if (! is_array($current) || $depth > 8) {
            return;
        }

        $visible = matrix_db_figma_node_is_visible($current, $ancestors_visible);
        if (! $visible) {
            return;
        }

        $type = (string) ($current['type'] ?? '');
        $name = strtolower((string) ($current['name'] ?? ''));
        $area = matrix_db_figma_node_area($current);

        $has_image_fill = false;
        foreach ($current['fills'] ?? array() as $fill) {
            if (is_array($fill) && ($fill['type'] ?? '') === 'IMAGE') {
                $has_image_fill = true;
                break;
            }
        }

        $is_visual = in_array($type, array('RECTANGLE', 'FRAME', 'VECTOR', 'ELLIPSE', 'INSTANCE'), true);
        if ($is_visual && ! empty($current['id']) && ($has_image_fill || $area >= 40000 || strpos($name, 'image') !== false || strpos($name, 'photo') !== false)) {
            $candidates[] = array(
                'id'   => (string) $current['id'],
                'area' => $area,
            );
        }

        foreach ($current['children'] ?? array() as $child) {
            $walk($child, $depth + 1, $visible);
        }
    };

    $walk($node, 0, true);

    usort(
        $candidates,
        function ($a, $b) {
            return ($b['area'] <=> $a['area']);
        }
    );

    $ids = array();
    foreach (array_slice($candidates, 0, 3) as $item) {
        $ids[] = $item['id'];
    }

    return array_values(array_unique($ids));
}

/**
 * Build implementation token summary from visible nodes.
 *
 * @param array<string,mixed> $document
 * @return array<int,string>
 */
function matrix_db_figma_build_implementation_tokens($document) {
    $lines = array();
    if (! is_array($document)) {
        return $lines;
    }

    $frame_size = matrix_db_figma_node_size_line($document);
    if ($frame_size !== '') {
        $box = $document['absoluteBoundingBox'] ?? array();
        $w   = isset($box['width']) ? (int) round((float) $box['width']) : 0;
        if ($w > 0) {
            $lines[] = 'max-width: max-w-[' . $w . 'px] on inner wrapper';
        }
    }

    $layout = matrix_db_figma_node_layout_line($document);
    if ($layout !== '') {
        $lines[] = 'frame layout: ' . $layout;
    }

    $bg = matrix_db_figma_node_fill_color($document);
    if ($bg !== '' && $bg !== 'gradient-linear') {
        $lines[] = 'background: ' . $bg;
    }

    $text_styles = array();
    $decorations = array();

    $walk = function ($current, $ancestors_visible = true) use (&$walk, &$text_styles, &$decorations) {
        if (! is_array($current)) {
            return;
        }
        if (! matrix_db_figma_node_is_visible($current, $ancestors_visible)) {
            return;
        }

        $type = (string) ($current['type'] ?? '');
        if ($type === 'TEXT') {
            $style = matrix_db_figma_text_style_line($current);
            if ($style !== '' && ! in_array($style, $text_styles, true)) {
                $text_styles[] = $style;
            }
        }

        if (matrix_db_figma_is_decorator_name((string) ($current['name'] ?? '')) || matrix_db_figma_is_vector_type($type)) {
            $size = matrix_db_figma_node_size_line($current);
            if ($size !== '' && matrix_db_figma_node_area($current) >= 15000) {
                $decorations[] = '"' . ($current['name'] ?? '') . '" ' . $size
                    . ' opacity=' . matrix_db_figma_node_opacity($current)
                    . ' format=' . matrix_db_figma_node_preferred_format($current);
            }
        }

        foreach ($current['children'] ?? array() as $child) {
            $walk($child, true);
        }
    };

    $walk($document, true);

    foreach (array_slice($text_styles, 0, 6) as $style) {
        $lines[] = 'typography: ' . $style;
    }

    foreach (array_slice($decorations, 0, 4) as $decor) {
        $lines[] = 'decoration: ' . $decor;
    }

    return $lines;
}

/**
 * Build a markdown design brief for one Figma node (REST API — works headless).
 *
 * @param string $file_key
 * @param string $node_id
 * @return string|WP_Error
 */
function matrix_db_figma_design_brief($file_key, $node_id) {
    $file_key = (string) $file_key;
    $node_id  = (string) $node_id;
    if ($file_key === '' || $node_id === '') {
        return new WP_Error('figma_brief_args', 'fileKey and nodeId are required for a design brief.');
    }

    $document = matrix_db_figma_fetch_document_node($file_key, $node_id);
    if (is_wp_error($document)) {
        return $document;
    }

    $lines   = array();
    $lines[] = 'Frame: "' . ($document['name'] ?? '') . '"';
    $size    = matrix_db_figma_node_size_line($document);
    if ($size !== '') {
        $lines[] = 'Frame size: ' . $size;
    }
    $layout = matrix_db_figma_node_layout_line($document);
    if ($layout !== '') {
        $lines[] = 'Frame layout: ' . $layout;
    }
    $lines[] = 'fileKey=' . $file_key . ' nodeId=' . $node_id;
    $lines[] = '';
    $lines[] = 'Node tree (visible layers only — implement THIS frame):';
    $lines   = array_merge($lines, matrix_db_figma_design_brief_walk($document));

    $hidden = array_values(array_unique(matrix_db_figma_collect_hidden_node_names($document)));
    if (! empty($hidden)) {
        $lines[] = '';
        $lines[] = 'Excluded (hidden in Figma — do NOT render in HTML/templates):';
        foreach ($hidden as $name) {
            $lines[] = '- "' . $name . '"';
        }
    }

    $tokens = matrix_db_figma_build_implementation_tokens($document);
    if (! empty($tokens)) {
        $lines[] = '';
        $lines[] = 'Implementation tokens (copy exact values into Tailwind):';
        foreach ($tokens as $token) {
            $lines[] = '- ' . $token;
        }
    }

    $manifest = matrix_db_figma_discover_asset_manifest($file_key, $node_id);
    if (! is_wp_error($manifest) && ! empty($manifest)) {
        $lines[] = '';
        $lines[] = 'Figma asset exports (use these — do not hand-author SVG paths):';
        foreach ($manifest as $item) {
            $lines[] = sprintf(
                '- %s "%s" %s %d×%d opacity=%s → %s',
                (string) ($item['role'] ?? 'asset'),
                (string) ($item['name'] ?? ''),
                strtoupper((string) ($item['format'] ?? 'png')),
                (int) ($item['width'] ?? 0),
                (int) ($item['height'] ?? 0),
                (string) ($item['opacity'] ?? 1),
                (string) ($item['url'] ?? '')
            );
        }
    } else {
        $image_ids = matrix_db_figma_collect_image_node_ids($document);
        if (! empty($image_ids)) {
            $node_map = array();
            foreach ($image_ids as $id) {
                $node_map[ $id ] = array('id' => $id);
            }
            $urls = matrix_db_figma_render_nodes_by_format($file_key, $node_map);
            if (! is_wp_error($urls) && ! empty($urls)) {
                $lines[] = '';
                $lines[] = 'Figma image export URLs (for reference / seeding):';
                foreach ($urls as $url) {
                    $lines[] = '- ' . $url;
                }
            }
        }
    }

    return implode("\n", $lines);
}

/**
 * Prompt lines for stored asset manifest on a section.
 *
 * @param array<string,mixed> $section
 * @return array<int,string>
 */
function matrix_db_figma_asset_manifest_prompt_lines($section) {
    $manifest = $section['figma_assets'] ?? array();
    if (! is_array($manifest) || empty($manifest)) {
        return array();
    }

    $lines = array('## Pre-imported Figma assets');
    foreach ($manifest as $item) {
        if (! is_array($item)) {
            continue;
        }
        $role = (string) ($item['role'] ?? '');
        $name = (string) ($item['name'] ?? '');
        $format = (string) ($item['format'] ?? '');
        $attachment_id = (int) ($item['attachment_id'] ?? 0);
        if ($role === '' && $attachment_id <= 0) {
            continue;
        }
        $line = '- ' . ($role !== '' ? $role : 'asset');
        if ($name !== '') {
            $line .= ' ("' . $name . '")';
        }
        if ($format !== '') {
            $line .= ' [' . strtoupper($format) . ']';
        }
        if ($attachment_id > 0) {
            $line .= ' attachment_id=' . $attachment_id;
        }
        if (! empty($item['url'])) {
            $line .= ' url=' . (string) $item['url'];
        }
        $line .= ' — use matrix_render_attachment_image(); do not recreate SVG path data by hand.';
        $lines[] = $line;
    }
    $lines[] = '';

    return $lines;
}

/**
 * Brief block for prompt injection, with graceful fallback when token is missing.
 *
 * @param array<string,mixed> $section
 * @return array<int,string>
 */
function matrix_db_figma_design_brief_prompt_lines($section) {
    $file_key = (string) ($section['figma_file'] ?? '');
    $node_id  = (string) ($section['figma_node'] ?? '');

    if ($file_key === '' && ! empty($section['figma_url'])) {
        $parsed   = matrix_db_parse_figma_url($section['figma_url']);
        $file_key = $parsed['file_key'];
        $node_id  = $parsed['node_id'];
    }

    if ($file_key === '' || $node_id === '') {
        return array(
            '## Figma design context',
            'No fileKey/nodeId on this section — call Figma MCP get_design_context from the Figma URL before coding.',
        );
    }

    $brief = matrix_db_figma_design_brief($file_key, $node_id);
    if (is_wp_error($brief)) {
        return array(
            '## Figma design context',
            'Pre-fetch failed: ' . $brief->get_error_message(),
            'You MUST call Figma MCP get_design_context(fileKey=' . $file_key . ', nodeId=' . $node_id . ') before writing any theme files.',
            'If Figma MCP is unavailable in headless mode, run `cursor agent mcp login Figma` once on this machine.',
        );
    }

    $lines = array(
        '## Figma design context (pre-fetched — PRIMARY source of truth)',
        'Implement ONLY this frame (' . $node_id . '). Do NOT copy markup or copy from other jobs or sibling Figma frames.',
        'Do NOT render nodes listed under Excluded (hidden in Figma).',
        '',
        (string) $brief,
        '',
        'If Figma MCP is available, also call get_design_context(fileKey=' . $file_key . ', nodeId=' . $node_id . ') and reconcile with the tree above.',
    );

    return array_merge($lines, matrix_db_figma_asset_manifest_prompt_lines($section));
}
