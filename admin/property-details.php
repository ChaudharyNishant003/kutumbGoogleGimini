<?php
// admin-header.php is required after the redirects below (see property-form.php).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

$propertyId = (int)($_GET['property_id'] ?? 0);
$stmt = db()->prepare('SELECT id, name, is_eoi FROM properties WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$propertyId]);
$property = $stmt->fetch();
if (!$property) {
    header('Location: /admin/properties.php');
    exit;
}

$self = '/admin/property-details.php?property_id=' . $propertyId;

// One config per editable list. Field: [column, label, type, maxLength, required, placeholder].
// Types: text, textarea, int, decimal, date. Every list also has sort_order.
$sections = [
    'unit' => [
        'table' => 'property_unit_plans',
        'title' => 'Unit Plans',
        'help' => 'One row per unit type. If both saleable area and base rate are filled, the page shows "≈ approx price" (area × rate) — hidden while the project is in EOI mode. Use Group to split variants, e.g. "Cocoa County Gold".',
        'fields' => [
            ['group_label', 'Group', 'text', 80, false, 'Optional, e.g. Cocoa County Gold'],
            ['unit_label', 'Unit', 'text', 120, true, 'e.g. 3 BHK – Type A'],
            ['saleable_sqft', 'Saleable / super area (sq ft)', 'int', 6, false, 'e.g. 2475'],
            ['carpet_sqft', 'Carpet area (sq ft)', 'decimal', 9, false, 'e.g. 1333.12'],
            ['balcony_sqft', 'Balcony area (sq ft)', 'decimal', 9, false, 'e.g. 504.29'],
            ['rate_per_sqft', 'Base rate (₹ per sq ft)', 'int', 7, false, 'e.g. 17000'],
        ],
        'columns' => ['group_label', 'unit_label', 'saleable_sqft', 'carpet_sqft', 'balcony_sqft', 'rate_per_sqft'],
    ],
    'payment' => [
        'table' => 'property_payment_plans',
        'title' => 'Payment Plan',
        'help' => 'Rows with the same plan name are shown together as one table (e.g. "Payment Plan (30:40:30)"). A one-line note like "CLP & PLP available" goes in the property form instead.',
        'fields' => [
            ['plan_name', 'Plan name', 'text', 80, true, 'e.g. Payment Plan (30:40:30)'],
            ['milestone', 'Milestone', 'text', 200, true, 'e.g. On or within 30 days of application'],
            ['amount_text', 'Amount', 'text', 60, true, 'e.g. 10% less application amount'],
        ],
        'columns' => ['plan_name', 'milestone', 'amount_text'],
    ],
    'spec' => [
        'table' => 'property_specifications',
        'title' => 'Specifications',
        'help' => 'Rows with the same category are listed together under one collapsible heading on the page.',
        'fields' => [
            ['group_label', 'Group', 'text', 80, false, 'Optional, e.g. Cocoa County Gold'],
            ['category', 'Category', 'text', 60, true, 'e.g. Flooring'],
            ['detail', 'Detail', 'textarea', 500, true, 'e.g. Drawing/Dining/Kitchen: imported marble'],
        ],
        'columns' => ['group_label', 'category', 'detail'],
    ],
    'rera' => [
        'table' => 'property_rera_registrations',
        'title' => 'RERA Registrations',
        'help' => 'Add one row per phase. When a property has rows here, the page lists them all instead of the single "RERA Number" field on the property form.',
        'fields' => [
            ['phase_label', 'Phase', 'text', 80, false, 'Optional, e.g. Phase 2'],
            ['rera_number', 'RERA number', 'text', 80, true, 'e.g. RC/REP/HARERA/GGM/1086/818/2026/58'],
            ['registered_on', 'Dated', 'date', 10, false, ''],
        ],
        'columns' => ['phase_label', 'rera_number', 'registered_on'],
    ],
];

$errors = [];
$errorSection = null;
$posted = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $key = $_POST['section'] ?? '';
    $itemId = (int)($_POST['id'] ?? 0);
    $cfg = $sections[$key] ?? null;

    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired — please try again.';
    } elseif (!$cfg) {
        $errors[] = 'Unknown section.';
    } elseif ($action === 'save') {
        $values = [];
        foreach ($cfg['fields'] as [$col, $label, $type, $max, $required]) {
            $raw = trim((string)($_POST[$col] ?? ''));
            $posted[$col] = $raw;
            if ($raw === '') {
                if ($required) $errors[] = $label . ' is required.';
                $values[$col] = null;
                continue;
            }
            if (mb_strlen($raw) > $max) {
                $errors[] = $label . ' must be ' . $max . ' characters or fewer.';
                continue;
            }
            if ($type === 'int') {
                $clean = str_replace(',', '', $raw);
                if (!ctype_digit($clean)) { $errors[] = $label . ' must be a whole number.'; continue; }
                $values[$col] = (int)$clean;
            } elseif ($type === 'decimal') {
                $clean = str_replace(',', '', $raw);
                if (!is_numeric($clean) || (float)$clean < 0) { $errors[] = $label . ' must be a number.'; continue; }
                $values[$col] = round((float)$clean, 2);
            } elseif ($type === 'date') {
                $d = DateTime::createFromFormat('Y-m-d', $raw);
                if (!$d || $d->format('Y-m-d') !== $raw) { $errors[] = $label . ' is not a valid date.'; continue; }
                $values[$col] = $raw;
            } else {
                $values[$col] = $raw;
            }
        }
        $values['sort_order'] = (int)($_POST['sort_order'] ?? 0);
        $posted['sort_order'] = $values['sort_order'];

        if (!$errors) {
            $cols = array_keys($values);
            if ($itemId) {
                $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
                db()->prepare("UPDATE {$cfg['table']} SET $set WHERE id = ? AND property_id = ?")
                    ->execute([...array_values($values), $itemId, $propertyId]);
            } else {
                $placeholders = implode(',', array_fill(0, count($cols) + 1, '?'));
                db()->prepare("INSERT INTO {$cfg['table']} (property_id, " . implode(', ', $cols) . ") VALUES ($placeholders)")
                    ->execute([$propertyId, ...array_values($values)]);
            }
            header('Location: ' . $self . '&saved=' . $key . '#' . $key);
            exit;
        }
        $errorSection = $key;
    } elseif ($action === 'delete' && $itemId) {
        db()->prepare("DELETE FROM {$cfg['table']} WHERE id = ? AND property_id = ?")->execute([$itemId, $propertyId]);
        header('Location: ' . $self . '&deleted=' . $key . '#' . $key);
        exit;
    }
}

$rows = [];
foreach ($sections as $key => $cfg) {
    $stmt = db()->prepare("SELECT * FROM {$cfg['table']} WHERE property_id = ? ORDER BY sort_order, id");
    $stmt->execute([$propertyId]);
    $rows[$key] = $stmt->fetchAll();
}

$editKey = null;
$editRow = null;
foreach ($sections as $key => $cfg) {
    $editId = (int)($_GET['edit_' . $key] ?? 0);
    if (!$editId) continue;
    foreach ($rows[$key] as $r) {
        if ((int)$r['id'] === $editId) { $editKey = $key; $editRow = $r; }
    }
}

$page_title = 'Project Details — Kutumb Admin';
require_once __DIR__ . '/../includes/admin-header.php';
$savedKey = $_GET['saved'] ?? '';
$deletedKey = $_GET['deleted'] ?? '';
?>

<div class="admin-topbar">
  <h1>Project Details — <?= e($property['name']) ?></h1>
  <div style="display:flex; gap:10px; flex-wrap:wrap;">
    <a href="/property.php?id=<?= (int)$property['id'] ?>" class="btn btn-soft btn-sm" target="_blank" rel="noopener">View on site ↗</a>
    <a href="/admin/property-form.php?id=<?= (int)$property['id'] ?>" class="btn btn-navy btn-sm">← Back to Property</a>
  </div>
</div>

<p style="color:var(--ink-soft); font-size:13.5px; margin:-8px 0 16px; max-width:820px;">
  These lists appear on the property page under the description. Empty lists are simply hidden.
  Land area, towers, units, floors, rate and the EOI switch are on the <a href="/admin/property-form.php?id=<?= (int)$property['id'] ?>">property form</a>.
</p>
<?php if (!empty($property['is_eoi'])): ?>
  <p class="admin-note-warn">This property is in <strong>EOI mode</strong> — prices (including approx unit prices) are hidden on the site.</p>
<?php endif; ?>

<?php foreach ($sections as $key => $cfg):
    $isEdit = $editKey === $key;
    $formValues = $errorSection === $key ? $posted : ($isEdit ? $editRow : []);
?>
<div class="admin-card" id="<?= e($key) ?>">
  <h3 style="margin-bottom:6px;"><?= e($cfg['title']) ?></h3>
  <p style="font-size:12.5px; color:var(--ink-soft); margin:0 0 14px;"><?= e($cfg['help']) ?></p>

  <?php if ($savedKey === $key): ?><div class="form-note success">Saved.</div><?php endif; ?>
  <?php if ($deletedKey === $key): ?><div class="form-note success">Deleted.</div><?php endif; ?>
  <?php if ($errorSection === $key): foreach ($errors as $err): ?><div class="form-note error"><?= e($err) ?></div><?php endforeach; endif; ?>

  <?php if ($rows[$key]): ?>
    <table class="admin-table" style="margin-bottom:18px;">
      <thead><tr>
        <?php foreach ($cfg['fields'] as $f): ?><th><?= e($f[1]) ?></th><?php endforeach; ?>
        <th>Order</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows[$key] as $r): ?>
          <tr<?= $isEdit && (int)$editRow['id'] === (int)$r['id'] ? ' style="background:var(--paper-dim)"' : '' ?>>
            <?php foreach ($cfg['fields'] as $f): $v = $r[$f[0]]; ?>
              <td data-label="<?= e($f[1]) ?>"><?php
                if ($v === null || $v === '') echo '<span style="color:var(--ink-soft)">—</span>';
                elseif ($f[2] === 'decimal') echo e(format_area((float)$v));
                elseif ($f[2] === 'int') echo e(number_format((int)$v));
                elseif ($f[2] === 'date') echo e(date('d M Y', strtotime($v)));
                else echo e(excerpt((string)$v, 90));
              ?></td>
            <?php endforeach; ?>
            <td data-label="Order"><?= (int)$r['sort_order'] ?></td>
            <td data-label="" style="white-space:nowrap;">
              <a href="<?= e($self . '&edit_' . $key . '=' . (int)$r['id']) ?>#<?= e($key) ?>" style="font-size:13px; margin-right:12px;">Edit</a>
              <form method="post" action="<?= e($self) ?>" style="display:inline;" onsubmit="return confirm('Delete this row?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="section" value="<?= e($key) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" style="background:none;border:none;color:#d64545;cursor:pointer;padding:0;font-size:13px;">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p style="color:var(--ink-soft); font-size:13.5px;">Nothing added yet — this section is hidden on the site.</p>
  <?php endif; ?>

  <h4 style="margin:6px 0 10px; font-size:14px;"><?= $isEdit ? 'Edit row' : '+ Add a row' ?></h4>
  <form method="post" action="<?= e($self) ?>#<?= e($key) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="section" value="<?= e($key) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $isEdit ? (int)$editRow['id'] : 0 ?>">
    <div style="display:flex; gap:12px; flex-wrap:wrap;">
      <?php foreach ($cfg['fields'] as [$col, $label, $type, $max, $required, $placeholder]):
          $fid = 'pd-' . $key . '-' . $col;
          $val = (string)($formValues[$col] ?? '');
          if ($type === 'decimal' && is_numeric($val) && str_contains($val, '.')) $val = rtrim(rtrim($val, '0'), '.');
      ?>
        <div class="form-field" style="flex:<?= $type === 'textarea' ? '1 1 100%' : '1 1 180px' ?>;">
          <label for="<?= e($fid) ?>"><?= e($label) ?><?php if ($required): ?><span class="field-required-mark">*</span><?php endif; ?></label>
          <?php if ($type === 'textarea'): ?>
            <textarea id="<?= e($fid) ?>" name="<?= e($col) ?>" maxlength="<?= (int)$max ?>" rows="2" placeholder="<?= e($placeholder) ?>" <?= $required ? 'required' : '' ?>><?= e($val) ?></textarea>
          <?php elseif ($type === 'date'): ?>
            <input type="date" id="<?= e($fid) ?>" name="<?= e($col) ?>" value="<?= e($val) ?>">
          <?php else: ?>
            <input type="text" id="<?= e($fid) ?>" name="<?= e($col) ?>" maxlength="<?= (int)$max ?>" value="<?= e($val) ?>" placeholder="<?= e($placeholder) ?>" <?= in_array($type, ['int', 'decimal'], true) ? 'inputmode="decimal"' : '' ?> <?= $required ? 'required' : '' ?>>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="form-field" style="flex:0 0 110px;"><label for="pd-<?= e($key) ?>-sort">Order</label><input type="number" id="pd-<?= e($key) ?>-sort" name="sort_order" value="<?= (int)($formValues['sort_order'] ?? (count($rows[$key]) + 1)) ?>"></div>
    </div>
    <div style="display:flex; gap:12px;">
      <button type="submit" class="btn btn-primary btn-sm"><?= $isEdit ? 'Save row' : 'Add row' ?></button>
      <?php if ($isEdit): ?><a href="<?= e($self) ?>#<?= e($key) ?>" class="btn btn-soft btn-sm">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
