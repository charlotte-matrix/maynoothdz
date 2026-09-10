<?php
/**
 * Plugin Name: Matrix Sitemap Generator
 * Description: Import Slickplan XML/CSV and generate pages/post-types with mapping, main menu creation, and advanced per-item mapping with CPT generation.
 * Author: Matrix
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MENU
 */
add_action('admin_menu', function () {
    add_management_page(
        'Matrix Sitemap Generator',
        'Matrix Sitemap Generator',
        'manage_options',
        'matrix-sitemap-generator',
        'matrix_sitemap_admin_page'
    );
});

/**
 * Simple option keys to keep everything in one file.
 */
const MATRIX_SITEMAP_RAW_OPTION   = '_matrix_sitemap_import_raw';
const MATRIX_SITEMAP_STATE_OPTION = '_matrix_sitemap_import_state';

/**
 * Normalize header names (very tolerant).
 * e.g. "Parent ID", "parent-id", "parent_id " → "parent_id"
 */
function matrix_sitemap_normalize_header($header) {
    $header = strtolower(trim((string) $header));
    $header = preg_replace('/[^a-z0-9]+/', '_', $header);
    $header = preg_replace('/_+/', '_', $header);
    $header = trim($header, '_');
    return $header;
}

/**
 * Detect delimiter (comma, semicolon, or tab) and read CSV/TSV file.
 * Returns array of rows (each row = array of columns).
 */
function matrix_sitemap_read_delimited_file($path) {
    $contents = file_get_contents($path);
    if ($contents === false) {
        return ['rows' => [], 'delimiter' => ','];
    }

    $contents = str_replace(["\r\n", "\r"], "\n", $contents);
    $lines    = explode("\n", $contents);
    $lines    = array_filter($lines, static function($l) {
        return trim($l) !== '';
    });
    $lines    = array_values($lines);

    if (empty($lines)) {
        return ['rows' => [], 'delimiter' => ','];
    }

    $firstLine   = $lines[0];
    $commaCount  = substr_count($firstLine, ',');
    $tabCount    = substr_count($firstLine, "\t");
    $semiCount   = substr_count($firstLine, ';');

    if ($tabCount >= $commaCount && $tabCount >= $semiCount && $tabCount > 0) {
        $delimiter = "\t";
    } elseif ($semiCount >= $commaCount && $semiCount >= $tabCount && $semiCount > 0) {
        $delimiter = ';';
    } else {
        $delimiter = ',';
    }

    $rows = [];
    foreach ($lines as $line) {
        $rows[] = str_getcsv($line, $delimiter);
    }

    return [
        'rows'      => $rows,
        'delimiter' => $delimiter,
    ];
}

/**
 * Convert flat items (id/parent_id) into a tree for display.
 */
function matrix_sitemap_build_tree(array $items) {
    $byId = [];
    foreach ($items as $item) {
        $id = $item['id'];
        $item['children'] = [];
        $byId[$id] = $item;
    }

    $tree = [];
    foreach ($byId as $id => &$item) {
        $parent = $item['parent_id'];
        if ($parent && isset($byId[$parent])) {
            $byId[$parent]['children'][] = &$item;
        } else {
            $tree[] = &$item;
        }
    }

    return $tree;
}

/**
 * Read Slickplan XML export into a normalized item array.
 */
function matrix_sitemap_read_xml($path) {
    $xml = simplexml_load_file($path);
    if (!$xml) {
        return ['items' => [], 'errors' => ['Could not parse XML.']];
    }

    $items = [];
    $idCounter = 1;

    $recurse = function ($node, $parentId = null) use (&$recurse, &$items, &$idCounter) {
        foreach ($node->page as $page) {
            $id   = $idCounter++;
            $name = (string) ($page->name ?? '');
            $slug = (string) ($page->url_slug ?? '');
            $type = (string) ($page->page_type ?? '');

            $items[] = [
                'id'        => $id,
                'parent_id' => $parentId,
                'name'      => $name,
                'slug'      => $slug,
                'page_type' => $type,
                'raw'       => $page,
            ];

            if (isset($page->children)) {
                $recurse($page->children, $id);
            }
        }
    };

    if (isset($xml->children)) {
        $recurse($xml->children, null);
    } elseif (isset($xml->page)) {
        $recurse($xml, null);
    }

    return ['items' => $items, 'errors' => []];
}

/**
 * Handle CSV/TSV content in Slickplan export.
 * Uses the header row (id, parent_id, name, url_slug, page_type, …)
 */
function matrix_sitemap_read_csv_like($path) {
    $out  = matrix_sitemap_read_delimited_file($path);
    $rows = $out['rows'];

    if (empty($rows)) {
        return ['items' => [], 'errors' => ['CSV appears to be empty.']];
    }

    $headerRow = array_shift($rows);
    if (empty($headerRow)) {
        return ['items' => [], 'errors' => ['CSV has no header row.']];
    }

    // Normalise header names
    $normalizedHeaders = [];
    foreach ($headerRow as $i => $h) {
        $normalizedHeaders[$i] = matrix_sitemap_normalize_header($h);
    }

    // We require at least id, parent_id, name
    $required = ['id', 'parent_id', 'name'];
    $colIndex = [];

    foreach ($required as $req) {
        $foundIndex = array_search($req, $normalizedHeaders, true);
        if ($foundIndex === false) {
            $debugHeaders = implode(', ', $normalizedHeaders);
            return [
                'items'  => [],
                'errors' => [
                    "CSV is missing required column: {$req}",
                    "Detected header columns: {$debugHeaders}",
                ],
            ];
        }
        $colIndex[$req] = $foundIndex;
    }

    // Optional columns
    $optional = ['url_slug', 'page_type', 'link', 'notes'];
    foreach ($optional as $opt) {
        $idx = array_search($opt, $normalizedHeaders, true);
        if ($idx !== false) {
            $colIndex[$opt] = $idx;
        }
    }

    $items = [];
    foreach ($rows as $row) {
        if (!is_array($row) || count(array_filter($row, 'strlen')) === 0) {
            continue;
        }

        $id        = trim((string) ($row[$colIndex['id']] ?? ''));
        $parentRaw = trim((string) ($row[$colIndex['parent_id']] ?? ''));
        $name      = trim((string) ($row[$colIndex['name']] ?? ''));

        if ($id === '' || $name === '') {
            continue;
        }

        $parent_id = $parentRaw !== '' ? (int) $parentRaw : null;
        $slug      = '';
        $page_type = '';
        $link      = '';
        $notes     = '';

        if (isset($colIndex['url_slug'])) {
            $slug = trim((string) ($row[$colIndex['url_slug']] ?? ''));
        }
        if (isset($colIndex['page_type'])) {
            $page_type = trim((string) ($row[$colIndex['page_type']] ?? ''));
        }
        if (isset($colIndex['link'])) {
            $link = trim((string) ($row[$colIndex['link']] ?? ''));
        }
        if (isset($colIndex['notes'])) {
            $notes = trim((string) ($row[$colIndex['notes']] ?? ''));
        }

        $items[] = [
            'id'        => (int) $id,
            'parent_id' => $parent_id,
            'name'      => $name,
            'slug'      => $slug,
            'page_type' => $page_type,
            'link'      => $link,
            'notes'     => $notes,
            'raw'       => $row,
        ];
    }

    if (empty($items)) {
        return ['items' => [], 'errors' => ['Scan completed but no top-level items were found.']];
    }

    return ['items' => $items, 'errors' => []];
}

/**
 * Safe insert that does **NOT** overwrite existing posts with same slug.
 */
function matrix_sitemap_safe_insert_post($args) {
    $slug      = $args['post_name'] ?? '';
    $post_type = $args['post_type'] ?? 'page';

    if ($slug) {
        $existing = get_page_by_path($slug, OBJECT, $post_type);
        if ($existing && $existing->post_status !== 'trash') {
            // Skip creation – do not overwrite
            return $existing->ID;
        }
    }

    return wp_insert_post($args);
}

/**
 * Create/ensure "Main Menu" for top-level items and assign to 'primary'.
 *
 * @param array $branchConfigs  rootId => [ 'root_post_type' => ..., 'child_post_type' => ..., 'footer_only' => bool ]
 * @param array $wpByItemId     sitemap item id => created WP post ID
 */
function matrix_sitemap_ensure_main_menu(array $branchConfigs, array $wpByItemId) {
    if (empty($branchConfigs) || empty($wpByItemId)) {
        return;
    }

    $menu_name     = 'Main Menu';
    $menu_location = 'primary';

    // Find or create menu by name
    $existing_menu = wp_get_nav_menu_object($menu_name);
    if ($existing_menu instanceof WP_Term) {
        $menu_id = (int) $existing_menu->term_id;
    } else {
        $menu_id = wp_create_nav_menu($menu_name);
        if (is_wp_error($menu_id) || !$menu_id) {
            return;
        }
    }

    // Assign to 'primary' theme location
    $locations = get_theme_mod('nav_menu_locations');
    if (!is_array($locations)) {
        $locations = [];
    }
    $locations[$menu_location] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);

    // Get existing items in that menu to avoid duplicates
    $existing_items      = wp_get_nav_menu_items($menu_id);
    $existing_object_ids = [];
    if (!empty($existing_items)) {
        foreach ($existing_items as $item) {
            $existing_object_ids[] = (int) $item->object_id;
        }
    }

    // For each enabled root branch, add a top-level menu item if not already present
    foreach ($branchConfigs as $rootId => $conf) {
        if (empty($wpByItemId[$rootId])) {
            continue;
        }
        $post_id   = (int) $wpByItemId[$rootId];
        $post_type = sanitize_key($conf['root_post_type']);

        if (in_array($post_id, $existing_object_ids, true)) {
            continue; // already in menu
        }

        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-object-id' => $post_id,
            'menu-item-object'    => $post_type,
            'menu-item-type'      => 'post_type',
            'menu-item-status'    => 'publish',
        ]);
    }
}

/**
 * Guess a post type from sitemap row (available for quick/defaulting).
 */
function matrix_sitemap_guess_post_type($item) {
    $type = strtolower((string) ($item['page_type'] ?? ''));

    if (str_contains($type, 'blog') || str_contains($type, 'news')) {
        return 'post';
    }

    return 'page';
}

/**
 * Generate Extended CPT PHP files into the theme (inc/cpts/post-types).
 * Does not overwrite existing files. Returns number of files created.
 */
function matrix_sitemap_generate_cpt_files(array $new_cpts) {
    if (empty($new_cpts)) {
        return 0;
    }

    $theme_dir = get_stylesheet_directory();
    if (!$theme_dir || !is_dir($theme_dir)) {
        return 0;
    }

    $cpt_dir = $theme_dir . '/inc/cpts/post-types';
    if (!is_dir($cpt_dir)) {
        wp_mkdir_p($cpt_dir);
    }
    if (!is_dir($cpt_dir) || !is_writable($cpt_dir)) {
        return 0;
    }

    $created = 0;

    foreach ($new_cpts as $slug => $def) {
        $slug = sanitize_key($slug);
        if (!$slug) {
            continue;
        }

        $label = trim($def['label'] ?? '');
        if ($label === '') {
            $label = ucwords(str_replace(['-', '_'], ' ', $slug));
        }

        $file = $cpt_dir . '/' . $slug . '.php';
        if (file_exists($file)) {
            // Do not overwrite existing CPT file
            continue;
        }

        $singular = $label;
        $plural   = $label . 's';

        $code  = "<?php\n\n";
        $code .= "/**\n";
        $code .= " * Auto-generated by Matrix Sitemap Generator.\n";
        $code .= " */\n\n";
        $code .= "add_action('init', function() {\n";
        $code .= "    if (!function_exists('register_extended_post_type')) {\n";
        $code .= "        return;\n";
        $code .= "    }\n\n";
        $code .= "    register_extended_post_type('" . addslashes($slug) . "', [\n";
        $code .= "        'menu_icon'   => 'dashicons-admin-post',\n";
        $code .= "        'supports'    => ['title', 'editor'],\n";
        $code .= "        'has_archive' => true,\n";
        $code .= "        'rewrite'     => ['slug' => '" . addslashes($slug) . "'],\n";
        $code .= "        'show_in_rest'=> true,\n";
        $code .= "    ], [\n";
        $code .= "        'singular' => '" . addslashes($singular) . "',\n";
        $code .= "        'plural'   => '" . addslashes($plural) . "',\n";
        $code .= "        'slug'     => '" . addslashes($slug) . "',\n";
        $code .= "    ]);\n";
        $code .= "});\n";

        file_put_contents($file, $code);
        $created++;
    }

    return $created;
}

/**
 * Render QUICK mapping UI (branch-level) – Step 2.
 */
function matrix_sitemap_render_mapping_ui($raw) {
    $items = $raw['items'] ?? [];
    if (empty($items)) {
        echo '<p>No items were imported – nothing to map.</p>';
        return;
    }

    $tree     = matrix_sitemap_build_tree($items);
    $topLevel = $tree;

    // Available post types for dropdown
    $wp_types = get_post_types(
        [
            'show_ui' => true,
            'public'  => true,
        ],
        'objects'
    );

    $post_type_choices = [
        'page' => 'Page (page)',
        'post' => 'Post (post)',
    ];

    foreach ($wp_types as $type => $obj) {
        if (isset($post_type_choices[$type])) {
            continue;
        }
        $post_type_choices[$type] = sprintf('%s (%s)', $obj->labels->singular_name, $type);
    }

    // Remember checkbox state if POSTed (e.g. after validation error)
    $global_draft_checked = !empty($_POST['matrix_global_draft']);
    ?>
    <h2>Step 2A: Quick Mapping &amp; Generation</h2>
    <p>
        Use this for a fast import: map whole branches (top-level items and their children)
        to WordPress post types. Existing content with matching slugs is never overwritten.
        A <strong>Main Menu</strong> will be created/updated from the top-level items and
        assigned to the <code>primary</code> menu location.
    </p>

    <form method="post">
        <?php wp_nonce_field('matrix_sitemap_generate', 'matrix_sitemap_generate_nonce'); ?>
        <input type="hidden" name="matrix_mode" value="quick">

        <p style="margin: 1em 0; padding: 0.75em 1em; background: #f9f9f9; border-left: 4px solid #2271b1;">
            <label>
                <input type="checkbox" name="matrix_global_draft" value="1" <?php checked($global_draft_checked, true); ?>>
                Create new content as <strong>draft</strong> instead of published
            </label><br>
            <small>
                When unchecked, newly created items will be <strong>published</strong>. Existing content is never overwritten.
            </small>
        </p>

        <table class="widefat striped">
            <thead>
            <tr>
                <th>Top-level Item</th>
                <th>Example Children</th>
                <th>Root Post Type</th>
                <th>Children Post Type</th>
                <th>Import / Flags</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($topLevel as $root) :
                $rootId   = $root['id'];
                $children = $root['children'] ?? [];
                $preview  = array_slice($children, 0, 3);

                $nameLower = strtolower($root['name']);

                // Heuristic defaults
                $defaultChildType  = 'same'; // "Same as root post type"
                $defaultFooterOnly = false;

                if (str_contains($nameLower, 'blog') || str_contains($nameLower, 'news')) {
                    $defaultChildType = 'post';
                }

                if (str_contains($nameLower, 'footer')) {
                    $defaultFooterOnly = true;
                }
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($root['name']); ?></strong><br>
                        <code>ID: <?php echo (int) $rootId; ?></code>
                    </td>
                    <td>
                        <?php if ($preview) : ?>
                            <ul>
                                <?php foreach ($preview as $ch) : ?>
                                    <li><?php echo esc_html($ch['name']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <em>No direct children</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <select name="matrix_mapping[<?php echo (int) $rootId; ?>][root_post_type]">
                            <?php foreach ($post_type_choices as $type => $label) : ?>
                                <option value="<?php echo esc_attr($type); ?>"
                                    <?php selected($type, 'page'); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="matrix_mapping[<?php echo (int) $rootId; ?>][child_post_type]">
                            <option value="same"
                                <?php selected($defaultChildType, 'same'); ?>>
                                Same as root
                            </option>
                            <?php foreach ($post_type_choices as $type => $label) : ?>
                                <option value="<?php echo esc_attr($type); ?>"
                                    <?php selected($defaultChildType, $type); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="skip">Skip children</option>
                        </select>
                    </td>
                    <td>
                        <label style="display:block;margin-bottom:4px;">
                            <input type="checkbox"
                                   name="matrix_mapping[<?php echo (int) $rootId; ?>][enabled]"
                                   value="1"
                                   checked>
                            Import this branch
                        </label>

                        <label style="display:block;">
                            <input type="checkbox"
                                   name="matrix_mapping[<?php echo (int) $rootId; ?>][footer_only]"
                                   value="1"
                                   <?php checked($defaultFooterOnly, true); ?>>
                            Mark as “Footer only links”
                        </label>
                        <p class="description">
                            “Footer only” sets a <code>_matrix_footer_only</code> meta flag on all posts in this branch,
                            so your theme can hide them from main navigation if you wish.
                        </p>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">
                Generate WordPress Content (Quick Mode)
            </button>
        </p>
    </form>

    <?php
}

/**
 * Render ADVANCED mapping UI (per-item).
 */
function matrix_sitemap_render_advanced_mapping_ui($raw) {
    $items = $raw['items'] ?? [];
    if (empty($items)) {
        return;
    }

    $tree = matrix_sitemap_build_tree($items);

    // Index items by ID for path building
    $byId = [];
    foreach ($items as $item) {
        $byId[$item['id']] = $item;
    }

    // Build a path string "Parent » Child"
    $buildPath = function($itemId) use (&$byId, &$buildPath) {
        if (!isset($byId[$itemId])) {
            return '';
        }
        $item   = $byId[$itemId];
        $parent = $item['parent_id'];
        if (!$parent || !isset($byId[$parent])) {
            return $item['name'];
        }
        return $buildPath($parent) . ' » ' . $item['name'];
    };

    // Available post types
    $wp_types = get_post_types(
        [
            'show_ui' => true,
            'public'  => true,
        ],
        'objects'
    );

    $post_type_choices = [
        'auto'    => 'Auto (guess: page / post)',
        'page'    => 'Page (page)',
        'post'    => 'Post (post)',
    ];

    foreach ($wp_types as $type => $obj) {
        if (isset($post_type_choices[$type])) {
            continue;
        }
        $post_type_choices[$type] = sprintf('%s (%s)', $obj->labels->singular_name, $type);
    }

    $post_type_choices['new_cpt'] = '— New custom post type… —';
    $post_type_choices['skip']    = 'Skip this item';

    $global_draft_checked = !empty($_POST['matrix_global_draft_advanced']);
    ?>
    <h2>Step 2B: Advanced Mapping (Optional)</h2>
    <p>
        Use this when you want a finer level of control: set the post type for each individual item,
        including children. You can also define <strong>brand new custom post types</strong> here –
        the plugin will generate Extended CPT definition files inside your theme under
        <code>inc/cpts/post-types</code>.
    </p>

    <form method="post">
        <?php wp_nonce_field('matrix_sitemap_generate', 'matrix_sitemap_generate_nonce'); ?>
        <input type="hidden" name="matrix_mode" value="advanced">

        <p style="margin: 1em 0; padding: 0.75em 1em; background: #f9f9f9; border-left: 4px solid #2271b1;">
            <label>
                <input type="checkbox" name="matrix_global_draft" value="1" <?php checked($global_draft_checked, true); ?>>
                Create new content as <strong>draft</strong> instead of published
            </label><br>
            <small>
                When unchecked, newly created items will be <strong>published</strong>.
                Existing content is never overwritten.
            </small>
        </p>

        <table class="widefat striped">
            <thead>
            <tr>
                <th style="width:35%;">Item / Path</th>
                <th style="width:10%;">Slickplan ID</th>
                <th style="width:20%;">Post Type</th>
                <th style="width:25%;">New CPT Options (optional)</th>
                <th style="width:10%;">Import?</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $renderNode = function($node, $depth = 0) use (&$renderNode, $buildPath, $post_type_choices) {
                $id   = $node['id'];
                $path = $buildPath($id);
                $indent = str_repeat('&mdash; ', max(0, $depth));
                ?>
                <tr>
                    <td>
                        <?php echo wp_kses_post($indent); ?>
                        <strong><?php echo esc_html($node['name']); ?></strong><br>
                        <small><code><?php echo esc_html($path); ?></code></small>
                    </td>
                    <td>
                        <code><?php echo (int) $id; ?></code>
                    </td>
                    <td>
                        <select name="matrix_item_mapping[<?php echo (int) $id; ?>][post_type]">
                            <?php foreach ($post_type_choices as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>"
                                    <?php selected($value, 'auto'); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <small>Only used when “New custom post type…” is selected:</small><br>
                        <label>
                            Slug:
                            <input type="text"
                                   name="matrix_item_mapping[<?php echo (int) $id; ?>][new_cpt_slug]"
                                   placeholder="e.g. case-study"
                                   style="width:100%;max-width:200px;">
                        </label><br>
                        <label>
                            Label:
                            <input type="text"
                                   name="matrix_item_mapping[<?php echo (int) $id; ?>][new_cpt_label]"
                                   placeholder="e.g. Case Studies"
                                   style="width:100%;max-width:200px;">
                        </label>
                    </td>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="matrix_item_mapping[<?php echo (int) $id; ?>][enabled]"
                                   value="1"
                                   checked>
                            Import
                        </label>
                    </td>
                </tr>
                <?php
                if (!empty($node['children'])) {
                    foreach ($node['children'] as $child) {
                        $renderNode($child, $depth + 1);
                    }
                }
            };

            foreach ($tree as $rootNode) {
                $renderNode($rootNode, 0);
            }
            ?>
            </tbody>
        </table>

        <p class="description" style="margin-top:1em;">
            <strong>Notes:</strong><br>
            – <em>Auto (guess)</em> uses Slickplan <code>page_type</code> to choose between <code>page</code> and <code>post</code>.<br>
            – “New custom post type…” will cause the plugin to generate a
            <code>register_extended_post_type()</code> file under your theme’s
            <code>inc/cpts/post-types</code> directory, if it does not already exist.<br>
            – Items marked “Skip this item” will not be created.
        </p>

        <p class="submit">
            <button type="submit" class="button button-secondary">
                Generate WordPress Content (Advanced Mode)
            </button>
        </p>
    </form>
    <?php
}

/**
 * Handle Step 1 – upload/import.
 */
function matrix_sitemap_handle_import_upload() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!isset($_POST['matrix_sitemap_import_nonce']) ||
        !wp_verify_nonce($_POST['matrix_sitemap_import_nonce'], 'matrix_sitemap_import')) {
        return;
    }

    if (empty($_FILES['matrix_sitemap_file']['tmp_name'])) {
        add_settings_error('matrix_sitemap', 'no_file', 'Please choose a Slickplan CSV or XML file.');
        return;
    }

    $file   = $_FILES['matrix_sitemap_file'];
    $tmp    = $file['tmp_name'];
    $name   = $file['name'];
    $ext    = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $errors = [];
    $items  = [];

    if ($ext === 'xml') {
        $res    = matrix_sitemap_read_xml($tmp);
        $items  = $res['items'];
        $errors = $res['errors'];
    } else {
        $res    = matrix_sitemap_read_csv_like($tmp);
        $items  = $res['items'];
        $errors = $res['errors'];
    }

    if (!empty($errors)) {
        foreach ($errors as $err) {
            add_settings_error('matrix_sitemap', 'import_error', $err, 'error');
        }
        return;
    }

    if (empty($items)) {
        add_settings_error('matrix_sitemap', 'no_items', 'No items were found in the file.', 'error');
        return;
    }

    update_option(MATRIX_SITEMAP_RAW_OPTION, ['items' => $items], false);
    update_option(MATRIX_SITEMAP_STATE_OPTION, ['step' => 'mapping'], false);

    add_settings_error(
        'matrix_sitemap',
        'import_success',
        sprintf('Imported %d items from Slickplan. You can now use Quick or Advanced mapping below.', count($items)),
        'updated'
    );
}

/**
 * Handle Step 2 – generate content based on mapping (quick OR advanced) and create menu / CPT files where appropriate.
 */
function matrix_sitemap_handle_generate() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!isset($_POST['matrix_sitemap_generate_nonce']) ||
        !wp_verify_nonce($_POST['matrix_sitemap_generate_nonce'], 'matrix_sitemap_generate')) {
        return;
    }

    $raw = get_option(MATRIX_SITEMAP_RAW_OPTION);
    if (empty($raw['items']) || !is_array($raw['items'])) {
        add_settings_error('matrix_sitemap', 'no_raw', 'No imported data found. Please import again.', 'error');
        return;
    }

    $items = $raw['items'];

    // Index items by id for fast lookup
    $byId = [];
    foreach ($items as $item) {
        $byId[$item['id']] = $item;
    }

    $mode = isset($_POST['matrix_mode']) && $_POST['matrix_mode'] === 'advanced'
        ? 'advanced'
        : 'quick';

    // Global draft toggle
    $global_draft = !empty($_POST['matrix_global_draft']);

    /**
     * QUICK MODE (existing behaviour, branch-level mapping)
     */
    if ($mode === 'quick') {
        $mapping = isset($_POST['matrix_mapping']) && is_array($_POST['matrix_mapping'])
            ? $_POST['matrix_mapping']
            : [];

        // Resolve mapping per root id
        $branchConfigs = [];

        foreach ($mapping as $rootId => $conf) {
            $rootId  = (int) $rootId;
            $enabled = !empty($conf['enabled']);
            if (!$enabled) {
                continue;
            }

            $rootPostType = !empty($conf['root_post_type'])
                ? sanitize_key($conf['root_post_type'])
                : (!empty($conf['post_type'])
                    ? sanitize_key($conf['post_type'])
                    : 'page');

            $childPostType = isset($conf['child_post_type']) ? $conf['child_post_type'] : 'same';
            $childPostType = $childPostType !== '' ? $childPostType : 'same';

            $footerOnly = !empty($conf['footer_only']);

            $branchConfigs[$rootId] = [
                'root_post_type'  => $rootPostType,
                'child_post_type' => $childPostType,
                'footer_only'     => $footerOnly,
            ];
        }

        if (empty($branchConfigs)) {
            add_settings_error('matrix_sitemap', 'no_mapping', 'No branches were selected for import.', 'error');
            return;
        }

        // Helper to find root ancestor id for any item
        $rootOf = function($id) use (&$byId, &$rootOf) {
            if (!isset($byId[$id])) {
                return null;
            }
            $parent = $byId[$id]['parent_id'];
            if (!$parent || !isset($byId[$parent])) {
                return $id;
            }
            return $rootOf($parent);
        };

        $createdCount = 0;
        $skippedCount = 0;

        // Keep track of created WP IDs by Slickplan id so we can assign correct parents
        $wpByItemId = [];

        foreach ($items as $item) {
            $id       = $item['id'];
            $parentId = $item['parent_id'];
            $rootId   = $rootOf($id);

            if (!$rootId || !isset($branchConfigs[$rootId])) {
                continue; // Branch not enabled
            }

            $branch = $branchConfigs[$rootId];

            // Determine which post type this item should be
            if ($id === $rootId) {
                $postType = $branch['root_post_type'];
            } else {
                if ($branch['child_post_type'] === 'skip') {
                    continue;
                } elseif ($branch['child_post_type'] === 'same') {
                    $postType = $branch['root_post_type'];
                } else {
                    $postType = sanitize_key($branch['child_post_type']);
                }
            }

            $title = $item['name'];
            $slug  = $item['slug'] ?: sanitize_title($title);

            // Determine parent WordPress post ID (if any)
            $parentPostId = 0;
            if ($parentId && isset($wpByItemId[$parentId])) {
                $parentPostId = (int) $wpByItemId[$parentId];
            }

            $postArr = [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_status'  => $global_draft ? 'draft' : 'publish',
                'post_type'    => $postType,
                'post_parent'  => $parentPostId,
                'post_content' => '',
            ];

            $result = matrix_sitemap_safe_insert_post($postArr);

            if (is_wp_error($result)) {
                $skippedCount++;
                continue;
            }

            if ($result > 0) {
                $wpByItemId[$id] = $result;
                $createdCount++;

                if (!empty($branch['footer_only'])) {
                    update_post_meta($result, '_matrix_footer_only', 1);
                }
            } else {
                $skippedCount++;
            }
        }

        // Generate / update Main Menu from top-level branches
        matrix_sitemap_ensure_main_menu($branchConfigs, $wpByItemId);

        // Clear state; mapping has been executed
        delete_option(MATRIX_SITEMAP_RAW_OPTION);
        delete_option(MATRIX_SITEMAP_STATE_OPTION);

        add_settings_error(
            'matrix_sitemap',
            'generate_done',
            sprintf(
                'Quick generation complete. Created %d items, skipped %d (existing slug or error). Main Menu updated. New items were created as %s.',
                $createdCount,
                $skippedCount,
                $global_draft ? 'draft' : 'published'
            ),
            'updated'
        );

        return;
    }

    /**
     * ADVANCED MODE – per-item mapping & CPT generation
     */
    $itemMapping = isset($_POST['matrix_item_mapping']) && is_array($_POST['matrix_item_mapping'])
        ? $_POST['matrix_item_mapping']
        : [];

    if (empty($itemMapping)) {
        add_settings_error('matrix_sitemap', 'no_item_mapping', 'No advanced mappings were provided.', 'error');
        return;
    }

    $createdCount = 0;
    $skippedCount = 0;
    $wpByItemId   = [];
    $new_cpts     = [];

    foreach ($items as $item) {
        $id   = $item['id'];
        $conf = $itemMapping[$id] ?? null;

        if (!$conf) {
            continue;
        }

        $enabled = !empty($conf['enabled']);
        if (!$enabled) {
            continue;
        }

        $selected = $conf['post_type'] ?? 'auto';

        if ($selected === 'skip') {
            continue;
        }

        // Determine final post type
        if ($selected === 'new_cpt') {
            $slug_raw  = $conf['new_cpt_slug'] ?? '';
            $label_raw = $conf['new_cpt_label'] ?? '';

            $slug = sanitize_key($slug_raw);
            if (!$slug) {
                $slug = sanitize_key($item['slug'] ?: $item['name']);
            }

            $label = trim($label_raw) !== '' ? $label_raw : $item['name'];

            if ($slug) {
                $postType = $slug;

                if (!isset($new_cpts[$slug])) {
                    $new_cpts[$slug] = [
                        'label' => $label,
                    ];
                }
            } else {
                // Fallback to guessed type if slug somehow empty
                $postType = matrix_sitemap_guess_post_type($item);
            }
        } elseif ($selected === 'auto' || $selected === '') {
            $postType = matrix_sitemap_guess_post_type($item);
        } else {
            $postType = sanitize_key($selected);
        }

        $title = $item['name'];
        $slug  = $item['slug'] ?: sanitize_title($title);

        // Determine parent WordPress post ID (if any)
        $parentId     = $item['parent_id'];
        $parentPostId = 0;
        if ($parentId && isset($wpByItemId[$parentId])) {
            $parentPostId = (int) $wpByItemId[$parentId];
        }

        $postArr = [
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_status'  => $global_draft ? 'draft' : 'publish',
            'post_type'    => $postType,
            'post_parent'  => $parentPostId,
            'post_content' => '',
        ];

        $result = matrix_sitemap_safe_insert_post($postArr);

        if (is_wp_error($result)) {
            $skippedCount++;
            continue;
        }

        if ($result > 0) {
            $wpByItemId[$id] = $result;
            $createdCount++;
        } else {
            $skippedCount++;
        }
    }

    // Build branchConfigs from top-level items for menu creation
    $branchConfigs = [];
    foreach ($items as $item) {
        if (!empty($item['parent_id'])) {
            continue; // only top-level
        }
        $id   = $item['id'];
        $conf = $itemMapping[$id] ?? null;
        if (!$conf || empty($conf['enabled'])) {
            continue;
        }

        $selected = $conf['post_type'] ?? 'auto';
        if ($selected === 'skip') {
            continue;
        }

        if ($selected === 'new_cpt') {
            $slug_raw = $conf['new_cpt_slug'] ?? '';
            $slug     = sanitize_key($slug_raw);
            if (!$slug) {
                $slug = sanitize_key($item['slug'] ?: $item['name']);
            }
            $type = $slug ?: 'page';
        } elseif ($selected === 'auto' || $selected === '') {
            $type = matrix_sitemap_guess_post_type($item);
        } else {
            $type = sanitize_key($selected);
        }

        $branchConfigs[$id] = [
            'root_post_type'  => $type,
            'child_post_type' => 'same',
            'footer_only'     => false,
        ];
    }

    if (!empty($branchConfigs) && !empty($wpByItemId)) {
        matrix_sitemap_ensure_main_menu($branchConfigs, $wpByItemId);
    }

    // Generate CPT files for any new CPTs we saw in advanced mapping
    $cptCreated = matrix_sitemap_generate_cpt_files($new_cpts);

    // Clear state; mapping has been executed
    delete_option(MATRIX_SITEMAP_RAW_OPTION);
    delete_option(MATRIX_SITEMAP_STATE_OPTION);

    $msg = sprintf(
        'Advanced generation complete. Created %d items, skipped %d (existing slug or error). Main Menu updated (from top-level items).',
        $createdCount,
        $skippedCount
    );
    if ($cptCreated > 0) {
        $msg .= sprintf(' Generated %d new custom post type definition file(s) in your theme.', $cptCreated);
    }
    $msg .= sprintf(' New items were created as %s.', $global_draft ? 'draft' : 'published');

    add_settings_error(
        'matrix_sitemap',
        'generate_done_advanced',
        $msg,
        'updated'
    );
}

/**
 * ADMIN PAGE
 */
function matrix_sitemap_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle actions first
    if (isset($_POST['matrix_sitemap_action']) && $_POST['matrix_sitemap_action'] === 'import') {
        matrix_sitemap_handle_import_upload();
    } elseif (isset($_POST['matrix_sitemap_generate_nonce'])) {
        matrix_sitemap_handle_generate();
    }

    settings_errors('matrix_sitemap');

    $state = get_option(MATRIX_SITEMAP_STATE_OPTION);
    $raw   = get_option(MATRIX_SITEMAP_RAW_OPTION);
    ?>
    <div class="wrap">
        <h1>Matrix Sitemap Generator</h1>

        <p>
            Import a Slickplan sitemap export (CSV or XML), then either:
        </p>
        <ul style="list-style:disc;margin-left:1.5em;">
            <li><strong>Quick Mode</strong> – map whole branches (top-level + children) to WordPress post types.</li>
            <li><strong>Advanced Mode</strong> – map each individual item (including children) and even generate new custom post types
                as Extended CPT files in your theme.</li>
        </ul>
        <p>
            Existing posts/pages with matching slugs will <strong>not</strong> be overwritten.
            After import, a <strong>Main Menu</strong> is created/updated from the top-level items and
            assigned as the <code>primary</code> menu.
        </p>

        <hr>

        <!-- STEP 1: Upload -->
        <h2>Step 1: Upload Slickplan Export</h2>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('matrix_sitemap_import', 'matrix_sitemap_import_nonce'); ?>
            <input type="hidden" name="matrix_sitemap_action" value="import">

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="matrix_sitemap_file">Sitemap file</label></th>
                    <td>
                        <input type="file" name="matrix_sitemap_file" id="matrix_sitemap_file"
                               accept=".csv,.tsv,.txt,.xml" required>
                        <p class="description">
                            Upload the Slickplan export (CSV/TSV/XML). CSV and TSV with comma, semicolon, or tab
                            delimiters are supported.
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    Upload &amp; Scan Sitemap
                </button>
            </p>
        </form>

        <?php
        if (!empty($raw['items']) && (!empty($state['step']) && $state['step'] === 'mapping')) {
            echo '<hr>';
            matrix_sitemap_render_mapping_ui($raw);

            echo '<hr>';
            matrix_sitemap_render_advanced_mapping_ui($raw);
        }
        ?>

        <hr>

        <h2>Developer Notes</h2>
        <p>
            This tool is designed to be extended. After a successful import and mapping, you can:
        </p>
        <ul>
            <li>Adjust <code>matrix_sitemap_guess_post_type()</code> to auto-map by Slickplan <code>page_type</code> or <code>notes</code>.</li>
            <li>Use the generated <code>_matrix_footer_only</code> meta (quick mode) to control header/footer visibility.</li>
            <li>Leverage the generated Extended CPT files in <code>inc/cpts/post-types</code> alongside your existing
                <code>register_extended_post_type()</code> definitions.</li>
        </ul>
    </div>
    <?php
}
