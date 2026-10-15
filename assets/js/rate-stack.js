document.addEventListener('DOMContentLoaded', function () {
  var wrap = document.getElementById('rate-stack');
  if (!wrap) return;

  var corridors = [];
  try { corridors = JSON.parse(wrap.getAttribute('data-corridors') || '[]'); } catch (e) { corridors = []; }
  if (!corridors.length) return;

  var DEPTH = 3;
  var order = corridors.map(function (_, i) { return i; });
  var userControlled = false;
  var autoTimer = null;
  var dragging = false;
  var dragStartX = 0;
  var dragX = 0;
  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function pad(n, total) {
    var s = String(n);
    return s.length < String(total).length ? new Array(String(total).length - s.length + 1).join('0') + s : s;
  }

  function render() {
    wrap.innerHTML = '';
    var visible = order.slice(0, DEPTH);
    visible.forEach(function (cIdx, i) {
      var c = corridors[cIdx];
      var isFront = i === 0;
      var inset = i * 9;
      var el = document.createElement('a');
      el.className = 'rate-stack-card' + (dragging && isFront ? ' is-dragging' : '');
      el.href = '/sector.php?slug=' + encodeURIComponent(c.slug);
      if (isFront) el.setAttribute('data-cursor', 'Explore');
      el.style.width = 'calc(100% - ' + inset + 'px)';
      el.style.height = 'calc(100% - ' + inset + 'px)';
      el.style.zIndex = String(DEPTH - i);
      el.style.opacity = String(isFront ? 1 : Math.max(0.35, 0.85 - i * 0.28));
      el.style.pointerEvents = isFront ? 'auto' : 'none';
      var transform = 'translate(' + inset + 'px,' + inset + 'px)';
      if (isFront) transform = 'translate(' + dragX + 'px,0px) rotate(' + (dragX / 28) + 'deg)';
      el.style.transform = transform;

      var growthHtml = c.growth
        ? '<span class="rate-stack-growth">▲ ' + c.growth + ' this year</span>'
        : '';
      var dotsHtml = '';
      if (isFront) {
        dotsHtml = '<div class="rate-stack-dots">' + corridors.map(function (_, di) {
          return '<button type="button" class="rate-stack-dot' + (order[0] === di ? ' active' : '') + '" data-jump="' + di + '" aria-label="Show corridor"></button>';
        }).join('') + '</div>';
      }

      el.innerHTML =
        '<div class="rate-stack-card-inner">' +
          '<div>' +
            '<div class="rate-stack-eyebrow-row"><span class="rate-stack-eyebrow">Average Rate Today</span><span class="rate-stack-index">' + pad(cIdx + 1, corridors.length) + ' / ' + pad(corridors.length, corridors.length) + '</span></div>' +
            '<h3 class="rate-stack-name">' + c.name + '</h3>' +
            '<div class="rate-stack-rule"></div>' +
            '<div class="rate-stack-price-row"><span class="rate-stack-price">' + c.priceRange + '</span>' + growthHtml + '</div>' +
            '<p class="rate-stack-blurb">' + c.blurb + '</p>' +
          '</div>' +
          '<div class="rate-stack-foot"><span class="rate-stack-cta">View Corridor →</span>' + dotsHtml + '</div>' +
        '</div>';

      if (isFront) {
        el.addEventListener('pointerdown', onPointerDown);
        el.querySelectorAll('[data-jump]').forEach(function (dot) {
          dot.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            jumpTo(parseInt(dot.getAttribute('data-jump'), 10));
          });
        });
      }
      wrap.appendChild(el);
    });
  }

  function advance() {
    order = order.slice(1).concat([order[0]]);
    render();
  }

  function takeControl() {
    if (!userControlled) { userControlled = true; clearInterval(autoTimer); }
  }

  function jumpTo(idx) {
    takeControl();
    var pos = order.indexOf(idx);
    if (pos <= 0) return;
    order = order.slice(pos).concat(order.slice(0, pos));
    render();
  }

  function onPointerDown(e) {
    e.preventDefault();
    takeControl();
    dragging = true;
    dragStartX = e.clientX;
    dragX = 0;
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', onPointerUp);
  }
  function onPointerMove(e) {
    if (!dragging) return;
    dragX = e.clientX - dragStartX;
    render();
  }
  function onPointerUp() {
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', onPointerUp);
    var moved = Math.abs(dragX) > 70;
    dragging = false;
    dragX = 0;
    if (moved) advance();
    else render();
  }

  render();
  if (!prefersReducedMotion) {
    autoTimer = setInterval(function () { if (!userControlled) advance(); }, 3500);
  }
});
