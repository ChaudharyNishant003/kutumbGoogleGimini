<?php
// admin-header.php is required after the redirects below (see property-form.php).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

$propertyId = (int)($_GET['property_id'] ?? 0);
$stmt = db()->prepare('SELECT id, name, possession_text, info_verified_on FROM properties WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$propertyId]);
$property = $stmt->fetch();
if (!$property) {
    header('Location: /admin/properties.php');
    exit;
}

$self = '/admin/property-faqs.php?property_id=' . $propertyId;
$errors = [];

function valid_date_or_null(string $value, array &$errors, string $label): ?string
{
    if ($value === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $value);
    if (!$d || $d->format('Y-m-d') !== $value) {
        $errors[] = $label . ' is not a valid date.';
        return null;
    }
    if ($d > new DateTime('tomorrow')) {
        $errors[] = $label . ' can’t be in the future.';
        return null;
    }
    return $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $itemId = (int)($_POST['id'] ?? 0);
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired — please try again.';
    } elseif ($action === 'save_faq') {
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? 0);
        if ($question === '' || mb_strlen($question) > 255) $errors[] = 'Question is required (max 255 characters).';
        if ($answer === '' || mb_strlen($answer) > 3000) $errors[] = 'Answer is required (max 3000 characters).';
        if (!$errors) {
            if ($itemId) {
                db()->prepare('UPDATE property_faqs SET question=?, answer=?, sort_order=? WHERE id=? AND property_id=?')->execute([$question, $answer, $sort, $itemId, $propertyId]);
            } else {
                db()->prepare('INSERT INTO property_faqs (property_id, question, answer, sort_order) VALUES (?,?,?,?)')->execute([$propertyId, $question, $answer, $sort]);
            }
            header('Location: ' . $self . '&saved=faq');
            exit;
        }
    } elseif ($action === 'delete_faq' && $itemId) {
        db()->prepare('DELETE FROM property_faqs WHERE id=? AND property_id=?')->execute([$itemId, $propertyId]);
        header('Location: ' . $self . '&deleted=faq');
        exit;
    } elseif ($action === 'save_disclosure') {
        $text = trim($_POST['text'] ?? '');
        $reviewed = valid_date_or_null(trim($_POST['reviewed_by_counsel_on'] ?? ''), $errors, 'Counsel review date');
        if ($text === '' || mb_strlen($text) > 3000) $errors[] = 'Disclosure text is required (max 3000 characters).';
        if (!$errors) {
            if ($itemId) {
                db()->prepare('UPDATE property_disclosures SET text=?, reviewed_by_counsel_on=? WHERE id=? AND property_id=?')->execute([$text, $reviewed, $itemId, $propertyId]);
            } else {
                db()->prepare('INSERT INTO property_disclosures (property_id, text, reviewed_by_counsel_on) VALUES (?,?,?)')->execute([$propertyId, $text, $reviewed]);
            }
            header('Location: ' . $self . '&saved=disclosure');
            exit;
        }
    } elseif ($action === 'delete_disclosure' && $itemId) {
        db()->prepare('DELETE FROM property_disclosures WHERE id=? AND property_id=?')->execute([$itemId, $propertyId]);
        header('Location: ' . $self . '&deleted=disclosure');
        exit;
    }
}

$faqs = db()->prepare('SELECT * FROM property_faqs WHERE property_id = ? ORDER BY sort_order, id');
$faqs->execute([$propertyId]);
$faqs = $faqs->fetchAll();
$disclosures = db()->prepare('SELECT * FROM property_disclosures WHERE property_id = ? ORDER BY id');
$disclosures->execute([$propertyId]);
$disclosures = $disclosures->fetchAll();

$editFaq = null;
foreach ($faqs as $f) if ((int)$f['id'] === (int)($_GET['edit_faq'] ?? 0)) $editFaq = $f;
$editDisc = null;
foreach ($disclosures as $d) if ((int)$d['id'] === (int)($_GET['edit_disclosure'] ?? 0)) $editDisc = $d;

$page_title = 'FAQs & Disclosures — Kutumb Admin';
require_once __DIR__ . '/../includes/admin-header.php';
$savedWhat = $_GET['saved'] ?? '';
$deletedWhat = $_GET['deleted'] ?? '';
?>

<div class="admin-topbar">
  <h1>FAQs &amp; Disclosures — <?= e($property['name']) ?></h1>
  <a href="/admin/property-form.php?id=<?= (int)$property['id'] ?>" class="btn btn-navy btn-sm">← Back to Property</a>
</div>

<p style="color:var(--ink-soft); font-size:13.5px; margin:-8px 0 16px; max-width:820px;">
  The Project Assistant on this property's page answers only from what's saved here and on the property form — it quotes these answers word for word, with their "last updated" date.
  Questions it can't answer appear in <a href="/admin/ai-log.php?status=fallback">AI Question Log</a>, so you know which FAQs to add.
</p>
<?php if (!$property['info_verified_on']): ?>
  <p class="admin-note-warn">The assistant won't answer price or possession questions for this property until "Project information verified on" is set in the <a href="/admin/property-form.php?id=<?= (int)$property['id'] ?>">property form</a>.</p>
<?php endif; ?>

<?php if ($savedWhat): ?><div class="form-note success"><?= $savedWhat === 'faq' ? 'FAQ saved.' : 'Disclosure saved.' ?></div><?php endif; ?>
<?php if ($deletedWhat): ?><div class="form-note success"><?= $deletedWhat === 'faq' ? 'FAQ deleted.' : 'Disclosure deleted.' ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="form-note error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card">
  <h4 style="margin-bottom:12px;"><?= $editFaq ? 'Edit FAQ' : 'Add an FAQ' ?></h4>
  <form method="post" action="<?= e($self) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_faq">
    <input type="hidden" name="id" value="<?= (int)($editFaq['id'] ?? 0) ?>">
    <div class="form-field"><label for="faq-q">Question</label><input type="text" id="faq-q" name="question" maxlength="255" required value="<?= e($editFaq['question'] ?? ($_POST['question'] ?? '')) ?>" placeholder="e.g. Is there a clubhouse?"></div>
    <div class="form-field"><label for="faq-a">Answer</label><textarea id="faq-a" name="answer" maxlength="3000" required placeholder="Write the verified answer exactly as visitors should see it."><?= e($editFaq['answer'] ?? ($_POST['answer'] ?? '')) ?></textarea></div>
    <div class="form-field" style="max-width:160px;"><label for="faq-sort">Display order</label><input type="number" id="faq-sort" name="sort_order" value="<?= (int)($editFaq['sort_order'] ?? 0) ?>"></div>
    <div style="display:flex; gap:12px;">
      <button type="submit" class="btn btn-primary"><?= $editFaq ? 'Save FAQ' : 'Add FAQ' ?></button>
      <?php if ($editFaq): ?><a href="<?= e($self) ?>" class="btn btn-soft">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="admin-card">
  <?php if (!$faqs): ?>
    <p style="color:var(--ink-soft)">No FAQs yet for this property.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th>Question</th><th>Answer</th><th>Last updated</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($faqs as $f): ?>
          <tr>
            <td data-label="Question" style="font-weight:600; max-width:260px;"><?= e($f['question']) ?></td>
            <td data-label="Answer" style="max-width:420px;"><?= e(excerpt($f['answer'], 160)) ?></td>
            <td data-label="Last updated"><?= e(date('d M Y', strtotime($f['updated_at']))) ?></td>
            <td data-label="" style="white-space:nowrap;">
              <a href="<?= e($self . '&edit_faq=' . (int)$f['id']) ?>" style="font-size:13px; margin-right:12px;">Edit</a>
              <form method="post" action="<?= e($self) ?>" style="display:inline;" onsubmit="return confirm('Delete this FAQ?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_faq">
                <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                <button type="submit" style="background:none;border:none;color:#d64545;cursor:pointer;padding:0;font-size:13px;">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="admin-card">
  <h4 style="margin-bottom:6px;"><?= $editDisc ? 'Edit disclosure' : 'Add a disclosure' ?></h4>
  <p class="admin-note-warn" style="margin-top:8px;">Regulatory wording (RERA and similar) must be reviewed by your lawyer first. The assistant will <strong>not</strong> repeat a disclosure until "Reviewed by counsel on" is filled in.</p>
  <form method="post" action="<?= e($self) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_disclosure">
    <input type="hidden" name="id" value="<?= (int)($editDisc['id'] ?? 0) ?>">
    <div class="form-field"><label for="disc-text">Disclosure text</label><textarea id="disc-text" name="text" maxlength="3000" required><?= e($editDisc['text'] ?? ($_POST['text'] ?? '')) ?></textarea></div>
    <div class="form-field" style="max-width:260px;"><label for="disc-date">Reviewed by counsel on <span class="field-badge-optional">Leave empty until reviewed</span></label><input type="date" id="disc-date" name="reviewed_by_counsel_on" max="<?= date('Y-m-d') ?>" value="<?= e($editDisc['reviewed_by_counsel_on'] ?? '') ?>"></div>
    <div style="display:flex; gap:12px;">
      <button type="submit" class="btn btn-primary"><?= $editDisc ? 'Save disclosure' : 'Add disclosure' ?></button>
      <?php if ($editDisc): ?><a href="<?= e($self) ?>" class="btn btn-soft">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<?php if ($disclosures): ?>
<div class="admin-card">
  <table class="admin-table">
    <thead><tr><th>Disclosure</th><th>Counsel review</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($disclosures as $d): ?>
        <tr>
          <td data-label="Disclosure" style="max-width:560px;"><?= e(excerpt($d['text'], 200)) ?></td>
          <td data-label="Counsel review">
            <?php if ($d['reviewed_by_counsel_on']): ?>
              <span class="ai-status ai-status-answered">Reviewed <?= e(date('d M Y', strtotime($d['reviewed_by_counsel_on']))) ?></span>
            <?php else: ?>
              <span class="ai-status ai-status-fallback">Not reviewed — hidden from assistant</span>
            <?php endif; ?>
          </td>
          <td data-label="" style="white-space:nowrap;">
            <a href="<?= e($self . '&edit_disclosure=' . (int)$d['id']) ?>" style="font-size:13px; margin-right:12px;">Edit</a>
            <form method="post" action="<?= e($self) ?>" style="display:inline;" onsubmit="return confirm('Delete this disclosure?');">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete_disclosure">
              <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <button type="submit" style="background:none;border:none;color:#d64545;cursor:pointer;padding:0;font-size:13px;">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
