// QA Fortress — HTML Dashboard Generator (the reusable template).
// Reads every JSON file under docs/qa/reports/raw/ and renders one consolidated
// dashboard.html. Re-run this any time after a test cycle to refresh the dashboard
// with fresh numbers — the template itself doesn't change, only the data feeding it.
//
// Usage: node tests/backend/generate-dashboard.js

const fs = require('fs');
const path = require('path');

const RAW_DIR = path.join(__dirname, '..', '..', 'docs', 'qa', 'reports', 'raw');
const OUT_HTML = path.join(__dirname, '..', '..', 'docs', 'qa', 'reports', 'dashboard.html');
const OUT_DESKTOP = path.join(process.env.USERPROFILE || '', 'Desktop', 'Kutumb-QA-Dashboard.html');

function readJSON(file, fallback) {
  const p = path.join(RAW_DIR, file);
  if (!fs.existsSync(p)) return fallback;
  try { return JSON.parse(fs.readFileSync(p, 'utf8')); } catch { return fallback; }
}

function readPlaywrightResults() {
  const raw = readJSON('playwright-results.json', null);
  if (!raw) return { specs: [], stats: {} };
  // parse-playwright-log.js output shape: { specsFlat: [...], stats }
  if (raw.specsFlat) return { specs: raw.specsFlat, stats: raw.stats || {} };
  // Native Playwright JSON reporter shape (if a future run uses it directly)
  const specs = [];
  function walk(suite, filePrefix) {
    for (const s of suite.suites || []) walk(s, filePrefix);
    for (const spec of suite.specs || []) {
      for (const test of spec.tests || []) {
        const result = test.results?.[0] || {};
        specs.push({
          id: (spec.title.match(/QF-[A-Z]+-[\w]+/) || [spec.title])[0],
          title: spec.title,
          file: filePrefix,
          project: test.projectName,
          status: result.status || 'unknown',
          duration: result.duration || 0,
          error: result.error?.message || null,
        });
      }
    }
  }
  for (const suite of raw.suites || []) walk(suite, suite.title);
  return { specs, stats: raw.stats || {} };
}

const pw = readPlaywrightResults();
const secCompletion = readJSON('security-completion.json', []);
const chatbotSec = readJSON('chatbot-security.json', []);
const loadTest = readJSON('load-test.json', { lightBaseline: [], heavyBurst: [] });

const backendChecks = [...secCompletion, ...chatbotSec];

// Known findings carried forward from the earlier security sweep (not re-run here,
// see docs/qa/FEATURE_MAP.md "Known gaps") plus the new one found this cycle.
const knownDefects = [
  { id: 'QF-SEC-001', severity: 'P1', area: 'AUTH', title: 'Admin login has no brute-force/rate-limit protection', status: 'fixed', detail: "FIXED — admin/login.php now calls rate_limit_check('admin-login', 15, 10) before checking credentials, showing \"Too many login attempts\" past the threshold. 15/10min (not the public-form default of 5) so one shared office IP with several staff signing in doesn't get falsely locked out; a brute-force attempt against a bcrypt hash is still fully defeated well below that rate. Verified live via curl (blocked on the 6th-and-later of 7 rapid attempts before the threshold was widened, then reconfirmed at 15) and via an isolated regression test, tests/e2e/admin-login-rate-limit.spec.js (kept in its own file since it deliberately exhausts the quota and would otherwise block other tests' logins in the same run)." },
  { id: 'QF-SEC-002', severity: 'P1', area: 'AUTH', title: 'Session cookie missing HttpOnly flag', status: 'fixed', detail: "FIXED — ini_set('session.cookie_httponly', '1') added before session_start() in config/config.php, config.sample.php, and config.railway.php (the file Railway's Dockerfile actually deploys). Verified live via curl -I on /admin/login.php: Set-Cookie now includes HttpOnly. Regression test in tests/e2e/admin-auth-rbac.spec.js." },
  { id: 'QF-SEC-003', severity: 'P2', area: 'AUTH', title: 'Session cookie missing Secure and SameSite flags', status: 'fixed-partial', detail: "SameSite FIXED — ini_set('session.cookie_samesite', 'Lax') added alongside the HttpOnly fix, verified live via curl. The Secure flag was deliberately deferred: setting it requires correctly detecting HTTPS behind Railway's proxy from this local/offline environment, and getting that detection wrong risks locking out admin login in production (a cookie marked Secure is silently dropped over any connection the browser doesn't consider HTTPS). Recommended next step: add the Secure flag right after the next production deploy, verified live against the real Railway domain rather than guessed at from here." },
  { id: 'QF-SEC-004', severity: 'P2', area: 'DATA', title: 'No .htaccess in uploads/ blocking PHP execution', status: 'fixed', detail: "FIXED — added uploads/properties/.htaccess and uploads/documents/.htaccess (Require all denied on .php/.phtml, matching the root .htaccess's pattern). Confirmed the Dockerfile already sets AllowOverride All, so these take effect, and docker-start.sh's uploads-seed copy uses cp -rvn (no-clobber), so the new .htaccess files will land in the existing production volume on the next deploy without needing a manual step. Verified live: requesting a .php file under either directory now returns HTTP 403 (was previously whatever the file would have executed as). Regression check added to tests/backend/security-completion.php (QF-SEC-004-properties / QF-SEC-004-documents)." },
  { id: 'QF-UX-001', severity: 'P3', area: 'RBAC', title: 'Settings sidebar link shown to non-owner roles', status: 'fixed', detail: "FIXED — includes/admin-header.php now wraps both the \"Settings\" section label and the Settings link in the same role === 'owner' check already used for Manage Admins, so agent/admin users no longer see a link that would 403 for them. settings.php's own server-side require_role('owner') was already correct and unchanged. The existing QF-UX-001 Playwright test (previously a soft annotation-only check) was converted to a hard assertion and confirmed passing." },
  { id: 'QF-COMPAT-001', severity: 'P2', area: 'COMPAT', title: 'Compare toggle buttons fail to register clicks on Firefox, WebKit, and mobile emulation (Chromium unaffected)', status: 'fixed-partial', detail: "ROOT CAUSE FOUND AND FIXED for the real bug: the compare-toggle icon is an inline SVG with fill=\"none\" (stroke-only), and browsers differ on whether a click through its unpainted interior counts as hitting the icon or falling through to the card underneath — on Firefox this caused the click to fall through to the property card's own link and navigate away mid-test instead of toggling compare. Fixed with .compare-toggle svg { pointer-events: none }, forcing every click to resolve to the button itself regardless of engine. Also changed the hover effect from transform: scale() to box-shadow, removing a geometry change that could affect click-target stability. Confirmed fixed on chromium-desktop, firefox-desktop, and mobile-android (Chromium-based) — all pass cleanly now.\n\nREMAINING (webkit-desktop, mobile-ios — same WebKit engine): Playwright still reports \"element is not stable\" here. Investigated with a diagnostic (a fixed 5-second wait before clicking resolves it every time), which confirms this is Playwright's own conservative synthetic-click heuristic reacting to the page's legitimate scroll-triggered entrance animation (cards fade/slide in via GSAP the first time they scroll into view) — not a functional defect. A real tap isn't gated by that heuristic; browsers hit-test wherever a finger actually lands, animating or not. Tried two test-side mitigations (scroll-and-wait, wait-for-opacity) — neither fully resolved WebKit's timing without adding new flakiness elsewhere, so rather than keep chasing a test-tool artifact, this is left documented rather than 'fixed': WebKit/Safari users should be spot-checked manually rather than relying on this automated signal." },
  { id: 'QF-NFR-401', severity: 'P3', area: 'COMPAT', title: 'Firefox accessibility scan inconclusive on this machine (environment issue, not a product finding)', status: 'wontfix-environment', detail: "NOT FIXABLE VIA CODE — headless Firefox crashed during axe-core injection on this machine (RenderCompositorSWGL graphics error), a known headless-Firefox-on-Windows GPU compatibility issue unrelated to Kutumb's code. There is no application change that addresses this; it needs either a different test machine/CI runner or a manual Firefox spot-check instead of the automated a11y scan." },
];

function severityCounts(defects) {
  const c = { P0: 0, P1: 0, P2: 0, P3: 0 };
  defects.forEach(d => { c[d.severity] = (c[d.severity] || 0) + 1; });
  return c;
}

const STATUS_LABEL = {
  open: 'Open', fixed: 'Fixed', 'fixed-partial': 'Partially Fixed', 'wontfix-environment': 'Env. Limitation',
};
const openDefects = knownDefects.filter(d => (d.status || 'open') === 'open' || d.status === 'fixed-partial');

const pwPassed = pw.specs.filter(s => s.status === 'passed').length;
const pwFailed = pw.specs.filter(s => s.status === 'failed' || s.status === 'timedOut').length;
const pwSkipped = pw.specs.filter(s => s.status === 'skipped').length;
const pwTotal = pw.specs.length;

const backendPassed = backendChecks.filter(c => c.pass).length;
const backendTotal = backendChecks.length;

const sevCounts = severityCounts(openDefects);
const totalChecks = pwTotal + backendTotal;
const totalPassed = pwPassed + backendPassed;
const overallPassPct = totalChecks ? Math.round((totalPassed / totalChecks) * 100) : 0;

// Group Playwright specs by file (module)
const byModule = {};
for (const s of pw.specs) {
  const mod = s.file || 'other';
  byModule[mod] = byModule[mod] || { passed: 0, failed: 0, skipped: 0, specs: [] };
  if (s.status === 'passed') byModule[mod].passed++;
  else if (s.status === 'skipped') byModule[mod].skipped++;
  else byModule[mod].failed++;
  byModule[mod].specs.push(s);
}

const loadRows = (arr) => arr.map(r =>
  `<tr><td>${esc(r.target)}</td><td class="num">${r.p50}</td><td class="num">${r.p90}</td><td class="num">${r.p99}</td><td class="num">${r.max}</td><td class="num ${r.errorRate > 0 ? 'err' : ''}">${r.errorRate}%</td></tr>`
).join('');

function esc(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

const genTime = new Date().toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });

const html = `<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kutumb QA Dashboard</title>
<style>
:root{
  --navy:#002366; --navy-deep:#011438; --navy-950:#04081c;
  --orange:#ff9909; --orange-deep:#d97e00;
  --paper:#fbfaf6; --paper-dim:#f3f1ea; --line:#e4e1d6;
  --ink:#16191f; --ink-soft:#4a4f5a; --ink-muted:#898781;
  --surface:#ffffff;
  --good:#0ca30c; --warning:#c98500; --serious:#c2542f; --critical:#d03b3b;
  --s1:#002366; --s2:#eb6834; --s3:#1baf7a; --s4:#eda100;
  --font: -apple-system, "Segoe UI", system-ui, sans-serif;
  --font-display: 'Newsreader', Georgia, serif;
}
*{box-sizing:border-box;}
body{margin:0;font-family:var(--font);background:var(--paper);color:var(--ink);font-size:14px;}
.header{background:linear-gradient(160deg,var(--navy-deep),var(--navy-950));color:#fff;padding:28px 32px;}
.header h1{font-family:var(--font-display);font-size:26px;margin:0 0 4px;font-weight:500;}
.header h1 span{color:var(--orange);}
.header .meta{color:rgba(255,255,255,.6);font-size:12.5px;}
.wrap{max-width:1280px;margin:0 auto;padding:28px 32px 80px;}
.stat-strip{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:32px;}
.stat-tile{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:18px 20px;box-shadow:0 1px 2px rgba(1,20,56,.06),0 3px 10px rgba(1,20,56,.06);}
.stat-tile .num{font-family:var(--font-display);font-size:32px;color:var(--navy-deep);display:block;line-height:1;}
.stat-tile .label{font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-muted);margin-top:6px;display:block;}
.stat-tile.pass .num{color:var(--good);}
.stat-tile.fail .num{color:var(--critical);}
section{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:24px 26px;margin-bottom:24px;}
section h2{font-family:var(--font-display);font-size:19px;margin:0 0 4px;color:var(--navy-deep);font-weight:500;}
section .sub{color:var(--ink-soft);font-size:13px;margin-bottom:18px;}
.sev-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:8px;}
.sev-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:999px;font-size:12.5px;font-weight:600;cursor:pointer;border:1px solid transparent;}
.sev-pill.P0{background:#fbe0e0;color:var(--critical);}
.sev-pill.P1{background:#fde3d3;color:#b3480a;}
.sev-pill.P2{background:#fdf0d3;color:var(--warning);}
.sev-pill.P3{background:#e6ebf2;color:var(--ink-soft);}
.status-pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11.5px;font-weight:600;}
.status-pill.status-fixed{background:#dcf3dc;color:var(--good);}
.status-pill.status-fixed-partial{background:#fdf0d3;color:var(--warning);}
.status-pill.status-wontfix-environment{background:#e6ebf2;color:var(--ink-soft);}
.status-pill.status-open{background:#fbe0e0;color:var(--critical);}
.bar-chart{display:flex;flex-direction:column;gap:10px;}
.bar-row{display:grid;grid-template-columns:160px 1fr 50px;align-items:center;gap:12px;font-size:12.5px;}
.bar-row .label{color:var(--ink-soft);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.bar-track{background:var(--paper-dim);border-radius:6px;height:20px;overflow:hidden;display:flex;}
.bar-fill{height:100%;}
.bar-fill.pass{background:var(--good);}
.bar-fill.fail{background:var(--critical);}
.bar-fill.skip{background:var(--ink-muted);}
.bar-row .count{text-align:right;color:var(--navy-deep);font-weight:600;font-variant-numeric:tabular-nums;}
.defect-table, .load-table{width:100%;border-collapse:collapse;}
.defect-table th, .load-table th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:var(--ink-muted);padding:8px 10px;border-bottom:1px solid var(--line);}
.defect-table td, .load-table td{padding:10px;border-bottom:1px solid var(--line);vertical-align:top;font-size:13px;}
.load-table td.num{text-align:right;font-variant-numeric:tabular-nums;}
.load-table td.err{color:var(--critical);font-weight:600;}
.defect-row{cursor:pointer;}
.defect-row:hover{background:var(--paper-dim);}
.defect-detail{display:none;background:var(--paper-dim);padding:14px 16px;font-size:12.5px;color:var(--ink-soft);border-radius:8px;margin:0 0 8px;white-space:pre-line;}
.defect-detail.open{display:block;}
.badge-new{background:var(--orange);color:var(--navy-deep);font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;margin-left:6px;text-transform:uppercase;}
.legend{display:flex;gap:16px;font-size:12px;color:var(--ink-soft);margin-top:14px;}
.legend span{display:inline-flex;align-items:center;gap:5px;}
.legend i{width:10px;height:10px;border-radius:3px;display:inline-block;}
.click-hint{font-size:11.5px;color:var(--ink-muted);font-style:italic;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:24px;}
@media (max-width:900px){.stat-strip{grid-template-columns:repeat(2,1fr);}.two-col{grid-template-columns:1fr;}}
.check-icon{color:var(--good);}
.x-icon{color:var(--critical);}
.callout-note{background:#fdf0d3;border-left:3px solid var(--warning);border-radius:6px;padding:10px 14px;font-size:12px;color:#6b4d00;margin-top:12px;}
.callout-note code{background:rgba(0,0,0,.06);padding:1px 5px;border-radius:4px;}
</style>
</head>
<body>
<div class="header">
  <h1>Kutumb<span>.</span> QA Dashboard</h1>
  <div class="meta">Generated ${genTime} · Local Docker environment · Full Sweep (11 Sep 2026) + Fix Pass (15 Sep 2026)</div>
</div>
<div class="wrap">

  <div class="stat-strip">
    <div class="stat-tile"><span class="num">${totalChecks}</span><span class="label">Total Checks Run</span></div>
    <div class="stat-tile pass"><span class="num">${overallPassPct}%</span><span class="label">Overall Pass Rate</span></div>
    <div class="stat-tile fail"><span class="num">${pwFailed}</span><span class="label">Playwright Failures</span></div>
    <div class="stat-tile"><span class="num">${openDefects.length}</span><span class="label">Open Findings</span></div>
    <div class="stat-tile"><span class="num">${sevCounts.P1}</span><span class="label">P1 Findings</span></div>
  </div>

  <section>
    <h2>Severity Breakdown</h2>
    <div class="sub">Click a severity to jump to its findings below.</div>
    <div class="sev-row">
      <span class="sev-pill P0" onclick="scrollToDefects()">P0 Blocker — ${sevCounts.P0}</span>
      <span class="sev-pill P1" onclick="scrollToDefects()">P1 Critical — ${sevCounts.P1}</span>
      <span class="sev-pill P2" onclick="scrollToDefects()">P2 Major — ${sevCounts.P2}</span>
      <span class="sev-pill P3" onclick="scrollToDefects()">P3 Minor — ${sevCounts.P3}</span>
    </div>
  </section>

  <div class="two-col">
    <section>
      <h2>Functional / E2E (Playwright)</h2>
      <div class="sub">${pwTotal} tests across 5 browser/device profiles</div>
      <div class="callout-note">⚠ This chart is the original Full Sweep snapshot from 11 Sep 2026, before the fix pass. Of its ${pwFailed} failures, 15 (EMI zero-price check × 5 browsers, property-CRUD pagination check × 5 browsers) were bugs in the <em>test scripts themselves</em> — not the site. The 7 real findings from this snapshot were fixed on 15 Sep 2026 and re-verified with <strong>targeted</strong> reruns (the specific specs each fix touches, plus connected admin RBAC/CRUD regression), not a fresh full 5-browser suite run — see the Findings section below for exactly what was re-run per item.</div>
      <div class="bar-chart">
        ${Object.entries(byModule).map(([mod, d]) => {
          const total = d.passed + d.failed + d.skipped;
          const passPct = total ? (d.passed / total) * 100 : 0;
          const failPct = total ? (d.failed / total) * 100 : 0;
          const skipPct = total ? (d.skipped / total) * 100 : 0;
          return `<div class="bar-row"><span class="label" title="${esc(mod)}">${esc(mod.replace('.spec.js', ''))}</span>
            <div class="bar-track"><div class="bar-fill pass" style="width:${passPct}%"></div><div class="bar-fill fail" style="width:${failPct}%"></div><div class="bar-fill skip" style="width:${skipPct}%"></div></div>
            <span class="count">${d.passed}/${total}</span></div>`;
        }).join('') || '<p class="click-hint">No Playwright results yet — run <code>npx playwright test</code> then regenerate.</p>'}
      </div>
      <div class="legend"><span><i style="background:var(--good)"></i>Pass</span><span><i style="background:var(--critical)"></i>Fail</span><span><i style="background:var(--ink-muted)"></i>Skipped</span></div>
    </section>

    <section>
      <h2>Backend / Security Checks</h2>
      <div class="sub">${backendTotal} checks — IDOR, duplicate-submission, fuzzing, chatbot resilience</div>
      <div class="bar-chart">
        <div class="bar-row"><span class="label">All backend checks</span>
          <div class="bar-track"><div class="bar-fill pass" style="width:${backendTotal ? (backendPassed/backendTotal)*100 : 0}%"></div><div class="bar-fill fail" style="width:${backendTotal ? ((backendTotal-backendPassed)/backendTotal)*100 : 0}%"></div></div>
          <span class="count">${backendPassed}/${backendTotal}</span></div>
      </div>
      <table class="defect-table" style="margin-top:16px;">
        <tr><th>ID</th><th>Check</th><th>Result</th></tr>
        ${backendChecks.map(c => `<tr><td>${esc(c.id)}</td><td>${esc(c.title)}</td><td>${c.pass ? '<span class="check-icon">✓ Pass</span>' : '<span class="x-icon">✗ Fail</span>'}</td></tr>`).join('')}
      </table>
    </section>
  </div>

  <section>
    <h2>Load / Stress Test</h2>
    <div class="sub">Local Docker only — light baseline (30 concurrent) vs. heavy burst (300 concurrent). Response times in ms.</div>
    ${loadTest.lightBaseline.length ? `
    <h3 style="font-size:14px;color:var(--ink-soft);margin:16px 0 8px;">Light baseline (30 concurrent)</h3>
    <table class="load-table"><tr><th>Path</th><th>p50</th><th>p90</th><th>p99</th><th>max</th><th>Error rate</th></tr>${loadRows(loadTest.lightBaseline)}</table>
    <h3 style="font-size:14px;color:var(--ink-soft);margin:20px 0 8px;">Heavy burst (300 concurrent)</h3>
    <table class="load-table"><tr><th>Path</th><th>p50</th><th>p90</th><th>p99</th><th>max</th><th>Error rate</th></tr>${loadRows(loadTest.heavyBurst)}</table>
    ` : '<p class="click-hint">Load test not yet run — run <code>node tests/backend/load-test.js</code> then regenerate.</p>'}
  </section>

  <section id="defects">
    <h2>Findings (click a row to expand)</h2>
    <div class="sub">All 7 findings from the Full Sweep were addressed in this fix pass (click a row for what changed and how it was verified). Testing was targeted to the affected areas and connected modules, not a full suite re-run.</div>
    <table class="defect-table">
      <tr><th></th><th>ID</th><th>Severity</th><th>Area</th><th>Title</th><th>Status</th></tr>
      ${knownDefects.map((d, i) => `
        <tr class="defect-row" onclick="toggleDefect(${i})">
          <td>▸</td><td>${esc(d.id)}</td>
          <td><span class="sev-pill ${d.severity}">${d.severity}</span></td>
          <td>${esc(d.area)}</td>
          <td>${esc(d.title)}</td>
          <td><span class="status-pill status-${esc(d.status || 'open')}">${esc(STATUS_LABEL[d.status] || 'Open')}</span></td>
        </tr>
        <tr><td colspan="6" style="padding:0;border:none;"><div class="defect-detail" id="defect-${i}">${esc(d.detail)}</div></td></tr>
      `).join('')}
    </table>
  </section>

</div>
<script>
function toggleDefect(i) {
  var el = document.getElementById('defect-' + i);
  el.classList.toggle('open');
}
function scrollToDefects() {
  document.getElementById('defects').scrollIntoView({ behavior: 'smooth' });
}
</script>
</body>
</html>`;

fs.mkdirSync(path.dirname(OUT_HTML), { recursive: true });
fs.writeFileSync(OUT_HTML, html);
console.log('Written: ' + OUT_HTML);
try {
  fs.writeFileSync(OUT_DESKTOP, html);
  console.log('Copied to Desktop: ' + OUT_DESKTOP);
} catch (e) {
  console.log('Could not copy to Desktop: ' + e.message);
}
