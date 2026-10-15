# Kutumb Realty Website — Project History

This file is a plain-language record of everything built on this project so far, version by version. It's meant to be read by a non-developer — no technical jargon. Whenever a new round of work finishes, this file gets a new section added to the bottom, so nothing from earlier versions is ever lost.

Live local site: http://localhost:8000 (admin panel: http://localhost:8000/admin/login.php)
Live hosted site: https://kutumb-web-production.up.railway.app (admin panel: https://kutumb-web-production.up.railway.app/admin/login.php)
Code is backed up on GitHub: https://github.com/ChaudharyNishant003/Kutumb

---

## Version 1 — Core Website + Admin Panel

The foundation: a working real estate website plus a back-office dashboard to run it.

- **Property listings** — every project (Godrej Astra, Whiteland Aspen, etc.) with photos, price, configuration, and location.
- **Admin dashboard** — a login-protected panel to add/edit properties, manage photos and documents (like RERA certificates), write blog posts, manage the team page, and see incoming customer enquiries.
- **Buyer tools** — an EMI calculator, a "compare properties side by side" tool, a map-based search, and a "save my search, alert me later" feature.
- **Lead capture** — every contact form and enquiry gets saved into the admin panel so nothing gets missed.
- **Basic SEO** — pages are structured so Google can understand and index them properly.

## Version 2 — Premium Look & Feel

A visual upgrade pass — same features as Version 1, but styled to feel like a premium real estate brand instead of a generic template: refined colors, better typography, smoother animations on buttons and page scroll, and a cleaner-looking admin panel.

## Version 3 — UI/UX & Brand Messaging Overhaul (in progress)

A deeper round of improvements, based on a full audit of what was missing or could be better. Done in phases:

**Phase 1 — Fixed real bugs.** The mobile menu was breaking on some phones, some form fields weren't properly linked to their labels (an accessibility issue), and if the database ever hiccuped, visitors saw a raw error page instead of a proper "something went wrong" message. All fixed.

**Phase 2 — Easier browsing.** Added a proper "All Properties" page where visitors can filter and page through every listing (before, there was no single place to see everything). Also added a real search box on the homepage.

**Phase 3 — Better conversion tools.** Every "Chat on WhatsApp" button across the site now pre-fills a relevant message (e.g. mentions the specific property or corridor) instead of opening a blank chat. Added a "Talk to a real person" button inside the chatbot. Rewrote the homepage headline to actually say what the business does and where, instead of a vague line.

**Phase 4 — Mobile-friendly and honest.** The "compare properties" table now turns into an easy-to-read stacked view on phones. If a property is missing documents or an assigned agent, the site now says so honestly instead of just leaving blank space. Built a real, per-property amenities checklist in the admin panel (before, every property showed the same fake hardcoded list of amenities regardless of what it actually had). Removed dead/fake links (like social media icons that went nowhere).

**Phase 5 — Brand messaging.** Added a new "Why Work With an Advisor" page explaining why using Kutumb is better than going directly to a builder or a regular broker. Added a few sample success-story write-ups (clearly marked as placeholders — swap these with real client stories once available). Blog posts now show who wrote them.

**Phase 6 — Signature visual touches.** Added a scrolling banner showing live price ranges for each area/corridor. Added smooth crossfade transitions between pages. Upgraded the homepage headline animation to a more polished text-reveal effect.

**Phase 7 — More signature design.** The mouse cursor now shows helpful hints like "View" or "Compare" when hovering over things. Property photos got a stylish navy-and-orange tinted look on listing cards (the main photo on a property's own page stays full color). Added a horizontal scrolling "tour" of the different corridors/areas on the homepage.

**Still to come — Phase 8 (not started, needs a final go-ahead before building):** two bigger, more ambitious features — an interactive "from search to keys" storytelling section, and a redesigned dark-themed admin panel with quick keyboard shortcuts.

## Version 4 — Went Live on the Internet

Before this, the site only ran on this computer (localhost) — nobody else could visit it. This phase put it on the internet using a hosting service called Railway, so it now has a real, working web address anyone can open.

- **The site now has a public address**: https://kutumb-web-production.up.railway.app — open it from any phone or computer, anywhere.
- **A proper hosted database** was set up and matched to the real local database — all 9 properties, 6 corridors, 3 blog posts, 3 testimonials, and the property photo record that had been added through the local admin panel.
- **Permanent photo storage** is set up — property photos and documents uploaded through the admin panel from now on will survive future updates to the site instead of being lost.
- **Admin panel works the same way**, just at the new web address instead of localhost.
- The OpenAI-powered chatbot key wasn't carried over to the hosted site — the chatbot will show a "not configured" message until a key is added (same as it did locally before one was set).

## Version 5 — Fixed Scrolling Feeling Broken

After going live, scrolling through the site felt wrong in a few different ways — the page would shake, some content stayed invisible until you scrolled back up, content looked like it was lagging behind the scroll, and a normal trackpad swipe barely moved the page at all. All of this traced back to the same source: a "smooth scrolling" add-on (a third-party script called Lenis) that had been layered onto the site to make scrolling feel fancier, plus a matching animation library (GSAP ScrollTrigger) that a few effects depended on. Together they caused four separate problems:

- **Screen shaking while scrolling** — an earlier effect that hides the header while scrolling down was nudging the whole page up and down by 90 pixels every time, which fed back into itself and caused a visible vibration.
- **A horizontal "corridor tour" section that hijacked the mouse** — scrolling into it would lock the page in place and force the mouse wheel to drag a photo strip sideways instead of continuing down the page, which felt like the site had frozen.
- **Content staying invisible on phones** — property cards and section titles would sometimes stay blank (just their background color showing, no text/photo) until scrolling back up, because the animation library's math got confused every time a phone's address bar showed or hid itself mid-scroll (which happens constantly on real phones).
- **Sluggish, lagging scroll on both computer and phone** — the "smooth scrolling" add-on re-simulated scrolling in code instead of using the browser's own built-in scrolling, which made content trail visibly behind the actual scroll motion and made a single trackpad swipe move the page only a fraction as far as it normally would.

All four are fixed by removing the smooth-scrolling add-on entirely and switching the remaining animations to a simpler, more reliable method (the browser's native "is this on screen yet" detector instead of the add-on's own position math). Scrolling now behaves like scrolling on any normal website — because it now genuinely *is* the browser's own scrolling, not a simulation of it. Verified by running an automated browser test (not just eyeballing it) that scrolled the live site the way a real visitor would, on both a computer and a simulated phone, before and after each fix.

## Version 6 — Corridor Section Redesigned + Full Mobile Bug Sweep

**Corridor tour, rebuilt as requested.** The horizontal "explore Gurgaon corridor by corridor" section is back to its large, premium visual size — but redesigned so it can never trap a visitor: hovering directly over it and scrolling moves it sideways, but the moment your cursor leaves it (or it reaches its last card while you're still hovering), your normal up/down scroll takes over immediately. No more scroll-locking.

**A full, systematic mobile bug hunt.** Rather than guessing, every page was tested on a real emulated Android phone (Chromium via automated browser testing) and every finding was verified as fixed on the live site before being reported done. What was found and fixed:

- **The hamburger menu was badly broken** — tapping it opened a dropdown squeezed into a tiny ~64-pixel sliver near the top of the screen instead of covering the screen, which is why it looked like it "flashed and disappeared." Also fixed the site getting stuck (unable to scroll) that this caused. Now it's a proper full-screen menu.
- **Several sections were invisible on every page that used them** — the "Why Kutumb" trust section, the client testimonials, the team page, the "why an advisor" approach section, and some property listings would show a large blank gap instead of their actual content, on both mobile and desktop. This was a leftover animation-code conflict affecting 12 different spots across 8 pages. All now show properly.
- **Property cards looked cramped on some phone/tablet screen widths** — two cards would squeeze into a row too narrow for them, with one looking cut off. The listing grid now automatically uses however many columns actually fit comfortably, instead of forcing a fixed number.
- **The builder names (Godrej, M3M, Signature, etc.) looked like bare, unstyled text** — added a section label and proper pill/tag styling so it looks intentional.
- **The chat button and compare bar were floating on top of the open mobile menu** — now they hide themselves while the menu is open.
- **The ₹ symbol and some dashes were showing as "?" in prices and text** — this was corrupted data left over from the Version 4 database move to the hosting service (a technical mismatch in that one-time process, not something wrong with the site itself). Fixed directly in the hosted database, double-checked against the original correct text.
- **A "sideways scroll turns the screen white" bug was reported but could not be reproduced** after the above fixes on repeated automated testing — most likely it was a side effect of the broken hamburger menu above. Flagged to re-check after this update, rather than assumed fixed.

---

## Version 7 — Performance Optimization, Batch 1 (Safe, Zero-Risk Wins)

The first of a 3-batch optimization pass, based on a full code audit (`OPTIMIZATION_AUDIT.md`) turned into a developer implementation plan. This batch is purely behind-the-scenes speed and future-proofing — nothing looks or behaves differently to a visitor or in the admin panel.

- **Faster repeat visits** — the site's CSS, JavaScript, and images now tell the browser to remember them for a year instead of re-downloading them on every single visit, and text-based files (CSS/JS/HTML) are now compressed before sending. Both are standard, safe web-performance practices.
- **Fewer database lookups per page** — the list of corridors (Golf Course Road, Dwarka Expressway, etc.) was being looked up two or three separate times on every single page view (once for the scrolling ticker, once for the footer, once for the homepage) without realizing it already had the answer. It's now looked up once per page and reused.
- **Guard rails against future growth** — the homepage and the Insights list had no upper limit on how many properties/posts they'd try to show at once. Invisible today at 9 properties and 3 posts, but would have quietly gotten slower and slower as more get added. Set to a generous cap (60 properties, 100 posts) that won't be reached for a long while, so nothing visible changes today.
- **Faster sitemap** — the XML sitemap (used by Google, not visitors) used to be rebuilt from scratch on every single request; it's now cached for 15 minutes.
- **Database indexes added** — added a handful of database indexes on columns already used for filtering (property category, status, featured, etc.) in the admin panel and public filters. No effect at today's data size, but prevents those pages from slowing down as the amount of data grows.

## Version 8 — Performance Optimization, Batch 2 (Query Cleanup & Duplication)

Second batch of the optimization pass. Again, nothing looks or behaves differently — this is about how the code is written, not what it shows.

- **Database queries now ask for only what they need** — several places in the code were fetching every column of a table ("give me everything") when only a handful were ever actually used. Now they ask for exactly what's needed, which is lighter on the database and makes the code easier to reason about.
- **Fewer unnecessary scripts loaded per page** — three page-specific scripts (the homepage's animated intro canvas, the EMI calculator's live math, and the property photo gallery/lightbox) now only load on the one page each actually belongs to, instead of on every single page. (Checked carefully first: most of the site's animation code — fade-ins, button hover effects, the chat button's attention pulse — turned out to depend on a shared animation library that has to stay loaded everywhere, so that part was correctly left alone rather than risk breaking those effects site-wide.)
- **One shared "no-photo" admin card template** — Leads, Follow-Ups, Saved Searches, and Manage Admins each had their own copy-pasted version of the same card layout. They now all pull from one shared template (matching how Properties/Blog/Corridors/Team/Testimonials already share theirs), so a future style tweak only needs to happen in one place. Visually identical to before.
- **One shared form-submission checklist** — the two public lead forms (general enquiry and "save my search") each ran an identical copy-pasted sequence of spam/validation checks. Now they both call one shared version of that sequence.
- Verified live on the production site after deploy: every page's scripts load correctly, all four admin sections respond normally, and both public forms still validate and reject bad input exactly as before.

## Version 9 — Performance Optimization, Batch 3 (Images, Consistency, Accurate Analytics)

Third and final batch of the planned optimization pass — the biggest one, since it touches how photos are handled.

- **Property photos now get a lightweight "card" version automatically.** Previously, whatever a phone camera produced (sometimes 1.5MB+ for one photo) was served everywhere that photo appeared — the big detail-page photo and every small listing-card thumbnail alike. Now, the moment a property photo is uploaded, a second, much smaller copy is generated automatically (roughly 30–40x smaller) and used anywhere the photo appears small (the property grid, the compare tool, admin's Properties list, the photo-strip thumbnails on a property's own page). The original full-size photo is untouched and still used for the big detail-page image and the full-screen photo viewer. Confirmed working on a real existing photo already on the live site: 1.6MB → 44KB for the small version, with no visible quality loss at thumbnail size.
  - This only covers **property** photos for now (cover photo + gallery). Team member photos, blog cover images, testimonial photos, and corridor photos are not yet covered — a deliberate choice, to prove this out on one photo type before extending it, rather than changing how every photo on the site is handled in one go.
  - Photos uploaded *before* this change don't have a small version yet and will keep showing full-size until they're re-uploaded through the admin panel (or, going forward, until team/blog/testimonial/corridor photos get the same treatment). Nothing breaks in the meantime — the site is written to fall back to the original photo automatically whenever a small version doesn't exist yet.
- **Saved Searches now saves the same way Leads already does** — changing the Status or Assigned-to dropdown used to reload the whole page; now it saves instantly in the background with a small confirmation, matching how Leads has worked for a while.
- **View counts are now trustworthy** — a property/corridor/blog post's view count used to go up on every single page load, including the owner's own visits and search-engine crawlers. It now only counts once per visitor per day, so the Dashboard's "Top by views" numbers actually reflect real visitor interest going forward. (Past counts aren't retroactively corrected — only new views from here on are counted this way.)

This closes out the planned 3-batch optimization pass. Natural next steps, if wanted: extend the photo-compression treatment to team/blog/testimonial/corridor photos, and re-upload (or otherwise regenerate) small versions for property photos uploaded before this change.

## Where to find things

- **Admin login**: ask Claude for the current username/password — not written here on purpose, for security (this file is saved on GitHub).
- **All the code**: saved and backed up at the GitHub link above.
- **This file**: keep it updated after every new round of work, so anyone (including a future version of this AI assistant) can quickly catch up on what's been done.
