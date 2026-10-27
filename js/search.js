/* Header live search: suggestions come from GET /api/search/suggest. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;

  AP.bindLiveSearch = function (form) {
    var input = form.querySelector('input[name="q"]');
    var panel = form.querySelector('.search-suggest');
    if (!input || !panel) return;
    var seq = 0;

    function close() {
      panel.hidden = true;
      input.setAttribute('aria-expanded', 'false');
    }

    var render = u.debounce(function () {
      var q = input.value.trim();
      if (q.length < 2) { close(); return; }
      var mine = ++seq;
      AP.api.get('search/suggest', { q: q }).then(function (data) {
        if (mine !== seq) return;
        if (!data.items.length) {
          panel.innerHTML = '<p class="search-suggest__empty">نتیجه‌ای برای «' + u.escape(q) + '» پیدا نشد.</p>';
        } else {
          panel.innerHTML = data.items.map(function (p) {
            return '<a class="search-suggest__item" href="' + u.escape(p.url) + '">' +
              '<img src="' + u.escape(p.image) + '" alt="" width="48" height="48" loading="lazy">' +
              '<span class="search-suggest__text"><span class="search-suggest__title">' + u.escape(p.title) + '</span>' +
              '<span class="search-suggest__price">' + u.num(p.price) + ' تومان</span></span></a>';
          }).join('') +
            '<a class="search-suggest__all" href="search?q=' + encodeURIComponent(q) + '">مشاهده همه ' + u.num(data.total) + ' نتیجه</a>';
        }
        panel.hidden = false;
        input.setAttribute('aria-expanded', 'true');
      }).catch(close);
    }, 220);

    input.addEventListener('input', render);
    input.addEventListener('focus', render);
    input.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    document.addEventListener('click', function (e) { if (!form.contains(e.target)) close(); });
    form.addEventListener('submit', function (e) {
      if (!input.value.trim()) { e.preventDefault(); input.focus(); }
    });
  };
})();
