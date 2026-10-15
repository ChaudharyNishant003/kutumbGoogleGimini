// @ts-check
const { test, expect } = require('@playwright/test');

// Isolated on purpose: this test intentionally exhausts the admin-login
// rate limit (rate_limit_check('admin-login', 15, 10) in admin/login.php)
// for the runner's IP. Running it alongside admin-auth-rbac.spec.js or
// admin-crud.spec.js in the same execution would burn the quota those
// files' own logins depend on and cause unrelated failures. Run this file
// on its own, or wait ~10 minutes after it runs before running other admin
// E2E specs against the same local stack.
test('QF-SEC-001 (regression): repeated login POSTs from one session get rate-limited', async ({ page, request }) => {
  await page.goto('/admin/login.php');
  const csrfToken = await page.locator('input[name="csrf_token"]').getAttribute('value');
  const cookies = (await page.context().cookies()).map(c => `${c.name}=${c.value}`).join('; ');

  let lastBody = '';
  for (let i = 0; i < 17; i++) {
    const res = await request.post('/admin/login.php', {
      headers: { cookie: cookies, 'content-type': 'application/x-www-form-urlencoded' },
      data: `csrf_token=${csrfToken}&username=qa_rate_limit_probe&password=wrong`,
    });
    lastBody = await res.text();
  }
  expect(lastBody).toMatch(/too many login attempts/i);
});
