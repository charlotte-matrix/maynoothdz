<?php
/**
 * Auto-require PHP files in inc/helpers and inc/setup.
 *
 * Drop new helper or setup files into those folders (including subfolders
 * under helpers, e.g. inc/helpers/utils/) — no functions.php edits needed.
 *
 * @package Matrix_Starter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $directory Absolute path to a theme inc subdirectory.
 */
function matrix_starter_require_directory_php_files(string $directory): void {
    if (!is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    $files = [];
    foreach ($iterator as $file) {
        if (!$file->isFile() || substr($file->getFilename(), -4) !== '.php') {
            continue;
        }
        $files[] = $file->getPathname();
    }

    sort($files, SORT_STRING);

    foreach ($files as $path) {
        require_once $path;
    }
}

$helpers_dir = get_template_directory() . '/inc/helpers';

// Shared utils (menu icons, sections, etc.) before page-specific helpers.
matrix_starter_require_directory_php_files($helpers_dir . '/utils');
matrix_starter_require_directory_php_files($helpers_dir);
matrix_starter_require_directory_php_files(get_template_directory() . '/inc/setup');
