# Theme scripts

## `flexi-install.sh` — project bootstrap installer

**Run from the theme root** (or Local’s “Open Site Shell” with the site running):

```bash
npm run flexi:install
# or
bash scripts/flexi-install.sh
# optional: bash scripts/flexi-install.sh --force-activate
```

### What it is

One-shot setup for a **new Matrix Starter site** (Local, staging, or similar). It does **not** build theme assets (`npm run build` is separate). It prepares WordPress with the theme and the standard Matrix plugin stack.

### What it does

| Step | Action |
|------|--------|
| 1 | Resolves WordPress root (`WP_PATH` in theme `.env`, or auto-detect four levels up from `scripts/`) |
| 2 | **Clones** private Matrix plugins from GitHub into `wp-content/plugins/` (skips if folder already exists) |
| 3 | **Installs** common plugins from WordPress.org via WP-CLI |
| 4 | **Activates** the active theme directory + all plugins (when WP-CLI can reach the database) |
| 5 | **Configures Password Protected** for local/staging (see below) |

### Password Protected (auto-configured)

When the database is reachable, the script applies these defaults:

| Setting | Value |
|---------|--------|
| Whole-site protection | **On** |
| Allow administrators | **On** (logged-in admins skip the gate) |
| Remember me | **On** |
| Remember for | **21 days** |
| Password | `matrix` + current year (e.g. `matrix2026`) — set in the script; staging/crawler gate only |

Optional override in `.env`: `MATRIX_SITE_PASSWORD=matrix2026`

### WP Mail SMTP (auto-configured when `.env` is filled in)

Copy `.env.example` → `.env` and set `MATRIX_SMTP_GOOGLE_CLIENT_SECRET` (and other vars as needed).

| Setting | Value |
|---------|--------|
| From email | `devs@matrixinternet.ie` (force on) |
| From name | Site title, or `MATRIX_SMTP_FROM_NAME` / `MATRIX_PROJECT_NAME` (force on) |
| Mailer | Google / Gmail (manual OAuth app) |
| Client ID | Default Matrix app ID, or `MATRIX_SMTP_GOOGLE_CLIENT_ID` |

In [Google Cloud Console](https://console.cloud.google.com/), add this **Authorized redirect URI** to the OAuth client:

`https://connect.wpmailsmtp.com/google/`

Then in WordPress: **WP Mail SMTP → Settings** → complete **Authorize** (not done by the install script).

```bash
MATRIX_SMTP_GOOGLE_CLIENT_SECRET=your-secret-here
```

### Plugins installed

**From GitHub (cloned):**

| Folder | Repository | Purpose |
|--------|------------|---------|
| `matrix-component-importer` | [matrix-component-importer](https://github.com/bernardhanna/matrix-component-importer) | Import flexi blocks / components into the theme |
| `matrix-sitemap-generator` | [matrix-sitemap-generator](https://github.com/bernardhanna/matrix-sitemap-generator) | XML sitemap generation |
| `matrix-content-gathering` | [matrix-content-gathering](https://github.com/bernardhanna/matrix-content-gathering) | Client content editing form, CSV import/export for ACF flexi ([plugin docs](https://github.com/bernardhanna/matrix-content-gathering)) |

**From WordPress.org** (via `wp plugin install`, not Packagist):

| Slug | Plugin |
|------|--------|
| `classic-editor` | Classic Editor (WordPress Contributors) |
| `duplicate-page` | Duplicate Page (mndpsingh287) |
| `password-protected` | Password Protected |
| `prevent-browser-caching` | Prevent Browser Caching (Kostya Tereshchuk) |
| `seo-by-rank-math` | Rank Math SEO |
| `wp-mail-smtp` | WP Mail SMTP |

Packagist ([wpackagist.org](https://wpackagist.org)) can install WP plugins with Composer, but this project uses **WP-CLI** from the install script — no extra Composer wiring needed.

### Requirements

- **Git** — to clone custom plugins
- **WP-CLI** — optional but recommended for install/activate (bundled in Local’s site shell)
- **Site running** — start the site in Local (status **Running**) before running
- **ACF Pro** — required by the theme and Matrix Content Gathering (install separately; not part of this script)

### Local by Flywheel — database connection

If you run `npm run flexi:install` from a normal terminal (not Local’s site shell), WP-CLI may fail with “Error establishing a database connection” because `DB_HOST=localhost` points at the wrong MySQL (e.g. Homebrew on port 3306).

The script auto-detects Local sites and sets `DB_HOST` to the correct socket, for example:

`localhost:/Users/you/Library/Application Support/Local/run/<site-id>/mysql/mysqld.sock`

That change is written to `wp-config.php` once and fixes WP-CLI for future runs.

**Still required:** the **rollingdonut** site must be **Running** in Local when you run the script.

Plugin downloads use a longer timeout (`WP_TIMEOUT_INSTALL`, default 180s). If installs were skipped before, re-run after the site is running — the script installs all WordPress.org plugins in one batch with `--activate`.

### Flags

- `--force-activate` — try activation even when WP-CLI cannot query the database (e.g. site stopped)

### After install

- Matrix Component Importer: `/wp-admin/admin.php?page=matrix-ci-admin-page`
- Matrix Content Gathering: **Tools → Content Gathering**
- If content-gathering needs PHP deps: `cd wp-content/plugins/matrix-content-gathering && composer install`

### Related npm scripts

Theme-specific ACF seeders (run **after** flexi install + ACF setup), e.g. `npm run pace:home-setup` — see root `package.json`.
