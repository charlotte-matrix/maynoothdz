<?php
/**
 * Relax ACF validation for page builder fields (hero + flexi).
 *
 * Editors can save pages without empty hero/flexi sub-fields blocking publish.
 * Disables ACF's client-side validator on relaxed edit screens (root cause of
 * "Validation failed. 1 field requires attention" on classic editor saves).
 *
 * @package Matrix_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post types that use hero/flexi builders and should save without ACF blocking.
 *
 * @return list<string>
 */
function matrix_acf_relaxed_post_types(): array {
    return apply_filters('matrix_acf_relaxed_post_types', [
        'page',
        'post',
        'resource',
        'partner',
    ]);
}

/**
 * Flexible content root field names.
 *
 * @return list<string>
 */
function matrix_acf_relaxed_builder_field_names(): array {
    return ['flexible_content_blocks', 'hero_content_blocks'];
}

/**
 * Post ID being saved or validated (admin / AJAX / block editor).
 */
function matrix_acf_relaxed_resolve_post_id(): int {
    foreach (['_acf_post_id', 'post_ID', 'post_id', 'id'] as $key) {
        if (!empty($_POST[ $key ])) {
            return (int) $_POST[ $key ];
        }
        if (!empty($_REQUEST[ $key ])) {
            return (int) $_REQUEST[ $key ];
        }
    }

    if (function_exists('acf_request_arg')) {
        $from_acf = (int) acf_request_arg('post_id', 0);
        if ($from_acf > 0) {
            return $from_acf;
        }
    }

    global $post;
    if ($post instanceof WP_Post) {
        return (int) $post->ID;
    }

    return 0;
}

/**
 * Whether the current POST includes hero/flexi builder data.
 *
 * ACF submits field keys (field_hero_content_blocks_...) not bare field names.
 */
function matrix_acf_request_has_relaxed_builder(): bool {
    if (empty($_POST['acf']) || !is_array($_POST['acf'])) {
        return false;
    }

    $roots = matrix_acf_relaxed_builder_field_names();

    foreach (array_keys($_POST['acf']) as $key) {
        foreach ($roots as $root) {
            if (
                $key === $root
                || strncmp($key, $root . '_', strlen($root) + 1) === 0
                || str_contains($key, $root)
            ) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Whether the current request should skip ACF validation (save + AJAX + editor).
 */
function matrix_acf_should_relax_validation(): bool {
    if (matrix_acf_request_has_relaxed_builder()) {
        return true;
    }

    $post_id = matrix_acf_relaxed_resolve_post_id();
    if ($post_id > 0) {
        return in_array(get_post_type($post_id), matrix_acf_relaxed_post_types(), true);
    }

    $post_type = '';
    if (function_exists('acf_request_arg')) {
        $post_type = (string) acf_request_arg('post_type', '');
    }
    if ($post_type === '' && isset($_POST['post_type'])) {
        $post_type = (string) $_POST['post_type'];
    }
    if ($post_type === '' && isset($_REQUEST['post_type'])) {
        $post_type = (string) $_REQUEST['post_type'];
    }

    if ($post_type !== '' && in_array($post_type, matrix_acf_relaxed_post_types(), true)) {
        return true;
    }

    if (function_exists('get_current_screen')) {
        $screen = get_current_screen();
        if (
            $screen
            && $screen->base === 'post'
            && ! empty($screen->post_type)
            && in_array($screen->post_type, matrix_acf_relaxed_post_types(), true)
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Resolve a parent field without running acf/load_field (avoids infinite recursion).
 *
 * @param int|string $parent_key ACF field key or ID.
 * @return array<string, mixed>|null
 */
function matrix_acf_get_parent_field_unfiltered($parent_key): ?array {
    if ($parent_key === '' || $parent_key === 0) {
        return null;
    }

    if (function_exists('acf_get_store')) {
        $store = acf_get_store('fields');
        if ($store && $store->has($parent_key)) {
            $cached = $store->get($parent_key);
            return is_array($cached) ? $cached : null;
        }
    }

    if (function_exists('acf_is_local_field') && function_exists('acf_get_local_field') && acf_is_local_field($parent_key)) {
        $local = acf_get_local_field($parent_key);
        return is_array($local) ? $local : null;
    }

    if (function_exists('acf_get_raw_field')) {
        $raw = acf_get_raw_field($parent_key);
        return is_array($raw) ? $raw : null;
    }

    return null;
}

/**
 * Whether a field belongs to hero/flexi builders (any nesting depth).
 *
 * @param array<string, mixed> $field ACF field array.
 */
function matrix_acf_field_is_in_relaxed_builder(array $field): bool {
    if (matrix_acf_should_relax_validation()) {
        return true;
    }

    $builders = matrix_acf_relaxed_builder_field_names();
    $guard    = 0;
    $current  = $field;

    while ($guard++ < 40) {
        $name = (string) ($current['name'] ?? '');
        $type = (string) ($current['type'] ?? '');

        if ($name !== '' && in_array($name, $builders, true)) {
            return true;
        }
        if ($type === 'flexible_content') {
            return true;
        }

        $parent_key = $current['parent'] ?? '';
        $parent     = matrix_acf_get_parent_field_unfiltered($parent_key);
        if (! is_array($parent)) {
            break;
        }

        $current = $parent;
    }

    return false;
}

/**
 * Strip required / repeater minimum constraints in the editor UI.
 */
add_filter('acf/load_field', function ($field) {
    if (! is_array($field)) {
        return $field;
    }

    if (! matrix_acf_field_is_in_relaxed_builder($field)) {
        return $field;
    }

    $field['required'] = 0;

    if (($field['type'] ?? '') === 'repeater' && isset($field['min'])) {
        $field['min'] = 0;
    }

    return $field;
}, 20);

add_filter('acf/prepare_field', function ($field) {
    if (! is_array($field)) {
        return $field;
    }

    if (! matrix_acf_field_is_in_relaxed_builder($field)) {
        return $field;
    }

    $field['required'] = 0;

    if (($field['type'] ?? '') === 'repeater' && isset($field['min'])) {
        $field['min'] = 0;
    }

    return $field;
}, 20);

/**
 * Pass field validation when saving relaxed post types / builder payloads.
 */
add_filter('acf/validate_value', function ($valid, $value, $field, $input) {
    if (matrix_acf_should_relax_validation()) {
        return true;
    }

    if (is_array($field) && matrix_acf_field_is_in_relaxed_builder($field)) {
        return true;
    }

    return $valid;
}, 1, 4);

/**
 * Skip ACF server validation entirely for relaxed saves.
 */
add_action('acf/validate_save_post', function () {
    if (! matrix_acf_should_relax_validation()) {
        return;
    }

    if (isset(acf()->validation) && is_object(acf()->validation)) {
        remove_action('acf/validate_save_post', [acf()->validation, 'acf_validate_save_post'], 5);
    }

    if (function_exists('acf_reset_validation_errors')) {
        acf_reset_validation_errors();
    }
}, 4);

add_action('acf/validate_save_post', function () {
    if (! matrix_acf_should_relax_validation()) {
        return;
    }

    if (function_exists('acf_reset_validation_errors')) {
        acf_reset_validation_errors();
    }
}, 1000);

/**
 * Classic editor: disable ACF client-side validation (runs before AJAX and blocks save).
 */
add_action('acf/input/admin_enqueue_scripts', function () {
    if (! matrix_acf_should_relax_validation()) {
        return;
    }

    $script = <<<'JS'
(function ($) {
    if (typeof acf === 'undefined') {
        return;
    }

    var disableClientValidation = function () {
        if (acf.validation && typeof acf.validation.disable === 'function') {
            acf.validation.disable();
        }
    };

    acf.addAction('ready', disableClientValidation);
    acf.addAction('append', disableClientValidation);

    acf.addFilter('validation_complete', function (json) {
        json.valid = 1;
        if (json.errors) {
            json.errors = [];
        }
        return json;
    });
})(jQuery);
JS;

    wp_add_inline_script('acf-input', $script);
}, 20);
