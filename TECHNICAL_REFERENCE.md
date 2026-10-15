# Kutumb Realty — Technical Reference (Build Register)

**Purpose of this file:** ground truth about the actual current state of the codebase, meant to be handed to any AI (Claude, ChatGPT, etc.) that will write a bug report, enhancement request, or implementation plan for this project — so that document is accurate instead of guessed from memory of a conversation summary.

**How to use this file:** paste it or upload it alongside any bug/enhancement document you're asking another AI to write. Update it whenever a meaningful chunk of work lands (new feature, schema change, fixed bug) — see "Keeping this updated" at the bottom.

**Live site:** https://kutumb-web-production.up.railway.app (admin at `/admin/login.php`)
**Repo:** https://github.com/ChaudharyNishant003/Kutumb

---

## 1. Tech Stack

- **No frontend framework.** Plain PHP 8.2, procedural, server-rendered HTML (`.php` files mixing PHP and markup directly). No React/Vue/Next.js.
- **No CSS framework.** Hand-written CSS with a CSS custom-properties design-token system. No Tailwind, no CSS Modules, no Sass.
- **No JS framework, no bundler, no npm build step.** Vanilla JS loaded via plain `<script src="...">` tags. Any new JS library must be CDN-loadable with no build step (this constraint bit a prior "add a rich text editor" task — Quill.js or TinyMCE via CDN `<script>` tag is the intended approach).
- **Database:** MySQL (Railway-hosted), MariaDB for local dev. PDO for all DB access via a single `db()` singleton in `includes/db.php`.
- **Hosting:** Railway, custom Dockerfile (`php:8.2-apache`). Deploys via `railway up`, not a GitHub-connected auto-deploy.

## 2. Folder / File Structure

**Root (`/`)** — public site, one file per page: `index.php`, `properties.php`, `property.php`, `sector.php`, `blog.php`, `blog-post.php`, `team.php`, `agent.php`, `contact.php`, `compare.php`, `about.php`, `why-an-advisor.php`, `emi-calculator.php`, `map-search.php`, `saved-searches.php` (public "create a saved search" form — different from `admin/saved-searches.php`, the admin list), `sitemap.php`.

**`admin/`** — one file per admin page/action:

| Feature | List page | Add/Edit form | Delete/action files |
|---|---|---|---|
| Dashboard | `dashboard.php` (counters + merged analytics charts) | — | `analytics.php` is now just a redirect to `dashboard.php`, not a real page |
| Properties | `properties.php` | `property-form.php` | `delete-property.php`, `property-gallery.php` (photos), `property-documents.php` (docs), `delete-property-image.php`, `delete-property-document.php` |
| Leads | `enquiries.php` | — (inline edit) | `enquiry-update.php` (AJAX status/assignee update) |
| Follow-Ups | `follow-ups.php` | — | `follow-up-add.php`, `follow-up-complete.php` |
| Saved Searches | `saved-searches.php` | — | `saved-search-update.php` |
| Blog/Insights | `blog-list.php` | `blog-form.php` | `delete-blog-post.php` |
| Corridors | `corridors.php` | `corridor-form.php` | **no delete file exists yet** |
| Team | `team.php` | `team-form.php` | `delete-team-member.php` |
| Testimonials | `testimonials.php` | `testimonial-form.php` | `delete-testimonial.php` |
| Bin | `bin.php` | — | `bin-action.php` (restore/purge) |
| Manage Admin | `admins.php` | `admin-form.php` | `delete-admin.php`, `deactivate-admin.php` |
| Settings | `settings.php` (OpenAI key only, in-place save, no redirect) | | |
| Auth | `login.php`, `logout.php` | | |

**`includes/`** (shared PHP, not pages):
- `admin-header.php` / `admin-footer.php` — admin shell (sidebar, mobile hamburger, page wrapper)
- `header.php` / `footer.php` — public site shell; `nav.php` — public nav bar
- **`property-card.php`, `blog-card.php` — shared, reusable card partials for the PUBLIC site only.** Included in a loop by `index.php`, `properties.php`, `property.php`, `sector.php`, `agent.php`, `blog.php`, `blog-post.php`, `why-an-advisor.php`, `api/filter-properties.php`.
- **The admin panel has NO equivalent shared card component.** Every admin list page has its own inline `<table class="admin-table">` markup (becomes a stacked mobile "card" via CSS, not a PHP partial).
- `db.php` (`db()` PDO singleton), `auth.php` (`current_admin()`, `require_admin()`, `require_role()`, `ADMIN_ROLE_RANK`), `functions.php` (validation helpers, file upload, Bin helpers, pagination — see below), `leads.php` (lead/saved-search query helpers), `structured-data.php`, `error-page.php`, `compare-bar.php`.

**`assets/css/`**: `style.css` (public site + all design tokens — admin.css reuses these), `admin.css` (admin-only layout).
**`assets/js/`**: `main.js` (public interactions), `admin.js` (sidebar toggle + form-note→toast conversion), `toast.js` (the toast widget itself, shared both sides), `premium-ui.js` (public site polish).

**`database/`**: `schema.sql`, `seed.sql`. **`config/`**: `config.php` (local, gitignored), `config.sample.php`, `config.railway.php` (prod, copied to `config.php` at Docker build time).

## 3. Database Schema — Admin-Relevant Tables

Columns actually present per table (this is the part most likely to be guessed wrong by an AI that hasn't read the schema):

| Table | Has `created_at`? | Has `deleted_at`? (soft-delete/Bin support) | Notable columns |
|---|---|---|---|
| `properties` | ✅ | ✅ | `slug` unique **with random suffix** appended on create (never collides) |
| `blog_posts` | ✅ `created_at` + `updated_at` | ✅ | `slug` unique with random suffix |
| `team_members` | ✅ `created_at` only | ✅ | `slug` unique with random suffix |
| `testimonials` | ✅ | ✅ | no `slug` column at all |
| `corridors` | ❌ **missing** | ❌ **missing** | `slug` unique, **plain `slugify($name)`, NO random suffix** — duplicate names collide (see §7, already fixed to show a friendly error instead of crashing) |
| `admin_users` | ✅ | n/a (hard delete, with owner-protection rule — see §7) | `role` enum: `owner`/`admin`/`agent` |
| `enquiries` (Leads) | ✅ | n/a | `status` enum: `new`/`contacted`/`qualified`/`converted`/`lost` |
| `saved_searches` | ✅ | n/a | `status` enum: `new`/`contacted`/`matched`/`closed` |
| `follow_ups` | ✅ | n/a | Points to **either** `enquiry_id` **or** `saved_search_id`, never both (DB-level CHECK constraint) |
| `property_images`, `property_documents` | ✅ | n/a (cascade-deleted with parent property) | |
| `corridor_faqs` | n/a | n/a | fully replaced (delete-all-then-reinsert) on every corridor save |
| `amenities`, `property_amenities` | n/a | n/a | many-to-many join table |

**Bin/soft-delete system** (`includes/functions.php`, `bin_config()` function): currently covers exactly 4 types — `property`, `blog_post`, `team_member`, `testimonial`. **Corridors are not yet in the Bin system** (no `deleted_at` column, no `bin_config()` entry, no `delete-corridor.php`). Auto-purges anything past 15 days, checked opportunistically on every `admin/bin.php` page load (no cron available on this host).

## 4. Design Tokens

**`assets/css/style.css`**, top of file, `:root { ... }` — single source of truth, reused by `assets/css/admin.css`:

```
--navy: #002366          --orange: #ff9909
--navy-deep: #011438      --orange-deep: #d97e00
--navy-mid: #12336f       --orange-glow: rgba(255, 153, 9, 0.35)
--navy-950: #04081c
--ink: #16191f            --paper: #fbfaf6
--ink-soft: #4a4f5a        --paper-dim: #f3f1ea
--line: #e4e1d6            --white: #ffffff
--font-body: 'Jost', 'Segoe UI', system-ui, sans-serif
--font-display: 'Newsreader', 'Georgia', serif
--radius-sm: 7px / --radius-md: 12px / --radius-lg: 24px
--shadow-card, --shadow-lift (layered navy-tinted shadows)
--ease: cubic-bezier(0.16, 1, 0.3, 1)
```

Button classes in `style.css`: `.btn-primary` (orange bg), `.btn-navy` (navy bg), `.btn-outline` (**transparent bg, white text/border — designed for dark backgrounds like the hero section; looks nearly invisible if used on a light admin card**), `.btn-soft` (paper-dim bg, navy text).

## 5. Forms — Current Pattern (as of the last fix)

- **Structure**: every `*-form.php` requires `auth.php` + `functions.php` + `db.php` + calls `require_admin()` **first**, does all POST-handling/validation/redirect, and only requires `admin-header.php` (which prints HTML) **after** that — this ordering was a real bug fixed recently (redirects were silently failing in production because HTML had already started printing before `header('Location: ...')` ran). Any new form must follow this same order.
- **Two submit buttons** on every add/edit form: `name="action" value="close"` (save → back to list) and `name="action" value="new"` (save → blank form again, for adding the next item immediately). Both show a success message.
- **Validation**: server-side only, inline per field (`if ($x === '') $errors[] = '...'`). No client-side JS validation library. No dedicated `validate_name()`/`validate_phone()`/`validate_email()` helpers yet — each form writes its own ad hoc checks.
- **File uploads**: `includes/functions.php` → `handle_file_upload($file, $destDir, $allowedMimes, $maxBytes, &$error)`. **Already validates real file content-type via PHP's `finfo`, not just the extension** — this is done correctly already, don't re-flag it as missing.
- **Messages/toasts**: server renders `<div class="form-note success">...</div>` or `.error`; `assets/js/admin.js` runs on every admin page load, finds any `.form-note`, calls `window.kutumbToast(text, type)` (defined in `assets/js/toast.js`) with its **plain `textContent`**, then removes the div. **This means any HTML inside a `.form-note` (like a link) gets flattened to plain dead text once converted to a toast** — a real, known issue affecting the "Undo" link on delete confirmations.
- Toasts today are **generic/top-of-page only** — not anchored to a specific card/row.

## 6. Pagination

- `includes/functions.php`: `paginate_params($totalRows, $defaultPerPage = 10, $perPageOptions = [5,10,25,50])` reads `page`/`per_page` from `$_GET` (validated against the allowlist), returns page/offset/total_pages. `render_pagination($pg, $basePath)` renders prev/next/page-numbers + a per-page `<select>`.
- **Only wired into `admin/properties.php` so far.** To reuse elsewhere: add a `COUNT(*)` query, call `paginate_params()`, add `LIMIT/OFFSET` to the list query, call `render_pagination()` — no new plumbing needed, it's already generic.
- CSS: `.pagination`, `.pagination-controls`, `.page-btn`, `.pagination-perpage` in `assets/css/admin.css`.

## 7. Known-Good Patterns Worth Copying

- **Testimonials' delete flow** (`admin/testimonials.php` + `delete-testimonial.php`) is the reference implementation for "soft-delete to Bin" — copy this exact pattern for any new entity type.
- **Corridor duplicate-name handling** (`admin/corridor-form.php`) is the reference implementation for "check for a conflict before insert, show a friendly form error instead of letting the DB throw."
- **Owner-protection rule** (`admin/delete-admin.php`): server-side check that no admin, Owner included, can ever delete another Owner account. Mirror this pattern if any other "protected record" rule is ever needed.
- **Leads auto-save** (`admin/enquiry-update.php` + JS in `assets/js/admin.js`): the one place that already does AJAX-based inline update without a full page reload — reference for any future "convert this to AJAX" work (e.g. animated delete).

## 8. Explicitly NOT Present (don't assume these exist)

- No follow-up/reminder feature on Blog/Insights, Properties, Team, Testimonials, or Corridors — **only Leads and Saved Searches** have follow-ups.
- No shared admin card/list-item component (see §2).
- No rich text editor on the Blog body field — still a raw `<textarea>` expecting hand-typed HTML (sanitized server-side via `sanitize_post_body()` in `functions.php`, which currently allows: `p, br, h2-h4, ul, ol, li, a, strong, b, em, i, u, blockquote, img, figure, figcaption`, plus a `style="text-align: ..."` attribute on block tags — this allowlist was already extended once in anticipation of a rich text editor, so underline and alignment will survive sanitization once a toolbar is added).
- No rate-limiting or CAPTCHA on any public-facing form (contact, saved search creation).
- No name/phone/email format validation helpers — only presence checks.
- Corridors have no delete/Bin support, no `created_at`, no `deleted_at` (see §3).

## 9. Local Dev/UAT Environment (Docker)

- **Start:** `docker compose up -d` from this folder. **Stop:** `docker compose down` (data persists in a named volume; `down -v` would wipe it).
- Site: http://localhost:8000 — Admin: http://localhost:8000/admin/login.php
- Two isolated containers, own network/volumes (Compose project name `kutumb`, so this never shares state with any other local project): `app` (built from the existing `Dockerfile`) and `db` (`mariadb:10.11`, matching the version this project used natively). The DB port is not exposed to Windows — only `app` can reach it.
- `app`'s config comes from `config/config.railway.php` (same file used in production), fed by environment variables set in `docker-compose.yml` pointing `DB_HOST` at the `db` service instead of Railway. No separate local config file needed.
- Credentials live in `.env` (gitignored, not committed) — copy the pattern there if credentials ever need rotating.
- First boot restores `database/local_backup_20260906.sql` (a full dump of the real local data — 9 properties, 6 corridors, 3 blog posts, 3 testimonials — migrated 2026-09-06 from the old native devstack setup) into the `db` container's persistent volume. That file is gitignored (contains real lead/contact data); subsequent restarts reuse the volume and skip re-importing it.
- The old native local stack (`C:\Users\Nishant\devstack` — standalone MariaDB/PHP/Node binaries, `start-local-server.bat`) has been retired in favor of this Docker setup; the folder itself is left on disk untouched as a backup, not deleted.
- **Required workflow:** build/fix locally → test against this Docker stack → report pass/fail in plain terms → get explicit go-ahead → only then push to GitHub / redeploy to Railway. No step in this local setup pushes to Git or Railway on its own.

## 9a. AI Calling — telephony runtime (Phase 3)

- **Services** (all built from `./voice`): `voice` (FastAPI: `/simulate`, Plivo webhooks `/telephony/plivo/{answer,hangup,amd,recording,transfer,transfer-status}`, audio websocket `/ws/plivo/{attempt_id}`, `/health/component?name=`), `worker` (`python -m kutumb_voice.worker` — job loop over `ai_jobs`), `watchdog` (`python -m kutumb_voice.watchdog`; Railway cron `*/5`, locally `--loop 300`). All three in `docker-compose.yml`; **none deployed to Railway yet**.
- **Clocks — important:** the DB server runs **UTC** (`NOW()`), PHP runs **Asia/Kolkata**. `follow_ups.due_at` (written by PHP) is **IST wall-clock**; `ai_*` timestamps, `enquiries.created_at/inquiry_at` and `ai_jobs.run_at` are **DB clock (UTC)**. Python converts with `scheduler.ist_to_db()`. Anything new that writes `enquiries.ai_paused_until` must use the DB clock (`NOW() + INTERVAL …`).
- **Safety gates:** `sandbox_mode` is locked ON (only `sandbox_allowlist` numbers are dialed; unknown inbound callers go to `transfer_fallback_number`, never the AI). Eligibility is re-checked at fire time (`voice/kutumb_voice/eligibility.py`); compliance failures close the call as `blocked_compliance`, operational ones (window, caps, kill switch, pause) only postpone it. No call is dialed unless Plivo + STT + TTS keys and `runtime_public_url` are all set. Only an **active** `new_lead_qualification` campaign creates first-contact calls (the seed campaign is `draft`).
- **Webhook auth:** Plivo V3 signature, verified against `runtime_public_url` + path + query (not the proxied internal URL). The websocket uses our own 10-minute HMAC token.
- **PHP safety net:** `includes/ai_enqueue.php::ai_opportunistic_enqueue()` runs on `admin/dashboard.php` load and queues the periodic scans if none were queued in 10 minutes.
- **Migrations:** `0016_ai_pause.sql` (`enquiries.ai_paused_until`), `0017_ai_telephony.sql` (`ai_jobs.status` gains `canceled` — without it `record_opt_out` failed under strict SQL mode once a job was pending; facts→segments FK is now `ON DELETE SET NULL` so the 180-day transcript purge can run).

## 9b. AI Calling — admin dashboard (Phase 4)

- **Pages** (`admin/ai/`): `index.php` Overview (today's KPIs, live calls, component health, kill switch), `queue.php` (waiting calls + campaigns), `calls.php` + `call.php?id=` (call log / detail: attempts, recording, transcript, fact review, held-tool approvals, decision labels, events, cost), `followups.php`, `agent.php` (versions: clone → edit → activate; the active version is read-only), `compliance.php` (go-live checklist, the **only** sandbox-off switch — server-enforced, needs every item ticked + typing GO LIVE), `analytics.php`, `tuning.php` (labels per decision). Plus `admin/enquiry-view.php?id=` (single lead page) with the `admin/ai/_lead_timeline.php` partial. Overview/Queue/Calls/Follow-ups need `admin`; Agent/Compliance/Analytics/Tuning need `owner`; agents only see leads assigned to them.
- **Shared code:** `includes/ai_admin.php` (display helpers, compliance items, DPDP erasure, call-now, fact apply). All admin POSTs go to `admin/api/ai-actions.php` (CSRF + per-action role + `ai_audit_events` + flash + redirect back). Recordings stream through `admin/api/ai-recording.php` with a 10-minute signed link.
- **Showing times:** always render `ai_*` / `enquiries.created_at` timestamps with `ai_time()` (it measures the DB↔PHP clock offset per request); read datetime-local inputs with `ai_ist_to_db()`. `follow_ups.due_at` is already IST — use `date()` directly.
- **Decision config:** `ai_agent_versions.decision_config_json` holds `{mode, threshold}` per D1–D24; the voice runtime reads it through `voice/kutumb_voice/decision_registry.py` (spec defaults when blank) and logs `mode`, `threshold_used`, `turn_seq` and the caller's words per decision. See §9c for how each decision is used.
- **Wide tables:** AI pages get `body.ai-page`; `.admin-main { min-width: 0 }` + `.ai-page .admin-card { overflow-x: auto }` keep wide tables scrolling inside their card instead of widening the page.
- **Migration:** `0018_ai_review.sql` (fact `review_status`/`reviewed_by`/`reviewed_at`; tool-call `review_decision`/`reviewed_at`).

## 9c. AI Calling — decisions D1–D24 + tuning (Phase 5)

- **Engine:** `voice/kutumb_voice/decisions/engine.py` asks the rules and Jev in parallel each turn (questions in `decisions/catalog.py`). Shadow = act on the rule answer, log Jev's; live = act on Jev's; D1–D3 always take the stronger of the two. With Jev off, rules answer everything (`adapters/decision/rule_llm_fallback.py`).
- **Where each decision runs:** D1–D18 in `turn_controller.py` (per turn); D19–D22 in `post_call.py` (worker `post_call` job writes to the lead; `/simulate` only logs); D23 in the Plivo AMD webhook; D24 in `live_call.py` on a language change. D5/D9/D23/D24 are code-only.
- **D15 write gate:** every CRM write (except opt-out and reads) is gated; the runtime sends `gate` with the tool call. `admin/api/ai-tools.php` stores a `needs_human` write as a held invocation (HTTP 202, nothing executed) that an admin approves on the call page. "Qualified" from post-call is always proposed through the gate.
- **Times from speech:** `time_extraction.parse_spoken_datetime` (aaj/kal/parso/weekday + "N baje" + subah/shaam) first, the LLM only as a fallback; callbacks must fall inside the calling window and the 7-day consent window (otherwise a staff task), visits 10:00–18:00 within 60 days.
- **Tuning:** `admin/ai/tuning.php` sweeps 0.50–0.95 over human labels, recommends a threshold and applies it to the active agent version (audited). Shadow → live is refused server-side below 150 labels or when the target (D1 recall ≥ 99%, D15 precision ≥ 98%, D13 ≥ 95%, others accuracy ≥ 90%) isn't met at the saved threshold. Label choices come from `ai_decision_label_options()`.

## 10. Keeping This Updated

Whenever a meaningful round of work lands: update the relevant section above (new column → §3 table, new shared pattern → §7, newly-implemented "not present" item → move it out of §8, etc.). Treat this the same way `PROJECT_HISTORY.md` is treated (a living doc updated after each round of work) — except this one is written for an AI/technical audience, not a plain-language changelog for the client.
