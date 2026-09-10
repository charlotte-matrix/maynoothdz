# 3. Daily Flow for Development on Matrix Test

## Quick rules

- One section/file per branch & PR when possible.
- Never commit directly to `master` / `main` without agreement.
- Pull/rebase from `main` before starting and before merging.
- Test locally; keep PRs small.
- Run `npm run build` before merge if CSS/JS changed.

---

## Daily flow (terminal)

### 1. Get latest main

```bash
git checkout main
git fetch origin
git pull --rebase origin main
```

### 2. Create feature branch

```bash
git switch -c feat/section-short-name
git push -u origin feat/section-short-name
# e.g. feat/section-hero-cta
```

### 3. Develop

- Start Local site (**Running**).
- `npm run dev` in the theme folder.
- Edit only your assigned file(s) where possible.

### 4. Commit

```bash
git add path/to/your-file.php
git commit -m "[Section] Implement hero CTA to match Figma"
```

### 5. Push and open PR

- Base: `main`
- Compare: `feat/section-short-name`
- Link Figma / Monday ticket.

### 6. Rebase before merge

```bash
git fetch origin
git rebase origin/main
git push --force-with-lease
```

### 7. Merge

Squash & merge recommended. Pull `main` again locally before new work.

---

## GitHub Desktop (or UI)

1. Fetch origin → Checkout `main` → Pull
2. New branch → `feat/section-[short-name]`
3. Edit your file(s) → Commit to `feat/…`
4. Push origin → Open Pull Request
5. Before merge: Update from `main` (rebase) → Merge after approval

---

## Branch naming

| Type | Pattern | Example |
|------|---------|---------|
| Feature | `feat/section-[name]` | `feat/section-about-grid` |
| Fix | `fix/section-[name]` | `fix/section-footer-links` |

Commit messages: small and focused — e.g. `[Section] Tweak spacing at md/lg`.

---

## PR checklist

- [ ] Matches Figma (desktop / tablet / mobile)
- [ ] Only touches intended file(s)
- [ ] No PHP notices; `npm run build` passes
- [ ] Tailwind + theme conventions (no random inline CSS)
- [ ] Visual check (e.g. [Pixel Parallel](https://chromewebstore.google.com/detail/pixelparallel-by-htmlburg/iffnoibnepbcloaaagchjonfplimpkob))
- [ ] Rebased on latest `main`
- [ ] `.env` / secrets not in the diff

---

## Conflict avoidance

- Separate files per developer where possible.
- Rebase often.
- Coordinate changes to `header.php`, `functions.php`, or shared flexi partials.
- On conflict during rebase: resolve, test locally, then `git rebase --continue`.

---

## One-time setup (first day on project)

```bash
git clone <repo-url>
cd <theme-folder>
composer install
npm install
cp .env.example .env
# edit WP_PATH, BASE_URL
npm run flexi:install   # site Running in Local
# install ACF Pro
npm run dev
```
