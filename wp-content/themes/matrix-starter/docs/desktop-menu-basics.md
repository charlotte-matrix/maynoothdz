# Desktop menu basics

Guidelines for building and maintaining the site navigation in Matrix Starter. Menus are powered by **[Log1x Navi](https://github.com/Log1x/navi)**.

Related docs: [coding-guidelines.md](coding-guidelines.md), [accessibility-basics.md](accessibility-basics.md).

---

## File locations

| File | Purpose |
|------|---------|
| `template-parts/header/navbar.php` | Main nav shell (include from `header.php`) |
| `template-parts/header/navbar/dropdown.php` | Megamenu / submenu dropdown panel |
| `template-parts/header/navbar/mobile.php` | Mobile menu toggle and drawer |
| `template-parts/header/navbar/language-dropdown.php` | Country/language picker (when enabled) |
| `inc/theme-options/navbar.php` | ACF options: phone, CTAs, dropdown images, country picker |

Register WordPress menus as `primary` and `secondary` (or as required by the design). Navi builds them with:

```php
use Log1x\Navi\Navi;

$primary_navigation   = Navi::make()->build('primary');
$secondary_navigation = Navi::make()->build('secondary');
```

---

## Rules (required)

1. **Do not use `<header>`** — wrap navigation in `<section id="site-nav">` only.
2. **Always use `<section>`** with Alpine.js state for mobile open, dropdown index, and resize handling.
3. **Logo markup** — use the standard logo wrapper (see below).
4. **Desktop menu** — `hidden … lg:flex` on the primary `<ul>`; show at large breakpoint.
5. **Mobile** — `<?php get_template_part('template-parts/header/navbar/mobile'); ?>` below the desktop menu list.
6. **Dropdowns** — always delegate to the dropdown partial:

   ```php
   <?php get_template_part('template-parts/header/navbar/dropdown', null, ['item' => $item, 'index' => $index]); ?>
   ```

7. **Breakpoint** — reset mobile state when viewport is wider than **1084px** (adjust only if design specifies another width).
8. **Body scroll** — lock body overflow when mobile menu is open (`x-effect` on `isOpen`).
9. **Buttons** — CTAs use `.btn`, `w-fit whitespace-nowrap`, and theme focus styles ([accessibility-basics.md](accessibility-basics.md)).

> **Note:** The shipped `navbar.php` in this repo uses a fixed/sticky layout and a **1201px** breakpoint with search, donate, and country picker. Use the **baseline template** below for new sites; extend the live file only when the design requires those features.

---

## Alpine.js section shell

Wrap the entire nav in this structure (padding/background classes may match the design):

```html
<section
  id="site-nav"
  x-data="{
    isOpen: false,
    activeDropdown: null,
    toggleDropdown(index) {
      this.activeDropdown = (this.activeDropdown === index ? null : index);
    },
    checkWindowSize() {
      if (window.innerWidth > 1084) {
        this.isOpen = false;
        this.activeDropdown = null;
      }
    }
  }"
  x-init="window.addEventListener('resize', () => checkWindowSize())"
  class="py-4 bg-white"
  x-effect="isOpen ? document.body.style.overflow = 'hidden' : document.body.style.overflow = ''"
  role="banner"
>
  <!-- <nav> … -->
</section>
```

---

## Logo wrapper

Always wrap the logo in this block (navbar-specific layout; `self-stretch` is allowed here for alignment):

```html
<div class="flex flex-col items-start self-stretch my-auto min-w-60 w-[250px]">
  <a
    href="<?php echo esc_url(home_url('/')); ?>"
    class="flex justify-start"
    aria-label="<?php echo esc_attr(get_bloginfo('name')); ?> - Go to homepage"
  >
    <?php if ($logo_url) : ?>
      <img
        src="<?php echo esc_url($logo_url); ?>"
        alt="<?php echo esc_attr($logo_alt); ?>"
        class="object-contain w-auto h-auto"
      />
    <?php else : ?>
      <span class="text-xl font-bold text-slate-700"><?php echo get_bloginfo('name'); ?></span>
    <?php endif; ?>
  </a>
</div>
```

**PHP setup at top of template:**

```php
$logo_id  = get_theme_mod('custom_logo');
$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
$logo_alt = $logo_id ? get_post_meta($logo_id, '_wp_attachment_image_alt', true) : get_bloginfo('name');
```

---

## Desktop primary menu

- Container: `<ul id="primary-menu" class="hidden … lg:flex …" role="menubar">`
- Each top-level item: `<li class="relative group" role="none">`
- Links: `role="menuitem"`; if children exist: `aria-haspopup="true"` and `x-bind:aria-expanded` tied to `activeDropdown`
- Submenu toggle: `<button type="button" @click="toggleDropdown(<?php echo $index; ?>)" …>`
- Children: include `dropdown` partial (see above)

---

## Theme options (`inc/theme-options/navbar.php`)

Options are stored under the ACF group `navigation_settings_start` on **Options**.

**Minimum fields** (phone + contact CTA when shown in design):

```php
<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$navigationFields = new FieldsBuilder('navigation_settings');

$navigationFields
    ->addGroup('navigation_settings_start', [
        'label' => 'Navigation Settings',
    ])
        ->addText('phone_number', [
            'label' => 'Phone Number',
            'instructions' => 'Enter the phone number to display in the header (e.g., +353 1 283 2967)',
            'placeholder' => '+353 1 283 2967',
        ])
        ->addLink('contact_button', [
            'label' => 'Contact Button',
            'instructions' => 'Set contact button link and text',
        ])
    ->addAccordion('navigation_settings_end')->endpoint();

return $navigationFields;
```

**Read in `navbar.php`:**

```php
$nav_settings   = get_field('navigation_settings_start', 'option');
$phone_number   = $nav_settings['phone_number'] ?? null;
$contact_button = $nav_settings['contact_button'] ?? null;
```

The repo’s `inc/theme-options/navbar.php` also defines **Join Us**, **Donate**, **country picker**, and **dropdown images** for megamenus. Add or remove fields to match each project’s Figma design.

---

## Full baseline template

Copy into `template-parts/header/navbar.php` and adjust colors, spacing, and `max-w-[1600px]` to match the design frame.

```php
<?php
$logo_id = get_theme_mod('custom_logo');
$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
$logo_alt = $logo_id ? get_post_meta($logo_id, '_wp_attachment_image_alt', true) : get_bloginfo('name');

$nav_settings = get_field('navigation_settings_start', 'option');

$phone_number   = $nav_settings['phone_number'] ?? null;
$contact_button = $nav_settings['contact_button'] ?? null;

use Log1x\Navi\Navi;

$primary_navigation   = Navi::make()->build('primary');
$secondary_navigation = Navi::make()->build('secondary');
?>

<section
  id="site-nav"
  x-data="{
    isOpen: false,
    activeDropdown: null,
    toggleDropdown(index) {
      this.activeDropdown = (this.activeDropdown === index ? null : index);
    },
    checkWindowSize() {
      if (window.innerWidth > 1084) {
        this.isOpen = false;
        this.activeDropdown = null;
      }
    }
  }"
  x-init="window.addEventListener('resize', () => checkWindowSize())"
  class="pt-8 pb-6 bg-white"
  x-effect="isOpen ? document.body.style.overflow = 'hidden' : document.body.style.overflow = ''"
  role="banner"
>
  <nav
    class="flex flex-row gap-10 justify-between items-center self-stretch mx-auto max-w-[1600px] px-5"
    role="navigation"
    aria-label="Main navigation"
  >
    <!-- Logo -->
    <div class="flex flex-col items-start self-stretch my-auto min-w-60 w-[250px]">
      <a
        href="<?php echo esc_url(home_url('/')); ?>"
        class="flex justify-start"
        aria-label="<?php echo esc_attr(get_bloginfo('name')); ?> - Go to homepage"
      >
        <?php if ($logo_url) : ?>
          <img
            src="<?php echo esc_url($logo_url); ?>"
            alt="<?php echo esc_attr($logo_alt); ?>"
            class="object-contain w-auto h-auto"
          />
        <?php else : ?>
          <span class="text-xl font-bold text-slate-700"><?php echo get_bloginfo('name'); ?></span>
        <?php endif; ?>
      </a>
    </div>

    <!-- Desktop menu -->
    <?php if ($primary_navigation->isNotEmpty()) : ?>
      <ul
        id="primary-menu"
        class="hidden flex-row gap-5 items-center self-stretch pt-0.5 my-auto text-base font-medium xl:gap-10 lg:flex min-w-60 text-slate-700 max-md:max-w-full"
        role="menubar"
      >
        <?php foreach ($primary_navigation->toArray() as $index => $item) : ?>
          <li
            class="relative group <?php echo esc_attr($item->classes); ?> <?php echo $item->active ? 'current-item' : ''; ?>"
            role="none"
          >
            <div class="flex flex-col justify-center self-stretch pt-1 my-auto whitespace-nowrap">
              <div class="flex gap-1 items-center">
                <a
                  href="<?php echo esc_url($item->url); ?>"
                  class="self-stretch my-auto text-slate-700 hover:text-slate-900 focus:text-slate-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-400 rounded <?php echo $item->active ? 'active-item font-semibold' : ''; ?>"
                  role="menuitem"
                  <?php if ($item->children) : ?>
                    aria-haspopup="true"
                    aria-expanded="false"
                    x-bind:aria-expanded="activeDropdown === <?php echo $index; ?>"
                  <?php endif; ?>
                >
                  <?php echo esc_html($item->label); ?>
                </a>
                <?php if ($item->children) : ?>
                  <button
                    type="button"
                    class="p-1 ml-1 rounded focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-400"
                    @click="toggleDropdown(<?php echo $index; ?>)"
                    aria-label="Toggle <?php echo esc_attr($item->label); ?> submenu"
                  >
                    <svg width="17" height="18" viewBox="0 0 17 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path d="M4.25 6.48047L8.5 10.7305L12.75 6.48047" stroke="#344054" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </button>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($item->children) : ?>
              <?php get_template_part('template-parts/header/navbar/dropdown', null, ['item' => $item, 'index' => $index]); ?>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <!-- Mobile -->
    <?php get_template_part('template-parts/header/navbar/mobile'); ?>

    <!-- Phone + contact (desktop) -->
    <div class="flex gap-8 items-center self-stretch my-auto min-w-60 max-lg:hidden">
      <?php if ($phone_number) : ?>
        <a
          href="tel:<?php echo esc_attr(preg_replace('/[^+\d]/', '', $phone_number)); ?>"
          class="flex gap-2 items-center self-stretch my-auto text-sm font-semibold leading-none whitespace-nowrap rounded text-slate-700 hover:text-secondary focus:text-slate-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-400"
          aria-label="Call us at <?php echo esc_attr($phone_number); ?>"
        >
          <!-- phone icon SVG -->
          <span class="hidden lg:flex"><?php echo esc_html($phone_number); ?></span>
        </a>
      <?php endif; ?>

      <?php if ($contact_button) : ?>
        <a
          href="<?php echo esc_url($contact_button['url']); ?>"
          target="<?php echo esc_attr($contact_button['target'] ?: '_self'); ?>"
          class="btn flex gap-2 justify-center items-center w-fit whitespace-nowrap px-6 py-4 my-auto text-sm font-semibold leading-none text-black bg-secondary hover:bg-orange-500 focus:bg-orange-500 rounded min-h-[52px] transition-colors duration-200"
        >
          <?php echo esc_html($contact_button['title']); ?>
        </a>
      <?php endif; ?>
    </div>
  </nav>
</section>
```

---

## Dropdown partial

`template-parts/header/navbar/dropdown.php` expects:

| Argument | Type | Description |
|----------|------|-------------|
| `item` | Navi menu item object | Parent item with `children` |
| `index` | int | Loop index for `activeDropdown` / Alpine |

Megamenu images are optional: configure **Dropdown Images** in theme options (`menu_item` + `image` repeater). The partial maps images by WordPress menu item ID.

---

## Checklist (new nav implementation)

- [ ] Navi package installed; `primary` menu assigned in WP Admin
- [ ] No `<header>` — only `<section id="site-nav">`
- [ ] Alpine resize breakpoint matches design (default **1084px**)
- [ ] Logo wrapper and homepage `aria-label`
- [ ] Desktop `<ul>` uses `lg:flex` (hidden below `lg`)
- [ ] `mobile.php` included
- [ ] Child items use `dropdown` partial with `item` + `index`
- [ ] `inc/theme-options/navbar.php` fields match design (phone, contact, etc.)
- [ ] CTAs use `.btn` + `w-fit whitespace-nowrap`
- [ ] Keyboard and screen reader test on menu + dropdowns

---

## Agent prompts

- *Follow `docs/desktop-menu-basics.md` for navbar structure.*
- *Use Log1x Navi; no `<header>`; wrap in `#site-nav` section with Alpine.*
- *Desktop menu: `lg:flex`; mobile: `navbar/mobile` partial; dropdowns: `navbar/dropdown` partial.*
