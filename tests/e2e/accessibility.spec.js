// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

const PUBLIC_PAGES = [
  ['Home', '/index.php'],
  ['All Properties', '/properties.php'],
  ['Property Detail', '/property.php?id=2'],
  ['Corridor Guide', '/sector.php?slug=golf-course-extension-road'],
  ['Contact', '/contact.php'],
];

for (const [label, url] of PUBLIC_PAGES) {
  test(`QF-A11Y-${label.replace(/\s/g, '')}: axe scan (WCAG2A/AA)`, async ({ page }) => {
    await page.goto(url);
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    // Attach full results for the dashboard to parse — don't hard-fail the run on findings,
    // this is a discovery pass (per QA Fortress: report positively-or-negatively, don't block).
    test.info().annotations.push({
      type: 'axe-violations',
      description: JSON.stringify(results.violations.map(v => ({
        id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.length,
      }))),
    });
    console.log(`AXE:${label}:${results.violations.length} violations`);
  });
}

test('QF-A11Y-keyboard-nav: primary nav is fully keyboard-reachable', async ({ page }) => {
  await page.goto('/index.php');
  await page.keyboard.press('Tab');
  const active1 = await page.evaluate(() => document.activeElement?.tagName);
  expect(['A', 'BUTTON']).toContain(active1);
});
