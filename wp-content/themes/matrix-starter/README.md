# Matrix Starter

Matrix Starter is a modern and highly customizable WordPress theme that uses ACF Builder and Tailwind CSS to streamline development. Modular by its structure, it includes a robust set of tools and features to help you build custom WordPress themes quickly and efficiently.

## Clone and Install

Follow these steps to clone the repository and set up the project on your local machine. We recommend using **Local by Flywheel** and cloning straight to your theme folder for testing and development.

### Prerequisites

Ensure you have the following installed on your system:

* **PHP** (version 7.4 or higher)
* **Composer** (Dependency Manager for PHP)
* **Node.js** (which includes npm)
* **Git** (Version Control System)
* **WordPress** (Installed locally or on a server)

### Installation Steps

1. **Clone the Repository**

   ```bash
   git clone https://github.com/bernardhanna/matrix-starter.git
   ```

2. **Navigate to the Project Directory**

   ```bash
   cd matrix-starter
   ```

3. **Install PHP Dependencies**

   Make sure Composer is installed: [getcomposer.org](https://getcomposer.org/)

   ```bash
   composer install
   ```

4. **Install JavaScript Dependencies**

   Ensure Node.js and npm are installed: [nodejs.org](https://nodejs.org/)

   ```bash
   npm install
   ```

5. **Create and Configure `.env` File**

   Create a `.env` by copying the example:

   ```bash
   cp .env.example .env
   ```

   Then open `.env` and add your WordPress path (the folder that contains `wp-config.php` / `wp-load.php`).
   If your path contains spaces (e.g., Local by Flywheel), **wrap it in quotes**:

   ```dotenv
   # Path to your WordPress root
   # Example (Local by Flywheel):
   WP_PATH="/Users/yourname/Local Sites/your-site/app/public"
   ```

   > The installer **only reads** `.env`; it does not modify or delete it.

6. **Run Development Server with Watchers**

   ```bash
   npm run dev
   ```

   This will:

   * Watch and process CSS changes
   * Watch and process JS changes
   * Start the Webpack dev server with hot reloading

### Additional Steps

7. **Build the Assets for Production**

   ```bash
   npm run build
   ```

8. **Set Up WordPress**

   * **Place the Theme in WordPress:**

     Copy the `matrix-starter` theme folder to your WordPress installation’s `wp-content/themes/` directory (skip if you already cloned directly into `wp-content/themes`):

     ```bash
     cp -R ./matrix-starter /path-to-your-wordpress/wp-content/themes/
     ```

   * **(Optional) Activate the Theme via WP Admin:**

     Log in to **Appearance → Themes** and activate **Matrix Starter**.

9. **Install & Activate Required Items (Theme + Plugins)**

   Run from the **theme root** (see [scripts/README.md](scripts/README.md) for full details):

   ```bash
   npm run flexi:install
   ```

   This bootstrap script will:

   * Clone **Matrix Component Importer**, **Matrix Sitemap Generator**, and **[Matrix Content Gathering](https://github.com/bernardhanna/matrix-content-gathering)** (client content form + flexi CSV import/export)
   * Install common plugins from WordPress.org (Classic Editor, Duplicate Page, Password Protected, Prevent Browser Caching, Rank Math SEO, WP Mail SMTP)
   * Attempt to **activate** the theme and all plugins (best-effort if the DB is reachable)

   After install:

   * Component importer: `/wp-admin/admin.php?page=matrix-ci-admin-page`
   * Content gathering: **Tools → Content Gathering**
   * Still required separately: **ACF Pro**, then `npm run build`

### Troubleshooting

* **Local by Flywheel tips:**

  * Make sure the site is **running** in Local.
  * For smoothest results, open **Local → Open Site Shell** and run:

    ```bash
    npm run flexi:install
    ```
* **Activation skipped or failed:**

  * Double-check `WP_PATH` in `.env` (and make sure it’s **quoted** if it includes spaces).
  * You can manually activate via WP-CLI:

    ```bash
    wp --path="$WP_PATH" plugin activate matrix-component-importer matrix-sitemap-generator matrix-content-gathering/matrix-content-export.php --skip-plugins --skip-themes
    wp --path="$WP_PATH" theme activate matrix-starter --skip-plugins --skip-themes
    ```
* **Composer Not Found:**

  * Install and add to PATH: [getcomposer.org](https://getcomposer.org/)
* **npm Errors:**

  * Check versions:

    ```bash
    node -v
    npm -v
    ```
* **Permission Issues:**

  * Consider using [nvm](https://github.com/nvm-sh/nvm) or adjusting file permissions.

### E2E Smoke Tests

Run multisite smoke checks (Ireland/UK/AU/Global):

```bash
npm run test:e2e:network
```

The default target is:

```text
http://localhost:10014
```

You can override with:

```bash
BASE_URL="http://your-local-domain-or-port" npm run test:e2e:network
```

### Contact Form Smoke Test

Run the structured contact form smoke test:

```bash
npm run test:e2e:contact-form
```

Run it in headed/live browser mode:

```bash
npm run test:e2e:contact-form:live
```

If your contact form is on a different page, set the path:

```bash
CONTACT_FORM_PATH="/contact-us/" npm run test:e2e:contact-form:live
```

To submit the form (instead of fill-only smoke mode), set:

```bash
CONTACT_FORM_SUBMIT=1 CONTACT_FORM_PATH="/contact-us/" npm run test:e2e:contact-form:live
```

Note: `CONTACT_FORM_SUBMIT=1` can send real emails to recipients configured in the Contact Form block (and BCC if configured).

### Donation Form Smoke Test

Run the donation form smoke test:

```bash
npm run test:e2e:donation-form
```

Run it in headed/live browser mode:

```bash
npm run test:e2e:donation-form:live
```

If your donation form is on a different page, set the path:

```bash
DONATION_FORM_PATH="/donate/" npm run test:e2e:donation-form:live
```

To submit the donation form (instead of fill-only smoke mode), set:

```bash
DONATION_FORM_SUBMIT=1 DONATION_FORM_PATH="/donate/" npm run test:e2e:donation-form:live
```

Note: `DONATION_FORM_SUBMIT=1` should only be used in safe/sandbox environments.

---

### Features

* **ACF Builder** for custom fields in code
* **Tailwind CSS** for utility-first styling
* **Alpine.js** for lightweight interactivity
* **TypeScript** for better code quality
* **Webpack** for asset bundling
* **Extended CPTs** to quickly create post types & taxonomies
* **log1x/navi** for powerful navigation management
* **log1x/modern-acf-options** for modern ACF options pages
* **log1x/modern-login** for a modern WordPress login screen

### Theme Tokens (for fast site duplication)

To re-skin this theme quickly for a new client/site, update semantic tokens in `tailwind.config.js`:

- `THEME_TOKENS.brand` for primary/secondary/accent brand colors
- `THEME_TOKENS.text` for heading/body/muted text colors
- `THEME_TOKENS.surface` for page/panel/background colors
- `THEME_TOKENS.shape` for field/card/pill radii
- `THEME_TOKENS.size` for touch target and form field heights
- `THEME_TOKENS.font` for font family names (`montserrat`, `comfortaa`, `primary`)

These tokens are additive and mapped to existing values by default, so current styles stay visually consistent.

#### Font utilities (PACE)

- `font-primary` — Public Sans (default)
- `font-montserrat` — Montserrat (headings, kicker, CTAs)
- `font-comfortaa` — Comfortaa (supporting body copy)

Loaded via `assets/css/app.css` and `inc/enqueue-fonts.php` (same Google Fonts URL). CSS variables: `--font-montserrat`, `--font-comfortaa`.

#### New semantic utility examples

- `bg-brand-primary`, `hover:bg-brand-primary-hover`
- `text-content-heading`, `text-content-muted`
- `bg-surface-page`, `bg-surface-warm`
- `rounded-field`, `rounded-card`, `rounded-pill`
- `min-h-touch`, `min-h-field`

#### Accessibility helpers

See **[docs/accessibility-basics.md](docs/accessibility-basics.md)** for WCAG 2.1 AA requirements, theme classes, testing checklist, and full criteria reference.

- `a11y-focus` for consistent `:focus-visible` ring
- `tap-target` for minimum 44px interactive hit area
- `hocus:` variant to share hover + keyboard focus styles (example: `hocus:bg-brand-primary-hover`)
- `.btn` on all `<button>` elements and button-styled controls (focus ring in `assets/css/app.css`)

### Documentation

- **[Wiki (GitHub sync source)](docs/wiki/)** — project setup, flexi-install, daily flow, tests ([publish to GitHub Wiki](https://github.com/bernardhanna/matrix-starter/wiki/))
- **[Coding guidelines](docs/coding-guidelines.md)** — layout (Grid over Flex), section structure, buttons, classes to avoid, naming
- **[Accessibility basics](docs/accessibility-basics.md)** — WCAG 2.1 AA, theme focus classes, testing checklist
- **[Desktop menu basics](docs/desktop-menu-basics.md)** — Navi, `#site-nav` section shell, logo, dropdown/mobile partials, ACF options
- **[ACF WordPress conversion](docs/acf-wordpress-conversion.md)** — convert static HTML to dynamic ACF sections (general)
- **[Flexi blocks basics](docs/flexi-blocks-basics.md)** — ACF Builder flexi blocks, section structure, padding repeater, CTAs, templates
- **[Examples folder basics](docs/examples-folder-basics.md)** — `examples/` layout, naming, code style, copy-to-production paths
- **[PACE Hero spec](docs/superpowers/specs/pace-hero.md)** — Figma hero block (`3:5` / `3:299`), ACF + template contract

### Getting Started

(Your quick-start docs or examples can go here.)

### Contact

Bernard Hanna — [bernard@matrixinternet.ie](mailto:bernard@matrixinternet.ie)
Project Link: [https://github.com/bernardhanna/matrix-starter](https://github.com/bernardhanna/matrix-starter)

## Acknowledgements

* [ACF Builder](https://www.advancedcustomfields.com/)
* [Tailwind CSS](https://tailwindcss.com/)
* [Alpine.js](https://alpinejs.dev/)
* [TypeScript](https://www.typescriptlang.org/)
* [WordPress](https://wordpress.org/)
