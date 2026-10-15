-- Phase 5 (UI/UX build): add a case-study post type for illustrative client stories,
-- surfaced on the new "Why an Advisor" page and filterable on /blog.php.

ALTER TABLE blog_posts
  MODIFY COLUMN post_type ENUM('article','market-report','guide','case-study') NOT NULL DEFAULT 'article';

-- Three clearly-illustrative placeholder case studies so the "Why an Advisor" page and the
-- /blog.php Case Studies tab aren't empty at launch. Each body opens with an HTML comment
-- flagging it as a placeholder — find and replace with permissioned real client stories
-- (with the client's consent) via the admin Blog panel as soon as they're available.

INSERT INTO blog_posts (title, slug, excerpt, body, post_type, corridor_id, author_id, status, published_at, meta_description) VALUES
(
  'How a First-Time Buyer Avoided an Unregistered Project on Dwarka Expressway',
  'case-study-dwarka-expressway-first-time-buyer',
  'A first-time buyer nearly booked a unit in a project with no valid HRERA registration — here is how a corridor-level document check caught it before any money changed hands.',
  '<!-- PLACEHOLDER CASE STUDY — illustrative scenario, not a real named client. Replace with a real, permissioned story from the admin Blog panel when available. -->
<p>A first-time buyer came to us set on a specific project advertised heavily along Dwarka Expressway, drawn in by an attractive price-per-square-foot and a fast possession timeline.</p>
<h3>The red flag</h3>
<p>Before any booking amount was paid, a routine RERA cross-check showed the project''s registration had lapsed months earlier and had not been renewed — a detail buried in the fine print of the developer''s own marketing material.</p>
<h3>What we did instead</h3>
<p>We shortlisted two comparable, fully RERA-registered alternatives on the same corridor within the buyer''s budget, arranged site visits for both within the week, and helped negotiate a similar payment plan on the project that was ultimately booked.</p>
<h3>The outcome</h3>
<p>The buyer avoided tying up capital in a project with unresolved compliance issues, and closed on a registered alternative roughly three weeks later than originally planned — a short delay against what a stalled or legally contested project could have cost.</p>',
  'case-study',
  (SELECT id FROM corridors WHERE slug = 'dwarka-expressway' LIMIT 1),
  NULL,
  'published',
  DATE_SUB(NOW(), INTERVAL 18 DAY),
  'A first-time buyer nearly booked an unregistered project on Dwarka Expressway — how a document check caught it in time.'
),
(
  'Comparing Rental Yields: Southern Peripheral Road vs. Sohna Road',
  'case-study-spr-vs-sohna-rental-yield',
  'An investor weighing two corridors for rental income asked us to model both scenarios side by side rather than rely on generic averages — here is how the comparison actually played out.',
  '<!-- PLACEHOLDER CASE STUDY — illustrative scenario, not a real named client. Replace with a real, permissioned story from the admin Blog panel when available. -->
<p>An investor evaluating a second property for rental income was deciding between Southern Peripheral Road and Sohna Road, and wanted more than the broad "average yield" figures found in generic market reports.</p>
<h3>The approach</h3>
<p>We pulled comparable listings and recent rental transactions on both corridors, adjusted for unit size and configuration, and walked through occupancy patterns driven by nearby commercial and IT-park development on each side.</p>
<h3>What the numbers showed</h3>
<p>Southern Peripheral Road showed a marginally higher headline yield, but Sohna Road''s vacancy periods between tenants were shorter thanks to steadier demand from a nearby employment cluster — a factor the sticker-yield number alone did not capture.</p>
<h3>The outcome</h3>
<p>The investor chose a Sohna Road unit for the more consistent occupancy, prioritizing lower vacancy risk over the highest quoted yield — a tradeoff made explicit rather than assumed.</p>',
  'case-study',
  (SELECT id FROM corridors WHERE slug = 'sohna-road' LIMIT 1),
  NULL,
  'published',
  DATE_SUB(NOW(), INTERVAL 34 DAY),
  'An investor compared rental yields on Southern Peripheral Road vs. Sohna Road — the headline number wasn''t the deciding factor.'
),
(
  'A Remote Purchase, Fully Verified: Buying on Golf Course Road from Abroad',
  'case-study-golf-course-road-nri-remote-purchase',
  'An NRI buyer needed to complete a purchase on Golf Course Road without visiting India in person — here is how site verification and document checks were handled remotely.',
  '<!-- PLACEHOLDER CASE STUDY — illustrative scenario, not a real named client. Replace with a real, permissioned story from the admin Blog panel when available. -->
<p>An NRI buyer based overseas wanted to purchase on Golf Course Road but could not travel to Gurgaon before the developer''s inventory window closed.</p>
<h3>The challenge</h3>
<p>Site inspection, document verification and price negotiation all needed to happen without the buyer physically present, while still giving them enough confidence to commit a significant payment remotely.</p>
<h3>What we did</h3>
<p>We recorded a full walkthrough video of the unit and common areas, verified the RERA registration and title documents on the buyer''s behalf, and ran the price negotiation over a scheduled video call with the developer''s sales team joining directly.</p>
<h3>The outcome</h3>
<p>The purchase was completed with all documentation reviewed and explained before signing, and the buyer saw the finished unit in person for the first time only at possession — with no surprises against what had been verified remotely.</p>',
  'case-study',
  (SELECT id FROM corridors WHERE slug = 'golf-course-road' LIMIT 1),
  NULL,
  'published',
  DATE_SUB(NOW(), INTERVAL 7 DAY),
  'An NRI buyer purchased on Golf Course Road entirely remotely — how site verification and document checks were handled from abroad.'
);
