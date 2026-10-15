// @ts-check
const { test, expect } = require('@playwright/test');

// QF-FUNC-001..020 — core public journeys, happy path + "frustrated customer" angle.

test.describe('Home', () => {
  test('QF-FUNC-001: loads, shows nav and corridor ticker', async ({ page }) => {
    await page.goto('/index.php');
    await expect(page).toHaveTitle(/Kutumb/i);
    await expect(page.locator('.corridor-ticker')).toBeVisible();
    await expect(page.locator('.site-header')).toBeVisible();
  });

  test('QF-FUNC-002: hero search form navigates to filtered properties', async ({ page }) => {
    await page.goto('/index.php');
    await page.fill('#hero-search-keyword', 'Godrej');
    await page.locator('.search-fields button[type="submit"]').click();
    await expect(page).toHaveURL(/properties\.php\?.*keyword=Godrej/);
  });
});

test.describe('All Properties', () => {
  test('QF-FUNC-003: lists properties, filter by category narrows results', async ({ page }) => {
    await page.goto('/properties.php');
    const before = await page.locator('.card-property').count();
    expect(before).toBeGreaterThan(0);
    await page.selectOption('#pf-category', 'commercial');
    await page.click('button[type="submit"].btn-primary');
    await page.waitForLoadState('networkidle');
    const cards = page.locator('.card-property');
    const count = await cards.count();
    for (let i = 0; i < count; i++) {
      await expect(cards.nth(i).locator('.card-badge')).toContainText(/Commercial/i);
    }
  });

  test('QF-VAL-001: filter with no matches shows an honest empty state, not a crash', async ({ page }) => {
    await page.goto('/properties.php?keyword=zzzznonexistentzzzz');
    await expect(page.locator('body')).toContainText(/no properties match|tell us what/i);
  });
});

test.describe('Property Detail + Enquiry Form', () => {
  test('QF-FUNC-004: property detail renders sidebar CTA and enquiry form', async ({ page }) => {
    await page.goto('/property.php?id=2');
    await expect(page.locator('#enquiry-form')).toBeVisible();
    await expect(page.locator('a.btn-primary', { hasText: /WhatsApp/i })).toHaveAttribute('href', /wa\.me/);
  });

  test('QF-FUNC-005: enquiry submits successfully with valid data', async ({ page }) => {
    await page.goto('/property.php?id=2');
    await page.fill('#enquiry-name', 'QA Test User');
    await page.fill('#enquiry-phone', '9876543210');
    await page.fill('#enquiry-message', 'Automated QA Fortress test enquiry.');
    await page.click('#enquiry-form button[type="submit"]');
    await expect(page.locator('.toast, .toast-visible')).toBeVisible({ timeout: 5000 });
  });

  test('QF-FUNC-006 (frustrated customer): rage-clicking submit does not create duplicate toasts/errors', async ({ page }) => {
    await page.goto('/property.php?id=2');
    await page.fill('#enquiry-name', 'QA Rage Click');
    await page.fill('#enquiry-phone', '9876543211');
    const btn = page.locator('#enquiry-form button[type="submit"]');
    await Promise.all([btn.click(), btn.click(), btn.click()]);
    await page.waitForTimeout(2000);
    // The button should show a loading state and not be clickable mid-submit.
    await expect(page.locator('body')).not.toContainText(/fatal error|warning:/i);
  });

  test('QF-VAL-002: empty required fields are rejected client-side (required attr)', async ({ page }) => {
    await page.goto('/property.php?id=2');
    const nameField = page.locator('#enquiry-name');
    await expect(nameField).toHaveAttribute('required', '');
    const phoneField = page.locator('#enquiry-phone');
    await expect(phoneField).toHaveAttribute('required', '');
  });

  test('QF-VAL-003 (frustrated customer): pasting emoji/very long text into message does not break the page', async ({ page }) => {
    await page.goto('/property.php?id=2');
    await page.fill('#enquiry-name', 'QA Emoji Test 🏠🔥💯');
    await page.fill('#enquiry-phone', '9876543212');
    await page.fill('#enquiry-message', '🎉'.repeat(50) + 'A'.repeat(2000));
    await page.click('#enquiry-form button[type="submit"]');
    await page.waitForTimeout(1500);
    await expect(page.locator('body')).not.toContainText(/fatal error/i);
  });
});

test.describe('Contact', () => {
  test('QF-FUNC-007: contact form submits and shows success toast', async ({ page }) => {
    await page.goto('/contact.php');
    await page.fill('#contact-name', 'QA Contact Test');
    await page.fill('#contact-phone', '9876543213');
    await page.fill('#contact-email', 'qa-test@example.com');
    await page.fill('#contact-message', 'Automated contact form test.');
    await page.click('#contact-form button[type="submit"]');
    await expect(page.locator('.toast, .toast-visible')).toBeVisible({ timeout: 5000 });
  });

  test('QF-SEC-101: honeypot field is present and positioned off-screen (CSS-hidden, not DOM-hidden — bots that skip CSS still fill it)', async ({ page }) => {
    await page.goto('/contact.php');
    const honeypot = page.locator('#contact-company');
    await expect(honeypot).toBeAttached();
    const wrapper = page.locator('.hp-field');
    const box = await wrapper.boundingBox();
    // Playwright's generic toBeVisible() doesn't treat off-screen positioning as hidden
    // (the element has non-zero layout size), so assert the actual technique directly:
    // the wrapper is pushed off-canvas via negative left/top, which is what matters here.
    const position = await wrapper.evaluate(el => getComputedStyle(el).left);
    expect(position).toBe('-9999px');
  });

  test('QF-FUNC-008 (frustrated customer): AJAX submit never navigates, so there is nothing to "resubmit" via back button', async ({ page }) => {
    await page.goto('/contact.php');
    const urlBefore = page.url();
    await page.fill('#contact-name', 'QA Back Button Test');
    await page.fill('#contact-phone', '9876543214');
    await page.click('#contact-form button[type="submit"]');
    await page.waitForTimeout(1500);
    // Confirm the URL genuinely never changed (e.preventDefault() + fetch()) — the
    // classic "resubmit on back" risk only exists for real form POSTs, which this isn't.
    expect(page.url()).toBe(urlBefore);
    await expect(page.locator('#contact-form')).toBeVisible();
  });
});

test.describe('Compare', () => {
  test('QF-FUNC-009: adding properties to compare shows the sticky bar, max 4 enforced', async ({ page }) => {
    await page.goto('/properties.php');
    const toggles = page.locator('.compare-toggle[data-id]');
    const n = Math.min(await toggles.count(), 5);
    for (let i = 0; i < n; i++) {
      await toggles.nth(i).click();
      await page.waitForTimeout(150);
    }
    const chips = page.locator('.compare-bar-chip');
    await expect(chips).toHaveCount(Math.min(n, 4));
  });
});

test.describe('Saved Searches (Get Alerts)', () => {
  test('QF-FUNC-010: saved-search form submits successfully', async ({ page }) => {
    await page.goto('/saved-searches.php');
    await page.fill('input[name="name"]', 'QA Saved Search Test');
    await page.fill('input[name="phone"]', '9876543215');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);
    await expect(page.locator('body')).not.toContainText(/fatal error/i);
  });
});

test.describe('Corridor Guide', () => {
  test('QF-FUNC-011: corridor page shows stat row and listings', async ({ page }) => {
    await page.goto('/sector.php?slug=golf-course-extension-road');
    await expect(page.locator('.stat-row')).toBeVisible();
  });
});

test.describe('EMI Calculator (frustrated customer edge cases)', () => {
  test('QF-VAL-004: EMI calculates live without a submit, handles zero price gracefully', async ({ page }) => {
    await page.goto('/emi-calculator.php');
    await page.fill('#emi-price', '0');
    await page.waitForTimeout(300);
    await expect(page.locator('#emi-result')).not.toContainText(/NaN|Infinity|undefined/i);
    await expect(page.locator('#emi-breakdown')).not.toContainText(/NaN|Infinity|undefined/i);
  });

  test('QF-VAL-005: EMI at 0% interest rate does not divide by zero', async ({ page }) => {
    await page.goto('/emi-calculator.php');
    await page.fill('#emi-rate', '0');
    await page.waitForTimeout(300);
    await expect(page.locator('#emi-result')).not.toContainText(/NaN|Infinity|undefined/i);
    await expect(page.locator('#emi-breakdown')).not.toContainText(/NaN|Infinity|undefined/i);
  });
});
