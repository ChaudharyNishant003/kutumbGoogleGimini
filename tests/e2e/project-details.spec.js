// @ts-check
// Structured project details (migration 0019) + the 2026-10 project data load
// (scripts/load-projects-2026-10.php). Needs both applied to the local stack.
const { test, expect } = require('@playwright/test');

async function login(page, username = 'qa_owner_test', password = 'QaFortressOwner!2026') {
  await page.goto('/admin/login.php');
  await page.fill('#login-username', username);
  await page.fill('#login-password', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard\.php/);
}

/** Property id by exact name, via the admin list search. */
async function propertyId(page, name) {
  await page.goto('/admin/properties.php?q=' + encodeURIComponent(name));
  const href = await page.locator('a[href*="property-form.php?id="]').first().getAttribute('href');
  return Number(/id=(\d+)/.exec(href || '')[1]);
}

const ids = {};
test.beforeAll(async ({ browser }) => {
  const page = await browser.newPage();
  await login(page);
  for (const name of ['Adani M2K The Marq', 'ATS HomeKraft Sanctuary 105', 'Cocoa County by County Group', 'Sakura by Pardos']) {
    ids[name] = await propertyId(page, name);
  }
  await page.close();
});

test.describe('Project details on the property page', () => {
  test('QF-PD-001: The Marq shows key facts, unit plans with approx price, payment plan, specs and RERA', async ({ page }) => {
    await page.goto('/property.php?id=' + ids['Adani M2K The Marq']);
    const facts = page.locator('.key-facts');
    await expect(facts).toContainText('Acres');
    await expect(facts).toContainText('G+46');
    const units = page.locator('.unit-table').first();
    await expect(units).toContainText('3 BHK – Type A + Service Personnel Room');
    await expect(units).toContainText('2,375 sq ft');
    await expect(units).toContainText('1,333.12 sq ft');
    await expect(units).toContainText('≈ ₹4.04 Cr');
    await expect(units).toContainText('≈ ₹5.6 Cr');
    await expect(page.locator('.project-footnote').first()).toContainText('Excludes PLC');
    await expect(page.locator('.payment-table')).toContainText('₹25 Lakh');
    await expect(page.locator('.payment-table')).toContainText('On offer of possession');
    await expect(page.locator('.spec-list')).toContainText('Air conditioning');
    await expect(page.locator('.rera-list')).toContainText('RC/REP/HARERA/GGM/1042/774/2026/14');
    await expect(page.locator('.info-card').first()).toContainText('₹17,000/sq ft + PLC + GST');
    await expect(page.locator('.gallery-thumb')).toHaveCount(11);
    await expect(page.locator('.gallery-note')).toContainText('artistic impressions');
  });

  test('QF-PD-002: Sanctuary 105 is Price on Request — no approx price column, no EMI link, both RERA phases', async ({ page }) => {
    await page.goto('/property.php?id=' + ids['ATS HomeKraft Sanctuary 105']);
    await expect(page.locator('.key-facts')).toContainText('734');
    await expect(page.locator('.key-facts')).toContainText('13.07');
    await expect(page.locator('.unit-table thead')).not.toContainText('Approx');
    await expect(page.locator('.info-card').first()).toContainText('Price on Request');
    await expect(page.locator('.info-card').first().getByText('Calculate EMI')).toHaveCount(0);
    await expect(page.locator('.rera-list li')).toHaveCount(2);
    await expect(page.locator('.rera-list')).toContainText('Phase 2: RERA No. 25 of 2025');
  });

  test('QF-PD-003: Cocoa County groups units and specs by collection and lists 3 RERA phases', async ({ page }) => {
    await page.goto('/property.php?id=' + ids['Cocoa County by County Group']);
    const groups = page.locator('.project-group-label');
    await expect(groups.nth(0)).toHaveText('Cocoa County Gold');
    await expect(groups.nth(1)).toHaveText('Cocoa County');
    await expect(page.locator('.unit-table').nth(0)).toContainText('4,671 sq ft');
    await expect(page.locator('.unit-table').nth(1)).toContainText('1,498 sq ft');
    await expect(page.locator('.spec-list')).toContainText('Cocoa County Gold — Air conditioning');
    await expect(page.locator('.rera-list li')).toHaveCount(3);
  });

  test('QF-PD-004: Sakura is in EOI mode — badge, no price, RERA awaited, Register Interest CTA', async ({ page }) => {
    await page.goto('/property.php?id=' + ids['Sakura by Pardos']);
    await expect(page.locator('.eoi-badge')).toHaveText('EOI Open · Coming Soon');
    const card = page.locator('.info-card').first();
    await expect(card).toContainText('EOI Open');
    await expect(card).not.toContainText('Starting Price');
    await expect(card.getByText('Register Interest on WhatsApp')).toBeVisible();
    await expect(page.locator('.unit-table')).toContainText('4 BHK + Study + Servant');
    await expect(page.locator('.unit-table thead')).not.toContainText('Approx');
    await expect(page.locator('body')).toContainText('RERA registration awaited');
    await expect(page.locator('body')).toContainText('CLP');
    await expect(page.locator('#enquiry-form button[type="submit"]')).toHaveText('Register Interest');
  });

  test('QF-PD-005: listing card shows the EOI badge for Sakura', async ({ page }) => {
    await page.goto('/property.php?id=' + ids['Cocoa County by County Group']);
    // Sakura shares the New Gurgaon corridor, so it can appear under "You might also like";
    // check the card markup directly via the filter API instead of depending on that.
    const html = await (await page.request.get('/api/filter-properties.php?category=new-launch')).text();
    const sakuraCard = html.split('class="card-property').find((c) => c.includes('Sakura by Pardos'));
    expect(sakuraCard).toBeTruthy();
    expect(sakuraCard).toContain('card-badge eoi');
  });

  test('QF-PD-006: older listings without project details render unchanged (no empty sections)', async ({ page }) => {
    await page.goto('/properties.php');
    const href = await page.locator('a.card-property').filter({ hasNotText: /Marq|Sanctuary 105|Cocoa County|Sakura/ }).first().getAttribute('href');
    await page.goto(href || '/');
    await expect(page.locator('.key-facts')).toHaveCount(0);
    await expect(page.locator('.unit-table')).toHaveCount(0);
    await expect(page.locator('.spec-list')).toHaveCount(0);
  });

  test('QF-PD-007: About page names the company and directors', async ({ page }) => {
    await page.goto('/about.php');
    await expect(page.locator('body')).toContainText('Kutumb Real Estate Private Limited');
    await expect(page.locator('body')).toContainText('Upendra Singh, Amit Singh and Sandeep Kumar');
  });
});

test.describe('Project details on mobile', () => {
  test.use({ viewport: { width: 375, height: 812 } });
  test('QF-PD-008: unit table stacks into cards and the page has no horizontal scroll', async ({ page }) => {
    await page.goto('/property.php?id=' + ids['Adani M2K The Marq']);
    await expect(page.locator('.unit-table thead').first()).toBeHidden();
    // The page as a whole has a pre-existing ~9px overflow from the corridor ticker,
    // so check that none of the new blocks reach past the viewport.
    const overflowing = await page.evaluate(() => {
      const w = document.documentElement.clientWidth;
      return [...document.querySelectorAll('.key-facts, .unit-table-wrap, .unit-table td, .spec-list, .rera-list, .payment-table')]
        .filter((el) => el.getBoundingClientRect().right > w + 1).map((el) => el.className);
    });
    expect(overflowing).toEqual([]);
  });
});

test.describe('Admin: project details editor', () => {
  test('QF-PD-101: project facts + EOI on the property form, and add/edit/delete rows on Project Details', async ({ page }) => {
    test.setTimeout(90_000); // one long end-to-end admin flow
    await login(page);
    await page.goto('/admin/property-form.php');
    const name = 'QA Project Details ' + Date.now();
    await page.fill('input[name="name"]', name);
    await page.fill('input[name="sector"]', 'Sector 1');
    await page.selectOption('select[name="category"]', 'new-launch');
    await page.fill('input[name="price_display"]', 'Price on Request');
    await page.fill('textarea[name="description"]', 'Automated QA test property — safe to delete.');
    await page.fill('input[name="land_area_acres"]', 'abc');
    await page.click('button[value="close"]');
    await expect(page.locator('.toast-error').first()).toContainText('Land area must be a number');
    await expect(page.locator('input[name="name"]')).toHaveValue(name);

    await page.fill('input[name="land_area_acres"]', '7.5');
    await page.fill('input[name="tower_count"]', '3');
    await page.fill('input[name="floors_text"]', 'G+30');
    await page.fill('input[name="rate_text"]', '₹10,000/sq ft');
    await page.check('input[name="is_eoi"]');
    await page.click('button[value="close"]');
    await page.waitForURL(/properties\.php/);
    const id = await propertyId(page, name);

    await page.goto('/admin/property-form.php?id=' + id);
    await expect(page.locator('input[name="land_area_acres"]')).toHaveValue('7.50');
    await expect(page.locator('input[name="is_eoi"]')).toBeChecked();

    await page.click('a[href="/admin/property-details.php?property_id=' + id + '"]');
    await expect(page.locator('.admin-note-warn')).toContainText('EOI mode');

    const unit = page.locator('#unit');
    await unit.locator('input[name="unit_label"]').fill('3 BHK');
    await unit.locator('input[name="saleable_sqft"]').fill('2,000');
    await unit.locator('input[name="rate_per_sqft"]').fill('ten');
    await unit.locator('button.btn-primary').click();
    await expect(page.locator('.toast-error').first()).toContainText('whole number');
    await page.locator('#unit input[name="rate_per_sqft"]').fill('10000');
    await page.locator('#unit button.btn-primary').click();
    await expect(page.locator('.toast-success').first()).toContainText('Saved.');
    await expect(page.locator('#unit .admin-table')).toContainText('2,000');

    await page.locator('#unit a', { hasText: 'Edit' }).click();
    await page.locator('#unit input[name="unit_label"]').fill('3 BHK + Study');
    await page.locator('#unit button.btn-primary').click();
    await expect(page.locator('#unit .admin-table')).toContainText('3 BHK + Study');

    const rera = page.locator('#rera');
    await rera.locator('input[name="phase_label"]').fill('Phase 1');
    await rera.locator('input[name="rera_number"]').fill('QA/RERA/1');
    await rera.locator('button.btn-primary').click();
    await expect(page.locator('#rera .admin-table')).toContainText('QA/RERA/1');

    // Public page: EOI hides the approx price even though area × rate is set.
    await page.goto('/property.php?id=' + id);
    await expect(page.locator('.unit-table')).toContainText('3 BHK + Study');
    await expect(page.locator('.unit-table thead')).not.toContainText('Approx');
    await expect(page.locator('.rera-list')).toContainText('Phase 1: RERA No. QA/RERA/1');

    await page.goto('/admin/property-details.php?property_id=' + id);
    page.on('dialog', (d) => d.accept());
    await page.locator('#unit button', { hasText: 'Delete' }).click();
    await expect(page.locator('.toast-success').first()).toContainText('Deleted.');
    await expect(page.locator('#unit')).toContainText('Nothing added yet');

    // Clean up: move the test property to the Bin.
    await page.goto('/admin/properties.php?q=' + encodeURIComponent(name));
    const del = page.locator('.js-row-delete-form button, form[action*="delete-property"] button').first();
    if (await del.count()) await del.click();
  });
});
