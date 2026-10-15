<?php
/**
 * Structured project details on the property page (migration 0019).
 * Expects $property, $unitPlans, $paymentPlans, $specifications in scope.
 * Every block hides itself when there's nothing to show, so older listings
 * without this data render exactly as before.
 */
$isEoi = !empty($property['is_eoi']);

$facts = [];
if ($property['land_area_acres'] !== null) $facts[] = [rtrim(rtrim(number_format((float)$property['land_area_acres'], 2), '0'), '.'), 'Acres'];
if ($property['tower_count'] !== null) $facts[] = [(int)$property['tower_count'], (int)$property['tower_count'] === 1 ? 'Tower' : 'Towers'];
if ($property['unit_count'] !== null) $facts[] = [number_format((int)$property['unit_count']), 'Residences'];
if (!empty($property['floors_text'])) $facts[] = [$property['floors_text'], 'Floors'];
if (!empty($property['possession_text'])) $facts[] = [$property['possession_text'], 'Possession'];
// The rate (rate_text) is shown under the starting price in the sidebar, not here.

$unitGroups = [];
foreach ($unitPlans as $u) {
    $unitGroups[$u['group_label'] ?? ''][] = $u;
}
$hasCarpet = (bool)array_filter($unitPlans, fn($u) => $u['carpet_sqft'] !== null);
$hasBalcony = (bool)array_filter($unitPlans, fn($u) => $u['balcony_sqft'] !== null);
$hasSaleable = (bool)array_filter($unitPlans, fn($u) => $u['saleable_sqft'] !== null);
$showPrice = !$isEoi && (bool)array_filter($unitPlans, fn($u) => $u['rate_per_sqft'] !== null && $u['saleable_sqft'] !== null);

$paymentGroups = [];
foreach ($paymentPlans as $row) {
    $paymentGroups[$row['plan_name']][] = $row;
}

$specGroups = [];
foreach ($specifications as $s) {
    $specGroups[$s['group_label'] ?? ''][$s['category']][] = $s['detail'];
}
?>

<?php if ($facts): ?>
<div class="key-facts reveal" aria-label="Key facts">
  <?php foreach ($facts as [$value, $label]): ?>
    <div class="key-fact"><span class="key-fact-value"><?= e((string)$value) ?></span><span class="key-fact-label"><?= e($label) ?></span></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($unitPlans): ?>
<div class="reveal project-block">
  <h4 class="project-block-title">Unit Plans</h4>
  <?php foreach ($unitGroups as $groupLabel => $rows): ?>
    <?php if ($groupLabel !== ''): ?><div class="project-group-label"><?= e($groupLabel) ?></div><?php endif; ?>
    <div class="unit-table-wrap">
      <table class="unit-table">
        <thead>
          <tr>
            <th>Unit</th>
            <?php if ($hasSaleable): ?><th>Saleable / Super area</th><?php endif; ?>
            <?php if ($hasCarpet): ?><th>Carpet area</th><?php endif; ?>
            <?php if ($hasBalcony): ?><th>Balcony</th><?php endif; ?>
            <?php if ($showPrice): ?><th>Approx. price*</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $u): ?>
            <tr>
              <td data-label="Unit"><?= e($u['unit_label']) ?></td>
              <?php if ($hasSaleable): ?><td data-label="Saleable / Super area"><?= $u['saleable_sqft'] !== null ? e(format_area((float)$u['saleable_sqft'])) . ' sq ft' : '—' ?></td><?php endif; ?>
              <?php if ($hasCarpet): ?><td data-label="Carpet area"><?= $u['carpet_sqft'] !== null ? e(format_area((float)$u['carpet_sqft'])) . ' sq ft' : '—' ?></td><?php endif; ?>
              <?php if ($hasBalcony): ?><td data-label="Balcony"><?= $u['balcony_sqft'] !== null ? e(format_area((float)$u['balcony_sqft'])) . ' sq ft' : '—' ?></td><?php endif; ?>
              <?php if ($showPrice): ?>
                <td data-label="Approx. price*">
                  <?php if ($u['rate_per_sqft'] !== null && $u['saleable_sqft'] !== null): ?>
                    ≈ <?= e(format_price_short((float)$u['rate_per_sqft'] * (int)$u['saleable_sqft'])) ?>
                  <?php else: ?>On request<?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>
  <p class="project-footnote">
    <?php if ($showPrice): ?>*Approx. price = saleable area × base rate<?= !empty($property['rate_text']) ? ' (' . e($property['rate_text']) . ')' : '' ?>. Excludes PLC, parking, GST and other charges — ask your advisor for the exact cost sheet.<?php endif; ?>
    Areas as per the developer's plans; final areas are as per the Agreement to Sale.
  </p>
</div>
<?php endif; ?>

<?php if ($paymentGroups || !empty($property['payment_plan_note'])): ?>
<div class="reveal project-block">
  <h4 class="project-block-title">Payment Plan</h4>
  <?php foreach ($paymentGroups as $planName => $rows): ?>
    <div class="project-group-label"><?= e($planName) ?></div>
    <div class="unit-table-wrap">
      <table class="unit-table payment-table">
        <thead><tr><th>Milestone</th><th>Amount</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr><td data-label="Milestone"><?= e($row['milestone']) ?></td><td data-label="Amount"><?= e($row['amount_text']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>
  <?php if (!empty($property['payment_plan_note'])): ?><p class="project-footnote"><?= e($property['payment_plan_note']) ?></p><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($specGroups): ?>
<div class="reveal project-block">
  <h4 class="project-block-title">Specifications</h4>
  <div class="faq-list spec-list">
    <?php foreach ($specGroups as $groupLabel => $categories): ?>
      <?php foreach ($categories as $category => $details): ?>
        <details class="faq-item">
          <summary><?= $groupLabel !== '' ? e($groupLabel) . ' — ' : '' ?><?= e($category) ?></summary>
          <ul>
            <?php foreach ($details as $d): ?><li><?= e($d) ?></li><?php endforeach; ?>
          </ul>
        </details>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
