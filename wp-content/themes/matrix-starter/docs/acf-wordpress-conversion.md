# ACF WordPress conversion

General instructions for converting **static HTML** into dynamic WordPress sections using **ACF Builder**. All content must be editable in the admin; frontend output must follow theme conventions and Tailwind utilities.

For flexi-specific patterns (file pairing, CTA buttons, `.btn` styles), see [flexi-blocks-basics.md](flexi-blocks-basics.md). For layout and accessibility rules, see [coding-guidelines.md](coding-guidelines.md) and [accessibility-basics.md](accessibility-basics.md).

---

## Goal

Convert the provided static HTML into a dynamic WordPress section using ACF Builder. Ensure:

- All elements are dynamic and customizable in the WordPress admin.
- Output adheres to theme PHP structure, WordPress coding standards, and Tailwind classes.
- Sections are reusable via ACF Flexible Content (flexi blocks).

---

## ACF Builder structure

### Flexi blocks

- Use the **Flexi Block** pattern (flexible content layout).
- **Always** use `get_sub_field()` — **never** `get_field()` for layout field values.
- Define fields with **StoutLogic ACF Builder** (`FieldsBuilder`).
- **Always** set a block label:

```php
<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$gallery_001 = new FieldsBuilder('gallery_001', [
    'label' => 'Gallery 001',
]);
```

### Horizontal tabs

| Tab | Purpose |
|-----|---------|
| **Content** | Text, images, links, repeaters, WYSIWYG |
| **Design** | Background color and other visual options (when the design requires them) |
| **Layout** | Margins, padding repeater, column order, alignment (when applicable) |

---

## Frontend template

### Reference examples

- ACF field files: `examples/acf/flexi/acf_*.php` (and `examples/acf/hero/acf_*.php` for heroes).
- Output templates: `examples/flexi/*.php` → production copies in `template-parts/flexi/{layout}.php`.

Follow the flat template structure in those examples — **not** an outer `have_rows()` wrapper around the whole section.

```php
// ❌ Avoid in template-parts/flexi/*.php
<?php if (have_rows('content_014')): ?>
<?php while (have_rows('content_014')): the_row(); ?>
```

### Variables

Declare all `get_sub_field()` values at the **top of the file**, before `<section>`, unless structurally impossible.

### Tailwind

- Use **Tailwind classes exclusively** for layout and typography.
- **Inline CSS** only for dynamic values (e.g. background color from a color picker).
- **Strip** `min-w-[240px]` and aspect-ratio utilities such as `aspect-[1.32]`.

### Escaping

Escape all dynamic output:

- `esc_html()` — text
- `esc_attr()` — attributes
- `esc_url()` — URLs
- `wp_kses_post()` — WYSIWYG HTML

---

## Section structure

### Outer `<section>`

```html
<section
  id="<?php echo esc_attr($section_id); ?>"
  class="relative flex overflow-hidden …"
>
```

- **Unique section ID** on every block (required for scoped styles and ARIA):

```php
$section_id = 'gallery-001-' . wp_generate_uuid4();
```

### Inner wrapper

Immediately inside `<section>`:

```html
<div class="flex flex-col items-center w-full mx-auto max-w-[1088px] pt-5 pb-5 max-xl:px-5 <?php echo esc_attr(implode(' ', $padding_classes)); ?>">
```

Replace `1088px` with the **Figma frame max width**, or use the theme token `max-w-container` when it matches the design.

Apply **padding repeater classes** on this same div (not on `<section>`).

---

## Dynamic features

### Headings

- Text field for heading copy.
- Select for HTML tag with **all** options: `h1`, `h2`, `h3`, `h4`, `h5`, `h6`, `span`, `p`.
- Validate tag in PHP before output; set `id` for `aria-labelledby`.

```php
$allowed_heading_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span'];
if (!in_array($heading_tag, $allowed_heading_tags, true)) {
    $heading_tag = 'h2';
}
```

### Images

- Store attachment ID (or array) in ACF.
- Read **alt** and **title** from WordPress media metadata.
- Provide a meaningful fallback if alt is empty.

```php
$image_alt = get_post_meta($image, '_wp_attachment_image_alt', true) ?: 'Content image';
```

Use Tailwind for sizing (`object-contain`, `object-cover`, etc.).

### Links (`<a href>`)

**Always use a single ACF Link field** (`return_format` => `array`). Never separate URL, label, and target fields.

```php
$button = get_sub_field('content_button');
if (is_array($button) && !empty($button['url']) && !empty($button['title'])) {
    ?>
    <a
      href="<?php echo esc_url($button['url']); ?>"
      target="<?php echo esc_attr($button['target'] ?? '_self'); ?>"
      <?php if (($button['target'] ?? '') === '_blank') : ?>rel="noopener noreferrer"<?php endif; ?>
    >
      <?php echo esc_html($button['title']); ?>
    </a>
    <?php
}
```

---

## Reusable sections

- Register as **ACF Flexible Content** layouts so blocks are reusable across pages.
- Add **default/placeholder** values and clear **instructions** on fields.
- Use **conditional logic** to show/hide optional elements.
- Prefer **WYSIWYG** over plain textarea for rich content.
- Add class **`wp_editor`** on elements that output WYSIWYG HTML.

---

## Padding & layout (Layout tab)

### ACF repeater

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

Use breakpoint keys only — **not** vague names like `default`, `large`, or `small`.

### Frontend

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

---

## Output requirements

### Frontend (`template-parts/flexi/{layout}.php`)

- Tailwind-based HTML/PHP starting with `<section>` and the inner wrapper `<div>` described above.
- All ACF data via `get_sub_field()` at the top of the file.
- Unique `$section_id` on the section element.

### ACF PHP (`acf-fields/…` or `examples/acf/flexi/acf_{layout}.php`)

- FieldsBuilder with **Content**, **Design**, and **Layout** tabs as needed.
- Dynamic controls for headings, images, colors, padding, and screen sizes.
- Conditional logic for optional fields.

---

## Example use cases

- Convert sections with headings, images, and background colors into reusable flexi blocks.
- Let administrators update content, design, and layout in WP Admin without code changes.
- Match Figma frame width on the inner `max-w-[…]` wrapper and per-breakpoint padding via the repeater.

---

## Quick checklist

- [ ] `FieldsBuilder` with layout `label`
- [ ] Content / Design / Layout tabs
- [ ] `get_sub_field()` only; no outer flexi `have_rows` loop in template
- [ ] Unique `$section_id`
- [ ] `<section class="relative flex overflow-hidden">`
- [ ] Inner `max-w-[frameWidth]` or `max-w-container` + padding classes
- [ ] Heading text + tag select (h1–h6, span, p)
- [ ] Image alt from media + fallback
- [ ] All links via ACF Link array
- [ ] WYSIWYG + `wp_editor` class
- [ ] Escaped output throughout

---

## Agent prompt

> Convert the provided static HTML to WordPress using ACF Builder per `docs/acf-wordpress-conversion.md`. Use flexi structure, `get_sub_field`, Content/Design/Layout tabs, section wrapper with `max-w-[frame width]` and padding repeater. All `href` links must use ACF Link arrays. Reference `examples/acf/flexi/acf_*` and `examples/flexi/*`.

---

## Related docs

- [flexi-blocks-basics.md](flexi-blocks-basics.md) — full flexi block spec including CTAs and button styles
- [coding-guidelines.md](coding-guidelines.md) — Grid over Flex, banned classes, buttons
- [accessibility-basics.md](accessibility-basics.md) — WCAG 2.1 AA requirements
