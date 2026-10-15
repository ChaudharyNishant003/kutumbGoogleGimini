// QA Fortress Module 5.1 — light baseline + heavy burst, local Docker only.
// Usage: node tests/backend/load-test.js
const BASE = 'http://localhost:8000';
const TARGETS = [
  { name: 'Home', path: '/index.php' },
  { name: 'Properties', path: '/properties.php' },
  { name: 'Property Detail', path: '/property.php?id=2' },
  { name: 'Admin Dashboard (redirects, unauth)', path: '/admin/dashboard.php' },
  { name: 'Enquiry Submit (POST)', path: '/api/enquiry-submit.php', method: 'POST',
    body: 'name=LoadTest&phone=9999900099&source=load-test' },
];

async function fireOne(target) {
  const start = Date.now();
  try {
    const res = await fetch(BASE + target.path, {
      method: target.method || 'GET',
      body: target.body,
      headers: target.body ? { 'Content-Type': 'application/x-www-form-urlencoded' } : undefined,
      redirect: 'manual',
    });
    return { ok: res.status < 500, status: res.status, ms: Date.now() - start };
  } catch (e) {
    return { ok: false, status: 0, ms: Date.now() - start, error: e.message };
  }
}

async function burst(target, concurrency) {
  const results = await Promise.all(Array.from({ length: concurrency }, () => fireOne(target)));
  const times = results.map(r => r.ms).sort((a, b) => a - b);
  const errors = results.filter(r => !r.ok).length;
  const pct = (p) => times[Math.floor(times.length * p)] || times[times.length - 1];
  return {
    target: target.name, concurrency,
    min: times[0], p50: pct(0.5), p90: pct(0.9), p99: pct(0.99), max: times[times.length - 1],
    errorRate: +((errors / concurrency) * 100).toFixed(1),
  };
}

(async () => {
  const report = { lightBaseline: [], heavyBurst: [] };
  console.log('=== Light baseline (30 concurrent) ===');
  for (const t of TARGETS) {
    const r = await burst(t, 30);
    report.lightBaseline.push(r);
    console.log(`${r.target}: p50=${r.p50}ms p90=${r.p90}ms p99=${r.p99}ms max=${r.max}ms errors=${r.errorRate}%`);
  }
  console.log('\n=== Heavy burst (300 concurrent) ===');
  for (const t of TARGETS) {
    const r = await burst(t, 300);
    report.heavyBurst.push(r);
    console.log(`${r.target}: p50=${r.p50}ms p90=${r.p90}ms p99=${r.p99}ms max=${r.max}ms errors=${r.errorRate}%`);
  }
  require('fs').mkdirSync('docs/qa/reports/raw', { recursive: true });
  require('fs').writeFileSync('docs/qa/reports/raw/load-test.json', JSON.stringify(report, null, 2));
  console.log('\nWritten to docs/qa/reports/raw/load-test.json');
})();
