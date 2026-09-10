# Tests

Quality checks live in the theme repo. Set `BASE_URL` in `.env` (or export it) to match your Local port.

---

## Environment

```bash
cp .env.example .env
# BASE_URL=http://localhost:10029/
```

---

## PHP (Pest)

```bash
composer install
npm run test:php
```

---

## Playwright (E2E)

```bash
npm run test:e2e
npm run test:e2e:network          # multisite smoke
npm run test:e2e:contact-form
npm run test:e2e:donation-form
npm run test:watch                # UI mode
```

Override URL:

```bash
BASE_URL="http://localhost:10029/" npm run test:e2e:network
```

Contact form on a custom path:

```bash
CONTACT_FORM_PATH="/contact-us/" npm run test:e2e:contact-form
```

**Warning:** `CONTACT_FORM_SUBMIT=1` sends real emails — use sandbox only.

Donation form:

```bash
DONATION_FORM_PATH="/donate/" npm run test:e2e:donation-form
DONATION_FORM_SUBMIT=1 npm run test:e2e:donation-form:live   # sandbox only
```

---

## Accessibility

```bash
npm run test:a11y:quick
npm run test:a11y:full
```

See repo `docs/accessibility-basics.md`.

---

## Performance (Lighthouse CI)

```bash
npm run test:perf
npm run perf:open
```

---

## Broken links

```bash
npm run test:links
```

---

## Visual regression

```bash
npm run test:visual
```

---

## Full CI-style run locally

```bash
npm run ci
```

Runs: PHP tests, E2E, a11y, perf, link check (can be slow).

---

## Codegen (new E2E tests)

```bash
npm run e2e:codegen
```

Requires `BASE_URL` in `.env`.
