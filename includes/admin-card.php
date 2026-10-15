<?php
// Shared photo-card renderer for the admin tabs whose items have a photo —
// Properties, Team, Testimonials, Corridors, Blog & Insights. Mirrors the
// public site's photo/navy-overlay/white-text/orange-highlight treatment
// (see .corridor-journey-panel in style.css) so the admin panel reads as
// the same design system as the public site instead of a plain data table.
//
// $card keys:
//   image (string, pre-safe — from property_image_src()/property_placeholder_svg(), not re-escaped)
//   alt (string)
//   badge (string|null)
//   title (string)
//   meta (string[] — blank/null entries are skipped)
//   highlight_label (string|null), highlight_value (string|null)
//   added (string|null — already formatted)
//   edit_url (string)
//   actions_extra (string|null — raw HTML, e.g. a "View" link, rendered between Edit and Delete)
//   delete_form (string — raw HTML, e.g. the existing .js-row-delete-form markup)
//   row_id (int|null — becomes data-row-id, for the anchored-toast lookup)
//   highlighted (bool — adds a persistent "just saved" outline; see the
//     save-banner block on each list page, tied to ?saved=1&id=X in the URL)
function render_admin_card(array $card): string
{
    $image = $card['image'] ?? '';
    $alt = $card['alt'] ?? '';
    $badge = $card['badge'] ?? null;
    $title = $card['title'] ?? '';
    $meta = array_filter($card['meta'] ?? [], fn($line) => $line !== null && $line !== '');
    $highlightLabel = $card['highlight_label'] ?? null;
    $highlightValue = $card['highlight_value'] ?? null;
    $added = $card['added'] ?? null;
    $editUrl = $card['edit_url'] ?? '#';
    $actionsExtra = $card['actions_extra'] ?? '';
    $deleteFormHtml = $card['delete_form'] ?? '';
    $rowId = $card['row_id'] ?? null;
    $highlighted = !empty($card['highlighted']);

    ob_start();
    ?>
    <div class="admin-photo-card<?= $highlighted ? ' just-saved' : '' ?>"<?= $rowId ? ' data-row-id="' . (int)$rowId . '"' : '' ?>>
      <div class="admin-photo-card-media">
        <img src="<?= $image ?>" alt="<?= e($alt) ?>" loading="lazy">
        <?php if ($badge): ?><span class="admin-photo-card-badge"><?= e($badge) ?></span><?php endif; ?>
        <div class="admin-photo-card-overlay">
          <h3><?= e($title) ?></h3>
          <?php foreach ($meta as $line): ?><p><?= e($line) ?></p><?php endforeach; ?>
        </div>
      </div>
      <div class="admin-photo-card-body">
        <?php if ($highlightLabel !== null): ?>
          <div class="admin-photo-card-highlight">
            <span class="label"><?= e($highlightLabel) ?></span>
            <span class="value"><?= e((string)$highlightValue) ?></span>
          </div>
        <?php endif; ?>
        <?php if ($added): ?><div class="admin-photo-card-added">Added <?= e($added) ?></div><?php endif; ?>
        <div class="admin-photo-card-actions">
          <a href="<?= e($editUrl) ?>">Edit</a>
          <?= $actionsExtra ?>
          <?= $deleteFormHtml ?>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

// Shared shell for the "no photo" admin cards — Leads, Follow-Ups, Saved
// Searches, Manage Admins — mirroring render_admin_card()'s shell but for
// records with no image (decorative navy header + watermark instead, styled
// in admin.css under .admin-data-card-*).
//
// $card keys:
//   title (string, raw — escaped by this function)
//   subtitle (string|null, raw — escaped by this function)
//   badges (string[] — each a complete, already-safe '<span class="admin-data-card-badge ...">...</span>')
//   rows (string[] — each the already-safe INNER content of one .admin-data-card-row div;
//     this function supplies the wrapping div)
//   footer (string|null — already-safe raw HTML for .admin-data-card-footer's content)
//   row_id (int|null — becomes data-row-id, for the anchored-toast lookup)
function render_admin_data_card(array $card): string
{
    $title = $card['title'] ?? '';
    $subtitle = $card['subtitle'] ?? null;
    $badges = array_filter($card['badges'] ?? [], fn($b) => $b !== null && $b !== '');
    $rows = array_filter($card['rows'] ?? [], fn($r) => $r !== null && $r !== '');
    $footer = $card['footer'] ?? null;
    $rowId = $card['row_id'] ?? null;

    ob_start();
    ?>
    <div class="admin-photo-card admin-data-card"<?= $rowId ? ' data-row-id="' . (int)$rowId . '"' : '' ?>>
      <div class="admin-data-card-header">
        <h3><?= e($title) ?></h3>
        <?php if ($subtitle !== null && $subtitle !== ''): ?><div class="admin-data-card-sub"><?= e($subtitle) ?></div><?php endif; ?>
        <?php if ($badges): ?><div class="admin-data-card-badges"><?= implode('', $badges) ?></div><?php endif; ?>
      </div>
      <?php if ($rows): ?>
      <div class="admin-data-card-rows">
        <?php foreach ($rows as $row): ?><div class="admin-data-card-row"><?= $row ?></div><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($footer !== null && $footer !== ''): ?><div class="admin-data-card-footer"><?= $footer ?></div><?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
