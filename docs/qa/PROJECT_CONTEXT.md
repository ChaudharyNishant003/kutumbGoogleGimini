# Kutumb Realty — Project-Specific Context (QA Fortress Module 9)

Answers already established across this engagement, recorded here per the skill's spec rather than re-asked.

1. **Third-party services without a sandbox**: only OpenAI (chatbot). No sandbox mode exists — testing must mock failure paths (missing/invalid key) rather than spend real API quota per run, per the Production Firewall.
2. **Deprecated/unreachable features**: `admin/analytics.php` is a dead redirect stub to `dashboard.php` (its charts were merged in) — a `CANDIDATES FOR REMOVAL` item, not a bug. Corridors have no delete/Bin UI at all (not deprecated — never built).
3. **Compliance regime**: none formal. General care around lead/contact PII (names, phones, emails) is the working standard — no GDPR/HIPAA/PCI-specific requirement.
4. **Go-live/milestone**: a public redesign is in progress in parallel (separate track, not this testing cycle) — no hard deadline currently pinned to this QA pass specifically.
5. **Report audience**: Nishant, technical — the HTML dashboard should favor real numbers/charts over plain-language narrative.
6. **Existing test suite/CI**: none before this session. This QA Fortress run is establishing the first one (`tests/e2e/`, `tests/backend/`, `playwright.config.js`).
7. **Staging/test environment** (Standing Adaptation question): the local Docker dev/UAT stack (`docker-compose.yml`, `kutumb-app-1` + `kutumb-db-1`), built specifically to satisfy this requirement — confirmed isolated from production, healthy, and in active use for this test cycle.
