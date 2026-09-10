# 2. Local Setup & Build Tools for Matrix Starter Projects

Assumes you have a **project repo** cloned locally and WordPress running in **Local**.

---

## 2.1 Clone and enter the theme

```bash
cd "/Users/you/Local Sites/your-site/app/public/wp-content/themes/your-project-theme"
```

---

## 2.2 Install dependencies

```bash
composer install
npm install
```

Check versions if npm fails:

```bash
node -v
npm -v
```

---

## 2.3 Configure `.env`

```bash
cp .env.example .env
```

Edit `.env` for your Local site:

| Variable | Purpose |
|----------|---------|
| `WP_PATH` | WordPress root (`wp-config.php` folder). **Quote paths with spaces.** |
| `WP_HOME` / `BASE_URL` | Local URL (port from Local, e.g. `http://localhost:10029/`) |
| `DEV_SERVER_PORT` | Webpack dev server (default `3000`) |
| `WP_TIMEOUT` / `WP_TIMEOUT_INSTALL` | WP-CLI timeouts for flexi-install |
| `MATRIX_SMTP_*` | Gmail SMTP bootstrap (secret in `.env` only) |

Example:

```dotenv
WP_HOME=http://localhost:10029/
BASE_URL=http://localhost:10029/
WP_PATH="/Users/you/Local Sites/your-site/app/public"
```

The tooling **reads** `.env` only; it does not delete it.

---

## 2.4 Run flexi-install (plugins + theme bootstrap)

**Start the site in Local (Running)** first.

From the theme root:

```bash
npm run flexi:install
```

Or from Local → **Open Site Shell**:

```bash
npm run flexi:install
```

This script:

- Clones Matrix plugins: **matrix-component-importer**, **matrix-sitemap-generator**, **matrix-content-gathering**
- Installs from WordPress.org: Classic Editor, Duplicate Page, Password Protected, Prevent Browser Caching, Rank Math SEO, WP Mail SMTP
- Activates theme + plugins (when WP-CLI can reach the DB)
- Configures **Password Protected** (staging lock, password `matrix` + year, admins allowed)
- Configures **WP Mail SMTP** when `MATRIX_SMTP_GOOGLE_CLIENT_SECRET` is set in `.env`

**Does not:** run `npm run build`, install ACF Pro, or run `pace:*` content seeders.

**Still required manually:** [ACF Pro](https://www.advancedcustomfields.com/pro/)

See **[4. flexi-install & environment](4-flexi-install-and-environment.md)** for full detail.

### Local database / WP-CLI

If WP-CLI fails with “Error establishing a database connection” from a normal terminal, the script can patch `wp-config.php` to use Local’s MySQL **socket**. The site must still be **Running** in Local.

---

## 2.5 Development watchers

```bash
npm run dev
```

- Watches and rebuilds CSS (app, editor, WooCommerce if used)
- Bundles JS and runs webpack dev server

Keep this running while developing.

---

## 2.6 Production build

```bash
npm run build
```

Builds `dist/app.css`, `dist/editor.css`, `dist/woocommerce.css`, and production JS.

Commit `dist/` according to your project policy (many Matrix projects commit built assets for Plesk deploys).

---

## 2.7 Optional: PACE / flexi content seeders

After ACF field groups exist, project-specific seeders may be available, e.g.:

```bash
npm run pace:home-setup
npm run pace:flexi-setup
```

See `package.json` for the full list (`pace:*` scripts).

---

## 2.8 Commit and push

```bash
git status
git add .
git commit -m "Initial project setup based on Matrix Starter"
git push origin main
```

Do **not** commit `.env` or SMTP secrets.

---

## Theme documentation (in repo)

| Doc | Topic |
|-----|--------|
| `docs/coding-guidelines.md` | Layout, sections, naming |
| `docs/accessibility-basics.md` | WCAG, focus rings, testing |
| `docs/flexi-blocks-basics.md` | ACF flexi blocks |
| `docs/desktop-menu-basics.md` | Navigation / Navi |
| `docs/acf-wordpress-conversion.md` | Static HTML → ACF |

---

## Next step

**[3. Daily Flow](3-daily-flow-for-development.md)** for branch/PR workflow.
