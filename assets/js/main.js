document.addEventListener('DOMContentLoaded', function () {
  var hasGSAP = typeof gsap !== 'undefined';

  // Safety net: if the CDN library fails to load, force everything visible
  // instead of leaving .reveal elements permanently hidden.
  if (!hasGSAP) {
    document.querySelectorAll('.reveal, .reveal-stagger > *').forEach(function (el) {
      el.style.opacity = '1';
    });
  }

  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var isFinePointer = window.matchMedia('(pointer: fine)').matches;

  /* Scrolling is native — no smooth-scroll library. Lenis (previously used
     here) re-simulates scrolling in JS with roughly a 1-second easing lag
     behind the real input and, on Windows trackpads, only sees a fraction of
     the native gesture's distance. That's what made scrolled-in content look
     like it was trailing behind the scroll, and made a normal trackpad swipe
     barely move the page. Removing it hands scrolling back to the browser,
     which has neither problem. */

  /* ---------------- Header: scroll shadow + hide-on-scroll-down ----------------
     Uses transform, not margin-top, to hide the header: margin-top is part of
     document flow, so animating it on a `position: sticky` header shifted every
     bit of content below it on every scroll tick — which moved scrollY itself,
     which re-fired this same handler, which flipped the margin back, producing
     a visible up/down vibration in sync with Lenis's smoothed scroll.
     transform doesn't touch layout at all, so it can't feed back into scroll
     position — but a `transform` on an ancestor creates a new containing block
     for any `position:fixed` descendant (the mobile .main-nav overlay lives
     inside .site-header), which would break the mobile menu's full-viewport
     positioning. That fixed positioning only applies ≤960px (see style.css),
     so the hide-on-scroll effect is scoped to wider viewports, where no such
     descendant exists. */
  var header = document.querySelector('.site-header');
  if (header) {
    var lastY = window.scrollY;
    var headerTicking = false;
    var onScroll = function () {
      var y = window.scrollY;
      header.classList.toggle('scrolled', y > 8);
      if (window.matchMedia('(min-width: 961px)').matches) {
        if (y > lastY && y > 160) {
          header.style.transform = 'translateY(-100%)';
        } else {
          header.style.transform = 'translateY(0)';
        }
      } else {
        header.style.transform = '';
      }
      lastY = y;
      headerTicking = false;
    };
    header.style.transition = 'transform 0.4s ' + (hasGSAP ? 'cubic-bezier(0.22,1,0.36,1)' : 'ease');
    onScroll();
    // rAF-throttled: Lenis can fire many native scroll events per animation frame,
    // and running this on every one of them (on top of the progress bar's own
    // listener in premium-ui.js) was doubling up work per frame.
    window.addEventListener('scroll', function () {
      if (!headerTicking) {
        headerTicking = true;
        requestAnimationFrame(onScroll);
      }
    }, { passive: true });
  }

  /* ---------------- Mobile nav toggle ---------------- */
  var navToggle = document.querySelector('.nav-toggle');
  var mainNav = document.querySelector('.main-nav');
  if (navToggle && mainNav) {
    var setMobileNavOpen = function (open) {
      mainNav.classList.toggle('open', open);
      document.body.classList.toggle('nav-open', open);
      document.body.style.overflow = open ? 'hidden' : '';
    };
    navToggle.addEventListener('click', function () {
      setMobileNavOpen(!mainNav.classList.contains('open'));
    });
    mainNav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () { setMobileNavOpen(false); });
    });
  }

  /* ---------------- Hero canvas: dot field -> "Kutumb" -> skyline ---------------- */
  var heroCanvasEl = document.getElementById('hero-canvas');
  var heroIntroDone = Promise.resolve();

  if (heroCanvasEl && typeof HeroCanvas !== 'undefined' && hasGSAP) {
    var hc = new HeroCanvas(heroCanvasEl);

    if (prefersReducedMotion) {
      // Skip the multi-second sequence — render one static, fully-settled frame.
      hc.setPhase({ ambient: 1, text: 1, growth: 1 });
      hc.render(0);
    } else {
      hc.start();
      var introTl = gsap.timeline();
      var p = { ambient: 0, text: 0, growth: 0 };
      introTl
        .to(p, { ambient: 1, duration: 1.3, ease: 'power1.out', onUpdate: function () { hc.setPhase(p); } })
        .to(p, { text: 1, duration: 1.4, ease: 'power2.inOut', onUpdate: function () { hc.setPhase(p); } }, '+=0.2')
        .to(p, { growth: 1, duration: 2.1, ease: 'power2.inOut', onUpdate: function () { hc.setPhase(p); } }, '+=0.35');

      heroIntroDone = new Promise(function (resolve) { introTl.eventCallback('onComplete', resolve); });

      var heroSection = document.querySelector('.hero');
      if (heroSection && 'IntersectionObserver' in window) {
        var heroIO = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) { entry.isIntersecting ? hc.start() : hc.stop(); });
        }, { threshold: 0 });
        heroIO.observe(heroSection);
      }
    }
  }

  if (!hasGSAP || prefersReducedMotion) return;

  /* ---------------- Hero content entrance (plays once the canvas intro settles) ---------------- */
  var heroHeading = document.querySelector('.hero h1');
  var heroLede = document.querySelector('.hero p.lede');
  var heroEyebrow = document.querySelector('.hero .eyebrow');
  var heroCta = document.querySelector('.hero-cta');
  var searchPanel = document.querySelector('.search-panel');
  var split = (heroHeading && typeof SplitType !== 'undefined') ? new SplitType(heroHeading, { types: 'words', wordClass: 'split-word' }) : null;

  if (split) gsap.set(split.words, { clipPath: 'inset(0 100% 0 0)' });
  else if (heroHeading) gsap.set(heroHeading, { opacity: 0, y: 24 });
  if (heroEyebrow) gsap.set(heroEyebrow, { opacity: 0, y: 12 });
  if (heroLede) gsap.set(heroLede, { opacity: 0, y: 20 });
  if (heroCta) gsap.set(heroCta, { opacity: 0, y: 16 });
  if (searchPanel) gsap.set(searchPanel, { opacity: 0, y: 24, scale: 0.97 });

  heroIntroDone.then(function () {
    var heroTl = gsap.timeline({ defaults: { ease: 'power3.out' } });
    if (heroEyebrow) heroTl.to(heroEyebrow, { opacity: 1, y: 0, duration: 0.5 });
    if (split) heroTl.to(split.words, { clipPath: 'inset(0 0% 0 0)', duration: 0.9, stagger: 0.08, ease: 'power4.inOut' }, '-=0.15');
    else if (heroHeading) heroTl.to(heroHeading, { opacity: 1, y: 0, duration: 0.8 }, '-=0.15');
    if (heroLede) heroTl.to(heroLede, { opacity: 1, y: 0, duration: 0.7 }, '-=0.5');
    if (heroCta) heroTl.to(heroCta, { opacity: 1, y: 0, duration: 0.6 }, '-=0.4');
    if (searchPanel) heroTl.to(searchPanel, { opacity: 1, y: 0, scale: 1, duration: 0.7 }, '-=0.35');
  });

  /* ---------------- Scroll-triggered reveals ----------------
     IntersectionObserver, not GSAP ScrollTrigger: ScrollTrigger's `start: 'top 85%'`
     bakes in a pixel position computed from the viewport height at the moment it's
     set up. On mobile, the address bar hiding/showing changes that height mid-scroll,
     so the baked-in position goes stale — cards and section headers would scroll past
     where they were supposed to reveal and stay stuck at opacity:0 (just background
     visible) until a later resize happened to trigger a recalculation. An
     IntersectionObserver has no baked-in position — it checks real, current visibility
     every time, so it can't go stale like that. */
  if ('IntersectionObserver' in window) {
    var revealObserver = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        if (el.classList.contains('reveal-stagger')) {
          gsap.fromTo(el.children, { opacity: 0, y: 28 }, { opacity: 1, y: 0, duration: 0.7, ease: 'power3.out', stagger: 0.08 });
        } else {
          gsap.fromTo(el, { opacity: 0, y: 32 }, { opacity: 1, y: 0, duration: 0.9, ease: 'power3.out' });
        }
        obs.unobserve(el);
      });
    }, { threshold: 0, rootMargin: '0px 0px 15% 0px' });

    document.querySelectorAll('.reveal').forEach(function (el) {
      if (el.closest('.hero')) return; // hero handled by its own entrance timeline
      revealObserver.observe(el);
    });
    document.querySelectorAll('.reveal-stagger').forEach(function (group) {
      if (group.children.length) revealObserver.observe(group);
    });
  } else {
    document.querySelectorAll('.reveal, .reveal-stagger > *').forEach(function (el) {
      el.style.opacity = '1';
    });
  }

  /* ---------------- Corridor strip: hover + vertical scroll moves it sideways ----------------
     An earlier version pinned the whole page and hijacked scroll globally —
     that's gone. This is scoped instead: the listener lives on the strip
     itself, so it only ever fires while the cursor is actually over it.
     Move the cursor off, and nothing here runs — the page scrolls normally,
     immediately. Even while hovering, once the strip has reached its last or
     first card, the same scroll gesture falls straight through to the page
     instead of trying to keep intercepting it — so it can't trap anyone
     mid-strip either. Desktop/mouse only: touch visitors already swipe the
     strip directly, which needs no interception at all. */
  var corridorTrack = document.querySelector('.corridor-journey-track');
  if (corridorTrack && isFinePointer) {
    var corridorSnapTimer = null;
    corridorTrack.addEventListener('wheel', function (e) {
      var atStart = corridorTrack.scrollLeft <= 0;
      var atEnd = corridorTrack.scrollLeft >= corridorTrack.scrollWidth - corridorTrack.clientWidth - 1;
      if ((e.deltaY < 0 && atStart) || (e.deltaY > 0 && atEnd)) return; // let the page scroll through
      e.preventDefault();
      // CSS scroll-snap on this track fights small JS-driven scrollLeft nudges —
      // it kept snapping straight back to the current card after every tick,
      // netting zero movement. Suspend snapping only while ticks are actively
      // arriving, and let it settle back into alignment once the gesture ends.
      corridorTrack.style.scrollSnapType = 'none';
      corridorTrack.scrollLeft += e.deltaY;
      clearTimeout(corridorSnapTimer);
      corridorSnapTimer = setTimeout(function () {
        corridorTrack.style.scrollSnapType = '';
      }, 150);
    }, { passive: false });
  }

  /* ---------------- Animated stat counters ---------------- */
  gsap.utils.toArray('.stat-row .num').forEach(function (el) {
    var text = el.textContent.trim();
    var match = text.match(/^(\d+)(.*)$/);
    if (!match) return;
    var target = parseInt(match[1], 10);
    var suffix = match[2] || '';
    if (!('IntersectionObserver' in window)) return;
    var counter = { val: 0 };
    el.setAttribute('data-count', '1');
    var statIO = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        gsap.to(counter, {
          val: target, duration: 1.4, ease: 'power2.out',
          onUpdate: function () { el.textContent = Math.round(counter.val) + suffix; },
        });
        obs.unobserve(el);
      });
    }, { threshold: 0, rootMargin: '0px 0px 15% 0px' });
    statIO.observe(el);
  });

  /* ---------------- Magnetic buttons (desktop only) ---------------- */
  if (isFinePointer) {
    document.querySelectorAll('.btn-primary, .btn-navy').forEach(function (btn) {
      var xTo = gsap.quickTo(btn, 'x', { duration: 0.4, ease: 'power3' });
      var yTo = gsap.quickTo(btn, 'y', { duration: 0.4, ease: 'power3' });
      btn.addEventListener('mousemove', function (e) {
        var rect = btn.getBoundingClientRect();
        xTo((e.clientX - rect.left - rect.width / 2) * 0.25);
        yTo((e.clientY - rect.top - rect.height / 2) * 0.25);
      });
      btn.addEventListener('mouseleave', function () { xTo(0); yTo(0); });
    });
  }

  /* ---------------- Chat toggle: gentle attention pulse until first use ---------------- */
  var chatToggle = document.getElementById('chat-toggle');
  if (chatToggle) {
    var pulse = gsap.to(chatToggle, { scale: 1.08, duration: 0.9, repeat: -1, yoyo: true, ease: 'sine.inOut', delay: 3 });
    chatToggle.addEventListener('click', function () { pulse.kill(); gsap.set(chatToggle, { scale: 1 }); }, { once: true });
  }

  /* ---------------- Category tab filter (AJAX + GSAP swap, URL-state-aware) ---------------- */
  var tabs = document.querySelectorAll('.search-tab[data-category]');
  var grid = document.getElementById('property-grid');
  if (tabs.length && grid) {
    var activateTab = function (category) {
      tabs.forEach(function (t) { t.classList.toggle('active', t.getAttribute('data-category') === category); });
      var heroSearchCategory = document.getElementById('hero-search-category');
      if (heroSearchCategory) heroSearchCategory.value = category === 'all' ? '' : category;
    };

    var loadCategory = function (category, pushState) {
      activateTab(category);
      if (pushState) {
        var url = new URL(window.location.href);
        if (category === 'all') url.searchParams.delete('category');
        else url.searchParams.set('category', category);
        history.pushState({ category: category }, '', url);
      }

      gsap.to(grid, {
        opacity: 0, y: 12, duration: 0.25, ease: 'power2.in',
        onComplete: function () {
          fetch('/api/filter-properties.php?category=' + encodeURIComponent(category))
            .then(function (res) { return res.text(); })
            .then(function (html) {
              grid.innerHTML = html;
              document.dispatchEvent(new CustomEvent('kutumb:cards-updated'));
              gsap.fromTo(grid, { opacity: 0, y: 12 }, { opacity: 1, y: 0, duration: 0.35, ease: 'power2.out' });
              gsap.fromTo(grid.children, { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.5, stagger: 0.06, ease: 'power3.out', delay: 0.05 });
            })
            .catch(function () {
              gsap.to(grid, { opacity: 1, y: 0, duration: 0.3 });
            });
        },
      });
    };

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        loadCategory(tab.getAttribute('data-category'), true);
      });
    });

    window.addEventListener('popstate', function () {
      var urlCategory = new URL(window.location.href).searchParams.get('category') || 'all';
      loadCategory(urlCategory, false);
    });

    var initialCategory = new URL(window.location.href).searchParams.get('category');
    if (initialCategory) loadCategory(initialCategory, false);
  }
});

/* Campaign context: remember utm_* from the landing URL for this visit and attach it to every public lead form
   (all of them carry the honeypot field), so the enquiry says which campaign it came from. */
document.addEventListener('DOMContentLoaded', function () {
  var KEY = 'kutumb_campaign';
  var stored = '';
  try {
    var p = new URLSearchParams(window.location.search);
    var bits = ['utm_source', 'utm_medium', 'utm_campaign'].map(function (k) { return p.get(k) || ''; });
    if (bits.some(Boolean)) {
      stored = bits.join('/').replace(/\/+$/, '').slice(0, 80);
      sessionStorage.setItem(KEY, stored);
    } else {
      stored = sessionStorage.getItem(KEY) || '';
    }
  } catch (e) { /* storage blocked: skip campaign tagging */ }
  if (!stored) return;
  document.querySelectorAll('input[name="company_website"]').forEach(function (hp) {
    var form = hp.form;
    if (!form || form.querySelector('input[name="campaign"]')) return;
    var f = document.createElement('input');
    f.type = 'hidden'; f.name = 'campaign'; f.value = stored;
    form.appendChild(f);
  });
});
