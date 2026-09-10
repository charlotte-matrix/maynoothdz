import fs from "node:fs/promises";
import path from "node:path";
import { PATHS, THEME_ROOT } from "../config.js";
import { getFlexiInventory } from "../lib/flexi.js";

export type ThemeStatus = {
  themeRoot: string;
  nodeModulesPresent: boolean;
  distAssets: string[];
  envFilePresent: boolean;
  flexiLayoutCount: number;
  invalidFlexiLayouts: string[];
  notes: string[];
};

async function listDistAssets(): Promise<string[]> {
  try {
    const entries = await fs.readdir(PATHS.dist);
    return entries.sort();
  } catch {
    return [];
  }
}

export async function getThemeStatus(): Promise<ThemeStatus> {
  const [nodeModulesPresent, envFilePresent, distAssets, flexiLayouts] = await Promise.all([
    fs
      .access(path.join(THEME_ROOT, "node_modules"))
      .then(() => true)
      .catch(() => false),
    fs
      .access(path.join(THEME_ROOT, ".env"))
      .then(() => true)
      .catch(() => false),
    listDistAssets(),
    getFlexiInventory(),
  ]);

  const invalidFlexiLayouts = flexiLayouts
    .filter((layout) => !layout.valid)
    .map((layout) => layout.layout);

  const notes = [
    "Phase 1 MCP covers repo tooling only (flexi scaffolding, tokens, build, tests).",
    "WordPress runtime operations (Theme Options, page seeding) are Phase 2 via WP-CLI.",
    "Legacy pace:* npm scripts are not exposed — use flexi:install for bootstrap.",
  ];

  return {
    themeRoot: THEME_ROOT,
    nodeModulesPresent,
    distAssets,
    envFilePresent,
    flexiLayoutCount: flexiLayouts.length,
    invalidFlexiLayouts,
    notes,
  };
}

export async function readThemeDoc(relativePath: string): Promise<string> {
  const normalized = relativePath.replace(/^\/+/, "");
  const fullPath = path.join(THEME_ROOT, normalized);

  if (!fullPath.startsWith(path.join(THEME_ROOT, "docs"))) {
    throw new Error("Only docs/* resources are readable in Phase 1.");
  }

  return fs.readFile(fullPath, "utf8");
}
