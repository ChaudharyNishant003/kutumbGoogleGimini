# UAT branch

`uat` = every change not yet on `master`, in one branch, for a single round of testing.
Contains (in order): feature/voice-runtime-deploy → ai-phase3-telephony → ai-phase4-dashboard →
ai-phase5-jev-tuning → ai-settings-readiness → ai-phases-5.5-6-7 (17 commits ahead of master, 0 behind).

After approval: merge `uat` into `master`, then on production run migrations 0016, 0017, 0018
(after a DB backup) and deploy the web service. The voice runtime / worker need their own Railway service.

## UAT environment (since 2026-10-03)

A separate Railway project, `kutumb-uat` (MySQL 9 + `kutumb-web` with an uploads volume),
at https://kutumb-uat.up.railway.app. It never touches the production project `kutumb-realty`.

- Data: a copy of the production DB and uploads, taken 2026-10-03
  (`D:\Kutumb\db-backups\prod-pre-uat-20261003.sql`). Refresh it the same way before a new round.
- Its `APP_ENCRYPTION_KEY` differs from production, so AI/Plivo secrets copied from production
  can't be decrypted there — no real AI calls or charges. There are no voice/worker services.
- Admin test login: `uat-admin` (password kept outside the repo, in `D:\Kutumb\uat-credentials.txt`).
- Deploy: `git archive uat` → LF on `*.sh` → `railway up` with the kutumb-uat project/service ids,
  run any pending migrations on its MySQL first (with `--default-character-set=utf8mb4`).
