<?php
/**
 * Project CPT helpers + HTML summary download.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('matrix_dz_project_category_defaults')) {
    /**
     * Default colour / icon filename by category slug (map directory set).
     *
     * @return array<string, array{color:string,icon:string}>
     */
    function matrix_dz_project_category_defaults(): array
    {
        return [
            'energy'                          => ['color' => '#a47b13', 'icon' => 'energy-icon.svg'],
            'retrofit'                        => ['color' => '#d9684f', 'icon' => 'home-icon.svg'],
            'transport'                       => ['color' => '#02a59f', 'icon' => 'travel-icon.svg'],
            'biodiversity-resilience'         => ['color' => '#1b853f', 'icon' => 'leaf-icon.svg'],
            'community-action'                => ['color' => '#8562a5', 'icon' => 'community-icon.svg'],
            'public-realm'                    => ['color' => '#258d78', 'icon' => 'public-realm-icon.svg'],
            'education-awareness'             => ['color' => '#f3b63e', 'icon' => 'education-icon.svg'],
            'sustainable-practices'           => ['color' => '#006168', 'icon' => 'sustainable-icon.svg'],
            'circular-economy'                => ['color' => '#ad9d50', 'icon' => 'circular-icon.svg'],
            'water-nature-based-solutions'    => ['color' => '#43c6c6', 'icon' => 'water-nature-icon.svg'],
        ];
    }
}

if (!function_exists('matrix_dz_project_primary_category')) {
    function matrix_dz_project_primary_category(int $post_id): ?WP_Term
    {
        $terms = get_the_terms($post_id, 'project_category');
        if (!is_array($terms) || $terms === []) {
            return null;
        }
        return $terms[0];
    }
}

if (!function_exists('matrix_dz_project_category_style')) {
    /**
     * @return array{color:string,icon_url:string,label:string,slug:string}
     */
    function matrix_dz_project_category_style(?WP_Term $term): array
    {
        $defaults = matrix_dz_project_category_defaults();
        $slug     = $term ? $term->slug : '';
        $fallback = $defaults[$slug] ?? ['color' => '#203129', 'icon' => 'leaf-icon.svg'];

        $color = $fallback['color'];
        if ($term && function_exists('get_field')) {
            $picked = get_field('category_color', 'project_category_' . $term->term_id);
            if (is_string($picked) && $picked !== '') {
                $color = $picked;
            }
        }

        $icon_url = matrix_dz_assets_url($fallback['icon']);
        if ($term && function_exists('get_field')) {
            $icon = get_field('category_icon', 'project_category_' . $term->term_id);
            if (is_array($icon) && !empty($icon['url'])) {
                $icon_url = $icon['url'];
            }
        }

        return [
            'color'    => $color,
            'icon_url' => $icon_url,
            'label'    => $term ? $term->name : __('Project', 'matrix-starter'),
            'slug'     => $slug,
        ];
    }
}

if (!function_exists('matrix_dz_project_summary_url')) {
    function matrix_dz_project_summary_url(int $post_id): string
    {
        return add_query_arg('download', 'summary', get_permalink($post_id));
    }
}

if (!function_exists('matrix_dz_projects_archive_settings')) {
    /**
     * Theme Options → Projects directory settings with defaults.
     *
     * @return array{title:string,intro:string,sample_note:string,search_placeholder:string,map_url:string,page_size:int}
     */
    function matrix_dz_projects_archive_settings(): array
    {
        $group = function_exists('get_field') ? get_field('projects_archive', 'option') : null;
        if (!is_array($group)) {
            $group = [];
        }

        $map = (string) ($group['map_url'] ?? '/map/');
        if ($map === '') {
            $map = '/map/';
        }
        if (str_starts_with($map, '/')) {
            $map = home_url($map);
        }

        $page_size = (int) ($group['page_size'] ?? 9);
        if ($page_size < 1) {
            $page_size = 9;
        }

        return [
            'title'              => (string) ($group['title'] ?? __('Local projects & case studies', 'matrix-starter')),
            'intro'              => (string) ($group['intro'] ?? __('Discover the projects helping Maynooth take climate action — and the people and places making it happen.', 'matrix-starter')),
            'sample_note'        => (string) ($group['sample_note'] ?? __('Demonstration directory · Includes sample projects and illustrative photos.', 'matrix-starter')),
            'search_placeholder' => (string) ($group['search_placeholder'] ?? __('Search projects by name', 'matrix-starter')),
            'map_url'            => $map,
            'page_size'          => $page_size,
        ];
    }
}

if (!function_exists('matrix_dz_projects_directory_data')) {
    /**
     * JSON payload for the projects directory JS.
     *
     * @return array{categories:array<string,string>,categoryIcons:array<string,string>,projects:list<array<string,mixed>>,pageSize:int,mapUrl:string}
     */
    function matrix_dz_projects_directory_data(): array
    {
        $settings = matrix_dz_projects_archive_settings();
        $defaults = matrix_dz_project_category_defaults();

        $categories = [
            'All projects' => '#203129',
        ];
        $category_icons = [
            'All projects' => matrix_dz_assets_url('all-projects-icon.svg'),
        ];

        // Stable filter order matching the design directory.
        $order = [
            'energy',
            'retrofit',
            'transport',
            'biodiversity-resilience',
            'community-action',
            'public-realm',
            'education-awareness',
            'sustainable-practices',
            'circular-economy',
            'water-nature-based-solutions',
        ];

        $terms = get_terms([
            'taxonomy'   => 'project_category',
            'hide_empty' => false,
        ]);
        $by_slug = [];
        if (is_array($terms)) {
            foreach ($terms as $term) {
                $by_slug[$term->slug] = $term;
            }
        }

        foreach ($order as $slug) {
            $term = $by_slug[$slug] ?? null;
            if (!$term) {
                continue;
            }
            $style = matrix_dz_project_category_style($term);
            // Prefer theme default icon file when term has no custom icon uploaded.
            if (empty(get_field('category_icon', 'project_category_' . $term->term_id)) && isset($defaults[$slug])) {
                $style['icon_url'] = matrix_dz_assets_url($defaults[$slug]['icon']);
            }
            $categories[$term->name]     = $style['color'];
            $category_icons[$term->name] = $style['icon_url'];
            unset($by_slug[$slug]);
        }
        // Any leftover terms.
        foreach ($by_slug as $term) {
            $style = matrix_dz_project_category_style($term);
            $categories[$term->name]     = $style['color'];
            $category_icons[$term->name] = $style['icon_url'];
        }

        $query = new WP_Query([
            'post_type'      => 'project',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);

        $projects = [];
        foreach ($query->posts as $post) {
            $id    = (int) $post->ID;
            $term  = matrix_dz_project_primary_category($id);
            $style = matrix_dz_project_category_style($term);
            $status = (string) (get_field('project_status', $id) ?: '');
            $lead   = (string) (get_field('project_lead', $id) ?: '');
            if ($lead === '') {
                $lead = (string) get_the_excerpt($post);
            }
            $image = get_the_post_thumbnail_url($id, 'large') ?: '';

            $projects[] = [
                'id'          => $post->post_name,
                'title'       => get_the_title($id),
                'category'    => $style['label'],
                'status'      => $status !== '' ? $status : 'Example',
                'image'       => $image,
                'description' => $lead,
                'url'         => get_permalink($id),
                'date'        => (int) get_post_time('U', true, $post),
            ];
        }
        wp_reset_postdata();

        return [
            'categories'    => $categories,
            'categoryIcons' => $category_icons,
            'projects'      => $projects,
            'pageSize'      => $settings['page_size'],
            'mapUrl'        => $settings['map_url'],
        ];
    }
}

if (!function_exists('matrix_dz_project_build_summary_html')) {
    function matrix_dz_project_build_summary_html(int $post_id): string
    {
        $title    = get_the_title($post_id);
        $term     = matrix_dz_project_primary_category($post_id);
        $status   = (string) get_field('project_status', $post_id);
        $location = (string) get_field('project_location_label', $post_id);
        $why_raw = (string) get_field('project_why_body', $post_id);
        // Prefer real HTML paragraphs so the download matches the design summary.
        $paras = [];
        if ($why_raw !== '' && preg_match_all('#<p\b[^>]*>(.*?)</p>#is', $why_raw, $matches)) {
            foreach ($matches[1] as $chunk) {
                $text = trim(wp_strip_all_tags(html_entity_decode($chunk, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($text !== '') {
                    $paras[] = $text;
                }
            }
        }
        if ($paras === []) {
            $why_text = trim(wp_strip_all_tags($why_raw));
            $split    = preg_split("/\n\s*\n/", $why_text) ?: [];
            $paras    = array_values(array_filter(array_map('trim', $split)));
            if ($paras === [] && $why_text !== '') {
                $paras = [$why_text];
            }
        }

        $meta_bits = array_filter([
            $term ? $term->name : '',
            $status,
            $location !== '' ? (str_contains($location, ',') ? explode(',', $location)[0] : $location) : 'Maynooth',
        ]);

        $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $body = '';
        foreach ($paras as $p) {
            $body .= '<p>' . $esc($p) . '</p>';
        }

        $html  = '<!doctype html><html lang="en"><meta charset="utf-8">';
        $html .= '<meta name="viewport" content="width=device-width,initial-scale=1">';
        $html .= '<title>' . $esc($title) . ' — summary</title>';
        $html .= '<style>body{font:18px/1.65 system-ui;color:#203129;max-width:760px;margin:48px auto;padding:0 24px}h1{line-height:1.2}small{color:#526259}</style>';
        $html .= '<h1>' . $esc($title) . '</h1>';
        $html .= '<p>' . $esc(implode(' · ', $meta_bits)) . '</p>';
        $html .= '<p><small>Demonstration project summary. Content and figures require confirmation.</small></p>';
        $html .= '<h2>Why this project</h2>' . $body;
        $html .= '<h2>Get involved</h2>';
        $html .= '<p>Contact the Climate Action Office: climateaction@kildarecoco.ie</p>';
        $html .= '</html>';

        return $html;
    }
}

/**
 * Document title for the projects archive uses Theme Options copy.
 */
add_filter('document_title_parts', function (array $parts): array {
    if (!is_post_type_archive('project')) {
        return $parts;
    }
    $settings = matrix_dz_projects_archive_settings();
    if ($settings['title'] !== '') {
        $parts['title'] = $settings['title'];
    }
    return $parts;
});

if (!function_exists('matrix_dz_project_glance_items')) {
    /**
     * Load “At a glance” repeater rows, including a repair path when meta was
     * saved as a serialized array instead of ACF’s expected row count.
     *
     * @return list<array{label:string,value:string,is_pending:bool}>
     */
    function matrix_dz_project_glance_items(int $post_id): array
    {
        $items = function_exists('get_field') ? get_field('project_glance_items', $post_id) : null;
        if (is_array($items) && $items !== []) {
            return array_values($items);
        }

        $raw = get_post_meta($post_id, 'project_glance_items', true);

        // Seed fallback sometimes wrote the full rows array onto the repeater key.
        if (is_array($raw) && $raw !== [] && isset($raw[0]) && is_array($raw[0])) {
            $rows = [];
            foreach ($raw as $i => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $label = trim((string) ($row['label'] ?? ''));
                $value = trim((string) ($row['value'] ?? ''));
                if ($label === '' || $value === '') {
                    continue;
                }
                $pending = !empty($row['is_pending']);
                $rows[] = [
                    'label'      => $label,
                    'value'      => $value,
                    'is_pending' => $pending,
                ];
                // Heal into ACF’s expected shape for next load / admin edits.
                update_post_meta($post_id, "project_glance_items_{$i}_label", $label);
                update_post_meta($post_id, "project_glance_items_{$i}_value", $value);
                update_post_meta($post_id, "project_glance_items_{$i}_is_pending", $pending ? 1 : 0);
                update_post_meta($post_id, "_project_glance_items_{$i}_label", 'field_project_details_project_glance_items_label');
                update_post_meta($post_id, "_project_glance_items_{$i}_value", 'field_project_details_project_glance_items_value');
                update_post_meta($post_id, "_project_glance_items_{$i}_is_pending", 'field_project_details_project_glance_items_is_pending');
            }
            if ($rows !== []) {
                update_post_meta($post_id, 'project_glance_items', count($rows));
                update_post_meta($post_id, '_project_glance_items', 'field_project_details_project_glance_items');
            }
            return $rows;
        }

        // Rebuild from indexed subfields if a count is missing/wrong.
        $rows = [];
        for ($i = 0; $i < 50; $i++) {
            $label = (string) get_post_meta($post_id, "project_glance_items_{$i}_label", true);
            $value = (string) get_post_meta($post_id, "project_glance_items_{$i}_value", true);
            if ($label === '' && $value === '') {
                break;
            }
            if ($label === '' || $value === '') {
                continue;
            }
            $rows[] = [
                'label'      => $label,
                'value'      => $value,
                'is_pending' => (bool) get_post_meta($post_id, "project_glance_items_{$i}_is_pending", true),
            ];
        }
        if ($rows !== [] && (!is_numeric($raw) || (int) $raw !== count($rows))) {
            update_post_meta($post_id, 'project_glance_items', count($rows));
        }

        return $rows;
    }
}

add_action('template_redirect', function () {
    if (!is_singular('project')) {
        return;
    }
    if (!isset($_GET['download']) || (string) $_GET['download'] !== 'summary') {
        return;
    }

    $post_id = (int) get_queried_object_id();
    if ($post_id <= 0) {
        return;
    }

    if (!get_field('project_enable_summary', $post_id)) {
        status_header(404);
        exit;
    }

    $slug     = get_post_field('post_name', $post_id) ?: 'project';
    $filename = sanitize_file_name($slug . '-summary.html');
    $html     = matrix_dz_project_build_summary_html($post_id);

    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) strlen($html));
    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- full HTML document download
    exit;
});
