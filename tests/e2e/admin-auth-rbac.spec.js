// @ts-check
const { test, expect } = require('@playwright/test');

const ADMIN_PAGES = [
  'dashboard.php', 'properties.php', 'enquiries.php', 'follow-ups.php', 'saved-searches.php',
  'blog-list.php', 'corridors.php', 'team.php', 'testimonials.php', 'bin.php',
];
const OWNER_ONLY_PAGES = ['admins.php', 'settings.php'];

async function login(page, username, password) {
  await page.goto('/admin/login.php');
  await page.fill('#login-username', username);
  await page.fill('#login-password', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard\.php/);
}

test.describe('Auth bypass sweep (Tier 1)', () => {
  for (const p of [...ADMIN_PAGES, ...OWNER_ONLY_PAGES]) {
    test(`QF-AUTH-${p}: unauthenticated visit redirects to login`, async ({ page }) => {
      await page.goto(`/admin/${p}`);
      await expect(page).toHaveURL(/login\.php/);
    });
  }
});

test.describe('Login flow', () => {
  test('QF-AUTH-001: valid owner login succeeds', async ({ page }) => {
    await login(page, 'qa_owner_test', 'QaFortressOwner!2026');
    await expect(page).toHaveURL(/dashboard\.php/);
  });

  test('QF-AUTH-002: invalid password shows generic error (no user enumeration)', async ({ page }) => {
    await page.goto('/admin/login.php');
    await page.fill('#login-username', 'qa_owner_test');
    await page.fill('#login-password', 'wrong-password-xyz');
    await page.click('button[type="submit"]');
    await expect(page.locator('.form-note.error')).toContainText(/incorrect username or password/i);
  });

  test('QF-AUTH-003: nonexistent username shows the SAME generic error', async ({ page }) => {
    await page.goto('/admin/login.php');
    await page.fill('#login-username', 'no_such_user_xyz');
    await page.fill('#login-password', 'anything');
    await page.click('button[type="submit"]');
    await expect(page.locator('.form-note.error')).toContainText(/incorrect username or password/i);
  });

  test('QF-AUTH-004: logout destroys session, protected page redirects again', async ({ page }) => {
    await login(page, 'qa_owner_test', 'QaFortressOwner!2026');
    await page.goto('/admin/logout.php');
    await page.goto('/admin/dashboard.php');
    await expect(page).toHaveURL(/login\.php/);
  });

  test('QF-SEC-002/003 (regression): session cookie is HttpOnly and SameSite=Lax', async ({ request }) => {
    const res = await request.get('/admin/login.php');
    const setCookie = res.headers()['set-cookie'] || '';
    expect(setCookie).toMatch(/HttpOnly/i);
    expect(setCookie).toMatch(/SameSite=Lax/i);
  });
});

test.describe('RBAC — vertical privilege escalation', () => {
  for (const p of OWNER_ONLY_PAGES) {
    test(`QF-RBAC-${p}: agent role cannot reach ${p} via direct URL`, async ({ page }) => {
      await login(page, 'qa_agent_test', 'QaFortressAgent!2026');
      await page.goto(`/admin/${p}`);
      const status = await page.evaluate(() => document.body.innerText);
      expect(page.url()).toContain(p); // didn't get redirected away
      await expect(page.locator('body')).toContainText(/don't have permission|403/i);
    });

    test(`QF-RBAC-${p}-admin: admin role (not owner) also cannot reach ${p}`, async ({ page }) => {
      await login(page, 'qa_admin_test', 'QaFortressAdmin!2026');
      await page.goto(`/admin/${p}`);
      await expect(page.locator('body')).toContainText(/don't have permission|403/i);
    });
  }

  test('QF-RBAC-sidebar: agent does not see the Manage Admins link in sidebar', async ({ page }) => {
    await login(page, 'qa_agent_test', 'QaFortressAgent!2026');
    await expect(page.locator('.admin-nav a[href*="admins.php"]')).toHaveCount(0);
  });

  test('QF-UX-001 (regression): Settings link is role-gated in the sidebar, same as Manage Admins', async ({ page }) => {
    // includes/admin-header.php previously showed Settings to every role unconditionally,
    // while settings.php itself correctly enforces require_role('owner') server-side — a
    // dead-end nav link for agent/admin users. Fixed by wrapping it in the same owner check.
    await login(page, 'qa_agent_test', 'QaFortressAgent!2026');
    await expect(page.locator('.admin-nav a[href*="settings.php"]')).toHaveCount(0);
  });
});
