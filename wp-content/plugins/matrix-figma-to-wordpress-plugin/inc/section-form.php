<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Render one section card for the New build form.
 *
 * @param int                 $index
 * @param array<string,mixed> $section
 * @return void
 */
function matrix_db_render_section_card($index, $section = array()) {
    $section   = wp_parse_args($section, matrix_db_default_section());
    $types     = matrix_db_section_types();
    $type_key  = $section['section_type'] ?? 'flexi';
    $features  = matrix_db_section_feature_keys($section);
    $bps       = matrix_db_section_breakpoints($section);
    $views     = matrix_db_section_figma_views($section);
    $bp_opts   = matrix_db_breakpoint_presets();
    $type_feats = matrix_db_features_for_type($type_key);
    ?>
    <article class="matrix-db-section-card" data-index="<?php echo (int) $index; ?>">
        <header class="matrix-db-section-card__header">
            <h2 class="matrix-db-section-card__title">Section <span class="matrix-db-section-num"><?php echo (int) $index + 1; ?></span></h2>
            <button type="button" class="button-link-delete matrix-db-remove-card" aria-label="Remove section">Remove</button>
        </header>

        <div class="matrix-db-section-card__grid">
            <div class="matrix-db-field matrix-db-field--full">
                <label for="matrix-db-figma-<?php echo (int) $index; ?>">Desktop Figma frame</label>
                <textarea id="matrix-db-figma-<?php echo (int) $index; ?>" name="sections[<?php echo (int) $index; ?>][figma_url]" rows="2" class="large-text matrix-db-figma" placeholder="Paste Figma link or Copy example prompt"><?php echo esc_textarea($section['figma_url'] ?? ''); ?></textarea>
                <p class="description">Primary design reference. Wrapper text and <code>@</code> are stripped on paste.</p>
            </div>

            <div class="matrix-db-field">
                <label for="matrix-db-label-<?php echo (int) $index; ?>">Label</label>
                <input type="text" id="matrix-db-label-<?php echo (int) $index; ?>" name="sections[<?php echo (int) $index; ?>][label]" class="regular-text matrix-db-label" value="<?php echo esc_attr($section['label'] ?? ''); ?>" />
            </div>

            <div class="matrix-db-field">
                <label for="matrix-db-layout-<?php echo (int) $index; ?>">Layout slug</label>
                <input type="text" id="matrix-db-layout-<?php echo (int) $index; ?>" name="sections[<?php echo (int) $index; ?>][layout]" class="regular-text matrix-db-layout" placeholder="auto from label" value="<?php echo esc_attr($section['layout'] ?? ''); ?>" />
            </div>

            <div class="matrix-db-field">
                <label for="matrix-db-type-<?php echo (int) $index; ?>">Section type</label>
                <select id="matrix-db-type-<?php echo (int) $index; ?>" name="sections[<?php echo (int) $index; ?>][section_type]" class="matrix-db-section-type">
                    <?php foreach ($types as $key => $type) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($type_key, $key); ?>><?php echo esc_html($type['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <fieldset class="matrix-db-fieldset matrix-db-features-panel">
            <legend>Component features <span class="description">(helps the AI)</span></legend>
            <div class="matrix-db-feature-grid matrix-db-feature-list">
                <?php foreach ($type_feats as $fkey => $feature) : ?>
                    <label class="matrix-db-check">
                        <input type="checkbox" name="sections[<?php echo (int) $index; ?>][features][]" value="<?php echo esc_attr($fkey); ?>" <?php checked(in_array($fkey, $features, true)); ?> />
                        <span class="matrix-db-check__label"><?php echo esc_html($feature['label']); ?></span>
                        <?php if (! empty($feature['description'])) : ?>
                            <span class="matrix-db-check__desc"><?php echo esc_html($feature['description']); ?></span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="description matrix-db-features-empty" hidden>Select a section type to see available features.</p>
        </fieldset>

        <fieldset class="matrix-db-fieldset">
            <legend>Responsive Figma frames <span class="description">(optional)</span></legend>
            <div class="matrix-db-section-card__grid matrix-db-section-card__grid--3">
                <div class="matrix-db-field">
                    <label>Tablet frame</label>
                    <textarea name="sections[<?php echo (int) $index; ?>][figma_views][tablet][figma_url]" rows="2" class="large-text matrix-db-figma" placeholder="Figma link for tablet layout"><?php echo esc_textarea($views['tablet']['figma_url'] ?? ''); ?></textarea>
                </div>
                <div class="matrix-db-field">
                    <label>Mobile frame</label>
                    <textarea name="sections[<?php echo (int) $index; ?>][figma_views][mobile][figma_url]" rows="2" class="large-text matrix-db-figma" placeholder="Figma link for mobile layout"><?php echo esc_textarea($views['mobile']['figma_url'] ?? ''); ?></textarea>
                </div>
                <div class="matrix-db-field">
                    <label>Hover / variant frame</label>
                    <textarea name="sections[<?php echo (int) $index; ?>][figma_views][hover][figma_url]" rows="2" class="large-text matrix-db-figma" placeholder="Figma component hover or Property 1 = Hover"><?php echo esc_textarea($views['hover']['figma_url'] ?? ''); ?></textarea>
                    <p class="description">Paste the Figma <strong>Hover</strong> variant (e.g. Card → Property 1 = Hover).</p>
                </div>
            </div>
            <div class="matrix-db-section-card__grid matrix-db-breakpoint-row">
                <div class="matrix-db-field">
                    <label for="matrix-db-bp-tablet-<?php echo (int) $index; ?>">Tablet breakpoint</label>
                    <select id="matrix-db-bp-tablet-<?php echo (int) $index; ?>" name="sections[<?php echo (int) $index; ?>][breakpoints][tablet]">
                        <?php foreach ($bp_opts as $bkey => $bp) : ?>
                            <option value="<?php echo esc_attr($bkey); ?>" <?php selected($bps['tablet'], $bkey); ?>><?php echo esc_html($bp['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="matrix-db-field">
                    <label for="matrix-db-bp-mobile-<?php echo (int) $index; ?>">Mobile breakpoint</label>
                    <select id="matrix-db-bp-mobile-<?php echo (int) $index; ?>" name="sections[<?php echo (int) $index; ?>][breakpoints][mobile]">
                        <?php foreach ($bp_opts as $bkey => $bp) : ?>
                            <option value="<?php echo esc_attr($bkey); ?>" <?php selected($bps['mobile'], $bkey); ?>><?php echo esc_html($bp['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </fieldset>

        <div class="matrix-db-field matrix-db-field--full">
            <label for="matrix-db-notes-<?php echo (int) $index; ?>">Extra instructions for AI</label>
            <textarea id="matrix-db-notes-<?php echo (int) $index; ?>" name="sections[<?php echo (int) $index; ?>][feature_notes]" rows="3" class="large-text" placeholder="Anything else the agent should know…"><?php echo esc_textarea($section['feature_notes'] ?? ($section['instructions'] ?? '')); ?></textarea>
        </div>

        <input type="hidden" name="sections[<?php echo (int) $index; ?>][instructions]" value="" class="matrix-db-instructions-legacy" />
    </article>
    <?php
}
