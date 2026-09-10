import fs from "node:fs/promises";
import { PATHS } from "../config.js";

export type ThemeTokens = Record<string, Record<string, string>>;

function extractThemeTokensBlock(source: string): string | null {
  const start = source.indexOf("const THEME_TOKENS = {");
  if (start === -1) {
    return null;
  }

  let depth = 0;
  let started = false;

  for (let i = start; i < source.length; i += 1) {
    const char = source[i];
    if (char === "{") {
      depth += 1;
      started = true;
    } else if (char === "}") {
      depth -= 1;
      if (started && depth === 0) {
        return source.slice(start, i + 1);
      }
    }
  }

  return null;
}

function parseTokenValue(raw: string): string {
  return raw.trim().replace(/^['"]|['"]$/g, "");
}

export async function readThemeTokens(): Promise<ThemeTokens> {
  const source = await fs.readFile(PATHS.tailwindConfig, "utf8");
  const block = extractThemeTokensBlock(source);

  if (!block) {
    throw new Error("Could not find THEME_TOKENS in tailwind.config.js");
  }

  const tokens: ThemeTokens = {};
  let currentGroup: string | null = null;

  for (const line of block.split("\n")) {
    const groupMatch = /^\s{2}([a-zA-Z]+):\s*\{\s*$/.exec(line);
    if (groupMatch) {
      currentGroup = groupMatch[1];
      tokens[currentGroup] = {};
      continue;
    }

    const valueMatch = /^\s{4}([a-zA-Z0-9]+):\s*('([^']*)'|"([^"]*)"|([^,]+)),?\s*$/.exec(
      line,
    );
    if (valueMatch && currentGroup) {
      const key = valueMatch[1];
      const value = parseTokenValue(valueMatch[3] ?? valueMatch[4] ?? valueMatch[5] ?? "");
      tokens[currentGroup][key] = value;
    }

    if (/^\s{2}\},?\s*$/.test(line)) {
      currentGroup = null;
    }
  }

  return tokens;
}

export type TokenPatch = {
  group: string;
  key: string;
  value: string;
};

export async function updateThemeTokens(patches: TokenPatch[]): Promise<ThemeTokens> {
  let source = await fs.readFile(PATHS.tailwindConfig, "utf8");

  for (const patch of patches) {
    const pattern = new RegExp(
      `(\\s{4}${patch.key}:\\s*)(['"])[^'"]*(\\2)`,
      "m",
    );

    const groupBlock = new RegExp(
      `${patch.group}:\\s*\\{[\\s\\S]*?\\n\\s{2}\\}`,
      "m",
    );

    if (!groupBlock.test(source)) {
      throw new Error(`Token group not found: ${patch.group}`);
    }

    const scopedPattern = new RegExp(
      `(${patch.group}:\\s*\\{[\\s\\S]*?\\s{4}${patch.key}:\\s*)(['"])[^'"]*(\\2)`,
      "m",
    );

    if (!scopedPattern.test(source)) {
      throw new Error(`Token not found: ${patch.group}.${patch.key}`);
    }

    source = source.replace(scopedPattern, `$1'${patch.value.replace(/'/g, "\\'")}'`);
  }

  await fs.writeFile(PATHS.tailwindConfig, source, "utf8");
  return readThemeTokens();
}
