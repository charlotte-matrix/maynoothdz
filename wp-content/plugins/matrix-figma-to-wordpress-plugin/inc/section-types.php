<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Section type definitions for the optional helper dropdown.
 *
 * @return array<string,array{label:string,drop_in:string,library_hint:string,has_acf:bool}>
 */
function matrix_db_section_types() {
    $types = array(
        'flexi'        => array(
            'label'         => 'Flexi (default)',
            'drop_in'       => 'acf-fields/partials/blocks/acf_{layout}.php + template-parts/flexi/{layout}.php',
            'library_hint'  => 'content',
            'has_acf'       => true,
            'acf_path'      => 'acf-fields/partials/blocks/acf_{layout}.php',
            'template_path' => 'template-parts/flexi/{layout}.php',
        ),
        'hero'         => array(
            'label'         => 'Hero',
            'drop_in'       => 'acf-fields/partials/hero/acf_{layout}.php + template-parts/hero/{layout}.php',
            'library_hint'  => 'hero',
            'has_acf'       => true,
            'acf_path'      => 'acf-fields/partials/hero/acf_{layout}.php',
            'template_path' => 'template-parts/hero/{layout}.php',
        ),
        'single_hero'  => array(
            'label'         => 'Single hero',
            'drop_in'       => 'acf-fields/partials/hero/acf_{layout}.php + template-parts/single/{layout}.php',
            'library_hint'  => 'single-hero',
            'has_acf'       => true,
            'acf_path'      => 'acf-fields/partials/hero/acf_{layout}.php',
            'template_path' => 'template-parts/single/{layout}.php',
        ),
        'header'       => array(
            'label'         => 'Navbar / Header',
            'drop_in'       => 'template-parts/header/{layout}.php',
            'library_hint'  => 'navigation-desktop',
            'has_acf'       => false,
            'acf_path'      => '',
            'template_path' => 'template-parts/header/{layout}.php',
        ),
        'footer'       => array(
            'label'         => 'Footer',
            'drop_in'       => 'template-parts/footer/{layout}.php',
            'library_hint'  => 'footer',
            'has_acf'       => false,
            'acf_path'      => '',
            'template_path' => 'template-parts/footer/{layout}.php',
        ),
        'blog'         => array(
            'label'         => 'Blog',
            'drop_in'       => 'template-parts/blog/{layout}.php',
            'library_hint'  => 'blog',
            'has_acf'       => false,
            'acf_path'      => '',
            'template_path' => 'template-parts/blog/{layout}.php',
        ),
        '404'          => array(
            'label'         => '404',
            'drop_in'       => 'template-parts/404/{layout}.php',
            'library_hint'  => '404',
            'has_acf'       => false,
            'acf_path'      => '',
            'template_path' => 'template-parts/404/{layout}.php',
        ),
        'sitemap'      => array(
            'label'         => 'Sitemap',
            'drop_in'       => 'templates/{layout}.php',
            'library_hint'  => 'sitemap',
            'has_acf'       => false,
            'acf_path'      => '',
            'template_path' => 'templates/{layout}.php',
        ),
        'form_block'   => array(
            'label'         => 'Form block (flexi)',
            'drop_in'       => 'acf-fields/partials/blocks/acf_{layout}.php + template-parts/flexi/{layout}.php',
            'library_hint'  => 'contact',
            'has_acf'       => true,
            'acf_path'      => 'acf-fields/partials/blocks/acf_{layout}.php',
            'template_path' => 'template-parts/flexi/{layout}.php',
        ),
        'cpt'          => array(
            'label'         => 'CPT',
            'drop_in'       => 'inc/cpts/post-types/{layout}.php',
            'library_hint'  => 'custom-post-types',
            'has_acf'       => false,
            'acf_path'      => '',
            'template_path' => 'inc/cpts/post-types/{layout}.php',
        ),
        'taxonomy'     => array(
            'label'         => 'Taxonomy',
            'drop_in'       => 'inc/cpts/taxonomies/{layout}.php',
            'library_hint'  => 'taxonomies',
            'has_acf'       => false,
            'acf_path'      => '',
            'template_path' => 'inc/cpts/taxonomies/{layout}.php',
        ),
        'theme_option' => array(
            'label'         => 'Theme option',
            'drop_in'       => 'inc/theme-options/{layout}.php',
            'library_hint'  => 'theme-options',
            'has_acf'       => false,
            'acf_path'      => '',
            'template_path' => 'inc/theme-options/{layout}.php',
        ),
    );

    /**
     * Filter section types for project-specific conventions.
     *
     * @param array<string,array<string,mixed>> $types
     */
    return apply_filters('matrix_db_section_types', $types);
}

/**
 * @param string $type_key
 * @return array<string,mixed>
 */
function matrix_db_get_section_type($type_key) {
    $types = matrix_db_section_types();
    $key   = $type_key !== '' ? $type_key : 'flexi';
    return isset($types[ $key ]) ? $types[ $key ] : $types['flexi'];
}

/**
 * Resolve theme file paths for a section.
 *
 * @param array<string,mixed> $section
 * @return array{acf:string,template:string}
 */
function matrix_db_section_theme_paths($section) {
    $type   = matrix_db_get_section_type($section['section_type'] ?? 'flexi');
    $layout = $section['layout'] ?? '';
    $acf    = '';
    $tpl    = '';

    if (! empty($type['acf_path'])) {
        $acf = str_replace('{layout}', $layout, $type['acf_path']);
    }
    if (! empty($type['template_path'])) {
        $tpl = str_replace('{layout}', $layout, $type['template_path']);
    }

    return array(
        'acf'      => $acf,
        'template' => $tpl,
    );
}
