// tests/E2E/smoke.spec.ts
import { test, expect } from "@playwright/test";

test("home loads", async ({ page }) => {
  await page.goto("/");
  await expect(page).toHaveTitle("Hanley Pepper"); // ← exact match
});
