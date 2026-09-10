# Matrix Starter Wiki

**Matrix Starter** is a WordPress theme for Matrix Internet projects: ACF Builder flexi blocks, Tailwind CSS, Alpine.js, and a standard plugin/tooling stack.

**Repo:** https://github.com/bernardhanna/matrix-starter  
**Theme README:** see repo root `README.md`  
**Scripts detail:** `scripts/README.md` in the repo

---

## Wiki pages

| Page | What it covers |
|------|----------------|
| [1. New projects (Git + Plesk)](1-using-matrix-starter-for-new-projects-git-plesk-workflow.md) | Create a project repo from the starter, wire Plesk, first deploy |
| [2. Local setup & build tools](2-local-setup-and-build-tools.md) | Clone, Composer/npm, `.env`, `npm run dev` / `build`, `flexi:install` |
| [3. Daily development flow](3-daily-flow-for-development.md) | Branches, PRs, rebase, checklist |
| [4. flexi-install & environment](4-flexi-install-and-environment.md) | Bootstrap script, plugins, Password Protected, WP Mail SMTP, `.env` reference |
| [5. Tests & quality checks](Tests.md) | Playwright, a11y, Lighthouse, link checker |

> **Publishing to GitHub:** Copy each file’s body into the matching page at https://github.com/bernardhanna/matrix-starter/wiki/ (GitHub wiki titles/slugs may differ slightly from filenames here).

---

## Quick start (new site)

1. Create or clone the **project repo** (theme in `wp-content/themes/<project-theme>/`).
2. `composer install` and `npm install` in the theme folder.
3. `cp .env.example .env` — set `WP_PATH`, `BASE_URL`, and SMTP secret if needed.
4. Start the site in **Local** (status **Running**).
5. `npm run flexi:install` — plugins, theme activation, staging lock.
6. Install **ACF Pro** manually (not included in flexi-install).
7. `npm run build` — production CSS/JS.
8. Complete **WP Mail SMTP → Authorize** in wp-admin if using Gmail.

---

## Prerequisites

- PHP 7.4+
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) (LTS recommended)
- [Git](https://git-scm.com/)
- [Local by Flywheel](https://localwp.com/) (recommended for local WordPress)
- **WP-CLI** (included in Local’s “Open Site Shell”)
- **ACF Pro** (required by theme and Matrix Content Gathering)

---

## Contact

Bernard Hanna — [bernard@matrixinternet.ie](mailto:bernard@matrixinternet.ie)
