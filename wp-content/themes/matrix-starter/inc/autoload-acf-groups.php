<?php
/**
 * Autoload & register ACF groups (non-flexible groups, like post-type meta/options).
 * Looks for PHP files in defined directories. Each file can:
 *   - return a FieldsBuilder instance (preferred), or
 *   - return a raw ACF group array, or
 *   - self-register (in which case this loader will simply ignore the return).
 */

if (!defined('ABSPATH')) exit;

use StoutLogic\AcfBuilder\FieldsBuilder;

add_action('acf/init', function () {
    if (!function_exists('acf_add_local_field_group')) {
        return; // ACF not active
    }

    // Directories to scan (adjust to your theme)
    $dirs = [
        get_template_directory() . '/acf-fields/partials/pages',
        get_template_directory() . '/acf-fields/partials/post-types',
        get_template_directory() . '/acf-fields/partials/options',
        get_template_directory() . '/acf-fields/partials/taxonomies',
        get_template_directory() . '/acf-fields/post-types',
        get_template_directory() . '/acf-fields/options',
        get_template_directory() . '/acf-fields/taxonomies',
    ];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || substr($file->getFilename(), -4) !== '.php') continue;

            // Require and capture return value (if any)
            $returned = require $file->getPathname();

            // If a FieldsBuilder was returned, build & register it
            if ($returned instanceof FieldsBuilder) {
                acf_add_local_field_group($returned->build());
                continue;
            }

            // If a raw ACF local group array was returned, register it
            if (is_array($returned) && isset($returned['fields'], $returned['location'])) {
                acf_add_local_field_group($returned);
                continue;
            }

            // If the file self-registered (returns null/void), nothing to do.
        }
    }
}, 20);
