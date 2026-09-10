<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @return string
 */
function matrix_db_figma_token() {
    return (string) get_option('matrix_db_figma_token', '');
}

/**
 * @param string $path
 * @return array<string,mixed>|WP_Error
 */
function matrix_db_figma_api_get($path) {
    $token = matrix_db_figma_token();
    if ($token === '') {
        return new WP_Error('no_figma_token', 'Set a Figma personal access token in Figma to WordPress → Settings.');
    }

    $url  = 'https://api.figma.com/v1/' . ltrim($path, '/');
    $resp = wp_remote_get(
        $url,
        array(
            'timeout' => 30,
            'headers' => array(
                'X-Figma-Token' => $token,
            ),
        )
    );

    if (is_wp_error($resp)) {
        return $resp;
    }

    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code < 200 || $code >= 300) {
        return new WP_Error('figma_api', 'Figma API ' . $code . ': ' . wp_remote_retrieve_body($resp));
    }

    return is_array($body) ? $body : array();
}

/**
 * Whether a Figma node is visible (eye icon on) including ancestor chain.
 *
 * @param array<string,mixed> $node
 * @param bool                $ancestors_visible
 * @return bool
 */
function matrix_db_figma_node_is_visible($node, $ancestors_visible = true) {
    if (! is_array($node) || ! $ancestors_visible) {
        return false;
    }

    return ($node['visible'] ?? true) !== false;
}

/**
 * @param array<string,mixed> $node
 * @return array<string,mixed>|null
 */
function matrix_db_figma_find_named_node($node, $name) {
    if (! is_array($node)) {
        return null;
    }
    if (! matrix_db_figma_node_is_visible($node)) {
        return null;
    }
    if (strcasecmp((string) ($node['name'] ?? ''), $name) === 0) {
        return $node;
    }
    foreach ($node['children'] ?? array() as $child) {
        $found = matrix_db_figma_find_named_node($child, $name);
        if ($found) {
            return $found;
        }
    }
    return null;
}

/**
 * Vector-like Figma node types.
 *
 * @param string $type
 * @return bool
 */
function matrix_db_figma_is_vector_type($type) {
    return in_array($type, array('VECTOR', 'BOOLEAN_OPERATION', 'STAR', 'LINE', 'ELLIPSE'), true);
}

/**
 * Whether node (and visible children) are vector-only — prefer SVG export.
 *
 * @param array<string,mixed> $node
 * @return bool
 */
function matrix_db_figma_node_is_vector_tree($node) {
    if (! is_array($node)) {
        return false;
    }

    $type = (string) ($node['type'] ?? '');
    if (matrix_db_figma_is_vector_type($type)) {
        return true;
    }

    if (! in_array($type, array('GROUP', 'FRAME', 'COMPONENT', 'INSTANCE'), true)) {
        return false;
    }

    $children = $node['children'] ?? array();
    if (empty($children)) {
        return false;
    }

    foreach ($children as $child) {
        if (! is_array($child) || ! matrix_db_figma_node_is_visible($child)) {
            continue;
        }
        if (! matrix_db_figma_node_is_vector_tree($child) && ! matrix_db_figma_is_vector_type((string) ($child['type'] ?? ''))) {
            $child_type = (string) ($child['type'] ?? '');
            if (in_array($child_type, array('GROUP', 'FRAME'), true)) {
                if (! matrix_db_figma_node_is_vector_tree($child)) {
                    return false;
                }
                continue;
            }
            return false;
        }
    }

    return true;
}

/**
 * Preferred export format for a Figma node.
 *
 * @param array<string,mixed> $node
 * @return string png|svg
 */
function matrix_db_figma_node_preferred_format($node) {
    if (! is_array($node)) {
        return 'png';
    }

    $type = (string) ($node['type'] ?? '');

    foreach ($node['fills'] ?? array() as $fill) {
        if (is_array($fill) && ($fill['visible'] ?? true) !== false && ($fill['type'] ?? '') === 'IMAGE') {
            return 'png';
        }
    }

    if (matrix_db_figma_is_vector_type($type)) {
        return 'svg';
    }

    if (in_array($type, array('GROUP', 'FRAME', 'COMPONENT', 'INSTANCE'), true) && matrix_db_figma_node_is_vector_tree($node)) {
        return 'svg';
    }

    return 'png';
}

/**
 * Node bounding-box area.
 *
 * @param array<string,mixed> $node
 * @return float
 */
function matrix_db_figma_node_area($node) {
    $box = $node['absoluteBoundingBox'] ?? null;
    if (! is_array($box)) {
        return 0.0;
    }

    return (float) ($box['width'] ?? 0) * (float) ($box['height'] ?? 0);
}

/**
 * Node opacity (0–1).
 *
 * @param array<string,mixed> $node
 * @return float
 */
function matrix_db_figma_node_opacity($node) {
    if (! is_array($node)) {
        return 1.0;
    }

    return isset($node['opacity']) ? (float) $node['opacity'] : 1.0;
}

/**
 * Whether a node name suggests a decorative graphic.
 *
 * @param string $name
 * @return bool
 */
function matrix_db_figma_is_decorator_name($name) {
    $name = strtolower($name);
    $needles = array('decoration', 'decor', 'pattern', 'graphic', 'bg-', 'background', 'ornament', 'blob', 'shape');
    foreach ($needles as $needle) {
        if (strpos($name, $needle) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Collect decorative / background vector node candidates.
 *
 * @param array<string,mixed> $node
 * @return array<int,array{id:string,name:string,area:float,x:float,opacity:float,format:string}>
 */
function matrix_db_figma_collect_decorator_nodes($node) {
    $candidates = array();

    $walk = function ($current, $ancestors_visible = true) use (&$walk, &$candidates) {
        if (! is_array($current)) {
            return;
        }

        $visible = matrix_db_figma_node_is_visible($current, $ancestors_visible);
        if (! $visible) {
            return;
        }

        $type = (string) ($current['type'] ?? '');
        $name = (string) ($current['name'] ?? '');
        $box  = $current['absoluteBoundingBox'] ?? null;
        $area = matrix_db_figma_node_area($current);
        $x    = is_array($box) ? (float) ($box['x'] ?? 0) : 0.0;

        $is_absolute = (($current['layoutPositioning'] ?? '') === 'ABSOLUTE');
        $is_vectorish = matrix_db_figma_is_vector_type($type)
            || (in_array($type, array('GROUP', 'FRAME'), true) && matrix_db_figma_node_is_vector_tree($current));

        $name_match = matrix_db_figma_is_decorator_name($name);
        $large_bg   = $is_vectorish && $area >= 15000;

        if (! empty($current['id']) && $is_vectorish && ($name_match || $large_bg || $is_absolute)) {
            $candidates[] = array(
                'id'      => (string) $current['id'],
                'name'    => $name,
                'area'    => $area,
                'x'       => $x,
                'opacity' => matrix_db_figma_node_opacity($current),
                'format'  => matrix_db_figma_node_preferred_format($current),
            );
        }

        foreach ($current['children'] ?? array() as $child) {
            $walk($child, $visible);
        }
    };

    $walk($node, true);

    usort(
        $candidates,
        function ($a, $b) {
            return ($b['area'] <=> $a['area']);
        }
    );

    return $candidates;
}

/**
 * Collect exportable child node IDs from a Figma node tree.
 *
 * @param array<string,mixed> $node
 * @return array<int,string>
 */
function matrix_db_figma_collect_export_node_ids($node) {
    $logos = matrix_db_figma_find_named_node($node, 'Logos');
    if ($logos && ! empty($logos['children']) && is_array($logos['children'])) {
        $ids = array();
        foreach ($logos['children'] as $child) {
            if (! empty($child['id']) && matrix_db_figma_node_is_visible($child)) {
                $ids[] = (string) $child['id'];
            }
        }
        if (! empty($ids)) {
            return $ids;
        }
    }

    $decorators = matrix_db_figma_collect_decorator_nodes($node);
    if (! empty($decorators)) {
        return array_values(array_unique(array_column(array_slice($decorators, 0, 4), 'id')));
    }

    $ids  = array();
    $walk = function ($current, $depth = 0, $ancestors_visible = true) use (&$walk, &$ids) {
        if (! is_array($current) || $depth > 6) {
            return;
        }

        $visible = matrix_db_figma_node_is_visible($current, $ancestors_visible);
        if (! $visible) {
            return;
        }

        $type = (string) ($current['type'] ?? '');
        if (in_array($type, array('FRAME', 'COMPONENT', 'INSTANCE', 'GROUP', 'RECTANGLE', 'VECTOR'), true) && ! empty($current['id'])) {
            if ($depth >= 2) {
                $ids[] = (string) $current['id'];
            }
        }
        foreach ($current['children'] ?? array() as $child) {
            $walk($child, $depth + 1, $visible);
        }
    };
    $walk($node, 0, true);

    return array_values(array_unique($ids));
}

/**
 * Fetch render URLs for node IDs via Figma Images API.
 *
 * @param string              $file_key
 * @param array<int,string>   $node_ids
 * @param string              $format png|svg|jpg
 * @return array<int,string>|WP_Error
 */
function matrix_db_figma_render_urls($file_key, $node_ids, $format = 'png') {
    $node_ids = array_values(array_filter(array_map('strval', $node_ids)));
    if ($file_key === '' || empty($node_ids)) {
        return array();
    }

    $path = sprintf(
        'images/%s?ids=%s&format=%s',
        rawurlencode($file_key),
        rawurlencode(implode(',', $node_ids)),
        rawurlencode($format)
    );
    $res = matrix_db_figma_api_get($path);
    if (is_wp_error($res)) {
        return $res;
    }

    $images = isset($res['images']) && is_array($res['images']) ? $res['images'] : array();
    $out    = array();
    foreach ($node_ids as $node_id) {
        if (! empty($images[ $node_id ])) {
            $out[ $node_id ] = (string) $images[ $node_id ];
        }
    }

    return $out;
}

/**
 * Render nodes with per-node format preference.
 *
 * @param string                             $file_key
 * @param array<int,array<string,mixed>>     $nodes keyed by node id
 * @return array<string,string>|WP_Error node_id => url
 */
function matrix_db_figma_render_nodes_by_format($file_key, $nodes) {
    if ($file_key === '' || empty($nodes)) {
        return array();
    }

    $by_format = array(
        'png' => array(),
        'svg' => array(),
    );

    foreach ($nodes as $node_id => $node) {
        $format = is_array($node) ? matrix_db_figma_node_preferred_format($node) : 'png';
        if (! isset($by_format[ $format ])) {
            $format = 'png';
        }
        $by_format[ $format ][] = (string) $node_id;
    }

    $urls = array();
    foreach ($by_format as $format => $ids) {
        if (empty($ids)) {
            continue;
        }
        $batch = matrix_db_figma_render_urls($file_key, $ids, $format);
        if (is_wp_error($batch)) {
            if ($format === 'svg') {
                $batch = matrix_db_figma_render_urls($file_key, $ids, 'png');
            }
            if (is_wp_error($batch)) {
                return $batch;
            }
        }
        foreach ($batch as $node_id => $url) {
            $urls[ $node_id ] = $url;
        }
    }

    return $urls;
}

/**
 * Fetch a Figma document node via REST API.
 *
 * @param string $file_key
 * @param string $node_id
 * @return array<string,mixed>|WP_Error
 */
function matrix_db_figma_fetch_document_node($file_key, $node_id) {
    if ($file_key === '' || $node_id === '') {
        return new WP_Error('figma_args', 'fileKey and nodeId are required.');
    }

    $path = sprintf(
        'files/%s/nodes?ids=%s',
        rawurlencode($file_key),
        rawurlencode($node_id)
    );
    $res = matrix_db_figma_api_get($path);
    if (is_wp_error($res)) {
        return $res;
    }

    $document = $res['nodes'][ $node_id ]['document'] ?? null;
    if (! is_array($document)) {
        return new WP_Error('figma_node', 'Figma node not found: ' . $node_id);
    }

    return $document;
}

/**
 * Find a node by ID in a document tree.
 *
 * @param array<string,mixed> $node
 * @param string              $node_id
 * @return array<string,mixed>|null
 */
function matrix_db_figma_find_node_by_id($node, $node_id) {
    if (! is_array($node)) {
        return null;
    }
    if ((string) ($node['id'] ?? '') === $node_id) {
        return $node;
    }
    foreach ($node['children'] ?? array() as $child) {
        $found = matrix_db_figma_find_node_by_id($child, $node_id);
        if ($found) {
            return $found;
        }
    }

    return null;
}

/**
 *
 * @param array<int,array<string,mixed>> $candidates
 * @return array<int,array<string,mixed>>
 */
function matrix_db_figma_assign_asset_roles($candidates) {
    if (empty($candidates)) {
        return array();
    }

    $logos = array();
    $decor = array();
    $photos = array();

    foreach ($candidates as $item) {
        $role_hint = (string) ($item['role_hint'] ?? '');
        if ($role_hint === 'logo') {
            $logos[] = $item;
            continue;
        }
        if ($role_hint === 'decoration' || matrix_db_figma_is_decorator_name((string) ($item['name'] ?? ''))) {
            $decor[] = $item;
            continue;
        }
        if (($item['format'] ?? 'png') === 'svg') {
            $decor[] = $item;
            continue;
        }
        $photos[] = $item;
    }

    $out = array();

    foreach ($logos as $item) {
        $item['role'] = 'logo';
        $out[]        = $item;
    }

    usort(
        $decor,
        function ($a, $b) {
            $x_cmp = ($b['x'] ?? 0) <=> ($a['x'] ?? 0);
            if ($x_cmp !== 0) {
                return $x_cmp;
            }

            return ($b['area'] ?? 0) <=> ($a['area'] ?? 0);
        }
    );

    $decor_roles = array('decoration_primary', 'decoration_secondary');
    foreach (array_slice($decor, 0, 2) as $index => $item) {
        $item['role'] = $decor_roles[ $index ] ?? 'decoration';
        $out[]        = $item;
    }

    foreach ($photos as $index => $item) {
        $item['role'] = $index === 0 ? 'image' : 'image_' . ($index + 1);
        $out[]        = $item;
    }

    return $out;
}

/**
 * Discover export manifest for a Figma section node.
 *
 * @param string $file_key
 * @param string $node_id
 * @return array<int,array<string,mixed>>|WP_Error
 */
function matrix_db_figma_discover_asset_manifest($file_key, $node_id) {
    if ($file_key === '' || $node_id === '') {
        return array();
    }

    $document = matrix_db_figma_fetch_document_node($file_key, $node_id);
    if (is_wp_error($document)) {
        return $document;
    }

    $candidates = array();
    $node_map  = array();

    $logos = matrix_db_figma_find_named_node($document, 'Logos');
    if ($logos && ! empty($logos['children']) && is_array($logos['children'])) {
        foreach ($logos['children'] as $child) {
            if (! is_array($child) || empty($child['id']) || ! matrix_db_figma_node_is_visible($child)) {
                continue;
            }
            $id = (string) $child['id'];
            $box = $child['absoluteBoundingBox'] ?? array();
            $candidates[] = array(
                'node_id'   => $id,
                'name'      => (string) ($child['name'] ?? ''),
                'role_hint' => 'logo',
                'format'    => matrix_db_figma_node_preferred_format($child),
                'width'     => isset($box['width']) ? (int) round((float) $box['width']) : 0,
                'height'    => isset($box['height']) ? (int) round((float) $box['height']) : 0,
                'opacity'   => matrix_db_figma_node_opacity($child),
                'area'      => matrix_db_figma_node_area($child),
                'x'         => isset($box['x']) ? (float) $box['x'] : 0.0,
            );
            $node_map[ $id ] = $child;
        }
    }

    if (empty($candidates)) {
        foreach (matrix_db_figma_collect_decorator_nodes($document) as $decor) {
            $id    = $decor['id'];
            $found = matrix_db_figma_find_node_by_id($document, $id);
            $box   = is_array($found) ? ($found['absoluteBoundingBox'] ?? array()) : array();

            $candidates[] = array(
                'node_id'   => $id,
                'name'      => $decor['name'],
                'role_hint' => 'decoration',
                'format'    => $decor['format'],
                'width'     => isset($box['width']) ? (int) round((float) $box['width']) : 0,
                'height'    => isset($box['height']) ? (int) round((float) $box['height']) : 0,
                'opacity'   => $decor['opacity'],
                'area'      => $decor['area'],
                'x'         => $decor['x'],
            );
            if (is_array($found)) {
                $node_map[ $id ] = $found;
            }
        }
    }

    if (empty($candidates)) {
        $ids = matrix_db_figma_collect_export_node_ids($document);
        foreach ($ids as $id) {
            $found = matrix_db_figma_find_node_by_id($document, $id);
            if (! is_array($found) || ! matrix_db_figma_node_is_visible($found)) {
                continue;
            }
            $box = $found['absoluteBoundingBox'] ?? array();
            $candidates[] = array(
                'node_id' => $id,
                'name'    => (string) ($found['name'] ?? ''),
                'format'  => matrix_db_figma_node_preferred_format($found),
                'width'   => isset($box['width']) ? (int) round((float) $box['width']) : 0,
                'height'  => isset($box['height']) ? (int) round((float) $box['height']) : 0,
                'opacity' => matrix_db_figma_node_opacity($found),
                'area'    => matrix_db_figma_node_area($found),
                'x'       => isset($box['x']) ? (float) $box['x'] : 0.0,
            );
            $node_map[ $id ] = $found;
        }
    }

    if (empty($candidates)) {
        return array();
    }

    $candidates = matrix_db_figma_assign_asset_roles($candidates);
    $urls       = matrix_db_figma_render_nodes_by_format($file_key, $node_map);
    if (is_wp_error($urls)) {
        return $urls;
    }

    $manifest = array();
    foreach ($candidates as $item) {
        $node_id = (string) ($item['node_id'] ?? '');
        if ($node_id === '' || empty($urls[ $node_id ])) {
            continue;
        }
        $manifest[] = array(
            'node_id' => $node_id,
            'name'    => (string) ($item['name'] ?? ''),
            'role'    => (string) ($item['role'] ?? 'asset'),
            'format'  => (string) ($item['format'] ?? 'png'),
            'url'     => (string) $urls[ $node_id ],
            'width'   => (int) ($item['width'] ?? 0),
            'height'  => (int) ($item['height'] ?? 0),
            'opacity' => (float) ($item['opacity'] ?? 1.0),
        );
    }

    return $manifest;
}

/**
 * Discover export URLs for a Figma section node (legacy flat list).
 *
 * @param string $file_key
 * @param string $node_id
 * @return array<int,string>|WP_Error
 */
function matrix_db_figma_discover_asset_urls($file_key, $node_id) {
    $manifest = matrix_db_figma_discover_asset_manifest($file_key, $node_id);
    if (is_wp_error($manifest)) {
        return $manifest;
    }

    $urls = array();
    foreach ($manifest as $item) {
        if (! empty($item['url'])) {
            $urls[] = (string) $item['url'];
        }
    }

    return $urls;
}
