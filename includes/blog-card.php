<?php
/** Expects $post (blog_posts row, optionally joined with corridor_name) and optional $i in scope. */
$i = $i ?? 0;
$typeLabels = ['article' => 'Article', 'market-report' => 'Market Report', 'guide' => 'Guide', 'case-study' => 'Case Study'];
?>
<a class="card-property reveal" href="/blog-post.php?slug=<?= e($post['slug']) ?>" style="--i:<?= $i % 8 ?>" data-cursor="Read">
  <div class="card-media">
    <span class="card-badge"><?= e($typeLabels[$post['post_type']] ?? 'Article') ?></span>
    <?php if (!empty($post['cover_image_path'])): ?>
      <img src="<?= UPLOAD_URL . e($post['cover_image_path']) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
    <?php else: ?>
      <img src="<?= property_placeholder_svg($post['title']) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
    <?php endif; ?>
  </div>
  <div class="card-body">
    <h3><?= e($post['title']) ?></h3>
    <div class="card-meta"><?= e(date('d M Y', strtotime($post['published_at'] ?? $post['created_at']))) ?><?= !empty($post['author_name']) ? ' · ' . e($post['author_name']) : '' ?><?= !empty($post['corridor_name']) ? ' · ' . e($post['corridor_name']) : '' ?></div>
    <p style="font-size:13.5px; color:var(--ink-soft); margin:2px 0 0;"><?= e($post['excerpt']) ?></p>
  </div>
</a>
