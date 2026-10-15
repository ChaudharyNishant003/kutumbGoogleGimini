<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/structured-data.php';

$corridors = get_active_corridors();

// Trade-off line per corridor, keyed by slug — bespoke copy, not pulled from
// the admin-editable `description` field (which serves a different purpose
// on the corridor guide page). Any corridor without an entry here just
// doesn't get a trade-off line rather than showing something wrong.
$corridorTradeOffs = [
    'golf-course-road' => "Deepest resale market and the shortest commute to the corporate belt — and the highest per-sq-ft entry in Gurgaon.",
    'golf-course-extension-road' => "Newer towers, wider roads and better layouts for the money; a few stretches still depend on road work that is not finished.",
    'dwarka-expressway' => "Best airport access and the strongest appreciation case; day-to-day retail, schools and clinics are still filling in.",
    'southern-peripheral-road' => "Central to both Golf Course roads with more space per rupee; peak-hour traffic at the junctions is the price you pay.",
    'sohna-road' => "Mature social infrastructure at a lower entry point; the housing stock is older and the layouts denser than newer corridors.",
    'new-gurgaon' => "The most square feet your budget will ever buy here; the commute to Cyber City is the longest of the tracked corridors.",
    'mg-road-old-gurgaon' => "Metro at the doorstep and genuinely settled neighbourhoods; buildings are older and parking is tight.",
    'sector-79-95-belt' => "Large townships with real amenity sets inside the gate; outside it, everyday retail is still thin in several pockets.",
];

$page_title = 'Why Work With an Advisor — Kutumb Realty';
$page_description = 'Going direct to a builder or through a transactional broker both have limits — see how a Kutumb advisor curates, verifies and negotiates differently.';
$structured_data = structured_data_breadcrumbs([
    ['name' => 'Home', 'url' => '/index.php'],
    ['name' => 'Why an Advisor', 'url' => '/why-an-advisor.php'],
]);
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain">
  <div class="container two-col">
    <div class="reveal">
      <div class="breadcrumb"><a href="/index.php">Home</a> / Why an Advisor</div>
      <h1>Why work with an advisor</h1>
      <p>Buying direct from a builder works. Buying through a transactional broker works. They just optimise for someone else.</p>
      <p>This page shows you the actual difference — the criteria, an illustrative example shortlist including what we would throw out, and what we aim to commit to.</p>
    </div>
    <div class="advisor-card info-card reveal">
      <span class="eyebrow" style="margin-bottom:8px">Example Advisor Profile</span>
      <p class="advisor-disclaimer advisor-disclaimer--top">Illustrative profile, not a real person &mdash; <a href="/team.php">meet the actual team</a>.</p>
      <div class="advisor-card-head">
        <span class="advisor-avatar">AK</span>
        <div>
          <strong>Arjun Khanna</strong>
          <span>Senior Advisor · Gurgaon</span>
        </div>
      </div>
      <p class="advisor-quote">"You'll get a shortlist I can defend line by line — including the projects I left out, and why."</p>
      <p class="advisor-hours">Replies in ~10 min · Mon–Sat, 9am–8pm</p>
      <a href="#consultation" class="btn btn-primary btn-block">Book a consultation</a>
    </div>
  </div>
  <div class="container">
    <div class="stat-row">
      <div><span class="num"><?= SITE_FOUNDED_YEAR ?></span><span class="label">Advising Gurgaon families since</span></div>
      <div><span class="num"><?= SITE_CUSTOMERS_SERVED ?></span><span class="label">Families guided to a purchase</span></div>
      <div><span class="num"><?= count($corridors) ?></span><span class="label">Gurgaon corridors we actively track</span></div>
      <div><span class="num">RERA</span><span class="label">Status shown on every project page</span></div>
    </div>
  </div>
</section>

<section>
  <div class="container">
    <div class="section-head reveal">
      <div>
        <span class="eyebrow">The Difference</span>
        <h2>Same market, three different sets of interests</h2>
        <p>Tap any row to see what it means on a real search.</p>
      </div>
    </div>
    <div class="advisor-table-wrap reveal">
      <table class="compare-table cmp-table">
        <thead>
          <tr>
            <th>Criterion</th>
            <th>Direct to Builder</th>
            <th>Transactional Broker</th>
            <th class="cmp-col-advisor">A Kutumb Advisor</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $criteria = [
              [
                  'q' => 'What the incentive is tied to',
                  'builder' => "Selling that builder's own inventory",
                  'broker' => 'Closing volume, quickly',
                  'advisor' => 'Fit — the one home you actually sign for',
                  'detail' => "A builder's sales team is measured on that project's bookings, so every comparison you hear is framed inside their own inventory. A volume broker is measured on deals closed this month. We are engaged before a shortlist exists and judged on whether the home still fits you two years later — which is why we will tell you to wait if nothing fits.",
              ],
              [
                  'q' => 'Inventory you get to see',
                  'builder' => "One developer's projects only",
                  'broker' => 'Wide, but weighted to what moves fastest',
                  'advisor' => 'Compared across corridors, then narrowed',
                  'detail' => 'A ₹2.5–4 Cr, 3BHK brief usually has 30–40 credible options across Golf Course Extension Road, Southern Peripheral Road and Dwarka Expressway. Seeing three of them is not a choice. We start from the full corridor set, then cut it down on criteria you set — and we show you the cut list, not just the survivors.',
              ],
              [
                  'q' => 'Who verifies HRERA and legal',
                  'builder' => 'Left to you and your lawyer',
                  'broker' => 'Usually left to you',
                  'advisor' => 'Checked before the project reaches your shortlist',
                  'detail' => 'Registration number, validity, sanctioned plan, tower-wise completion dates and any extension filed — pulled and read before we put a project in front of you. If a registration has been extended more than once, that goes on the page you see, not in the footnotes.',
              ],
              [
                  'q' => 'Your place in the negotiation',
                  'builder' => 'Across the table from their sales team',
                  'broker' => 'Told the outcome afterwards',
                  'advisor' => 'In the room, with us on your side of it',
                  'detail' => "You hear every number as it is said, including the ones we push back on. Before the meeting you get the corridor's actual transacted range for that configuration, so you know whether the \"special discount\" is a discount at all.",
              ],
              [
                  'q' => 'After the papers are signed',
                  'builder' => 'Handed over to a service desk',
                  'broker' => 'The relationship generally ends',
                  'advisor' => 'Same advisor, same number, still reachable',
                  'detail' => 'Registration appointments, handover checks, possession-delay letters, and the resale question four years later — all of it goes back to the advisor who ran your search, not to a general inbox.',
              ],
          ];
          foreach ($criteria as $i => $row): ?>
            <tr class="cmp-row" data-target="cmp-detail-<?= $i ?>" tabindex="0">
              <td><span class="cmp-chevron">›</span><?= e($row['q']) ?></td>
              <td class="cmp-dim"><?= e($row['builder']) ?></td>
              <td class="cmp-dim"><?= e($row['broker']) ?></td>
              <td class="cmp-col-advisor"><?= e($row['advisor']) ?></td>
            </tr>
            <tr class="cmp-detail" id="cmp-detail-<?= $i ?>" hidden>
              <td colspan="4"><?= e($row['detail']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section style="background:var(--paper-dim)">
  <div class="container">
    <div class="section-head reveal">
      <div>
        <span class="eyebrow">An Illustrative Shortlist</span>
        <h2>What filtering actually looks like</h2>
        <p class="example-banner"><strong>Illustrative example &mdash; not a real client search.</strong> Projects are unnamed and the figures are examples of the kind of reasoning we apply. The reasoning is the point.</p>
      </div>
    </div>
    <div class="shortlist-brief reveal">
      <div><span class="label">The Brief</span><span class="value">3BHK, ₹2.5–4 Cr</span></div>
      <div><span class="label">Corridors Opened</span><span class="value">GC Ext Road, SPR, Dwarka Expressway</span></div>
      <div><span class="label">Must-Haves</span><span class="value">Possession ≤ 3 years, HRERA valid, 9th floor or above</span></div>
      <div><span class="label">Funnel</span><span class="value">34 reviewed → 9 visited → 3 shortlisted</span></div>
    </div>
    <div class="shortlist-grid reveal-stagger">
      <div class="shortlist-card">
        <span class="shortlist-status">✓ Shortlisted</span>
        <h4>Sector 65 · 3BHK · 2,150 sq ft</h4>
        <p class="shortlist-loc">Golf Course Extension Road</p>
        <p class="shortlist-price">₹16,300/sq ft · possession Q4 2027</p>
        <p>Priced inside the corridor band, HRERA valid to 2028 with no extension filed, and the only option on the list where the developer's last two towers were handed over on schedule.</p>
      </div>
      <div class="shortlist-card">
        <span class="shortlist-status">✓ Shortlisted</span>
        <h4>Sector 71 · 3BHK + study · 2,410 sq ft</h4>
        <p class="shortlist-loc">Southern Peripheral Road</p>
        <p class="shortlist-price">₹13,900/sq ft · ready to move</p>
        <p>The most space per rupee of the three and no construction risk left. Trade-off we flagged: peak-hour exit onto SPR adds roughly 15 minutes to a Cyber City commute.</p>
      </div>
      <div class="shortlist-card">
        <span class="shortlist-status">✓ Shortlisted</span>
        <h4>Sector 102 · 3BHK · 1,980 sq ft</h4>
        <p class="shortlist-loc">Dwarka Expressway</p>
        <p class="shortlist-price">₹14,100/sq ft · possession Q2 2027</p>
        <p>Strongest appreciation case of the three given airport access, and the lowest entry price. Trade-off we flagged: local retail and schools are still two to three years behind the towers.</p>
      </div>
    </div>
    <div class="rejected-block reveal">
      <h3>Left out — and why</h3>
      <p>The part you normally never get to read.</p>
      <div class="rejected-grid">
        <div class="rejected-item"><span class="x">✕</span><div><strong>Sector 79 · 3BHK · possession 2028</strong><p>HRERA registration already extended twice. Possession maths stops being a date and becomes a hope.</p></div></div>
        <div class="rejected-item"><span class="x">✕</span><div><strong>Sector 63 · 3BHK · ready to move</strong><p>Quoted 12% above what comparable units on the same stretch transacted at last quarter, with no premium to justify it.</p></div></div>
        <div class="rejected-item"><span class="x">✕</span><div><strong>Sector 99 · 3BHK · possession 2027</strong><p>Only 4th-floor inventory left against a stated must-have of 9th floor or above. A good project, wrong unit.</p></div></div>
        <div class="rejected-item"><span class="x">✕</span><div><strong>Sector 37D · 3BHK · possession 2026</strong><p>The bedroom face of the tower looks onto a sanctioned 60-metre road alignment. Fine today, loud in three years.</p></div></div>
      </div>
    </div>
  </div>
</section>

<section class="trust-section texture-grain">
  <div class="container">
    <div class="section-head reveal">
      <div>
        <span class="eyebrow">The Advisor Charter</span>
        <h2>Four things we commit to, in writing</h2>
        <p>Every client gets these four lines on paper at the start of a search. If we break one, you are entitled to say so and walk.</p>
      </div>
    </div>
    <div class="trust-grid reveal-stagger">
      <div class="trust-item">
        <span class="charter-num">01</span>
        <h4>HRERA checked before you enquire</h4>
        <p>Registration status, validity and any extension filed are read before a project reaches your shortlist — not after you have paid a booking amount.</p>
      </div>
      <div class="trust-item">
        <span class="charter-num">02</span>
        <h4>You are in the room for pricing</h4>
        <p>Every pricing conversation happens with you present. We do not negotiate behind a closed door and report a number back to you afterwards.</p>
      </div>
      <div class="trust-item">
        <span class="charter-num">03</span>
        <h4>The same advisor after registration</h4>
        <p>The person who ran your search stays reachable on the same number through handover, possession and whatever comes after it.</p>
      </div>
      <div class="trust-item">
        <span class="charter-num">04</span>
        <h4>Follow-up only when you ask</h4>
        <p>You set the rhythm of contact. No call rotations, no month-end pressure, no third salesperson ringing you about the same tower.</p>
      </div>
    </div>
  </div>
</section>

<?php if ($corridors): ?>
<section>
  <div class="container">
    <div class="section-head reveal">
      <div>
        <span class="eyebrow">Corridor Reality</span>
        <h2>What each corridor costs you</h2>
        <p>Indicative bands for new and resale stock. Every corridor is a trade-off — an advisor's job is to name it before you visit, not after.</p>
      </div>
    </div>
    <div class="advisor-table-wrap reveal">
      <table class="compare-table">
        <thead><tr><th>Corridor</th><th>Rate Band</th><th>The Trade-Off</th></tr></thead>
        <tbody>
          <?php foreach ($corridors as $c): ?>
            <tr>
              <td><a href="/sector.php?slug=<?= e($c['slug']) ?>"><?= e($c['name']) ?></a></td>
              <td class="cmp-dim"><?= e($c['avg_price_band']) ?></td>
              <td class="cmp-dim"><?= e($corridorTradeOffs[$c['slug']] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php endif; ?>

<section style="background:var(--paper-dim)">
  <div class="container">
    <div class="section-head reveal">
      <div>
        <span class="eyebrow">How It Runs</span>
        <h2>Four steps, and who owns each one</h2>
      </div>
    </div>
    <div class="process-grid reveal-stagger">
      <div class="process-card">
        <span class="process-duration">Day 1 · 30 min</span>
        <span class="charter-num">01</span>
        <h4>The brief</h4>
        <p>A call to fix budget, corridors, possession window and the two or three things you will not compromise on.</p>
        <p class="process-owner">You bring the constraints. We bring the questions that narrow them.</p>
      </div>
      <div class="process-card">
        <span class="process-duration">3–5 days</span>
        <span class="charter-num">02</span>
        <h4>The shortlist</h4>
        <p>We compare across your corridors, run HRERA and document checks, and write down what was ruled out and why.</p>
        <p class="process-owner">Ours to build. Yours to challenge, line by line.</p>
      </div>
      <div class="process-card">
        <span class="process-duration">1–2 weekends</span>
        <span class="charter-num">03</span>
        <h4>Site visits</h4>
        <p>The same advisor at every visit, with the floor-plate, tower orientation and traffic timing checked on the ground.</p>
        <p class="process-owner">No project-wise salesperson handoffs.</p>
      </div>
      <div class="process-card">
        <span class="process-duration">2–6 weeks</span>
        <span class="charter-num">04</span>
        <h4>Negotiation &amp; registration</h4>
        <p>Pricing discussed with you in the room, then paperwork, bank coordination and registration run to a dated checklist.</p>
        <p class="process-owner">You decide. We do the chasing.</p>
      </div>
    </div>
  </div>
</section>

<section id="consultation">
  <div class="container two-col">
    <div class="reveal">
      <span class="eyebrow">Book a Consultation</span>
      <h2>Tell us the budget and the corridor. We will do the filtering.</h2>
      <p>A 30-minute call, then a shortlist you can argue with. No volume pitch, and no follow-up unless you ask for it — that one is in the charter.</p>
      <ul class="info-list">
        <li>✓ Shortlist with the rejected options included</li>
        <li>✓ HRERA and document status for every shortlisted project</li>
        <li>✓ Corridor rate bands for your configuration</li>
      </ul>
    </div>
    <div class="reveal">
      <div class="info-card">
        <form id="advisor-consultation-form">
          <div class="hp-field" aria-hidden="true"><label for="ac-company">Company Website</label><input type="text" id="ac-company" name="company_website" tabindex="-1" autocomplete="off"></div>
          <div class="form-field"><label for="ac-name">Full Name</label><input type="text" id="ac-name" name="name" required></div>
          <div class="form-field">
            <label for="ac-budget">Budget</label>
            <select id="ac-budget" name="budget_band" required>
              <option value="">Select a band</option>
              <option value="1-1.5">₹1–1.5 Cr</option>
              <option value="1.5-2.5">₹1.5–2.5 Cr</option>
              <option value="2.5-4">₹2.5–4 Cr</option>
              <option value="4-6">₹4–6 Cr</option>
              <option value="6+">₹6 Cr and above</option>
              <option value="commercial">Commercial — separate enquiry</option>
            </select>
          </div>
          <div class="form-field">
            <label for="ac-corridor">Corridor of Interest</label>
            <select id="ac-corridor" name="corridor_id">
              <option value="">Not sure yet — advise me</option>
              <?php foreach ($corridors as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label for="ac-timeline">Timeline</label>
            <select id="ac-timeline" name="timeline" required>
              <option value="">When are you buying?</option>
              <option value="now">Within 3 months</option>
              <option value="6m">3–6 months</option>
              <option value="12m">6–12 months</option>
              <option value="research">Researching for now</option>
            </select>
          </div>
          <div class="form-field"><label for="ac-phone">Phone</label><input type="tel" id="ac-phone" name="phone" required></div>
          <input type="hidden" name="source" value="why-an-advisor">
          <?php include __DIR__ . '/includes/lead-disclosure.php'; ?>
          <button type="submit" class="btn btn-primary btn-block">Book my consultation</button>
          <p class="advisor-form-note">One call to fix a slot. No marketing calls after.</p>
        </form>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
document.querySelectorAll('.cmp-row').forEach(function (row) {
  row.addEventListener('click', function () {
    var detail = document.getElementById(row.dataset.target);
    var open = !detail.hidden;
    detail.hidden = open;
    row.classList.toggle('cmp-row-open', !open);
  });
  row.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); row.click(); }
  });
});

document.getElementById('advisor-consultation-form').addEventListener('submit', function (e) {
  e.preventDefault();
  var form = e.target;
  var btn = form.querySelector('button[type="submit"]');
  kutumbSetLoading(btn, true);
  fetch('/api/saved-search-submit.php', { method: 'POST', body: new FormData(form) })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      kutumbToast(res.message, res.ok ? 'success' : 'error');
      if (res.ok) form.reset();
    })
    .catch(function () {
      kutumbToast('Something went wrong. Please call or WhatsApp us instead.', 'error');
    })
    .finally(function () { kutumbSetLoading(btn, false); });
});
</script>
