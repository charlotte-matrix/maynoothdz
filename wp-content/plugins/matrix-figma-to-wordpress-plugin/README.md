# Matrix Figma to WordPress

WordPress plugin that turns Figma designs into **Matrix Starter** theme sections using Cursor Cloud Agents or Local Cursor.

## What it does

Paste Figma links (batch supported), pick a section type (Flexi, Hero, Footer, CPT, etc.), and generate drop-in theme files via agents that use **Figma MCP** and **matrix-starter MCP**. Seed completed flexi blocks onto the `/flexi/` review page, redo individual sections, and run light QC checks.

## Features

- **Batch builds** — multiple Figma links per job with optional section type and instructions
- **Section type helper** — Flexi, Hero, Footer, Header, Blog, 404, CPT, Taxonomy, Theme option, and more
- **Dual runtime** — Cursor Cloud Agent (default) or **Generate (Local)** headless agent on this machine
- **Local auto pipeline** — `cursor agent --print` → `npm run build` → seed/resync `/flexi/` → job complete
- **Redo** — **Redo (Local)** for same-machine Cursor quality, or **Redo (Cloud)** for a GitHub PR
- **`/flexi/` seeding** — add layouts to the review page; **Resync on /flexi/** refreshes existing rows after a rebuild
- **QC tab** — file existence checks, optional MCP preflight CLI, manual approve

## Requirements

- [Matrix Starter](https://github.com/Matrix-Internet/matrix-starter-theme) theme (or compatible drop-in structure)
- ACF Pro
- Cursor API key (Cloud mode)
- Theme `mcp-server` built (`npm run build`) for preflight CLI on Local
- Cursor MCP: Figma + matrix-starter configured for agents
- **Figma personal access token** in Settings (pre-fetches design context into local prompts — required for headless parity with IDE)

### Headless local vs Cursor IDE

The WP **Generate (Local)** pipeline runs `cursor agent --print` from PHP. That is not identical to the Cursor IDE agent:

| | IDE agent | Headless (`--print`) |
|---|-----------|----------------------|
| Figma MCP | Usually authenticated | Often `requires_authentication` until you run `cursor agent mcp login Figma` |
| matrix-starter MCP | From workspace config | Written to `{theme}/.cursor/mcp.json` on each local run |
| Design reference | `get_design_context` | **Pre-fetched** into the job prompt via Figma REST API (when token is set) |

The pre-fetched brief includes **visible layers only**, **Implementation tokens** (colours, typography, spacing), and **SVG-first asset exports** for decorative vectors. Hidden Figma layers (eye icon off) are listed under Excluded and must not appear in templates.

For best results: set a **Figma token** in plugin Settings, run `cursor agent mcp login Figma` once on the machine, and build `mcp-server` (`npm run build`).

## Install

Cloned automatically by `matrix-starter` setup scripts (`flexi-install.sh` via `matrix-plugins.sh`):

```bash
# From matrix-starter-theme
./scripts/flexi-install.sh
```

Or manually:

```bash
gh repo clone Matrix-Internet/matrix-figma-to-wordpress-plugin wp-content/plugins/matrix-figma-to-wordpress-plugin
wp plugin activate matrix-figma-to-wordpress
```

## Usage

1. WP Admin → **Figma to WordPress → Settings** — Cursor API key, GitHub repo URL, theme path
2. **Figma to WordPress → New build** — paste Figma links (supports Figma “Copy example prompt” paste)
3. **Generate (Cloud)** or **Generate (Local)** — local runs headlessly when API key + Cursor CLI are set
4. Job page shows live log while the local agent runs; `/flexi/` is seeded automatically on success
5. **Redo (Local)** / **Redo (Cloud)** or **Approve** per section

**Local vs Cloud redo:** Cloud redo updates the remote theme via PR — pull into Local before expecting ACF/template changes here. Local redo exports a prompt to run in Cursor on this machine (same workflow as the Cursor app). After any rebuild, click **Resync on /flexi/** so review-page content matches the new fields.

Local queue prompts are written to `{theme}/.cursor/figma-to-wordpress-jobs/job-{id}.md`.

## Related

- Theme MCP: `mcp-server/README.md`
- Agent workflow: `AGENTS.md` (in matrix-starter-theme)
- Theme docs: `docs/figma-to-wordpress.md`

## Roadmap

- Phase 2: PR branch naming, CI gates
- Phase 3: Figma visual diff, axe auto-run, QC score
