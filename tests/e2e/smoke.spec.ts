import { test, expect } from '@playwright/test';

// Placeholder smoke test; the full suite is written in session 3 (TEST-PLAN).
test('guest is redirected to login', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveURL(/\/login/);
});
