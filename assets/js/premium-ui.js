document.addEventListener('DOMContentLoaded', function () {
  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var isFinePointer = window.matchMedia('(pointer: fine)').matches;
  var isAdmin = document.body.classList.contains('admin');

  /* ---------------- Scroll progress bar (everywhere) ---------------- */
  var progress = document.createElement('div');
  progress.className = 'scroll-progress';
  document.body.appendChild(progress);
  // Measuring scrollHeight is a forced layout read — doing it on every scroll
  // event (instead of only when the page's height can actually change) was
  // the other half of the double-work-per-frame scroll jank.
  var docHeight = 0;
  function measureDocHeight() {
    docHeight = document.documentElement.scrollHeight - window.innerHeight;
  }
  var progressTicking = false;
  function updateProgress() {
    progress.style.width = (docHeight > 0 ? (window.scrollY / docHeight) * 100 : 0) + '%';
    progressTicking = false;
  }
  measureDocHeight();
  updateProgress();
  window.addEventListener('scroll', function () {
    if (!progressTicking) {
      progressTicking = true;
      requestAnimationFrame(updateProgress);
    }
  }, { passive: true });
  window.addEventListener('resize', measureDocHeight);
  window.addEventListener('load', measureDocHeight);
  document.addEventListener('kutumb:cards-updated', measureDocHeight);

  /* ---------------- One-time load curtain (public pages, once per session) ---------------- */
  if (!isAdmin && !prefersReducedMotion) {
    var shown = false;
    try { shown = !!sessionStorage.getItem('kutumb_intro_shown'); } catch (e) {}
    if (!shown) {
      var curtain = document.createElement('div');
      curtain.className = 'load-curtain texture-grain';
      curtain.innerHTML = '<span class="load-curtain-mark">Kutumb<span>Realty</span></span>';
      document.body.appendChild(curtain);
      requestAnimationFrame(function () {
        setTimeout(function () {
          curtain.classList.add('load-curtain-hide');
          setTimeout(function () { curtain.remove(); }, 700);
        }, 300);
      });
      try { sessionStorage.setItem('kutumb_intro_shown', '1'); } catch (e) {}
    }
  }

  /* ---------------- Custom cursor + card tilt (fine pointer, public pages only) ---------------- */
  if (!isFinePointer || isAdmin || prefersReducedMotion) return;

  document.body.classList.add('has-custom-cursor');

  var cursorDot = document.createElement('div');
  cursorDot.className = 'cursor-dot';
  var cursorRing = document.createElement('div');
  cursorRing.className = 'cursor-ring';
  var cursorLabel = document.createElement('div');
  cursorLabel.className = 'cursor-label';
  document.body.appendChild(cursorDot);
  document.body.appendChild(cursorRing);
  document.body.appendChild(cursorLabel);

  var mouseX = -100, mouseY = -100, ringX = -100, ringY = -100;
  document.addEventListener('mousemove', function (e) {
    mouseX = e.clientX; mouseY = e.clientY;
    cursorDot.style.transform = 'translate(' + mouseX + 'px,' + mouseY + 'px)';
  });
  (function raf() {
    ringX += (mouseX - ringX) * 0.18;
    ringY += (mouseY - ringY) * 0.18;
    var t = 'translate(' + ringX + 'px,' + ringY + 'px)';
    cursorRing.style.transform = t;
    cursorLabel.style.transform = t + ' translate(-50%, -50%)';
    requestAnimationFrame(raf);
  })();

  document.querySelectorAll('a, button, summary, .compare-toggle').forEach(function (el) {
    el.addEventListener('mouseenter', function () {
      cursorRing.classList.add('cursor-ring-active');
      var label = el.getAttribute('data-cursor');
      if (label) {
        cursorLabel.textContent = label;
        cursorLabel.classList.add('cursor-label-active');
      }
    });
    el.addEventListener('mouseleave', function () {
      cursorRing.classList.remove('cursor-ring-active');
      cursorLabel.classList.remove('cursor-label-active');
    });
  });

  /* ---------------- Cursor-tilt on property cards ---------------- */
  document.querySelectorAll('.card-property').forEach(function (card) {
    var bounds = null;
    card.addEventListener('mouseenter', function () {
      bounds = card.getBoundingClientRect();
      card.style.transition = 'transform 0.1s ease-out, box-shadow 0.45s var(--ease)';
    });
    card.addEventListener('mousemove', function (e) {
      if (!bounds) bounds = card.getBoundingClientRect();
      var px = (e.clientX - bounds.left) / bounds.width - 0.5;
      var py = (e.clientY - bounds.top) / bounds.height - 0.5;
      card.style.transform = 'perspective(800px) rotateX(' + (-py * 6) + 'deg) rotateY(' + (px * 8) + 'deg) translateY(-6px)';
    });
    card.addEventListener('mouseleave', function () {
      card.style.transition = 'transform 0.5s var(--ease), box-shadow 0.45s var(--ease)';
      card.style.transform = '';
      bounds = null;
    });
  });
});
