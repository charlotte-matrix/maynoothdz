<?php
$acf_fields_dir = get_template_directory() . '/acf-fields';

if (!is_dir($acf_fields_dir)) {
    return;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($acf_fields_dir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || substr($file->getFilename(), -4) !== '.php') {
        continue;
    }

    $path = $file->getPathname();

    // Flexible/hero child partials are loaded by their parent loaders:
    // - acf-fields/partials/flexi.php
    // - acf-fields/partials/hero.php
    // Loading child files here first causes require_once collisions
    // and results in invalid/empty layout definitions.
    if (
        strpos($path, '/acf-fields/partials/blocks/') !== false ||
        strpos($path, '/acf-fields/partials/hero/') !== false ||
        strpos($path, '/acf-fields/partials/pages/') !== false
    ) {
        continue;
    }

    require_once $path;
}