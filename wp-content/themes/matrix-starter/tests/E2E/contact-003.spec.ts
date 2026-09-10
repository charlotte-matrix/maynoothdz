// tests/E2E/contact-003.spec.ts
import { test, expect } from "@playwright/test";

/** ──────────────────────────────────────────────────────────
 *  Turn off headless and capture video/trace for THIS FILE
 *  (done before any describe/test is declared)
 *  ────────────────────────────────────────────────────────── */
test.use({ headless: false, video: "on", trace: "on" });

test.describe("Contact-003 – validation & submit", () => {
  test("shows validation errors then submits successfully", async ({
    page,
  }) => {
    await page.goto("/#contact");

    const name = page.getByTestId("field-name");
    const email = page.getByTestId("field-email");
    const submit = page.getByTestId("submit-btn");
    const success = page.getByTestId("form-success");

    /* 1. Trigger client-side validation */
    await submit.click();
    await expect(
      page.getByText("Please enter your name", { exact: true })
    ).toBeVisible();
    await expect(page.getByText(/valid email/i)).toBeVisible();

    /* 2. Fill with valid data */
    await name.fill("Ada Lovelace");
    await email.fill("ada@example.com");
    await submit.click();

    /* 3. Wait for success */
    await expect(success).toBeVisible({ timeout: 5_000 });
    await expect(success).toHaveText(/thanks/i);
  });
});
