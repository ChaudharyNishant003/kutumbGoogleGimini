<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/structured-data.php';

$slug = $_GET['slug'] ?? '';

try {
    $stmt = db()->prepare("SELECT p.*, c.name AS corridor_name, c.slug AS corridor_slug, au.full_name AS author_name, tm.slug AS author_slug, tm.photo_path AS author_photo
        FROM blog_posts p
        LEFT JOIN corridors c ON c.id = p.corridor_id
        LEFT JOIN admin_users au ON au.id = p.author_id
        LEFT JOIN team_members tm ON tm.admin_user_id = p.author_id
        WHERE p.slug = ? AND p.status = 'published' AND p.deleted_at IS NULL LIMIT 1");
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
} catch (Throwable $e) {
    require __DIR__ . '/includes/error-page.php';
}

if (!$post) {
    http_response_code(404);
    $page_title = 'Post not found — Kutumb Realty';
    include __DIR__ . '/includes/header.php';
    echo '<section><div class="container"><h1>Post not found</h1><p><a href="/blog.php">Back to Insights</a></p></div></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

try {
    record_view_once('blog_posts', $post['id'], 'kutumb_viewed_post');

    $related_stmt = db()->prepare("SELECT p.*, c.name AS corridor_name, au.full_name AS author_name, tm.slug AS author_slug, tm.photo_path AS author_photo
        FROM blog_posts p
        LEFT JOIN corridors c ON c.id = p.corridor_id
        LEFT JOIN admin_users au ON au.id = p.author_id
        LEFT JOIN team_members tm ON tm.admin_user_id = p.author_id
        WHERE p.status='published' AND p.deleted_at IS NULL AND p.corridor_id <=> ? AND p.id != ? ORDER BY p.published_at DESC LIMIT 3");
    $related_stmt->execute([$post['corridor_id'], $post['id']]);
    $related = $related_stmt->fetchAll();
} catch (Throwable $e) {
    require __DIR__ . '/includes/error-page.php';
}

$page_title = $post['title'] . ' — Kutumb Realty';
$page_description = $post['meta_description'] ?: $post['excerpt'];
$structured_data = structured_data_article($post)
    . structured_data_breadcrumbs(array_values(array_filter([
        ['name' => 'Home', 'url' => '/index.php'],
        ['name' => 'Insights', 'url' => '/blog.php'],
        $post['corridor_name'] ? ['name' => $post['corridor_name'], 'url' => '/sector.php?slug=' . $post['corridor_slug']] : null,
        ['name' => $post['title'], 'url' => '/blog-post.php?slug=' . $post['slug']],
    ])));
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain" style="padding-bottom:32px;">
  <div class="container">
    <div class="breadcrumb">
      <a href="/index.php">Home</a> / <a href="/blog.php">Insights</a>
      <?php if ($post['corridor_name']): ?> / <a href="/sector.php?slug=<?= e($post['corridor_slug']) ?>"><?= e($post['corridor_name']) ?></a><?php endif; ?>
    </div>
    <h1><?= e($post['title']) ?></h1>
    <div style="display:flex; align-items:center; gap:10px; margin-top:6px;">
      <?php if (!empty($post['author_photo'])): ?>
        <img src="<?= UPLOAD_URL . e($post['author_photo']) ?>" alt="<?= e($post['author_name']) ?>" style="width:28px; height:28px; border-radius:50%; object-fit:cover;">
      <?php endif; ?>
      <p style="margin:0;">
        <?php if (!empty($post['author_name'])): ?>
          By <?php if (!empty($post['author_slug'])): ?><a href="/agent.php?slug=<?= e($post['author_slug']) ?>" style="color:inherit; text-decoration:underline;"><?= e($post['author_name']) ?></a><?php else: ?><?= e($post['author_name']) ?><?php endif; ?> ·
        <?php endif; ?>
        <?= e(date('d M Y', strtotime($post['published_at']))) ?>
        <?php if (!empty($post['updated_at']) && strtotime($post['updated_at']) - strtotime($post['published_at']) > 86400): ?> &middot; Updated <?= e(date('d M Y', strtotime($post['updated_at']))) ?><?php endif; ?>
      </p>
    </div>
  </div>
</section>

<section style="padding-top:40px;">
  <div class="container">
    <div class="two-col">
      <div>
        <?php if (!empty($post['cover_image_path'])): ?>
          <div class="gallery-hero reveal">
            <img src="<?= UPLOAD_URL . e($post['cover_image_path']) ?>" alt="<?= e($post['title']) ?>">
          </div>
        <?php endif; ?>
        <div class="reveal blog-body"><?= $post['body'] ?></div>
        <?php if (in_array($post['post_type'] ?? '', ['market-report', 'case-study'], true)): ?>
          <p class="blog-source-note">Prices, rates and timelines in this article are indicative and reflect the date it was published or last updated. Confirm current figures with an advisor before you decide.</p>
        <?php endif; ?>
      </div>
      <div class="sticky-cta">
        <div class="info-card reveal">
          <?php $postUrl = SITE_URL . '/blog-post.php?slug=' . $post['slug']; ?>
          <?php if (!empty($post['corridor_name'])): ?>
          <h4>Talk to a <?= e($post['corridor_name']) ?> specialist</h4>
          <p style="font-size:13px; margin-bottom:16px;">Have a question about <?= e($post['corridor_name']) ?> or this article? Get a straight answer from a Kutumb advisor.</p>
          <a href="<?= whatsapp_url('Hi, I read "' . $post['title'] . '" (' . $postUrl . ') and have a question about ' . $post['corridor_name'] . '.') ?>" class="btn btn-primary btn-block" target="_blank" rel="noopener">Chat on WhatsApp</a>
          <a href="/sector.php?slug=<?= e($post['corridor_slug']) ?>" class="btn btn-soft btn-block" style="margin-top:10px;">See <?= e($post['corridor_name']) ?> listings</a>
          <?php else: ?>
          <h4>Talk to an advisor</h4>
          <p style="font-size:13px; margin-bottom:16px;">Have a question about this article? Get a straight answer from a Kutumb advisor.</p>
          <a href="<?= whatsapp_url('Hi, I read "' . $post['title'] . '" (' . $postUrl . ') and have a question.') ?>" class="btn btn-primary btn-block" target="_blank" rel="noopener">Chat on WhatsApp</a>
          <?php endif; ?>
          <a href="/contact.php?ref=<?= e(rawurlencode('blog-' . substr($post['slug'], 0, 29))) ?>" class="btn btn-soft btn-block" style="margin-top:10px;">Request a callback</a>
        </div>
      </div>
    </div>

    <?php if ($related): ?>
    <div style="margin-top:72px;">
      <div class="section-head reveal">
        <div><span class="eyebrow">More Insights</span><h2>You might also like</h2></div>
      </div>
      <div class="property-grid reveal reveal-stagger">
        <?php foreach ($related as $i => $post): include __DIR__ . '/includes/blog-card.php'; endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
