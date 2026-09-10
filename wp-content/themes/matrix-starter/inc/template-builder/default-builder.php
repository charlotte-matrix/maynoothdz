<?php
/**
 * Template Builder Defaults + Flexible Content Crash Guard (NON-DESTRUCTIVE)
 *
 * Fixes:
 * Fatal error: Cannot access offset of type array in isset or empty
 * in ACF Pro flexible content load_value
 *
 * Why it happens:
 * Flexible Content field meta is stored as an ARRAY OF LAYOUT NAMES.
 * If that array is corrupted (layout becomes an array), ACF uses it as an array key and fatals.
 *
 * What we do:
 * - Intercept acf/pre_load_value for flexible_content fields
 * - Read RAW post meta (layout list)
 * - Sanitize each layout item to a STRING
 * - Then call ACF's own flexible_content->load_value() with sanitized raw meta
 *
 * IMPORTANT:
 * - We do NOT rewrite DB meta.
 * - We do NOT remove/replace ACF's loader.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Coerce a layout value into a safe string.
 */
if (!function_exists('matrix_fc_layout_to_string')) {
    function matrix_fc_layout_to_string($layout): string {
        if (is_scalar($layout)) {
            return (string) $layout;
        }

        if (is_array($layout)) {
            // Common shapes we’ve seen:
            // ['value' => 'hero_001']
            if (isset($layout['value']) && is_scalar($layout['value'])) {
                return (string) $layout['value'];
            }

            // ['name' => 'hero_001']
            if (isset($layout['name']) && is_scalar($layout['name'])) {
                return (string) $layout['name'];
            }

            // [0 => 'hero_001'] or mixed
            foreach ($layout as $v) {
                if (is_scalar($v)) {
                    return (string) $v;
                }
                if (is_array($v)) {
                    if (isset($v['value']) && is_scalar($v['value'])) {
                        return (string) $v['value'];
                    }
                    if (isset($v['name']) && is_scalar($v['name'])) {
                        return (string) $v['name'];
                    }
                }
            }
        }

        return '';
    }
}

/**
 * Sanitize RAW flexible content stored value (layout list OR rows).
 *
 * ACF stores flexible content meta typically as:
 *   ['layout_one', 'layout_two']
 *
 * But after corruption it can become:
 *   [ ['value'=>'layout_one'], ['value'=>'layout_two'] ]
 *
 * In some cases (after filters), you might already see rows:
 *   [ ['acf_fc_layout'=>'layout_one', ...], ... ]
 */
if (!function_exists('matrix_sanitize_flexible_value')) {
    function matrix_sanitize_flexible_value($value) {
        if (!is_array($value)) {
            return $value;
        }

        // Case A: a single row array with acf_fc_layout
        if (isset($value['acf_fc_layout'])) {
            $value['acf_fc_layout'] = matrix_fc_layout_to_string($value['acf_fc_layout']);
            return $value;
        }

        // Decide whether this is:
        // - a layout list: [ 'hero_001', 'acf_title' ]
        // - or rows: [ ['acf_fc_layout'=>...], ... ]
        $is_rows = false;
        foreach ($value as $item) {
            if (is_array($item) && array_key_exists('acf_fc_layout', $item)) {
                $is_rows = true;
                break;
            }
        }

        // Case B: rows
        if ($is_rows) {
            foreach ($value as $i => $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (array_key_exists('acf_fc_layout', $row)) {
                    $row['acf_fc_layout'] = matrix_fc_layout_to_string($row['acf_fc_layout']);
                    $value[$i] = $row;
                }
            }
            return $value;
        }

        // Case C: layout list
        $clean = [];
        foreach ($value as $item) {
            $layout = matrix_fc_layout_to_string($item);
            if ($layout !== '') {
                $clean[] = $layout;
            }
        }
        return $clean;
    }
}

/**
 * Resolve post type reliably in admin add-new contexts.
 */
if (!function_exists('matrix_resolve_post_type_for_acf')) {
    function matrix_resolve_post_type_for_acf($post_id): string {
        if (is_numeric($post_id)) {
            $type = get_post_type((int) $post_id);
            if ($type) {
                return $type;
            }
        }

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && !empty($screen->post_type)) {
                return (string) $screen->post_type;
            }
        }

        if (!empty($_GET['post_type'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return sanitize_key($_GET['post_type']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        return 'post';
    }
}

/**
 * Default stack for sectors + practice_areas flexible_content_blocks.
 * IMPORTANT: Layout names must match your ACF layout "name" values exactly.
 */
if (!function_exists('matrix_sector_like_default_stack')) {
    function matrix_sector_like_default_stack(): array {
        return [
           // ['acf_fc_layout' => 'partners'],
        ];
    }
}

/**
 * ✅ CRASH GUARD
 * Intercept flexible content loading BEFORE ACF's own load_value runs.
 * Sanitize raw layout list, then run ACF loader with clean data.
 */
add_filter('acf/pre_load_value', function ($pre, $post_id, $field) {
    if (!is_array($field) || empty($field['type']) || $field['type'] !== 'flexible_content') {
        return $pre;
    }

    // Avoid recursion
    static $in_progress = [];
    $guard_key = !empty($field['key']) ? (string) $field['key'] : (string) ($field['name'] ?? 'flex');

    if (!empty($in_progress[$guard_key])) {
        return $pre;
    }

    // Only real numeric posts (skip options, users, terms, etc)
    if (!is_numeric($post_id) || empty($field['name'])) {
        return $pre;
    }

    $meta_key = (string) $field['name'];
    $raw      = get_post_meta((int) $post_id, $meta_key, true);

    // If empty meta, let ACF proceed (defaults can be applied in load_value filters)
    if (empty($raw)) {
        return $pre;
    }

    // Sanitize raw in BOTH formats
    $sanitized = matrix_sanitize_flexible_value($raw);

    // If ACF available, run its own flexible loader safely.
    if (function_exists('acf') && is_object(acf()) && isset(acf()->fields)) {
        $flex = acf()->fields->get_field_type('flexible_content');
        if ($flex && is_object($flex) && method_exists($flex, 'load_value')) {
            $in_progress[$guard_key] = true;
            $result = $flex->load_value($sanitized, $post_id, $field);
            unset($in_progress[$guard_key]);

            return $result;
        }
    }

    // Fallback: return sanitized raw (safer than fatal)
    return $sanitized;
}, -9999, 3);

/**
 * Prevent re-corruption on save.
 * NOTE: update_value receives ROWS; we still sanitize defensively.
 */
add_filter('acf/update_value/type=flexible_content', function ($value, $post_id, $field) {
    return matrix_sanitize_flexible_value($value);
}, -9999, 3);

/**
 * --------------------
 * HERO DEFAULTS
 * --------------------
 * - Posts: banner_image
 * - Pages: subhero
 * - Sectors/practice_areas: subhero
 */
// Respect empty hero if user removed it
add_filter('acf/update_value/name=hero_content_blocks', function ($value, $post_id, $field) {
    // Keep your existing sanitizer
    $value = matrix_sanitize_flexible_value($value);

    if (empty($value)) {
        update_post_meta((int)$post_id, '_matrix_hero_intentionally_empty', 1);
    } else {
        delete_post_meta((int)$post_id, '_matrix_hero_intentionally_empty');
    }

    return $value;
}, -9999, 3);

// Only apply default if not intentionally empty (and only once)
add_filter('acf/load_value/name=hero_content_blocks', function ($value, $post_id, $field) {
    if (!empty($value)) {
        return $value; // has data, do nothing
    }

    // If user intentionally cleared it, respect that
    if (get_post_meta((int)$post_id, '_matrix_hero_intentionally_empty', true)) {
        return $value; // keep empty
    }

    // Apply default only once
    $already_applied = get_post_meta((int)$post_id, '_matrix_hero_default_applied', true);
    if ($already_applied) {
        return $value; // don't re-apply
    }

    $type = matrix_resolve_post_type_for_acf($post_id);
    if ($type === 'page') {
        update_post_meta((int)$post_id, '_matrix_hero_default_applied', 1);
        return [
            [
                'acf_fc_layout'    => 'subhero',
                'heading_tag'      => 'h1',
                'background_color' => '#003b65',
                'decoration_style' => 'default_grey',
            ],
        ];
    }

    if ($type === 'post') {
        update_post_meta((int)$post_id, '_matrix_hero_default_applied', 1);
        return [ ['acf_fc_layout' => 'hero'] ];
    }

    return $value;
}, 10, 3);
/**
 * --------------------
 * FLEX DEFAULTS
 * --------------------
 * - Posts: single_post_content
 * - Sectors/practice_areas: sector-like stack
 */
add_filter('acf/load_value/name=flexible_content_blocks', function ($value, $post_id, $field) {
    if (!empty($value)) {
        return $value;
    }

    $type = matrix_resolve_post_type_for_acf($post_id);

    if ($type === 'post') {
        return [ ['acf_fc_layout' => 'single_post_content'] ];
    }

    return $value;
}, 10, 3);
