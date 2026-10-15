# Kutumb Realty — Feature Map (QA Fortress Module 1)

Backbone for the whole test cycle — every feature here must trace to at least one `QF-<AREA>-<NNN>` in `COVERAGE.md`.

## Stack
PHP 8.2, procedural, server-rendered, no framework. PDO/MySQL (MariaDB locally via Docker). Apache. No JS bundler — vanilla JS + CDN libraries (GSAP, SplitType, Leaflet, Quill). Hosted on Railway; local dev/UAT via `docker-compose.yml` (isolated `app` + `db` containers).

## Roles
`agent` < `admin` < `owner` (rank-ordered, `includes/auth.php`). Owner-only: Manage Admins, Settings. All three: everything else in `/admin/*`. Public/anonymous: everything under the site root.

## Public entry points (16 pages + 1 XML + 5 API endpoints)
`index.php`, `properties.php`, `property.php`, `sector.php`, `blog.php`, `blog-post.php`, `team.php`, `agent.php`, `contact.php`, `compare.php`, `about.php`, `why-an-advisor.php`, `emi-calculator.php`, `map-search.php`, `saved-searches.php`, `sitemap.php`.
API: `api/enquiry-submit.php`, `api/saved-search-submit.php`, `api/filter-properties.php`, `api/map-properties.php`, `api/chatbot.php`.

## Admin entry points (37 files)
Dashboard · Properties (+gallery, +documents) · Leads/Enquiries · Follow-Ups · Saved Searches · Blog & Insights · Corridors · Team · Testimonials · Bin · Manage Admins (owner) · Settings (owner) · Auth (login/logout). Full file-level detail in `TECHNICAL_REFERENCE.md` §2.

## Data model (13 tables)
`admin_users, corridors, team_members, properties, property_images, property_documents, corridor_faqs, blog_posts, testimonials, app_settings, enquiries, saved_searches, form_submission_log, amenities, property_amenities, follow_ups`. Soft-delete (Bin): properties, blog_posts, team_members, testimonials (NOT corridors — no `deleted_at` column). Details in `TECHNICAL_REFERENCE.md` §3.

## Trust boundaries
- Anonymous visitor → must never reach any `/admin/*` action, must never see another visitor's enquiry/saved-search data, must never see soft-deleted content.
- `agent`/`admin` → must never reach Manage Admins or Settings (owner-only), must never delete/deactivate an `owner` account.
- Any admin → must never bypass CSRF on a state-changing action, must never see another admin's session hijacked via XSS/cookie theft.

## External dependencies
- **OpenAI (chatbot)** — no sandbox/test mode. Key stored in `app_settings` or config constant, masked in UI. Must mock failure modes (missing/invalid key) rather than burn real API quota per test run.
- **Google Maps embed** (contact page) — read-only iframe, no server call, no risk.
- **Leaflet/OSM** (map-search) — client-side only, no auth.
- **WhatsApp deep links** — `wa.me` URLs, no server-side call at all.
- **Railway** (prod hosting) — never touched by this test cycle.

## Known gaps (carried forward, not re-discovered — see `OPTIMIZATION_AUDIT.md`, `TECHNICAL_REFERENCE.md` §8, and the QA security sweep already completed)
- Admin login has no rate-limiting (confirmed live).
- Session cookie missing `HttpOnly`/`Secure`/`SameSite` (confirmed via `php -i`).
- `admin/admins.php` returns wrong HTTP status to non-owners (header-before-authcheck ordering bug, no data leak).
- No `.htaccess` in `uploads/` blocking PHP execution (defense-in-depth gap; live bypass attempt failed).
- No format validation beyond presence checks on several public form fields.
- Corridors have no Bin/soft-delete support.
- Leads (AJAX) and Saved Searches (full-reload) use two different inline-update patterns for the same UI job.
