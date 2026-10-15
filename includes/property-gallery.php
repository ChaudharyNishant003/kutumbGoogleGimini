<?php /** Expects $images (property_images rows) and $property in scope. */ ?>
<div class="gallery-strip reveal" id="property-gallery">
  <?php foreach ($images as $idx => $img): ?>
    <button type="button" class="gallery-thumb" data-full="<?= UPLOAD_URL . e($img['image_path']) ?>" data-index="<?= $idx ?>">
      <img src="<?= image_card_url($img['image_path'], UPLOAD_URL, UPLOAD_DIR) ?>" alt="<?= e($property['name']) ?> photo <?= $idx + 1 ?>" loading="lazy">
    </button>
  <?php endforeach; ?>
</div>
<div class="gallery-lightbox" id="gallery-lightbox" aria-hidden="true">
  <button type="button" class="gallery-lightbox-close" aria-label="Close">&times;</button>
  <button type="button" class="gallery-lightbox-prev" aria-label="Previous">&larr;</button>
  <img src="" alt="" id="gallery-lightbox-img">
  <button type="button" class="gallery-lightbox-next" aria-label="Next">&rarr;</button>
</div>
