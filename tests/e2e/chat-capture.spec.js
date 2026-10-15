// @ts-check
// Website chat → visitor log → lead. Runs against the local Docker stack with the real OpenAI key
// (a dozen small gpt-4o-mini calls per run) because the counting, the phone gate and the lead
// creation all happen on the server. Reads/writes the DB through `docker exec`; cleans up its own
// "ZZ Chat" rows. Places auto-fill makes a few real OpenStreetMap (Nominatim) requests.
const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');

const DB = process.env.LEAD_FLOW_DB || 'kutumb-main-db-1';
const APP = process.env.LEAD_FLOW_APP || 'kutumb-main-app-1';
const AGENT_PASS = 'ZzAgentTest!2026';

function sql(query) {
  const out = execFileSync('docker', ['exec', '-i', DB, 'mysql', '--default-character-set=utf8mb4', '-umain', '-pmain-pass', 'railway', '-N', '-B'],
    { input: "SET time_zone = '+05:30';\n" + query, encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore'] });
  return out.trim() === '' ? [] : out.trim().split('\n').map(line => line.split('\t'));
}
/** A new browser context on the local stack (contexts made by hand don't inherit the config's baseURL). */
function newContext(browser, opts = {}) {
  return browser.newContext({ baseURL: process.env.BASE_URL || 'http://localhost:8000', ...opts });
}
function one(query) { const r = sql(query); return r.length ? r[0][0] : null; }
function cleanup() {
  sql(`DELETE FROM ai_query_log WHERE visitor_id IN (SELECT id FROM ai_visitors WHERE name LIKE 'ZZ Chat%' OR question_count > 0 AND phone IS NULL AND first_seen_at > NOW() - INTERVAL 1 DAY);
       DELETE FROM ai_visitors WHERE name LIKE 'ZZ Chat%' OR (phone IS NULL AND first_seen_at > NOW() - INTERVAL 1 DAY);
       DELETE FROM follow_ups WHERE enquiry_id IN (SELECT id FROM enquiries WHERE name LIKE 'ZZ Chat%');
       DELETE FROM saved_searches WHERE name LIKE 'ZZ Chat%';
       DELETE FROM enquiries WHERE name LIKE 'ZZ Chat%';
       DELETE FROM places WHERE name LIKE 'ZZ Chat%';
       DELETE FROM admin_users WHERE username = 'zz_chat_admin';
       DELETE FROM form_submission_log; DELETE FROM ai_rate_limits;`);
}

async function openChat(page, path = '/about.php') {
  await page.goto(path, { waitUntil: 'networkidle' });
  await page.click('#chat-toggle', { force: true });
}
/** Sends a chat message and returns the bot's reply text (the first bubble added after it). */
async function ask(page, text) {
  const before = await page.locator('#chat-messages > .chat-bubble').count();
  await page.fill('#chat-input', text);
  await page.press('#chat-input', 'Enter');
  await page.waitForFunction((n) => {
    const all = document.querySelectorAll('#chat-messages > .chat-bubble');
    return all.length > n + 1 && !/^Typing/.test(all[n + 1].textContent || '');
  }, before, { timeout: 40000 });
  return (await page.locator('#chat-messages > .chat-bubble').nth(before + 1).textContent() || '').trim();
}
async function adminLogin(page) {
  sql("DELETE FROM form_submission_log WHERE endpoint = 'admin-login';");
  await page.goto('/admin/login.php');
  await page.fill('#login-username', 'zz_chat_admin');
  await page.fill('#login-password', AGENT_PASS);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard\.php/);
}

test.describe.configure({ mode: 'serial' });

test.describe('Website chat → visitor log → lead', () => {
  test.setTimeout(240000);
  let visitorId = '';
  let leadId = '';
  /** The first visitor's browser (cookie kv_vid), reused to come back "10 days later". */
  let visitorState;

  test.beforeAll(() => {
    cleanup();
    const hash = execFileSync('docker', ['exec', APP, 'php', '-r', `echo password_hash('${AGENT_PASS}', PASSWORD_DEFAULT);`], { encoding: 'utf8' }).trim();
    sql(`INSERT INTO admin_users (username, password_hash, role, full_name, active) VALUES ('zz_chat_admin', '${hash}', 'owner', 'ZZ Chat Admin', 1);
         UPDATE app_settings SET auto_assign_leads = 1 WHERE id = 1;`);
  });
  test.afterAll(cleanup);

  test('QF-CHAT-001: 3 answers → requirement buttons, 5 → phone required, number → lead with requirement; every turn logged', async ({ page }) => {
    await openChat(page);
    for (const q of ['Which projects are on Dwarka Expressway?', 'Which of them is ready to move?', 'Do you have 4 BHK homes?']) {
      expect(await ask(page, q)).not.toMatch(/name and phone/i);
    }
    // Requirement: four button questions, each answerable or skippable.
    const questions = ['budget', 'bedrooms', 'area', 'planning to buy'];
    const picks = ['₹2.5–4 Cr', '3 BHK', null, 'Within 3 months'];
    for (let i = 0; i < 4; i++) {
      // The open (not yet answered) requirement question.
      const box = page.locator('[data-lc="requirement"]:has(.lc-skip:enabled)').last();
      await expect(box.locator('.lc-text')).toContainText(questions[i]);
      if (picks[i]) await box.locator('.lc-option', { hasText: picks[i] }).click(); else await box.locator('.lc-skip').click();
      await expect(page.locator('[data-lc="requirement"] .lc-picked')).toHaveCount(i + 1);
    }
    await expect(page.locator('#chat-messages')).toContainText('that helps us find the right homes');

    expect(await ask(page, 'What is the price of Godrej Vrikshya?')).toMatch(/3\.75/);
    await expect(page.locator('[data-lc="contact"]:not(.lc-required)')).toHaveCount(1); // buying intent → optional form
    await ask(page, 'Is there a metro station nearby?');
    await expect(page.locator('[data-lc="contact"].lc-required')).toHaveCount(1);       // 5 answers → required

    expect(await ask(page, 'Any commercial projects?')).toMatch(/name and phone/i);     // 6th: no answer without a number
    visitorId = one("SELECT id FROM ai_visitors WHERE question_count = 5 AND phone IS NULL ORDER BY id DESC LIMIT 1") || '';
    expect(visitorId).not.toBe('');
    expect(sql(`SELECT endpoint, status FROM ai_query_log WHERE visitor_id = ${visitorId} ORDER BY id`).map(r => r.join(':')))
      .toEqual(['chatbot:answered', 'chatbot:answered', 'chatbot:answered', 'chatbot:answered', 'chatbot:answered', 'chatbot:gated']);

    // A wrong number is refused, a right one becomes a lead.
    const form = page.locator('[data-lc="contact"].lc-required').last();
    await form.locator('input[name="name"]').fill('ZZ Chat Buyer');
    await form.locator('input[name="phone"]').fill('12345');
    await form.locator('.lc-send').click();
    await expect(form.locator('.lc-msg')).toContainText(/10-digit/);
    await form.locator('input[name="phone"]').fill('9000000881');
    await form.locator('.lc-send').click();
    await expect(page.locator('#chat-messages')).toContainText('Thank you, ZZ Chat Buyer');

    const [lead] = sql("SELECT id, source, IFNULL(assigned_admin_id,''), message FROM enquiries WHERE name = 'ZZ Chat Buyer'");
    leadId = lead[0];
    expect(lead[1]).toBe('chatbot');
    expect(lead[2]).not.toBe('');                                   // auto-assigned to an agent
    expect(lead[3]).toContain('Budget: ₹2.5–4 Cr');
    expect(lead[3]).toContain('Size: 3 BHK');
    expect(one(`SELECT COUNT(*) FROM follow_ups WHERE enquiry_id = ${leadId} AND completed = 0`)).toBe('1');
    const ss = sql(`SELECT s.budget_min, s.budget_max, s.bhk_config, s.timeline, s.assigned_admin_id = e.assigned_admin_id
                    FROM ai_visitors v JOIN saved_searches s ON s.id = v.saved_search_id JOIN enquiries e ON e.id = v.enquiry_id WHERE v.id = ${visitorId}`)[0];
    expect(ss).toEqual(['25000000.00', '40000000.00', '3 BHK', 'now', '1']);

    expect(await ask(page, 'Any commercial projects?')).not.toMatch(/name and phone/i); // answers again
    await page.reload();
    await page.click('#chat-toggle', { force: true });
    await page.waitForTimeout(1200);
    await expect(page.locator('[data-lc="contact"]')).toHaveCount(0);                  // never asked again
    visitorState = await page.context().storageState();
  });

  test('QF-CHAT-002: a returning visitor stays one entry, with each visit grouped; admin sees it by visitor and on the lead', async ({ browser }) => {
    // Pretend the last activity was 10 days ago, then come back in the same browser.
    sql(`UPDATE ai_visitors SET last_seen_at = NOW() - INTERVAL 10 DAY WHERE id = ${visitorId};`);
    const visitorCtx = await newContext(browser, { storageState: visitorState });
    const page = await visitorCtx.newPage();
    await openChat(page, '/contact.php');
    await ask(page, 'Do you have anything on Golf Course Road?');
    expect(sql(`SELECT visit_count FROM ai_visitors WHERE id = ${visitorId}`)[0][0]).toBe('2');
    expect(one(`SELECT COUNT(DISTINCT visit_no) FROM ai_query_log WHERE visitor_id = ${visitorId}`)).toBe('2');
    expect(one(`SELECT COUNT(*) FROM ai_visitors WHERE name = 'ZZ Chat Buyer'`)).toBe('1');

    const adminCtx = await newContext(browser);
    const admin = await adminCtx.newPage();
    await adminLogin(admin);
    await admin.goto('/admin/ai-log.php');
    const row = admin.locator('tr', { hasText: 'ZZ Chat Buyer' });
    await expect(row).toHaveCount(1);
    await expect(row).toContainText('9000000881');
    await expect(row).toContainText('Budget: ₹2.5–4 Cr');
    await row.locator('a', { hasText: 'Open' }).click();
    await expect(admin.locator('.ai-visit-head')).toHaveCount(2);
    await expect(admin.locator('.ai-visit-head').first()).toContainText('Visit 2');
    await expect(admin.locator('.ai-turn').first()).toContainText('Golf Course Road');
    await expect(admin.locator('body')).toContainText('Which projects are on Dwarka Expressway?');
    await expect(admin.locator('.ai-status-gated')).toHaveCount(1);
    await admin.goto(`/admin/enquiry-view.php?id=${leadId}`);
    await expect(admin.locator('.admin-card', { hasText: 'Website chats' })).toContainText('Do you have 4 BHK homes?');
    await admin.goto('/admin/ai-log.php?view=questions');
    await expect(admin.locator('tbody')).toContainText('Which of them is ready to move?');
    await adminCtx.close();
    await visitorCtx.close();
  });

  test('QF-CHAT-003: the same phone from another browser joins the existing lead instead of creating a duplicate', async ({ browser }) => {
    const ctx = await newContext(browser);
    const page = await ctx.newPage();
    await openChat(page);
    await ask(page, 'Can someone call me about a site visit?');
    const form = page.locator('[data-lc="contact"]').last();
    await expect(form).toBeVisible();
    await form.locator('input[name="name"]').fill('ZZ Chat Buyer');
    await form.locator('input[name="phone"]').fill('9000000881');
    await form.locator('.lc-send').click();
    await expect(page.locator('#chat-messages')).toContainText('Thank you');
    expect(one("SELECT COUNT(*) FROM enquiries WHERE name = 'ZZ Chat Buyer'")).toBe('1');
    expect(one(`SELECT COUNT(*) FROM ai_visitors WHERE enquiry_id = ${leadId}`)).toBe('2');
    expect(one(`SELECT COUNT(*) FROM follow_ups WHERE enquiry_id = ${leadId} AND note LIKE '%website chat again%'`)).toBe('1');
    await ctx.close();
  });

  test('QF-CHAT-004: project assistant answers without a verified date (clearly marked) and also asks for a number after 5', async ({ browser }) => {
    const pid = one("SELECT id FROM properties WHERE deleted_at IS NULL AND info_verified_on IS NULL AND price_display NOT LIKE '%equest%' AND price_display <> '' ORDER BY id LIMIT 1");
    const ctx = await newContext(browser);
    const page = await ctx.newPage();
    await page.goto(`/property.php?id=${pid}`, { waitUntil: 'networkidle' });
    await page.click('[data-pa-toggle]');
    await page.fill('#ai-pa-q', 'What is the starting price?');
    await page.press('#ai-pa-q', 'Enter');
    const answer = page.locator('[data-pa-log] .ai-answer').last();
    await expect(answer).toContainText('starting price', { timeout: 30000 });
    await expect(answer).toContainText('As listed on our website');
    // Same visitor, already at the limit: the next question is answered with the phone form.
    sql("UPDATE ai_visitors SET question_count = 5 WHERE phone IS NULL ORDER BY last_seen_at DESC LIMIT 1;");
    await page.fill('#ai-pa-q', 'When is possession?');
    await page.press('#ai-pa-q', 'Enter');
    await expect(page.locator('[data-pa-log] [data-lc="contact"].lc-required')).toBeVisible({ timeout: 30000 });
    await expect(page.locator('[data-pa-log]')).toContainText(/name and phone/i);
    await ctx.close();
  });

  test('QF-PLACES-001: auto-fill adds OpenStreetMap places near a project, never touches manual or hidden places', async ({ page }) => {
    const [pid, lat, lng] = sql("SELECT id, lat, lng FROM properties WHERE deleted_at IS NULL AND lat IS NOT NULL ORDER BY id LIMIT 1")[0];
    sql(`INSERT INTO places (name, category, lat, lng, source, verified_on) VALUES ('ZZ Chat Manual School', 'school', ${Number(lat) + 0.001}, ${lng}, 'Our team', '2026-01-01');`);
    const hidden = one("SELECT id FROM places WHERE osm_ref IS NOT NULL AND osm_ref <> 'fixed:igi' AND deleted_at IS NULL ORDER BY id LIMIT 1");
    if (hidden) sql(`UPDATE places SET deleted_at = NOW() WHERE id = ${hidden};`);
    await adminLogin(page);
    await page.goto('/admin/places.php');
    const res = await page.evaluate(async ({ pid }) => {
      const token = document.querySelector('input[name="csrf_token"]') ? document.querySelector('input[name="csrf_token"]').value : '';
      const r = await fetch('/admin/places-autofill.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ property_id: pid, csrf_token: token }) });
      return r.json();
    }, { pid: Number(pid) });
    expect(res.ok).toBe(true);
    expect(one("SELECT CONCAT(source, '|', verified_on) FROM places WHERE name = 'ZZ Chat Manual School'")).toBe('Our team|2026-01-01');
    if (hidden) expect(one(`SELECT deleted_at IS NOT NULL FROM places WHERE id = ${hidden}`)).toBe('1');
    if (hidden) sql(`UPDATE places SET deleted_at = NULL WHERE id = ${hidden};`);
    await page.goto(`/property.php?id=${pid}`);
    await expect(page.locator('#ai-neighborhood')).toContainText('ZZ Chat Manual School');
  });

  test('QF-PROP-001: Properties shows what each project is missing for the assistant', async ({ page }) => {
    await adminLogin(page);
    await page.goto('/admin/properties.php');
    await expect(page.locator('.admin-filter-active')).toContainText('missing information');
    await page.click('text=Show them');
    await expect(page.locator('body')).toContainText('⚠ Missing:');
  });
});
