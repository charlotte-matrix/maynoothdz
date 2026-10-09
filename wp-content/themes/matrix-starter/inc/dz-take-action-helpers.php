<?php
/**
 * Take Action CPT helpers — directory listing + theme colours.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('matrix_dz_take_action_theme_defaults')) {
    /**
     * Colour / default icon by pathway theme style key.
     *
     * @return array<string, array{color:string,icon:string,label:string}>
     */
    function matrix_dz_take_action_theme_defaults(): array
    {
        return [
            'retrofit'     => ['color' => '#d9684f', 'icon' => 'home-action-icon.svg', 'label' => 'Retrofit'],
            'transport'    => ['color' => '#02a59f', 'icon' => 'travel-action-icon.svg', 'label' => 'Travel'],
            'biodiversity' => ['color' => '#1b853f', 'icon' => 'leaf-action-icon.svg', 'label' => 'Biodiversity'],
            'energy'       => ['color' => '#a47b13', 'icon' => 'energy-icon.svg', 'label' => 'Energy'],
            'public-realm' => ['color' => '#258d78', 'icon' => 'public-realm-icon.svg', 'label' => 'Public realm'],
            'community'    => ['color' => '#8562a5', 'icon' => 'community-icon.svg', 'label' => 'Community'],
        ];
    }
}

if (!function_exists('matrix_dz_take_action_archive_settings')) {
    /**
     * @return array{title:string,intro:string,sample_note:string,search_placeholder:string,page_size:int}
     */
    function matrix_dz_take_action_archive_settings(): array
    {
        $group = function_exists('get_field') ? get_field('take_action_archive', 'option') : null;
        if (!is_array($group)) {
            $group = [];
        }

        $page_size = (int) ($group['page_size'] ?? 9);
        if ($page_size < 1) {
            $page_size = 9;
        }

        return [
            'title'              => (string) ($group['title'] ?? __('Take action in Maynooth', 'matrix-starter')),
            'intro'              => (string) ($group['intro'] ?? __('Practical pathways to cut carbon at home, on the move, and in the community — pick a theme and follow the steps.', 'matrix-starter')),
            'sample_note'        => (string) ($group['sample_note'] ?? ''),
            'search_placeholder' => (string) ($group['search_placeholder'] ?? __('Search actions by name', 'matrix-starter')),
            'page_size'          => $page_size,
        ];
    }
}

if (!function_exists('matrix_dz_take_action_directory_data')) {
    /**
     * JSON payload for the Take Action directory (no category filters).
     *
     * @return array{actions:list<array<string,mixed>>,pageSize:int}
     */
    function matrix_dz_take_action_directory_data(): array
    {
        $settings = matrix_dz_take_action_archive_settings();
        $defaults = matrix_dz_take_action_theme_defaults();

        $query = new WP_Query([
            'post_type'      => 'take_action',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);

        $actions = [];
        foreach ($query->posts as $post) {
            $id    = (int) $post->ID;
            $style = (string) (get_field('ta_tag_style', $id) ?: 'retrofit');
            $theme = $defaults[$style] ?? $defaults['retrofit'];
            $label = (string) (get_field('ta_tag_label', $id) ?: $theme['label']);
            $intro = (string) (get_field('ta_intro', $id) ?: '');
            if ($intro === '') {
                $intro = (string) get_the_excerpt($post);
            }
            $title = (string) (get_field('ta_title', $id) ?: get_the_title($id));
            $image = get_the_post_thumbnail_url($id, 'large') ?: '';

            $icon_url = matrix_dz_assets_url($theme['icon']);
            $icon     = get_field('ta_tag_icon', $id);
            if (is_array($icon) && !empty($icon['url'])) {
                $icon_url = $icon['url'];
            }

            $actions[] = [
                'id'          => $post->post_name,
                'title'       => $title,
                'theme'       => $label,
                'themeColor'  => $theme['color'],
                'themeIcon'   => $icon_url,
                'description' => $intro,
                'image'       => $image,
                'url'         => get_permalink($id),
                'date'        => (int) get_post_time('U', true, $post),
            ];
        }
        wp_reset_postdata();

        return [
            'actions'  => $actions,
            'pageSize' => $settings['page_size'],
        ];
    }
}
