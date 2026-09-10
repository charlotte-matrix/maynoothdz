# Matrix Starter MCP (Phase 1)

Dev tooling MCP server for the Matrix Starter WordPress theme. Exposes filesystem and npm workflows to Cursor agents — **not** WordPress runtime admin.

Legacy `pace:*` setup scripts are intentionally excluded. Use `npm run flexi:install` for project bootstrap.

## Tools

| Tool | Purpose |
|------|---------|
| `theme_status` | Repo health: `dist/`, dependencies, flexi parity |
| `list_flexi_layouts` | Inventory of ACF + template pairs |
| `validate_flexi_blocks` | Fail if any layout is missing its pair |
| `scaffold_flexi_block` | Generate `acf_{layout}.php` + `{layout}.php` |
| `get_theme_tokens` | Read `THEME_TOKENS` from `tailwind.config.js` |
| `update_theme_tokens` | Patch semantic tokens (then run `theme_build`) |
| `theme_build` | `npm run build` |
| `theme_test` | `test:php`, `test:e2e`, `test:a11y`, `test:links`, or `ci` |

## Resources

| URI | Content |
|-----|---------|
| `theme://architecture` | Folder map and bootstrap flow |
| `theme://docs/flexi-blocks-basics` | Flexi block conventions |
| `theme://docs/daily-flow` | Branch/build/PR workflow |

## Install

```bash
cd mcp-server
npm install
npm run build
```

## Cursor configuration

Add to your MCP settings (adjust the theme path):

```json
{
  "mcpServers": {
    "matrix-starter": {
      "command": "node",
      "args": ["/absolute/path/to/theme/mcp-server/dist/index.js"]
    }
  }
}
```

## Phase 2 (planned)

WP-CLI tools for Theme Options reads, plugin checks, and generic page seeding — without reviving client-specific `pace:*` seeders.
