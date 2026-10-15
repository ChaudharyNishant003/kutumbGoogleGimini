# AI features — deploy checklist (Railway)

Branch: `feature/ai-redesign`, merged into `master`. Nothing is pushed or deployed until you do the steps below.

## Order matters: database first, then code

The new code reads new columns (`properties.possession_text`, `properties.info_verified_on`, the `app_settings.ai_*` settings) and new tables. Run the migration **before** the new code goes live. The old code keeps working fine against the migrated database, because every change is additive.

1. **Back up the Railway MySQL database** (Railway dashboard → MySQL service → Backups, or `mysqldump`).
2. **Run the migration** `database/migrations/0014_ai_features.sql` on Railway MySQL, e.g. from the Railway MySQL "Query" tab: paste the file and run it once.
   - Check: `SHOW TABLES LIKE 'places';` returns a row, and `SELECT ai_enabled FROM app_settings;` returns `1`.
3. **Push `master` / deploy** as you normally do (`railway up` or git push).
4. **Admin → Settings:**
   - Paste the OpenAI API key (if it isn't already there; the live site has its own database, separate from local).
   - Check the AI limits: 20 per visitor per 10 minutes, 100 per visitor per day, 1,500 AI calls per day site-wide.
5. **OpenAI dashboard → Settings → Limits:** set a **monthly budget** and **email alerts**. The site-wide daily cap limits how many calls the site makes; the OpenAI budget is the final safety net on cost.
6. **Data entry** (the AI features show only what is entered here):
   - **Property locations:** Admin → Properties → Edit → "Paste Google Maps link". Properties without a location show "⚠ No location set" in the admin list. Corridors have the same box.
   - **Nearby Places:** Admin → AI Data → Nearby Places: schools, hospitals, metro, airport, malls, offices, each with a source and "verified on" date.
   - **Project facts:** Property form → "Expected possession" and "Project information verified on". Without the verified-on date, the assistant won't answer price/possession questions for that project.
   - **FAQs & Disclosures:** Property form → "FAQs & Disclosures". Disclosures are used only after "Reviewed by counsel on" is filled in. **Have your lawyer review RERA/regulatory wording first.**
7. **Smoke test on the live site:**
   - Home → type "3 BHK under 3 crore ready to move" → check the chips → "Show N matching projects".
   - A property with a location → Neighbourhood list → "View on map" → ask "How far is the airport?".
   - The same property → "Ask about this project" → "When is possession expected?" → the answer shows a source and date.
   - Admin → AI Data → AI Question Log shows those questions.

## Switching AI off

Admin → Settings → untick "AI features switched on". This takes effect immediately:
- The Home Finder falls back to a plain keyword search.
- The project assistant is hidden (the Asha chat comes back).
- The neighbourhood list still shows, without its question box.
- Every AI endpoint returns a polite "switched off" message.

## Rolling back

- **Code:** revert the merge commit on `master` (`git revert -m 1 <merge-commit>`) and redeploy.
- **Database:** no rollback needed. The old code ignores the new tables and columns. To remove them completely, drop `places`, `property_faqs`, `property_disclosures`, `ai_rate_limits`, `ai_query_log`, and the new columns listed in the migration.

## Running the tests

- **Unit tests (PHP, no OpenAI calls):**
  `docker exec -i kutumb-app-1 php /var/www/html/tests/backend/ai-unit.php`
- **End-to-end:** `npx playwright test tests/e2e/ai-features.spec.js`. The AI responses are mocked, so this run makes no OpenAI calls.
