// @ts-check
const { test, expect } = require('@playwright/test');

async function login(page, username = 'qa_owner_test', password = 'QaFortressOwner!2026') {
  await page.goto('/admin/login.php');
  await page.fill('#login-username', username);
  await page.fill('#login-password', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard\.php/);
}

test.describe('Property CRUD (Tier 2)', () => {
  test('QF-FUNC-101: create a property, appears in list, then soft-delete to Bin, then restore', async ({ page }) => {
    await login(page);
    await page.goto('/admin/property-form.php');
    const uniqueName = 'QA Fortress Test Property ' + Date.now();
    await page.fill('input[name="name"]', uniqueName);
    await page.fill('input[name="sector"]', 'Sector 1');
    await page.selectOption('select[name="category"]', 'new-launch');
    await page.fill('input[name="price_display"]', '1 Cr+');
    await page.fill('textarea[name="description"]', 'Automated QA Fortress test property — safe to delete.');
    await page.click('button[value="close"]');
    await page.waitForURL(/properties\.php/);
    // The new property sorts last among non-featured listings (ORDER BY featured DESC, id ASC)
    // and could land past page 1 once >9 properties exist — search by name instead of
    // relying on it being visible on whatever page loads by default.
    await page.goto('/admin/properties.php?q=' + encodeURIComponent(uniqueName));
    await expect(page.locator('body')).toContainText(uniqueName);

    // Soft-delete. admin.js's .js-row-delete-form handler calls window.confirm()
    // synchronously inside the click, so the dialog listener must be registered
    // BEFORE clicking — Playwright auto-dismisses (cancels) any dialog raised by
    // an action if no listener was already attached, which silently no-ops the
    // delete and was masking real failures further down this test.
    const row = page.locator('.admin-photo-card', { hasText: uniqueName });
    page.once('dialog', d => d.accept());
    await row.locator('.js-row-delete-form button, button[type="submit"]').last().click();
    await page.waitForTimeout(2000);

    // Confirm it's in Bin — same pagination risk as properties.php above, search by name.
    // Assert on the card itself, not raw body text: bin.php's own "no items match"
    // empty-state message embeds the search term, so a body-text substring check
    // passes even when nothing was actually found.
    await page.goto('/admin/bin.php?q=' + encodeURIComponent(uniqueName));
    const binRow = page.locator('.admin-data-card, .admin-photo-card', { hasText: uniqueName }).first();
    await expect(binRow).toBeVisible();

    // Restore
    await binRow.locator('button[type="submit"]', { hasText: /restore/i }).click();
    await page.waitForTimeout(1500);
    await page.goto('/admin/properties.php?q=' + encodeURIComponent(uniqueName));
    await expect(page.locator('body')).toContainText(uniqueName);

    // Cleanup: soft-delete again so this doesn't linger in the live list
    const cleanupRow = page.locator('.admin-photo-card', { hasText: uniqueName });
    page.once('dialog', d => d.accept());
    await cleanupRow.locator('button[type="submit"]').last().click();
    await page.waitForTimeout(1000);
  });

  test('QF-VAL-101: property form blocks submission of empty required fields (HTML5 validation)', async ({ page }) => {
    await login(page);
    await page.goto('/admin/property-form.php');
    await page.click('button[value="close"]');
    // Required fields have the `required` attribute — the browser blocks submission
    // client-side before it ever reaches the server, so we confirm that instead of a
    // server-rendered error (which never fires in this path).
    const stillOnForm = page.url().includes('property-form.php');
    expect(stillOnForm).toBe(true);
    const nameValidity = await page.locator('#pf-name').evaluate(el => el.validity.valid);
    expect(nameValidity).toBe(false);
  });
});

test.describe('Corridor duplicate-name handling', () => {
  test('QF-FUNC-102: creating a corridor with a name that already exists shows a friendly error, not a crash', async ({ page }) => {
    await login(page);
    await page.goto('/admin/corridors.php');
    const existingName = await page.locator('.admin-photo-card h3, .admin-photo-card-overlay h3').first().innerText();
    await page.goto('/admin/corridor-form.php');
    await page.fill('input[name="name"]', existingName.trim());
    await page.fill('input[name="avg_price_band"]', '10,000/sq ft');
    await page.fill('textarea[name="description"]', 'Duplicate name test.');
    await page.click('button[value="close"]');
    await expect(page.locator('body')).not.toContainText(/fatal error|integrity constraint/i);
    await expect(page.locator('.form-note.error, [class*="error"]')).toBeVisible();
  });
});

test.describe('Leads inline update (AJAX)', () => {
  test('QF-FUNC-103: changing a lead status saves via AJAX without a full page reload', async ({ page }) => {
    await login(page);
    await page.goto('/admin/enquiries.php');
    const statusSelect = page.locator('.js-lead-update-field[name="status"]').first();
    const count = await statusSelect.count();
    test.skip(count === 0, 'No leads exist to test against');
    const before = page.url();
    await statusSelect.selectOption('contacted');
    await page.waitForTimeout(1000);
    expect(page.url()).toBe(before); // no navigation happened — confirms AJAX, not full reload
    await expect(page.locator('.toast, .toast-anchored')).toBeVisible({ timeout: 3000 });
  });
});

test.describe('Owner-protection rule', () => {
  test('QF-RBAC-201: an owner account cannot be deleted, even by another owner', async ({ page }) => {
    await login(page); // qa_owner_test
    await page.goto('/admin/admins.php');
    const ownerRow = page.locator('.admin-data-card, tr', { hasText: 'admin' }).first();
    const deleteBtn = ownerRow.locator('button', { hasText: /delete/i });
    await expect(deleteBtn).toHaveCount(0); // delete action should be absent for owner rows
  });
});
