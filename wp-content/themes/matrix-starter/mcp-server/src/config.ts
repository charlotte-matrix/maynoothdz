import path from "node:path";
import { fileURLToPath } from "node:url";

const moduleDir = path.dirname(fileURLToPath(import.meta.url));

/** Absolute path to the Matrix Starter theme root (parent of mcp-server/). */
export const THEME_ROOT = path.resolve(moduleDir, "../..");

export const PATHS = {
  acfBlocks: path.join(THEME_ROOT, "acf-fields/partials/blocks"),
  flexiTemplates: path.join(THEME_ROOT, "template-parts/flexi"),
  tailwindConfig: path.join(THEME_ROOT, "tailwind.config.js"),
  dist: path.join(THEME_ROOT, "dist"),
  docs: path.join(THEME_ROOT, "docs"),
  envExample: path.join(THEME_ROOT, ".env.example"),
  flexiInstallScript: path.join(THEME_ROOT, "scripts/flexi-install.sh"),
} as const;

export function layoutFromAcfFilename(filename: string): string | null {
  const match = /^acf_(.+)\.php$/.exec(filename);
  return match?.[1] ?? null;
}

export function layoutFromFlexiFilename(filename: string): string | null {
  const match = /^(.+)\.php$/.exec(filename);
  return match?.[1] ?? null;
}
