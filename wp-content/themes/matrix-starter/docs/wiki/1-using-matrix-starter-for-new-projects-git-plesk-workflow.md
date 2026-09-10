# 1. Using Matrix Starter for New Projects (Git + Plesk Workflow)

This page is for **starting a new client project** from Matrix Starter and connecting it to **GitHub + Plesk**.

---

## Overview

Typical flow:

1. Create a **new GitHub repo** for the project (do not develop directly on `matrix-starter` main for client work).
2. Copy or clone the starter into that repo (often as the theme folder name for the project).
3. Set up **Local** for development.
4. Wire **Plesk** to pull from the project repo and deploy the theme.
5. Run **flexi-install** and **build** on each environment as needed.

---

## 1.1 Create the project repository

**Option A — GitHub template / duplicate**

- Create `your-client-project` on GitHub.
- Push the theme based on `matrix-starter` (rename theme folder if required, e.g. `rollingdonut`).

**Option B — Clone starter into theme path**

```bash
cd "/path/to/Local Sites/your-site/app/public/wp-content/themes"
git clone https://github.com/bernardhanna/matrix-starter.git your-project-theme
cd your-project-theme
git remote set-url origin git@github.com:your-org/your-project-theme.git
```

Use a **project-specific repo** so client changes never overwrite the upstream starter.

---

## 1.2 Branch strategy

| Branch | Use |
|--------|-----|
| `main` / `master` | Production-ready theme |
| `feat/...` | Features (one section/file per PR when possible) |
| `fix/...` | Hotfixes |

Do not commit secrets (`.env`, API keys). Commit `.env.example` only.

---

## 1.3 Plesk deployment

1. In Plesk, enable **Git** for the site (or subdomain).
2. Point the repository to the **project GitHub repo**.
3. Set deploy path to the theme directory under `wp-content/themes/<theme-slug>/`.
4. Configure deploy actions if needed, e.g.:

   ```bash
   composer install --no-dev
   npm ci
   npm run build
   ```

5. On the server, install **ACF Pro** and any licensed plugins outside Git.
6. Run `wp` commands or wp-admin once to activate theme/plugins if not deployed via CI.

**Staging:** flexi-install sets **Password Protected** with password `matrix` + current year (e.g. `matrix2026`). Logged-in administrators bypass the gate. This is for crawlers/staging only, not high-security auth.

---

## 1.4 First deploy checklist

- [ ] Project repo created and remote set
- [ ] Theme on server at correct path
- [ ] `npm run build` assets present (`dist/`)
- [ ] ACF Pro installed and licensed
- [ ] `flexi-install` run once on staging (or plugins installed manually)
- [ ] WP Mail SMTP authorized (Google OAuth in wp-admin)
- [ ] Rank Math / sitemap / content-gathering URLs checked
- [ ] Staging password documented for the team (`matrix2026` style)

---

## Next step

Continue with **[2. Local Setup & Build Tools](2-local-setup-and-build-tools.md)**.
