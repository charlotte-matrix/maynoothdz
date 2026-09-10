# Examples folder — code style & conventions

The `examples/` directory is a **reference library** of copy-paste templates for new Matrix Starter sites. It is listed in `.gitignore` so client-specific blocks stay local; match its patterns whenever you add or update files there.

Related docs: [acf-wordpress-conversion.md](acf-wordpress-conversion.md), [flexi-blocks-basics.md](flexi-blocks-basics.md), [coding-guidelines.md](coding-guidelines.md).

---

## Purpose

| Goal | Detail |
|------|--------|
| **Reference** | Canonical patterns for flexi blocks, heroes, CPTs, ACF fields, footer |
| **Copy source** | Copy into production paths when building a site — do not load `examples/` directly in production |
| **Style guide** | New files must match naming, structure, and PHP/HTML style of existing examples |

When implementing a feature, **find the closest example first**, then adapt — do not invent a new structure.

---

## Directory structure

```
examples/
├── acf/
│   ├── flexi/          # ACF Builder field groups (acf_{layout}.php)
│   └── hero/           # Hero field groups (acf_hero.php, acf_subhero.php)
├── flexi/              # Flexi frontend templates ({layout}.php)
├── hero/               # Hero frontend templates (hero.php, subhero.php)
├── footer/             # Footer template reference
└── cpts/               # CPT + taxonomy registration snippets
```

---

## Naming conventions

### Flexi blocks

| Type | Pattern | Example |
|------|---------|---------|
| ACF fields | `acf_{layout}.php` | `acf_content_002.php` |
| Frontend | `{layout}.php` | `content_002.php` |
| FieldsBuilder slug | Same as layout slug | `'content_002'` |
| Human label | Descriptive in Builder | `'Content with Image or Video'` |

The **layout slug** must match across ACF, flexible content layout name, and template filename.

### Heroes

| ACF | Template |
|-----|----------|
| `examples/acf/hero/acf_hero.php` | `examples/hero/hero.php` |
| `examples/acf/hero/acf_subhero.php` | `examples/hero/subhero.php` |

### CPTs

| Pattern | Example |
|---------|---------|
| `{post-type}.php` or `{feature}.php` | `people.php`, `running-groups.php` |
| File header comment | `Plugin Name: CPT – People` |
| Post type slug | `snake_case` (`running_group`) |
| Rewrite slug | `kebab-case` (`running-groups`) |

Use `register_extended_post_type()` from Extended CPTs; guard with `ABSPATH` and wrap in `add_action('init', …)`.

### Post / option ACF (non-flexi)

| Pattern | Example |
|---------|---------|
| `acf` not in filename | `post_single.php` in `examples/cpts/` |
| FieldsBuilder slug | `post_single` |
| `setLocation()` | Tied to post type, options page, or taxonomy |

---

## Production copy targets

When shipping a block to the active theme, copy from `examples/` to:

| Example path | Production path |
|--------------|-----------------|
| `examples/acf/flexi/acf_{layout}.php` | `acf-fields/partials/blocks/acf_{layout}.php` (or register via `acf-fields/partials/flexi.php` glob) |
| `examples/flexi/{layout}.php` | `template-parts/flexi/{layout}.php` |
| `examples/acf/hero/acf_{name}.php` | `acf-fields/partials/hero/acf_{name}.php` |
| `examples/hero/{name}.php` | `template-parts/hero/{name}.php` |
| `examples/footer/footer.php` | `template-parts/footer/footer.php` (or partial used by theme) |
| `examples/cpts/{name}.php` | MU-plugin, `inc/cpts/`, or site-specific plugin |

Register new ACF groups through the theme’s autoload (`inc/autoload-acf-fields.php`, `acf-fields/partials/flexi.php`).

---

## PHP code style (match examples)

### File header

**Flexi / hero templates** — start with variables only (no `ABSPATH` guard in most flexi files; heroes may include it):

```php
<?php
$section_id = 'faq-section-' . uniqid();
$heading = get_sub_field('heading');
```

**ACF Builder files**:

```php
<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$faq = new FieldsBuilder('faq', [
    'label' => 'FAQ Section',
]);
```

**CPT files**:

```php
<?php
/**
 * Plugin Name: CPT – People
 */

if (!defined('ABSPATH')) exit;
```

### Rules (aligned with theme docs)

- **No shorthand PHP** (`<?=`).
- **Always** `get_sub_field()` in flexi/hero templates — never `get_field()` for layout fields.
- **No outer flexible-content loop** in templates (`have_rows('layout_name')` wrapping the whole file).
- **Escape output**: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` for WYSIWYG.
- **Unique section ID**: `uniqid()` or `wp_generate_uuid4()` prefix (e.g. `'faq-section-' . uniqid()`).
- **Links**: ACF Link fields only (`return_format` => `array`); check `is_array()` + `url` + `title` before render.
- **Comments**: Section headers in heroes (`// Content (ACF)`) are fine; avoid noise elsewhere.

### HTML / Tailwind (match `examples/flexi/*`)

- Open with `<section class="relative flex overflow-hidden …">`.
- Inner wrapper: `flex flex-col items-center w-full mx-auto max-w-[…] or max-w-container … max-xl:px-5` (or `max-lg:px-5` per example).
- **Padding repeater** built into `$padding_classes` and merged on section or inner div — same loop as [acf-wordpress-conversion.md](acf-wordpress-conversion.md).
- **Tailwind only** except dynamic `style="background-color: …"` from color pickers.
- **Do not** add `min-w-[240px]`, `aspect-[1.32]`, or `self-stretch` unless an existing example in the same block type already uses it.
- **WYSIWYG** output wrappers use class `wp_editor`.
- **Headings**: dynamic tag from ACF select (`h1`–`h6`, `p`, `span`).

### ACF Builder (match `examples/acf/flexi/*`)

- Tabs: **Content**, **Design** (if needed), **Layout** (padding repeater, layout toggles).
- Every field: `label`, `instructions`; use `default_value` for placeholders.
- `->addLink(…, ['return_format' => 'array'])` for all buttons/links.
- Padding repeater with screen sizes: `xxs`, `xs`, `mob`, `sm`, `md`, `lg`, `xl`, `xxl`, `ultrawide`.
- End file with `return $builder_variable;`.

---

## Pairing checklist (new block)

When adding a new flexi block to `examples/`:

1. [ ] Create `examples/acf/flexi/acf_{layout}.php` — FieldsBuilder slug = `{layout}`
2. [ ] Create `examples/flexi/{layout}.php` — same slug, flat template
3. [ ] Layout slug matches flexible content layout key in ACF export/register
4. [ ] Variables + padding loop at top; `<section>` + inner wrapper structure
5. [ ] Closest existing example reviewed (e.g. `content_002`, `faq`, `cta_large_button`)
6. [ ] Copy to `acf-fields/` + `template-parts/flexi/` when activating on a site

---

## Reference map (common examples)

| Use case | ACF | Template |
|----------|-----|----------|
| Two-column content + media | `acf_content_002.php` | `content_002.php` |
| FAQ accordion | `acf_faq.php` | `faq.php` |
| Large CTA | `acf_cta_large_button.php` | `cta_large_button.php` |
| Contact form block | `acf_contact_form_001.php` | `contact_form_001.php` |
| Blog listing | `acf_blog_listing.php` | `blog_listing.php` |
| Hero | `acf_hero.php` | `hero.php` |
| Subhero | `acf_subhero.php` | `subhero.php` |
| People CPT | — | `cpts/people.php` |
| Post meta fields | — | `cpts/post_single.php` |

---

## Git ignore

The entire `examples/` folder is in `.gitignore`. To share a block with the team, either:

- Copy needed files into tracked paths (`template-parts/`, `acf-fields/`), or
- Temporarily force-add: `git add -f examples/flexi/my_block.php` (use sparingly).

---

## Agent prompt

> Add a new block to `examples/` matching `docs/examples-folder-basics.md`: pair `acf_{layout}.php` + `{layout}.php`, follow structure in the closest existing example, use `get_sub_field`, section wrapper, padding repeater, ACF Link arrays, and theme coding guidelines.

---

## Related docs

- [acf-wordpress-conversion.md](acf-wordpress-conversion.md) — static HTML → ACF (general)
- [flexi-blocks-basics.md](flexi-blocks-basics.md) — flexi CTAs, accessibility, full spec
- [coding-guidelines.md](coding-guidelines.md) — Grid over Flex, banned utilities
- [accessibility-basics.md](accessibility-basics.md) — WCAG, `.btn`, focus
