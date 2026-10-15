<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ai-filters.php';

$corridors = db()->query('SELECT id, name FROM corridors WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
$categories = ['new-launch' => 'New Launch', 'under-construction' => 'Under Construction', 'ready-to-move' => 'Ready To Move', 'commercial' => 'Commercial'];

// Pre-fill from the visitor's last search so they don't retype it (WCAG 3.3.7).
$last = filters_remembered();
$pre = [
    'corridor_id' => $last['corridor_id'] ?? null,
    'category' => $last['category'] ?? '',
    'budget_min' => !empty($last['price_min']) ? (int)$last['price_min'] : '',
    'budget_max' => !empty($last['price_max']) ? (int)$last['price_max'] : '',
    'bhk' => !empty($last['bhk']) ? implode(' / ', $last['bhk']) . ' BHK' : '',
    'notes' => $last ? trim(($last['sectors'] ? 'Sectors: ' . implode(', ', $last['sectors']) . '. ' : '') . ($last['prefs'] ? 'Preferences: ' . implode(', ', array_map(fn($x) => FILTER_PREFS[$x]['label'], $last['prefs'])) . '.' : '')) : '',
];

$page_title = 'Get Matched by an Advisor — Kutumb Realty';
$page_scripts = ['ai-finder'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain">
  <div class="container">
    <div class="breadcrumb"><a href="/index.php">Home</a> / Get Matched</div>
    <h1>Tell us what you're looking for</h1>
    <p>Save your search criteria and a Kutumb advisor will personally reach out when we have a match. This is a person-to-person service &mdash; we don&rsquo;t send automated listing alerts.</p>
  </div>
</section>

<section>
  <div class="container two-col">
    <div class="reveal">
      <span class="eyebrow">Save a Search</span>
      <h2>We'll get in touch personally</h2>
      <?php
      $hf_variant = 'compact';
      $hf_filters = null;
      $hf_title = 'Describe it — we’ll fill in the form for you';
      $hf_fill_form = '#saved-search-form';
      include __DIR__ . '/includes/ai/home-finder.php';
      ?>
      <?php if ($last): ?><p class="ai-prefill-note">We’ve filled this in from your last search (<?= e(filters_describe($last)) ?>) — change anything you like.</p><?php endif; ?>
      <form id="saved-search-form">
        <div class="hp-field" aria-hidden="true"><label for="ss-company">Company Website</label><input type="text" id="ss-company" name="company_website" tabindex="-1" autocomplete="off"></div>
        <div class="form-field"><label for="ss-name">Full Name</label><input type="text" id="ss-name" name="name" required></div>
        <div class="form-field"><label for="ss-phone">Phone</label><input type="tel" id="ss-phone" name="phone" required></div>
        <div class="form-field"><label for="ss-email">Email (optional)</label><input type="email" id="ss-email" name="email"></div>
        <div style="display:flex; gap:16px;">
          <div class="form-field" style="flex:1">
            <label for="ss-corridor">Preferred Corridor</label>
            <select id="ss-corridor" name="corridor_id">
              <option value="">Any</option>
              <?php foreach ($corridors as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$pre['corridor_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-field" style="flex:1">
            <label for="ss-category">Category</label>
            <select id="ss-category" name="category">
              <option value="">Any</option>
              <?php foreach ($categories as $val => $label): ?><option value="<?= e($val) ?>" <?= $pre['category'] === $val ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="display:flex; gap:16px;">
          <div class="form-field" style="flex:1"><label for="ss-budget-min">Min Budget (₹)</label><input type="number" id="ss-budget-min" name="budget_min" min="0" step="100000" value="<?= e((string)$pre['budget_min']) ?>"></div>
          <div class="form-field" style="flex:1"><label for="ss-budget-max">Max Budget (₹)</label><input type="number" id="ss-budget-max" name="budget_max" min="0" step="100000" value="<?= e((string)$pre['budget_max']) ?>"></div>
        </div>
        <div class="form-field"><label for="ss-bhk">Configuration (optional)</label><input type="text" id="ss-bhk" name="bhk_config" placeholder="e.g. 3 BHK" value="<?= e($pre['bhk']) ?>"></div>
        <div class="form-field">
          <label for="ss-timeline">When are you looking to buy? (optional)</label>
          <select id="ss-timeline" name="timeline">
            <option value="">Not sure yet</option>
            <option value="now">Within 3 months</option>
            <option value="6m">3&ndash;6 months</option>
            <option value="12m">6&ndash;12 months</option>
            <option value="research">Researching for now</option>
          </select>
        </div>
        <input type="hidden" name="source" value="saved-searches-page">
        <div class="form-field"><label for="ss-notes">Notes (optional)</label><textarea id="ss-notes" name="notes" placeholder="Anything else we should know?"><?= e($pre['notes']) ?></textarea></div>
        <div class="form-field form-check"><label><input type="checkbox" name="call_ok" value="1"> Please call me to discuss my search</label></div>
        <?php $leadDisclosureMode = 'optin'; include __DIR__ . '/includes/lead-disclosure.php'; ?>
        <button type="submit" class="btn btn-primary">Save My Search</button>
      </form>
    </div>
    <div class="reveal">
      <div class="info-card">
        <h4>Prefer to talk now?</h4>
        <p style="font-size:13px; margin-bottom:16px;">Skip the form and chat with an advisor directly.</p>
        <a href="<?= whatsapp_url("Hi, I'd like help setting up a saved property search.") ?>" class="btn btn-primary btn-block" target="_blank" rel="noopener">Chat on WhatsApp</a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
document.getElementById('saved-search-form').addEventListener('submit', function (e) {
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
