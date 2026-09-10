// playwright.config.ts
import { defineConfig } from "@playwright/test";
import * as dotenv from "dotenv";
dotenv.config({ path: ".env" });
// ─────────────────────────────────────────────
// 1)  Load environment variables from .env
// ─────────────────────────────────────────────
dotenv.config({ path: ".env" });

export default defineConfig({
  testDir: "tests/E2E",
  outputDir: "tests/pw-artifacts", // traces, videos, screenshots
  reporter: [
    ["list"],
    ["html", { outputFolder: "tests/playwright-report", open: "never" }],
  ],
  use: {
    baseURL:
      process.env.BASE_URL || process.env.WP_HOME || "http://localhost:8888",
    trace: "on-first-retry",
    video: "retain-on-failure",
  },
  projects: [
    { name: "chromium", use: { browserName: "chromium" } },
    { name: "firefox", use: { browserName: "firefox" } },
  ],
});
