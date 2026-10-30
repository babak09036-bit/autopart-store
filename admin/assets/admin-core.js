/* AutoPart admin — core framework: API, router, layout, toasts, modals, tables, forms, media picker, charts. */
(function () {
  'use strict';

  var A = window.A = { routes: [], me: null };
  var cfg = window.ADMIN;
  var FA = '۰۱۲۳۴۵۶۷۸۹';

  /* ---------------- Helpers ---------------- */
  A.esc = function (v) {
    return String(v === null || v === undefined ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  };
  A.fa = function (v) { return String(v === null || v === undefined ? '' : v).replace(/\d/g, function (d) { return FA[d]; }); };
  A.en = function (v) { return String(v || '').replace(/[۰-۹]/g, function (d) { return FA.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }); };
  A.num = function (n) { return new Intl.NumberFormat('fa-IR').format(Math.round(Number(n) || 0)); };
  A.money = function (n) { return A.num(n) + ' <small>تومان</small>'; };
  A.icon = function (name, cls) { return '<svg class="icon ' + (cls || '') + '" aria-hidden="true"><use href="assets/icons/icons.svg#i-' + name + '"></use></svg>'; };
  A.$ = function (s, r) { return (r || document).querySelector(s); };
  A.$$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  A.can = function (p) { return !!(A.me && A.me.permissions.indexOf(p) > -1); };
  A.canAny = function (list) { return list.split('|').some(A.can); };
  A.debounce = function (fn, ms) { var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); }; };
  A.slugify = function (t) { return A.en(t).trim().toLowerCase().replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/[^\p{L}\p{N}]+/gu, '-').replace(/^-+|-+$/g, ''); };

  /* ---------------- API ---------------- */
  function ApiError(message, status, errors) { this.message = message; this.status = status; this.errors = errors || {}; }
  A.request = function (method, url, body) {
    var headers = { 'Accept': 'application/json', 'X-CSRF-Token': cfg.csrf };
    var payload;
    if (body instanceof FormData) payload = body;
    else if (body !== undefined) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
    return fetch(url, { method: method, headers: headers, body: payload, credentials: 'same-origin' }).then(function (res) {
      return res.json().catch(function () { return { success: false, message: 'پاسخ نامعتبر از سرور.' }; }).then(function (json) {
        if (res.status === 401) { location.reload(); throw new ApiError(json.message, 401); }
        if (!res.ok || !json.success) throw new ApiError(json.message || 'خطایی رخ داده است.', res.status, json.errors);
        if (json.data && json.data.csrf) cfg.csrf = json.data.csrf;
        return json.data;
      });
    }, function () { throw new ApiError('ارتباط با سرور برقرار نشد.', 0); });
  };
  A.qs = function (p) {
    var parts = [];
    Object.keys(p || {}).forEach(function (k) {
      var v = p[k];
      if (v === undefined || v === null || v === '') return;
      parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
    });
    return parts.length ? '?' + parts.join('&') : '';
  };
  A.get = function (path, params) { return A.request('GET', 'api/admin/' + path + A.qs(params)); };
  A.post = function (path, body) { return A.request('POST', 'api/admin/' + path, body === undefined ? {} : body); };
  A.put = function (path, body, params) { return A.request('PUT', 'api/admin/' + path + A.qs(params), body || {}); };
  A.patch = function (path, body) { return A.request('PATCH', 'api/admin/' + path, body || {}); };
  A.del = function (path, params) { return A.request('DELETE', 'api/admin/' + path + A.qs(params), {}); };
  A.pub = function (path, params) { return A.request('GET', 'api/' + path + A.qs(params)); };

  /* ---------------- Toasts / modal / confirm ---------------- */
  A.toast = function (msg, type) {
    var box = document.getElementById('a-toasts');
    var el = document.createElement('div');
    el.className = 'a-toast a-toast--' + (type || 'success');
    el.innerHTML = A.icon(type === 'error' ? 'alert' : 'check') + '<span>' + A.esc(msg) + '</span>';
    box.appendChild(el);
    setTimeout(function () { el.classList.add('is-out'); setTimeout(function () { el.remove(); }, 300); }, 3500);
  };
  A.fail = function (err) { A.toast(err.message || 'خطا', 'error'); };

  A.modal = function (opts) {
    var last = document.activeElement;
    var wrap = document.createElement('div');
    wrap.className = 'a-modal';
    wrap.innerHTML = '<div class="a-modal__overlay" data-close></div><div class="a-modal__box' + (opts.wide ? ' a-modal__box--wide' : '') + '" role="dialog" aria-modal="true" aria-labelledby="a-modal-title">' +
      '<div class="a-modal__head"><h2 id="a-modal-title">' + A.esc(opts.title) + '</h2><button type="button" class="a-icon-btn" data-close aria-label="بستن">' + A.icon('close') + '</button></div>' +
      '<div class="a-modal__body">' + (opts.body || '') + '</div>' + (opts.foot ? '<div class="a-modal__foot">' + opts.foot + '</div>' : '') + '</div>';
    document.body.appendChild(wrap);
    document.body.classList.add('a-no-scroll');
    var api = {
      el: wrap,
      close: function () { wrap.remove(); document.body.classList.remove('a-no-scroll'); document.removeEventListener('keydown', onKey); if (last && last.focus) last.focus(); }
    };
    function onKey(e) { if (e.key === 'Escape') api.close(); }
    document.addEventListener('keydown', onKey);
    wrap.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) api.close(); });
    var first = wrap.querySelector('input, select, textarea, button:not([data-close])');
    (first || wrap.querySelector('.a-modal__box')).focus();
    if (opts.onOpen) opts.onOpen(wrap, api);
    return api;
  };

  /** Confirmation for sensitive operations; resolves true/false. */
  A.confirm = function (message, opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
      var m = A.modal({ title: opts.title || 'تأیید عملیات', body: '<p>' + A.esc(message) + '</p>',
        foot: '<button type="button" class="a-btn ' + (opts.danger === false ? 'a-btn--primary' : 'a-btn--danger') + '" data-yes>' + A.esc(opts.yes || 'بله، انجام شود') + '</button><button type="button" class="a-btn a-btn--light" data-close>انصراف</button>' });
      var answered = false;
      m.el.querySelector('[data-yes]').addEventListener('click', function () { answered = true; m.close(); resolve(true); });
      m.el.addEventListener('click', function (e) { if (e.target.closest('[data-close]') && !answered) resolve(false); });
      m.el.querySelector('[data-yes]').focus();
    });
  };

  /* ---------------- Layout & router ---------------- */
  A.MENU = [
    ['داشبورد', [['#/dashboard', 'grid', 'داشبورد', 'dashboard.view'], ['#/reports', 'sort', 'گزارش‌ها', 'reports.view'], ['#/notifications', 'alert', 'اعلان‌ها', 'notifications.view']]],
    ['فروشگاه', [['#/products', 'package-track', 'محصولات', 'products.view|products.manage'], ['#/inventory', 'grid', 'انبار و موجودی', 'inventory.manage|products.view'],
      ['#/pricing', 'discount', 'تغییر گروهی قیمت', 'pricing.bulk'], ['#/categories', 'grid', 'دسته‌بندی‌ها', 'categories.manage'], ['#/brands', 'award', 'برندها', 'brands.manage'],
      ['#/vehicles', 'car', 'خودروها', 'vehicles.manage'], ['#/orders', 'truck', 'سفارش‌ها', 'orders.view'], ['#/customers', 'user', 'مشتریان', 'customers.view'], ['#/coupons', 'discount', 'کدهای تخفیف', 'coupons.manage']]],
    ['تعامل', [['#/reviews', 'star', 'مدیریت نظرات', 'reviews.view|reviews.manage'], ['#/questions', 'headset', 'پرسش‌های محصول', 'questions.manage'], ['#/messages', 'mail', 'پیام‌های تماس', 'messages.manage'], ['#/newsletter', 'mail', 'خبرنامه', 'newsletter.view']]],
    ['مدیریت محتوای سایت', [['#/homepage', 'home', 'صفحه اصلی', 'content.homepage'], ['#/banners', 'flash', 'بنرها', 'content.banners'], ['#/menus', 'menu', 'منوها', 'content.menus'],
      ['#/pages', 'grid', 'صفحات', 'content.pages'], ['#/faqs', 'headset', 'سوالات متداول', 'content.pages'], ['#/media', 'camera', 'فایل‌ها و تصاویر', 'media.manage']]],
    ['مدیریت وبلاگ', [['#/blog/posts', 'sparkle', 'مقالات', 'blog.posts'], ['#/blog/categories', 'grid', 'دسته‌ها', 'blog.taxonomy'], ['#/blog/tags', 'discount', 'برچسب‌ها', 'blog.taxonomy'],
      ['#/blog/authors', 'user', 'نویسندگان', 'blog.taxonomy'], ['#/blog/comments', 'mail', 'دیدگاه‌ها', 'blog.comments']]],
    ['مدیریت سئو', [['#/seo', 'search', 'سئو محتوا', 'seo.manage'], ['#/redirects', 'return', 'ریدایرکت‌ها', 'redirects.manage'], ['#/sitemap', 'grid', 'سایت‌مپ و robots', 'seo.manage']]],
    ['تنظیمات', [['#/settings', 'settings', 'تنظیمات فروشگاه', 'settings.manage'], ['#/admins', 'shield', 'مدیران و نقش‌ها', 'admins.manage'], ['#/activity', 'clock', 'گزارش فعالیت‌ها', 'logs.view']]]
  ];

  function renderSide() {
    var html = '<a class="admin__brand" href="admin#/dashboard"><img src="assets/images/ui/logo.svg" alt="" width="140" height="36"><span>پنل مدیریت</span></a><nav>';
    A.MENU.forEach(function (group) {
      var items = group[1].filter(function (it) { return A.canAny(it[3]); });
      if (!items.length) return;
      html += '<p class="admin__group">' + group[0] + '</p><ul>' + items.map(function (it) {
        return '<li><a href="admin' + it[0] + '" data-nav="' + it[0] + '">' + A.icon(it[1], 'icon--sm') + '<span>' + it[2] + '</span></a></li>';
      }).join('') + '</ul>';
    });
    document.getElementById('admin-side').innerHTML = html + '</nav>';
  }

  function markNav() {
    var hash = location.hash || '#/dashboard';
    var best = null;
    A.$$('[data-nav]').forEach(function (a) {
      var n = a.dataset.nav;
      if (hash === n || hash.indexOf(n + '/') === 0 || hash.indexOf(n + '?') === 0) { if (!best || n.length > best.dataset.nav.length) best = a; }
      a.removeAttribute('aria-current');
    });
    if (best) best.setAttribute('aria-current', 'page');
  }

  /** route('#/products/:id', fn(params, query)) */
  A.route = function (pattern, handler) {
    var keys = [];
    var re = new RegExp('^' + pattern.replace(/:(\w+)/g, function (_, k) { keys.push(k); return '([^/?]+)'; }) + '$');
    A.routes.push({ re: re, keys: keys, handler: handler });
  };

  A.query = function () {
    var q = {};
    var s = (location.hash.split('?')[1] || '');
    s.split('&').forEach(function (p) { if (!p) return; var kv = p.split('='); q[decodeURIComponent(kv[0])] = decodeURIComponent(kv[1] || ''); });
    return q;
  };
  A.go = function (hash) { if (location.hash === hash) dispatch(); else location.hash = hash; };
  A.setQuery = function (q) {
    var path = (location.hash || '#/dashboard').split('?')[0];
    history.replaceState(null, '', location.pathname + path + A.qs(q));
  };

  A.view = function (title, html) {
    document.getElementById('admin-title').textContent = title;
    document.title = title + ' — پنل مدیریت ' + cfg.store;
    // A fresh container per screen, so listeners added by the previous screen are discarded with it.
    var v = document.getElementById('admin-view');
    var page = document.createElement('div');
    page.className = 'a-page';
    page.innerHTML = html;
    v.replaceChildren(page);
    return page;
  };

  function dispatch() {
    var path = (location.hash || '#/dashboard').split('?')[0];
    markNav();
    document.getElementById('admin').classList.remove('is-menu-open');
    for (var i = 0; i < A.routes.length; i++) {
      var m = path.match(A.routes[i].re);
      if (m) {
        var params = {};
        A.routes[i].keys.forEach(function (k, j) { params[k] = decodeURIComponent(m[j + 1]); });
        try { A.routes[i].handler(params, A.query()); } catch (e) { A.view('خطا', '<p class="a-alert a-alert--error">' + A.esc(e.message) + '</p>'); }
        document.getElementById('admin-view').scrollTop = 0;
        window.scrollTo(0, 0);
        return;
      }
    }
    A.view('یافت نشد', '<div class="a-empty"><p>صفحه درخواستی در پنل وجود ندارد.</p><a class="a-btn a-btn--primary" href="admin#/dashboard">داشبورد</a></div>');
  }

  A.loading = function (title) { return A.view(title, '<div class="a-loading">در حال بارگذاری…</div>'); };

  /* ---------------- Badges ---------------- */
  A.badge = function (text, tone) { return '<span class="a-badge a-badge--' + (tone || 'muted') + '">' + A.esc(text) + '</span>'; };
  A.yesno = function (v) { return Number(v) ? A.badge('فعال', 'green') : A.badge('غیرفعال', 'muted'); };

  /* ---------------- Forms ---------------- */
  /**
   * field: { name, label, type, options, help, full, required, placeholder, dir, folder, endpoint, labelKey, rows }
   * types: text, email, number, url, textarea, select, checkbox, datetime, color, image, multi, tags, keyvalue, html, password, readonly
   */
  A.field = function (f, value) {
    var id = 'f-' + f.name.replace(/[^\w]/g, '_');
    var v = value === undefined || value === null ? (f.default !== undefined ? f.default : '') : value;
    var attrs = (f.required ? ' required' : '') + (f.placeholder ? ' placeholder="' + A.esc(f.placeholder) + '"' : '') + (f.dir ? ' dir="' + f.dir + '"' : '') + (f.maxlength ? ' maxlength="' + f.maxlength + '"' : '');
    var input;
    switch (f.type) {
      case 'textarea':
        input = '<textarea class="a-input" id="' + id + '" name="' + f.name + '" rows="' + (f.rows || 4) + '"' + attrs + '>' + A.esc(v) + '</textarea>'; break;
      case 'select':
        input = '<select class="a-input" id="' + id + '" name="' + f.name + '"' + attrs + '>' + (f.empty !== undefined ? '<option value="">' + A.esc(f.empty) + '</option>' : '') +
          (f.options || []).map(function (o) { return '<option value="' + A.esc(o[0]) + '"' + (String(o[0]) === String(v) ? ' selected' : '') + '>' + A.esc(o[1]) + '</option>'; }).join('') + '</select>'; break;
      case 'checkbox':
        return '<label class="a-check' + (f.full ? ' a-span' : '') + '"><input type="checkbox" name="' + f.name + '" value="1"' + (Number(v) || v === true ? ' checked' : '') + '><span>' + A.esc(f.label) + '</span></label>';
      case 'datetime':
        input = '<input class="a-input" type="datetime-local" id="' + id + '" name="' + f.name + '" value="' + A.esc(v) + '">'; break;
      case 'color':
        input = '<div class="a-color"><input type="color" id="' + id + '" name="' + f.name + '" value="' + A.esc(v || '#ffffff') + '"><label class="a-check"><input type="checkbox" data-color-none="' + f.name + '"' + (v ? '' : ' checked') + '><span>بدون رنگ</span></label></div>'; break;
      case 'image':
        input = '<div class="a-image" data-image-field="' + f.name + '"><img src="' + A.esc(v || 'assets/images/ui/placeholder.svg') + '" alt="" width="72" height="72">' +
          '<input class="a-input" id="' + id + '" name="' + f.name + '" value="' + A.esc(v) + '" dir="ltr" placeholder="assets/... یا uploads/...">' +
          '<button type="button" class="a-btn a-btn--light a-btn--sm" data-pick="' + f.name + '" data-folder="' + (f.folder || 'general') + '">انتخاب</button></div>'; break;
      case 'multi':
        input = '<div class="a-multi" data-multi="' + f.name + '" data-endpoint="' + A.esc(f.endpoint) + '" data-label="' + (f.labelKey || 'name') + '" data-value=\'' + A.esc(JSON.stringify(v || [])) + '\'>' +
          '<div class="a-multi__chips"></div><input class="a-input" type="search" placeholder="جستجو و افزودن…" aria-label="' + A.esc(f.label) + '"><div class="a-multi__menu" hidden></div></div>'; break;
      case 'tags':
        input = '<input class="a-input" id="' + id + '" name="' + f.name + '" value="' + A.esc((v || []).join('، ')) + '" placeholder="با ویرگول جدا کنید">'; break;
      case 'keyvalue':
        input = '<div class="a-kv" data-kv="' + f.name + '">' + (v || []).map(kvRow).join('') + '</div><button type="button" class="a-btn a-btn--light a-btn--sm" data-kv-add="' + f.name + '">' + A.icon('plus', 'icon--sm') + 'افزودن ردیف</button>'; break;
      case 'html':
        input = '<div class="a-editor-slot" data-editor="' + f.name + '" data-folder="' + (f.folder || 'blog') + '"></div><textarea hidden name="' + f.name + '">' + A.esc(v) + '</textarea>'; break;
      case 'readonly':
        input = '<div class="a-readonly">' + (f.html ? v : A.esc(v)) + '</div>'; break;
      default:
        input = '<input class="a-input" type="' + (f.type || 'text') + '" id="' + id + '" name="' + f.name + '" value="' + A.esc(v) + '"' + attrs + (f.type === 'number' ? ' step="' + (f.step || 'any') + '"' : '') + '>';
    }
    return '<div class="a-field' + (f.full ? ' a-span' : '') + '"><label for="' + id + '">' + A.esc(f.label) + (f.required ? ' <b class="a-req">*</b>' : '') + '</label>' + input +
      (f.help ? '<p class="a-hint">' + f.help + '</p>' : '') + '<p class="a-error" data-error-for="' + f.name + '"></p></div>';
  };

  function kvRow(r) {
    return '<div class="a-kv__row"><input class="a-input" data-k placeholder="عنوان" value="' + A.esc(r.name || '') + '"><input class="a-input" data-v placeholder="مقدار" value="' + A.esc(r.value || '') + '">' +
      '<button type="button" class="a-icon-btn" data-kv-del aria-label="حذف ردیف">' + A.icon('trash', 'icon--sm') + '</button></div>';
  }

  /** Activates image pickers, multi selects, key/value rows and editors inside a container. */
  A.enhance = function (root) {
    A.$$('[data-pick]', root).forEach(function (btn) {
      btn.addEventListener('click', function () {
        A.pickMedia(btn.dataset.folder).then(function (path) {
          if (!path) return;
          var input = root.querySelector('[name="' + btn.dataset.pick + '"]');
          input.value = path;
          input.dispatchEvent(new Event('input', { bubbles: true }));
        });
      });
    });
    A.$$('[data-image-field]', root).forEach(function (box) {
      var input = box.querySelector('input');
      input.addEventListener('input', function () { box.querySelector('img').src = input.value || 'assets/images/ui/placeholder.svg'; });
    });
    A.$$('[data-kv-add]', root).forEach(function (btn) {
      btn.addEventListener('click', function () { root.querySelector('[data-kv="' + btn.dataset.kvAdd + '"]').insertAdjacentHTML('beforeend', kvRow({})); });
    });
    root.addEventListener('click', function (e) { var d = e.target.closest('[data-kv-del]'); if (d) d.parentNode.remove(); });
    A.$$('[data-multi]', root).forEach(initMulti);
    A.$$('[data-editor]', root).forEach(function (slot) {
      var ta = root.querySelector('textarea[name="' + slot.dataset.editor + '"]');
      if (A.Editor) A.Editor.mount(slot, ta);
    });
  };

  /** "brand_name+name" joins several fields into one label. */
  function labelOf(it, key) { return key.split('+').map(function (k) { return it[k]; }).filter(Boolean).join(' ') || it.title || it.name; }

  /** Async multi-select backed by an admin list endpoint (?q=). Stores ids. */
  function initMulti(box) {
    var input = box.querySelector('input');
    var menu = box.querySelector('.a-multi__menu');
    var chips = box.querySelector('.a-multi__chips');
    var labelKey = box.dataset.label;
    var selected = {};
    var ids = JSON.parse(box.dataset.value || '[]');
    function draw() {
      chips.innerHTML = Object.keys(selected).map(function (id) {
        return '<span class="a-chip">' + A.esc(selected[id]) + '<button type="button" data-unpick="' + id + '" aria-label="حذف">×</button></span>';
      }).join('');
      box.dataset.selected = JSON.stringify(Object.keys(selected).map(Number));
    }
    if (ids.length) {
      A.request('GET', box.dataset.endpoint + A.qs({ ids: ids.join(','), per_page: 100 })).then(function (d) {
        var items = d.items || d;
        ids.forEach(function (id) {
          var it = items.filter(function (x) { return Number(x.id) === Number(id); })[0];
          selected[id] = it ? labelOf(it, labelKey) : '#' + id;
        });
        draw();
      }).catch(function () { ids.forEach(function (id) { selected[id] = '#' + id; }); draw(); });
    }
    draw();
    var search = A.debounce(function () {
      A.request('GET', box.dataset.endpoint + A.qs({ q: input.value.trim(), per_page: 12 })).then(function (d) {
        var items = d.items || d;
        menu.innerHTML = items.length ? items.map(function (it) {
          return '<button type="button" data-pick-id="' + it.id + '">' + A.esc(labelOf(it, labelKey)) + '</button>';
        }).join('') : '<p class="a-hint">موردی پیدا نشد.</p>';
        menu.hidden = false;
      });
    }, 250);
    input.addEventListener('input', search);
    input.addEventListener('focus', search);
    box.addEventListener('click', function (e) {
      var pick = e.target.closest('[data-pick-id]');
      if (pick) { selected[pick.dataset.pickId] = pick.textContent; draw(); menu.hidden = true; input.value = ''; return; }
      var un = e.target.closest('[data-unpick]');
      if (un) { delete selected[un.dataset.unpick]; draw(); }
    });
    document.addEventListener('click', function (e) { if (!box.contains(e.target)) menu.hidden = true; });
  }

  /** Reads a form built with A.field into a plain object. */
  A.read = function (form, fields) {
    var out = {};
    fields.forEach(function (f) {
      if (f.type === 'readonly') return;
      var el = form.elements[f.name];
      switch (f.type) {
        case 'checkbox': out[f.name] = !!(el && el.checked); break;
        case 'multi': out[f.name] = JSON.parse((form.querySelector('[data-multi="' + f.name + '"]') || {}).dataset.selected || '[]'); break;
        case 'tags': out[f.name] = (el.value || '').split(/[،,]/).map(function (s) { return s.trim(); }).filter(Boolean); break;
        case 'keyvalue':
          out[f.name] = A.$$('.a-kv__row', form.querySelector('[data-kv="' + f.name + '"]')).map(function (r) {
            return { name: r.querySelector('[data-k]').value.trim(), value: r.querySelector('[data-v]').value.trim() };
          }).filter(function (r) { return r.name && r.value; }); break;
        case 'color': out[f.name] = form.querySelector('[data-color-none="' + f.name + '"]').checked ? '' : el.value; break;
        case 'html': if (A.Editor) A.Editor.sync(form, f.name); out[f.name] = el.value; break;
        case 'number': out[f.name] = el.value === '' ? '' : A.en(el.value); break;
        // technical fields (left-to-right, email, url) get Latin digits; Persian text keeps its own digits
        default: out[f.name] = el ? (f.dir === 'ltr' || f.type === 'email' || f.type === 'url' ? A.en(el.value) : el.value).trim() : '';
      }
    });
    return out;
  };

  A.showErrors = function (form, err) {
    A.$$('[data-error-for]', form).forEach(function (e) { e.textContent = ''; });
    var first;
    Object.keys(err.errors || {}).forEach(function (k) {
      var slot = form.querySelector('[data-error-for="' + k + '"]');
      if (slot) { slot.textContent = err.errors[k]; first = first || slot; }
    });
    if (first) first.scrollIntoView({ block: 'center', behavior: 'smooth' });
    A.toast(err.message, 'error');
  };

  /** Modal form: resolves after a successful save(data) promise. */
  A.formModal = function (opts) {
    return new Promise(function (resolve) {
      var m = A.modal({ title: opts.title, wide: opts.wide,
        body: '<form class="a-form a-grid" novalidate>' + opts.fields.map(function (f) { return A.field(f, opts.data ? opts.data[f.name] : undefined); }).join('') + (opts.extra || '') + '</form>',
        foot: '<button type="button" class="a-btn a-btn--primary" data-save>' + (opts.saveText || 'ذخیره') + '</button><button type="button" class="a-btn a-btn--light" data-close>انصراف</button>' });
      var form = m.el.querySelector('form');
      A.enhance(form);
      if (opts.onOpen) opts.onOpen(form, m);
      function save() {
        var data = A.read(form, opts.fields);
        var btn = m.el.querySelector('[data-save]');
        btn.disabled = true;
        opts.save(data).then(function (res) { m.close(); resolve(res); }).catch(function (err) { btn.disabled = false; A.showErrors(form, err); });
      }
      m.el.querySelector('[data-save]').addEventListener('click', save);
      form.addEventListener('submit', function (e) { e.preventDefault(); save(); });
    });
  };

  /* ---------------- List view ---------------- */
  /**
   * opts: { title, endpoint (admin path), columns: [{key,label,render(row),sort}], filters: [{name,label,options}], search: bool,
   *         actions(row) → html, toolbar: html, onRow(e,row), defaultQuery, empty }
   */
  A.list = function (opts) {
    var q = Object.assign({ page: 1 }, opts.defaultQuery || {}, A.query());
    var root = A.view(opts.title, '<div class="a-card">' +
      '<div class="a-toolbar">' + (opts.search !== false ? '<input class="a-input a-toolbar__search" type="search" placeholder="جستجو…" value="' + A.esc(q.q || '') + '" data-q>' : '') +
      (opts.filters || []).map(function (f) {
        return '<select class="a-input" data-filter="' + f.name + '" aria-label="' + A.esc(f.label) + '"><option value="">' + A.esc(f.label) + '</option>' + f.options.map(function (o) {
          return '<option value="' + A.esc(o[0]) + '"' + (String(q[f.name]) === String(o[0]) ? ' selected' : '') + '>' + A.esc(o[1]) + '</option>';
        }).join('') + '</select>';
      }).join('') + '<span class="a-toolbar__spacer"></span>' + (opts.toolbar || '') + '</div>' +
      (opts.tabs ? '<div class="a-tabs">' + opts.tabs.map(function (t) { return '<button type="button" class="a-tab" data-tab-value="' + t[0] + '" aria-pressed="' + (String(q[opts.tabKey] || '') === t[0]) + '">' + A.esc(t[1]) + '</button>'; }).join('') + '</div>' : '') +
      '<div class="a-table-wrap"><table class="a-table"><thead><tr>' + opts.columns.map(function (c) {
        return '<th>' + (c.sort ? '<button type="button" data-sort="' + c.sort + '">' + A.esc(c.label) + (q.sort === c.sort ? (q.dir === 'asc' ? ' ▲' : ' ▼') : '') + '</button>' : A.esc(c.label)) + '</th>';
      }).join('') + (opts.actions ? '<th>عملیات</th>' : '') + '</tr></thead><tbody data-body><tr><td colspan="99" class="a-loading">در حال بارگذاری…</td></tr></tbody></table></div>' +
      '<div class="a-pager" data-pager></div></div>');
    var body = A.$('[data-body]', root);
    var rows = [];

    function load() {
      A.setQuery(q);
      A.get(opts.endpoint, q).then(function (d) {
        rows = d.items;
        if (opts.onData) opts.onData(d, root);
        body.innerHTML = rows.length ? rows.map(function (r, i) {
          return '<tr data-i="' + i + '">' + opts.columns.map(function (c) { return '<td>' + (c.render ? c.render(r) : /^-?\d+$/.test(String(r[c.key])) ? A.fa(r[c.key]) : A.esc(r[c.key])) + '</td>'; }).join('') +
            (opts.actions ? '<td class="a-actions">' + opts.actions(r) + '</td>' : '') + '</tr>';
        }).join('') : '<tr><td colspan="99"><div class="a-empty">' + (opts.empty || 'موردی یافت نشد.') + '</div></td></tr>';
        var p = d.pagination;
        A.$('[data-pager]', root).innerHTML = p && p.pages > 1 ? '<span>' + A.num(p.total) + ' مورد</span>' +
          '<button type="button" class="a-btn a-btn--light a-btn--sm" data-page="' + (p.page - 1) + '"' + (p.page <= 1 ? ' disabled' : '') + '>قبلی</button>' +
          '<span>صفحه ' + A.fa(p.page) + ' از ' + A.fa(p.pages) + '</span>' +
          '<button type="button" class="a-btn a-btn--light a-btn--sm" data-page="' + (p.page + 1) + '"' + (p.page >= p.pages ? ' disabled' : '') + '>بعدی</button>'
          : (p ? '<span>' + A.num(p.total) + ' مورد</span>' : '');
      }).catch(function (err) { body.innerHTML = '<tr><td colspan="99" class="a-error">' + A.esc(err.message) + '</td></tr>'; });
    }

    var search = A.debounce(function (v) { q.q = v; q.page = 1; load(); }, 300);
    root.addEventListener('input', function (e) { if (e.target.matches('[data-q]')) search(e.target.value.trim()); });
    root.addEventListener('change', function (e) {
      var f = e.target.closest('[data-filter]');
      if (f) { q[f.dataset.filter] = f.value; q.page = 1; load(); }
    });
    root.addEventListener('click', function (e) {
      var s = e.target.closest('[data-sort]');
      if (s) { q.dir = q.sort === s.dataset.sort && q.dir !== 'asc' ? 'asc' : 'desc'; q.sort = s.dataset.sort; A.list(Object.assign({}, opts, { defaultQuery: q })); return; }
      var pg = e.target.closest('[data-page]');
      if (pg && !pg.disabled) { q.page = Number(pg.dataset.page); load(); return; }
      var tab = e.target.closest('[data-tab-value]');
      if (tab) { q[opts.tabKey] = tab.dataset.tabValue; q.page = 1; A.$$('[data-tab-value]', root).forEach(function (t) { t.setAttribute('aria-pressed', String(t === tab)); }); load(); return; }
      var tr = e.target.closest('tr[data-i]');
      if (tr && opts.onRow) opts.onRow(e, rows[Number(tr.dataset.i)], load);
    });
    load();
    return { reload: load, root: root };
  };

  /** Generic CRUD screen for /api/admin/r/{key}. */
  A.crud = function (key, opts) {
    var endpoint = 'r/' + key;
    function edit(row, reload) {
      var p = row ? A.get(endpoint + '/' + row.id) : Promise.resolve(opts.defaults || {});
      p.then(function (data) {
        var fields = typeof opts.fields === 'function' ? opts.fields(data) : opts.fields;
        (opts.dates || []).forEach(function (d) { data[d] = data[d + '_local'] || ''; });
        A.formModal({ title: (row ? 'ویرایش ' : 'افزودن ') + opts.label, fields: fields, data: data, wide: opts.wide, extra: opts.extra ? opts.extra(data) : '', onOpen: opts.onOpen,
          save: function (d) { return row ? A.put(endpoint + '/' + row.id, d) : A.post(endpoint, d); } })
          .then(function () { A.toast(opts.label + ' ذخیره شد.'); reload(); });
      }).catch(A.fail);
    }
    var list = A.list({
      title: opts.title, endpoint: endpoint, columns: opts.columns, filters: opts.filters, tabs: opts.tabs, tabKey: opts.tabKey,
      toolbar: (opts.readonly ? '' : '<button type="button" class="a-btn a-btn--primary a-btn--sm" data-create>' + A.icon('plus', 'icon--sm') + 'افزودن ' + opts.label + '</button>') + (opts.toolbar || ''),
      actions: function (r) {
        return (opts.rowActions ? opts.rowActions(r) : '') + '<button type="button" class="a-btn a-btn--light a-btn--sm" data-edit>' + (opts.readonly ? 'مشاهده' : 'ویرایش') + '</button>' +
          (opts.noDelete ? '' : '<button type="button" class="a-btn a-btn--danger-ghost a-btn--sm" data-delete>حذف</button>');
      },
      onRow: function (e, row, reload) {
        if (e.target.closest('[data-edit]')) edit(row, reload);
        if (e.target.closest('[data-delete]')) {
          A.confirm('«' + (row.name || row.title || row.code || row.source_path || row.email || row.subject || ('#' + row.id)) + '» حذف شود؟ این عملیات قابل بازگشت نیست.').then(function (ok) {
            if (ok) A.del(endpoint + '/' + row.id).then(function (r) { A.toast(opts.label + ' حذف شد.'); reload(); }).catch(A.fail);
          });
        }
        if (opts.onRowAction) opts.onRowAction(e, row, reload);
      }
    });
    list.root.addEventListener('click', function (e) { if (e.target.closest('[data-create]')) edit(null, list.reload); });
    return list;
  };

  /* ---------------- Media picker ---------------- */
  A.pickMedia = function (folder) {
    return new Promise(function (resolve) {
      var chosen = null;
      var m = A.modal({ title: 'انتخاب تصویر', wide: true,
        body: '<div class="a-toolbar"><input class="a-input" type="search" placeholder="جستجو…" data-mq><label class="a-btn a-btn--primary a-btn--sm a-upload">' + A.icon('plus', 'icon--sm') + 'بارگذاری تصویر جدید<input type="file" accept="image/jpeg,image/png,image/webp" hidden data-mfile></label></div>' +
          '<p class="a-hint">فرمت‌های مجاز: JPG، PNG، WebP تا ۵ مگابایت. می‌توانید مسیر تصاویر داخلی پروژه (assets/...) را هم مستقیم در فیلد وارد کنید.</p><div class="a-media-grid" data-mgrid></div>' });
      var grid = m.el.querySelector('[data-mgrid]');
      function load(q) {
        A.get('media', { q: q || '', per_page: 48 }).then(function (d) {
          grid.innerHTML = d.items.length ? d.items.map(function (x) {
            return '<button type="button" class="a-media" data-path="' + A.esc(x.path) + '"><img src="' + A.esc(x.thumb_path || x.path) + '" alt="' + A.esc(x.alt || '') + '" loading="lazy"><span>' + A.esc(x.original_name) + '</span></button>';
          }).join('') : '<div class="a-empty">هنوز تصویری بارگذاری نشده است.</div>';
        }).catch(A.fail);
      }
      m.el.addEventListener('input', A.debounce(function (e) { if (e.target.matches('[data-mq]')) load(e.target.value); }, 300));
      m.el.addEventListener('change', function (e) {
        if (!e.target.matches('[data-mfile]') || !e.target.files[0]) return;
        var fd = new FormData();
        fd.append('file', e.target.files[0]);
        fd.append('folder', folder || 'general');
        A.post('media', fd).then(function (x) { A.toast('تصویر بارگذاری شد.'); chosen = x.path; m.close(); resolve(chosen); }).catch(A.fail);
      });
      m.el.addEventListener('click', function (e) {
        var b = e.target.closest('[data-path]');
        if (b) { chosen = b.dataset.path; m.close(); resolve(chosen); }
        if (e.target.closest('[data-close]') && !chosen) resolve(null);
      });
      load('');
    });
  };

  /* ---------------- Charts (canvas, no library) ---------------- */
  A.chart = function (canvas, labels, series, opts) {
    opts = opts || {};
    var dpr = window.devicePixelRatio || 1;
    var w = canvas.clientWidth, h = canvas.clientHeight || 240;
    canvas.width = w * dpr; canvas.height = h * dpr;
    var ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, w, h);
    var pad = { l: 56, r: 12, t: 12, b: 28 };
    var max = Math.max.apply(null, series.reduce(function (a, s) { return a.concat(s.data); }, [1]));
    var gw = w - pad.l - pad.r, gh = h - pad.t - pad.b;
    ctx.font = '11px Tahoma, sans-serif';
    ctx.fillStyle = '#888';
    ctx.strokeStyle = '#eee';
    for (var i = 0; i <= 4; i++) {
      var y = pad.t + gh - gh * i / 4;
      ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(w - pad.r, y); ctx.stroke();
      ctx.textAlign = 'right';
      ctx.fillText(A.num(max * i / 4 / (opts.unit || 1)) + (opts.unitLabel || ''), pad.l - 6, y + 4);
    }
    var step = gw / Math.max(1, labels.length - (opts.bar ? 0 : 1));
    ctx.textAlign = 'center';
    var every = Math.ceil(labels.length / 10);
    labels.forEach(function (l, i) {
      if (i % every === 0) ctx.fillText(l, pad.l + (opts.bar ? step * (i + 0.5) : step * i), h - 8);
    });
    series.forEach(function (s) {
      ctx.strokeStyle = s.color; ctx.fillStyle = s.color; ctx.lineWidth = 2;
      if (opts.bar) {
        s.data.forEach(function (v, i) {
          var bh = gh * v / max;
          ctx.fillRect(pad.l + step * i + step * 0.2, pad.t + gh - bh, step * 0.6, bh);
        });
        return;
      }
      ctx.beginPath();
      s.data.forEach(function (v, i) {
        var x = pad.l + step * i, y = pad.t + gh - gh * v / max;
        if (i) ctx.lineTo(x, y); else ctx.moveTo(x, y);
      });
      ctx.stroke();
      ctx.globalAlpha = 0.08;
      ctx.lineTo(pad.l + step * (s.data.length - 1), pad.t + gh); ctx.lineTo(pad.l, pad.t + gh); ctx.closePath(); ctx.fill();
      ctx.globalAlpha = 1;
    });
  };

  /* ---------------- Boot ---------------- */
  A.boot = function () {
    A.request('GET', 'api/admin/auth/me').then(function (me) {
      A.me = me;
      document.getElementById('admin-user').textContent = me.admin.name + (me.admin.roles.length ? ' — ' + me.admin.roles.join('، ') : '');
      var badge = document.getElementById('admin-unread');
      badge.textContent = A.num(me.unread_notifications);
      badge.hidden = !me.unread_notifications;
      renderSide();
      window.addEventListener('hashchange', dispatch);
      dispatch();
    }).catch(A.fail);
    document.getElementById('admin-logout').addEventListener('click', function () {
      A.post('auth/logout').then(function () { location.reload(); });
    });
    document.getElementById('admin-menu-btn').addEventListener('click', function () {
      var el = document.getElementById('admin');
      var open = !el.classList.contains('is-menu-open');
      el.classList.toggle('is-menu-open', open);
      this.setAttribute('aria-expanded', String(open));
    });
  };
})();
