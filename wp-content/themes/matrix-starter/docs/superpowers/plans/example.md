# What We Offer Intro Two Column Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing `what_we_offer` flexi block with a new `intro_two_column` layout variant that matches Figma node `2888:3479` while preserving the current image-led layout.

**Architecture:** Add one small helper layer to normalize the layout style and service accent colors, then update the existing ACF builder and template to branch between `image_feature` and `intro_two_column`. Seed `/flexi/` with both variants so the reuse strategy is visibly proven.

**Tech Stack:** WordPress PHP, ACF Builder, existing flexi block templates, Pest/PHPUnit, local `wp eval` seeding, and local browser verification.

---

## File Structure

- `inc/what-we-offer-functions.php`
  Small helper file for layout-style normalization and accent-color resolution.
- `functions.php`
  Loads the new `what-we-offer` helper file.
- `tests/Unit/WhatWeOfferLayoutTest.php`
  Focused tests for layout-style fallback and accent-color fallback behavior.
- `acf-fields/partials/blocks/acf_what_we_offer.php`
  Adds `layout_style`, `intro_text`, and per-row `accent_color` with conditional logic.
- `template-parts/flexi/what_we_offer.php`
  Renders the current `image_feature` layout and the new `intro_two_column` layout.

No new block file should be created. This remains a layout extension of `what_we_offer`.

### Task 1: Add helper coverage first

**Files:**
- Create: `inc/what-we-offer-functions.php`
- Modify: `functions.php`
- Create: `tests/Unit/WhatWeOfferLayoutTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/WhatWeOfferLayoutTest.php` with:

```php
<?php

require_once dirname(__DIR__, 2) . '/inc/what-we-offer-functions.php';

test('what we offer layout style falls back to image_feature', function () {
    expect(function_exists('matrix_resolve_what_we_offer_layout_style'))->toBeTrue();

    expect(matrix_resolve_what_we_offer_layout_style('intro_two_column'))->toBe('intro_two_column')
        ->and(matrix_resolve_what_we_offer_layout_style('image_feature'))->toBe('image_feature')
        ->and(matrix_resolve_what_we_offer_layout_style(''))->toBe('image_feature')
        ->and(matrix_resolve_what_we_offer_layout_style('unknown'))->toBe('image_feature');
});

test('what we offer accent color uses explicit value first', function () {
    expect(function_exists('matrix_get_what_we_offer_accent_color'))->toBeTrue();

    $row = ['accent_color' => '#B4A8CE'];

    expect(matrix_get_what_we_offer_accent_color($row, 0))->toBe('#B4A8CE');
});

test('what we offer accent color rotates through fallback palette', function () {
    expect(matrix_get_what_we_offer_accent_color([], 0))->toBe('#6FC9C0')
        ->and(matrix_get_what_we_offer_accent_color([], 1))->toBe('#C3DBAE')
        ->and(matrix_get_what_we_offer_accent_color([], 2))->toBe('#B4A8CE')
        ->and(matrix_get_what_we_offer_accent_color([], 3))->toBe('#E4B8D6')
        ->and(matrix_get_what_we_offer_accent_color([], 4))->toBe('#6FC9C0');
});
```

- [ ] **Step 2: Run the test to verify RED**

Run: `php vendor/bin/pest "tests/Unit/WhatWeOfferLayoutTest.php"`

Expected: FAIL because the helper file/functions do not exist yet.

- [ ] **Step 3: Add the minimal helper implementation**

Create `inc/what-we-offer-functions.php` with:

```php
<?php

function matrix_resolve_what_we_offer_layout_style($value)
{
    $value = is_string($value) ? trim($value) : '';

    if ($value === 'intro_two_column') {
        return 'intro_two_column';
    }

    return 'image_feature';
}

function matrix_get_what_we_offer_accent_palette()
{
    return [
        '#6FC9C0',
        '#C3DBAE',
        '#B4A8CE',
        '#E4B8D6',
    ];
}

function matrix_get_what_we_offer_accent_color($service_row, $index = 0)
{
    $accent_color = is_array($service_row) ? trim((string) ($service_row['accent_color'] ?? '')) : '';

    if ($accent_color !== '') {
        return $accent_color;
    }

    $palette = matrix_get_what_we_offer_accent_palette();

    if ($palette === []) {
        return '#6FC9C0';
    }

    return $palette[max(0, (int) $index) % count($palette)];
}
```

Add this near the other helper includes in `functions.php`:

```php
require_once get_template_directory() . '/inc/what-we-offer-functions.php';
```

- [ ] **Step 4: Run the test to verify GREEN**

Run: `php vendor/bin/pest "tests/Unit/WhatWeOfferLayoutTest.php"`

Expected: PASS.

### Task 2: Extend the ACF builder

**Files:**
- Modify: `acf-fields/partials/blocks/acf_what_we_offer.php`

- [ ] **Step 1: Add the new layout-level fields**

Add near the top of the content tab:

```php
->addSelect('layout_style', [
    'label' => 'Layout Style',
    'choices' => [
        'image_feature' => 'Image Feature',
        'intro_two_column' => 'Intro + Two Column',
    ],
    'default_value' => 'image_feature',
    'required' => 1,
    'ui' => 1,
])
->addTextarea('intro_text', [
    'label' => 'Intro Text',
    'rows' => 3,
    'new_lines' => 'br',
    'conditional_logic' => [
        [
            [
                'field' => 'layout_style',
                'operator' => '==',
                'value' => 'intro_two_column',
            ],
        ],
    ],
])
```

- [ ] **Step 2: Add per-row accent color**

Inside the `services` repeater add:

```php
->addColorPicker('accent_color', [
    'label' => 'Accent Color',
    'instructions' => 'Used for the left rail in the Intro + Two Column layout.',
    'conditional_logic' => [
        [
            [
                'field' => 'layout_style',
                'operator' => '==',
                'value' => 'intro_two_column',
            ],
        ],
    ],
])
```

- [ ] **Step 3: Hide the large image for the new layout**

Add conditional logic to `main_image` so it only shows for `image_feature`.

- [ ] **Step 4: Lint the builder**

Run: `php -l "acf-fields/partials/blocks/acf_what_we_offer.php"`

Expected: `No syntax errors detected`.

### Task 3: Render the new layout variant

**Files:**
- Modify: `template-parts/flexi/what_we_offer.php`

- [ ] **Step 1: Normalize the selected layout**

At the top of the template, resolve the style and intro text:

```php
$layout_style = function_exists('matrix_resolve_what_we_offer_layout_style')
    ? matrix_resolve_what_we_offer_layout_style(get_sub_field('layout_style'))
    : 'image_feature';
$intro_text = (string) get_sub_field('intro_text');
```

- [ ] **Step 2: Preserve the current layout as `image_feature`**

Wrap the current image-led rendering in:

```php
if ($layout_style === 'image_feature') {
    // existing layout
}
```

Keep the current output behavior intact.

- [ ] **Step 3: Add the `intro_two_column` branch**

Render:

- heading
- underline
- intro text when present
- 2-column services grid on desktop
- 1-column on mobile

Use a structure like:

```php
<?php if ($layout_style === 'intro_two_column') : ?>
    <div class="mt-16 w-full max-w-[1018px] max-md:mt-10">
        <?php if ($intro_text !== '') : ?>
            <div class="max-w-[606px] font-primary text-[16px] font-medium leading-[28px] text-[#08284B]">
                <?php echo wp_kses_post(wpautop($intro_text)); ?>
            </div>
        <?php endif; ?>

        <div class="mt-8 grid grid-cols-1 gap-x-10 gap-y-8 lg:grid-cols-2">
            <?php foreach ($services as $index => $service) : ?>
                <?php $accent_color = matrix_get_what_we_offer_accent_color($service, $index); ?>
                <article class="flex gap-6 items-start min-h-[140px]">
                    <div class="shrink-0 w-[40px] rounded-[4px]" style="background-color: <?php echo esc_attr($accent_color); ?>; min-height: 140px;"></div>
                    <div class="flex flex-col gap-4 min-w-0">
                        <!-- title + optional arrow + description -->
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
```

- [ ] **Step 4: Keep the service link behavior**

For linked rows, keep the title row interactive. For unlinked rows, render static content only.

- [ ] **Step 5: Lint the template**

Run: `php -l "template-parts/flexi/what_we_offer.php"`

Expected: `No syntax errors detected`.

### Task 4: Seed `/flexi/` with both layouts

**Files:**
- Modify WordPress data only on page ID `329`

- [ ] **Step 1: Preserve the existing demo row**

Inspect the current `/flexi/` rows first so the existing `what_we_offer` example is not lost.

- [ ] **Step 2: Add a second `what_we_offer` row for `intro_two_column`**

Seed a new row with:

- `layout_style` = `intro_two_column`
- heading
- intro text
- four services
- four accent colors matching the Figma palette family
- no `main_image`

Use service titles similar to the Figma:

- `Inpatient Care`
- `St Patrick's at Home`
- `Outpatient Care - Dean Clinics`
- `Day Programmes`

- [ ] **Step 3: Verify the row exists**

Run:

```bash
curl -s "http://localhost:10034/flexi/" | rg -n "Inpatient Care|St Patrick's at Home|Day Programmes|Outpatient Care"
```

Expected: matches for the new variant’s seeded content.

### Task 5: Build and verify

**Files:**
- Review only unless focused fixes are needed

- [ ] **Step 1: Rebuild assets**

Run: `npm run build`

Expected: exit code `0`.

- [ ] **Step 2: Run focused tests and lints**

Run:

```bash
php vendor/bin/pest "tests/Unit/WhatWeOfferLayoutTest.php"
php -l "inc/what-we-offer-functions.php"
php -l "acf-fields/partials/blocks/acf_what_we_offer.php"
php -l "template-parts/flexi/what_we_offer.php"
```

Expected: tests pass and all PHP files lint cleanly.

- [ ] **Step 3: Check IDE diagnostics**

Read diagnostics for:

- `inc/what-we-offer-functions.php`
- `acf-fields/partials/blocks/acf_what_we_offer.php`
- `template-parts/flexi/what_we_offer.php`

Expected: no newly introduced issues.

- [ ] **Step 4: Browser-check `/flexi/`**

Verify:

- the original `what_we_offer` layout still renders
- the new `intro_two_column` layout appears as a second example
- the new variant has no large side image
- the new variant shows two columns on desktop
- the new variant collapses to one column on mobile
- linked service titles remain interactive

### Task 6: Final review

**Files:**
- Review only

- [ ] **Step 1: Confirm scope stayed right-sized**

Checklist:

- still one block, not two
- existing layout preserved
- new layout added
- intro text only used by new layout
- main image hidden for new layout
- no unrelated refactor introduced

- [ ] **Step 2: Note any acceptable approximation**

If the dark two-tone rail effect from the Figma was simplified to keep the code clean, record that explicitly in the final handoff.
