# Flexi blocks basics

Convert static HTML into dynamic WordPress **ACF Flexible Content** blocks. Every element must be editable in the admin, output must follow theme conventions, and all markup must meet **WCAG 2.1 AA** (see [accessibility-basics.md](accessibility-basics.md)).

Related: [coding-guidelines.md](coding-guidelines.md), [desktop-menu-basics.md](desktop-menu-basics.md).

---

## Goal

> Convert the provided static HTML into a dynamic WordPress section using ACF Builder. Ensure all elements are dynamic and customizable through the WordPress admin panel. Adhere strictly to coding structure, conventions, and Tailwind classes. Ensure compatibility with assistive technologies (screen readers, keyboard users).

---

## File structure

Each flexi block is **two files**:

| File | Location | Purpose |
|------|----------|---------|
| ACF field definition | `acf-fields/…` or `examples/acf/flexi/acf_{layout}.php` | FieldsBuilder definition |
| Frontend template | `template-parts/flexi/{layout}.php` | Rendered on the page |

Reference implementations live under `examples/acf/flexi/` and `examples/flexi/` (e.g. `acf_content_002.php` + `content_002.php`).

The flexible content loader resolves: `template-parts/flexi/{layout}.php` (see `inc/flexible-content-functions.php`).

Production ACF definitions live in `acf-fields/partials/blocks/acf_{layout}.php` (auto-registered via `acf-fields/partials/flexi.php`).

---

## Flexi review page (`/flexi/`)

Use the **Flexi blocks review** page (`/flexi/`) to preview every production block with Figma-default content.

- **Seed / refresh:** `npm run pace:flexi-setup` (or `wp pace-flexi setup --force`)
- **Logic:** `inc/setup/pace-flexi-setup.php` — adds a page intro, a label row per block, then each block’s demo data
- **When you ship a new block:** add `acf_{layout}.php` + `template-parts/flexi/{layout}.php`, then register a seeder in `matrix_pace_flexi_review_layouts()` and bump `MATRIX_PACE_FLEXI_SETUP_VERSION`

---

## ACF Flexi rules

### Field access

- **Always** use `get_sub_field()` inside flexi layouts — **never** `get_field()` for layout fields.
- **Never** wrap the frontend template in outer flexible-content loops:

```php
// ❌ Do not do this in template-parts/flexi/*.php
<?php if (have_rows('content_014')): ?>
<?php while (have_rows('content_014')): the_row(); ?>
```

The parent flexible content handler already calls `the_row()`. Templates are flat output files.

### ACF Builder

- Define fields with **StoutLogic ACF Builder** (`FieldsBuilder`).
- **Always** set a human-readable block label matching the layout slug.

```php
<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$gallery_001 = new FieldsBuilder('gallery_001', [
    'label' => 'Gallery 001',
]);

// … tabs and fields …

return $gallery_001;
```

### Tabs (horizontal)

Organize fields into logical tabs:

| Tab | Use for |
|-----|---------|
| **Content** | Text, images, links, repeaters, WYSIWYG |
| **Design** | Background color, visual options (when required by design) |
| **Layout** | Margins, padding repeater, column order, alignment |

Use `->addTab('Content', ['label' => 'Content'])` etc.

### Links (buttons & CTAs)

**Always use a single ACF Link field** (`->addLink(…, ['return_format' => 'array'])`).

Never split URL, title, and target into separate fields.

```php
$button = get_sub_field('content_button');
if (is_array($button) && !empty($button['url']) && !empty($button['title'])) {
    // render <a>
}
```

### Other field rules

- Use **WYSIWYG** over plain textarea where rich text is needed.
- Add class **`wp_editor`** on wrappers that output WYSIWYG HTML.
- Include **placeholder/default values** and clear **instructions** on fields.
- Use **conditional logic** to show/hide optional elements.
- Blocks must be **reusable** across pages via ACF Flexible Content.

---

## Frontend template rules

### Variables first

Declare all `get_sub_field()` variables at the **top of the file**, before `<section>`, unless structurally impossible.

```php
<?php
$section_id = 'content-section-two-' . uniqid();
$heading = get_sub_field('heading');
$heading_tag = get_sub_field('heading_tag');
// …
```

### Section ID

Every section wrapper needs a **unique ID** (for scoped styles and ARIA):

```php
$section_id = 'gallery-001-' . wp_generate_uuid4();
// or
$section_id = 'content-section-two-' . uniqid();
```

### PHP style

- **No shorthand PHP** (`<?=`).
- **Escape all output**: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` for WYSIWYG.

### Tailwind & classes

- Use **Tailwind classes exclusively** for layout and typography.
- **Inline CSS** only for dynamic values (e.g. `background-color` from a color picker).
- **Do not** use `min-w-[240px]`, `aspect-[1.32]`, or similar aspect-ratio utilities — see [coding-guidelines.md](coding-guidelines.md).
- Prefer **Grid over Flex** for two-dimensional layouts.

### Section structure

```html
<section
  id="<?php echo esc_attr($section_id); ?>"
  class="relative flex overflow-hidden …"
  role="region"
  aria-labelledby="<?php echo esc_attr($section_id); ?>-heading"
>
  <div class="flex flex-col items-center w-full mx-auto max-w-container pt-5 pb-5 max-lg:px-5 <?php echo esc_attr(implode(' ', $padding_classes)); ?>">
    <!-- content -->
  </div>
</section>
```

Use `max-w-container` (theme token) or `max-w-[1088px]` when the Figma frame width differs.

Apply **padding repeater classes** on this inner wrapper div (same element as `max-w-container`).

---

## Headings

- Dynamic heading text field.
- Select field for tag with **all** options: `h1`, `h2`, `h3`, `h4`, `h5`, `h6`, `span`, `p`.
- Validate allowed tags in PHP before output.
- Tie heading `id` to section for `aria-labelledby`.

```php
$allowed_heading_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span'];
if (!in_array($heading_tag, $allowed_heading_tags, true)) {
    $heading_tag = 'h2';
}
```

```html
<<?php echo esc_attr($heading_tag); ?>
  id="<?php echo esc_attr($section_id); ?>-heading"
  class="…"
>
  <?php echo esc_html($heading); ?>
</<?php echo esc_attr($heading_tag); ?>>
```

---

## Images

- Read attachment ID from ACF; resolve URL and alt from WordPress media.
- Fallback alt to meaningful default (e.g. heading text or site name).

```php
$image = get_sub_field('image');
$image_alt = get_post_meta($image, '_wp_attachment_image_alt', true) ?: 'Content image';
```

Use Tailwind for sizing; `object-contain` / `object-cover` as appropriate.

---

## CTA buttons (required pattern)

Every dynamic CTA must follow this structure. **Do not** replace the focus/hover treatment with utilities alone — use `.btn` / `.btn-primary` **and** a scoped style block when custom colors are required.

### 1. Link element

```php
<?php if (is_array($content_button) && !empty($content_button['url']) && !empty($content_button['title'])) : ?>
  <?php $button_class = 'flexi-cta-' . wp_generate_uuid4(); ?>
  <a
    href="<?php echo esc_url($content_button['url']); ?>"
    target="<?php echo esc_attr($content_button['target'] ?? '_self'); ?>"
    <?php if (($content_button['target'] ?? '') === '_blank') : ?>rel="noopener noreferrer"<?php endif; ?>
    class="btn btn-primary inline-flex gap-2 justify-center items-center w-fit whitespace-nowrap px-6 py-3 … <?php echo esc_attr($button_class); ?>"
    aria-label="<?php echo esc_attr($content_button['title']); ?>"
  >
    <?php echo esc_html($content_button['title']); ?>
    <?php if ($show_icon) : ?>
      <svg … aria-hidden="true">…</svg>
    <?php endif; ?>
  </a>
<?php endif; ?>
```

- Escape all dynamic values.
- Include `aria-label`, `target`, and `rel="noopener noreferrer"` for `_blank`.
- Icons: inline SVG with `aria-hidden="true"`, Font Awesome from theme, or placeholder — **not** broken builder SVG imports.

### 2. Scoped style block (do not omit)

Place **immediately after** the closing `</a>` (or after the button wrapper). Use a **unique class per button instance** when multiple CTAs exist.

```php
<style>
#<?php echo esc_attr($section_id); ?> a.<?php echo esc_attr($button_class); ?>:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px var(--Turquoise-500, #1C959B);
}
#<?php echo esc_attr($section_id); ?> a.<?php echo esc_attr($button_class); ?>:hover {
  /* match design hover if not fully covered by .btn-primary */
}
</style>
```

This ensures keyboard focus visibility and contrast per WCAG 2.1. Do not skip even when hover/focus colors match the base.

---

## Padding repeater (Layout tab)

### ACF definition

```php
->addRepeater('padding_settings', [
    'label' => 'Padding Settings',
    'instructions' => 'Customize padding for different screen sizes.',
    'button_label' => 'Add Screen Size Padding',
])
    ->addSelect('screen_size', [
        'label' => 'Screen Size',
        'choices' => [
            'xxs' => 'xxs',
            'xs' => 'xs',
            'mob' => 'mob',
            'sm' => 'sm',
            'md' => 'md',
            'lg' => 'lg',
            'xl' => 'xl',
            'xxl' => 'xxl',
            'ultrawide' => 'ultrawide',
        ],
    ])
    ->addNumber('padding_top', [
        'label' => 'Padding Top',
        'instructions' => 'Set the top padding in rem.',
        'min' => 0,
        'max' => 20,
        'step' => 0.1,
        'append' => 'rem',
    ])
    ->addNumber('padding_bottom', [
        'label' => 'Padding Bottom',
        'instructions' => 'Set the bottom padding in rem.',
        'min' => 0,
        'max' => 20,
        'step' => 0.1,
        'append' => 'rem',
    ])
->endRepeater()
```

**Do not** use vague size names like `default`, `large`, or `small` — only the breakpoint keys above.

### Frontend loop

```php
$padding_classes = [];
if (have_rows('padding_settings')) {
    while (have_rows('padding_settings')) {
        the_row();
        $screen_size = get_sub_field('screen_size');
        $padding_top = get_sub_field('padding_top');
        $padding_bottom = get_sub_field('padding_bottom');
        $padding_classes[] = "{$screen_size}:pt-[{$padding_top}rem]";
        $padding_classes[] = "{$screen_size}:pb-[{$padding_bottom}rem]";
    }
}
```

Merge `$padding_classes` onto the inner `max-w-container` wrapper div.

---

## Section-scoped styles

Optional `<style>` block at the bottom of the template for:

- `.wp_editor` list/paragraph overrides scoped to `#{$section_id}`
- Embed/video helpers
- Button focus/hover overrides (see CTA pattern above)

Always scope selectors with `#<?php echo esc_attr($section_id); ?>`.

---

## Checklist (new flexi block)

- [ ] `acf_{layout}.php` with `FieldsBuilder` label and Content / Design / Layout tabs
- [ ] `template-parts/flexi/{layout}.php` — flat template, no outer `have_rows` loop
- [ ] All fields via `get_sub_field()`
- [ ] Unique `$section_id` on `<section>`
- [ ] `relative flex overflow-hidden` on section; inner `max-w-container` wrapper
- [ ] Padding repeater on inner wrapper
- [ ] Headings: dynamic text + tag select (h1–h6, span, p)
- [ ] Images: alt/title from media + fallback
- [ ] CTAs: ACF Link array only; `.btn` + `w-fit whitespace-nowrap` + scoped `<style>`
- [ ] WYSIWYG fields wrapped with `wp_editor` class
- [ ] Escaping on all dynamic output
- [ ] No `min-w-[240px]`, `aspect-[…]`, or `self-stretch` (except documented nav exceptions)
- [ ] Accessibility: landmarks, labels, focus, alt text ([accessibility-basics.md](accessibility-basics.md))

---

## Agent prompts

- *Convert static HTML to a Matrix Starter flexi block per `docs/flexi-blocks-basics.md`.*
- *Use ACF Builder with Content/Design/Layout tabs; `get_sub_field` only; Link fields as arrays.*
- *Match `examples/acf/flexi/acf_*` and `examples/flexi/*` output structure.*
- *Section: `relative flex overflow-hidden`, inner `max-w-container`, padding repeater on wrapper div.*
- *Full WCAG 2.1 AA; include `.btn`, aria-label, and scoped button focus styles.*

---

## Example references

| Block | ACF | Template |
|-------|-----|----------|
| Content + media | `examples/acf/flexi/acf_content_002.php` | `examples/flexi/content_002.php` |
| CTA large button | `examples/acf/flexi/acf_cta_large_button.php` | `examples/flexi/cta_large_button.php` |
| FAQ accordion | `examples/acf/flexi/acf_faq.php` | `examples/flexi/faq.php` |
| Contact form | `examples/acf/flexi/acf_contact_form_001.php` | `examples/flexi/contact_form_001.php` |

Copy patterns from these when building new blocks; ship production templates under `template-parts/flexi/` and register ACF in `acf-fields/`.
