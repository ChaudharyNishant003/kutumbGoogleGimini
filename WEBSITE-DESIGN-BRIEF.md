# Kutumb Realty — Website Design Brief

**Scope: public website only.** The admin panel is deliberately out of scope for this document — nothing in here describes or governs `/admin/*`.

**Purpose:** this is the input brief for redesigning the public site with Claude Design (canvas). It carries everything a design pass needs — exact colour values, type scale, every page's section order, component specs, and real content to fill mockups with instead of lorem ipsum.

**Keep this file current.** Any public-site design change from here on gets recorded here. Admin-side changes do not belong in this file.

**Direction agreed for this pass (revised):** navy stays, but the accent and type system moved from orange/Newsreader/Jost to a warmer gold accent with a Playfair Display/Inter pairing — set by the first approved canvas artboard (Home — Desktop) and confirmed with the site owner. Layout, hierarchy, spacing and polish continue to be refined on top of that.

**Last verified against code:** 15 September 2026 (`assets/css/style.css`, `index.php`, and the live local database — homepage redesign implemented against this revision).

---

## 1. Design system

Single source of truth: the `:root` block at the top of `assets/css/style.css`. Everything below is read from there, not guessed.

### 1.1 Colour

| Token | Hex | Where it's used |
|---|---|---|
| `--navy` | `#002366` | Links, active search tab, `.btn-navy`, card CTA text |
| `--navy-deep` | `#011438` | All headings, dark section backgrounds, footer gradient start, text on gold buttons |
| `--navy-mid` | `#12336f` | Reserved mid-tone (declared, barely used — available) |
| `--navy-950` | `#04081c` | Deepest ground: corridor ticker bar, footer gradient end, chat panel header, load curtain |
| `--gold` | `#c9a154` | Primary CTA fill, featured badge, eyebrow counters, chat toggle, cursor accent, focus glow |
| `--gold-deep` | `#8a6f38` | Hover state for gold, eyebrow text, link hover |
| `--gold-glow` | `rgba(201,161,84,0.35)` | 4px focus ring on form fields |
| `--ink` | `#16191f` | Primary body text |
| `--ink-soft` | `#4a4f5a` | Paragraphs, meta text, labels |
| `--paper` | `#fbfaf6` | Page background — warm off-white, never pure white |
| `--paper-dim` | `#f3f1ea` | Alternating section backgrounds, `.btn-soft`, document rows, bot chat bubbles |
| `--line` | `#e4e1d6` | Every hairline border and divider |
| `--white` | `#ffffff` | Card surfaces |

`--orange`/`--orange-deep`/`--orange-glow` still exist in `style.css` as aliases pointing at the gold tokens, purely so out-of-scope stylesheets (`admin.css`) and pages (About, Team, Why an Advisor) that haven't been redesigned yet don't break. New work should reference `--gold*`, never `--orange*`.

**Semantic colours** (state only, used sparingly): success `#1c6e2f` on `#ebfcea`, error `#a34a00` on `#fff1e0`, WhatsApp-link green `#1F7A44` on `#E9F5EE`.

The palette's discipline is worth preserving: two brand anchors, warm neutrals rather than grey, and three navy depths so dark sections have somewhere to sit without touching pure black.

### 1.2 Typography

Two families, loaded from Google Fonts, with a strict job split:

- **Playfair Display** (serif) — `--font-display`. Every heading, every price, testimonial quote body, stat numbers, the logo. This is what makes a ₹3.75 Cr listing read as an institution rather than a classified ad.
- **Inter** (geometric sans) — `--font-body`. Everything read as a sentence: nav, paragraphs, labels, buttons, form fields, meta lines.

| Role | Size | Notes |
|---|---|---|
| Hero H1 | `clamp(40px, 6vw, 76px)` | Newsreader, white on dark, `-0.01em` tracking |
| Page hero H1 | `clamp(30px, 4vw, 46px)` | Newsreader, white on navy gradient |
| Section H2 | `clamp(28px, 3.4vw, 38px)` | Newsreader, max-width 560px |
| Card title H3 | 20px | Newsreader |
| Card price | 23px | Newsreader, navy-deep |
| Stat number | 40px | Newsreader |
| Hero lede | 18px | Jost, 78% white |
| Body | 16px / 1.65 | Jost, ink-soft |
| Nav link | 15px | Jost |
| Button | 15px / 500 | Jost |
| Meta, pills | 13px | Jost, ink-soft |
| Eyebrow | 12px / 500 | Jost, uppercase, `0.16em` tracking, orange-deep |

**Eyebrow numbering:** section eyebrows auto-number via a CSS counter — `01 — CURATED LISTINGS`, `02 — …`. This is a signature detail; it encodes reading order, not decoration.

### 1.3 Geometry, elevation, motion

- **Radii:** `7px` (buttons, inputs, thumbs), `12px` (panels, map, FAQ items), `24px` (property/testimonial cards, search panel, info cards). Pills use `999px`.
- **Container:** `1240px` max-width, `24px` side padding.
- **Section rhythm:** `88px` vertical padding, dropping to `56px` at ≤720px.
- **Shadows** — navy-tinted, never flat black. This is called out in the source as the single highest-leverage detail in the system:
  - `--shadow-card`: `0 1px 2px rgba(1,20,56,.06), 0 3px 10px rgba(1,20,56,.07), 0 24px 48px -20px rgba(1,20,56,.32)`
  - `--shadow-lift` (hover): `0 4px 14px rgba(1,20,56,.12), 0 34px 68px -22px rgba(1,20,56,.45), 0 0 0 1px rgba(255,153,9,.07)`
- **Easing:** `cubic-bezier(0.16, 1, 0.3, 1)` — one curve, used everywhere.
- **Hover convention:** cards lift `-8px` (property), `-6px` (team), `-5px` (testimonial); buttons lift `-2px`. All pick up `--shadow-lift`.

### 1.4 Breakpoints

`1080px` (footer columns) · `960px` (nav → full-screen menu, property grid → auto-fit) · `900px` (two-col → stacked, trust/testimonial grids) · `720px` (section padding) · `640px` (single column, compare table → cards)

---

## 2. Page inventory

15 visual pages plus one XML endpoint. The five marked **CORE** are the redesign scope for this pass.

| Page | Route | Purpose | Scope |
|---|---|---|---|
| Home | `index.php` | Hero, curated listings, corridor tour, trust, testimonials | **CORE** |
| All Properties | `properties.php` | Full portfolio, filterable, paginated 12/page | **CORE** |
| Property Detail | `property.php?id=` | Single listing, gallery, enquiry, agent | **CORE** |
| Corridor Guide | `sector.php?slug=` | Area landing page with FAQs and listings | **CORE** |
| Contact | `contact.php` | Enquiry form + office info + map | **CORE** |
| Insights list | `blog.php` | Article grid with type filter tabs | Later |
| Insights post | `blog-post.php?slug=` | Long-form article | Later |
| Team | `team.php` | Advisor grid | Later |
| Agent profile | `agent.php?slug=` | Single advisor + their listings | Later |
| Compare | `compare.php?ids=` | Side-by-side, max 4 properties | Later |
| About | `about.php` | Story, stats, process, team preview | Later |
| Why an Advisor | `why-an-advisor.php` | 3-way comparison, case studies | Later |
| EMI Calculator | `emi-calculator.php` | Live client-side loan maths | Later |
| Map Search | `map-search.php` | Leaflet map with property pins | Later |
| Get Alerts | `saved-searches.php` | Saved-search signup form | Later |
| Sitemap | `sitemap.php` | XML only — no visual design | N/A |

**Global chrome on every page:** corridor ticker (top) → sticky header → page content → footer → floating chat toggle (bottom-right) → compare bar (bottom, slides up when active).

---

## 3. The five core pages, section by section

### 3.1 Home — `index.php`

| # | Section | Content |
|---|---|---|
| 1 | Corridor ticker | Infinite marquee, navy-950 bar. Fixed "Average rates today" label, then corridor name + gold price band items interleaved with italic Playfair trust-line snippets, duplicated ×2 for seamless loop, pauses on hover |
| 2 | Sticky header | 84px tall, frosted glass over paper (`blur(16px) saturate(1.4)`). Mountain-peak mark + wordmark left, 6 nav links centre, phone + "Enquire Now" right. Underline wipes in left→right on hover |
| 3 | Hero | Near-black `#050607` ground with an animated canvas. Eyebrow ("01 — Curated Portfolio", auto-numbered) → H1 → lede → **"Ask us anything" teaser bar** (typewriter-cycled sample questions; opens the real chat widget on submit) → two CTAs (gold "View Featured Properties" + outline WhatsApp). Desktop ≥960px only: a floating **Average Rate Stack** card carousel beside the copy — auto-advances every 3.5s, drag/swipe to advance, dot nav, pulls from `corridors` (name, price band, blurb, optional YoY growth badge) |
| 4 | Stat bar | Dotted radial-gradient fade on paper background. 3 numbers: years advising, families advised, corridors covered (count driven by the live corridor list) |
| 5 | Curated Listings filter | Paper-dim card (not frosted-over-hero — moved here from the old hero search panel). Five category tabs (All / New Launch / Under Construction / Ready To Move / Commercial) — AJAX-filters the grid below without reload. Then keyword input + corridor select + navy Filter button |
| 6 | Featured properties | **Bento grid** — 3 columns, first card spans 2×2 with a 16:13 image. Property cards, **full-colour photography** (no grayscale/duotone — see Recommendation #3, now adopted) |
| 7 | Corridor journey | Navy band. Horizontal snap-scroll panels, each `min(760px, 88vw)` wide × `min(70vh, 640px)` tall. Full-bleed photo, navy gradient overlay, `01 / 06` counter, name, excerpt, gold price-band pill |
| 8 | Trust grid | Navy-deep. 4 columns, outlined cards, gold icon + title + one line (Advisors first brokers second / RERA-verified always / WhatsApp-fast responses / Corridor specialists). "1,500+ customers since 2019" sits as a standalone corner callout next to the section heading, not a 5th card |
| 9 | Partners strip | Centred pill row: Godrej, Emaar, Signature, Whiteland, M3M, Ashiana — Playfair Display text in paper-dim pills |
| 10 | Testimonials | Paper-dim band. 3 columns. White cards, giant gold quote glyph bleeding off the top-left corner, quote set in Playfair Display |
| 11 | Footer | 5-column navy gradient (`2fr 1fr 1fr 1fr 1fr`): brand (with mountain-peak mark) + socials, Explore, Tools, Corridors, Contact. HRERA disclaimer bottom |
| — | Chat widget | Persona: "Asha, your Kutumb advisor" with an avatar bubble in the panel header. Same real `/api/chatbot.php` backend as before — the hero teaser bar opens this same widget rather than running a second reply pipeline |

**Mobile:** grid drops to `auto-fit minmax(260px, 1fr)` at ≤960px; nav becomes a full-screen frosted slide-in; trust grid → 2 columns; testimonials → 1 column at ≤640px.

### 3.2 All Properties — `properties.php`

1. **Page hero** — navy gradient (`165deg, navy-deep → navy-950`), 76px padding, grain texture. Breadcrumb → H1 "All Properties" → one-line description.
2. **Filter bar** — white card, 12px radius, card shadow. Four inline fields: keyword text, corridor select, category select, max-budget number, then orange Filter button. Wraps on mobile.
3. **Result count** — plain ink-soft line: "9 properties found".
4. **Property grid** — standard 3-column (not bento), 12 per page.
5. **Pagination** — centred `← Previous | Page 1 of 2 | Next →` using soft small buttons.
6. **Empty state** — one sentence with an inline link to Contact.

### 3.3 Property Detail — `property.php?id=`

1. **Breadcrumb hero** — navy gradient. Home / Corridor / Name, then H1, then `Sector · Developer · Category`.
2. **Gallery hero** — 16:8 full-width image, 12px radius.
3. **Thumbnail strip** — 100×74px thumbs, horizontally scrollable, duotone-treated, click opens a full-screen lightbox (navy 92% backdrop, arrows, keyboard nav).
4. **Two-column body** (`1.4fr / 1fr`, stacks at 900px):
   - **Left:** About + description → pill row (BHK, sector, category, corridor band) → amenity grid (3 cols, orange checkmarks) → optional virtual tour (16:9 embed) → Documents & Compliance (RERA number + document rows in paper-dim) → "Advisor's Note" callout in paper-dim
   - **Right (sticky, `top:104px`):** price info-card — eyebrow "Starting Price" → 32px Newsreader price → orange WhatsApp button → Call Now + Calculate EMI side by side → "+ Add to Compare" underlined toggle → divider → inline callback form (name, phone, message) → below it, the advisor card with photo, name, role
5. **Related properties** — "More on this corridor / You might also like", 3 cards.

### 3.4 Corridor Guide — `sector.php?slug=`

1. **Page hero** — navy gradient, breadcrumb, corridor name, description, then a **stat row**: price band + curated-listing count, both 40px Newsreader in white.
2. **Full-bleed hero image** — edge-to-edge, no radius (if the corridor has one).
3. **Listings** — section head with "Ask an advisor" navy button on the right, then property grid.
4. **"Is [corridor] right for you?"** — paper-dim band, two-column: long description left, "Talk to a corridor specialist" info-card with orange WhatsApp CTA right.
5. **FAQ accordion** — max-width 760px, white cards, `+`/`–` orange marker, native `<details>`.
6. **Related Insights** — paper-dim band, 3 blog cards.

### 3.5 Contact — `contact.php`

1. **Page hero** — navy gradient. "Talk to a Kutumb advisor" + "we'll get back within one business day, faster on WhatsApp".
2. **Two-column:**
   - **Left:** eyebrow "Send an Enquiry" → H2 "We'll call you back" → form (Full Name, Phone, Email optional, "What are you looking for?" textarea with a real placeholder example) → orange Submit. Submits via AJAX, replies with a toast, no page reload.
   - **Right:** Office info-card (address, phone, email, navy WhatsApp button) then a 4:3 embedded Google Map with 12px radius.

---

## 4. Component library

| Component | Spec |
|---|---|
| **Property card** | White, 24px radius, card shadow. 4:3 image, greyscaled + navy→orange duotone (`mix-blend-mode: color`) at rest. Badge top-left (frosted white, or solid orange if Featured). Compare toggle top-right (30px circle, turns orange when active). Body: 20px Newsreader title, `sector · developer` meta, then a top-bordered price row — 23px Newsreader amount left, "View Details →" right. Hover: lift 8px, image scales 1.08, CTA gap widens and turns orange |
| **Buttons** | 44px min-height, 14×28px padding, 7px radius, 15px/500. `primary` orange on navy-deep text · `navy` navy on white · `outline` transparent with white border (**dark backgrounds only** — invisible on light) · `soft` paper-dim with navy text. `.btn-sm` is 36px |
| **Testimonial card** | White, 24px radius, 1px line border. 72px orange `"` glyph at 30% opacity bleeding off top-left. Quote in 17.5px Newsreader. Name bold navy-deep 14px, role 12px ink-soft |
| **Team card** | White, 12px radius. Square 1:1 photo, then name and role. Hover lift 6px |
| **Info card** | White, 24px radius, 26px padding, line border, card shadow. Used for every sidebar CTA block |
| **Form field** | Full-width, 7px radius, line border, 13×15px padding. Label above: 13px/500 navy-deep. Focus: orange border + 4px orange glow ring |
| **Pill** | 999px radius, line border, 8×16px, 13px ink-soft |
| **Eyebrow** | 12px uppercase, `0.16em` tracking, orange-deep, auto-numbered in section heads |
| **Chat widget** | 60px orange circle bottom-right. Opens a 360×480px frosted panel: navy-950 header, green "talk to a person on WhatsApp" strip, bubbles (navy = user, paper-dim = bot), pill input with orange send button |
| **Compare bar** | Fixed bottom, navy-deep, slides up when ≥1 property selected. Removable chips + Clear + "Compare Now". Max 4 items, stored in `localStorage` |
| **Toast** | Top-right, white, 3px left border (green success / amber error), slides in from the right |
| **FAQ item** | White, 12px radius, 18×22px padding. `+` → `–` orange marker |

**Signature motion:** one-time navy load curtain on first visit · animated hero canvas · word-by-word H1 reveal · scroll-reveal fade-ups · custom cursor with contextual labels (View / Compare / Read / Explore) on desktop · magnetic buttons · page cross-fade via the View Transitions API · grain texture overlay on dark sections.

---

## 5. Real content for mockups

Use these actual values. **No lorem ipsum, no invented projects** — the design has to hold up against real Indian property names, real ₹-Cr price formats, and real corridor names.

**Corridors (6):**
Golf Course Road ₹22,000–35,000+/sq ft · Golf Course Extension Road ₹14,000–19,500/sq ft · Southern Peripheral Road ₹12,350–17,900/sq ft · Sohna Road ₹11,000–15,000/sq ft · Dwarka Expressway ₹8,000–15,000/sq ft · New Gurgaon ₹4,500–8,000/sq ft

`corridors.price_growth_label` (e.g. `"+9.1%"`) is a new, optional, admin-editable field (added for the Average Rate Stack's YoY badge) — left blank until real figures are supplied; the badge simply doesn't render for a corridor with no value set.

**Properties (9 — 3 featured):**

| Name | Sector | Developer | Category | Price |
|---|---|---|---|---|
| Godrej Vrikshya ★ | Sector 103 | Godrej | New Launch | 3.75 Cr+ |
| Whiteland Aspen & Brissville ★ | Sector 76 | Whiteland | New Launch | 3.7 Cr+ |
| Godrej Aristocrat ★ | Sector 49 | Godrej | Ready to Move | 2.6 Cr |
| Godrej Astra | Sector 54 | Godrej | New Launch | 10 Cr+ |
| M3M Golf Hills-2 | Sector 79 | M3M | Under Construction | 1.27 Cr+ |
| Ashiana Amarah Phase 4 | Sector 93 | Ashiana | Under Construction | 2.04 Cr+ |
| Smartworld One DXP | Dwarka Expressway | Smartworld | Commercial | 70 Lakhs+ |
| Omaxe Street | New Gurgaon | Omaxe | Commercial | 60 Lakhs+ |
| 32 Milestone | Sohna Road | Milestone | Commercial | 70 Lakhs+ |

**Testimonials (3, verbatim):**
- "The team walked us through every RERA document before we signed anything. No pressure, just clear answers." — Verified Buyer, Sector 54, Golf Course Road
- "We compared three corridors before deciding — Kutumb's sector guides made the tradeoffs obvious." — Verified Buyer, Sector 79, SPR
- "Fast WhatsApp responses and a genuinely curated shortlist instead of a hundred irrelevant listings." — Verified Buyer, Sector 93, New Gurgaon

**Amenities (12):** Clubhouse · Landscaped Gardens · 24x7 Security · Power Backup · Covered Parking · Swimming Pool · Kids' Play Area · Gymnasium · Jogging Track · Indoor Games Room · Rainwater Harvesting · EV Charging Points

**Homepage copy in use:** Eyebrow "Curated Portfolio" · H1 "Gurgaon's most trusted property advisors." · Lede "Kutumb means family. And family never gives you the wrong advice." · Section 2 "A handpicked portfolio, not an overwhelming list" · Section 3 "Explore Gurgaon, corridor by corridor" · Trust "Advisors first, brokers second." (with the four trust-point set above) · Stat bar "7+ / 1,500+ / 6" · Testimonials heading "Verified buyers, real decisions"

**Hero "Ask us anything" starter questions (also used as chat-widget conversation starters):**
- "What's the rate for a 3BHK in Sector 82?"
- "Where can I find a home within a ₹50 lakh budget?"
- "Is Dwarka Expressway a good buy right now?"

---

## 6. Constraints and freedoms

### Must keep working

- **WhatsApp-first CTAs**, pre-filled with context (property name, corridor). These are conversion infrastructure, not decoration — every one of them earns its place.
- **The five-tab category filter** on the homepage search panel, filtering without a page reload.
- **Compare**, max 4 properties, `localStorage`-backed, with the sticky bottom bar.
- **The sticky price/enquiry sidebar** on property detail — this is the page's conversion surface.
- **Corridor guides as SEO landing pages** — stat row, FAQs, and related posts all feed structured data.
- **Chat widget** with a one-click human WhatsApp handoff.
- **Toast-based form feedback** — no full-page reloads on enquiry submit.

### Free to reinterpret

Card geometry and radii · grid density and bento arrangement · hero treatment and the canvas animation · which motion touches survive · corridor journey format (the underlying data doesn't require a horizontal scroller) · section ordering on Home · badge and pill styling · footer column structure.

### Technical constraints the design must live inside

- **Server-rendered PHP, no frontend framework, no build step.** Layouts must be achievable in hand-written CSS.
- **Any new library must load from a CDN via a plain `<script>`/`<link>` tag** — there is no bundler.
- Images are served from `/uploads/properties/`; a 480px-wide `-card` derivative now exists for card/thumbnail contexts, full-size for hero and lightbox.
- Fonts come from Google Fonts. Adding a third family costs real load time — justify it.

---

## 7. Recommendations

Marked opinion, not documentation. Judgement calls for the redesign, based on reading the current code and content together.

1. **Reconsider the 100vh hero.** `min-height: 100vh` means a first-time visitor sees zero property listings without scrolling. For a site whose entire value proposition is "a handpicked portfolio, not an overwhelming list", showing that portfolio sooner is likely worth more than the full-screen canvas moment. Consider ~72–80vh so the first row of cards peeks above the fold.

2. **The bento first-card is doing a lot of work with only 9 listings.** With three featured properties, the 2×2 hero card plus five standard cards leaves an awkward tail. Either commit to a stronger featured/standard visual distinction, or drop the bento and let all nine sit in an even grid.

3. ~~**The greyscale + duotone treatment on property photos is a genuine risk.**~~ **Adopted.** Property cards now show full-colour photography with a light navy gradient on hover instead of the grayscale+duotone treatment.

4. **`--navy-mid` is declared and effectively unused.** There's an unused mid-tone available if the redesign needs another step between navy and navy-deep.

5. ~~**The corridor ticker is the site's most distinctive element** and also its least explained.~~ **Adopted.** The ticker now carries a fixed "Average rates today" label before the scrolling track.

6. **`.btn-outline` is white-on-transparent and only works on dark backgrounds.** It is currently invisible if placed on a light card. Either give it a dark-background-safe variant or add a light-surface outline button.

---

## 8. Canvas plan

Ten artboards, desktop and mobile for each of the five core pages.

| # | Artboard | Width | Notes |
|---|---|---|---|
| 1 | Home — Desktop | 1440 | Full scroll: ticker → hero+search → bento grid → corridor journey → trust → partners → testimonials → footer |
| 2 | Home — Mobile | 390 | Slide-in nav state, single-column grid, corridor panels at 88vw |
| 3 | All Properties — Desktop | 1440 | Filter bar populated with real values, 9 cards, pagination |
| 4 | All Properties — Mobile | 390 | Stacked filters, auto-fit single column |
| 5 | Property Detail — Desktop | 1440 | Godrej Vrikshya as the example. Gallery + sticky sidebar + amenity grid + docs |
| 6 | Property Detail — Mobile | 390 | Sidebar unsticks and moves below content |
| 7 | Corridor Guide — Desktop | 1440 | Golf Course Extension Road as the example. Stat row, listings, FAQ accordion |
| 8 | Corridor Guide — Mobile | 390 | Stacked stat row |
| 9 | Contact — Desktop | 1440 | Two-column form + office card + map |
| 10 | Contact — Mobile | 390 | Stacked, form first |

**Build order:** Home desktop first — it establishes the full visual language. Get that approved before the other nine, so a direction change costs one artboard instead of ten.
