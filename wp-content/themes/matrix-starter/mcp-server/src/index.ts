#!/usr/bin/env node

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListResourcesRequestSchema,
  ListToolsRequestSchema,
  ReadResourceRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";
import { z } from "zod";
import { THEME_ROOT } from "./config.js";
import {
  getFlexiInventory,
  scaffoldFlexiBlock,
  validateFlexiBlocks,
} from "./lib/flexi.js";
import { formatCommandResult, runNpmScript } from "./lib/exec.js";
import { getThemeStatus, readThemeDoc } from "./lib/status.js";
import { readThemeTokens, updateThemeTokens } from "./lib/tokens.js";

const server = new McpServer(
  {
    name: "matrix-starter",
    version: "0.1.0",
  },
  {
    capabilities: {
      tools: {},
      resources: {},
    },
  },
);

const TOOLS = [
  {
    name: "theme_status",
    description:
      "Report Matrix Starter repo health: dist assets, node_modules, .env, flexi layout parity.",
    inputSchema: {
      type: "object",
      properties: {},
      additionalProperties: false,
    },
  },
  {
    name: "list_flexi_layouts",
    description:
      "List flexi block layouts and whether each has a matching ACF definition and PHP template.",
    inputSchema: {
      type: "object",
      properties: {},
      additionalProperties: false,
    },
  },
  {
    name: "validate_flexi_blocks",
    description:
      "Validate that every acf-fields/partials/blocks/acf_{layout}.php has template-parts/flexi/{layout}.php.",
    inputSchema: {
      type: "object",
      properties: {},
      additionalProperties: false,
    },
  },
  {
    name: "scaffold_flexi_block",
    description:
      "Create a new flexi block pair (ACF Builder partial + template-parts/flexi template) from the theme starter pattern.",
    inputSchema: {
      type: "object",
      properties: {
        layout: {
          type: "string",
          description: "Layout slug, lowercase snake_case (e.g. content_002).",
        },
        label: {
          type: "string",
          description: "Human-readable block label shown in WordPress admin.",
        },
        overwrite: {
          type: "boolean",
          description: "Overwrite existing files when true. Defaults to false.",
        },
      },
      required: ["layout"],
      additionalProperties: false,
    },
  },
  {
    name: "get_theme_tokens",
    description: "Read semantic THEME_TOKENS from tailwind.config.js.",
    inputSchema: {
      type: "object",
      properties: {},
      additionalProperties: false,
    },
  },
  {
    name: "update_theme_tokens",
    description:
      "Patch one or more semantic token values in tailwind.config.js. Run theme_build after changing colors.",
    inputSchema: {
      type: "object",
      properties: {
        patches: {
          type: "array",
          items: {
            type: "object",
            properties: {
              group: { type: "string", description: "Token group (brand, text, surface, ...)." },
              key: { type: "string", description: "Token key within the group." },
              value: { type: "string", description: "New token value." },
            },
            required: ["group", "key", "value"],
          },
          minItems: 1,
        },
      },
      required: ["patches"],
      additionalProperties: false,
    },
  },
  {
    name: "theme_build",
    description: "Run npm run build (PostCSS + Webpack) to regenerate dist/ assets.",
    inputSchema: {
      type: "object",
      properties: {},
      additionalProperties: false,
    },
  },
  {
    name: "theme_test",
    description:
      "Run theme test scripts. Supports php, e2e, a11y, links, or full ci pipeline.",
    inputSchema: {
      type: "object",
      properties: {
        suite: {
          type: "string",
          enum: ["php", "e2e", "a11y", "links", "ci"],
          description: "Which npm test script to run. Defaults to php.",
        },
      },
      additionalProperties: false,
    },
  },
] as const;

const RESOURCES = [
  {
    uri: "theme://architecture",
    name: "Matrix Starter architecture",
    description: "High-level map of theme folders and extension points.",
    mimeType: "text/markdown",
  },
  {
    uri: "theme://docs/flexi-blocks-basics",
    name: "Flexi blocks basics",
    description: "How to build ACF flexible content blocks in Matrix Starter.",
    mimeType: "text/markdown",
  },
  {
    uri: "theme://docs/daily-flow",
    name: "Daily development flow",
    description: "Branching, build, and PR workflow for theme development.",
    mimeType: "text/markdown",
  },
] as const;

const architectureMarkdown = `# Matrix Starter architecture

Theme root: \`${THEME_ROOT}\`

## Extension points

| Concern | Location |
|---------|----------|
| Flexi ACF layouts | \`acf-fields/partials/blocks/acf_{layout}.php\` |
| Flexi templates | \`template-parts/flexi/{layout}.php\` |
| Flexi registration | \`acf-fields/partials/flexi.php\` (auto-loads blocks/*.php) |
| Hero fields | \`acf-fields/partials/hero.php\` + \`template-parts/hero/\` |
| Theme options | \`inc/theme-options.php\` + \`inc/theme-options/*.php\` |
| Semantic tokens | \`tailwind.config.js\` → \`THEME_TOKENS\` |
| Built assets | \`dist/\` (generated by \`npm run build\`) |

## Current bootstrap (not legacy pace:* scripts)

1. \`composer install\`
2. \`npm install\`
3. Copy \`.env.example\` → \`.env\` and set \`WP_PATH\`
4. \`npm run flexi:install\` — clones Matrix plugins, activates theme via WP-CLI
5. Install ACF Pro manually
6. \`npm run build\` or \`npm run dev\`

## Phase 1 MCP scope

Filesystem + npm tooling only. WordPress content/options tooling is planned for Phase 2 (WP-CLI).
`;

server.server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: TOOLS.map(({ name, description, inputSchema }) => ({
    name,
    description,
    inputSchema,
  })),
}));

server.server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  try {
    switch (name) {
      case "theme_status": {
        const status = await getThemeStatus();
        return {
          content: [{ type: "text", text: JSON.stringify(status, null, 2) }],
        };
      }

      case "list_flexi_layouts": {
        const layouts = await getFlexiInventory();
        return {
          content: [{ type: "text", text: JSON.stringify(layouts, null, 2) }],
        };
      }

      case "validate_flexi_blocks": {
        const result = await validateFlexiBlocks();
        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "scaffold_flexi_block": {
        const input = z
          .object({
            layout: z.string(),
            label: z.string().optional(),
            overwrite: z.boolean().optional(),
          })
          .parse(args ?? {});

        const result = await scaffoldFlexiBlock({
          layout: input.layout,
          label: input.label ?? "",
          overwrite: input.overwrite ?? false,
        });

        return {
          content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
        };
      }

      case "get_theme_tokens": {
        const tokens = await readThemeTokens();
        return {
          content: [{ type: "text", text: JSON.stringify(tokens, null, 2) }],
        };
      }

      case "update_theme_tokens": {
        const input = z
          .object({
            patches: z.array(
              z.object({
                group: z.string(),
                key: z.string(),
                value: z.string(),
              }),
            ),
          })
          .parse(args ?? {});

        const tokens = await updateThemeTokens(input.patches);
        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(
                {
                  updated: input.patches,
                  tokens,
                  nextStep: "Run theme_build to regenerate dist/*.css",
                },
                null,
                2,
              ),
            },
          ],
        };
      }

      case "theme_build": {
        const result = await runNpmScript("build", { timeoutMs: 15 * 60 * 1000 });
        return {
          content: [{ type: "text", text: formatCommandResult(result) }],
          isError: result.exitCode !== 0,
        };
      }

      case "theme_test": {
        const input = z
          .object({
            suite: z.enum(["php", "e2e", "a11y", "links", "ci"]).optional(),
          })
          .parse(args ?? {});

        const suite = input.suite ?? "php";
        const scriptMap = {
          php: "test:php",
          e2e: "test:e2e",
          a11y: "test:a11y",
          links: "test:links",
          ci: "ci",
        } as const;

        const result = await runNpmScript(scriptMap[suite], {
          timeoutMs: 30 * 60 * 1000,
        });

        return {
          content: [{ type: "text", text: formatCommandResult(result) }],
          isError: result.exitCode !== 0,
        };
      }

      default:
        return {
          content: [{ type: "text", text: `Unknown tool: ${name}` }],
          isError: true,
        };
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    return {
      content: [{ type: "text", text: message }],
      isError: true,
    };
  }
});

server.server.setRequestHandler(ListResourcesRequestSchema, async () => ({
  resources: RESOURCES.map(({ uri, name, description, mimeType }) => ({
    uri,
    name,
    description,
    mimeType,
  })),
}));

server.server.setRequestHandler(ReadResourceRequestSchema, async (request) => {
  const { uri } = request.params;

  switch (uri) {
    case "theme://architecture":
      return {
        contents: [
          {
            uri,
            mimeType: "text/markdown",
            text: architectureMarkdown,
          },
        ],
      };

    case "theme://docs/flexi-blocks-basics":
      return {
        contents: [
          {
            uri,
            mimeType: "text/markdown",
            text: await readThemeDoc("docs/flexi-blocks-basics.md"),
          },
        ],
      };

    case "theme://docs/daily-flow":
      return {
        contents: [
          {
            uri,
            mimeType: "text/markdown",
            text: await readThemeDoc("docs/wiki/3-daily-flow-for-development.md"),
          },
        ],
      };

    default:
      throw new Error(`Unknown resource: ${uri}`);
  }
});

async function main(): Promise<void> {
  const transport = new StdioServerTransport();
  await server.connect(transport);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
