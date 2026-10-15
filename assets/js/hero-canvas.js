/**
 * Home hero background: blue night sky, small house icons that fly TOWARD the viewer.
 *
 * Life of one home (a "slot" on the screen):
 *   1. appears as a tiny home part-way out in the sky (far away),
 *   2. flies toward the viewer, growing, with a tapered comet trail of its last ~4.5 s,
 *   3. never leaves the screen: it arrives, its window lights up warm, a soft ripple marks
 *      the landing, and it slowly fades away in place, the trail shrinking back into it,
 *   4. as soon as it has faded, the next home starts again from far away.
 *
 * Look: deep navy sky with slow-drifting nebula light (brand blue + a breath of gold) and faint
 * fixed, twinkling star dust for depth (nothing falls, so it never reads as snow). The sky is
 * quieted behind the headline so the text stays easy to read.
 *
 * Even coverage: arrival spots come from a low-discrepancy (R2) sequence with a slight outward
 * bias, and every slot has a staggered phase, so no side of the screen looks heavier.
 * Same API as the old hero canvas (setPhase / render / start / stop).
 */
(function () {
  var TAU = Math.PI * 2;
  function frac(v) { return v - Math.floor(v); }
  function clamp01(x) { return x < 0 ? 0 : x > 1 ? 1 : x; }
  function smooth(a, b, x) { var t = clamp01((x - a) / (b - a)); return t * t * (3 - 2 * t); }
  function rng(seed) { return function () { seed = (seed * 16807) % 2147483647; return seed / 2147483647; }; }

  // Roof chevron echoes the Kutumb logo mark. Door cut-out; the window is either cut out
  // (on its way) or glowing warm (arrived: the lights are on at home).
  function houseSprite(size, rgb, lit) {
    var c = document.createElement('canvas');
    c.width = c.height = size;
    var g = c.getContext('2d');
    var s = size * 0.5, ox = size / 2, oy = size / 2 + s * 0.04;
    g.shadowColor = 'rgba(' + rgb + ',.9)';
    g.shadowBlur = size * 0.14;
    g.fillStyle = 'rgba(' + rgb + ',1)';
    g.beginPath();
    g.moveTo(ox, oy - s * 0.55);
    g.lineTo(ox + s * 0.55, oy - s * 0.02);
    g.lineTo(ox + s * 0.38, oy - s * 0.02);
    g.lineTo(ox + s * 0.38, oy + s * 0.48);
    g.lineTo(ox - s * 0.38, oy + s * 0.48);
    g.lineTo(ox - s * 0.38, oy - s * 0.02);
    g.lineTo(ox - s * 0.55, oy - s * 0.02);
    g.closePath();
    g.fill();
    g.shadowBlur = 0;
    g.globalCompositeOperation = 'destination-out';
    g.fillRect(ox - s * 0.25, oy + s * 0.18, s * 0.18, s * 0.30);   // door
    g.fillRect(ox + s * 0.06, oy + s * 0.08, s * 0.20, s * 0.17);   // window
    if (lit) {
      g.globalCompositeOperation = 'source-over';
      g.shadowColor = 'rgba(255,196,110,1)';
      g.shadowBlur = size * 0.22;
      g.fillStyle = 'rgba(255,214,140,1)';
      g.fillRect(ox + s * 0.06, oy + s * 0.08, s * 0.20, s * 0.17);
    }
    return c;
  }

  function glowDot(size, rgb) {
    var c = document.createElement('canvas');
    c.width = c.height = size;
    var g = c.getContext('2d'), r = size / 2;
    var grd = g.createRadialGradient(r, r, 0, r, r, r);
    grd.addColorStop(0, 'rgba(' + rgb + ',1)');
    grd.addColorStop(0.25, 'rgba(' + rgb + ',.45)');
    grd.addColorStop(1, 'rgba(' + rgb + ',0)');
    g.fillStyle = grd;
    g.fillRect(0, 0, size, size);
    return c;
  }

  var WHITE = '235,242,255', GOLD = '226,190,110', TRAIL = '170,200,255';
  // R2 low-discrepancy constants: even, non-clumping coverage.
  var B1 = 0.7548776662, B2 = 0.5698402910;     // 2-D: arrival spots
  var A3 = 0.5497004779;                        // staggered phases

  var FLIGHT = 6.4;      // seconds flying toward the viewer
  var FADE = 2.8;        // seconds fading away in place
  var TRAIL_SECS = 4.5;  // how much of the path the trail shows
  var POW = 0.85;        // slight outward bias of arrival spots (balances homes in flight)

  function HeroCanvas(canvas) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    this.dpr = Math.min(window.devicePixelRatio || 1, 2);
    this.phase = { ambient: 0, text: 0, growth: 0 };
    this.running = false;
    this._raf = null;
    this._t0 = null;
    this.house = houseSprite(96, WHITE, false);
    this.houseGold = houseSprite(96, GOLD, false);
    this.lit = houseSprite(96, WHITE, true);
    this.litGold = houseSprite(96, GOLD, true);
    this.glow = glowDot(32, '220,230,255');
    this.glowWarm = glowDot(32, '255,205,130');
    this.reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    this._resizeHandler = this.resize.bind(this);
    window.addEventListener('resize', this._resizeHandler);
    this.resize();
  }

  HeroCanvas.prototype.resize = function () {
    var rect = this.canvas.getBoundingClientRect();
    this.width = rect.width;
    this.height = rect.height;
    this.canvas.width = Math.max(1, rect.width * this.dpr);
    this.canvas.height = Math.max(1, rect.height * this.dpr);
    this.ctx.setTransform(this.dpr, 0, 0, this.dpr, 0, 0);
    this.build();
  };

  HeroCanvas.prototype.build = function () {
    var rand = rng(23);
    var area = Math.max(0.5, Math.min((this.width * this.height) / (1280 * 720), 1.8));
    this.n = Math.round(175 * area);
    // Jittered grid: one home per cell, random spot inside its cell each time it comes back.
    // Random-looking like rain, yet no part of the screen is ever empty or crowded.
    this.cols = Math.max(4, Math.round(Math.sqrt(this.n * this.width / Math.max(1, this.height))));
    this.rows = Math.max(3, Math.ceil(this.n / this.cols));
    var order = [];
    for (var c0 = 0; c0 < this.cols * this.rows; c0++) order.push(c0);
    for (var o = order.length - 1; o > 0; o--) { var r0 = Math.floor(rand() * (o + 1)), tmp = order[o]; order[o] = order[r0]; order[r0] = tmp; }
    this.n = this.cols * this.rows;
    this.homes = [];
    for (var i = 0; i < this.n; i++) {
      var flight = FLIGHT * (0.75 + rand() * 0.5);
      this.homes.push({
        i: i,
        cell: order[i],
        phase: rand(),
        flight: flight,
        len: flight + FADE + 0.3,
        s0: 4 + rand() * 2,              // size when it first appears (far away)
        k0: 0.86 + rand() * 0.1,         // appears almost where it lands: drops in like rain, slight outward drift
        sBase: 24 + rand() * 22,         // size on arrival
        a: 0.78 + rand() * 0.22,
        gold: rand() < 0.24,
        lean: (rand() - 0.5) * 0.24,
        wp: rand() * TAU, rs: 0.25 + rand() * 0.35,
      });
    }
    // Faint, fixed, twinkling star dust far behind the homes (depth; nothing falls).
    this.stars = [];
    for (var si = 0, ns = Math.round(240 * area); si < ns; si++) {
      this.stars.push({ x: rand() * this.width, y: rand() * this.height, r: 0.5 + rand() * 1.0, a: 0.12 + rand() * 0.3, tw: rand() * TAU, sp: 0.4 + rand() * 1.2 });
    }
  };

  HeroCanvas.prototype.setPhase = function (patch) {
    for (var k in patch) if (Object.prototype.hasOwnProperty.call(patch, k)) this.phase[k] = patch[k];
  };

  /** Where the home is after `tf` seconds of flight (tf clamped to its flight time). */
  function onPath(h, E, C, s1, tf) {
    var x = clamp01(tf / h.flight);
    var p = Math.sin(x * Math.PI / 2);                    // gentle ease-out: it arrives softly
    var size = h.s0 * Math.pow(s1 / h.s0, p);             // constant zoom rate
    var k = h.k0 + (1 - h.k0) * p;                        // moves outward from where it appeared to its spot
    return { x: C.x + (E.x - C.x) * k, y: C.y + (E.y - C.y) * k, size: size, p: p };
  }

  /** Full state of one home at time t (head, tail of the trail, alpha). */
  HeroCanvas.prototype.stateAt = function (h, t) {
    var W = this.width, H = this.height;
    var u = t / h.len + h.phase, cycle = Math.floor(u), tl = (u - cycle) * h.len;
    var C = { x: W / 2, y: H / 2 };
    // random spot inside this home's cell, fresh every cycle (hash of slot + cycle)
    var hx = frac(Math.sin((h.i + 1) * 12.9898 + cycle * 78.233) * 43758.5453);
    var hy = frac(Math.sin((h.i + 1) * 39.3468 + cycle * 11.135) * 24634.6345);
    var cx = h.cell % this.cols, cy = Math.floor(h.cell / this.cols);
    var E = {
      x: ((cx + 0.08 + 0.84 * hx) / this.cols) * W,
      y: ((cy + 0.08 + 0.84 * hy) / this.rows) * H,
    };
    var dist = Math.min(1, Math.hypot((E.x - C.x) / (W / 2), (E.y - C.y) / (H / 2)));
    var s1 = h.sBase * (0.85 + 0.3 * dist);

    var tf = Math.min(tl, h.flight);
    var head = onPath(h, E, C, s1, tf);
    var fadeP = tl > h.flight ? clamp01((tl - h.flight) / FADE) : 0;
    var appear = smooth(0.0, 1.8, tl);
    var alive = 1 - smooth(0.0, 1, fadeP);
    var alpha = h.a * appear * alive * (1 - 0.25 * head.p);

    // Trail: the last TRAIL_SECS of the path; while fading it shrinks back into the home.
    var tailT = Math.max(0, tf - TRAIL_SECS);
    tailT = tailT + (tf - tailT) * smooth(0, 1, fadeP);
    var tail = onPath(h, E, C, s1, tailT);
    return { head: head, tail: tail, size: head.size, alpha: alpha, trailAlpha: h.a * appear * alive, q: head.p, tl: tl, fadeP: fadeP };
  };

  HeroCanvas.prototype.render = function (time) {
    var ctx = this.ctx, W = this.width, H = this.height;
    if (this._t0 === null) this._t0 = time;
    var t = (time - this._t0) / 1000 + (this.reduced ? 9 : 0);
    var fade = this.phase.ambient || 0;
    var S = Math.max(W, H);

    // --- Sky: deep navy with slow-drifting nebula light ---
    ctx.globalCompositeOperation = 'source-over';
    ctx.globalAlpha = 1;
    var bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, '#02040b');
    bg.addColorStop(0.5, '#050a1a');
    bg.addColorStop(1, '#0a1430');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);
    var neb = [
      [0.78 + 0.03 * Math.sin(t * 0.05), 0.30 + 0.02 * Math.cos(t * 0.04), 0.55, '46,84,180', 0.24],
      [0.16 + 0.03 * Math.cos(t * 0.045), 0.18, 0.45, '72,64,160', 0.14],
      [0.64, 0.98 + 0.02 * Math.sin(t * 0.06), 0.50, '201,160,84', 0.10],
      [0.50, 0.50, 0.70, '60,90,170', 0.08],
    ];
    ctx.globalCompositeOperation = 'lighter';
    for (var ni = 0; ni < neb.length; ni++) {
      var nb = neb[ni], nx = nb[0] * W, ny = nb[1] * H, nr = nb[2] * S;
      var ng = ctx.createRadialGradient(nx, ny, 0, nx, ny, nr);
      ng.addColorStop(0, 'rgba(' + nb[3] + ',' + nb[4] * fade + ')');
      ng.addColorStop(0.45, 'rgba(' + nb[3] + ',' + nb[4] * 0.35 * fade + ')');
      ng.addColorStop(1, 'rgba(' + nb[3] + ',0)');
      ctx.fillStyle = ng;
      ctx.fillRect(0, 0, W, H);
    }
    for (var sj = 0; sj < this.stars.length; sj++) {
      var st0 = this.stars[sj], tw = 0.55 + 0.45 * Math.sin(t * st0.sp + st0.tw);
      ctx.globalAlpha = st0.a * tw * fade;
      var sd = st0.r * 4;
      ctx.drawImage(this.glow, st0.x - sd / 2, st0.y - sd / 2, sd, sd);
    }

    // --- Comet trails: tapered, bright at the home, vanishing toward where it came from ---
    var states = [];
    for (var j = 0; j < this.homes.length; j++) {
      var h = this.homes[j], s = this.stateAt(h, t);
      if (s.alpha <= 0.005 && s.trailAlpha <= 0.005) continue;
      states.push([h, s]);
      var dx = s.head.x - s.tail.x, dy = s.head.y - s.tail.y, len = Math.hypot(dx, dy);
      if (len > 4 && s.trailAlpha > 0.005) {
        var col = h.gold ? GOLD : TRAIL, ta = s.trailAlpha * fade;
        var px = -dy / len, py = dx / len, w = 0.6 + s.size * 0.10;
        var g = ctx.createLinearGradient(s.tail.x, s.tail.y, s.head.x, s.head.y);
        g.addColorStop(0, 'rgba(' + col + ',0)');
        g.addColorStop(0.7, 'rgba(' + col + ',' + 0.16 * ta + ')');
        g.addColorStop(1, 'rgba(' + col + ',' + 0.42 * ta + ')');
        ctx.globalAlpha = 1;
        ctx.fillStyle = g;
        ctx.beginPath();
        ctx.moveTo(s.tail.x, s.tail.y);
        ctx.lineTo(s.head.x + px * w, s.head.y + py * w);
        ctx.lineTo(s.head.x - px * w, s.head.y - py * w);
        ctx.closePath();
        ctx.fill();
      }
    }

    // --- Homes: crisp on the way, then the window lights up warm as each one arrives ---
    for (var k = 0; k < states.length; k++) {
      var hh = states[k][0], sk = states[k][1];
      if (sk.alpha <= 0.005) continue;
      var litMix = smooth(0.82, 1.0, sk.q);
      var a = sk.alpha * fade;
      var halo = sk.size * (1.4 + 0.9 * litMix);
      ctx.globalAlpha = a * (0.08 + 0.20 * litMix);
      ctx.drawImage(litMix > 0.5 ? this.glowWarm : this.glow, sk.head.x - halo, sk.head.y - halo, halo * 2, halo * 2);
      if (false) {                                      // landing ripple removed (read as "arranged")
        var rp = sk.fadeP / 0.55;
        ctx.globalAlpha = (1 - rp) * 0.16 * sk.alpha * fade;
        ctx.strokeStyle = 'rgba(' + (hh.gold ? GOLD : '200,220,255') + ',1)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.arc(sk.head.x, sk.head.y, sk.size * (0.7 + 1.1 * rp), 0, TAU);
        ctx.stroke();
      }
      ctx.save();
      ctx.translate(sk.head.x, sk.head.y);
      ctx.rotate(hh.lean + Math.sin(t * hh.rs + hh.wp) * 0.16);
      if (litMix < 1) {
        ctx.globalAlpha = a * (1 - litMix);
        ctx.drawImage(hh.gold ? this.houseGold : this.house, -sk.size / 2, -sk.size / 2, sk.size, sk.size);
      }
      if (litMix > 0) {
        ctx.globalAlpha = a * litMix;
        ctx.drawImage(hh.gold ? this.litGold : this.lit, -sk.size / 2, -sk.size / 2, sk.size, sk.size);
      }
      ctx.restore();
    }
    ctx.globalAlpha = 1;
    ctx.globalCompositeOperation = 'source-over';

    // --- Readability: quiet the sky behind the headline and toward the bottom ---
    var tv = ctx.createRadialGradient(W * 0.27, H * 0.52, 0, W * 0.27, H * 0.52, Math.max(W * 0.42, 320));
    tv.addColorStop(0, 'rgba(2,4,11,.50)');
    tv.addColorStop(1, 'rgba(2,4,11,0)');
    ctx.fillStyle = tv;
    ctx.fillRect(0, 0, W, H);
    var v = ctx.createLinearGradient(0, H * 0.5, 0, H);
    v.addColorStop(0, 'rgba(0,0,0,0)');
    v.addColorStop(1, 'rgba(0,0,0,.5)');
    ctx.fillStyle = v;
    ctx.fillRect(0, 0, W, H);
  };

  HeroCanvas.prototype.start = function () {
    if (this.running) return;
    this.running = true;
    var self = this;
    if (this.reduced) { this.render(performance.now()); return; }
    var loop = function (t) {
      if (!self.running) return;
      self.render(t);
      self._raf = requestAnimationFrame(loop);
    };
    this._raf = requestAnimationFrame(loop);
  };

  HeroCanvas.prototype.stop = function () {
    this.running = false;
    if (this._raf) cancelAnimationFrame(this._raf);
  };

  window.HeroCanvas = HeroCanvas;
})();
