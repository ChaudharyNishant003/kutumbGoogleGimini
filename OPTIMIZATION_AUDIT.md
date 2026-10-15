# Kutumb Realty — Optimization Audit & Planning Brief

**Purpose of this file:** a ground-truth snapshot of where the current codebase is inefficient, duplicated, or will not scale — written to be pasted into a fresh AI chat (Claude, ChatGPT, etc.) so that session can help plan optimization work from real facts instead of guessing from a code excerpt. Companion to `TECHNICAL_REFERENCE.md` (general architecture reference) and the "Kutumb Design Reference" document (visual/UX system) — this file is scoped to **performance, database efficiency, and code duplication only**.

**Suggested opening prompt to pair with this file:**
> "This is a performance/code-structure audit of my real estate PHP site (Kutumb). Read it, then help me turn it into a prioritized optimization plan — cheap/high-impact fixes first, bigger structural changes after. Ask me before assuming which ones I want to do."

**Snapshot date:** September 2026. **Current data scale** (matters a lot for judging urgency below): 9 properties, 6 corridors, 3 blog posts, 3 testimonials, low four-figure page views. Several findings below are "fine today, will bite at 10x scale" — treat those as roadmap items, not fires.

---

## 1. Database & Query Efficiency

### 1.1 The `corridors` table is queried 2–3 times per single page view, unmemoized
Three separate, independent queries against the same small table run on one page load, with no caching between them:
- `includes/nav.php:7` — `SELECT name, slug, avg_price_band FROM corridors WHERE deleted_at IS NULL ORDER BY name` — runs via the corridor ticker, which is included on **every public page** (nav.php is pulled in by header.php).
- `includes/footer.php:41` — a second, different-column query for the footer's corridor links — also on **every public page**.
- `index.php:6` — a third query (`SELECT *`) for the homepage's own search dropdown and corridor-journey strip.

None of these reuse each other's result even when they run in the same request (nav.php and footer.php don't share a variable name or a cache). Cheapest real fix in the codebase: one `get_active_corridors()` helper in `functions.php` with a static-variable, request-scoped cache, used by all three call sites. Removes 1–2 redundant queries from **every single page view, sitewide**, for free.

### 1.2 `property.php` makes up to 7 sequential round-trips per page view
Lines 9, 26, 28, 32, 36, 43, 48: property+corridor join → `view_count` UPDATE → related properties → gallery images → documents → agent lookup → amenities join. None of these are looped (not classic N+1), but it's seven serial queries before the page can finish rendering. Candidates worth profiling: combining amenities+images (always needed together) into fewer round-trips, or fetching them concurrently rather than serially.

### 1.3 `SELECT *` used throughout instead of named columns
`property.php:9, 28, 32, 36, 43`; `functions.php:271` (`bin_purge_item`). Harmless at current row widths, but means every future column addition silently grows the payload/memory of pages that don't need the new column, and makes it hard to see what a page actually depends on at a glance.

### 1.4 Unbounded queries that will not stay cheap
- `index.php:7` — `SELECT * FROM properties WHERE deleted_at IS NULL ORDER BY featured DESC, id ASC` has **no LIMIT**. Fine at 9 rows; will render the entire catalog on the homepage once inventory grows.
- `blog.php` loads every published post with no pagination (confirmed in the earlier full-site scan). Fine at 3 posts, not at 300.
- `sitemap.php` re-queries every content table from scratch on every single request, uncached.

### 1.5 No indexes beyond primary/foreign keys on actively-filtered columns
`schema.sql` defines FK indexes automatically (InnoDB) but nothing explicit on: `properties.category`, `properties.featured`, `properties.deleted_at`, `properties.price_min` (all used in `properties.php`'s filter form); `blog_posts.status` / `published_at`; `enquiries.status`; `saved_searches.status` (both used by admin filter dropdowns). Irrelevant at today's row counts — a full scan of 50 rows is instant — but exactly the kind of thing that turns admin list pages sluggish once leads/listings grow into the thousands. Cheap to add now, expensive to diagnose later.

---

## 2. Asset & Media Performance

### 2.1 No image processing pipeline on upload — the single biggest real page-weight cost on the site
`includes/functions.php:111`, `handle_file_upload()`: validates real MIME type via `finfo` and renames the file — it never resizes or re-compresses anything. Whatever a phone camera produces (up to the 5MB cap set in `config.php`) is exactly what gets served everywhere that photo appears: the full property hero, every listing card, *and* a 100×74px gallery thumbnail. This one gap almost certainly outweighs every other performance item in this document combined.

**Fix direction:** generate 1–2 resized derivatives at upload time (e.g. ~1600px "full" + ~400px "card") using GD or Imagick, store both, and point cards/thumbnails at the small one. Needs `docker-php-ext-install gd` added to the `Dockerfile` (currently only installs `pdo pdo_mysql mysqli curl`).

### 2.2 Every public page loads the full JS bundle, regardless of need
`includes/footer.php:91–100` unconditionally loads GSAP, SplitType, `hero-canvas.js`, `chatbot.js`, `compare.js`, `emi-calculator.js`, `gallery.js`, and `premium-ui.js` on **every** public page. `hero-canvas.js` only does anything on `index.php`; `emi-calculator.js` only matters on `emi-calculator.php`; `gallery.js` only matters on pages with a photo gallery. These are already placed at the end of `<body>` so they don't block first paint, but it's still real unused bytes and parse time on every page that isn't the one that needs them.

**Fix direction:** a `$page_scripts` array set before `include footer.php`, so footer.php loops a per-page list instead of a fixed one.

### 2.3 No HTTP caching or compression configured for static assets
`.htaccess` (14 lines total) only denies `.sql`/`.ini` access and disables directory listing — there is no `Cache-Control`/`Expires` header for `/assets/` (CSS/JS) or `/uploads/` (images), and neither `.htaccess` nor the `Dockerfile` enables `mod_deflate`/`mod_headers` for compression. Every returning visitor re-downloads `style.css`, `admin.css`, and every JS file on every single visit.

**Fix direction:** add a `<FilesMatch>` block with a year-long `Cache-Control: public, max-age=31536000, immutable` for assets/uploads, and add `deflate` to the `a2enmod rewrite headers` line already in the `Dockerfile`.

---

## 3. Code Duplication / Structure

### 3.1 Two admin card shells exist — only one is actually shared
`includes/admin-card.php`'s `render_admin_card()` is reused by Properties, Blog, Corridors, Team, and Testimonials. But Leads, Follow-Ups, Saved Searches, and Manage Admins each hand-roll their own near-identical `.admin-data-card` markup inline, one copy per file (confirmed in the earlier full-site scan). A second `render_admin_data_card()` helper, mirroring the existing pattern, would remove roughly four files' worth of duplicated markup.

### 3.2 Leads and Saved Searches solve the same UI problem two different ways
`admin/enquiry-update.php` saves the Status/Assignee dropdowns via AJAX with no page reload. `admin/saved-search-update.php` does the identical job via a full-page POST reload. Not a performance bug, but a real inconsistency — worth picking one pattern and applying it to both.

### 3.3 The three public lead forms share validation logic but not the sequence around it
`/api/enquiry-submit.php` and `/api/saved-search-submit.php` (and the public contact/saved-search forms behind them) each call the same `honeypot_tripped()` → `rate_limit_check()` → `validate_*()` → insert sequence from `functions.php`, but that *sequence* is copy-pasted per endpoint rather than wrapped in one shared helper that takes a table name and field map.

---

## 4. Data-Integrity Notes (the numbers you'd optimize against)

### 4.1 View counters have no dedup
`property.php:26` (and the equivalent for `corridors.view_count` / `blog_posts.view_count`) increments on **every** page load — the owner's own repeat visits, admin previews while editing content, and any crawler, all count. The Dashboard's "Top Properties / Top Corridors / Top Posts by views" widgets are built directly on these numbers. Right now they're a raw hit-counter, not a real popularity signal — worth session/cookie-deduping before trusting them to justify any content or performance decision (e.g. "deprioritize the least-viewed listing").

---

## 5. Scalability Watch-list (fine today, will not stay fine)

- `blog.php` — no pagination once post count grows past a couple dozen.
- `sitemap.php` — rebuilt from live queries on every request; will want caching once content volume grows.
- `bin_purge_expired()` (`functions.php:300`) scans all 5 bin-eligible tables on every single Bin page load, then purges matched rows one at a time (`functions.php:265`) rather than batching — negligible at current volume, will show up in query counts as deleted-item history grows.
- No explicit DB connection pooling/persistence (`includes/db.php` uses a plain per-request PDO connection) — standard for PHP-per-request hosting like this Apache/mod_php setup, but worth knowing if traffic ever justifies a different hosting model (PHP-FPM + connection pool, or a managed DB proxy).

---

## 6. Suggested Prioritization

| # | Fix | Impact | Effort |
|---|---|---|---|
| 2.3 | Cache/compression headers for static assets | High, sitewide, zero app-code risk | S |
| 1.1 | Dedupe the repeated `corridors` query | Small but literally free | S |
| 2.1 | Image resize/compress pipeline on upload | Highest real-world page-weight win | M |
| 2.2 | Conditional per-page script loading | Medium, cuts unused JS everywhere | S–M |
| 3.1 | Shared `render_admin_data_card()` partial | Maintainability, not raw speed | M |
| 4.1 | Dedup view-count increments | Fixes the data you'd plan against | S |
| 1.5 | Add indexes on filtered columns | Zero cost today, prevents a future cliff | S |
| 1.2 | Reduce/parallelize property.php's 7 queries | Modest today, matters under real traffic | M |
| 1.4 | Add LIMIT/pagination to `index.php`, `blog.php` | Prevents future full-table page loads | S |

*(S = under a day, M = a focused day or two, based on this codebase's size — not a formal estimate.)*

---

## 7. Explicitly Out of Scope Here

- **Visual/UX redesign** — see the separate "Kutumb Design Reference" document (tokens, components, page-by-page breakdown, business flows) from this same project.
- **Security review** — form validation depth, rate-limiting robustness, auth hardening, and similar are not covered here; ask for a dedicated security pass if that's wanted next.
- **Framework/build-tooling migration** — this audit assumes the current no-framework, no-build-step, CDN-only constraints stay in place (see `TECHNICAL_REFERENCE.md` §1). A rewrite onto a framework is a different, much larger conversation than "optimize the current code."
