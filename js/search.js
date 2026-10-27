/* Product search (shared by the header live search and search.html). */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;

  function haystack(p) {
    var cat = AP.catalog.category(p.category);
    return {
      title: u.compact(p.title),
      rest: u.compact([AP.catalog.brandName(p.brand), p.brand, p.sub, cat ? cat.name : '', p.description].join(' '))
    };
  }

  /**
   * Every query word must appear somewhere (title, brand, category, description).
   * Matching is space/half-space insensitive and treats Arabic ي/ك as Persian ی/ک.
   */
  AP.search = function (query) {
    var words = u.normalize(query).split(' ').filter(Boolean).map(u.compact);
    if (!words.length) return [];
    return AP.data.products.map(function (p) {
      var h = haystack(p);
      var score = 0;
      for (var i = 0; i < words.length; i++) {
        if (h.title.indexOf(words[i]) > -1) score += 3;
        else if (h.rest.indexOf(words[i]) > -1) score += 1;
        else return null;
      }
      if (h.title.indexOf(u.compact(query)) === 0) score += 2;
      return { p: p, score: score };
    }).filter(Boolean).sort(function (a, b) {
      return b.score - a.score || b.p.reviewsCount - a.p.reviewsCount;
    }).map(function (r) { return r.p; });
  };

  /** Wires a header search form to a live suggestion dropdown (max 6 results). */
  AP.bindLiveSearch = function (form) {
    var input = form.querySelector('input[name="q"]');
    var panel = form.querySelector('.search-suggest');
    if (!input || !panel) return;

    function close() {
      panel.hidden = true;
      input.setAttribute('aria-expanded', 'false');
    }

    var render = u.debounce(function () {
      var q = input.value.trim();
      if (q.length < 2) { close(); return; }
      var results = AP.search(q);
      if (!results.length) {
        panel.innerHTML = '<p class="search-suggest__empty">نتیجه‌ای برای «' + u.escape(q) + '» پیدا نشد.</p>';
      } else {
        panel.innerHTML = results.slice(0, 6).map(function (p) {
          return '<a class="search-suggest__item" href="product-details.html?id=' + p.id + '">' +
            '<img src="' + p.image + '" alt="" width="48" height="48" loading="lazy">' +
            '<span class="search-suggest__text"><span class="search-suggest__title">' + u.escape(p.title) + '</span>' +
            '<span class="search-suggest__price">' + u.num(p.price) + ' تومان</span></span></a>';
        }).join('') +
          '<a class="search-suggest__all" href="search.html?q=' + encodeURIComponent(q) + '">مشاهده همه ' + u.num(results.length) + ' نتیجه</a>';
      }
      panel.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }, 180);

    input.addEventListener('input', render);
    input.addEventListener('focus', render);
    input.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    document.addEventListener('click', function (e) { if (!form.contains(e.target)) close(); });
    form.addEventListener('submit', function (e) {
      if (!input.value.trim()) { e.preventDefault(); input.focus(); }
    });
  };
})();
