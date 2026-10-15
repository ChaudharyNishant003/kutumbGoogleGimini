# Release and rollback process

Every production release follows the same steps, so any release can be undone.

## Branches
- `feature/*`: one branch per piece of work. Never commit straight to `master`.
- `uat`: everything waiting for approval, merged together. Test only this branch.
- `master`: only what is (or is about to be) on production. Changes arrive only as a
  `--no-ff` merge of `uat`, so one release = one merge commit.

## Before a release
1. Production DB backup → `D:\Kutumb\db-backups\prod-pre-release-<YYYYMMDD>.sql`
   (`railway ssh --service MySQL -- 'mysqldump ... --single-transaction'`).
2. Tag the current master: `rollback/pre-release-<YYYY-MM-DD>` (message names the Railway
   deployment id that was live and the backup file).
3. Test the migrations on a copy of that backup, in the same MySQL version as production
   (production is MySQL 9.4; local dev is MariaDB).
4. Build the release image from a clean `git archive master` export (LF line endings on `*.sh`)
   and smoke-test it against the migrated copy.

## Release
1. `git merge --no-ff uat` into master, with a NAME / WHAT IT DOES / WHAT'S INCLUDED message.
2. Tag it `release/<YYYY-MM-DD>` and push master + tags.
3. Run the pending migrations on production (all migrations are additive: new nullable columns
   and new tables only, so the previous code keeps working on the migrated DB).
4. `railway up` from the clean export. Record the new deployment id in the release notes.
5. One smoke test of the new changes on production.

## Rollback
- **Code:** `railway redeploy`/rollback to the deployment id in the `rollback/*` tag message
  (fastest), or `git revert -m 1 <release merge commit>` and deploy that.
- **Database:** migrations are additive, so the old code runs on the new schema; normally no DB
  rollback is needed. If data itself is damaged, restore the pre-release backup.
- `schema_migrations` records which migration files production has applied.

## Log of releases
| Date | Merge commit | Rollback tag | Backup | Railway deploy before |
|---|---|---|---|---|
| 2026-09-30 | release/2026-09-30 | rollback/pre-release-2026-09-30 | prod-pre-release-20260930.sql | bd3ecc15 |
| 2026-10-04 | release/2026-10-04 | rollback/pre-release-2026-10-04 | prod-pre-release-20261004-final.sql | web 204cd079, voice 8ba45c84 |
