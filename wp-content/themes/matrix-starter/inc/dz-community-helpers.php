<?php
/**
 * Community helpers — authors, posts feed, initials.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('matrix_dz_community_category_slug')) {
    function matrix_dz_community_category_slug(): string
    {
        return 'community';
    }
}

if (!function_exists('matrix_dz_is_community_post')) {
    function matrix_dz_is_community_post(int $post_id = 0): bool
    {
        if ($post_id <= 0) {
            $post_id = (int) get_the_ID();
        }
        return $post_id > 0 && has_category(matrix_dz_community_category_slug(), $post_id);
    }
}

if (!function_exists('matrix_dz_initials_from_name')) {
    function matrix_dz_initials_from_name(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return '';
        }
        $parts = explode(' ', $name);
        if (count($parts) === 1) {
            return strtoupper(mb_substr($parts[0], 0, 2));
        }
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
    }
}

if (!function_exists('matrix_dz_community_author_meta')) {
    /**
     * @return array{name:string,initials:string,is_council:bool,user_id:int}
     */
    function matrix_dz_community_author_meta(int $post_id): array
    {
        $user_id = (int) get_post_field('post_author', $post_id);
        $user    = $user_id ? get_userdata($user_id) : false;
        $name    = $user ? (string) $user->display_name : get_bloginfo('name');
        $group   = $user ? (string) get_user_meta($user_id, 'community_group_name', true) : '';
        if ($group !== '') {
            $name = $group;
        }

        $is_council = (bool) preg_match('/climate action|council/i', $name);

        return [
            'name'       => $name,
            'initials'   => matrix_dz_initials_from_name($name),
            'is_council' => $is_council,
            'user_id'    => $user_id,
        ];
    }
}

if (!function_exists('matrix_dz_community_posts_query')) {
    /**
     * @param array{count?:int,include?:int[]} $args
     */
    function matrix_dz_community_posts_query(array $args = []): WP_Query
    {
        $count   = max(1, (int) ($args['count'] ?? 6));
        $include = array_values(array_filter(array_map('intval', $args['include'] ?? [])));

        $query_args = [
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ];

        if ($include !== []) {
            $query_args['post__in']       = $include;
            $query_args['orderby']        = 'post__in';
            $query_args['posts_per_page'] = count($include);
        } else {
            $query_args['posts_per_page'] = $count;
            $query_args['orderby']        = 'date';
            $query_args['order']          = 'DESC';
            $query_args['category_name']  = matrix_dz_community_category_slug();
        }

        return new WP_Query($query_args);
    }
}

if (!function_exists('matrix_dz_upcoming_events')) {
    /**
     * @return WP_Post[]
     */
    function matrix_dz_upcoming_events(int $limit = 6): array
    {
        $today = gmdate('Y-m-d');
        $q     = new WP_Query([
            'post_type'      => 'event',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'meta_key'       => 'event_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => 'event_date',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
            ],
            'no_found_rows'  => true,
        ]);

        return $q->posts;
    }
}

if (!function_exists('matrix_dz_ensure_community_category')) {
    function matrix_dz_ensure_community_category(): int
    {
        $slug = matrix_dz_community_category_slug();
        $term = term_exists($slug, 'category');
        if (!$term) {
            $term = wp_insert_term('Community', 'category', [
                'slug'        => $slug,
                'description' => 'Community updates from local groups',
            ]);
        }
        if (is_wp_error($term)) {
            return 0;
        }
        return (int) (is_array($term) ? $term['term_id'] : $term);
    }
}

/**
 * Contributors only see their own posts & events in admin.
 */
add_action('pre_get_posts', function (WP_Query $query): void {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    global $pagenow;
    if ($pagenow !== 'edit.php') {
        return;
    }
    if (current_user_can('edit_others_posts')) {
        return;
    }
    $query->set('author', get_current_user_id());
});

/**
 * Auto-assign the Community category when contributors save posts.
 */
add_action('save_post_post', function (int $post_id, WP_Post $post, bool $update): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if ($post->post_type !== 'post') {
        return;
    }
    if (!is_user_logged_in() || current_user_can('edit_others_posts')) {
        return;
    }
    $cat_id = matrix_dz_ensure_community_category();
    if ($cat_id > 0) {
        wp_set_post_categories($post_id, [$cat_id], true);
    }
}, 20, 3);
