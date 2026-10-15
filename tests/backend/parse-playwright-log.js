// Parses the Playwright `list` reporter's text output (since the CLI --reporter=list
// flag overrode the json reporter configured in playwright.config.js for the full run)
// into the same shape generate-dashboard.js expects. Avoids a 35-minute re-run.
const fs = require('fs');
const path = require('path');

const logPath = process.argv[2];
const outPath = path.join(__dirname, '..', '..', 'docs', 'qa', 'reports', 'raw', 'playwright-results.json');

const lines = fs.readFileSync(logPath, 'utf8').split('\n');
const specs = [];
const lineRe = /^\s*(ok|x)\s+\d+\s+\[([\w-]+)\]\s+›\s+(tests\\e2e\\[\w.-]+\.js):\d+:\d+\s+›\s+(.+?)\s+›\s+(.+?)\s+\(([\d.]+)(m|s)\)\s*$/;

for (const line of lines) {
  const m = line.match(lineRe);
  if (!m) continue;
  const [, result, project, file, describe, title, durNum, durUnit] = m;
  const idMatch = title.match(/QF-[A-Z0-9]+-[\w.]+/);
  specs.push({
    id: idMatch ? idMatch[0] : title.slice(0, 20),
    title: `${describe} › ${title}`,
    file: file.split('\\').pop(),
    project,
    status: result === 'ok' ? 'passed' : 'failed',
    duration: Math.round(parseFloat(durNum) * (durUnit === 'm' ? 60000 : 1000)),
  });
}

fs.mkdirSync(path.dirname(outPath), { recursive: true });
fs.writeFileSync(outPath, JSON.stringify({ suites: [], specsFlat: specs, stats: {
  expected: specs.filter(s => s.status === 'passed').length,
  unexpected: specs.filter(s => s.status === 'failed').length,
} }, null, 2));
console.log(`Parsed ${specs.length} test results -> ${outPath}`);
console.log(`Passed: ${specs.filter(s=>s.status==='passed').length}, Failed: ${specs.filter(s=>s.status==='failed').length}`);
