// @ts-check
// Admin → website sync: what an admin creates, edits or deletes in Blog, Corridors, Team,
// Testimonials and Nearby Places shows up (or disappears) on the public site straight away.
// Local Docker stack only — cleans its own "ZZ Sync" rows through `docker exec`.
const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');

const DB = process.env.LEAD_FLOW_DB || 'kutumb-main-db-1';

function sql(query) {
  const out = execFileSync('docker', ['exec', '-i', DB, 'mysql', '--default-character-set=utf8mb4', '-umain', '-pmain-pass', 'railway', '-N', '-B'], { input: query, encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore'] });
  return out.trim() === '' ? [] : out.trim().split('\n').map(line => line.split('\t'));
}
function cleanup() {
  sql(`DELETE FROM blog_posts WHERE title LIKE 'ZZ Sync%';
       DELETE FROM corridor_faqs WHERE corridor_id IN (SELECT id FROM corridors WHERE name LIKE 'ZZ Sync%');
       DELETE FROM corridors WHERE name LIKE 'ZZ Sync%';
       DELETE FROM team_members WHERE name LIKE 'ZZ Sync%';
       DELETE FROM testimonials WHERE client_name LIKE 'ZZ Sync%';
       DELETE FROM places WHERE name LIKE 'ZZ Sync%';
       DELETE FROM form_submission_log WHERE endpoint = 'admin-login';`);
}

async function login(page) {
  await page.goto('/admin/login.php');
  await page.fill('#login-username', 'qa_admin_test');
  await page.fill('#login-password', 'QaFortressAdmin!2026');
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard\.php/);
}
/** Deletes an item from an admin list page with its own Delete button (accepting the confirm dialog). */
async function deleteFromList(page, listPath, id, name) {
  await page.goto(listPath);
  page.once('dialog', d => d.accept());
  await page.locator(`form[action*="delete-"]:has(input[name="id"][value="${id}"]) button`).click();
  await expect(page.locator('body')).not.toContainText(name, { timeout: 8000 });
}

test.describe.configure({ mode: 'serial' });

test.describe('Admin → website sync', () => {
  test.beforeAll(cleanup);
  test.afterAll(cleanup);
  test.beforeEach(async ({ page }) => { sql("DELETE FROM form_submission_log WHERE endpoint = 'admin-login';"); await login(page); });

  test('QF-SYNC-001: Blog & Insights — publish, edit and delete reach the Insights page', async ({ page }) => {
    await page.goto('/admin/blog-form.php');
    await page.fill('#bf-title', 'ZZ Sync Post');
    await page.fill('#bf-excerpt', 'Short summary for the sync test.');
    await page.click('#bf-body-editor .ql-editor');
    await page.keyboard.type('Body of the sync test post. Written by the automated sync check.');
    await expect(page.locator('#bf-body-editor .ql-editor')).toContainText('automated sync check');
    await page.selectOption('#bf-status', 'published');
    await page.click('button[value="close"]');
    await page.goto('/blog.php');
    await expect(page.locator('body')).toContainText('ZZ Sync Post');
    const [id, slug] = sql("SELECT id, slug FROM blog_posts WHERE title = 'ZZ Sync Post'")[0];
    await page.goto(`/blog-post.php?slug=${slug}`);
    await expect(page.locator('h1')).toContainText('ZZ Sync Post');

    await page.goto(`/admin/blog-form.php?id=${id}`);
    await page.fill('#bf-title', 'ZZ Sync Post Edited');
    await page.click('button[value="close"]');
    await page.goto('/blog.php');
    await expect(page.locator('body')).toContainText('ZZ Sync Post Edited');

    await deleteFromList(page, '/admin/blog-list.php', id, 'ZZ Sync Post Edited');
    await page.goto('/blog.php');
    await expect(page.locator('body')).not.toContainText('ZZ Sync Post');
  });

  test('QF-SYNC-002: Corridors — a new corridor gets its public guide page; edits show; delete removes it', async ({ page }) => {
    await page.goto('/admin/corridor-form.php');
    await page.fill('#cf-name', 'ZZ Sync Corridor');
    await page.fill('#cf-band', '₹9,000–12,000/sq ft');
    await page.fill('#cf-description', 'Sync test corridor description.');
    await page.click('button[value="close"]');
    const [id, slug] = sql("SELECT id, slug FROM corridors WHERE name = 'ZZ Sync Corridor'")[0];
    await page.goto(`/sector.php?slug=${slug}`);
    await expect(page.locator('h1')).toContainText('ZZ Sync Corridor');
    await expect(page.locator('body')).toContainText('₹9,000–12,000/sq ft');

    await page.goto(`/admin/corridor-form.php?id=${id}`);
    await page.fill('#cf-band', '₹10,000–13,000/sq ft');
    await page.click('button[value="close"]');
    await page.goto(`/sector.php?slug=${slug}`);
    await expect(page.locator('body')).toContainText('₹10,000–13,000/sq ft');

    await deleteFromList(page, '/admin/corridors.php', id, 'ZZ Sync Corridor');
    const r = await page.goto(`/sector.php?slug=${slug}`);
    expect(r && r.status()).toBe(404);
  });

  test('QF-SYNC-003: Team — an active member appears on the Team page; edits show; delete removes them', async ({ page }) => {
    await page.goto('/admin/team-form.php');
    await page.fill('#tf-name', 'ZZ Sync Advisor');
    await page.fill('#tf-role', 'Senior Advisor');
    await page.click('button[value="close"]');
    await page.goto('/team.php');
    await expect(page.locator('body')).toContainText('ZZ Sync Advisor');
    await expect(page.locator('body')).toContainText('Senior Advisor');

    const [id] = sql("SELECT id FROM team_members WHERE name = 'ZZ Sync Advisor'")[0];
    await page.goto(`/admin/team-form.php?id=${id}`);
    await page.fill('#tf-role', 'Corridor Specialist');
    await page.click('button[value="close"]');
    await page.goto('/team.php');
    await expect(page.locator('body')).toContainText('Corridor Specialist');

    await deleteFromList(page, '/admin/team.php', id, 'ZZ Sync Advisor');
    await page.goto('/team.php');
    await expect(page.locator('body')).not.toContainText('ZZ Sync Advisor');
  });

  test('QF-SYNC-004: Testimonials — a home-page testimonial shows on the home page; edits show; delete removes it', async ({ page }) => {
    await page.goto('/admin/testimonial-form.php');
    await page.fill('#tsf-name', 'ZZ Sync Client');
    await page.fill('#tsf-quote', 'Sync test quote — very helpful advisors.');
    await page.fill('#tsf-sort', '-1');
    await page.check('input[name="featured_home"]');
    await page.click('button[value="close"]');
    await page.goto('/index.php');
    await expect(page.locator('body')).toContainText('ZZ Sync Client');

    const [id] = sql("SELECT id FROM testimonials WHERE client_name = 'ZZ Sync Client'")[0];
    await page.goto(`/admin/testimonial-form.php?id=${id}`);
    await page.fill('#tsf-quote', 'Edited sync quote — they found us the right home.');
    await page.click('button[value="close"]');
    await page.goto('/index.php');
    await expect(page.locator('body')).toContainText('Edited sync quote');

    await deleteFromList(page, '/admin/testimonials.php', id, 'ZZ Sync Client');
    await page.goto('/index.php');
    await expect(page.locator('body')).not.toContainText('ZZ Sync Client');
  });

  test('QF-SYNC-005: Nearby Places — a place near a project appears in "What\'s around" on that project page; delete removes it', async ({ page }) => {
    const [propId, lat, lng] = sql('SELECT id, lat, lng FROM properties WHERE lat IS NOT NULL AND deleted_at IS NULL ORDER BY id LIMIT 1')[0];
    await page.goto('/admin/place-form.php');
    await page.fill('#pl-name', 'ZZ Sync School');
    await page.selectOption('#pl-category', 'school');
    await page.fill('#pl-lat', String(Number(lat) + 0.002));
    await page.fill('#pl-lng', lng);
    await page.fill('#pl-source', 'Sync test');
    await page.fill('#pl-verified', '2026-10-01');
    await page.click('button[type="submit"]:has-text("Add Place")');
    await page.goto(`/property.php?id=${propId}`);
    await expect(page.locator('body')).toContainText('ZZ Sync School');

    const [placeId] = sql("SELECT id FROM places WHERE name = 'ZZ Sync School'")[0];
    await deleteFromList(page, '/admin/places.php', placeId, 'ZZ Sync School');
    await page.goto(`/property.php?id=${propId}`);
    await expect(page.locator('body')).not.toContainText('ZZ Sync School');
  });
});
