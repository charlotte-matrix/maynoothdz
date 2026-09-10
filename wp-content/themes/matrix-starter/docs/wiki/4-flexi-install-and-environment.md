# 4. flexi-install & environment

Reference for `npm run flexi:install` and theme `.env` variables.

---

## Command

```bash
npm run flexi:install
# optional:
bash scripts/flexi-install.sh --force-activate
```

Run from the **theme root**. Site must be **Running** in Local.

---

## What it does

| Step | Action |
|------|--------|
| 1 | Resolve WordPress root (`WP_PATH` or auto-detect) |
| 2 | Clone Matrix GitHub plugins (skip if folder exists) |
| 3 | Install WordPress.org plugins via WP-CLI |
| 4 | Activate theme + plugins |
| 5 | Configure Password Protected |
| 6 | Configure WP Mail SMTP (if secret in `.env`) |

**Does not:** `npm run build`, ACF Pro, OAuth “Authorize” click in wp-admin.

---

## Plugins

### Cloned from GitHub

| Folder | Repo | Purpose |
|--------|------|---------|
| `matrix-component-importer` | [matrix-component-importer](https://github.com/bernardhanna/matrix-component-importer) | Import flexi components |
| `matrix-sitemap-generator` | [matrix-sitemap-generator](https://github.com/bernardhanna/matrix-sitemap-generator) | XML sitemap |
| `matrix-content-gathering` | [matrix-content-gathering](https://github.com/bernardhanna/matrix-content-gathering) | Client content form + CSV flexi import/export |

### WordPress.org

Classic Editor, Duplicate Page, Password Protected, Prevent Browser Caching, Rank Math SEO, WP Mail SMTP.

---

## Password Protected (staging)

Applied automatically when the database is reachable:

| Setting | Value |
|---------|--------|
| Site lock | On |
| Allow administrators | On |
| Remember me | On (21 days) |
| Password | `matrix` + current year (e.g. `matrix2026`) |

Set in the install script — **not a secret**; blocks crawlers on staging/local.

Optional override in `.env`:

```dotenv
MATRIX_SITE_PASSWORD=matrix2026
```

---

## WP Mail SMTP (Gmail)

1. Copy `.env.example` → `.env`.
2. Set `MATRIX_SMTP_GOOGLE_CLIENT_SECRET` (never commit `.env`).
3. Re-run `npm run flexi:install`.

Defaults (can override in `.env`):

| Setting | Default |
|---------|---------|
| From email | `devs@matrixinternet.ie` |
| From name | Site title, or `MATRIX_SMTP_FROM_NAME` / `MATRIX_PROJECT_NAME` |
| Mailer | Google / Gmail |
| Client ID | Matrix shared app ID in `.env.example` |

**Google Cloud Console:** add authorized redirect URI:

`https://connect.wpmailsmtp.com/google/`

Then in WordPress: **WP Mail SMTP → Settings → Authorize** (manual step).

---

## `.env` reference

Copy from `.env.example`:

```dotenv
# URLs — tests, Playwright, a11y
WP_HOME=http://localhost:10029/
BASE_URL=http://localhost:10029/

# WordPress root — required for WP-CLI outside Local shell
WP_PATH="/Users/you/Local Sites/your-site/app/public"

# flexi-install timeouts
WP_TIMEOUT=15
WP_TIMEOUT_INSTALL=180

# SMTP (secret required for auto-config)
MATRIX_SMTP_FROM_EMAIL=devs@matrixinternet.ie
MATRIX_SMTP_FROM_NAME=
MATRIX_PROJECT_NAME=
MATRIX_SMTP_GOOGLE_CLIENT_ID=...
MATRIX_SMTP_GOOGLE_CLIENT_SECRET=

# Webpack
DEV_SERVER_PORT=3000

# DB — usually empty on Local (wp-config handles connection)
DB_HOST=
DB_NAME=
DB_USER=
DB_PASSWORD=
```

---

## After install

| Tool | Where |
|------|--------|
| Component Importer | `/wp-admin/admin.php?page=matrix-ci-admin-page` |
| Content Gathering | **Tools → Content Gathering** |
| Content Gathering Composer | `cd wp-content/plugins/matrix-content-gathering && composer install` |

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| DB connection error | Site **Running** in Local; re-run install; check `WP_PATH` |
| Plugins not installed | Re-run after DB fix; script uses 180s install timeout |
| SMTP not configured | Add `MATRIX_SMTP_GOOGLE_CLIENT_SECRET` to `.env`, re-run |
| Activation skipped | `bash scripts/flexi-install.sh --force-activate` or activate in wp-admin |

Full script docs: `scripts/README.md` in the repo.
