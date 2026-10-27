/* Product listing engine shared by products.html, category.html and search.html. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var page = document.body.dataset.page;
  var all = AP.data.products;
  var params = u.params();
  var PRICE_MAX = Math.ceil(Math.max.apply(null, all.map(function (p) { return p.price; })) / 100000) * 100000;
  var PRICE_STEP = 50000;

  var SORTS = [
    ['relevance', 'مرتبط‌ترین'], ['popular', 'محبوب‌ترین'], ['best', 'پرفروش‌ترین'], ['new', 'جدیدترین'],
    ['cheap', 'ارزان‌ترین'], ['expensive', 'گران‌ترین'], ['discount', 'بیشترین تخفیف']
  ].filter(function (s) { return s[0] !== 'relevance' || page === 'search'; });

  var FLAGS = [
    ['instock', 'فقط کالاهای موجود'], ['discount', 'فقط تخفیف‌دار'], ['amazing', 'پیشنهاد شگفت‌انگیز'],
    ['best', 'پرفروش‌ها'], ['expert', 'منتخب کارشناسان']
  ];

  function list(name, valid) {
    return (params.get(name) || '').split(',').filter(function (v) { return v && valid(v); });
  }

  var state = {
    q: (params.get('q') || '').trim(),
    cats: list('cat', AP.catalog.category),
    brands: list('brand', AP.catalog.brand),
    min: Math.max(0, Number(params.get('min')) || 0),
    max: Math.min(PRICE_MAX, Number(params.get('max')) || PRICE_MAX),
    vehicle: AP.catalog.model(params.get('vehicle') || '') ? params.get('vehicle') : '',
    sub: params.get('sub') || '',
    sort: params.get('sort') || (page === 'search' ? 'relevance' : 'popular')
  };
  FLAGS.forEach(function (f) { state[f[0]] = params.get(f[0]) === '1'; });
  if (!SORTS.some(function (s) { return s[0] === state.sort; })) state.sort = 'popular';

  var lockedCat = page === 'category' ? AP.catalog.category(params.get('cat') || '') : null;
  if (page === 'category') state.cats = lockedCat ? [lockedCat.slug] : [];

  var els = {
    title: document.getElementById('listing-title'),
    head: document.getElementById('listing-head'),
    crumb: document.getElementById('crumb-current'),
    filters: document.getElementById('filters'),
    form: document.getElementById('filter-form'),
    sort: document.getElementById('sort-options'),
    count: document.getElementById('result-count'),
    active: document.getElementById('active-filters'),
    results: document.getElementById('results'),
    empty: document.getElementById('empty')
  };

  /* ---------------- Filtering & sorting ---------------- */
  function popularity(p) { return p.rating * Math.log(p.reviewsCount + 1); }

  function apply() {
    var pool = page === 'search' ? (state.q ? AP.search(state.q) : []) : all.slice();
    var out = pool.filter(function (p) {
      if (state.cats.length && state.cats.indexOf(p.category) === -1) return false;
      if (state.brands.length && state.brands.indexOf(p.brand) === -1) return false;
      if (p.price < state.min || p.price > state.max) return false;
      if (state.vehicle && !AP.catalog.isCompatible(p, state.vehicle)) return false;
      if (state.sub && p.sub !== state.sub) return false;
      if (state.instock && p.stock < 1) return false;
      if (state.discount && !u.discount(p)) return false;
      if (state.amazing && !p.isAmazing) return false;
      if (state.best && !p.isBestseller) return false;
      if (state.expert && !p.isExpertPick) return false;
      return true;
    });
    var sorters = {
      popular: function (a, b) { return popularity(b) - popularity(a); },
      best: function (a, b) { return b.reviewsCount - a.reviewsCount; },
      'new': function (a, b) { return (b.isNew - a.isNew) || b.id - a.id; },
      cheap: function (a, b) { return a.price - b.price; },
      expensive: function (a, b) { return b.price - a.price; },
      discount: function (a, b) { return u.discount(b) - u.discount(a); }
    };
    if (sorters[state.sort]) out.sort(sorters[state.sort]);
    // available products first, sold-out last
    return out.filter(function (p) { return p.stock > 0; }).concat(out.filter(function (p) { return p.stock < 1; }));
  }

  function syncURL() {
    var q = new URLSearchParams();
    if (state.q) q.set('q', state.q);
    if (state.cats.length) q.set('cat', state.cats.join(','));
    if (state.brands.length) q.set('brand', state.brands.join(','));
    if (state.min > 0) q.set('min', state.min);
    if (state.max < PRICE_MAX) q.set('max', state.max);
    if (state.vehicle) q.set('vehicle', state.vehicle);
    if (state.sub) q.set('sub', state.sub);
    FLAGS.forEach(function (f) { if (state[f[0]]) q.set(f[0], '1'); });
    if (state.sort !== (page === 'search' ? 'relevance' : 'popular')) q.set('sort', state.sort);
    var qs = q.toString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
  }

  /* ---------------- Page heading ---------------- */
  function heading() {
    if (page === 'search') return state.q ? 'نتایج جستجو برای «' + state.q + '»' : 'جستجوی محصولات';
    if (page === 'category') return lockedCat ? lockedCat.name : 'دسته‌بندی یافت نشد';
    if (state.amazing) return 'پیشنهادهای شگفت‌انگیز';
    if (state.discount) return 'محصولات تخفیف‌دار';
    if (state.best) return 'پرفروش‌ترین محصولات';
    if (state.expert) return 'منتخب کارشناسان';
    if (state.vehicle) return 'محصولات سازگار با ' + AP.catalog.model(state.vehicle).model.name;
    if (state.brands.length === 1) return 'محصولات برند ' + AP.catalog.brandName(state.brands[0]);
    if (state.cats.length) {
      return state.cats.map(function (c) { return AP.catalog.category(c).short; }).join('، ');
    }
    return 'همه محصولات';
  }

  function renderHead() {
    var text = heading();
    els.title.textContent = text;
    if (els.crumb) els.crumb.textContent = text;
    document.title = text + ' | اتوپارت';

    if (page === 'category' && lockedCat) {
      var inCat = all.filter(function (p) { return p.category === lockedCat.slug; });
      var subs = inCat.map(function (p) { return p.sub; }).filter(function (s, i, a) { return a.indexOf(s) === i; });
      els.head.innerHTML = '<div class="cat-hero"><div><p class="badge badge--soft">' + u.num(inCat.length) + ' کالا در این دسته</p>' +
        '<p>' + 'خرید اینترنتی ' + lockedCat.name + ' با ضمانت اصالت، ارسال سریع و امکان مقایسه قیمت و برند.' + '</p>' +
        (subs.length > 1 ? '<div class="sub-chips" role="group" aria-label="زیردسته‌ها">' +
          '<button type="button" class="chip" data-sub="" aria-pressed="' + (!state.sub) + '">همه</button>' +
          subs.map(function (s) { return '<button type="button" class="chip" data-sub="' + u.escape(s) + '" aria-pressed="' + (state.sub === s) + '">' + u.escape(s) + '</button>'; }).join('') +
          '</div>' : '') +
        '</div><img src="' + lockedCat.image + '" alt="" width="240" height="240"></div>';
    }
  }

  /* ---------------- Filters UI ---------------- */
  function countBy(key, value) { return all.filter(function (p) { return p[key] === value; }).length; }

  function buildFilters() {
    var html = '';
    if (page !== 'category') {
      html += '<fieldset class="filter-group"><legend>دسته‌بندی</legend><div class="check-list">' +
        AP.data.categories.map(function (c) {
          return '<label class="check"><input type="checkbox" name="cat" value="' + c.slug + '"' + (state.cats.indexOf(c.slug) > -1 ? ' checked' : '') + '>' +
            c.short + '<span class="count">' + u.num(countBy('category', c.slug)) + '</span></label>';
        }).join('') + '</div></fieldset>';
    }
    html += '<fieldset class="filter-group"><legend>برند</legend><div class="check-list">' +
      AP.data.brands.map(function (b) {
        return '<label class="check"><input type="checkbox" name="brand" value="' + b.slug + '"' + (state.brands.indexOf(b.slug) > -1 ? ' checked' : '') + '>' +
          b.name + '<span class="count">' + u.num(countBy('brand', b.slug)) + '</span></label>';
      }).join('') + '</div></fieldset>';

    html += '<fieldset class="filter-group"><legend>محدوده قیمت (تومان)</legend><div class="price-range">' +
      '<label for="price-min">از</label><input type="range" id="price-min" name="min" min="0" max="' + PRICE_MAX + '" step="' + PRICE_STEP + '" value="' + state.min + '">' +
      '<label for="price-max">تا</label><input type="range" id="price-max" name="max" min="0" max="' + PRICE_MAX + '" step="' + PRICE_STEP + '" value="' + state.max + '">' +
      '<div class="price-range__values"><span id="price-min-out"></span><span id="price-max-out"></span></div></div></fieldset>';

    html += '<fieldset class="filter-group"><legend>سازگار با خودرو</legend>' +
      '<label class="visually-hidden" for="vehicle-filter">انتخاب خودرو</label><select class="select" id="vehicle-filter" name="vehicle"><option value="">همه خودروها</option>' +
      AP.data.vehicles.map(function (b) {
        return '<optgroup label="' + b.name + '">' + b.models.map(function (m) {
          return '<option value="' + m.slug + '"' + (state.vehicle === m.slug ? ' selected' : '') + '>' + m.name + '</option>';
        }).join('') + '</optgroup>';
      }).join('') + '</select></fieldset>';

    html += '<fieldset class="filter-group"><legend>وضعیت کالا</legend>' +
      FLAGS.map(function (f) {
        return '<label class="switch-row"><span>' + f[1] + '</span><input type="checkbox" name="' + f[0] + '"' + (state[f[0]] ? ' checked' : '') + '></label>';
      }).join('') + '</fieldset>';

    els.form.innerHTML = html;
    updatePriceLabels();
  }

  function updatePriceLabels() {
    var min = els.form.elements.min, max = els.form.elements.max;
    document.getElementById('price-min-out').textContent = 'از ' + u.num(min.value);
    document.getElementById('price-max-out').textContent = 'تا ' + u.num(max.value);
  }

  function readForm() {
    var f = els.form.elements;
    if (page !== 'category') {
      state.cats = Array.prototype.filter.call(els.form.querySelectorAll('input[name="cat"]'), function (i) { return i.checked; }).map(function (i) { return i.value; });
    }
    state.brands = Array.prototype.filter.call(els.form.querySelectorAll('input[name="brand"]'), function (i) { return i.checked; }).map(function (i) { return i.value; });
    var min = Number(f.min.value), max = Number(f.max.value);
    if (min > max) { var t = min; min = max; max = t; }
    state.min = min; state.max = max;
    state.vehicle = f.vehicle.value;
    FLAGS.forEach(function (fl) { state[fl[0]] = f[fl[0]].checked; });
  }

  function buildSort() {
    els.sort.innerHTML = SORTS.map(function (s) {
      return '<button type="button" data-sort="' + s[0] + '" aria-pressed="' + (state.sort === s[0]) + '">' + s[1] + '</button>';
    }).join('');
  }

  function activeChips() {
    var chips = [];
    function chip(label, key, value) {
      chips.push('<button type="button" class="chip" data-remove="' + key + '" data-value="' + u.escape(value || '') + '">' + u.escape(label) + u.icon('close') + '</button>');
    }
    if (page !== 'category') state.cats.forEach(function (c) { chip(AP.catalog.category(c).short, 'cat', c); });
    state.brands.forEach(function (b) { chip(AP.catalog.brandName(b), 'brand', b); });
    if (state.min > 0 || state.max < PRICE_MAX) chip('قیمت: ' + u.num(state.min) + ' تا ' + u.num(state.max), 'price');
    if (state.vehicle) chip(AP.catalog.model(state.vehicle).model.name, 'vehicle');
    if (state.sub) chip(state.sub, 'sub');
    FLAGS.forEach(function (f) { if (state[f[0]]) chip(f[1], f[0]); });
    els.active.innerHTML = chips.length ? chips.join('') + '<button type="button" class="chip" data-remove="all">حذف همه فیلترها</button>' : '';
    return chips.length;
  }

  /* ---------------- Render ---------------- */
  function render() {
    renderHead();
    var results = apply();
    var activeCount = activeChips();
    els.count.textContent = u.num(results.length) + ' کالا';
    els.results.innerHTML = AP.cards(results);
    els.results.hidden = results.length === 0;
    els.empty.hidden = results.length > 0;

    if (!results.length) {
      var popular = AP.data.categories.slice(0, 6).map(function (c) {
        return '<a class="chip" href="category.html?cat=' + c.slug + '">' + c.short + '</a>';
      }).join('');
      var title = page === 'search' && !state.q ? 'عبارتی برای جستجو وارد کنید'
        : page === 'category' && !lockedCat ? 'این دسته‌بندی وجود ندارد'
          : page === 'search' && !activeCount ? 'نتیجه‌ای برای «' + state.q + '» پیدا نشد'
            : 'کالایی با این فیلترها پیدا نشد';
      var hint = page === 'search' && !activeCount ? 'املای عبارت را بررسی کنید یا از کلمات کلی‌تر مثل «روغن» یا «دوربین» استفاده کنید.'
        : 'فیلترها را کمتر کنید یا یکی از دسته‌های محبوب را ببینید.';
      els.empty.innerHTML = '<img src="assets/images/ui/no-results.svg" alt="" width="320" height="240">' +
        '<h2>' + u.escape(title) + '</h2><p>' + hint + '</p>' +
        (activeCount ? '<button type="button" class="btn btn--primary" data-remove="all">حذف همه فیلترها</button>' : '') +
        '<p><strong>دسته‌های محبوب</strong></p><div class="chips">' + popular + '</div>';
    }
    syncURL();
  }

  /* ---------------- Events ---------------- */
  els.form.addEventListener('input', function (e) {
    if (e.target.type === 'range') updatePriceLabels();
  });
  els.form.addEventListener('change', function () { readForm(); render(); });

  els.sort.addEventListener('click', function (e) {
    var b = e.target.closest('[data-sort]');
    if (!b) return;
    state.sort = b.dataset.sort;
    buildSort();
    render();
  });

  document.addEventListener('click', function (e) {
    var rm = e.target.closest('[data-remove]');
    if (rm) {
      var key = rm.dataset.remove, val = rm.dataset.value;
      if (key === 'all') {
        if (page !== 'category') state.cats = [];
        state.brands = []; state.min = 0; state.max = PRICE_MAX; state.vehicle = ''; state.sub = '';
        FLAGS.forEach(function (f) { state[f[0]] = false; });
      } else if (key === 'cat') state.cats = state.cats.filter(function (c) { return c !== val; });
      else if (key === 'brand') state.brands = state.brands.filter(function (b) { return b !== val; });
      else if (key === 'price') { state.min = 0; state.max = PRICE_MAX; }
      else if (key === 'vehicle') state.vehicle = '';
      else if (key === 'sub') state.sub = '';
      else state[key] = false;
      buildFilters();
      render();
      return;
    }
    var sub = e.target.closest('[data-sub]');
    if (sub) { state.sub = sub.dataset.sub; render(); }
  });

  /* Mobile filter drawer */
  var openBtn = document.querySelector('[data-filters-open]');
  function setFilters(open) {
    els.filters.classList.toggle('is-open', open);
    openBtn.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('no-scroll', open);
    if (open) els.filters.querySelector('[data-filters-close]').focus(); else openBtn.focus();
  }
  openBtn.addEventListener('click', function () { setFilters(true); });
  els.filters.querySelector('[data-filters-close]').addEventListener('click', function () { setFilters(false); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && els.filters.classList.contains('is-open')) setFilters(false);
  });
  document.addEventListener('click', function (e) {
    if (els.filters.classList.contains('is-open') && !els.filters.contains(e.target) && !openBtn.contains(e.target)) setFilters(false);
  });

  /* Search page: inline search box mirrors the query */
  var searchForm = document.getElementById('search-page-form');
  if (searchForm) {
    searchForm.elements.q.value = state.q;
    searchForm.addEventListener('submit', function (e) {
      e.preventDefault();
      state.q = searchForm.elements.q.value.trim();
      var headerInput = document.getElementById('header-q');
      if (headerInput) headerInput.value = state.q;
      render();
    });
  }

  buildFilters();
  buildSort();
  render();
})();
