<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Tailwind breakpoint presets (Matrix Starter defaults).
 *
 * @return array<string,array{label:string,px:int,prompt:string}>
 */
function matrix_db_breakpoint_presets() {
    $presets = array(
        'lg'   => array(
            'label'  => 'lg — tablet / desktop switch (1084px)',
            'px'     => 1084,
            'prompt' => 'max-lg: (below 1084px)',
        ),
        'tab'  => array(
            'label'  => 'tab — tablet (993px)',
            'px'     => 993,
            'prompt' => 'max-tab: (below 993px)',
        ),
        'md'   => array(
            'label'  => 'md — mobile landscape (768px)',
            'px'     => 768,
            'prompt' => 'max-md: (below 768px)',
        ),
        'mob'  => array(
            'label'  => 'mob — mobile (575px)',
            'px'     => 575,
            'prompt' => 'max-mob: (below 575px)',
        ),
        'sm'   => array(
            'label'  => 'sm — small (640px)',
            'px'     => 640,
            'prompt' => 'max-sm: (below 640px)',
        ),
    );

    return apply_filters('matrix_db_breakpoint_presets', $presets);
}

/**
 * Default breakpoint keys for tablet and mobile layouts.
 *
 * @return array{tablet:string,mobile:string}
 */
function matrix_db_default_breakpoints() {
    return array(
        'tablet' => 'lg',
        'mobile' => 'md',
    );
}

/**
 * Optional component / behaviour flags to steer the agent.
 *
 * @return array<string,array<string,array{label:string,description:string,prompt:string,types:array<int,string>}>>
 */
function matrix_db_section_feature_registry() {
    $all_types = array_keys(matrix_db_section_types());

    $registry = array(
        'counter'        => array(
            'label'       => 'Counter / stats',
            'description' => 'Animated or static number counters',
            'prompt'      => 'Includes counter/stats. Use accessible markup; prefer CSS or lightweight JS consistent with theme patterns.',
            'types'       => $all_types,
        ),
        'slider'         => array(
            'label'       => 'Slider / carousel',
            'description' => 'Horizontal content slider',
            'prompt'      => 'Includes a slider/carousel. Ensure keyboard focus, aria labels, and pause controls where appropriate.',
            'types'       => array('flexi', 'form_block', 'hero', 'blog'),
        ),
        'tabs'           => array(
            'label'       => 'Tabs',
            'description' => 'Tabbed content panels',
            'prompt'      => 'Includes tabs. Use button roles, aria-selected, and keyboard navigation.',
            'types'       => array('flexi', 'form_block', 'hero'),
        ),
        'accordion'      => array(
            'label'       => 'Accordion',
            'description' => 'Expand/collapse panels',
            'prompt'      => 'Includes accordion. Use aria-expanded and associate headers with panels.',
            'types'       => array('flexi', 'form_block'),
        ),
        'video'          => array(
            'label'       => 'Video',
            'description' => 'Background or inline video',
            'prompt'      => 'Includes video. Respect prefers-reduced-motion; provide poster/fallback image.',
            'types'       => array('flexi', 'hero', 'single_hero', 'form_block'),
        ),
        'cards_hover'    => array(
            'label'       => 'Cards with hover state',
            'description' => 'Card lift, colour, or shadow on hover',
            'prompt'      => 'Cards have a distinct hover state. Implement with Tailwind hover:, group-hover:, and transition utilities. If a hover Figma frame is provided, match it exactly.',
            'types'       => array('flexi', 'form_block', 'hero', 'blog'),
        ),
        'mega_menu'      => array(
            'label'       => 'Mega menu dropdown',
            'description' => 'Full-width or multi-column nav dropdown',
            'prompt'      => 'Navbar uses a mega menu dropdown. Implement accessible flyout/mega panel with focus trap considerations and keyboard escape.',
            'types'       => array('header'),
        ),
        'dropdown_nav'   => array(
            'label'       => 'Dropdown navigation',
            'description' => 'Standard nested dropdown menus',
            'prompt'      => 'Navbar includes dropdown submenus. Use aria-haspopup, aria-expanded, and keyboard-friendly open/close.',
            'types'       => array('header'),
        ),
        'sticky_header'  => array(
            'label'       => 'Sticky / shrink on scroll',
            'description' => 'Header sticks or compacts when scrolling',
            'prompt'      => 'Header is sticky or shrinks on scroll. Use Tailwind sticky/top-0 and document any JS needed in template comments only if unavoidable.',
            'types'       => array('header'),
        ),
        'mobile_drawer'  => array(
            'label'       => 'Mobile menu drawer',
            'description' => 'Hamburger opens off-canvas menu',
            'prompt'      => 'Mobile navigation uses a drawer/off-canvas pattern. Match the mobile Figma frame; trap focus while open.',
            'types'       => array('header'),
        ),
        'search'         => array(
            'label'       => 'Search',
            'description' => 'Header search field or toggle',
            'prompt'      => 'Includes search UI in the header. Use accessible form labels and focus styles.',
            'types'       => array('header'),
        ),
        'cta_buttons'    => array(
            'label'       => 'Multiple CTAs',
            'description' => 'Primary + secondary buttons',
            'prompt'      => 'Section includes primary and secondary CTAs. Use ACF Link fields and .btn patterns with scoped focus styles.',
            'types'       => array('flexi', 'form_block', 'hero', 'single_hero'),
        ),
        'image_gallery'  => array(
            'label'       => 'Image gallery',
            'description' => 'Grid or mosaic of images',
            'prompt'      => 'Includes an image gallery. Use matrix_render_attachment_image() for SVG/logos.',
            'types'       => array('flexi', 'form_block', 'blog'),
        ),
        'form_fields'    => array(
            'label'       => 'Form fields',
            'description' => 'Contact or lead capture form',
            'prompt'      => 'Includes form fields. Wire to theme form patterns; label every input; do not skip error states.',
            'types'       => array('form_block', 'flexi'),
        ),
    );

    return apply_filters('matrix_db_section_feature_registry', $registry);
}

/**
 * Features available for a section type.
 *
 * @param string $section_type
 * @return array<string,array<string,mixed>>
 */
function matrix_db_features_for_type($section_type) {
    $registry = matrix_db_section_feature_registry();
    $type     = $section_type !== '' ? $section_type : 'flexi';
    $out      = array();

    foreach ($registry as $key => $feature) {
        $types = $feature['types'] ?? array();
        if (in_array($type, $types, true)) {
            $out[ $key ] = $feature;
        }
    }

    return $out;
}

/**
 * @param array<string,mixed> $section
 * @return array<int,string>
 */
function matrix_db_section_feature_keys($section) {
    $raw = $section['features'] ?? array();
    if (! is_array($raw)) {
        return array();
    }

    return array_values(array_filter(array_map('sanitize_key', $raw)));
}

/**
 * Prompt lines for selected features, responsive frames, and breakpoints.
 *
 * @param array<string,mixed> $section
 * @return array<int,string>
 */
function matrix_db_section_ai_context_lines($section) {
    $lines    = array();
    $registry = matrix_db_section_feature_registry();
    $features = matrix_db_section_feature_keys($section);

    if (! empty($features)) {
        $lines[] = '## Component features (implement all that apply)';
        foreach ($features as $key) {
            if (! empty($registry[ $key ]['prompt'])) {
                $lines[] = '- ' . $registry[ $key ]['label'] . ': ' . $registry[ $key ]['prompt'];
            }
        }
        $lines[] = '';
    }

    $bps     = matrix_db_section_breakpoints($section);
    $presets = matrix_db_breakpoint_presets();
    $lines[] = '## Responsive breakpoints';
    $lines[] = 'Use Matrix Starter Tailwind screens. Switch layout at:';
    if (! empty($presets[ $bps['tablet'] ])) {
        $lines[] = '- Tablet layout applies below: ' . $presets[ $bps['tablet'] ]['prompt'];
    }
    if (! empty($presets[ $bps['mobile'] ])) {
        $lines[] = '- Mobile layout applies below: ' . $presets[ $bps['mobile'] ]['prompt'];
    }
    $lines[] = 'Prefer max-lg:, max-md:, max-mob: utilities matching these breakpoints — not arbitrary media queries.';
    $lines[] = '';

    $views = matrix_db_section_figma_views($section);
    if (! empty($views)) {
        $lines[] = '## Additional Figma frames (variants)';
        foreach ($views as $view_key => $view) {
            if (empty($view['figma_url']) && empty($view['figma_node'])) {
                continue;
            }
            $label = ucfirst($view_key);
            if ($view_key === 'hover') {
                $label = 'Hover state';
            }
            $lines[] = $label . ': ' . ($view['figma_url'] ?? '');
            if (! empty($view['figma_file']) && ! empty($view['figma_node'])) {
                $lines[] = '  Figma MCP: fileKey=' . $view['figma_file'] . ' nodeId=' . $view['figma_node'];
            }
            if ($view_key === 'hover') {
                $lines[] = '  Implement hover/focus-visible styles to match this frame. Use Tailwind hover:, group-hover:, transition, and focus-visible:ring.';
            }
        }
        $lines[] = '';
    }

    if (! empty($section['feature_notes'])) {
        $lines[] = '## Additional build notes';
        $lines[] = (string) $section['feature_notes'];
        $lines[] = '';
    }

    return $lines;
}

/**
 * @param array<string,mixed> $section
 * @return array{tablet:string,mobile:string}
 */
function matrix_db_section_breakpoints($section) {
    $defaults = matrix_db_default_breakpoints();
    $bps      = is_array($section['breakpoints'] ?? null) ? $section['breakpoints'] : array();

    return array(
        'tablet' => sanitize_key($bps['tablet'] ?? $defaults['tablet']),
        'mobile' => sanitize_key($bps['mobile'] ?? $defaults['mobile']),
    );
}

/**
 * @param array<string,mixed> $section
 * @return array<string,array{figma_url:string,figma_file:string,figma_node:string}>
 */
function matrix_db_section_figma_views($section) {
    $raw  = is_array($section['figma_views'] ?? null) ? $section['figma_views'] : array();
    $keys = array('tablet', 'mobile', 'hover');
    $out  = array();

    foreach ($keys as $key) {
        $row = is_array($raw[ $key ] ?? null) ? $raw[ $key ] : array();
        $url = matrix_db_parse_figma_url($row['figma_url'] ?? '');
        $out[ $key ] = array(
            'figma_url'  => $url['url'],
            'figma_file' => $url['file_key'],
            'figma_node' => $url['node_id'],
        );
    }

    return $out;
}

/**
 * Design brief blocks for optional responsive / hover Figma nodes.
 *
 * @param array<string,mixed> $section
 * @return array<int,string>
 */
function matrix_db_figma_view_brief_prompt_lines($section) {
    $lines = array();
    $views = matrix_db_section_figma_views($section);
    $labels = array(
        'tablet' => 'Tablet Figma design context',
        'mobile' => 'Mobile Figma design context',
        'hover'  => 'Hover Figma design context',
    );

    foreach ($views as $key => $view) {
        if ($view['figma_file'] === '' || $view['figma_node'] === '') {
            continue;
        }
        $brief = matrix_db_figma_design_brief($view['figma_file'], $view['figma_node']);
        if (is_wp_error($brief)) {
            continue;
        }
        $lines[] = '## ' . ($labels[ $key ] ?? $key);
        $lines[] = (string) $brief;
        $lines[] = '';
    }

    return $lines;
}
