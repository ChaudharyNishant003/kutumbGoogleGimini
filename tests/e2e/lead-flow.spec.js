// @ts-check
// Website lead → CRM flow on the clean (no voice agent) build: leads from every public
// form land in Leads, go round-robin to active agents with a "first call" follow-up,
// agents see only their own work, admins reassign. Local Docker stack only — it reads
// and writes the DB through `docker exec` (container from LEAD_FLOW_DB, default kutumb-main-db-1).
const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');

const DB = process.env.LEAD_FLOW_DB || 'kutumb-main-db-1';
const APP = process.env.LEAD_FLOW_APP || 'kutumb-main-app-1';
const AGENT_PASS = 'ZzAgentTest!2026'; // local fixture accounts only, created and removed by this file

function sql(query) {
  const out = execFileSync('docker', ['exec', '-i', DB, 'mysql', '--default-character-set=utf8mb4', '-umain', '-pmain-pass', 'railway', '-N', '-B'], { input: "SET time_zone = '+05:30';\n" + query, encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore'] });
  return out.trim() === '' ? [] : out.trim().split('\n').map(line => line.split('\t'));
}
function clearRateLimits() {
  sql("DELETE FROM form_submission_log WHERE endpoint IN ('admin-login', 'enquiry-submit', 'saved-search-submit');");
}
function cleanup() {
  sql(`DELETE FROM follow_ups WHERE enquiry_id IN (SELECT id FROM enquiries WHERE name LIKE 'ZZ Lead%');
       DELETE FROM enquiries WHERE name LIKE 'ZZ Lead%';
       DELETE FROM saved_searches WHERE name LIKE 'ZZ Lead%';
       DELETE FROM admin_users WHERE username IN ('zz_agent_a', 'zz_agent_b');`);
}

async function login(page, username, password) {
  await page.goto('/admin/login.php');
  await page.fill('#login-username', username);
  await page.fill('#login-password', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard\.php/);
}

test.describe.configure({ mode: 'serial' });

test.describe('Lead flow: website → Leads → agent', () => {
  /** @type {string[]} */
  let agentOrder = [];
  /** @type {Record<string, string>} */
  const ids = {};

  test.beforeAll(() => {
    cleanup();
    clearRateLimits();
    const hash = execFileSync('docker', ['exec', APP, 'php', '-r', `echo password_hash('${AGENT_PASS}', PASSWORD_DEFAULT);`], { encoding: 'utf8' }).trim();
    sql(`INSERT INTO admin_users (username, password_hash, role, full_name, active) VALUES
           ('zz_agent_a', '${hash}', 'agent', 'ZZ Agent A', 1), ('zz_agent_b', '${hash}', 'agent', 'ZZ Agent B', 1);
         UPDATE app_settings SET auto_assign_leads = 1, last_assigned_admin_id = NULL WHERE id = 1;`);
    agentOrder = sql("SELECT id FROM admin_users WHERE role = 'agent' AND active = 1 ORDER BY id").map(r => r[0]);
    for (const [id, u] of sql("SELECT id, username FROM admin_users WHERE username IN ('zz_agent_a', 'zz_agent_b')")) ids[u] = id;
  });
  test.afterAll(() => { cleanup(); clearRateLimits(); sql('UPDATE app_settings SET auto_assign_leads = 1 WHERE id = 1;'); });

  test('QF-LEAD-001: six website leads from five forms are recorded and shared out in turn, each with a first-call follow-up', async ({ page }) => {
    const toast = page.locator('.toast, .toast-visible');

    await page.goto('/contact.php');
    await page.fill('#contact-name', 'ZZ Lead 1 Contact');
    await page.fill('#contact-phone', '9000000001');
    await page.fill('#contact-email', 'zz-lead-1@example.com');
    await page.fill('#contact-message', 'Looking for a 3 BHK on Golf Course Road.');
    await page.click('#contact-form button[type="submit"]');
    await expect(toast).toBeVisible({ timeout: 8000 });

    await page.goto('/property.php?id=2');
    await page.fill('#enquiry-name', 'ZZ Lead 2 Property');
    await page.fill('#enquiry-phone', '9000000002');
    await page.fill('#enquiry-message', 'Please share the price list.');
    await page.click('#enquiry-form button[type="submit"]');
    await expect(toast).toBeVisible({ timeout: 8000 });

    await page.goto('/saved-searches.php');
    await page.fill('#saved-search-form input[name="name"]', 'ZZ Lead 3 Saved Search');
    await page.fill('#saved-search-form input[name="phone"]', '9000000003');
    await page.selectOption('#ss-corridor', { index: 1 });
    await page.click('#saved-search-form button[type="submit"]');
    await expect(toast).toBeVisible({ timeout: 8000 });

    await page.goto('/why-an-advisor.php');
    await page.fill('#advisor-consultation-form input[name="name"]', 'ZZ Lead 4 Consultation');
    await page.selectOption('#ac-budget', { index: 1 });
    await page.selectOption('#ac-timeline', { index: 1 });
    await page.fill('#advisor-consultation-form input[name="phone"]', '9000000004');
    await page.click('#advisor-consultation-form button[type="submit"]');
    await expect(toast).toBeVisible({ timeout: 8000 });

    for (const n of [5, 6]) {
      await page.goto('/contact.php');
      await page.fill('#contact-name', `ZZ Lead ${n} Contact`);
      await page.fill('#contact-phone', `900000000${n}`);
      await page.click('#contact-form button[type="submit"]');
      await expect(toast).toBeVisible({ timeout: 8000 });
    }

    const leads = sql("SELECT id, name, IFNULL(assigned_admin_id, ''), IFNULL(source, '') FROM enquiries WHERE name LIKE 'ZZ Lead%' ORDER BY id");
    expect(leads.map(l => l[1].replace(/ (Contact|Property|Saved Search|Consultation)$/, ''))).toEqual(
      ['ZZ Lead 1', 'ZZ Lead 2', 'ZZ Lead 3', 'ZZ Lead 4', 'ZZ Lead 5', 'ZZ Lead 6']);
    // Round-robin: lead n goes to agent (n-1) mod count, in id order.
    leads.forEach((l, i) => expect(l[2], `${l[1]} owner`).toBe(agentOrder[i % agentOrder.length]));
    // Every lead has exactly one open follow-up, owned by the lead's agent, due about 2 hours out.
    for (const [id, name, owner] of leads) {
      const fu = sql(`SELECT assigned_admin_id, note, TIMESTAMPDIFF(MINUTE, NOW(), due_at) FROM follow_ups WHERE enquiry_id = ${id} AND completed = 0`);
      expect(fu.length, `${name} follow-ups`).toBe(1);
      expect(fu[0][0]).toBe(owner);
      expect(fu[0][1]).toMatch(/first call/i);
      expect(Number(fu[0][2])).toBeGreaterThan(110);
      expect(Number(fu[0][2])).toBeLessThanOrEqual(120);
    }
    // The saved search is kept, and belongs to the same agent as its lead.
    const ss = sql("SELECT s.assigned_admin_id, e.assigned_admin_id FROM saved_searches s JOIN enquiries e ON e.name = s.name WHERE s.name = 'ZZ Lead 3 Saved Search'");
    expect(ss.length).toBe(1);
    expect(ss[0][0]).toBe(ss[0][1]);
  });

  test('QF-LEAD-002: an agent sees only their own leads, follow-ups and menu', async ({ page }) => {
    clearRateLimits();
    await login(page, 'zz_agent_a', AGENT_PASS);
    await expect(page.locator('h1')).toHaveText('My Day');
    const nav = page.locator('.admin-nav');
    await expect(nav).toContainText('My Leads');
    for (const hidden of ['Properties', 'Blog', 'Corridors', 'Team', 'Testimonials', 'Bin', 'Settings', 'Manage Admins', 'Nearby Places', 'AI Question Log', 'Call Queue', 'AI Calling Settings']) {
      await expect(nav).not.toContainText(hidden);
    }
    const mine = sql(`SELECT name FROM enquiries WHERE name LIKE 'ZZ Lead%' AND assigned_admin_id = ${ids.zz_agent_a}`).map(r => r[0]);
    const others = sql(`SELECT id, name FROM enquiries WHERE name LIKE 'ZZ Lead%' AND assigned_admin_id <> ${ids.zz_agent_a}`);
    expect(mine.length).toBeGreaterThan(0);
    await page.goto('/admin/enquiries.php');
    await expect(page.locator('h1')).toHaveText('My Leads');
    for (const name of mine) await expect(page.locator('.admin-card')).toContainText(name);
    for (const [, name] of others) await expect(page.locator('.admin-card')).not.toContainText(name);
    // Someone else's lead and admin-only pages are closed to an agent.
    const r = await page.goto(`/admin/enquiry-view.php?id=${others[0][0]}`);
    expect(r && r.status()).toBe(404);
    await page.goto('/admin/properties.php');
    await expect(page.locator('body')).toContainText(/permission/i);
    await page.goto('/admin/follow-ups.php');
    for (const name of mine) await expect(page.locator('.admin-card').first()).toContainText(name);
    for (const [, name] of others) await expect(page.locator('.admin-card').first()).not.toContainText(name);
  });

  test('QF-LEAD-003: an agent works their lead (status + follow-up done) but cannot reassign it', async ({ page }) => {
    clearRateLimits();
    const [leadId] = sql(`SELECT id FROM enquiries WHERE name LIKE 'ZZ Lead%' AND assigned_admin_id = ${ids.zz_agent_a} ORDER BY id LIMIT 1`)[0];
    await login(page, 'zz_agent_a', AGENT_PASS);
    await page.goto(`/admin/enquiry-view.php?id=${leadId}`);
    await expect(page.locator('#lv-owner')).toHaveCount(0);
    await page.selectOption('#lv-status', 'contacted');
    await expect(page.locator('.toast, .toast-visible')).toBeVisible({ timeout: 5000 });
    await page.click('text=Mark Done');
    await page.waitForURL(new RegExp(`enquiry-view\\.php\\?id=${leadId}`));
    expect(sql(`SELECT status FROM enquiries WHERE id = ${leadId}`)[0][0]).toBe('contacted');
    expect(sql(`SELECT COUNT(*) FROM follow_ups WHERE enquiry_id = ${leadId} AND completed = 1`)[0][0]).toBe('1');
    // A hand-made request to move the lead to another agent is ignored.
    const res = await page.evaluate(async ({ leadId, other }) => {
      const f = new FormData();
      f.append('id', leadId); f.append('status', 'contacted'); f.append('assigned_admin_id', other);
      f.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
      return (await fetch('/admin/enquiry-update.php', { method: 'POST', body: f })).json();
    }, { leadId, other: ids.zz_agent_b });
    expect(res.success).toBe(true);
    expect(sql(`SELECT assigned_admin_id FROM enquiries WHERE id = ${leadId}`)[0][0]).toBe(ids.zz_agent_a);
  });

  test('QF-LEAD-004: an admin reassigns a lead and its open follow-up moves to the new agent', async ({ page }) => {
    clearRateLimits();
    const [leadId] = sql(`SELECT e.id FROM enquiries e WHERE e.name LIKE 'ZZ Lead%' AND e.assigned_admin_id = ${ids.zz_agent_a}
                          AND EXISTS (SELECT 1 FROM follow_ups f WHERE f.enquiry_id = e.id AND f.completed = 0) ORDER BY e.id LIMIT 1`)[0];
    await login(page, 'qa_admin_test', 'QaFortressAdmin!2026');
    await page.goto(`/admin/enquiry-view.php?id=${leadId}`);
    await page.selectOption('#lv-owner', ids.zz_agent_b);
    await expect(page.locator('.toast, .toast-visible')).toBeVisible({ timeout: 5000 });
    expect(sql(`SELECT assigned_admin_id FROM enquiries WHERE id = ${leadId}`)[0][0]).toBe(ids.zz_agent_b);
    expect(sql(`SELECT DISTINCT assigned_admin_id FROM follow_ups WHERE enquiry_id = ${leadId} AND completed = 0`)).toEqual([[ids.zz_agent_b]]);
    // The dashboard's team table reflects the new owner.
    await page.goto('/admin/dashboard.php');
    await expect(page.locator('.admin-card', { hasText: 'Team Workload' })).toContainText('ZZ Agent B');
  });

  test('QF-LEAD-005: Settings keeps the OpenAI key, shows AI as Coming soon, and auto-assign can be switched off', async ({ page }) => {
    clearRateLimits();
    await login(page, 'qa_owner_test', 'QaFortressOwner!2026');
    await page.goto('/admin/settings.php');
    await expect(page.locator('body')).toContainText('OpenAI API Key');
    await expect(page.locator('.admin-card:has(h4:text-is("AI features"))')).toContainText('Coming soon.');
    await expect(page.locator('body')).not.toContainText(/questions per visitor|calling/i);
    await page.uncheck('input[name="auto_assign_leads"]');
    await Promise.all([page.waitForNavigation(), page.click('.admin-card:has(input[name="auto_assign_leads"]) button[type="submit"]')]);
    expect(sql('SELECT auto_assign_leads FROM app_settings WHERE id = 1')[0][0]).toBe('0');
    clearRateLimits();
    await page.goto('/contact.php');
    await page.fill('#contact-name', 'ZZ Lead 7 Unassigned');
    await page.fill('#contact-phone', '9000000007');
    await page.click('#contact-form button[type="submit"]');
    await expect(page.locator('.toast, .toast-visible')).toBeVisible({ timeout: 8000 });
    expect(sql("SELECT IFNULL(assigned_admin_id, 'none') FROM enquiries WHERE name = 'ZZ Lead 7 Unassigned'")[0][0]).toBe('none');
    await page.goto('/admin/settings.php');
    await page.check('input[name="auto_assign_leads"]');
    await Promise.all([page.waitForNavigation(), page.click('.admin-card:has(input[name="auto_assign_leads"]) button[type="submit"]')]);
    expect(sql('SELECT auto_assign_leads FROM app_settings WHERE id = 1')[0][0]).toBe('1');
  });

  test('QF-LEAD-006: voice-agent pages are gone and the website forms no longer mention AI calls', async ({ page }) => {
    clearRateLimits();
    await login(page, 'qa_owner_test', 'QaFortressOwner!2026');
    for (const p of ['/admin/ai/index.php', '/admin/ai/queue.php', '/admin/ai/settings.php', '/admin/api/ai-actions.php']) {
      const r = await page.goto(p);
      expect(r && r.status(), p).toBe(404);
    }
    for (const p of ['/contact.php', '/saved-searches.php', '/privacy-policy.php', '/terms.php']) {
      await page.goto(p);
      await expect(page.locator('body'), p).not.toContainText(/calling assistant|AI-assisted call/i);
    }
    await page.goto('/index.php');
    await expect(page.locator('[data-mic]')).toHaveCount(0);
  });
});
