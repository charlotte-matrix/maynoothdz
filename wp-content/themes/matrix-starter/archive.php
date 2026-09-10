<?php
/**
 * Archive template.
 * Uses subhero + blog listing + newsletter to match News & Media structure.
 */

get_header();
?>
<main id="main-content" class="site-main">
<?php

$queried_object    = get_queried_object();
$is_people_archive = is_post_type_archive('people');
$is_faq_archive    = is_post_type_archive('faq');
$is_post_archive   = is_category() || is_tag() || is_author() || is_date() || is_search();

if ($is_post_archive) {
    $term = $queried_object instanceof WP_Term ? $queried_object : null;
    get_template_part('template-parts/hero/subhero', null, matrix_pace_blog_subhero_args($term));
    get_template_part('template-parts/blog/pace-listing');
    get_template_part('template-parts/flexi/newsletter_001');
} else {
    $archive_title = is_category()
        ? single_cat_title('', false)
        : wp_strip_all_tags(get_the_archive_title());
    $archive_title = preg_replace('/^\s*Archives?:\s*/i', '', (string) $archive_title);
    $archive_desc  = trim((string) get_the_archive_description());
    if ($archive_desc === '') {
        if ($is_people_archive) {
            $archive_desc = 'Meet the Sanctuary Runners team.';
        } elseif ($is_faq_archive) {
            $archive_desc = 'Browse all frequently asked questions.';
        } else {
            $archive_desc = 'Browse updates, stories, and announcements from Sanctuary Runners.';
        }
    }
    $posts_page_id   = (int) get_option('page_for_posts');
    $news_media_url  = $posts_page_id ? get_permalink($posts_page_id) : home_url('/news-and-media/');
    $faq_archive_url = get_post_type_archive_link('faq') ?: home_url('/faqs/');

    if ($is_people_archive) {
        $archive_parent_label = 'Our Team';
        $archive_parent_url   = home_url('/about/our-team/');
    } elseif ($is_faq_archive) {
        $archive_parent_label = 'FAQs';
        $archive_parent_url   = $faq_archive_url;
    } else {
        $archive_parent_label = 'News & Media';
        $archive_parent_url   = $news_media_url;
    }

    $breadcrumbs = [[
        'title'      => 'Home',
        'url'        => home_url('/'),
        'is_current' => false,
    ]];

    if ($is_faq_archive) {
        $breadcrumbs[] = [
            'title'      => 'FAQs',
            'url'        => '',
            'is_current' => true,
        ];
    } else {
        $breadcrumbs[] = [
            'title'      => $archive_parent_label,
            'url'        => $archive_parent_url,
            'is_current' => false,
        ];
        $breadcrumbs[] = [
            'title'      => $archive_title !== '' ? $archive_title : 'Archive',
            'url'        => '',
            'is_current' => true,
        ];
    }

    get_template_part('template-parts/hero/subhero', null, matrix_get_archive_subhero_media_args($queried_object, [
        'heading'            => $archive_title !== '' ? $archive_title : 'Archive',
        'heading_tag'        => 'h1',
        'content'            => $archive_desc,
        'layout_option'      => 'layout_2',
        'background_color'   => '#EEF6FC',
        'use_white_text'     => false,
        'custom_breadcrumbs' => true,
        'breadcrumbs'        => $breadcrumbs,
        'primary_cta'        => null,
        'secondary_cta'      => null,
    ]));

    if ($is_people_archive) {
        get_template_part('template-parts/flexi/people_listing');
    } elseif ($is_faq_archive) {
        get_template_part('template-parts/flexi/faq_listing');
    } else {
        get_template_part('template-parts/flexi/blog_listing');
        get_template_part('template-parts/flexi/newsletter_001');
    }
}
?>
</main>
<?php
get_footer();

