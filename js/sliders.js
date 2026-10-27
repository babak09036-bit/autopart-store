/* Reusable sliders: a full-width hero slider and scroll-snap product carousels (RTL aware). */
(function () {
  'use strict';

  var AP = window.AP;
  var isRTL = document.documentElement.dir === 'rtl';
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /**
   * Hero slider. Markup: [data-hero] > .hero__track > .hero__slide*, [data-prev], [data-next], .hero__dots
   */
  function HeroSlider(root, options) {
    var opts = options || {};
    var track = root.querySelector('.hero__track');
    var slides = Array.prototype.slice.call(root.querySelectorAll('.hero__slide'));
    var dotsWrap = root.querySelector('.hero__dots');
    var interval = opts.interval || 5000;
    var index = 0, timer = null, paused = false;
    if (!track || slides.length < 2) return;

    var dots = slides.map(function (slide, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'hero__dot';
      b.setAttribute('aria-label', 'نمایش اسلاید ' + AP.utils.faDigits(i + 1));
      b.addEventListener('click', function () { go(i); restart(); });
      dotsWrap.appendChild(b);
      return b;
    });

    function go(i) {
      index = (i + slides.length) % slides.length;
      track.style.transform = 'translateX(' + (isRTL ? index * 100 : -index * 100) + '%)';
      slides.forEach(function (s, k) {
        var active = k === index;
        s.setAttribute('aria-hidden', String(!active));
        if (active) s.removeAttribute('inert'); else s.setAttribute('inert', '');
      });
      dots.forEach(function (d, k) { d.setAttribute('aria-current', k === index ? 'true' : 'false'); });
    }

    function next() { go(index + 1); }
    function prev() { go(index - 1); }

    function restart() {
      clearInterval(timer);
      if (reduceMotion || paused) return;
      timer = setInterval(next, interval);
    }

    root.querySelector('[data-next]').addEventListener('click', function () { next(); restart(); });
    root.querySelector('[data-prev]').addEventListener('click', function () { prev(); restart(); });

    root.addEventListener('mouseenter', function () { paused = true; clearInterval(timer); });
    root.addEventListener('mouseleave', function () { paused = false; restart(); });
    root.addEventListener('focusin', function () { paused = true; clearInterval(timer); });
    root.addEventListener('focusout', function () { paused = false; restart(); });

    root.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { isRTL ? next() : prev(); restart(); }
      if (e.key === 'ArrowRight') { isRTL ? prev() : next(); restart(); }
    });

    /* Touch / pen swipe through Pointer Events. */
    var startX = null, startY = null;
    track.addEventListener('pointerdown', function (e) {
      if (e.pointerType === 'mouse') return;
      startX = e.clientX; startY = e.clientY;
    });
    track.addEventListener('pointerup', function (e) {
      if (startX === null) return;
      var dx = e.clientX - startX, dy = e.clientY - startY;
      startX = null;
      if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy)) return;
      // In RTL the next slide sits to the left, so a rightward swipe reveals it.
      if ((dx > 0) === isRTL) next(); else prev();
      restart();
    });
    track.addEventListener('pointercancel', function () { startX = null; });

    go(0);
    restart();
  }

  /**
   * Product carousel. Markup: [data-carousel] > .carousel__track, optional [data-prev]/[data-next].
   * Native horizontal scrolling gives touch swipe and keyboard scrolling for free.
   */
  function Carousel(root) {
    var track = root.querySelector('.carousel__track');
    var prevBtn = root.querySelector('[data-prev]');
    var nextBtn = root.querySelector('[data-next]');
    if (!track) return;
    var dir = isRTL ? -1 : 1;

    function maxScroll() { return track.scrollWidth - track.clientWidth; }

    function update() {
      var pos = Math.abs(track.scrollLeft);
      if (prevBtn) prevBtn.disabled = pos < 4;
      if (nextBtn) nextBtn.disabled = pos >= maxScroll() - 4;
    }

    function move(step) {
      track.scrollBy({ left: dir * step * track.clientWidth * 0.85, behavior: reduceMotion ? 'auto' : 'smooth' });
    }

    if (prevBtn) prevBtn.addEventListener('click', function () { move(-1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { move(1); });
    track.addEventListener('scroll', AP.utils.debounce(update, 60), { passive: true });
    window.addEventListener('resize', AP.utils.debounce(update, 120));

    if (root.hasAttribute('data-autoplay') && !reduceMotion) {
      var hovering = false;
      root.addEventListener('mouseenter', function () { hovering = true; });
      root.addEventListener('mouseleave', function () { hovering = false; });
      setInterval(function () {
        if (hovering || document.hidden) return;
        if (Math.abs(track.scrollLeft) >= maxScroll() - 4) track.scrollTo({ left: 0, behavior: 'smooth' });
        else move(1);
      }, 6000);
    }

    update();
  }

  AP.HeroSlider = HeroSlider;
  AP.initCarousels = function (scope) {
    Array.prototype.forEach.call((scope || document).querySelectorAll('[data-carousel]'), function (el) {
      if (el.dataset.ready) return;
      el.dataset.ready = '1';
      Carousel(el);
    });
  };
})();
