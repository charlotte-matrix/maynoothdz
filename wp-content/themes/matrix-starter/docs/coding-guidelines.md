# Coding guidelines

Follow these fundamental principles when generating or editing theme code (flexi components, templates, PHP partials, and front-end markup).

For accessibility-specific rules, see [accessibility-basics.md](accessibility-basics.md).

---

## Code quality

### Layout

- **Prefer CSS Grid over Flexbox** where applicable. Order of preference: **Grid → Flex**.
- Use Flexbox for simple one-dimensional alignment; use Grid for two-dimensional layouts, card grids, and section content structure.

### General

- Write **clean, maintainable, and concise** code.
- Follow **DRY** (Don't Repeat Yourself) — extract repeated patterns into partials, helpers, or shared classes when it improves clarity.
- Ensure **proper error handling** — guard empty ACF fields, validate URLs/IDs before output, and fail safely in templates.

### Classes to avoid

Do **not** add these in new or generated markup:

| Avoid | Reason |
|-------|--------|
| `min-w-[240px]` | Arbitrary min-widths from exports; use theme tokens or responsive layout instead. |
| `aspect-[…]` / `aspect-ratio` utilities (e.g. `aspect-[1.32]`) | Prefer explicit dimensions, `object-fit`, or grid-based sizing. |
| `self-stretch` | Do not use on any element. |

### Buttons

All buttons and button-styled links must include:

- `w-fit whitespace-nowrap`
- `.btn` (or `.btn-primary` / `.form-btn` where appropriate) for **hover and accessibility** focus styles — see [accessibility-basics.md](accessibility-basics.md)

**Example:**

```html
<a href="<?php echo esc_url($url); ?>"
   class="btn btn-primary inline-flex items-center gap-2 w-fit whitespace-nowrap …">
  <?php echo esc_html($label); ?>
</a>
```

---

## Section structure

Every content block should start with a **`<section>`** element.

### Outer `<section>`

Add these classes to the **first** `<section>` in the component:

```
relative flex overflow-hidden
```

Plus any background, padding, or `aria-*` attributes required for the block.

**Example:**

```html
<section
  id="<?php echo esc_attr($section_id); ?>"
  class="relative flex overflow-hidden <?php echo esc_attr(implode(' ', $padding_classes)); ?>"
  role="region"
  aria-labelledby="<?php echo esc_attr($section_id); ?>-heading"
>
```

### Inner content wrapper

Immediately inside the `<section>`, wrap all section content in:

```html
<div class="flex flex-col items-center w-full mx-auto max-w-[1088px] pt-5 pb-5 max-xl:px-5">
```

Replace `1088px` with the **max width of the Figma frame** for that component when it differs. When the frame matches a theme token, you may use `max-w-container` (or `max-w-container-md` / `max-w-container-lg`) instead of a raw pixel value — see `tailwind.config.js`.

**Full skeleton:**

```html
<section id="…" class="relative flex overflow-hidden …">
  <div class="flex flex-col items-center w-full mx-auto max-w-[1088px] pt-5 pb-5 max-xl:px-5">
    <!-- headings, grid/flex content, CTAs -->
  </div>
</section>
```

Padding variants (`pt-14`, `max-lg:px-5`, responsive top padding) are acceptable when the design or existing component pattern requires them; keep the wrapper structure consistent.

---

## Documentation

- Add **meaningful comments** only for non-obvious business logic, workarounds, or integration constraints.
- **Document assumptions or limitations** in code comments or in this `docs/` folder when behavior depends on ACF structure, multisite, or third-party plugins.

Prefer self-explanatory naming over heavy commenting.

---

## Naming conventions

- Use **descriptive and consistent** names for variables, functions, classes, and file names.
- Follow **language-specific conventions** (WordPress/PHP: `snake_case` for functions and hooks; CSS: kebab-case utility classes aligned with Tailwind).
- **Avoid abbreviations** unless widely recognized (`url`, `id`, `cta`, `acf`).

### PHP / templates

- Prefix section IDs with a clear slug: `content-section-two-`, `faq-section-`, etc.
- Use `$section_id` for `id`, `aria-labelledby`, and heading `id` suffixes.

### Files

- Flexi layouts: `template-parts/flexi/{layout_name}.php` or project convention under `examples/flexi/` for reference copies.
- Match ACF layout slug to filename where possible.

---

## Agent and review prompts

Use these when briefing AI tools or reviewing generated markup:

- *Follow the theme coding guidelines in `docs/coding-guidelines.md`.*
- *Prefer Grid over Flex; never use `self-stretch`, `min-w-[240px]`, or arbitrary `aspect-[…]` classes.*
- *Start sections with `<section class="relative flex overflow-hidden">` and the standard inner max-width wrapper.*
- *Buttons must include `w-fit whitespace-nowrap` and `.btn` for focus/hover accessibility.*

---

## Related docs

- [accessibility-basics.md](accessibility-basics.md) — WCAG 2.1 AA, `.btn`, ARIA, testing
- [README.md](../README.md) — install, tokens, theme utilities
