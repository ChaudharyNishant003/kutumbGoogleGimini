document.addEventListener('DOMContentLoaded', function () {
  var strip = document.getElementById('property-gallery');
  var lightbox = document.getElementById('gallery-lightbox');
  if (!strip || !lightbox) return;

  var thumbs = Array.prototype.slice.call(strip.querySelectorAll('.gallery-thumb'));
  if (!thumbs.length) return;

  var lightboxImg = document.getElementById('gallery-lightbox-img');
  var closeBtn = lightbox.querySelector('.gallery-lightbox-close');
  var prevBtn = lightbox.querySelector('.gallery-lightbox-prev');
  var nextBtn = lightbox.querySelector('.gallery-lightbox-next');
  var current = 0;

  function open(index) {
    current = index;
    lightboxImg.src = thumbs[current].getAttribute('data-full');
    lightbox.classList.add('open');
    lightbox.setAttribute('aria-hidden', 'false');
  }

  function close() {
    lightbox.classList.remove('open');
    lightbox.setAttribute('aria-hidden', 'true');
  }

  function step(delta) {
    current = (current + delta + thumbs.length) % thumbs.length;
    lightboxImg.src = thumbs[current].getAttribute('data-full');
  }

  thumbs.forEach(function (thumb, idx) {
    thumb.addEventListener('click', function () { open(idx); });
  });
  closeBtn.addEventListener('click', close);
  prevBtn.addEventListener('click', function () { step(-1); });
  nextBtn.addEventListener('click', function () { step(1); });
  lightbox.addEventListener('click', function (e) { if (e.target === lightbox) close(); });
  document.addEventListener('keydown', function (e) {
    if (!lightbox.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') step(-1);
    if (e.key === 'ArrowRight') step(1);
  });
});
