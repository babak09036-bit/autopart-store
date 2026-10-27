/* Product listings (products / category / brand / search). Filtering, sorting and paging happen on the server (GET /api/products). */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var esc = u.escape;
  var page = document.body.dataset.page;
  var fixed = (AP.boot && AP.boot.listing) || {};
  var params = u.params();
  var facets = null;

  var SORTS = [['relevance', 'مرتبط‌ترین'], ['popular', 'محبوب‌ترین'], ['best', 'پرفروش‌ترین'], ['new', 'جدیدترین'], ['cheap', 'ارزان‌ترین'], ['expensive', 'گران‌ترین'], ['discount', 'بیشترین تخفیف']]
    .filter(function (s) { return s[0] !== 'relevance' || page === 'search'; });
  var FLAGS = [['instock', 'فقط کالاهای موجود'], ['discount', 'فقط تخفیف‌دار'], ['amazing', 'پیشنهاد شگفت‌انگیز'], ['best', 'پرفروش‌ها'], ['expert', 'منتخب کارشناسان'], ['new', 'جدید']];

  function list(name) { return (params.get(name) || '').split(',').filter(Boolean); }

  var state = {
    q: params.get('q') || '',
    cat: fixed.cat ? [fixed.cat] : list('cat'),
    brand: fixed.brand ? [fixed.brand] : list('brand'),
    min: params.get('min') || '',
    max: params.get('max') || '',
    vehicle: params.get('vehicle') || '',
    year: params.get('vehicle') && /^\d{4}$/.test(params.get('year') || '') ? params.get('year') : '',
    type: params.get('type') || '',
    sort: params.get('sort') || (page === 'search' ? 'relevance' : 'popular'),
    page: Number(params.get('page')) || 1
  };
  FLAGS.forEach(function (f) { state[f[0]] = params.get(f[0]) === '1'; });

  var els = {
    form: document.getElementById('filter-form'), filters: document.getElementById('filters'), sort: document.getElementById('sort-options'),
    count: document.getElementById('result-count'), active: document.getElementById('active-filters'), results: document.getElementById('results'),
    empty: document.getElementById('empty'), pager: document.getElementById('pager'), types: document.getElementById('type-chips'), title: document.getElementById('listing-title')
  };

  function query() {
    var q = { q: state.q, cat: state.cat, brand: state.brand, min: state.min, max: state.max, vehicle: state.vehicle, year: state.vehicle ? state.year : '', type: state.type, sort: state.sort, page: state.page > 1 ? state.page : '' };
    FLAGS.forEach(function (f) { q[f[0]] = state[f[0]]; });
    return q;
  }

  function syncURL() {
    var q = query();
    if (fixed.cat) delete q.cat;
    if (fixed.brand) delete q.brand;
    if (q.sort === (page === 'search' ? 'relevance' : 'popular')) delete q.sort;
    history.replaceState(null, '', location.pathname + AP.api.qs(q));
  }

  /* ---------------- Filters UI ---------------- */
  function buildFilters() {
    var html = '';
    if (!fixed.cat) {
      html += '<fieldset class="filter-group"><legend>دسته‌بندی</legend><div class="check-list">' + facets.categories.map(function (c) {
        return '<label class="check' + (c.parent ? ' check--child' : '') + '"><input type="checkbox" name="cat" value="' + esc(c.slug) + '"' + (state.cat.indexOf(c.slug) > -1 ? ' checked' : '') + '>' +
          esc(c.name) + '<span class="count">' + u.num(c.count) + '</span></label>';
      }).join('') + '</div></fieldset>';
    }
    if (!fixed.brand) {
      html += '<fieldset class="filter-group"><legend>برند</legend><div class="check-list">' + facets.brands.map(function (b) {
        return '<label class="check"><input type="checkbox" name="brand" value="' + esc(b.slug) + '"' + (state.brand.indexOf(b.slug) > -1 ? ' checked' : '') + '>' +
          esc(b.name) + '<span class="count">' + u.num(b.count) + '</span></label>';
      }).join('') + '</div></fieldset>';
    }
    var maxPrice = Math.ceil((facets.price_max || 1000000) / 100000) * 100000;
    html += '<fieldset class="filter-group"><legend>محدوده قیمت (تومان)</legend><div class="price-range">' +
      '<label for="price-min">از</label><input type="range" id="price-min" name="min" min="0" max="' + maxPrice + '" step="50000" value="' + (state.min || 0) + '">' +
      '<label for="price-max">تا</label><input type="range" id="price-max" name="max" min="0" max="' + maxPrice + '" step="50000" value="' + (state.max || maxPrice) + '">' +
      '<div class="price-range__values"><span id="price-min-out"></span><span id="price-max-out"></span></div></div></fieldset>';
    html += '<fieldset class="filter-group"><legend>سازگار با خودرو</legend><label class="visually-hidden" for="vehicle-filter">انتخاب خودرو</label>' +
      '<select class="select" id="vehicle-filter" name="vehicle"><option value="">همه خودروها</option>' + facets.vehicles.map(function (b) {
        return '<optgroup label="' + esc(b.name) + '">' + b.models.map(function (m) {
          return '<option value="' + esc(m.slug) + '"' + (state.vehicle === m.slug ? ' selected' : '') + '>' + esc(m.name) + '</option>';
        }).join('') + '</optgroup>';
      }).join('') + '</select>' + myCarButton() + '</fieldset>';
    html += '<fieldset class="filter-group"><legend>وضعیت کالا</legend>' + FLAGS.map(function (f) {
      return '<label class="switch-row"><span>' + f[1] + '</span><input type="checkbox" name="' + f[0] + '"' + (state[f[0]] ? ' checked' : '') + '></label>';
    }).join('') + '</fieldset>';
    els.form.innerHTML = html;
    els.form.dataset.maxPrice = maxPrice;
    priceLabels();
  }

  function priceLabels() {
    var f = els.form.elements;
    if (!f.min) return;
    document.getElementById('price-min-out').textContent = 'از ' + u.num(f.min.value);
    document.getElementById('price-max-out').textContent = 'تا ' + u.num(f.max.value);
  }

  function readForm() {
    var f = els.form.elements;
    var checked = function (name) { return u.$$('input[name="' + name + '"]:checked', els.form).map(function (i) { return i.value; }); };
    if (!fixed.cat) state.cat = checked('cat');
    if (!fixed.brand) state.brand = checked('brand');
    var min = Number(f.min.value), max = Number(f.max.value);
    if (min > max) { var t = min; min = max; max = t; }
    state.min = min > 0 ? min : '';
    state.max = max < Number(els.form.dataset.maxPrice) ? max : '';
    if (state.vehicle !== f.vehicle.value) state.year = '';
    state.vehicle = f.vehicle.value;
    FLAGS.forEach(function (fl) { state[fl[0]] = f[fl[0]].checked; });
    state.page = 1;
  }

  function buildSort() {
    els.sort.innerHTML = SORTS.map(function (s) {
      return '<button type="button" data-sort="' + s[0] + '" aria-pressed="' + (state.sort === s[0]) + '">' + s[1] + '</button>';
    }).join('');
  }

  /** Vehicle saved by the home/product vehicle selector (same browser only). */
  function savedCar() { try { return JSON.parse(localStorage.getItem('ap_vehicle') || 'null'); } catch (e) { return null; } }
  function myCarButton() {
    var car = savedCar();
    if (!car || !car.model || (state.vehicle === car.model && String(state.year) === String(car.year))) return '';
    return '<button type="button" class="btn btn--light btn--sm btn--block my-car" data-my-car>فقط سازگار با ' + esc(car.modelName || car.model) + ' مدل ' + u.faDigits(car.year) + '</button>';
  }
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-my-car]')) return;
    var car = savedCar();
    state.vehicle = car.model; state.year = String(car.year); state.page = 1;
    if (facets) buildFilters();
    load(false);
  });

  function vehicleName(slug) {
    var name = slug;
    (facets ? facets.vehicles : []).forEach(function (b) { b.models.forEach(function (m) { if (m.slug === slug) name = b.name + ' ' + m.name; }); });
    return name;
  }

  function activeChips() {
    var chips = [];
    function chip(label, key, value) {
      chips.push('<button type="button" class="chip" data-remove="' + key + '" data-value="' + esc(value || '') + '">' + esc(label) + u.icon('close') + '</button>');
    }
    if (!fixed.cat) state.cat.forEach(function (c) {
      var f = facets && facets.categories.filter(function (x) { return x.slug === c; })[0];
      chip(f ? f.name : c, 'cat', c);
    });
    if (!fixed.brand) state.brand.forEach(function (b) {
      var f = facets && facets.brands.filter(function (x) { return x.slug === b; })[0];
      chip(f ? f.name : b, 'brand', b);
    });
    if (state.min || state.max) chip('قیمت: ' + u.num(state.min || 0) + ' تا ' + (state.max ? u.num(state.max) : 'بیشترین'), 'price');
    if (state.vehicle) chip('سازگار با ' + vehicleName(state.vehicle) + (state.year ? ' مدل ' + u.faDigits(state.year) : ''), 'vehicle');
    if (state.type) chip(state.type, 'type');
    FLAGS.forEach(function (f) { if (state[f[0]]) chip(f[1], f[0]); });
    els.active.innerHTML = chips.length ? chips.join('') + '<button type="button" class="chip" data-remove="all">حذف همه فیلترها</button>' : '';
    return chips.length;
  }

  /* ---------------- Fetch & render ---------------- */
  var seq = 0;
  function load(withFacets) {
    var mine = ++seq;
    var q = query();
    q.per_page = 24;
    if (withFacets) q.facets = 1;
    els.results.setAttribute('aria-busy', 'true');
    if (page === 'search' && !state.q) {
      render({ items: [], pagination: { total: 0, pages: 0, page: 1 } });
      if (withFacets) AP.api.get('products', { facets: 1, per_page: 1 }).then(function (d) { facets = d.facets; buildFilters(); });
      return;
    }
    AP.api.get('products', q).then(function (data) {
      if (mine !== seq) return;
      if (data.facets) { facets = data.facets; buildFilters(); }
      render(data);
    }).catch(function (err) {
      els.results.removeAttribute('aria-busy');
      AP.toast(err.message, 'error');
    });
  }

  function render(data) {
    var activeCount = activeChips();
    els.results.removeAttribute('aria-busy');
    els.count.textContent = u.num(data.pagination.total) + ' کالا';
    els.results.innerHTML = AP.cards(data.items);
    els.results.hidden = data.items.length === 0;
    els.empty.hidden = data.items.length > 0;
    if (!data.items.length) {
      var title = page === 'search' && !state.q ? 'عبارتی برای جستجو وارد کنید'
        : page === 'search' && !activeCount ? 'نتیجه‌ای برای «' + state.q + '» پیدا نشد' : 'کالایی با این فیلترها پیدا نشد';
      var popular = facets ? facets.categories.filter(function (c) { return c.parent; }).slice(0, 6).map(function (c) {
        return '<a class="chip" href="categories/' + esc(c.slug) + '">' + esc(c.name) + '</a>';
      }).join('') : '';
      els.empty.innerHTML = '<img src="assets/images/ui/no-results.svg" alt="" width="320" height="240"><h2>' + esc(title) + '</h2>' +
        '<p>' + (page === 'search' && !activeCount ? 'املای عبارت را بررسی کنید یا از کلمات کلی‌تر مثل «روغن» یا «دوربین» استفاده کنید.' : 'فیلترها را کمتر کنید یا یکی از دسته‌های محبوب را ببینید.') + '</p>' +
        (activeCount ? '<button type="button" class="btn btn--primary" data-remove="all">حذف همه فیلترها</button>' : '') +
        (popular ? '<p><strong>دسته‌های محبوب</strong></p><div class="chips">' + popular + '</div>' : '');
    }
    renderPager(data.pagination);
    renderTypes(data.items);
    syncURL();
  }

  function renderPager(p) {
    if (!els.pager) return;
    if (!p.pages || p.pages <= 1) { els.pager.hidden = true; return; }
    var html = '';
    if (p.page > 1) html += '<button type="button" class="pager__btn" data-page="' + (p.page - 1) + '">' + u.icon('chevron-right', 'icon--sm') + 'قبلی</button>';
    for (var i = 1; i <= p.pages; i++) {
      html += i === p.page ? '<span class="pager__num" aria-current="page">' + u.faDigits(i) + '</span>' : '<button type="button" class="pager__num" data-page="' + i + '">' + u.faDigits(i) + '</button>';
    }
    if (p.page < p.pages) html += '<button type="button" class="pager__btn" data-page="' + (p.page + 1) + '">بعدی' + u.icon('chevron-left', 'icon--sm') + '</button>';
    els.pager.innerHTML = html;
    els.pager.hidden = false;
  }

  /* Product-type chips on category pages (built from the first loaded page). */
  var typesBuilt = false;
  function renderTypes(items) {
    if (!els.types || typesBuilt) return;
    var types = [];
    items.forEach(function (p) { if (p.type && types.indexOf(p.type) === -1) types.push(p.type); });
    if (types.length < 2) return;
    typesBuilt = true;
    els.types.innerHTML = '<button type="button" class="chip" data-type="" aria-pressed="' + (!state.type) + '">همه</button>' + types.map(function (t) {
      return '<button type="button" class="chip" data-type="' + esc(t) + '" aria-pressed="' + (state.type === t) + '">' + esc(t) + '</button>';
    }).join('');
  }

  /* ---------------- Events ---------------- */
  els.form.addEventListener('input', function (e) { if (e.target.type === 'range') priceLabels(); });
  els.form.addEventListener('change', function () { readForm(); load(false); });
  els.sort.addEventListener('click', function (e) {
    var b = e.target.closest('[data-sort]');
    if (!b) return;
    state.sort = b.dataset.sort;
    state.page = 1;
    buildSort();
    load(false);
  });
  if (els.pager) els.pager.addEventListener('click', function (e) {
    var b = e.target.closest('[data-page]');
    if (!b) return;
    state.page = Number(b.dataset.page);
    load(false);
    window.scrollTo({ top: els.title.offsetTop - 120, behavior: 'smooth' });
  });

  document.addEventListener('click', function (e) {
    var rm = e.target.closest('[data-remove]');
    if (rm) {
      var key = rm.dataset.remove, val = rm.dataset.value;
      if (key === 'all') {
        if (!fixed.cat) state.cat = [];
        if (!fixed.brand) state.brand = [];
        state.min = state.max = state.vehicle = state.year = state.type = '';
        FLAGS.forEach(function (f) { state[f[0]] = false; });
      } else if (key === 'cat') state.cat = state.cat.filter(function (c) { return c !== val; });
      else if (key === 'brand') state.brand = state.brand.filter(function (b) { return b !== val; });
      else if (key === 'price') state.min = state.max = '';
      else if (key === 'vehicle') state.vehicle = state.year = '';
      else if (key === 'type') state.type = '';
      else state[key] = false;
      state.page = 1;
      if (facets) buildFilters();
      u.$$('[data-type]').forEach(function (t) { t.setAttribute('aria-pressed', String(t.dataset.type === state.type)); });
      load(false);
      return;
    }
    var type = e.target.closest('[data-type]');
    if (type) {
      state.type = type.dataset.type;
      state.page = 1;
      u.$$('[data-type]').forEach(function (t) { t.setAttribute('aria-pressed', String(t === type)); });
      load(false);
    }
  });

  var openBtn = document.querySelector('[data-filters-open]');
  function setFilters(open) {
    els.filters.classList.toggle('is-open', open);
    openBtn.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('no-scroll', open);
    if (open) els.filters.querySelector('[data-filters-close]').focus(); else openBtn.focus();
  }
  openBtn.addEventListener('click', function () { setFilters(true); });
  els.filters.querySelector('[data-filters-close]').addEventListener('click', function () { setFilters(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && els.filters.classList.contains('is-open')) setFilters(false); });
  document.addEventListener('click', function (e) {
    if (els.filters.classList.contains('is-open') && !els.filters.contains(e.target) && !openBtn.contains(e.target)) setFilters(false);
  });

  var searchForm = document.getElementById('search-page-form');
  if (searchForm) {
    searchForm.addEventListener('submit', function (e) {
      e.preventDefault();
      state.q = searchForm.elements.q.value.trim();
      state.page = 1;
      els.title.textContent = state.q ? 'نتایج جستجو برای «' + state.q + '»' : 'جستجوی محصولات';
      document.title = els.title.textContent + ' | ' + ((AP.boot.settings || {}).store_name || '');
      var header = document.getElementById('header-q');
      if (header) header.value = state.q;
      load(false);
    });
  }

  buildSort();
  load(true);
})();
