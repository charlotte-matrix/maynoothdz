<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Flexi block quality standards injected into every agent prompt.
 *
 * @return array<int,string>
 */
function matrix_db_prompt_flexi_standards() {
    return array(
        '## Flexi quality standards (required — match Cursor app / AGENTS.md)',
        '',
        'Before finishing each flexi block, read `docs/flexi-blocks-basics.md` and `AGENTS.md`.',
        '',
        'ACF (`acf-fields/partials/blocks/acf_{layout}.php`):',
        '- FieldsBuilder with human-readable block label.',
        '- Content tab (+ Design tab when background colour etc. is needed).',
        '- Do NOT add `padding_settings` or padding repeaters — spacing is Tailwind only.',
        '- Every heading needs a companion `heading_tag` select with ALL choices: h1, h2, h3, h4, h5, h6, p, span.',
        '- Image fields: allow svg,png,jpg,jpeg,webp where logos/icons appear.',
        '- Decorative vectors: ACF image fields + `matrix_render_attachment_image()` — never hand-author complex SVG path `d=` markup.',
        '',
        'Template (`template-parts/flexi/{layout}.php`):',
        '- Flat template: `get_sub_field()` only — no outer `have_rows()` loop.',
        '- Do NOT render Figma layers listed as hidden / under Excluded (hidden in Figma) in the design brief.',
        '- Unique `$section_id` on `<section>`; `role="region"` + `aria-labelledby` when a heading exists.',
        '- Section: `relative flex overflow-hidden`; one inner wrapper with `max-w-[{figmaFrameWidth}px]` + `max-lg:px-5` (from Figma — do NOT stack `max-w-container` and a second max-width).',
        '- Vertical spacing via Tailwind `py-*` on section or inner wrapper.',
        '- Copy exact hex, font-size, line-height, gap, and padding from Implementation tokens — no rounding unless the brief omits a value.',
        '- Validate heading tag before output:',
        '  `$allowed_heading_tags = [\'h1\',\'h2\',\'h3\',\'h4\',\'h5\',\'h6\',\'p\',\'span\'];`',
        '- Logos/SVG: use `matrix_render_attachment_image()` with `max_height_class` — not raw `<img>` for SVG.',
        '- Decorative SVGs: `pointer-events-none absolute … aria-hidden="true"` sized per brief; do NOT add CSS `opacity-*` on the wrapper when the SVG encodes path opacity.',
        '- Escape all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).',
        '- CTAs: ACF Link array + `.btn` + scoped focus `<style>` when buttons exist.',
        '- Hover states: when a hover Figma frame is provided, match colours/shadows/transform with `hover:`, `group-hover:`, `transition`, and `focus-visible:` — not JS-only hovers for simple cards.',
        '',
        'Finish checklist per flexi layout:',
        '- `preflight_flexi_block { layout }` must pass.',
        '- `validate_flexi_a11y_conventions { layout }` must pass.',
        '- `theme_build` after Tailwind class changes.',
        '- On redo: overwrite existing ACF + template files for the layout slug.',
    );
}
