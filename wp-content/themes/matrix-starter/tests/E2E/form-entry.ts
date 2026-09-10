import { test, expect } from "@playwright/test";

test("Contact-003 form submits", async ({ page }) => {
  await page.goto(process.env.BASE_URL ?? "http://localhost:8888");
  const form = page.locator('form[data-theme-form^="contact-003-"]');
  await form.getByLabel("Name").fill("Ada");
  await form.getByLabel("Email").fill("ada@example.com");
  await form.getByLabel("Message").fill("Hello world!");
  await form.locator("button[type=submit]").click();
  await expect(page).toHaveURL(/thank|success/i);
});
