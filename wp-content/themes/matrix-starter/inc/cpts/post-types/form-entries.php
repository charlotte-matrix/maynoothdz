<?php
/**
 * “Form Entries” CPT
 * – Top-level menu item with e-mail icon
 * – Hidden on front-end (`public => false`)
 * – No Gutenberg/editor UI (supports => false)
 * – Custom columns + read-only metabox further below
 */

add_action( 'init', function () {

    /* ——— Register using Extended CPTs (same style as FAQs) ——— */
    register_extended_post_type(
        'form_entry',
        [
            'menu_icon'      => 'dashicons-email-alt', // small mail icon
            'supports'       => false,                 // no title/body editor
            'public'         => false,
            'show_ui'        => true,
            'show_in_menu'   => true,                  // top-level
            'menu_position'  => 26,                    // under "Comments"
            'capability_type'=> 'post',
            'map_meta_cap'   => true,
        ],
        [
            'singular' => 'Form Entry',
            'plural'   => 'Form Entries',
            'slug'     => 'form-entries',
        ]
    );

} );



/* ────────────────────────────────────────────────────────────────
 *  Admin-side UX helpers
 * ──────────────────────────────────────────────────────────────── */

/* 1. Columns in wp-admin list table */
add_filter( 'manage_edit-form_entry_columns', function ( $cols ) {
    return [
        'cb'    => '<input type="checkbox" />',
        'title' => 'Entry',   // keeps default sortable "title" column (date/time)
        'name'  => 'Name',
        'email' => 'Email',
        'date'  => 'Date',
    ];
} );

add_action( 'manage_form_entry_posts_custom_column', function ( $col, $post_id ) {
    switch ( $col ) {
        case 'name':
            echo esc_html( get_post_meta( $post_id, 'name', true ) );
            break;
        case 'email':
            echo esc_html( get_post_meta( $post_id, 'email', true ) );
            break;
    }
}, 10, 2 );


/* 2. Read-only “Submission Details” metabox on single screen */
add_action( 'add_meta_boxes_form_entry', function () {
    add_meta_box(
        'form_entry_details',
        'Submission Details',
        function ( $post ) {

            $meta = get_post_meta( $post->ID );
            echo '<table class="widefat striped">';

            foreach ( $meta as $key => $vals ) {
                if ( str_starts_with( $key, '_' ) ) { continue; } // skip core/internal keys
                $label = ucwords( str_replace( '_', ' ', $key ) );
                $value = is_array( $vals ) ? implode( ', ', $vals ) : $vals[0];
                printf(
                    '<tr><th style="width:25%%;">%s</th><td>%s</td></tr>',
                    esc_html( $label ),
                    esc_html( $value )
                );
            }

            echo '</table>';
        },
        'form_entry',
        'normal',
        'high'
    );
} );

/**
 * CSV export for Form Entries.
 */
if (!function_exists('matrix_render_form_entries_export_page')) {
    function matrix_render_form_entries_export_page() {
        if (!current_user_can('edit_posts')) {
            wp_die('You do not have permission to export entries.');
        }

        $selected_form_name = isset($_GET['form_name']) ? sanitize_text_field((string) $_GET['form_name']) : '';
        $available_names = get_posts([
            'post_type' => 'form_entry',
            'post_status' => 'private',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $form_names = [];
        foreach ($available_names as $entry_id) {
            $name = (string) get_post_meta((int) $entry_id, 'form_name', true);
            if ($name === '') {
                $name = trim((string) get_the_title((int) $entry_id));
                if (strpos($name, ' – ') !== false) {
                    $name = trim((string) strstr($name, ' – ', true));
                }
            }
            if ($name !== '') {
                $form_names[$name] = $name;
            }
        }
        ksort($form_names);
        ?>
        <div class="wrap">
            <h1>Export Form Entries</h1>
            <p>Download all entries as CSV, or filter by form type (Get Involved, Renew, Contact, etc.).</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('matrix_export_form_entries_csv', 'matrix_export_form_entries_csv_nonce'); ?>
                <input type="hidden" name="action" value="matrix_export_form_entries_csv">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="matrix-form-name">Form type</label></th>
                        <td>
                            <select id="matrix-form-name" name="form_name">
                                <option value="">All form types</option>
                                <?php foreach ($form_names as $form_name): ?>
                                    <option value="<?php echo esc_attr($form_name); ?>" <?php selected($selected_form_name, $form_name); ?>>
                                        <?php echo esc_html($form_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Download CSV'); ?>
            </form>
        </div>
        <?php
    }
}

if (!function_exists('matrix_register_form_entries_export_menu')) {
    function matrix_register_form_entries_export_menu() {
        // Safety: remove pre-existing duplicate entries with same slug, then add once.
        remove_submenu_page('edit.php?post_type=form_entry', 'form-entry-export');
        add_submenu_page(
            'edit.php?post_type=form_entry',
            'Export Form Entries',
            'Export CSV',
            'edit_posts',
            'form-entry-export',
            'matrix_render_form_entries_export_page'
        );
    }
}

if (!has_action('admin_menu', 'matrix_register_form_entries_export_menu')) {
    add_action('admin_menu', 'matrix_register_form_entries_export_menu', 99);
}

add_action('admin_post_matrix_export_form_entries_csv', function () {
    if (!current_user_can('edit_posts')) {
        wp_die('You do not have permission to export entries.');
    }
    if (
        empty($_POST['matrix_export_form_entries_csv_nonce']) ||
        !wp_verify_nonce(sanitize_text_field((string) $_POST['matrix_export_form_entries_csv_nonce']), 'matrix_export_form_entries_csv')
    ) {
        wp_die('Invalid export request.');
    }

    $selected_form_name = isset($_POST['form_name']) ? sanitize_text_field((string) $_POST['form_name']) : '';
    $query_args = [
        'post_type' => 'form_entry',
        'post_status' => 'private',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'fields' => 'ids',
        'no_found_rows' => true,
    ];

    if ($selected_form_name !== '') {
        $query_args['meta_query'] = [
            [
                'key' => 'form_name',
                'value' => $selected_form_name,
            ],
        ];
    }

    $entry_ids = get_posts($query_args);
    $rows = [];
    $all_columns = ['entry_id', 'submitted_at', 'form_name'];

    foreach ($entry_ids as $entry_id) {
        $entry_id = (int) $entry_id;
        $meta = get_post_meta($entry_id);
        $row = [
            'entry_id' => $entry_id,
            'submitted_at' => get_the_date('Y-m-d H:i:s', $entry_id),
            'form_name' => (string) get_post_meta($entry_id, 'form_name', true),
        ];

        if ($row['form_name'] === '') {
            $fallback = trim((string) get_the_title($entry_id));
            if (strpos($fallback, ' – ') !== false) {
                $fallback = trim((string) strstr($fallback, ' – ', true));
            }
            $row['form_name'] = $fallback;
        }

        foreach ($meta as $key => $vals) {
            if ($key === 'form_name' || str_starts_with((string) $key, '_')) {
                continue;
            }
            $value = is_array($vals) ? implode(', ', array_map('strval', $vals)) : (string) $vals;
            $row[$key] = $value;
            if (!in_array($key, $all_columns, true)) {
                $all_columns[] = $key;
            }
        }

        $rows[] = $row;
    }

    $filename_suffix = $selected_form_name !== '' ? sanitize_title($selected_form_name) : 'all';
    $filename = 'form-entries-' . $filename_suffix . '-' . gmdate('Ymd-His') . '.csv';

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    if (!$output) {
        wp_die('Unable to generate CSV export.');
    }

    fputcsv($output, $all_columns);
    foreach ($rows as $row) {
        $line = [];
        foreach ($all_columns as $col) {
            $line[] = isset($row[$col]) ? (string) $row[$col] : '';
        }
        fputcsv($output, $line);
    }
    fclose($output);
    exit;
});