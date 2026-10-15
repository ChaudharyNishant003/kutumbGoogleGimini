// @ts-check
const { test, expect } = require('@playwright/test');

// QF-AI-001..014 — AI features. OpenAI-backed endpoints are mocked with page.route so these
// runs are deterministic and cost nothing; everything that doesn't call the model runs for real.

const finderReply = (over = {}) => ({
  ok: true,
  state: 'B',
  chips: [
    { label: 'Dwarka Expressway', type: 'req', query: { bhk: [3], price_max: 40000000 } },
    { label: '3 BHK', type: 'req', query: { corridor_id: 3, price_max: 40000000 } },
    { label: 'Up to ₹4 Cr', type: 'req', query: { corridor_id: 3, bhk: [3] } },
  ],
  count: 4,
  query: { corridor_id: 3, bhk: [3], price_max: 40000000 },
  relaxations: [],
  unrecognised: [],
  ambiguity: null,
  city: null,
  ...over,
});

test.describe('AI Home Finder (F2)', () => {
  test('QF-AI-001: interprets text into editable chips and links to real results', async ({ page }) => {
    await page.route('**/api/ai-home-finder.php', async (route) => {
      const body = route.request().postDataJSON();
      expect(body.mode).toBe('interpret');
      expect(body.query).toContain('3 BHK');
      await route.fulfill({ json: finderReply() });
    });
    await page.goto('/index.php', { waitUntil: 'domcontentloaded' });
    await page.fill('#ai-finder-hero-q', '3 BHK on Dwarka Expressway under 4 crore');
    await page.press('#ai-finder-hero-q', 'Enter');
    const finder = page.locator('#ai-finder-hero');
    await expect(finder).toHaveAttribute('data-state', 'B');
    await expect(finder.locator('[data-chips="req"] .ai-chip')).toHaveCount(3);
    const link = finder.locator('[data-search-link]');
    await expect(link).toHaveText('Show 4 matching projects');
    await expect(link).toHaveAttribute('href', /properties\.php\?corridor_id=3.*bhk%5B%5D=3/);
  });

  test('QF-AI-002: removing a chip re-counts without calling the model', async ({ page }) => {
    let counts = 0;
    await page.route('**/api/ai-home-finder.php', async (route) => {
      const body = route.request().postDataJSON();
      if (body.mode === 'count') {
        counts++;
        await route.fulfill({ json: finderReply({ chips: finderReply().chips.slice(1), count: 9, query: body.filters }) });
      } else {
        await route.fulfill({ json: finderReply() });
      }
    });
    await page.goto('/index.php', { waitUntil: 'domcontentloaded' });
    await page.fill('#ai-finder-hero-q', '3 BHK dwarka expressway 4 cr');
    await page.press('#ai-finder-hero-q', 'Enter');
    await page.locator('#ai-finder-hero .ai-chip-x').first().click();
    await expect(page.locator('#ai-finder-hero [data-search-link]')).toHaveText('Show 9 matching projects');
    expect(counts).toBe(1);
  });

  test('QF-AI-003: no exact match shows closest options (state C)', async ({ page }) => {
    await page.route('**/api/ai-home-finder.php', (route) => route.fulfill({ json: finderReply({
      state: 'C', count: 0,
      relaxations: [{ label: '3 BHK · Up to ₹4.4 Cr', count: 2, url: '/properties.php?price_max=44000000', query: { bhk: [3], price_max: 44000000 } }],
    }) }));
    await page.goto('/index.php', { waitUntil: 'domcontentloaded' });
    await page.fill('#ai-finder-hero-q', 'x');
    await page.press('#ai-finder-hero-q', 'Enter');
    const finder = page.locator('#ai-finder-hero');
    await expect(finder).toHaveAttribute('data-state', 'C');
    await expect(finder.locator('.ai-option')).toContainText('2 projects');
  });

  test('QF-AI-004: ambiguous term asks instead of guessing (state D), never offers school ratings', async ({ page }) => {
    await page.route('**/api/ai-home-finder.php', (route) => route.fulfill({ json: finderReply({ state: 'D', ambiguity: { term: 'good schools', kind: 'school_quality' } }) }));
    await page.goto('/index.php', { waitUntil: 'domcontentloaded' });
    await page.fill('#ai-finder-hero-q', 'near good schools');
    await page.press('#ai-finder-hero-q', 'Enter');
    const finder = page.locator('#ai-finder-hero');
    await expect(finder).toHaveAttribute('data-state', 'D');
    await expect(finder.locator('[data-amb-term]')).toHaveText('good schools');
    await expect(finder.locator('[data-amb="nearest_school"]')).toBeVisible();
    await expect(finder).not.toContainText(/rated|ranking of schools/i);
  });

  test('QF-AI-005: model text is rendered as text, never as HTML', async ({ page }) => {
    await page.route('**/api/ai-home-finder.php', (route) => route.fulfill({ json: finderReply({
      chips: [{ label: '<img src=x onerror="window.__xss=1">', type: 'req', query: {} }],
    }) }));
    await page.goto('/index.php', { waitUntil: 'domcontentloaded' });
    await page.fill('#ai-finder-hero-q', 'x');
    await page.press('#ai-finder-hero-q', 'Enter');
    await expect(page.locator('#ai-finder-hero .ai-chip').first()).toContainText('<img');
    expect(await page.evaluate(() => window.__xss)).toBeUndefined();
  });

  test('QF-AI-006: rate-limited reply shows a friendly message with WhatsApp', async ({ page }) => {
    await page.route('**/api/ai-home-finder.php', (route) => route.fulfill({ status: 429, json: { ok: false, code: 'limited', message: "You've asked a lot of questions in a short time.", whatsapp: 'https://wa.me/910000000000' } }));
    await page.goto('/index.php', { waitUntil: 'domcontentloaded' });
    await page.fill('#ai-finder-hero-q', 'x');
    await page.press('#ai-finder-hero-q', 'Enter');
    await expect(page.locator('#ai-finder-hero [data-finder-msg]')).toContainText('a lot of questions');
    await expect(page.locator('#ai-finder-hero [data-finder-msg] a')).toHaveAttribute('href', /wa\.me/);
  });
});

test.describe('Recommendations (F1) — real data, no LLM', () => {
  test('QF-AI-007: results page explains fit, and "3.5 BHK" is never counted as 3 BHK', async ({ page }) => {
    await page.goto('/properties.php?bhk%5B%5D=3&price_max=40000000');
    const panel = page.locator('#ai-recommend');
    await expect(panel).toBeVisible();
    await expect(panel.locator('.ai-rec').first()).toContainText('Recommended because');
    await expect(panel).not.toContainText('3 BHK available (3.5');
  });

  test('QF-AI-008: without filters, no brief is invented (the finder above is the single prompt)', async ({ page }) => {
    await page.goto('/properties.php');
    // The "Ranked by fit" invite was removed from this page. The standard filters come first and the
    // AI finder is an optional toggle below them (collapsed until the visitor opens it).
    await expect(page.locator('.ai-recommend-invite')).toHaveCount(0);
    await expect(page.locator('#pf-keyword')).toBeVisible();
    const toggle = page.locator('.finder-toggle');
    await expect(toggle).not.toHaveAttribute('open', '');
    await toggle.locator('summary').click();
    await expect(page.locator('[data-finder]').first()).toBeVisible();
    await expect(page.locator('.ai-rec')).toHaveCount(0);
  });

  test('QF-AI-009: chips on the results page are real links that remove one filter', async ({ page }) => {
    await page.goto('/properties.php?bhk%5B%5D=3&price_max=40000000');
    const x = page.locator('#ai-finder-compact .ai-chip-x').first();
    const href = await x.getAttribute('href');
    expect(href).toMatch(/^\/properties\.php/);
    await x.click();
    await page.waitForURL(/properties\.php/);
    await expect(page.locator('#ai-finder-compact .ai-chip')).toHaveCount(1);
  });
});

test.describe('Project Assistant (F5)', () => {
  test('QF-AI-010: opens, answers with source + date, closes with Escape and returns focus', async ({ page }) => {
    await page.route('**/api/ai-assistant.php', (route) => route.fulfill({ json: {
      ok: true, kind: 'answer', intent: 'possession', title: 'Possession timeline',
      lines: ['The project page currently lists expected possession as December 2028.'],
      source: 'Source: project information updated 20 September 2026.',
      caveat: 'This is an expected timeline and may be subject to official updates.',
      links: [{ text: 'Speak with the team', href: '/contact.php?property=2&topic=possession' }],
    } }));
    await page.goto('/property.php?id=2');
    const launcher = page.locator('[data-pa-toggle]');
    await launcher.click();
    await expect(page.locator('#ai-pa-panel')).toBeVisible();
    await page.locator('[data-pa-ask="When is possession expected?"]').click();
    await expect(page.locator('.ai-pa-log .ai-source')).toContainText('updated 20 September 2026');
    await page.keyboard.press('Escape');
    await expect(page.locator('#ai-pa-panel')).toBeHidden();
    await expect(launcher).toBeFocused();
  });

  test('QF-AI-011: "Speak with the team" carries the question to the contact form (no retyping)', async ({ page }) => {
    await page.route('**/api/ai-assistant.php', (route) => route.fulfill({ json: {
      ok: true, kind: 'fallback', intent: 'unsupported', title: null,
      lines: ['I don’t have a verified current answer for that — here’s how to reach our team.'],
      links: [{ text: 'Speak with the team', href: '/contact.php?property=2&topic=unsupported' }],
    } }));
    await page.goto('/property.php?id=2');
    await page.locator('[data-pa-toggle]').click();
    await page.fill('#ai-pa-q', 'Will prices go up next year?');
    await page.press('#ai-pa-q', 'Enter');
    await page.locator('.ai-pa-log a', { hasText: 'Speak with the team' }).click();
    await page.waitForURL(/contact\.php\?property=2/);
    await expect(page.locator('#contact-message')).toHaveValue(/Will prices go up next year\?/);
    await expect(page).not.toHaveURL(/prices/);
  });

  test('QF-AI-012: the site chatbot button is replaced by the project assistant on listing pages only', async ({ page }) => {
    await page.goto('/property.php?id=2');
    await expect(page.locator('#chat-toggle')).toBeHidden();
    await page.goto('/about.php');
    await expect(page.locator('#chat-toggle')).toBeVisible();
  });
});

test.describe('Map & Neighbourhood (F4)', () => {
  test('QF-AI-013: map search always shows a plain list of the same results', async ({ page }) => {
    await page.goto('/map-search.php');
    const items = page.locator('[data-map-list] li');
    await expect(items.first()).toBeVisible();
    expect(await items.count()).toBeGreaterThan(0);
  });

  test('QF-AI-014: AI endpoints reject cross-site requests', async ({ request }) => {
    const res = await request.post('/api/ai-home-finder.php', {
      headers: { Origin: 'https://evil.example', 'Content-Type': 'application/json' },
      data: { mode: 'count', filters: {} },
    });
    expect(res.status()).toBe(403);
  });
});
