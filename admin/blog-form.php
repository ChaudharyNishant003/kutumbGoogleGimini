<?php
// admin-header.php is required after POST-handling below, not here — see the
// comment in property-form.php for why (early output breaks header('Location')).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

$page_title = 'Post — Kutumb Admin';

$id = (int)($_GET['id'] ?? 0);
$post = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM blog_posts WHERE id = ?');
    $stmt->execute([$id]);
    $post = $stmt->fetch();
}

$corridors = db()->query('SELECT * FROM corridors WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
$postTypes = ['article' => 'Article', 'market-report' => 'Market Report', 'guide' => 'Guide', 'case-study' => 'Case Study'];
$statuses = ['draft' => 'Draft', 'published' => 'Published'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired — please try again.';
    }

    $title = trim($_POST['title'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $body = sanitize_post_body($_POST['body'] ?? '');
    $postType = $_POST['post_type'] ?? 'article';
    $corridorId = !empty($_POST['corridor_id']) ? (int)$_POST['corridor_id'] : null;
    $status = $_POST['status'] ?? 'draft';
    $metaDescription = trim($_POST['meta_description'] ?? '');

    if ($title === '' || $excerpt === '' || $body === '' || !isset($postTypes[$postType]) || !isset($statuses[$status])) {
        $errors[] = 'Please fill in title, excerpt, body, and select a valid type/status.';
    }

    $coverPath = $post['cover_image_path'] ?? null;
    if (!empty($_FILES['cover_image']['name'])) {
        $uploadError = null;
        $newName = handle_file_upload($_FILES['cover_image'], UPLOAD_DIR, image_mime_map(), MAX_UPLOAD_BYTES, $uploadError);
        if ($uploadError) {
            $errors[] = $uploadError;
        } elseif ($newName) {
            delete_uploaded_file(UPLOAD_DIR, $coverPath);
            $coverPath = $newName;
        }
    }

    if (!$errors) {
        $isNew = !$post;
        $admin = current_admin();
        $publishedAt = $post['published_at'] ?? null;
        if ($status === 'published' && !$publishedAt) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        if ($post) {
            $slug = $post['slug']; // keep existing slug on edit
            $stmt = db()->prepare('UPDATE blog_posts SET title=?, slug=?, excerpt=?, body=?, cover_image_path=?, post_type=?, corridor_id=?, status=?, published_at=?, meta_description=? WHERE id=?');
            $stmt->execute([$title, $slug, $excerpt, $body, $coverPath, $postType, $corridorId, $status, $publishedAt, $metaDescription ?: null, $post['id']]);
            $postId = $post['id'];
        } else {
            $slug = slugify($title) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            $stmt = db()->prepare('INSERT INTO blog_posts (title, slug, excerpt, body, cover_image_path, post_type, corridor_id, author_id, status, published_at, meta_description) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$title, $slug, $excerpt, $body, $coverPath, $postType, $corridorId, $admin['id'], $status, $publishedAt, $metaDescription ?: null]);
            $postId = (int)db()->lastInsertId();
        }
        if (($_POST['action'] ?? 'close') === 'new') {
            header('Location: /admin/blog-form.php?saved=1');
        } else {
            header('Location: /admin/blog-list.php?saved=1&id=' . $postId . '&new=' . ($isNew ? '1' : '0'));
        }
        exit;
    }

    $post = [
        'id' => $post['id'] ?? null, 'title' => $title, 'excerpt' => $excerpt, 'body' => $body,
        'post_type' => $postType, 'corridor_id' => $corridorId, 'status' => $status,
        'meta_description' => $metaDescription, 'cover_image_path' => $coverPath,
    ];
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-topbar">
  <h1><?= $post && !empty($post['id']) ? 'Edit Post' : 'Add Post' ?></h1>
  <a href="/admin/blog-list.php" class="btn btn-navy btn-sm">← Back</a>
</div>

<?php if (isset($_GET['saved'])): ?><div class="form-note success">Post saved successfully. Add another below, or head back to the list.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="form-note error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="form-field"><label for="bf-title">Title<span class="field-required-mark">*</span></label><input type="text" id="bf-title" name="title" required value="<?= e($post['title'] ?? '') ?>"></div>
    <div class="form-field"><label for="bf-excerpt">Excerpt<span class="field-required-mark">*</span></label><textarea id="bf-excerpt" name="excerpt" required><?= e($post['excerpt'] ?? '') ?></textarea></div>

    <div style="display:flex; gap:16px;">
      <div class="form-field" style="flex:1">
        <label for="bf-type">Type</label>
        <select id="bf-type" name="post_type">
          <?php foreach ($postTypes as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= (($post['post_type'] ?? 'article') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field" style="flex:1">
        <label for="bf-corridor">Corridor<span class="field-badge-optional">Optional</span></label>
        <select id="bf-corridor" name="corridor_id">
          <option value="">— None —</option>
          <?php foreach ($corridors as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (isset($post['corridor_id']) && $post['corridor_id'] == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field" style="flex:1">
        <label for="bf-status">Status</label>
        <select id="bf-status" name="status">
          <?php foreach ($statuses as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= (($post['status'] ?? 'draft') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-field">
      <label for="bf-body-editor">Body<span class="field-required-mark">*</span></label>
      <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
      <div id="bf-body-editor" style="background:#fff;"></div>
      <textarea id="bf-body" name="body" required style="display:none;"><?= e($post['body'] ?? '') ?></textarea>
      <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
      <script>
        (function () {
          var textarea = document.getElementById('bf-body');
          var AlignStyle = Quill.import('attributors/style/align');
          Quill.register(AlignStyle, true);

          var quill = new Quill('#bf-body-editor', {
            theme: 'snow',
            modules: {
              toolbar: {
                container: [
                  [{ header: [2, 3, 4, false] }],
                  ['bold', 'italic', 'underline'],
                  ['blockquote'],
                  [{ list: 'ordered' }, { list: 'bullet' }],
                  [{ align: [] }],
                  ['link', 'image'],
                  ['clean'],
                ],
                handlers: {
                  image: function () {
                    var url = window.prompt('Image URL (https://...)');
                    if (!url) return;
                    var range = this.quill.getSelection(true);
                    this.quill.insertEmbed(range.index, 'image', url, 'user');
                  },
                },
              },
            },
          });

          if (textarea.value) quill.root.innerHTML = textarea.value;
          quill.on('text-change', function () { textarea.value = quill.root.innerHTML; });
          textarea.closest('form').addEventListener('submit', function () { textarea.value = quill.root.innerHTML; });
        })();
      </script>
    </div>

    <div class="form-field"><label for="bf-meta">Meta description (for search results)<span class="field-badge-optional">Optional</span></label><input type="text" id="bf-meta" name="meta_description" value="<?= e($post['meta_description'] ?? '') ?>"></div>

    <div class="form-field">
      <label for="bf-cover">Cover Image<span class="field-badge-optional">Optional</span> <?= !empty($post['cover_image_path']) ? '(replace existing)' : '' ?></label>
      <?php if (!empty($post['cover_image_path'])): ?>
        <img class="thumb" style="width:64px;height:64px;margin-bottom:8px;" src="<?= UPLOAD_URL . e($post['cover_image_path']) ?>" alt="">
      <?php endif; ?>
      <input type="file" id="bf-cover" name="cover_image" accept=".jpg,.jpeg,.png,.webp">
    </div>

    <div style="display:flex; gap:10px;">
      <button type="submit" name="action" value="close" class="btn btn-primary">Save &amp; Close</button>
      <button type="submit" name="action" value="new" class="btn btn-navy">Save &amp; Add New</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
