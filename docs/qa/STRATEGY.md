# Kutumb Realty — Test Strategy (QA Fortress Module 2)

## Mode
**Full Sweep** — first comprehensive run combining functional, security, and non-functional testing in one cycle (a prior pass already covered a slice of Tier-1 security; this run completes it rather than repeating it — see `FEATURE_MAP.md`'s "Known gaps").

## Execution order
SKILL.md §2, Tier 1→5, unconditional:
1. Auth/session, RBAC, isolation (mostly done; finishing IDOR + duplicate-submission)
2. Core CRUD + business-rule state machines, every entity
3. Input validation, error handling, breadth
4. Performance/load, accessibility, compatibility, third-party resilience
5. Extremes — scoped down deliberately (see below)

## Environment
Local Docker (`kutumb-app-1` + `kutumb-db-1`), never production. Standing Adaptation satisfied.

## Automation
- **Frontend/E2E**: Playwright (`playwright.config.js`), 5 projects — Chromium/Firefox/WebKit desktop + Pixel 7 + iPhone 14 device emulation. Tests in `tests/e2e/`.
- **Accessibility**: `@axe-core/playwright`, injected into the same Playwright runs against the 5 core public pages + admin dashboard.
- **Backend/API/security**: plain Node/PHP scripts in `tests/backend/` (same proven pattern as the earlier security sweep — no PHPUnit/Composer install).
- **Load**: a small Node script (built-in `fetch`, concurrent requests) — no `ab`/`k6` on this machine.

## Non-functional depth
- **Load**: light baseline ~30 concurrent + one heavy burst ~300 concurrent, on Home, Properties, Property Detail, admin Dashboard, and `api/enquiry-submit.php`. Local only — free, no cost, no production traffic.
- **Accessibility**: automated axe-core scan (WCAG2A/AA rules) + a manual keyboard-only nav pass.
- **Compatibility**: the 5 Playwright projects above cover desktop (3 engines) + mobile (2 device profiles) in one suite run.
- **Third-party resilience**: OpenAI key missing/invalid → confirm the WhatsApp-redirect fallback fires.
- **Tier 5 (extremes)**: explicitly out of scope this pass — chaos/fault-injection and long-run soak testing aren't warranted at this project's current scale; heavy load is a single bounded burst, not sustained.

## Test-file layout
```
tests/e2e/          — Playwright specs (public + admin flows, per-journey)
tests/backend/       — Node/PHP scripts (security, API, load)
docs/qa/reports/     — dashboard.html (the deliverable) + raw/ (JSON results feeding it)
```

## Output
Per Nishant's explicit instruction: **not prose reports** — a single consolidated HTML QA Dashboard (`docs/qa/reports/dashboard.html`), sectioned by module, chart-driven, click-to-expand for defect/test detail. Built once per run, saved as a reusable template so future sweeps regenerate the same format with fresh data. `COVERAGE.md` stays plain markdown underneath it for traceability/grep-ability.
