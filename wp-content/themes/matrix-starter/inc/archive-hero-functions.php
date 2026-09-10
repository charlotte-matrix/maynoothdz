<?php

if (!function_exists('matrix_get_archive_hero_settings')) {
    function matrix_get_archive_hero_settings(): array {
        static $settings = null;

        if ($settings !== null) {
            return $settings;
        }

        $settings = [
            'default_image' => 0,
        ];

        return $settings;
    }
}

if (!function_exists('matrix_get_archive_hero_image_id')) {
    function matrix_get_archive_hero_image_id($queried_object = null): int {
        $settings = matrix_get_archive_hero_settings();
        $image_id = (int) ($settings['default_image'] ?? 0);

        if (
            $queried_object instanceof WP_Term &&
            $queried_object->taxonomy === 'category' &&
            function_exists('get_field')
        ) {
            $term_image_id = (int) (get_field('archive_hero_svg', $queried_object->taxonomy . '_' . $queried_object->term_id) ?: 0);
            if ($term_image_id) {
                $image_id = $term_image_id;
            }
        }

        return $image_id;
    }
}

if (!function_exists('matrix_get_archive_hero_description')) {
    function matrix_get_archive_hero_description(string $default_description = '', $queried_object = null): string {
        $description = trim($default_description);

        if (
            $queried_object instanceof WP_Term &&
            $queried_object->taxonomy === 'category' &&
            function_exists('get_field')
        ) {
            $term_description = trim((string) get_field('archive_hero_description', $queried_object->taxonomy . '_' . $queried_object->term_id));
            if ($term_description !== '') {
                return $term_description;
            }
        }

        return $description;
    }
}

if (!function_exists('matrix_get_archive_subhero_media_args')) {
    function matrix_get_archive_subhero_media_args($queried_object = null, array $args = []): array {
        $image_id = matrix_get_archive_hero_image_id($queried_object);
        $default_description = array_key_exists('content', $args) ? (string) $args['content'] : '';

        return array_merge($args, [
            'content' => matrix_get_archive_hero_description($default_description, $queried_object),
            'image' => $image_id ?: null,
            'image_presentation' => $image_id ? 'full_height_right_svg' : 'default',
            'layout_2_desktop_min_height' => 450,
        ]);
    }
}
