/* AutoPart admin — screens. Every action goes through /api/admin/* where PHP re-checks permissions; hiding a menu is only cosmetic. */
(function () {
  'use strict';

  var esc = A.esc, $ = A.$, $$ = A.$$;

  /* ---------------- Shared helpers ---------------- */
  function opts(map) { return Object.keys(map).map(function (k) { return [k, map[k]]; }); }
  var ACTIVE = [['1', 'فعال'], ['0', 'غیرفعال']];
  var ORDER_TONE = { pending_payment: 'orange', paid: 'blue', processing: 'blue', ready_to_ship: 'blue', shipped: 'blue', delivered: 'green', cancelled: 'muted', refunded: 'red' };
  var ORDER_STATUS = { pending_payment: 'در انتظار پرداخت', paid: 'پرداخت‌شده', processing: 'در حال پردازش', ready_to_ship: 'آماده ارسال', shipped: 'ارسال‌شده', delivered: 'تحویل‌شده', cancelled: 'لغوشده', refunded: 'مستردشده' };
  var PRODUCT_STATUS = { draft: 'پیش‌نویس', published: 'منتشرشده', archived: 'بایگانی' };
  var POST_STATUS = { draft: 'پیش‌نویس', scheduled: 'زمان‌بندی‌شده', published: 'منتشرشده', unpublished: 'لغو انتشار' };
  var REVIEW_STATUS = { pending: 'در انتظار بررسی', approved: 'تأییدشده', rejected: 'ردشده' };
  var COMMENT_STATUS = { pending: 'در انتظار', approved: 'تأییدشده', rejected: 'ردشده', spam: 'اسپم' };
  var PLACEMENTS = { hero: 'اسلایدر اصلی', promo: 'بنر تبلیغاتی', category: 'صفحه دسته', special_offer: 'پیشنهاد ویژه', product: 'صفحه محصول', blog: 'وبلاگ', mobile: 'موبایل' };
  var TONE = { published: 'green', approved: 'green', active: 'green', subscribed: 'green', draft: 'muted', archived: 'muted', unpublished: 'muted', pending: 'orange', scheduled: 'blue', rejected: 'red', spam: 'red', suspended: 'red', disabled: 'red', new: 'orange', read: 'muted' };
  var ROBOTS = [['', 'پیش‌فرض (بر اساس قابل ایندکس بودن)'], ['index, follow', 'index, follow'], ['noindex, follow', 'noindex, follow'], ['index, nofollow', 'index, nofollow'], ['noindex, nofollow', 'noindex, nofollow']];

  function status(map, v) { return A.badge(map[v] || v, TONE[v] || ORDER_TONE[v] || 'muted'); }
  function stars(n) { n = Math.round(Number(n) || 0); return '<span class="a-stars" aria-label="' + A.fa(n) + ' از ۵">' + '★★★★★'.slice(0, n) + '<span class="a-muted">' + '★★★★★'.slice(n) + '</span></span>'; }
  function ltr(v) { return v ? '<span class="a-ltr">' + esc(v) + '</span>' : '<span class="a-muted">—</span>'; }
  function cut(v, n) { v = String(v || ''); return esc(v.length > n ? v.slice(0, n) + '…' : v); }
  function card(title, body, extra) { return '<section class="a-card"><h2 class="a-card__title">' + esc(title) + (extra || '') + '</h2>' + body + '</section>'; }
  function btn(text, attr, cls) { return '<button type="button" class="a-btn ' + (cls || 'a-btn--light') + ' a-btn--sm" ' + attr + '>' + text + '</button>'; }
  function idOptions(items, label) { return items.map(function (x) { return [String(x.id), x[label || 'name']]; }); }
  function failView(title) { return function (err) { A.view(title, '<div class="a-alert a-alert--error">' + esc(err.message) + '</div>'); }; }
  function siteUrl(path) { return window.ADMIN.base + (path || ''); }
  function all(path, params) { return A.get(path, Object.assign({ per_page: 100 }, params || {})).then(function (d) { return d.items; }); }

  /** Registers a screen; the permission check here only avoids pointless requests — the API enforces it. */
  function page(pattern, perm, fn) {
    A.route(pattern, function (params, query) {
      if (perm && !A.canAny(perm)) {
        A.view('دسترسی غیرمجاز', '<div class="a-alert a-alert--error">شما مجوز دسترسی به این بخش را ندارید.</div>');
        return;
      }
      fn(params, query);
    });
  }

  /** Simple tabs: buttons [data-tab] toggle panels [data-panel]. */
  function tabs(root) {
    root.addEventListener('click', function (e) {
      var t = e.target.closest('[data-tab]');
      if (!t || !root.contains(t)) return;
      $$('[data-tab]', root).forEach(function (b) { b.setAttribute('aria-selected', String(b === t)); });
      $$('[data-panel]', root).forEach(function (p) { p.hidden = p.dataset.panel !== t.dataset.tab; });
    });
  }
  function tabBar(list) {
    return '<div class="a-tabs" role="tablist">' + list.map(function (t, i) { return '<button type="button" class="a-tab" role="tab" data-tab="' + t[0] + '" aria-selected="' + (i === 0) + '">' + esc(t[1]) + '</button>'; }).join('') + '</div>';
  }
  function fields(list, data) { return list.map(function (f) { return A.field(f, data[f.name]); }).join(''); }

  /** Moves a repeatable row up/down/removes it. */
  function rowTools(container) {
    container.addEventListener('click', function (e) {
      var row = e.target.closest('.a-row');
      if (!row) return;
      if (e.target.closest('[data-up]') && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
      if (e.target.closest('[data-down]') && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
      if (e.target.closest('[data-remove]')) row.remove();
    });
  }
  var TOOLS = '<div class="a-row__tools"><button type="button" class="a-icon-btn" data-up aria-label="انتقال به بالا">▲</button><button type="button" class="a-icon-btn" data-down aria-label="انتقال به پایین">▼</button>' +
    '<button type="button" class="a-icon-btn" data-remove aria-label="حذف">' + A.icon('trash', 'icon--sm') + '</button></div>';

  /* ---------------- SEO block (products, posts, any content) ---------------- */
  var SEO_FIELDS = [
    { name: 'seo_title', label: 'عنوان سئو (Title)', full: true, maxlength: 190, help: 'خالی بماند تا از عنوان محتوا ساخته شود.' },
    { name: 'meta_description', label: 'توضیحات متا', type: 'textarea', rows: 3, full: true, maxlength: 320 },
    { name: 'canonical_url', label: 'آدرس Canonical', dir: 'ltr', full: true, help: 'فقط اگر نسخه اصلی این صفحه آدرس دیگری دارد.' },
    { name: 'is_indexable', label: 'قابل ایندکس توسط موتورهای جستجو', type: 'checkbox', default: 1 },
    { name: 'meta_robots', label: 'Meta robots', type: 'select', options: ROBOTS },
    { name: 'og_title', label: 'عنوان Open Graph' },
    { name: 'og_description', label: 'توضیحات Open Graph' },
    { name: 'og_image', label: 'تصویر Open Graph', type: 'image', folder: 'general', full: true },
    { name: 'twitter_title', label: 'عنوان Twitter' },
    { name: 'twitter_description', label: 'توضیحات Twitter' }
  ];
  function seoForm(record, defaults) {
    var data = Object.assign({ is_indexable: 1 }, record || {});
    return '<div class="a-grid" data-seo-form>' + fields(SEO_FIELDS, data) + '</div><h3 class="a-hint">پیش‌نمایش در نتایج گوگل</h3><div data-serp></div>';
  }
  function bindSerp(root, getDefaults) {
    var box = $('[data-serp]', root);
    function draw() {
      var d = getDefaults();
      var t = $('[name="seo_title"]', root).value.trim() || d.title;
      var desc = $('[name="meta_description"]', root).value.trim() || d.description;
      box.innerHTML = A.serpPreview(t, d.url, desc);
    }
    root.addEventListener('input', A.debounce(draw, 200));
    draw();
  }
  /** root is the <form> containing the SEO block (A.read needs form.elements). */
  function readSeo(root) { return A.read(root, SEO_FIELDS); }
  function seoHasValue(d) { return Object.keys(d).some(function (k) { return k === 'is_indexable' ? !d[k] : !!d[k]; }); }

  /* ================================================================
     Dashboard, reports, notifications
     ================================================================ */
  function stat(label, value, href, warn) {
    var tag = href ? 'a' : 'div';
    return '<' + tag + ' class="a-stat' + (warn ? ' a-stat--warn' : '') + '"' + (href ? ' href="admin' + href + '"' : '') + '><span>' + esc(label) + '</span><strong>' + value + '</strong></' + tag + '>';
  }
  function salesChart(canvas, rows) {
    A.chart(canvas, rows.map(function (r) { return r.label; }), [{ data: rows.map(function (r) { return r.total; }), color: '#D71920' }], { unit: 1000000, unitLabel: ' م' });
  }

  page('#/dashboard', 'dashboard.view', function () {
    A.loading('داشبورد');
    A.get('dashboard').then(function (d) {
      var c = d.cards;
      var cards = [
        ['sales_today', 'فروش امروز', A.money(c.sales_today), '#/reports'], ['sales_month', 'فروش این ماه', A.money(c.sales_month), '#/reports'],
        ['orders_total', 'کل سفارش‌ها', A.num(c.orders_total), '#/orders'], ['orders_new', 'سفارش‌های در جریان', A.num(c.orders_new), '#/orders?status=paid'],
        ['customers', 'مشتریان', A.num(c.customers), '#/customers'], ['products', 'محصولات منتشرشده', A.num(c.products), '#/products'],
        ['low_stock', 'محصولات کم‌موجود', A.num(c.low_stock), '#/products?stock=low', c.low_stock > 0], ['pending_reviews', 'نظرات در انتظار', A.num(c.pending_reviews), '#/reviews?status=pending', c.pending_reviews > 0],
        ['unread_messages', 'پیام‌های خوانده‌نشده', A.num(c.unread_messages), '#/messages?status=new', c.unread_messages > 0], ['active_banners', 'بنرهای فعال', A.num(c.active_banners), '#/banners'],
        ['posts_published', 'مقالات منتشرشده', A.num(c.posts_published), '#/blog/posts?status=published'], ['posts_draft', 'پیش‌نویس‌ها', A.num(c.posts_draft), '#/blog/posts?status=draft']
      ].filter(function (x) { return c[x[0]] !== null && c[x[0]] !== undefined; });
      var html = '<div class="a-stats">' + cards.map(function (x) { return stat(x[1], x[2], x[3], x[4]); }).join('') + '</div>';
      if (d.sales_chart.length) html += card('فروش ۳۰ روز اخیر (میلیون تومان)', '<canvas class="a-chart" data-chart role="img" aria-label="نمودار فروش روزانه"></canvas>', '<a href="admin#/reports">گزارش کامل</a>');
      var orders = d.latest_orders.length ? '<div class="a-table-wrap"><table class="a-table"><tbody>' + d.latest_orders.map(function (o) {
        return '<tr><td><a href="admin#/orders/' + o.id + '">' + A.fa(o.order_number) + '</a></td><td>' + esc(o.customer_name) + '</td><td>' + A.money(o.grand_total) + '</td><td>' + status(ORDER_STATUS, o.status) + '</td><td class="a-muted">' + esc(o.placed_at_fa) + '</td></tr>';
      }).join('') + '</tbody></table></div>' : '<div class="a-empty">هنوز سفارشی ثبت نشده است.</div>';
      var top = d.top_products.length ? '<ul class="a-list-plain">' + d.top_products.map(function (p) { return '<li><span>' + esc(p.name) + '</span><span>' + A.num(p.qty) + ' عدد</span></li>'; }).join('') + '</ul>' : '<div class="a-empty">هنوز فروشی ثبت نشده است.</div>';
      var low = d.low_stock.length ? '<ul class="a-list-plain">' + d.low_stock.map(function (p) { return '<li><a href="admin#/products/' + p.id + '">' + esc(p.name) + '</a>' + A.badge(A.num(p.stock) + ' عدد', Number(p.stock) ? 'orange' : 'red') + '</li>'; }).join('') + '</ul>' : '<div class="a-empty">موجودی همه محصولات کافی است.</div>';
      html += '<div class="a-cols a-cols--3" style="margin-top:16px">' + (A.can('orders.view') ? card('آخرین سفارش‌ها', orders, '<a href="admin#/orders">همه</a>') : '') + card('پرفروش‌ترین محصولات', top) + '</div>';
      var reviews = d.latest_reviews.length ? '<ul class="a-list-plain">' + d.latest_reviews.map(function (r) { return '<li><a href="admin#/reviews?status=' + r.status + '">' + cut(r.title, 40) + '<br><small>' + esc(r.product) + '</small></a>' + status(REVIEW_STATUS, r.status) + '</li>'; }).join('') + '</ul>' : '<div class="a-empty">نظری ثبت نشده است.</div>';
      var msgs = d.latest_messages.length ? '<ul class="a-list-plain">' + d.latest_messages.map(function (m) { return '<li><a href="admin#/messages">' + cut(m.subject || m.name, 40) + '<br><small>' + esc(m.name) + ' — ' + esc(m.at) + '</small></a>' + status({ new: 'جدید', read: 'خوانده‌شده', archived: 'بایگانی' }, m.status) + '</li>'; }).join('') + '</ul>' : '<div class="a-empty">پیامی وجود ندارد.</div>';
      html += '<div class="a-cols" style="margin-top:16px">' + card('کم‌موجودها', low) + (A.can('reviews.view') ? card('آخرین نظرات', reviews) : '') + (A.can('messages.manage') ? card('آخرین پیام‌ها', msgs) : '') + '</div>';
      var v = A.view('داشبورد', html);
      var canvas = $('[data-chart]', v);
      if (canvas) salesChart(canvas, d.sales_chart);
    }).catch(failView('داشبورد'));
  });

  page('#/reports', 'reports.view', function (p, q) {
    var days = q.days || '30';
    A.loading('گزارش‌ها');
    A.get('reports', { days: days }).then(function (d) {
      var s = d.summary;
      var html = '<div class="a-toolbar"><label for="r-days">بازه:</label><select class="a-input" id="r-days">' + [['7', '۷ روز'], ['30', '۳۰ روز'], ['90', '۹۰ روز'], ['365', 'یک سال']].map(function (o) {
        return '<option value="' + o[0] + '"' + (o[0] === String(d.range_days) ? ' selected' : '') + '>' + o[1] + '</option>';
      }).join('') + '</select><span class="a-hint">فقط سفارش‌های پرداخت‌شده و مراحل بعد از آن در فروش محاسبه می‌شوند.</span></div>';
      html += '<div class="a-stats">' + stat('فروش بازه', A.money(s.total)) + stat('تعداد سفارش', A.num(s.orders)) + stat('میانگین سفارش', A.money(s.average)) + stat('مجموع تخفیف‌ها', A.money(s.discounts)) +
        stat('سفارش‌های در انتظار پردازش', A.num(d.pending_orders), '#/orders?status=paid') + stat('نظرات در انتظار', A.num(d.pending_reviews), '#/reviews?status=pending') +
        stat('مقالات منتشرشده / پیش‌نویس / زمان‌بندی', A.fa(d.posts.published + ' / ' + d.posts.draft + ' / ' + d.posts.scheduled)) + stat('پیام‌های خوانده‌نشده', A.num(d.unread_messages), '#/messages') + '</div>';
      html += card('فروش روزانه (میلیون تومان)', '<canvas class="a-chart" data-daily role="img" aria-label="نمودار فروش روزانه"></canvas>');
      html += card('فروش ماهانه (میلیون تومان)', d.monthly.length ? '<canvas class="a-chart" data-monthly role="img" aria-label="نمودار فروش ماهانه"></canvas>' : '<div class="a-empty">داده‌ای برای ۱۲ ماه اخیر وجود ندارد.</div>');
      var maxQ = Math.max.apply(null, d.top_products.map(function (x) { return Number(x.qty); }).concat([1]));
      var top = d.top_products.length ? '<div class="a-bars">' + d.top_products.map(function (x) {
        return '<div><span title="' + esc(x.name) + '">' + cut(x.name, 18) + '</span><span><i style="width:' + (Number(x.qty) / maxQ * 100) + '%"></i></span><span>' + A.num(x.qty) + '</span></div>';
      }).join('') + '</div>' : '<div class="a-empty">در این بازه فروشی ثبت نشده است.</div>';
      var st = d.status_counts.length ? '<ul class="a-list-plain">' + d.status_counts.map(function (x) { return '<li><span>' + status(ORDER_STATUS, x.status) + '</span><span>' + A.num(x.c) + '</span></li>'; }).join('') + '</ul>' : '<div class="a-empty">سفارشی وجود ندارد.</div>';
      html += '<div class="a-cols" style="margin-top:16px">' + card('پرفروش‌ترین محصولات بازه', top) + card('سفارش‌ها بر اساس وضعیت', st) + '</div>';
      var low = d.low_stock.length ? '<ul class="a-list-plain">' + d.low_stock.map(function (x) { return '<li><a href="admin#/products/' + x.id + '">' + esc(x.name) + '</a><span>' + A.num(x.stock) + '</span></li>'; }).join('') + '</ul>' : '<div class="a-empty">کالای کم‌موجود نداریم.</div>';
      var cust = d.new_customers.length ? '<ul class="a-list-plain">' + d.new_customers.map(function (x) { return '<li><a href="admin#/customers/' + x.id + '">' + esc(x.name) + '</a><span class="a-muted">' + esc(x.at) + '</span></li>'; }).join('') + '</ul>' : '<div class="a-empty">مشتری جدیدی ثبت نشده است.</div>';
      html += '<div class="a-cols" style="margin-top:16px">' + card('محصولات کم‌موجود', low) + (A.can('customers.view') ? card('مشتریان جدید', cust) : '') + '</div>';
      var v = A.view('گزارش‌ها', html);
      salesChart($('[data-daily]', v), d.daily);
      if (d.monthly.length) A.chart($('[data-monthly]', v), d.monthly.map(function (m) { return m.label; }), [{ data: d.monthly.map(function (m) { return m.total; }), color: '#1557b0' }], { bar: true, unit: 1000000, unitLabel: ' م' });
      $('#r-days', v).addEventListener('change', function () { A.go('#/reports?days=' + this.value); });
    }).catch(failView('گزارش‌ها'));
  });

  page('#/notifications', 'notifications.view', function () {
    A.loading('اعلان‌ها');
    A.get('notifications').then(function (rows) {
      var html = '<div class="a-card"><div class="a-toolbar"><span class="a-toolbar__spacer"></span>' + btn('علامت‌گذاری همه به‌عنوان خوانده‌شده', 'data-read-all') + '</div>' +
        (rows.length ? '<ul class="a-list-plain">' + rows.map(function (n) {
          return '<li><div>' + (n.read_at ? '' : A.badge('جدید', 'red') + ' ') + (n.link ? '<a href="' + esc(n.link) + '">' + esc(n.title) + '</a>' : esc(n.title)) + (n.body ? '<br><small>' + esc(n.body) + '</small>' : '') + '</div><span class="a-muted">' + esc(n.at) + '</span></li>';
        }).join('') + '</ul>' : '<div class="a-empty">اعلانی وجود ندارد.</div>') + '</div>';
      var v = A.view('اعلان‌ها', html);
      $('[data-read-all]', v).addEventListener('click', function () {
        A.post('notifications/read').then(function () { document.getElementById('admin-unread').hidden = true; A.toast('همه اعلان‌ها خوانده شد.'); A.go('#/notifications'); }).catch(A.fail);
      });
    }).catch(failView('اعلان‌ها'));
  });

  /* ================================================================
     Products
     ================================================================ */
  var FLAGS = [['is_featured', 'ویژه'], ['is_bestseller', 'پرفروش'], ['is_new', 'جدید'], ['is_amazing', 'شگفت‌انگیز']];

  function stockModal(row, reload) {
    A.formModal({ title: 'تغییر موجودی: ' + row.name, saveText: 'ثبت تغییر',
      fields: [{ name: 'change', label: 'مقدار تغییر (مثبت برای افزایش، منفی برای کاهش)', type: 'number', step: 1, required: true, full: true, help: 'موجودی فعلی: ' + A.num(row.stock) + ' عدد' },
        { name: 'note', label: 'توضیح (مثلاً ورود بار جدید)', full: true }],
      save: function (d) { return A.post('products/' + row.id + '/stock', d); } })
      .then(function (r) { A.toast('موجودی جدید: ' + A.num(r.stock)); reload(); });
  }

  page('#/products', 'products.view|products.manage', function () {
    A.loading('محصولات');
    Promise.all([all('r/categories'), all('r/brands')]).then(function (r) {
      var manage = A.can('products.manage');
      A.list({
        title: 'محصولات', endpoint: 'products',
        filters: [{ name: 'status', label: 'همه وضعیت‌ها', options: opts(PRODUCT_STATUS) },
          { name: 'stock', label: 'موجودی', options: [['in', 'موجود'], ['low', 'کم‌موجود'], ['out', 'ناموجود']] },
          { name: 'category_id', label: 'همه دسته‌ها', options: idOptions(r[0]) }, { name: 'brand_id', label: 'همه برندها', options: idOptions(r[1]) }],
        toolbar: manage ? '<a class="a-btn a-btn--primary a-btn--sm" href="admin#/products/new">' + A.icon('plus', 'icon--sm') + 'افزودن محصول</a>' : '',
        columns: [
          { label: 'محصول', sort: 'name', render: function (x) {
            return '<div class="a-cell-main"><img class="a-thumb" src="' + esc(x.image || 'assets/images/ui/placeholder.svg') + '" alt="" width="44" height="44" loading="lazy"><div><strong>' + esc(x.name) + '</strong><small>' + esc([x.category_name, x.brand_name].filter(Boolean).join(' · ')) + '</small></div></div>';
          } },
          { label: 'SKU', render: function (x) { return ltr(x.sku); } },
          { label: 'قیمت', sort: 'price', render: function (x) {
            return Number(x.final_price) < Number(x.price) ? '<del class="a-muted">' + A.num(x.price) + '</del><br>' + A.money(x.final_price) : A.money(x.price);
          } },
          { label: 'موجودی', sort: 'stock', render: function (x) {
            var s = Number(x.stock);
            return A.badge(s ? A.num(s) + ' عدد' : 'ناموجود', !s ? 'red' : s <= Number(x.low_threshold) ? 'orange' : 'green') + (A.can('inventory.manage') ? ' <button type="button" class="a-icon-btn" data-stock aria-label="تغییر موجودی">' + A.icon('plus', 'icon--sm') + '</button>' : '');
          } },
          { label: 'فروش', sort: 'sales', render: function (x) { return A.num(x.sales_count); } },
          { label: 'وضعیت', render: function (x) { return status(PRODUCT_STATUS, x.status); } },
          { label: 'نشان‌ها', render: function (x) {
            return FLAGS.map(function (f) {
              return manage ? '<button type="button" class="a-btn a-btn--sm ' + (Number(x[f[0]]) ? 'a-btn--primary' : 'a-btn--ghost') + '" data-flag="' + f[0] + '" aria-pressed="' + !!Number(x[f[0]]) + '">' + f[1] + '</button>'
                : (Number(x[f[0]]) ? A.badge(f[1], 'blue') : '');
            }).join(' ');
          } }
        ],
        actions: function (x) {
          return '<a class="a-btn a-btn--light a-btn--sm" href="admin#/products/' + x.id + '">' + (manage ? 'ویرایش' : 'مشاهده') + '</a>' +
            '<a class="a-btn a-btn--ghost a-btn--sm" href="' + esc(siteUrl('products/' + x.slug)) + '" target="_blank" rel="noopener">نمایش</a>' +
            (manage ? btn('حذف', 'data-delete', 'a-btn--danger-ghost') : '');
        },
        onRow: function (e, row, reload) {
          var flag = e.target.closest('[data-flag]');
          if (flag) {
            var body = {}; body[flag.dataset.flag] = !Number(row[flag.dataset.flag]);
            A.patch('products/' + row.id + '/flags', body).then(function () { A.toast('ذخیره شد.'); reload(); }).catch(A.fail);
          }
          if (e.target.closest('[data-stock]')) stockModal(row, reload);
          if (e.target.closest('[data-delete]')) {
            A.confirm('محصول «' + row.name + '» حذف شود؟ اگر در سفارشی استفاده شده باشد، به‌جای حذف بایگانی می‌شود.').then(function (ok) {
              if (ok) A.del('products/' + row.id).then(function (res) { A.toast(res && res.archived ? 'محصول در سفارش‌ها استفاده شده بود و بایگانی شد.' : 'محصول حذف شد.'); reload(); }).catch(A.fail);
            });
          }
        }
      });
    }).catch(failView('محصولات'));
  });

  function imageRow(img) {
    return '<div class="a-row" data-img-row><img src="' + esc(img.path || 'assets/images/ui/placeholder.svg') + '" alt="" width="52" height="52"><div class="a-row__body">' +
      '<input class="a-input" data-path dir="ltr" value="' + esc(img.path || '') + '" aria-label="مسیر تصویر" placeholder="uploads/... یا assets/...">' +
      '<input class="a-input" data-alt value="' + esc(img.alt || '') + '" aria-label="متن جایگزین" placeholder="متن جایگزین (Alt)"></div>' + TOOLS + '</div>';
  }
  function variantRow(v) {
    return '<div class="a-row" data-variant-row><span class="a-drag" aria-hidden="true">⋮</span><div class="a-row__body"><input type="hidden" data-vid value="' + esc(v.id || '') + '">' +
      '<input class="a-input" data-vname value="' + esc(v.name || '') + '" placeholder="نام تنوع (مثلاً ۴ لیتری)" aria-label="نام تنوع">' +
      '<input class="a-input" data-vsku dir="ltr" value="' + esc(v.sku || '') + '" placeholder="SKU" aria-label="SKU تنوع">' +
      '<input class="a-input" data-vprice type="number" value="' + esc(v.price || '') + '" placeholder="قیمت (خالی = قیمت محصول)" aria-label="قیمت تنوع">' +
      '<input class="a-input" data-vstock type="number" min="0" value="' + esc(v.stock === undefined ? 0 : v.stock) + '" placeholder="موجودی" aria-label="موجودی تنوع">' +
      '<label class="a-check"><input type="checkbox" data-vactive' + (v.is_active === undefined || Number(v.is_active) ? ' checked' : '') + '><span>فعال</span></label></div>' + TOOLS + '</div>';
  }

  /** One fitment row: vehicle model (grouped by car brand) + optional year range + note. */
  function fitmentRow(models, v) {
    var groups = {};
    models.forEach(function (m) { (groups[m.brand_name] = groups[m.brand_name] || []).push(m); });
    return '<div class="a-row" data-fit-row><span class="a-drag" aria-hidden="true">⋮</span><div class="a-row__body">' +
      '<select class="a-input" data-fmodel aria-label="خودرو"><option value="">— انتخاب خودرو —</option>' + Object.keys(groups).map(function (b) {
        return '<optgroup label="' + esc(b) + '">' + groups[b].map(function (m) {
          return '<option value="' + m.id + '"' + (String(m.id) === String(v.vehicle_model_id) ? ' selected' : '') + '>' + esc(b + ' ' + m.name) + ' (' + A.fa(m.year_from + '–' + m.year_to) + ')</option>';
        }).join('') + '</optgroup>';
      }).join('') + '</select>' +
      '<input class="a-input" data-ffrom type="number" value="' + esc(v.year_from || '') + '" placeholder="از سال (اختیاری)" aria-label="از سال">' +
      '<input class="a-input" data-fto type="number" value="' + esc(v.year_to || '') + '" placeholder="تا سال (اختیاری)" aria-label="تا سال">' +
      '<input class="a-input" data-fnote value="' + esc(v.note || '') + '" placeholder="توضیح (مثلاً فقط موتور TU5)" aria-label="توضیح سازگاری"></div>' + TOOLS + '</div>';
  }

  page('#/products/:id', 'products.view|products.manage', function (params) {
    var id = params.id === 'new' ? null : params.id;
    var title = id ? 'ویرایش محصول' : 'افزودن محصول';
    A.loading(title);
    Promise.all([
      id ? A.get('products/' + id) : Promise.resolve({ status: 'draft', images: [], attributes: [], variants: [], category_ids: [], vehicles: [], related_ids: [], complementary_ids: [], stock: 0, low_stock_threshold: 5 }),
      all('r/brands'), all('r/categories'), id && A.can('seo.manage') ? A.get('seo/product/' + id) : Promise.resolve(null), all('r/vehicle-models')
    ]).then(function (r) {
      var p = r[0], brands = r[1], cats = r[2], seo = r[3], models = r[4];
      p.sale_starts_at = p.sale_starts_at_local || ''; p.sale_ends_at = p.sale_ends_at_local || '';
      var main = [
        { name: 'name', label: 'نام محصول', required: true, full: true, maxlength: 190 },
        { name: 'slug', label: 'اسلاگ (آدرس)', help: 'خالی بماند تا از نام ساخته شود. با تغییر اسلاگ، ریدایرکت ۳۰۱ خودکار ساخته می‌شود.' },
        { name: 'status', label: 'وضعیت', type: 'select', options: opts(PRODUCT_STATUS) },
        { name: 'brand_id', label: 'برند', type: 'select', empty: '— بدون برند —', options: idOptions(brands) },
        { name: 'product_type', label: 'نوع محصول', placeholder: 'مثلاً روغن موتور' },
        { name: 'sku', label: 'SKU', dir: 'ltr' },
        { name: 'gtin', label: 'GTIN / بارکد', dir: 'ltr', help: 'فقط بارکد واقعی کالا را وارد کنید (۸ تا ۱۴ رقم).' },
        { name: 'category_ids', label: 'دسته‌بندی‌ها', type: 'multi', endpoint: 'api/admin/r/categories', full: true },
        { name: 'primary_category_id', label: 'دسته اصلی (برای مسیر راهنما)', type: 'select', empty: '— انتخاب —', options: idOptions(cats) },
        { name: 'short_description', label: 'توضیح کوتاه', type: 'textarea', rows: 3, full: true, maxlength: 500 },
        { name: 'description', label: 'توضیحات کامل', type: 'html', full: true }
      ].concat(FLAGS.map(function (f) { return { name: f[0], label: 'نشان «' + f[1] + '»', type: 'checkbox' }; }));
      var pricing = [
        { name: 'price', label: 'قیمت (تومان)', type: 'number', required: true, step: 1 },
        { name: 'sale_price', label: 'قیمت با تخفیف (تومان)', type: 'number', step: 1, help: 'خالی = بدون تخفیف. قیمت نهایی همیشه در سرور محاسبه می‌شود.' },
        { name: 'sale_starts_at', label: 'شروع تخفیف', type: 'datetime' },
        { name: 'sale_ends_at', label: 'پایان تخفیف', type: 'datetime' },
        { name: 'stock', label: 'موجودی (بدون تنوع)', type: 'number', step: 1, help: 'اگر تنوع تعریف شود، موجودی از جمع تنوع‌ها محاسبه می‌شود.' },
        { name: 'low_stock_threshold', label: 'حد هشدار کم‌موجودی', type: 'number', step: 1 }
      ];
      var extra = [
        { name: 'attributes', label: 'مشخصات فنی', type: 'keyvalue', full: true },
        { name: 'is_universal_fit', label: 'مناسب همه خودروها (عمومی)', type: 'checkbox', full: true, help: 'فقط برای کالاهایی که واقعاً به مدل خودرو وابسته نیستند (مثل جاروبرقی یا خوشبوکننده).' },
        { name: 'related_ids', label: 'محصولات مرتبط', type: 'multi', endpoint: 'api/admin/products', full: true },
        { name: 'complementary_ids', label: 'محصولات مکمل', type: 'multi', endpoint: 'api/admin/products', full: true }
      ];
      var tabList = [['main', 'اطلاعات اصلی'], ['pricing', 'قیمت و موجودی'], ['images', 'تصاویر'], ['extra', 'مشخصات، تنوع و سازگاری']];
      if (A.can('seo.manage')) tabList.push(['seo', 'سئو']);
      var moves = (p.movements || []).length ? '<h3 class="a-hint">آخرین تغییرات موجودی</h3><ul class="a-list-plain">' + p.movements.map(function (m) {
        return '<li><span>' + A.fa((m.change_qty > 0 ? '+' : '') + m.change_qty) + ' ← ' + A.num(m.quantity_after) + ' <small>' + esc(m.note || m.reason) + '</small></span><span class="a-muted">' + esc(m.at) + '</span></li>';
      }).join('') + '</ul>' : '';
      var html = '<form class="a-card" data-product novalidate>' + tabBar(tabList) +
        '<div class="a-grid" data-panel="main">' + fields(main, p) + '</div>' +
        '<div class="a-panel" data-panel="pricing" hidden><div class="a-grid">' + fields(pricing, p) + '</div>' + moves + '</div>' +
        '<div class="a-panel" data-panel="images" hidden><p class="a-hint">تصویر اول، تصویر اصلی محصول است. ترتیب را با دکمه‌ها تغییر دهید. برای هر تصویر متن جایگزین بنویسید.</p><p class="a-error" data-error-for="images"></p>' +
          '<div class="a-rows" data-images>' + p.images.map(imageRow).join('') + '</div>' + btn(A.icon('plus', 'icon--sm') + 'افزودن تصویر', 'data-add-image') + '</div>' +
        '<div class="a-panel" data-panel="extra" hidden><div class="a-grid">' + fields(extra, p) + '</div>' +
          '<h3 class="a-card__title" style="margin-top:20px">خودروهای سازگار</h3><p class="a-hint">برای هر خودرو، اگر کالا فقط برای بخشی از سال‌های تولید مناسب است، بازه سال را بنویسید (خالی = همه سال‌های آن مدل). ' +
          (models.length ? '' : '<a href="admin#/vehicles">ابتدا برند و مدل خودرو تعریف کنید.</a>') + '</p><p class="a-error" data-error-for="vehicles"></p>' +
          '<div class="a-rows" data-fitments>' + (p.vehicles || []).map(function (v) { return fitmentRow(models, v); }).join('') + '</div>' + btn(A.icon('plus', 'icon--sm') + 'افزودن خودرو', 'data-add-fitment') +
          '<h3 class="a-card__title" style="margin-top:20px">تنوع‌ها (رنگ، حجم، سایز…)</h3><p class="a-error" data-error-for="variants"></p>' +
          '<div class="a-rows" data-variants>' + p.variants.map(variantRow).join('') + '</div>' + btn(A.icon('plus', 'icon--sm') + 'افزودن تنوع', 'data-add-variant') + '</div>' +
        (A.can('seo.manage') ? '<div class="a-panel" data-panel="seo" hidden>' + (id ? seoForm(seo && seo.record) : '<div class="a-alert">تنظیمات سئو پس از اولین ذخیره محصول فعال می‌شود.</div>') + '</div>' : '') +
        '<div class="a-sticky-actions">' + (A.can('products.manage') ? '<button type="submit" class="a-btn a-btn--primary">ذخیره محصول</button>' : '') +
        '<a class="a-btn a-btn--light" href="admin#/products">بازگشت به فهرست</a>' + (id ? '<a class="a-btn a-btn--ghost" href="' + esc(siteUrl('products/' + p.slug)) + '" target="_blank" rel="noopener">نمایش در سایت</a>' : '') + '</div></form>';
      var v = A.view(id ? 'ویرایش: ' + p.name : title, html);
      var form = $('[data-product]', v);
      tabs(form);
      A.enhance(form);
      rowTools(form);
      $('[data-add-image]', form).addEventListener('click', function () {
        A.pickMedia('products').then(function (path) { if (path) $('[data-images]', form).insertAdjacentHTML('beforeend', imageRow({ path: path, alt: $('[name="name"]', form).value })); });
      });
      form.addEventListener('input', function (e) { if (e.target.matches('[data-path]')) e.target.closest('.a-row').querySelector('img').src = e.target.value || 'assets/images/ui/placeholder.svg'; });
      $('[data-add-variant]', form).addEventListener('click', function () { $('[data-variants]', form).insertAdjacentHTML('beforeend', variantRow({})); });
      $('[data-add-fitment]', form).addEventListener('click', function () { $('[data-fitments]', form).insertAdjacentHTML('beforeend', fitmentRow(models, {})); });
      if (id && seo) bindSerp(form, function () { return { title: $('[name="name"]', form).value, description: $('[name="short_description"]', form).value || seo.defaults.description, url: seo.defaults.url }; });

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!A.can('products.manage')) return;
        var data = A.read(form, main.concat(pricing, extra));
        data.images = $$('[data-img-row]', form).map(function (row) { return { path: $('[data-path]', row).value.trim(), alt: $('[data-alt]', row).value.trim() }; }).filter(function (x) { return x.path; });
        data.variants = $$('[data-variant-row]', form).map(function (row) {
          return { id: $('[data-vid]', row).value, name: $('[data-vname]', row).value.trim(), sku: $('[data-vsku]', row).value.trim(), price: A.en($('[data-vprice]', row).value),
            stock: Number(A.en($('[data-vstock]', row).value) || 0), is_active: $('[data-vactive]', row).checked };
        }).filter(function (x) { return x.name || x.sku; });
        data.vehicles = $$('[data-fit-row]', form).map(function (row) {
          return { vehicle_model_id: $('[data-fmodel]', row).value, year_from: A.en($('[data-ffrom]', row).value), year_to: A.en($('[data-fto]', row).value), note: $('[data-fnote]', row).value.trim() };
        });
        var submit = $('[type="submit"]', form);
        submit.disabled = true;
        (id ? A.put('products/' + id, data) : A.post('products', data)).then(function (res) {
          var pid = id || res.id;
          var seoData = id && $('[data-seo-form]', form) ? readSeo(form) : null;
          return (seoData && (seo.record || seoHasValue(seoData)) ? A.put('seo/product/' + pid, seoData) : Promise.resolve()).then(function () { return pid; });
        }).then(function (pid) {
          A.toast('محصول ذخیره شد.');
          if (!id) A.go('#/products/' + pid); else submit.disabled = false;
        }).catch(function (err) {
          submit.disabled = false;
          A.showErrors(form, err);
          var slot = Object.keys(err.errors || {}).map(function (k) { return form.querySelector('[data-error-for="' + k + '"]'); }).filter(Boolean)[0];
          var panel = slot && slot.closest('[data-panel]');
          if (panel) $('[data-tab="' + panel.dataset.panel + '"]', form).click();
        });
      });
    }).catch(failView(title));
  });

  /* ---------------- Categories, brands, vehicles ---------------- */
  page('#/categories', 'categories.manage', function () {
    all('r/categories', { parent_id: 'null' }).then(function (parents) {
      A.crud('categories', {
        title: 'دسته‌بندی‌ها', label: 'دسته‌بندی',
        filters: [{ name: 'is_active', label: 'وضعیت', options: ACTIVE }],
        columns: [{ label: 'نام', key: 'name', sort: 'name' }, { label: 'والد', render: function (x) { return esc(x.parent_name || '—'); } }, { label: 'اسلاگ', render: function (x) { return ltr(x.slug); } },
          { label: 'محصولات', render: function (x) { return A.num(x.products_count); } }, { label: 'ترتیب', key: 'sort_order', sort: 'sort_order' }, { label: 'وضعیت', render: function (x) { return A.yesno(x.is_active); } }],
        rowActions: seoAction('category'),
        fields: [{ name: 'name', label: 'نام', required: true }, { name: 'short_name', label: 'نام کوتاه (برای منو)' }, { name: 'slug', label: 'اسلاگ', help: 'تغییر اسلاگ، ریدایرکت ۳۰۱ می‌سازد.' },
          { name: 'parent_id', label: 'دسته والد', type: 'select', empty: '— دسته اصلی —', options: idOptions(parents) }, { name: 'description', label: 'توضیحات', type: 'textarea', full: true },
          { name: 'image_path', label: 'تصویر', type: 'image', folder: 'categories', full: true }, { name: 'sort_order', label: 'ترتیب نمایش', type: 'number', step: 1 }, { name: 'is_active', label: 'فعال', type: 'checkbox', default: 1 }],
        defaults: { is_active: 1, sort_order: 0 }
      });
    }).catch(failView('دسته‌بندی‌ها'));
  });

  function seoAction(type) {
    return function (x) { return A.can('seo.manage') ? '<a class="a-btn a-btn--ghost a-btn--sm" href="admin#/seo/' + type + '/' + x.id + '">سئو</a>' : ''; };
  }

  page('#/brands', 'brands.manage', function () {
    A.crud('brands', {
      title: 'برندها', label: 'برند',
      filters: [{ name: 'is_active', label: 'وضعیت', options: ACTIVE }],
      columns: [{ label: 'لوگو', render: function (x) { return '<img class="a-thumb" src="' + esc(x.logo_path || 'assets/images/ui/placeholder.svg') + '" alt="" width="44" height="44">'; } },
        { label: 'نام', key: 'name', sort: 'name' }, { label: 'اسلاگ', render: function (x) { return ltr(x.slug); } }, { label: 'محصولات', render: function (x) { return A.num(x.products_count); } },
        { label: 'وضعیت', render: function (x) { return A.yesno(x.is_active); } }],
      rowActions: seoAction('brand'),
      fields: [{ name: 'name', label: 'نام', required: true }, { name: 'slug', label: 'اسلاگ' }, { name: 'logo_path', label: 'لوگو', type: 'image', folder: 'brands', full: true },
        { name: 'description', label: 'توضیحات', type: 'textarea', full: true }, { name: 'sort_order', label: 'ترتیب', type: 'number', step: 1 }, { name: 'is_active', label: 'فعال', type: 'checkbox', default: 1 }],
      defaults: { is_active: 1 }
    });
  });

  var VEHICLE_SWITCH = '<a class="a-btn a-btn--ghost a-btn--sm" href="admin#/vehicles">برندهای خودرو</a><a class="a-btn a-btn--ghost a-btn--sm" href="admin#/vehicles/models">مدل‌های خودرو</a>';
  page('#/vehicles', 'vehicles.manage', function () {
    A.crud('vehicle-brands', { title: 'برندهای خودرو', label: 'برند خودرو', toolbar: VEHICLE_SWITCH,
      columns: [{ label: 'نام', key: 'name', sort: 'name' }, { label: 'اسلاگ', render: function (x) { return ltr(x.slug); } }, { label: 'ترتیب', key: 'sort_order' }],
      fields: [{ name: 'name', label: 'نام', required: true }, { name: 'slug', label: 'اسلاگ' }, { name: 'sort_order', label: 'ترتیب', type: 'number', step: 1 }] });
  });
  page('#/vehicles/models', 'vehicles.manage', function () {
    all('r/vehicle-brands').then(function (brands) {
      A.crud('vehicle-models', { title: 'مدل‌های خودرو', label: 'مدل خودرو', toolbar: VEHICLE_SWITCH,
        filters: [{ name: 'vehicle_brand_id', label: 'همه برندها', options: idOptions(brands) }],
        columns: [{ label: 'برند', key: 'brand_name' }, { label: 'مدل', key: 'name', sort: 'name' }, { label: 'سال‌ها', render: function (x) { return A.fa(x.year_from + ' تا ' + x.year_to); } }],
        fields: [{ name: 'vehicle_brand_id', label: 'برند', type: 'select', options: idOptions(brands), required: true }, { name: 'name', label: 'نام مدل', required: true }, { name: 'slug', label: 'اسلاگ' },
          { name: 'year_from', label: 'از سال (شمسی)', type: 'number', step: 1, required: true }, { name: 'year_to', label: 'تا سال (شمسی)', type: 'number', step: 1, required: true }, { name: 'sort_order', label: 'ترتیب', type: 'number', step: 1 }] });
    }).catch(failView('مدل‌های خودرو'));
  });

  /* ================================================================
     Orders & customers
     ================================================================ */
  page('#/orders', 'orders.view', function () {
    var list = A.list({
      title: 'سفارش‌ها', endpoint: 'orders', tabKey: 'status',
      tabs: [['', 'همه']].concat(opts(ORDER_STATUS)),
      filters: [{ name: 'payment_status', label: 'وضعیت پرداخت', options: [['unpaid', 'پرداخت‌نشده'], ['paid', 'پرداخت‌شده'], ['failed', 'ناموفق'], ['refunded', 'مسترد']] }],
      toolbar: '<label class="a-hint" for="o-from">از</label><input class="a-input" type="date" id="o-from" data-date="from"><label class="a-hint" for="o-to">تا</label><input class="a-input" type="date" id="o-to" data-date="to">',
      columns: [{ label: 'شماره', render: function (x) { return '<a href="admin#/orders/' + x.id + '">' + A.fa(x.order_number) + '</a>'; } },
        { label: 'مشتری', render: function (x) { return esc(x.customer_name) + '<br><small class="a-ltr">' + esc(x.customer_mobile) + '</small>'; } },
        { label: 'اقلام', render: function (x) { return A.num(x.items_count); } }, { label: 'مبلغ', sort: 'total', render: function (x) { return A.money(x.grand_total); } },
        { label: 'وضعیت', render: function (x) { return status(ORDER_STATUS, x.status); } },
        { label: 'پرداخت', render: function (x) { return A.badge({ unpaid: 'پرداخت‌نشده', paid: 'پرداخت‌شده', failed: 'ناموفق', refunded: 'مسترد' }[x.payment_status], x.payment_status === 'paid' ? 'green' : x.payment_status === 'failed' ? 'red' : 'muted'); } },
        { label: 'کد رهگیری', render: function (x) { return ltr(x.tracking_code); } }, { label: 'تاریخ', sort: 'placed_at', render: function (x) { return esc(x.placed_at_fa); } }],
      actions: function (x) { return '<a class="a-btn a-btn--light a-btn--sm" href="admin#/orders/' + x.id + '">جزئیات</a>'; }
    });
    var root = list.root;
    root.addEventListener('change', function (e) {
      var d = e.target.closest('[data-date]');
      if (!d) return;
      var q = A.query(); q[d.dataset.date] = d.value; q.page = 1;
      A.go('#/orders' + A.qs(q));
    });
    var q = A.query();
    ['from', 'to'].forEach(function (k) { if (q[k]) $('[data-date="' + k + '"]', root).value = q[k]; });
  });

  page('#/orders/:id', 'orders.view', function (params) {
    A.loading('جزئیات سفارش');
    A.get('orders/' + params.id).then(function (d) {
      var o = d.order, a = d.address, s = d.shipment;
      var items = '<div class="a-table-wrap"><table class="a-table"><thead><tr><th>محصول</th><th>SKU</th><th>قیمت واحد</th><th>تعداد</th><th>جمع</th></tr></thead><tbody>' + d.items.map(function (i) {
        return '<tr><td>' + (i.product_id ? '<a href="admin#/products/' + i.product_id + '">' + esc(i.product_name) + '</a>' : esc(i.product_name)) + (i.variant_name ? '<br><small>' + esc(i.variant_name) + '</small>' : '') + '</td><td>' + ltr(i.sku) + '</td><td>' +
          (Number(i.original_price) > Number(i.unit_price) ? '<del class="a-muted">' + A.num(i.original_price) + '</del> ' : '') + A.money(i.unit_price) + '</td><td>' + A.num(i.quantity) + '</td><td>' + A.money(i.line_total) + '</td></tr>';
      }).join('') + '</tbody></table></div><div class="a-totals"><div><span>جمع کالاها</span><span>' + A.money(o.subtotal) + '</span></div><div><span>تخفیف' + (o.coupon_code ? ' (' + esc(o.coupon_code) + ')' : '') + '</span><span>' + A.money(o.discount_total) + '</span></div>' +
        '<div><span>هزینه ارسال</span><span>' + (Number(o.shipping_total) ? A.money(o.shipping_total) : 'رایگان') + '</span></div><div><span>مبلغ نهایی</span><span>' + A.money(o.grand_total) + '</span></div></div>';
      var customer = '<dl class="a-dl"><dt>نام</dt><dd>' + (o.user_id && A.can('customers.view') ? '<a href="admin#/customers/' + o.user_id + '">' + esc(o.customer_name) + '</a>' : esc(o.customer_name)) + '</dd><dt>موبایل</dt><dd>' + ltr(o.customer_mobile) + '</dd><dt>ایمیل</dt><dd>' + ltr(o.customer_email) + '</dd>' +
        (d.customer_orders !== null ? '<dt>سفارش‌های مشتری</dt><dd>' + A.num(d.customer_orders) + '</dd>' : '<dt>نوع</dt><dd>مهمان</dd>') + (o.customer_note ? '<dt>یادداشت مشتری</dt><dd>' + esc(o.customer_note) + '</dd>' : '') + '</dl>';
      var address = a ? (a.hidden ? '<p>' + esc(a.province + '، ' + a.city) + '</p><p class="a-hint">نمایش نشانی کامل به مجوز «اطلاعات حساس مشتری» نیاز دارد.</p>'
        : '<dl class="a-dl"><dt>گیرنده</dt><dd>' + esc(a.receiver_name) + '</dd><dt>تلفن</dt><dd>' + ltr(a.receiver_mobile) + '</dd><dt>استان / شهر</dt><dd>' + esc(a.province + '، ' + a.city) + '</dd><dt>نشانی</dt><dd>' + esc(a.address_line) + '</dd><dt>کد پستی</dt><dd>' + ltr(a.postal_code) + '</dd></dl>') : '<div class="a-empty">نشانی ثبت نشده است.</div>';
      var history = d.history.length ? '<ul class="a-timeline">' + d.history.map(function (h) { return '<li><strong>' + esc(h.label) + '</strong> — <span class="a-muted">' + esc(h.at) + (h.admin_name ? ' — ' + esc(h.admin_name) : '') + '</span>' + (h.note ? '<br>' + esc(h.note) : '') + '</li>'; }).join('') + '</ul>' : '<div class="a-empty">تغییری ثبت نشده است.</div>';
      var payments = d.payments.length ? '<ul class="a-list-plain">' + d.payments.map(function (p) {
        return '<li><span>' + esc(p.gateway) + (Number(p.is_test) ? ' ' + A.badge('آزمایشی', 'orange') : '') + ' — ' + A.money(p.amount) + (p.reference ? ' <small class="a-ltr">' + esc(p.reference) + '</small>' : '') + '</span><span>' + A.badge(p.status, p.status === 'paid' ? 'green' : p.status === 'failed' ? 'red' : 'muted') + ' <small>' + esc(p.at) + '</small></span></li>';
      }).join('') + '</ul>' : '<div class="a-empty">پرداختی ثبت نشده است.</div>';
      var manage = A.can('orders.manage');
      var statusForm = manage && d.allowed_transitions.length ? '<form class="a-grid" data-status-form novalidate>' +
        A.field({ name: 'status', label: 'وضعیت جدید', type: 'select', options: d.allowed_transitions.map(function (t) { return [t.value, t.label]; }) }) +
        A.field({ name: 'note', label: 'توضیح (در تاریخچه ثبت می‌شود)' }) + A.field({ name: 'carrier', label: 'شرکت حمل' }, s && s.carrier) + A.field({ name: 'tracking_code', label: 'کد رهگیری مرسوله', dir: 'ltr' }, s && s.tracking_code) +
        '<div class="a-span"><button type="submit" class="a-btn a-btn--primary">تغییر وضعیت</button> <span class="a-hint">لغو یا استرداد، موجودی کالاها را برمی‌گرداند.</span></div></form>'
        : '<p class="a-hint">' + (manage ? 'این سفارش در وضعیت نهایی است و تغییر وضعیت ندارد.' : 'برای تغییر وضعیت به مجوز مدیریت سفارش نیاز دارید.') + '</p>';
      var noteForm = manage ? '<form data-note-form novalidate>' + A.field({ name: 'admin_note', label: 'یادداشت داخلی (فقط مدیران می‌بینند)', type: 'textarea', rows: 3 }, o.admin_note) +
        '<button type="submit" class="a-btn a-btn--light a-btn--sm" style="margin-top:8px">ذخیره یادداشت</button></form>' : (o.admin_note ? '<p>' + esc(o.admin_note) + '</p>' : '');
      var html = '<div class="a-toolbar"><a class="a-btn a-btn--light a-btn--sm" href="admin#/orders">بازگشت</a>' + status(ORDER_STATUS, o.status) + '<span class="a-muted">ثبت: ' + esc(o.placed_at_fa) + '</span></div>' +
        '<div class="a-cols a-cols--3"><div>' + card('اقلام سفارش', items) + card('تغییر وضعیت', statusForm) + card('تاریخچه وضعیت', history) + '</div>' +
        '<div>' + card('مشتری', customer) + card('نشانی ارسال', address) + card('پرداخت‌ها', payments) + (s ? card('ارسال', '<dl class="a-dl"><dt>وضعیت</dt><dd>' + esc(s.status) + '</dd><dt>حمل‌کننده</dt><dd>' + esc(s.carrier || '—') + '</dd><dt>کد رهگیری</dt><dd>' + ltr(s.tracking_code) + '</dd></dl>') : '') + card('یادداشت', noteForm) + '</div></div>';
      var v = A.view('سفارش ' + A.fa(o.order_number), html);
      var sf = $('[data-status-form]', v);
      if (sf) sf.addEventListener('submit', function (e) {
        e.preventDefault();
        var data = A.read(sf, [{ name: 'status' }, { name: 'note' }, { name: 'carrier' }, { name: 'tracking_code' }]);
        A.confirm('وضعیت سفارش به «' + ORDER_STATUS[data.status] + '» تغییر کند؟', { danger: data.status === 'cancelled' || data.status === 'refunded' }).then(function (ok) {
          if (ok) A.put('orders/' + o.id + '/status', data).then(function () { A.toast('وضعیت سفارش تغییر کرد.'); A.go('#/orders/' + o.id); }).catch(function (err) { A.showErrors(sf, err); });
        });
      });
      var nf = $('[data-note-form]', v);
      if (nf) nf.addEventListener('submit', function (e) {
        e.preventDefault();
        A.put('orders/' + o.id, { admin_note: nf.elements.admin_note.value }).then(function () { A.toast('یادداشت ذخیره شد.'); }).catch(A.fail);
      });
    }).catch(failView('جزئیات سفارش'));
  });

  page('#/customers', 'customers.view', function () {
    A.list({
      title: 'مشتریان', endpoint: 'customers',
      filters: [{ name: 'status', label: 'وضعیت حساب', options: [['active', 'فعال'], ['suspended', 'مسدود']] }],
      columns: [{ label: 'نام', sort: 'name', render: function (x) { return '<a href="admin#/customers/' + x.id + '">' + esc(x.full_name) + '</a>'; } },
        { label: 'موبایل', render: function (x) { return ltr(x.mobile); } }, { label: 'ایمیل', render: function (x) { return ltr(x.email); } },
        { label: 'سفارش‌ها', sort: 'orders', render: function (x) { return A.num(x.orders_count); } }, { label: 'مجموع خرید', sort: 'spent', render: function (x) { return A.money(x.total_spent); } },
        { label: 'وضعیت', render: function (x) { return status({ active: 'فعال', suspended: 'مسدود' }, x.status); } }, { label: 'عضویت', sort: 'created_at', render: function (x) { return esc(x.created_at_fa); } }],
      actions: function (x) { return '<a class="a-btn a-btn--light a-btn--sm" href="admin#/customers/' + x.id + '">جزئیات</a>'; }
    });
  });

  page('#/customers/:id', 'customers.view', function (params) {
    A.loading('مشتری');
    A.get('customers/' + params.id).then(function (d) {
      var c = d.customer;
      var info = '<dl class="a-dl"><dt>موبایل</dt><dd>' + ltr(c.mobile) + '</dd><dt>ایمیل</dt><dd>' + ltr(c.email) + '</dd><dt>عضویت</dt><dd>' + esc(c.created_at_fa) + '</dd><dt>آخرین ورود</dt><dd>' + esc(c.last_login_fa || '—') + '</dd>' +
        '<dt>مجموع خرید</dt><dd>' + A.money(d.total_spent) + '</dd><dt>نظرات</dt><dd>' + A.num(d.reviews_count) + '</dd><dt>وضعیت</dt><dd>' + status({ active: 'فعال', suspended: 'مسدود' }, c.status) + '</dd></dl>' +
        (A.can('customers.manage') ? '<p style="margin-top:14px">' + (c.status === 'active' ? btn('مسدود کردن حساب', 'data-status="suspended"', 'a-btn--danger-ghost') : btn('فعال کردن حساب', 'data-status="active"', 'a-btn--primary')) + '</p>' : '');
      var orders = d.orders.length ? '<div class="a-table-wrap"><table class="a-table"><tbody>' + d.orders.map(function (o) {
        return '<tr><td><a href="admin#/orders/' + o.id + '">' + A.fa(o.order_number) + '</a></td><td>' + A.money(o.grand_total) + '</td><td>' + status(ORDER_STATUS, o.status) + '</td><td class="a-muted">' + esc(o.placed_at_fa) + '</td></tr>';
      }).join('') + '</tbody></table></div>' : '<div class="a-empty">سفارشی ندارد.</div>';
      var addr = d.addresses.length ? '<ul class="a-list-plain">' + d.addresses.map(function (x) { return '<li><span><strong>' + esc(x.title) + '</strong> — ' + esc(x.receiver_name) + '<br><small>' + esc(x.province + '، ' + x.city + '، ' + x.address_line) + '</small></span></li>'; }).join('') + '</ul>'
        : '<p class="a-hint">' + (A.can('customers.sensitive') ? 'نشانی ثبت نشده است.' : 'نمایش نشانی‌ها به مجوز «اطلاعات حساس مشتری» نیاز دارد.') + '</p>';
      var v = A.view(c.full_name, '<div class="a-toolbar"><a class="a-btn a-btn--light a-btn--sm" href="admin#/customers">بازگشت</a></div><div class="a-cols a-cols--3"><div>' + card('سفارش‌ها', orders) + card('نشانی‌ها', addr) + '</div>' + card('اطلاعات حساب', info) + '</div>');
      v.addEventListener('click', function (e) {
        var b = e.target.closest('[data-status]');
        if (!b) return;
        A.confirm(b.dataset.status === 'suspended' ? 'حساب این مشتری مسدود شود؟ امکان ورود و خرید را از دست می‌دهد.' : 'حساب این مشتری فعال شود؟', { danger: b.dataset.status === 'suspended' }).then(function (ok) {
          if (ok) A.put('customers/' + c.id + '/status', { status: b.dataset.status }).then(function () { A.toast('وضعیت حساب ذخیره شد.'); A.go('#/customers/' + c.id); }).catch(A.fail);
        });
      });
    }).catch(failView('مشتری'));
  });

  page('#/coupons', 'coupons.manage', function () {
    A.crud('coupons', {
      title: 'کدهای تخفیف', label: 'کد تخفیف', wide: true, dates: ['starts_at', 'ends_at'],
      filters: [{ name: 'is_active', label: 'وضعیت', options: ACTIVE }, { name: 'type', label: 'نوع', options: [['percent', 'درصدی'], ['fixed', 'مبلغ ثابت']] }],
      columns: [{ label: 'کد', sort: 'code', render: function (x) { return ltr(x.code); } }, { label: 'تخفیف', render: function (x) { return x.type === 'percent' ? A.fa(Number(x.value)) + '٪' : A.money(x.value); } },
        { label: 'حداقل خرید', render: function (x) { return Number(x.min_order_amount) ? A.money(x.min_order_amount) : '—'; } },
        { label: 'استفاده', sort: 'used_count', render: function (x) { return A.num(x.used_count) + (x.usage_limit ? ' از ' + A.num(x.usage_limit) : ''); } },
        { label: 'پایان', sort: 'ends_at', render: function (x) { return x.ends_at ? esc(x.ends_at_local.replace('T', ' ')) : 'بدون پایان'; } }, { label: 'وضعیت', render: function (x) { return A.yesno(x.is_active); } }],
      fields: [{ name: 'code', label: 'کد (حروف انگلیسی، عدد، - و _)', required: true, dir: 'ltr' }, { name: 'description', label: 'توضیح داخلی' },
        { name: 'type', label: 'نوع', type: 'select', options: [['percent', 'درصدی'], ['fixed', 'مبلغ ثابت (تومان)']] }, { name: 'value', label: 'مقدار', type: 'number', required: true },
        { name: 'min_order_amount', label: 'حداقل مبلغ سفارش', type: 'number', step: 1 }, { name: 'max_discount_amount', label: 'سقف تخفیف (برای درصدی)', type: 'number', step: 1 },
        { name: 'starts_at', label: 'شروع', type: 'datetime' }, { name: 'ends_at', label: 'پایان', type: 'datetime' },
        { name: 'usage_limit', label: 'سقف کل استفاده', type: 'number', step: 1 }, { name: 'per_user_limit', label: 'سقف استفاده هر کاربر', type: 'number', step: 1 },
        { name: 'product_ids', label: 'فقط برای این محصولات (خالی = همه)', type: 'multi', endpoint: 'api/admin/products', full: true },
        { name: 'category_ids', label: 'فقط برای این دسته‌ها (خالی = همه)', type: 'multi', endpoint: 'api/admin/r/categories', full: true },
        { name: 'is_active', label: 'فعال', type: 'checkbox', default: 1 }],
      defaults: { type: 'percent', is_active: 1, product_ids: [], category_ids: [] }
    });
  });

  /* ================================================================
     Reviews, questions, messages, newsletter
     ================================================================ */
  function rejectModal(row, reload) {
    A.formModal({ title: 'رد نظر', saveText: 'رد نظر',
      fields: [{ name: 'rejection_reason', label: 'دلیل رد (به کاربر اطلاع داده می‌شود)', type: 'textarea', rows: 3, required: true, full: true }],
      data: { rejection_reason: row.rejection_reason },
      save: function (d) { return A.put('reviews/' + row.id, { status: 'rejected', rejection_reason: d.rejection_reason }); } })
      .then(function () { A.toast('نظر رد شد.'); reload(); });
  }
  function reviewModal(row, reload) {
    A.get('reviews/' + row.id).then(function (r) {
      var manage = A.can('reviews.manage');
      var flds = [{ name: 'title', label: 'عنوان', full: true }, { name: 'body', label: 'متن نظر', type: 'textarea', rows: 5, full: true },
        { name: 'admin_reply', label: 'پاسخ رسمی فروشگاه', type: 'textarea', rows: 3, full: true }, { name: 'admin_reply_visible', label: 'نمایش پاسخ در سایت', type: 'checkbox' }];
      var info = '<div class="a-span"><dl class="a-dl"><dt>محصول</dt><dd><a href="' + esc(siteUrl('products/' + r.product_slug)) + '" target="_blank" rel="noopener">' + esc(r.product_name) + '</a></dd><dt>کاربر</dt><dd>' + esc(r.user_name) + '</dd>' +
        '<dt>امتیاز</dt><dd>' + stars(r.rating) + '</dd><dt>خریدار تأییدشده</dt><dd>' + (Number(r.is_verified_buyer) ? A.badge('بله — سفارش تحویل‌شده دارد', 'green') : 'خیر') + '</dd><dt>تاریخ</dt><dd>' + esc(r.at) + '</dd><dt>وضعیت</dt><dd>' + status(REVIEW_STATUS, r.status) + '</dd></dl>' +
        (r.images.length ? '<div class="a-media-grid" style="margin-top:10px">' + r.images.map(function (p) { return '<a class="a-media" href="' + esc(p) + '" target="_blank" rel="noopener"><img src="' + esc(p) + '" alt="تصویر ارسالی کاربر"></a>'; }).join('') + '</div>' : '') + '</div>';
      var hist = r.history.length ? '<div class="a-span"><h3 class="a-hint">تاریخچه تغییرات</h3><ul class="a-list-plain">' + r.history.map(function (h) { return '<li><span>' + esc(h.description) + '</span><small>' + esc((h.admin_name || '') + ' — ' + h.at) + '</small></li>'; }).join('') + '</ul></div>' : '';
      if (!manage) { A.modal({ title: 'نظر کاربر', wide: true, body: '<div class="a-grid">' + info + '<div class="a-span"><strong>' + esc(r.title) + '</strong><div class="a-review-body">' + esc(r.body) + '</div></div>' + hist + '</div>' }); return; }
      A.formModal({ title: 'بررسی و ویرایش نظر', wide: true, fields: flds, data: r, extra: info.replace('a-span', 'a-span" style="order:-1') + hist,
        save: function (d) { return A.put('reviews/' + r.id, d); } }).then(function () { A.toast('نظر ذخیره شد.'); reload(); });
    }).catch(A.fail);
  }

  page('#/reviews', 'reviews.view|reviews.manage', function () {
    var manage = A.can('reviews.manage');
    A.list({
      title: 'مدیریت نظرات', endpoint: 'reviews', tabKey: 'status', defaultQuery: { status: 'pending' },
      tabs: [['pending', 'در انتظار بررسی'], ['approved', 'تأییدشده'], ['rejected', 'ردشده'], ['', 'همه']],
      filters: [{ name: 'rating', label: 'امتیاز', options: [['5', '۵ ستاره'], ['4', '۴ ستاره'], ['3', '۳ ستاره'], ['2', '۲ ستاره'], ['1', '۱ ستاره']] }],
      onData: function (d, root) {
        $$('[data-tab-value]', root).forEach(function (t) { var n = d.counts[t.dataset.tabValue]; if (n !== undefined && !t.querySelector('b')) t.insertAdjacentHTML('beforeend', '<b>' + A.num(n) + '</b>'); });
      },
      columns: [{ label: 'نظر', render: function (x) { return '<strong>' + esc(x.title) + '</strong> ' + stars(x.rating) + '<div class="a-review-body">' + cut(x.body, 220) + '</div>' + (x.rejection_reason ? '<small>دلیل رد: ' + esc(x.rejection_reason) + '</small>' : ''); } },
        { label: 'محصول', render: function (x) { return esc(x.product_name); } },
        { label: 'کاربر', render: function (x) { return esc(x.user_name) + (Number(x.is_verified_buyer) ? '<br>' + A.badge('خریدار', 'green') : '') + (Number(x.images_count) ? '<br><small>' + A.num(x.images_count) + ' تصویر</small>' : ''); } },
        { label: 'وضعیت', render: function (x) { return status(REVIEW_STATUS, x.status) + (x.admin_reply ? '<br><small>پاسخ داده شد</small>' : ''); } },
        { label: 'تاریخ', sort: 'created_at', render: function (x) { return esc(x.at); } }],
      actions: function (x) {
        return (manage && x.status !== 'approved' ? btn('تأیید', 'data-approve', 'a-btn--primary') : '') + (manage && x.status !== 'rejected' ? btn('رد', 'data-reject', 'a-btn--danger-ghost') : '') +
          btn(manage ? 'بررسی / پاسخ' : 'مشاهده', 'data-open') + (manage ? btn('حذف', 'data-delete', 'a-btn--danger-ghost') : '');
      },
      onRow: function (e, row, reload) {
        if (e.target.closest('[data-approve]')) A.put('reviews/' + row.id, { status: 'approved' }).then(function () { A.toast('نظر تأیید و منتشر شد.'); reload(); }).catch(A.fail);
        if (e.target.closest('[data-reject]')) rejectModal(row, reload);
        if (e.target.closest('[data-open]')) reviewModal(row, reload);
        if (e.target.closest('[data-delete]')) A.confirm('این نظر برای همیشه حذف شود؟ امتیاز محصول دوباره محاسبه می‌شود.').then(function (ok) {
          if (ok) A.del('reviews/' + row.id).then(function () { A.toast('نظر حذف شد.'); reload(); }).catch(A.fail);
        });
      }
    });
  });

  page('#/questions', 'questions.manage', function () {
    A.crud('questions', {
      title: 'پرسش‌های محصول', label: 'پرسش', readonly: true, tabKey: 'status', defaultQuery: { status: 'pending' },
      tabs: [['pending', 'در انتظار'], ['approved', 'تأییدشده'], ['rejected', 'ردشده'], ['', 'همه']],
      columns: [{ label: 'پرسش', render: function (x) { return cut(x.question, 160) + (x.answer ? '<br><small>پاسخ: ' + cut(x.answer, 100) + '</small>' : ''); } },
        { label: 'محصول', key: 'product_name' }, { label: 'کاربر', render: function (x) { return esc(x.user_name || '—'); } }, { label: 'وضعیت', render: function (x) { return status(REVIEW_STATUS, x.status); } }],
      fields: function (d) {
        return [{ name: 'question_view', label: 'پرسش', type: 'readonly', full: true, default: d.question }, { name: 'answer', label: 'پاسخ', type: 'textarea', rows: 4, full: true },
          { name: 'status', label: 'وضعیت انتشار', type: 'select', options: [['pending', 'در انتظار'], ['approved', 'تأیید و نمایش'], ['rejected', 'رد']] }];
      }
    });
  });

  page('#/messages', 'messages.manage', function () {
    A.crud('messages', {
      title: 'پیام‌های تماس', label: 'پیام', readonly: true, tabKey: 'status', defaultQuery: { status: 'new' },
      tabs: [['new', 'جدید'], ['read', 'خوانده‌شده'], ['archived', 'بایگانی'], ['', 'همه']],
      columns: [{ label: 'فرستنده', render: function (x) { return esc(x.name) + '<br><small class="a-ltr">' + esc(x.contact) + '</small>'; } }, { label: 'موضوع', render: function (x) { return esc(x.subject || '—'); } },
        { label: 'پیام', render: function (x) { return cut(x.message, 120); } }, { label: 'وضعیت', render: function (x) { return status({ new: 'جدید', read: 'خوانده‌شده', archived: 'بایگانی' }, x.status); } }],
      fields: function (d) {
        return [{ name: 'from_view', label: 'فرستنده', type: 'readonly', default: d.name + ' — ' + d.contact }, { name: 'subject_view', label: 'موضوع', type: 'readonly', default: d.subject || '—' },
          { name: 'message_view', label: 'متن پیام', type: 'readonly', full: true, default: d.message },
          { name: 'status', label: 'وضعیت', type: 'select', options: [['new', 'جدید'], ['read', 'خوانده‌شده'], ['archived', 'بایگانی']] }];
      }
    });
  });

  page('#/newsletter', 'newsletter.view', function () {
    var list = A.crud('newsletter', {
      title: 'خبرنامه', label: 'مشترک', readonly: true,
      filters: [{ name: 'status', label: 'وضعیت', options: [['subscribed', 'عضو'], ['unsubscribed', 'لغو عضویت']] }],
      toolbar: btn('خروجی CSV', 'data-csv'),
      columns: [{ label: 'ایمیل', render: function (x) { return ltr(x.email); } }, { label: 'وضعیت', render: function (x) { return status({ subscribed: 'عضو', unsubscribed: 'لغو عضویت' }, x.status); } }, { label: 'تاریخ عضویت', render: function (x) { return esc(x.created_at); } }],
      fields: [{ name: 'status', label: 'وضعیت', type: 'select', options: [['subscribed', 'عضو'], ['unsubscribed', 'لغو عضویت']] }]
    });
    list.root.addEventListener('click', function (e) {
      if (!e.target.closest('[data-csv]')) return;
      var rows = [];
      (function next(pageNo) {
        A.get('r/newsletter', { status: 'subscribed', per_page: 100, page: pageNo }).then(function (d) {
          rows = rows.concat(d.items);
          if (pageNo < d.pagination.pages) return next(pageNo + 1);
          var csv = '﻿email,status,created_at\n' + rows.map(function (r) { return [r.email, r.status, r.created_at].map(function (v) { return '"' + String(v || '').replace(/"/g, '""').replace(/^[=+\-@]/, "'$&") + '"'; }).join(','); }).join('\n');
          var a = document.createElement('a');
          a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
          a.download = 'newsletter-subscribers.csv';
          document.body.appendChild(a); a.click(); a.remove();
          A.toast(A.num(rows.length) + ' ایمیل خروجی گرفته شد.');
        }).catch(A.fail);
      })(1);
    });
  });

  /* ================================================================
     Site content: homepage, banners, menus, pages, FAQs, media
     ================================================================ */
  var PRODUCT_SOURCES = [['latest', 'جدیدترین'], ['amazing', 'شگفت‌انگیزها'], ['bestseller', 'پرفروش‌ها'], ['new', 'نشان «جدید»'], ['featured', 'ویژه'], ['discount', 'تخفیف‌دار'], ['category', 'از یک دسته'], ['manual', 'انتخاب دستی']];
  var PRODUCT_TYPES = ['amazing', 'ranked_products', 'product_grid', 'product_slider', 'compact_grid', 'mosaic'];
  var BANNER_TYPES = ['hero_slider', 'promo_banners', 'big_banner', 'two_banners', 'discounted'];

  function sectionFields(type, s, items) {
    var f = [];
    var placementOpts = opts(PLACEMENTS);
    if (PRODUCT_TYPES.indexOf(type) > -1) {
      f.push({ name: 'source', label: 'منبع محصولات', type: 'select', options: PRODUCT_SOURCES }, { name: 'category', label: 'اسلاگ دسته (برای منبع «از یک دسته»)', dir: 'ltr' },
        { name: 'limit', label: 'تعداد', type: 'number', step: 1 }, { name: 'products', label: 'محصولات (برای منبع «انتخاب دستی»)', type: 'multi', endpoint: 'api/admin/products', full: true });
    }
    if (type === 'product_grid') f.push({ name: 'layout', label: 'چیدمان', type: 'select', options: [['default', 'عادی'], ['split', 'با تصویر کناری'], ['dark', 'تیره']] }, { name: 'columns', label: 'تعداد ستون', type: 'number', step: 1 },
      { name: 'action', label: 'دکمه کارت', type: 'select', options: [['cart', 'افزودن به سبد'], ['details', 'مشاهده جزئیات']] }, { name: 'image', label: 'تصویر کناری', type: 'image', folder: 'banners', full: true });
    if (BANNER_TYPES.indexOf(type) > -1) f.push({ name: 'banner_placement', label: 'جایگاه بنرها', type: 'select', options: placementOpts, help: 'بنرها از بخش «بنرها» با همین جایگاه خوانده می‌شوند.' });
    if (type === 'hero_slider') f.push({ name: 'interval', label: 'فاصله تعویض اسلاید (میلی‌ثانیه)', type: 'number', step: 500 });
    if (type === 'discounted' || type === 'tabbed_products') f.push({ name: 'limit', label: 'تعداد محصولات', type: 'number', step: 1 });
    if (type === 'blog_posts') f.push({ name: 'source', label: 'منبع مقالات', type: 'select', options: [['latest', 'جدیدترین'], ['category', 'از یک دسته']] }, { name: 'category', label: 'اسلاگ دسته وبلاگ', dir: 'ltr' }, { name: 'limit', label: 'تعداد', type: 'number', step: 1 });
    if (type === 'quick_categories' || type === 'visual_categories') f.push({ name: 'categories', label: 'دسته‌ها (به همین ترتیب)', type: 'multi', endpoint: 'api/admin/r/categories', full: true });
    if (type === 'text') f.push({ name: 'body', label: 'محتوا', type: 'html', full: true });
    if (['vehicle_selector', 'testimonials', 'newsletter', 'benefits', 'text', 'tabbed_products', 'hero_slider', 'promo_banners', 'big_banner', 'two_banners'].indexOf(type) === -1) f.push({ name: 'link', label: 'لینک «مشاهده همه»', dir: 'ltr' });
    var data = Object.assign({}, s, {
      products: items.filter(function (i) { return i.item_type === 'product'; }).map(function (i) { return i.item_id; }),
      categories: items.filter(function (i) { return i.item_type === 'category'; }).map(function (i) { return i.item_id; })
    });
    return { fields: f, data: data };
  }
  function tabRow(t) {
    return '<div class="a-row" data-tabrow><span class="a-drag" aria-hidden="true">⋮</span><div class="a-row__body"><input class="a-input" data-l value="' + esc(t.label || '') + '" placeholder="عنوان زبانه" aria-label="عنوان زبانه">' +
      '<input class="a-input" data-c dir="ltr" value="' + esc(t.category || '') + '" placeholder="اسلاگ دسته" aria-label="اسلاگ دسته"></div>' + TOOLS + '</div>';
  }
  function benefitRow(b) {
    return '<div class="a-row" data-benefit><span class="a-drag" aria-hidden="true">⋮</span><div class="a-row__body"><input class="a-input" data-icon dir="ltr" value="' + esc(b.icon || 'check') + '" placeholder="آیکون (truck، shield…)" aria-label="نام آیکون">' +
      '<input class="a-input" data-t value="' + esc(b.title || '') + '" placeholder="عنوان" aria-label="عنوان"><input class="a-input" data-x value="' + esc(b.text || '') + '" placeholder="توضیح" aria-label="توضیح">' +
      '<input class="a-input" data-u dir="ltr" value="' + esc(b.url || '') + '" placeholder="لینک (اختیاری)" aria-label="لینک"></div>' + TOOLS + '</div>';
  }
  function testimonialRow(t) {
    var p = t.payload || {};
    return '<div class="a-row" data-testimonial data-sample="' + (p.sample ? 1 : 0) + '"><span class="a-drag" aria-hidden="true">⋮</span><div class="a-row__body">' + (p.sample ? '<span class="a-span">' + A.badge('محتوای نمونه — با نظر واقعی مشتری جایگزین کنید', 'orange') + '</span>' : '') +
      '<input class="a-input" data-n value="' + esc(p.name || '') + '" placeholder="نام" aria-label="نام"><input class="a-input" data-city value="' + esc(p.city || '') + '" placeholder="شهر" aria-label="شهر">' +
      '<input class="a-input" data-r type="number" min="1" max="5" value="' + esc(p.rating || 5) + '" aria-label="امتیاز"><input class="a-input" data-pid type="number" value="' + esc(p.product_id || '') + '" placeholder="شناسه محصول" aria-label="شناسه محصول">' +
      '<textarea class="a-input a-span" data-txt rows="2" placeholder="متن نظر" aria-label="متن نظر">' + esc(p.text || '') + '</textarea><input type="hidden" data-av value="' + esc(p.avatar || '') + '"></div>' + TOOLS + '</div>';
  }

  function sectionModal(section, types, done) {
    var type = section.type;
    var conf = sectionFields(type, section.settings || {}, section.items || []);
    var base = [{ name: 'title', label: 'عنوان', full: true }, { name: 'subtitle', label: 'زیرعنوان', full: true }, { name: 'bg_color', label: 'رنگ پس‌زمینه', type: 'color' }, { name: 'is_active', label: 'فعال', type: 'checkbox', default: 1 }];
    var s = section.settings || {};
    var extra = '';
    if (type === 'tabbed_products') extra = '<div class="a-span"><h3 class="a-hint">زبانه‌ها (حداکثر ۶)</h3><div class="a-rows" data-tabs>' + (s.tabs || []).map(tabRow).join('') + '</div>' + btn('افزودن زبانه', 'data-add-tab') + '</div>';
    if (type === 'benefits') extra = '<div class="a-span"><h3 class="a-hint">مزایا (حداکثر ۸)</h3><div class="a-rows" data-benefits>' + (s.items || []).map(benefitRow).join('') + '</div>' + btn('افزودن مورد', 'data-add-benefit') + '</div>';
    if (type === 'testimonials') extra = '<div class="a-span"><p class="a-alert a-alert--warn">فقط نظرهای واقعی مشتریان را با اجازه خودشان منتشر کنید.</p><div class="a-rows" data-testimonials>' + (section.items || []).filter(function (i) { return i.item_type === 'testimonial'; }).map(testimonialRow).join('') + '</div>' + btn('افزودن نظر', 'data-add-testimonial') + '</div>';
    var allFields = base.concat(conf.fields);
    A.formModal({ title: (section.id ? 'ویرایش سکشن: ' : 'سکشن جدید: ') + (types[type] || type), wide: true, fields: allFields, data: Object.assign({}, conf.data, { title: section.title, subtitle: section.subtitle, bg_color: section.bg_color, is_active: section.id ? section.is_active : 1 }), extra: extra,
      onOpen: function (form) {
        rowTools(form);
        form.addEventListener('click', function (e) {
          if (e.target.closest('[data-add-tab]')) $('[data-tabs]', form).insertAdjacentHTML('beforeend', tabRow({}));
          if (e.target.closest('[data-add-benefit]')) $('[data-benefits]', form).insertAdjacentHTML('beforeend', benefitRow({}));
          if (e.target.closest('[data-add-testimonial]')) $('[data-testimonials]', form).insertAdjacentHTML('beforeend', testimonialRow({}));
        });
      },
      save: function (d) {
        var form = $('.a-modal form');
        var settings = {};
        conf.fields.forEach(function (f) { if (['products', 'categories'].indexOf(f.name) === -1 && d[f.name] !== '' && d[f.name] !== undefined) settings[f.name] = d[f.name]; });
        var items = [];
        (d.products || []).forEach(function (id) { items.push({ item_type: 'product', item_id: id }); });
        (d.categories || []).forEach(function (id) { items.push({ item_type: 'category', item_id: id }); });
        if (type === 'tabbed_products') settings.tabs = $$('[data-tabrow]', form).map(function (r) { return { label: $('[data-l]', r).value.trim(), category: $('[data-c]', r).value.trim() }; });
        if (type === 'benefits') settings.items = $$('[data-benefit]', form).map(function (r) { return { icon: $('[data-icon]', r).value.trim(), title: $('[data-t]', r).value.trim(), text: $('[data-x]', r).value.trim(), url: $('[data-u]', r).value.trim() }; });
        if (type === 'testimonials') $$('[data-testimonial]', form).forEach(function (r) {
          var payload = { name: $('[data-n]', r).value.trim(), city: $('[data-city]', r).value.trim(), rating: Number(A.en($('[data-r]', r).value) || 5), product_id: A.en($('[data-pid]', r).value), text: $('[data-txt]', r).value.trim(), avatar: $('[data-av]', r).value };
          if (r.dataset.sample === '1') payload.sample = true;
          if (payload.name && payload.text) items.push({ item_type: 'testimonial', payload: payload });
        });
        var body = { type: type, title: d.title, subtitle: d.subtitle, bg_color: d.bg_color, is_active: d.is_active, settings: settings, items: items };
        return section.id ? A.put('homepage/' + section.id, body) : A.post('homepage', body);
      } }).then(function () { A.toast('سکشن ذخیره شد.'); done(); });
  }

  page('#/homepage', 'content.homepage', function () {
    A.loading('صفحه اصلی');
    A.get('homepage').then(function (d) {
      var types = d.types;
      var html = '<div class="a-card"><div class="a-toolbar"><span class="a-hint">ترتیب سکشن‌ها را با کشیدن یا دکمه‌های بالا/پایین تغییر دهید و «ذخیره ترتیب» را بزنید.</span><span class="a-toolbar__spacer"></span>' +
        '<select class="a-input" data-new-type aria-label="نوع سکشن جدید">' + Object.keys(types).map(function (k) { return '<option value="' + k + '">' + esc(types[k]) + '</option>'; }).join('') + '</select>' +
        btn(A.icon('plus', 'icon--sm') + 'افزودن سکشن', 'data-add', 'a-btn--primary') + btn('ذخیره ترتیب', 'data-save-order') +
        '<a class="a-btn a-btn--ghost a-btn--sm" href="' + esc(siteUrl('')) + '" target="_blank" rel="noopener">مشاهده صفحه اصلی</a></div>' +
        '<div class="a-rows a-section-list" data-sections>' + d.sections.map(function (s) {
          return '<div class="a-row' + (Number(s.is_active) ? '' : ' is-off') + '" draggable="true" data-id="' + s.id + '"><span class="a-drag" aria-hidden="true">⋮⋮</span><div><strong>' + esc(s.title || s.type_label) + '</strong> <small>' + esc(s.type_label) + '</small> ' + A.yesno(s.is_active) +
            ((s.items || []).some(function (i) { return i.payload && i.payload.sample; }) ? ' ' + A.badge('شامل محتوای نمونه', 'orange') : '') + '</div>' +
            '<div class="a-row__tools"><button type="button" class="a-icon-btn" data-up aria-label="انتقال به بالا">▲</button><button type="button" class="a-icon-btn" data-down aria-label="انتقال به پایین">▼</button>' +
            btn(Number(s.is_active) ? 'غیرفعال' : 'فعال', 'data-toggle') + btn('ویرایش', 'data-edit') + btn('حذف', 'data-del', 'a-btn--danger-ghost') + '</div></div>';
        }).join('') + '</div></div>';
      var v = A.view('صفحه اصلی', html);
      var list = $('[data-sections]', v);
      var byId = {}; d.sections.forEach(function (s) { byId[s.id] = s; });
      var dragging = null;
      list.addEventListener('dragstart', function (e) { dragging = e.target.closest('.a-row'); dragging.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move'; });
      list.addEventListener('dragend', function () { if (dragging) dragging.classList.remove('is-dragging'); dragging = null; });
      list.addEventListener('dragover', function (e) {
        e.preventDefault();
        var over = e.target.closest('.a-row');
        if (!dragging || !over || over === dragging) return;
        var r = over.getBoundingClientRect();
        list.insertBefore(dragging, e.clientY > r.top + r.height / 2 ? over.nextSibling : over);
      });
      v.addEventListener('click', function (e) {
        var row = e.target.closest('.a-row');
        var reload = function () { A.go('#/homepage'); };
        if (e.target.closest('[data-up]') && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
        if (e.target.closest('[data-down]') && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
        if (e.target.closest('[data-save-order]')) A.post('homepage/reorder', { ids: $$('.a-row', list).map(function (r) { return Number(r.dataset.id); }) }).then(function () { A.toast('ترتیب سکشن‌ها ذخیره شد.'); }).catch(A.fail);
        if (e.target.closest('[data-add]')) sectionModal({ type: $('[data-new-type]', v).value, settings: {}, items: [] }, types, reload);
        if (!row) return;
        var s = byId[row.dataset.id];
        if (e.target.closest('[data-edit]')) sectionModal(s, types, reload);
        if (e.target.closest('[data-toggle]')) A.put('homepage/' + s.id, { type: s.type, title: s.title, subtitle: s.subtitle, bg_color: s.bg_color, is_active: !Number(s.is_active), settings: s.settings, items: s.items }).then(reload).catch(A.fail);
        if (e.target.closest('[data-del]')) A.confirm('سکشن «' + (s.title || s.type_label) + '» حذف شود؟').then(function (ok) { if (ok) A.del('homepage/' + s.id).then(function () { A.toast('سکشن حذف شد.'); reload(); }).catch(A.fail); });
      });
    }).catch(failView('صفحه اصلی'));
  });

  page('#/banners', 'content.banners', function () {
    A.crud('banners', {
      title: 'بنرها', label: 'بنر', wide: true, dates: ['starts_at', 'ends_at'],
      filters: [{ name: 'placement', label: 'همه جایگاه‌ها', options: opts(PLACEMENTS) }, { name: 'is_active', label: 'وضعیت', options: ACTIVE }],
      columns: [{ label: 'تصویر', render: function (x) { return '<img class="a-thumb" style="width:96px" src="' + esc(x.image_desktop) + '" alt="" width="96" height="44">'; } }, { label: 'عنوان', key: 'title', sort: 'title' },
        { label: 'جایگاه', render: function (x) { return esc(PLACEMENTS[x.placement] || x.placement); } }, { label: 'ترتیب', key: 'sort_order', sort: 'sort_order' },
        { label: 'زمان‌بندی', render: function (x) { return x.starts_at || x.ends_at ? '<small>' + esc((x.starts_at_local || '…').replace('T', ' ') + ' تا ' + (x.ends_at_local || '…').replace('T', ' ')) + '</small>' : 'همیشه'; } },
        { label: 'وضعیت', render: function (x) { return A.yesno(x.is_active); } }],
      fields: [{ name: 'placement', label: 'جایگاه', type: 'select', options: opts(PLACEMENTS) }, { name: 'title', label: 'عنوان', required: true }, { name: 'eyebrow', label: 'متن کوچک بالای عنوان' },
        { name: 'button_text', label: 'متن دکمه' }, { name: 'description', label: 'توضیح', full: true }, { name: 'button_url', label: 'لینک', dir: 'ltr', full: true },
        { name: 'image_desktop', label: 'تصویر دسکتاپ', type: 'image', folder: 'banners', required: true, full: true }, { name: 'image_mobile', label: 'تصویر موبایل (اختیاری)', type: 'image', folder: 'banners', full: true },
        { name: 'image_alt', label: 'متن جایگزین تصویر', full: true }, { name: 'bg_color', label: 'رنگ پس‌زمینه', type: 'color' }, { name: 'text_theme', label: 'رنگ متن', type: 'select', options: [['dark', 'تیره'], ['light', 'روشن']] },
        { name: 'starts_at', label: 'شروع نمایش', type: 'datetime' }, { name: 'ends_at', label: 'پایان نمایش', type: 'datetime' }, { name: 'sort_order', label: 'ترتیب', type: 'number', step: 1 }, { name: 'is_active', label: 'فعال', type: 'checkbox', default: 1 }],
      defaults: { placement: 'hero', text_theme: 'dark', is_active: 1, sort_order: 0 },
      extra: function () { return '<div class="a-span"><h3 class="a-hint">پیش‌نمایش</h3><div class="a-banner-preview" data-preview></div></div>'; },
      onOpen: function (form) {
        function draw() {
          var img = $('[name="image_desktop"]', form).value;
          var bg = $('[data-color-none="bg_color"]', form).checked ? '#f5f6f8' : $('[name="bg_color"]', form).value;
          var light = $('[name="text_theme"]', form).value === 'light';
          $('[data-preview]', form).innerHTML = '<div style="display:flex;align-items:center;gap:16px;padding:16px;background:' + esc(bg) + ';color:' + (light ? '#fff' : '#171717') + '"><div style="flex:1"><small style="color:inherit;opacity:.8">' + esc($('[name="eyebrow"]', form).value) + '</small>' +
            '<h3 style="margin:4px 0">' + esc($('[name="title"]', form).value) + '</h3><p style="margin:0 0 8px">' + esc($('[name="description"]', form).value) + '</p>' + ($('[name="button_text"]', form).value ? '<span class="a-btn a-btn--primary a-btn--sm">' + esc($('[name="button_text"]', form).value) + '</span>' : '') +
            '</div>' + (img ? '<img src="' + esc(img) + '" alt="" style="width:45%;max-height:160px;object-fit:contain">' : '') + '</div>';
        }
        form.addEventListener('input', A.debounce(draw, 150));
        form.addEventListener('change', draw);
        draw();
      }
    });
  });

  page('#/menus', 'content.menus', function () {
    A.loading('منوها');
    A.get('menus').then(function (menus) {
      var html = menus.map(function (m) {
        var tops = m.items.filter(function (i) { return !i.parent_id; });
        var rowsHtml = tops.map(function (t) {
          return menuItemRow(t, false) + m.items.filter(function (c) { return Number(c.parent_id) === Number(t.id); }).map(function (c) { return menuItemRow(c, true); }).join('');
        }).join('');
        return '<section class="a-card" data-menu="' + m.id + '"><h2 class="a-card__title"><span>' + esc(m.name) + ' <small class="a-ltr">' + esc(m.location) + '</small> ' + A.yesno(m.is_active) + '</span><span>' +
          btn(Number(m.is_active) ? 'غیرفعال کردن' : 'فعال کردن', 'data-toggle-menu') + ' ' + btn(A.icon('plus', 'icon--sm') + 'افزودن آیتم', 'data-add-item', 'a-btn--primary') + '</span></h2>' +
          (rowsHtml ? '<div class="a-table-wrap"><table class="a-table"><tbody>' + rowsHtml + '</tbody></table></div>' : '<div class="a-empty">آیتمی ندارد.</div>') + '</section>';
      }).join('');
      var v = A.view('منوها', html || '<div class="a-empty">منویی تعریف نشده است.</div>');
      var reload = function () { A.go('#/menus'); };
      v.addEventListener('click', function (e) {
        var sec = e.target.closest('[data-menu]');
        if (!sec) return;
        var menu = menus.filter(function (m) { return String(m.id) === sec.dataset.menu; })[0];
        var tr = e.target.closest('[data-item]');
        var item = tr && menu.items.filter(function (i) { return String(i.id) === tr.dataset.item; })[0];
        if (e.target.closest('[data-toggle-menu]')) A.put('menus/' + menu.id, { is_active: !Number(menu.is_active) }).then(reload).catch(A.fail);
        if (e.target.closest('[data-add-item]')) menuItemModal(menu, { menu_id: menu.id, is_active: 1, sort_order: menu.items.length }, reload);
        if (item && e.target.closest('[data-edit]')) menuItemModal(menu, item, reload);
        if (item && e.target.closest('[data-del]')) A.confirm('آیتم «' + item.title + '» و زیرمنوهای آن حذف شوند؟').then(function (ok) { if (ok) A.del('menus/items/' + item.id).then(function () { A.toast('حذف شد.'); reload(); }).catch(A.fail); });
      });
    }).catch(failView('منوها'));
  });
  function menuItemRow(i, child) {
    return '<tr data-item="' + i.id + '"><td>' + (child ? '<span class="a-muted">— </span>' : '') + esc(i.title) + (Number(i.is_highlight) ? ' ' + A.badge('برجسته', 'red') : '') + '</td><td>' + ltr(i.url) + '</td><td>' + A.fa(i.sort_order) + '</td><td>' + A.yesno(i.is_active) + '</td>' +
      '<td class="a-actions">' + btn('ویرایش', 'data-edit') + btn('حذف', 'data-del', 'a-btn--danger-ghost') + '</td></tr>';
  }
  function menuItemModal(menu, item, done) {
    var parents = menu.items.filter(function (i) { return !i.parent_id && i.id !== item.id; });
    A.formModal({ title: item.id ? 'ویرایش آیتم منو' : 'آیتم جدید در ' + menu.name, data: item,
      fields: [{ name: 'title', label: 'عنوان', required: true }, { name: 'url', label: 'لینک (مثلاً categories/oil یا https://...)', dir: 'ltr', required: true },
        { name: 'parent_id', label: 'والد', type: 'select', empty: '— سطح اول —', options: idOptions(parents, 'title') }, { name: 'sort_order', label: 'ترتیب', type: 'number', step: 1 },
        { name: 'image_path', label: 'تصویر (برای مگامنو)', type: 'image', folder: 'general', full: true }, { name: 'is_highlight', label: 'نمایش برجسته', type: 'checkbox' }, { name: 'is_active', label: 'فعال', type: 'checkbox' }],
      save: function (d) { d.menu_id = menu.id; return item.id ? A.put('menus/items/' + item.id, d) : A.post('menus/items', d); } })
      .then(function () { A.toast('آیتم منو ذخیره شد.'); done(); });
  }

  page('#/pages', 'content.pages', function () {
    A.crud('pages', {
      title: 'صفحات', label: 'صفحه', wide: true,
      filters: [{ name: 'status', label: 'وضعیت', options: [['published', 'منتشرشده'], ['draft', 'پیش‌نویس']] }],
      columns: [{ label: 'عنوان', key: 'title', sort: 'title' }, { label: 'آدرس', render: function (x) { return ltr('pages/' + x.slug); } }, { label: 'قالب', key: 'template' },
        { label: 'وضعیت', render: function (x) { return status({ published: 'منتشرشده', draft: 'پیش‌نویس' }, x.status); } }, { label: 'آخرین تغییر', sort: 'updated_at', render: function (x) { return esc(x.updated_at); } }],
      rowActions: function (x) { return '<a class="a-btn a-btn--ghost a-btn--sm" href="' + esc(siteUrl('pages/' + x.slug)) + '" target="_blank" rel="noopener">نمایش</a>' + seoAction('page')(x); },
      fields: [{ name: 'title', label: 'عنوان', required: true }, { name: 'slug', label: 'اسلاگ', help: 'تغییر اسلاگ، ریدایرکت ۳۰۱ می‌سازد.' },
        { name: 'template', label: 'قالب', type: 'select', options: [['default', 'عادی'], ['contact', 'تماس با ما (با فرم)'], ['faq', 'سوالات متداول']] }, { name: 'status', label: 'وضعیت', type: 'select', options: [['draft', 'پیش‌نویس'], ['published', 'منتشرشده']] },
        { name: 'excerpt', label: 'خلاصه', type: 'textarea', rows: 2, full: true }, { name: 'featured_image', label: 'تصویر', type: 'image', folder: 'pages', full: true }, { name: 'content', label: 'محتوا', type: 'html', full: true }],
      defaults: { template: 'default', status: 'draft' }
    });
  });

  page('#/faqs', 'content.pages', function () {
    A.crud('faqs', {
      title: 'سوالات متداول', label: 'پرسش متداول',
      filters: [{ name: 'product_id', label: 'نوع', options: [['null', 'عمومی فروشگاه']] }, { name: 'is_active', label: 'وضعیت', options: ACTIVE }],
      columns: [{ label: 'پرسش', render: function (x) { return cut(x.question, 120); } }, { label: 'محصول', render: function (x) { return esc(x.product_name || 'عمومی'); } }, { label: 'ترتیب', key: 'sort_order', sort: 'sort_order' }, { label: 'وضعیت', render: function (x) { return A.yesno(x.is_active); } }],
      fields: [{ name: 'question', label: 'پرسش', required: true, full: true }, { name: 'answer', label: 'پاسخ', type: 'textarea', rows: 5, required: true, full: true },
        { name: 'product_id', label: 'شناسه محصول (خالی = سوال عمومی فروشگاه)', type: 'number', step: 1 }, { name: 'sort_order', label: 'ترتیب', type: 'number', step: 1 }, { name: 'is_active', label: 'فعال', type: 'checkbox', default: 1 }],
      defaults: { is_active: 1 }
    });
  });

  var FOLDERS = { products: 'محصولات', banners: 'بنرها', categories: 'دسته‌ها', brands: 'برندها', blog: 'وبلاگ', pages: 'صفحات', reviews: 'نظرات کاربران', general: 'عمومی' };
  page('#/media', 'media.manage', function (p, query) {
    var q = Object.assign({ page: 1 }, query);
    var v = A.view('فایل‌ها و تصاویر', '<div class="a-card"><div class="a-toolbar"><input class="a-input a-toolbar__search" type="search" placeholder="جستجو در نام یا متن جایگزین…" data-q value="' + esc(q.q || '') + '">' +
      '<select class="a-input" data-folder aria-label="پوشه"><option value="">همه پوشه‌ها</option>' + opts(FOLDERS).map(function (o) { return '<option value="' + o[0] + '"' + (q.folder === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select>' +
      '<span class="a-toolbar__spacer"></span><label class="a-btn a-btn--primary a-btn--sm a-upload">' + A.icon('plus', 'icon--sm') + 'بارگذاری تصویر<input type="file" accept="image/jpeg,image/png,image/webp" hidden data-file multiple></label></div>' +
      '<p class="a-hint">JPG، PNG و WebP تا ۵ مگابایت. نوع واقعی فایل در سرور بررسی و تصویر دوباره ذخیره می‌شود؛ SVG و فایل‌های اجرایی پذیرفته نمی‌شوند.</p><div class="a-media-grid" data-grid></div><div class="a-pager" data-pager></div></div>');
    var grid = $('[data-grid]', v);
    var items = [];
    function load() {
      A.setQuery(q);
      A.get('media', q).then(function (d) {
        items = d.items;
        grid.innerHTML = items.length ? items.map(function (x, i) {
          return '<div class="a-media" data-i="' + i + '"><img src="' + esc(x.thumb_path || x.path) + '" alt="' + esc(x.alt || '') + '" loading="lazy"><span title="' + esc(x.original_name) + '">' + esc(x.original_name) + '</span><span>' + esc(FOLDERS[x.folder] || x.folder) + ' — ' + A.num(Math.round(x.size_bytes / 1024)) + ' KB</span>' +
            '<div class="a-media__tools">' + btn('کپی مسیر', 'data-copy') + btn('Alt', 'data-alt') + btn('حذف', 'data-del', 'a-btn--danger-ghost') + '</div></div>';
        }).join('') : '<div class="a-empty">تصویری پیدا نشد.</div>';
        var pg = d.pagination;
        $('[data-pager]', v).innerHTML = pg.pages > 1 ? '<span>' + A.num(pg.total) + ' فایل</span>' + btn('قبلی', 'data-page="' + (pg.page - 1) + '"' + (pg.page <= 1 ? ' disabled' : '')) + '<span>صفحه ' + A.fa(pg.page) + ' از ' + A.fa(pg.pages) + '</span>' + btn('بعدی', 'data-page="' + (pg.page + 1) + '"' + (pg.page >= pg.pages ? ' disabled' : '')) : '';
      }).catch(A.fail);
    }
    v.addEventListener('input', A.debounce(function (e) { if (e.target.matches('[data-q]')) { q.q = e.target.value.trim(); q.page = 1; load(); } }, 300));
    v.addEventListener('change', function (e) {
      if (e.target.matches('[data-folder]')) { q.folder = e.target.value; q.page = 1; load(); }
      if (e.target.matches('[data-file]')) {
        var files = Array.prototype.slice.call(e.target.files);
        files.reduce(function (chain, f) {
          return chain.then(function () { var fd = new FormData(); fd.append('file', f); fd.append('folder', q.folder || 'general'); return A.post('media', fd).then(function () { A.toast('«' + f.name + '» بارگذاری شد.'); }).catch(A.fail); });
        }, Promise.resolve()).then(load);
        e.target.value = '';
      }
    });
    v.addEventListener('click', function (e) {
      var pg = e.target.closest('[data-page]');
      if (pg && !pg.disabled) { q.page = Number(pg.dataset.page); load(); return; }
      var box = e.target.closest('[data-i]');
      if (!box) return;
      var m = items[Number(box.dataset.i)];
      if (e.target.closest('[data-copy]')) (navigator.clipboard ? navigator.clipboard.writeText(m.path) : Promise.reject()).then(function () { A.toast('مسیر کپی شد: ' + m.path); }, function () { A.toast(m.path); });
      if (e.target.closest('[data-alt]')) A.formModal({ title: 'متن جایگزین تصویر', fields: [{ name: 'alt', label: 'متن جایگزین (توضیح کوتاه محتوای تصویر)', full: true }], data: m, save: function (d) { return A.put('media/' + m.id, d); } }).then(function () { A.toast('ذخیره شد.'); load(); });
      if (e.target.closest('[data-del]')) A.confirm('تصویر «' + m.original_name + '» حذف شود؟').then(function (ok) {
        if (!ok) return;
        A.del('media/' + m.id).then(function () { A.toast('تصویر حذف شد.'); load(); }).catch(function (err) {
          if (err.status !== 409) return A.fail(err);
          A.confirm(err.message, { title: 'تصویر در حال استفاده است', yes: 'حذف قطعی' }).then(function (force) { if (force) A.del('media/' + m.id, { force: 1 }).then(function () { A.toast('تصویر حذف شد.'); load(); }).catch(A.fail); });
        });
      });
    });
    load();
  });

  /* ================================================================
     Blog
     ================================================================ */
  page('#/blog/posts', 'blog.posts', function () {
    all('r/blog-categories').then(function (cats) {
      A.list({
        title: 'مقالات', endpoint: 'blog/posts', tabKey: 'status',
        tabs: [['', 'همه']].concat(opts(POST_STATUS)),
        filters: [{ name: 'category_id', label: 'همه دسته‌ها', options: idOptions(cats) }],
        toolbar: '<a class="a-btn a-btn--primary a-btn--sm" href="admin#/blog/posts/new">' + A.icon('plus', 'icon--sm') + 'مقاله جدید</a>',
        columns: [{ label: 'عنوان', sort: 'title', render: function (x) { return '<a href="admin#/blog/posts/' + x.id + '">' + esc(x.title) + '</a>' + (Number(x.is_pillar) ? ' ' + A.badge('مقاله مرجع', 'blue') : '') + (Number(x.is_featured) ? ' ' + A.badge('ویژه', 'red') : '') + '<br><small>' + esc(x.categories || '') + '</small>'; } },
          { label: 'نویسنده', render: function (x) { return esc(x.author_name || '—'); } }, { label: 'وضعیت', render: function (x) { return status(POST_STATUS, x.status); } },
          { label: 'انتشار', sort: 'published_at', render: function (x) { return esc(x.published_at_fa || '—'); } }, { label: 'بازدید', sort: 'views', render: function (x) { return A.num(x.view_count); } },
          { label: 'دیدگاه در انتظار', render: function (x) { return Number(x.pending_comments) ? '<a href="admin#/blog/comments?status=pending">' + A.badge(A.num(x.pending_comments), 'orange') + '</a>' : '—'; } }],
        actions: function (x) { return '<a class="a-btn a-btn--light a-btn--sm" href="admin#/blog/posts/' + x.id + '">ویرایش</a><a class="a-btn a-btn--ghost a-btn--sm" href="' + esc(siteUrl(x.status === 'published' ? 'blog/' + x.slug : 'blog/preview/' + x.id)) + '" target="_blank" rel="noopener">' + (x.status === 'published' ? 'نمایش' : 'پیش‌نمایش') + '</a>' + btn('حذف', 'data-delete', 'a-btn--danger-ghost'); },
        onRow: function (e, row, reload) {
          if (e.target.closest('[data-delete]')) A.confirm('مقاله «' + row.title + '» حذف شود؟ این عملیات قابل بازگشت نیست.').then(function (ok) { if (ok) A.del('blog/posts/' + row.id).then(function () { A.toast('مقاله حذف شد.'); reload(); }).catch(A.fail); });
        }
      });
    }).catch(failView('مقالات'));
  });

  page('#/blog/posts/:id', 'blog.posts', function (params) {
    var id = params.id === 'new' ? null : params.id;
    var title = id ? 'ویرایش مقاله' : 'مقاله جدید';
    A.loading(title);
    Promise.all([
      id ? A.get('blog/posts/' + id) : Promise.resolve({ status: 'draft', allow_comments: 1, category_ids: [], tag_names: [], related_post_ids: [], product_ids: [], revisions: [] }),
      all('r/blog-authors'), id && A.can('seo.manage') ? A.get('seo/post/' + id) : Promise.resolve(null)
    ]).then(function (r) {
      var p = r[0], authors = r[1], seo = r[2];
      p.published_at = p.published_at_local || '';
      var mainF = [{ name: 'title', label: 'عنوان مقاله (H1)', required: true, full: true }, { name: 'subtitle', label: 'زیرعنوان', full: true },
        { name: 'slug', label: 'اسلاگ', full: true, help: 'خالی بماند تا از عنوان ساخته شود. تغییر اسلاگ مقاله منتشرشده، ریدایرکت ۳۰۱ می‌سازد.' },
        { name: 'excerpt', label: 'خلاصه', type: 'textarea', rows: 3, full: true, maxlength: 500 }, { name: 'content', label: 'متن مقاله', type: 'html', full: true }];
      var sideF = [{ name: 'status', label: 'وضعیت', type: 'select', options: opts(POST_STATUS), full: true },
        { name: 'published_at', label: 'زمان انتشار (برای زمان‌بندی، به وقت تهران)', type: 'datetime', full: true },
        { name: 'author_id', label: 'نویسنده', type: 'select', empty: '— انتخاب —', options: idOptions(authors), full: true },
        { name: 'category_ids', label: 'دسته‌ها', type: 'multi', endpoint: 'api/admin/r/blog-categories', full: true }, { name: 'tag_names', label: 'برچسب‌ها', type: 'tags', full: true },
        { name: 'featured_image', label: 'تصویر شاخص', type: 'image', folder: 'blog', full: true }, { name: 'featured_image_alt', label: 'متن جایگزین تصویر', full: true }, { name: 'featured_image_caption', label: 'زیرنویس تصویر', full: true },
        { name: 'is_featured', label: 'مقاله ویژه', type: 'checkbox', full: true }, { name: 'is_pillar', label: 'مقاله مرجع (Pillar)', type: 'checkbox', full: true }, { name: 'allow_comments', label: 'امکان ثبت دیدگاه', type: 'checkbox', full: true },
        { name: 'related_post_ids', label: 'مقالات مرتبط', type: 'multi', endpoint: 'api/admin/blog/posts', labelKey: 'title', full: true }, { name: 'product_ids', label: 'محصولات مرتبط', type: 'multi', endpoint: 'api/admin/products', full: true }];
      var kwF = [{ name: 'focus_keyword', label: 'عبارت کلیدی اصلی', full: true }, { name: 'related_keywords', label: 'عبارات مرتبط (با ویرگول)', full: true }];
      var revs = id && p.revisions.length ? '<ul class="a-list-plain">' + p.revisions.map(function (x) { return '<li><span>' + cut(x.title, 30) + '<br><small>' + esc(x.at + (x.admin_name ? ' — ' + x.admin_name : '')) + '</small></span>' + btn('بازگردانی', 'data-restore="' + x.id + '"') + '</li>'; }).join('') + '</ul>' : '<p class="a-hint">با هر ذخیره تغییر متن یا عنوان، نسخه قبلی نگه داشته می‌شود.</p>';
      var html = '<form data-post novalidate><div class="a-editor-layout"><div>' + '<section class="a-card"><div class="a-grid">' + fields(mainF, p) + '</div></section>' +
        '<section class="a-card"><h2 class="a-card__title">سئو مقاله</h2><div class="a-grid">' + fields(kwF, p) + '</div>' +
        (A.can('seo.manage') ? (id ? '<div style="margin-top:14px">' + seoForm(seo && seo.record) + '</div>' : '<div class="a-alert" style="margin-top:14px">عنوان سئو، توضیحات متا و Open Graph پس از اولین ذخیره فعال می‌شوند.</div>') : '') + '</section>' +
        '<section class="a-card"><h2 class="a-card__title">تحلیل سئو و خوانایی</h2><div data-analysis></div></section></div>' +
        '<aside><section class="a-card"><div class="a-grid">' + fields(sideF, p) + '</div></section>' + (id ? card('نسخه‌های قبلی', revs) : '') + '</aside></div>' +
        '<div class="a-sticky-actions"><button type="submit" class="a-btn a-btn--primary">ذخیره مقاله</button><a class="a-btn a-btn--light" href="admin#/blog/posts">بازگشت</a>' +
        (id ? '<a class="a-btn a-btn--ghost" href="' + esc(siteUrl('blog/preview/' + id)) + '" target="_blank" rel="noopener">پیش‌نمایش</a>' : '') + '<span class="a-hint" data-autosave></span></div></form>';
      var v = A.view(id ? 'ویرایش: ' + p.title : title, html);
      var form = $('[data-post]', v);
      A.enhance(form);
      function val(n) { var el = form.querySelector('[name="' + n + '"]'); return el ? el.value.trim() : ''; }
      function analyze() {
        if (A.Editor) A.Editor.sync(form, 'content');
        var res = A.analyzeSeo({ title: val('title'), seoTitle: val('seo_title'), description: val('meta_description') || val('excerpt'), slug: val('slug') || A.slugify(val('title')),
          html: form.querySelector('textarea[name="content"]').value, keyword: val('focus_keyword'), related: val('related_keywords'), image: val('featured_image'), siteHost: location.host });
        $('[data-analysis]', form).innerHTML = A.renderSeoChecks(res);
      }
      form.addEventListener('input', A.debounce(analyze, 500));
      form.addEventListener('keyup', A.debounce(analyze, 800));
      analyze();
      if (id && seo) bindSerp(form, function () { return { title: val('title'), description: val('excerpt') || seo.defaults.description, url: seo.defaults.url }; });
      form.addEventListener('click', function (e) {
        var rb = e.target.closest('[data-restore]');
        if (rb) A.confirm('متن فعلی با این نسخه جایگزین شود؟ (متن فعلی هم به‌عنوان نسخه ذخیره می‌شود)', { danger: false }).then(function (ok) {
          if (ok) A.post('blog/posts/' + id + '/revisions/' + rb.dataset.restore + '/restore').then(function () { A.toast('نسخه بازگردانی شد.'); A.go('#/blog/posts/' + id); }).catch(A.fail);
        });
      });
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var data = A.read(form, mainF.concat(sideF, kwF));
        var submit = $('[type="submit"]', form);
        submit.disabled = true;
        (id ? A.put('blog/posts/' + id, data) : A.post('blog/posts', data)).then(function (res) {
          var pid = id || res.id;
          var seoData = id && $('[data-seo-form]', form) ? readSeo(form) : null;
          return (seoData && (seo.record || seoHasValue(seoData)) ? A.put('seo/post/' + pid, seoData) : Promise.resolve()).then(function () { return pid; });
        }).then(function (pid) {
          A.toast('مقاله ذخیره شد.');
          A.go('#/blog/posts/' + pid);
        }).catch(function (err) { submit.disabled = false; A.showErrors(form, err); });
      });
    }).catch(failView(title));
  });

  page('#/blog/categories', 'blog.taxonomy', function () {
    all('r/blog-categories').then(function (cats) {
      A.crud('blog-categories', { title: 'دسته‌های وبلاگ', label: 'دسته وبلاگ',
        columns: [{ label: 'نام', key: 'name', sort: 'name' }, { label: 'اسلاگ', render: function (x) { return ltr(x.slug); } }, { label: 'مقالات', render: function (x) { return A.num(x.posts_count); } }, { label: 'ترتیب', key: 'sort_order', sort: 'sort_order' }],
        rowActions: seoAction('blog_category'),
        fields: [{ name: 'name', label: 'نام', required: true }, { name: 'slug', label: 'اسلاگ' }, { name: 'parent_id', label: 'والد', type: 'select', empty: '— بدون والد —', options: idOptions(cats) },
          { name: 'sort_order', label: 'ترتیب', type: 'number', step: 1 }, { name: 'description', label: 'توضیحات (بالای صفحه دسته نمایش داده می‌شود)', type: 'textarea', full: true }, { name: 'image_path', label: 'تصویر', type: 'image', folder: 'blog', full: true }] });
    }).catch(failView('دسته‌های وبلاگ'));
  });
  page('#/blog/tags', 'blog.taxonomy', function () {
    A.crud('blog-tags', { title: 'برچسب‌ها', label: 'برچسب',
      columns: [{ label: 'نام', key: 'name', sort: 'name' }, { label: 'اسلاگ', render: function (x) { return ltr(x.slug); } }, { label: 'مقالات', render: function (x) { return A.num(x.posts_count); } }],
      rowActions: seoAction('blog_tag'),
      fields: [{ name: 'name', label: 'نام', required: true }, { name: 'slug', label: 'اسلاگ' }] });
  });
  page('#/blog/authors', 'blog.taxonomy', function () {
    A.crud('blog-authors', { title: 'نویسندگان', label: 'نویسنده',
      columns: [{ label: 'نام', key: 'name', sort: 'name' }, { label: 'تخصص', render: function (x) { return esc(x.expertise || '—'); } }, { label: 'مقالات', render: function (x) { return A.num(x.posts_count); } }],
      rowActions: seoAction('author'),
      fields: [{ name: 'name', label: 'نام واقعی نویسنده', required: true }, { name: 'slug', label: 'اسلاگ' }, { name: 'expertise', label: 'حوزه تخصص', full: true },
        { name: 'bio', label: 'معرفی کوتاه', type: 'textarea', full: true }, { name: 'avatar_path', label: 'تصویر', type: 'image', folder: 'blog', full: true }] });
  });

  page('#/blog/comments', 'blog.comments', function () {
    A.list({
      title: 'دیدگاه‌های وبلاگ', endpoint: 'blog/comments', tabKey: 'status', defaultQuery: { status: 'pending' },
      tabs: [['pending', 'در انتظار'], ['approved', 'تأییدشده'], ['rejected', 'ردشده'], ['spam', 'اسپم'], ['', 'همه']],
      filters: [{ name: 'reported', label: 'گزارش‌شده', options: [['1', 'فقط گزارش‌شده‌ها']] }],
      columns: [{ label: 'دیدگاه', render: function (x) { return '<div class="a-review-body">' + cut(x.body, 260) + '</div>' + (Number(x.report_count) ? A.badge(A.num(x.report_count) + ' گزارش', 'red') : '') + (x.parent_id ? ' <small>پاسخ به دیدگاه دیگر</small>' : ''); } },
        { label: 'نویسنده', render: function (x) { return esc(x.author_name) + '<br><small class="a-ltr">' + esc(x.author_email || '') + '</small>'; } },
        { label: 'مقاله', render: function (x) { return '<a href="admin#/blog/posts/' + x.post_id + '">' + cut(x.post_title, 40) + '</a>'; } },
        { label: 'وضعیت', render: function (x) { return status(COMMENT_STATUS, x.status); } }, { label: 'تاریخ', render: function (x) { return esc(x.at); } }],
      actions: function (x) {
        return (x.status !== 'approved' ? btn('تأیید', 'data-s="approved"', 'a-btn--primary') : '') + (x.status !== 'rejected' ? btn('رد', 'data-s="rejected"') : '') + (x.status !== 'spam' ? btn('اسپم', 'data-s="spam"') : '') +
          btn('پاسخ', 'data-reply') + btn('حذف', 'data-delete', 'a-btn--danger-ghost');
      },
      onRow: function (e, row, reload) {
        var s = e.target.closest('[data-s]');
        if (s) A.put('blog/comments/' + row.id, { status: s.dataset.s }).then(function () { A.toast('وضعیت دیدگاه تغییر کرد.'); reload(); }).catch(A.fail);
        if (e.target.closest('[data-reply]')) A.formModal({ title: 'پاسخ به ' + row.author_name, fields: [{ name: 'body', label: 'متن پاسخ (به نام فروشگاه منتشر می‌شود)', type: 'textarea', rows: 4, required: true, full: true }],
          extra: '<div class="a-span a-review-body">' + esc(row.body) + '</div>', save: function (d) { return A.post('blog/comments/' + row.id + '/reply', d); } }).then(function () { A.toast('پاسخ منتشر شد.'); reload(); });
        if (e.target.closest('[data-delete]')) A.confirm('این دیدگاه حذف شود؟').then(function (ok) { if (ok) A.del('blog/comments/' + row.id).then(function () { A.toast('دیدگاه حذف شد.'); reload(); }).catch(A.fail); });
      }
    });
  });

  /* ================================================================
     SEO: content overview, per-item editor, redirects, sitemap & robots
     ================================================================ */
  var SEO_TYPES = [['product', 'محصولات'], ['category', 'دسته‌ها'], ['brand', 'برندها'], ['page', 'صفحات'], ['post', 'مقالات'], ['blog_category', 'دسته‌های وبلاگ'], ['blog_tag', 'برچسب‌ها'], ['author', 'نویسندگان'], ['static', 'صفحات ثابت']];
  page('#/seo', 'seo.manage', function () {
    A.list({
      title: 'سئو محتوا', endpoint: 'seo', tabKey: 'type', defaultQuery: { type: 'product' }, tabs: SEO_TYPES,
      filters: [{ name: 'missing', label: 'همه', options: [['title', 'بدون عنوان سئو'], ['description', 'بدون توضیحات متا'], ['noindex', 'noindex']] }],
      columns: [{ label: 'محتوا', render: function (x) { return esc(x.title) + '<br><small class="a-ltr">/' + esc(x.url) + '</small>'; } },
        { label: 'عنوان سئو', render: function (x) { return x.seo_title ? cut(x.seo_title, 50) : A.badge('پیش‌فرض', 'muted'); } },
        { label: 'توضیحات متا', render: function (x) { return x.meta_description ? cut(x.meta_description, 70) : A.badge('خالی', 'orange'); } },
        { label: 'ایندکس', render: function (x) { return Number(x.is_indexable) ? A.badge('index', 'green') : A.badge('noindex', 'red'); } }],
      actions: function (x) { return '<a class="a-btn a-btn--light a-btn--sm" href="admin#/seo/' + (A.query().type || 'product') + '/' + x.id + '">ویرایش سئو</a>'; }
    });
  });

  page('#/seo/:type/:id', 'seo.manage', function (params) {
    A.loading('تنظیمات سئو');
    A.get('seo/' + params.type + '/' + params.id).then(function (d) {
      var def = d.defaults;
      var v = A.view('سئو: ' + def.title, '<form class="a-card" data-seo-page novalidate><p class="a-hint">مقادیر خالی از محتوای اصلی ساخته می‌شوند. ' + (def.focus_keyword ? 'عبارت کلیدی: «' + esc(def.focus_keyword) + '»' : '') + '</p>' + seoForm(d.record) +
        '<div class="a-sticky-actions"><button type="submit" class="a-btn a-btn--primary">ذخیره</button><button type="button" class="a-btn a-btn--light" data-back>بازگشت</button><a class="a-btn a-btn--ghost" href="' + esc(def.url) + '" target="_blank" rel="noopener">نمایش صفحه</a></div></form>');
      var form = $('[data-seo-page]', v);
      A.enhance(form);
      bindSerp(form, function () { return def; });
      $('[data-back]', form).addEventListener('click', function () { history.back(); });
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        A.put('seo/' + params.type + '/' + params.id, readSeo(form)).then(function () { A.toast('تنظیمات سئو ذخیره شد.'); }).catch(function (err) { A.showErrors(form, err); });
      });
    }).catch(failView('تنظیمات سئو'));
  });

  page('#/redirects', 'redirects.manage', function () {
    A.crud('redirects', {
      title: 'ریدایرکت‌ها', label: 'ریدایرکت',
      filters: [{ name: 'status_code', label: 'نوع', options: [['301', '۳۰۱ دائمی'], ['302', '۳۰۲ موقت']] }, { name: 'is_active', label: 'وضعیت', options: ACTIVE }],
      columns: [{ label: 'مبدأ', sort: 'source_path', render: function (x) { return ltr(x.source_path); } }, { label: 'مقصد', render: function (x) { return ltr(x.target_url); } },
        { label: 'کد', render: function (x) { return A.fa(x.status_code); } }, { label: 'استفاده', sort: 'hits', render: function (x) { return A.num(x.hits); } }, { label: 'وضعیت', render: function (x) { return A.yesno(x.is_active); } }],
      fields: [{ name: 'source_path', label: 'مسیر مبدأ (مثلاً /old-page)', dir: 'ltr', required: true, full: true }, { name: 'target_url', label: 'مقصد (مسیر داخلی یا آدرس کامل)', dir: 'ltr', required: true, full: true },
        { name: 'status_code', label: 'نوع', type: 'select', options: [['301', '۳۰۱ — انتقال دائمی'], ['302', '۳۰۲ — انتقال موقت']] }, { name: 'is_active', label: 'فعال', type: 'checkbox', default: 1 }],
      defaults: { status_code: '301', is_active: 1 }
    });
  });

  page('#/sitemap', 'seo.manage', function () {
    var SECTIONS = { pages: 'صفحات', products: 'محصولات', categories: 'دسته‌ها', brands: 'برندها', posts: 'مقالات', blog_categories: 'دسته‌های وبلاگ', authors: 'نویسندگان' };
    var list = A.crud('sitemap', {
      title: 'سایت‌مپ و robots', label: 'تنظیمات سایت‌مپ', readonly: true, noDelete: true, search: false,
      toolbar: '<a class="a-btn a-btn--ghost a-btn--sm" href="' + esc(siteUrl('sitemap.xml')) + '" target="_blank" rel="noopener">مشاهده sitemap.xml</a>',
      columns: [{ label: 'بخش', render: function (x) { return esc(SECTIONS[x.section] || x.section); } }, { label: 'وضعیت', render: function (x) { return A.yesno(x.is_enabled); } },
        { label: 'changefreq', render: function (x) { return ltr(x.changefreq); } }, { label: 'priority', render: function (x) { return ltr(x.priority); } }],
      fields: [{ name: 'is_enabled', label: 'در سایت‌مپ باشد', type: 'checkbox', full: true },
        { name: 'changefreq', label: 'changefreq', type: 'select', options: ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'].map(function (x) { return [x, x]; }) },
        { name: 'priority', label: 'priority (۰ تا ۱)', type: 'number', step: 0.1 }]
    });
    list.root.insertAdjacentHTML('beforeend', '<section class="a-card"><h2 class="a-card__title">robots.txt</h2><p class="a-hint">قوانین پایه (مسدود بودن پنل مدیریت، سبد خرید، حساب کاربری و API و آدرس سایت‌مپ) خودکار اضافه می‌شوند. فقط خطوط User-agent، Allow، Disallow و Crawl-delay مجاز است.</p>' +
      '<form data-robots novalidate>' + A.field({ name: 'extra', label: 'قوانین اضافه', type: 'textarea', rows: 5, dir: 'ltr', full: true }) + '<button type="submit" class="a-btn a-btn--primary a-btn--sm" style="margin-top:8px">ذخیره robots.txt</button></form>' +
      '<h3 class="a-hint">خروجی فعلی</h3><pre class="a-code" data-robots-preview>…</pre></section>');
    var form = $('[data-robots]', list.root);
    function loadRobots() { A.get('seo/robots').then(function (d) { form.elements.extra.value = d.extra; $('[data-robots-preview]', list.root).textContent = d.preview; }).catch(A.fail); }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      A.put('seo/robots', { extra: form.elements.extra.value }).then(function () { A.toast('robots.txt ذخیره شد.'); loadRobots(); }).catch(function (err) { A.showErrors(form, err); });
    });
    loadRobots();
  });

  /* ================================================================
     Settings, admins & roles, activity log
     ================================================================ */
  var SETTING_GROUPS = { general: 'عمومی', contact: 'تماس', social: 'شبکه‌های اجتماعی', header: 'سربرگ', footer: 'فوتر', shipping: 'ارسال', pricing: 'قیمت و پیشنهادها', reviews: 'نظرات', blog: 'وبلاگ', seo: 'سئو' };
  page('#/settings', 'settings.manage', function () {
    A.loading('تنظیمات فروشگاه');
    A.get('settings').then(function (rows) {
      var groups = {};
      rows.forEach(function (r) { (groups[r.group_name] = groups[r.group_name] || []).push(r); });
      var fieldsFor = function (r) {
        var f = { name: r.setting_key, label: r.label || r.setting_key, full: r.type === 'text' || r.type === 'json' || r.type === 'image' };
        if (r.type === 'bool') f.type = 'checkbox';
        else if (r.type === 'int') { f.type = 'number'; f.step = 1; }
        else if (r.type === 'text') { f.type = 'textarea'; f.rows = 3; }
        else if (r.type === 'json') { f.type = 'textarea'; f.rows = 4; f.dir = 'ltr'; f.help = 'قالب JSON معتبر.'; }
        else if (r.type === 'image') { f.type = 'image'; f.folder = 'general'; }
        else if (r.type === 'color') f.type = 'color';
        if (r.setting_key.indexOf('social_') === 0) { f.dir = 'ltr'; f.help = 'آدرس کامل با https://'; }
        if (r.setting_key === 'robots_extra') f.help = 'از بخش «سایت‌مپ و robots» هم قابل ویرایش است.';
        return f;
      };
      var keys = Object.keys(groups);
      var allFields = rows.map(fieldsFor);
      var data = {}; rows.forEach(function (r) { data[r.setting_key] = r.value; });
      var html = '<form class="a-card" data-settings novalidate>' + tabBar(keys.map(function (k) { return [k, SETTING_GROUPS[k] || k]; })) +
        keys.map(function (k, i) { return '<div class="a-grid a-panel" data-panel="' + k + '"' + (i ? ' hidden' : '') + '>' + groups[k].map(function (r) { return A.field(fieldsFor(r), data[r.setting_key]); }).join('') + '</div>'; }).join('') +
        '<div class="a-sticky-actions"><button type="submit" class="a-btn a-btn--primary">ذخیره تنظیمات</button><span class="a-hint">کلیدهای پرداخت و رمزها در فایل .env سرور نگه داری می‌شوند، نه در این صفحه.</span></div></form>';
      var v = A.view('تنظیمات فروشگاه', html);
      var form = $('[data-settings]', v);
      tabs(form);
      A.enhance(form);
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var values = A.read(form, allFields);
        A.put('settings', { settings: values }).then(function () { A.toast('تنظیمات ذخیره شد.'); }).catch(function (err) {
          A.showErrors(form, err);
          var slot = Object.keys(err.errors || {}).map(function (k) { return form.querySelector('[data-error-for="' + k + '"]'); }).filter(Boolean)[0];
          if (slot) $('[data-tab="' + slot.closest('[data-panel]').dataset.panel + '"]', form).click();
        });
      });
    }).catch(failView('تنظیمات فروشگاه'));
  });

  page('#/admins', 'admins.manage', function () {
    A.loading('مدیران و نقش‌ها');
    A.get('admins').then(function (d) {
      var roleName = {}; d.roles.forEach(function (r) { roleName[r.id] = r.name; });
      var admins = '<div class="a-table-wrap"><table class="a-table"><thead><tr><th>نام</th><th>نام کاربری</th><th>نقش‌ها</th><th>وضعیت</th><th>آخرین ورود</th><th></th></tr></thead><tbody>' + d.admins.map(function (a) {
        return '<tr data-admin="' + a.id + '"><td>' + esc(a.full_name) + '</td><td>' + ltr(a.username) + '</td><td>' + a.role_ids.map(function (r) { return A.badge(roleName[r] || r, 'blue'); }).join(' ') + '</td><td>' + status({ active: 'فعال', disabled: 'غیرفعال' }, a.status) + '</td><td class="a-muted">' + esc(a.last_login_fa || '—') + '</td><td class="a-actions">' + btn('ویرایش', 'data-edit-admin') + '</td></tr>';
      }).join('') + '</tbody></table></div>';
      var roles = '<ul class="a-list-plain">' + d.roles.map(function (r) {
        return '<li data-role="' + r.id + '"><span><strong>' + esc(r.name) + '</strong><br><small>' + esc(r.description || '') + ' — ' + A.num(r.permission_ids.length) + ' مجوز</small></span>' + (r.slug === 'super_admin' ? A.badge('همه مجوزها', 'green') : btn('ویرایش مجوزها', 'data-edit-role')) + '</li>';
      }).join('') + '</ul>';
      var v = A.view('مدیران و نقش‌ها', '<div class="a-cols a-cols--3">' + card('مدیران', admins, btn(A.icon('plus', 'icon--sm') + 'مدیر جدید', 'data-new-admin', 'a-btn--primary')) + card('نقش‌ها', roles) + '</div>');
      var reload = function () { A.go('#/admins'); };
      function roleChecks(selected) {
        return '<fieldset class="a-fieldset a-span"><legend>نقش‌ها</legend>' + d.roles.map(function (r) { return '<label class="a-check"><input type="checkbox" data-role-id value="' + r.id + '"' + (selected.indexOf(r.id) > -1 ? ' checked' : '') + '><span>' + esc(r.name) + '</span></label>'; }).join(' ') + '</fieldset>';
      }
      function adminModal(a) {
        A.formModal({ title: a ? 'ویرایش مدیر' : 'مدیر جدید', wide: true, data: a || { status: 'active' },
          fields: [{ name: 'full_name', label: 'نام کامل', required: true }, a ? { name: 'username_view', label: 'نام کاربری', type: 'readonly', default: a.username } : { name: 'username', label: 'نام کاربری', dir: 'ltr', required: true },
            { name: 'email', label: 'ایمیل', type: 'email', dir: 'ltr', required: true }, { name: 'status', label: 'وضعیت', type: 'select', options: [['active', 'فعال'], ['disabled', 'غیرفعال']] },
            { name: 'password', label: a ? 'رمز عبور جدید (خالی = بدون تغییر)' : 'رمز عبور', type: 'password', dir: 'ltr', required: !a, help: 'حداقل ۱۲ کاراکتر شامل حرف و عدد. رمز را حضوری یا از راه امن به مدیر بدهید.' }],
          extra: roleChecks(a ? a.role_ids : []),
          onOpen: function (form) { $('[name="password"]', form).setAttribute('autocomplete', 'new-password'); },
          save: function (data) {
            data.role_ids = $$('.a-modal [data-role-id]:checked').map(function (c) { return Number(c.value); });
            return a ? A.put('admins/' + a.id, data) : A.post('admins', data);
          } }).then(function () { A.toast('اطلاعات مدیر ذخیره شد.'); reload(); });
      }
      v.addEventListener('click', function (e) {
        if (e.target.closest('[data-new-admin]')) adminModal(null);
        var ar = e.target.closest('[data-admin]');
        if (ar && e.target.closest('[data-edit-admin]')) adminModal(d.admins.filter(function (a) { return String(a.id) === ar.dataset.admin; })[0]);
        var rr = e.target.closest('[data-role]');
        if (rr && e.target.closest('[data-edit-role]')) {
          var role = d.roles.filter(function (r) { return String(r.id) === rr.dataset.role; })[0];
          var groups = {};
          d.permissions.forEach(function (p) { (groups[p.group_name] = groups[p.group_name] || []).push(p); });
          var m = A.modal({ title: 'مجوزهای نقش «' + role.name + '»', wide: true,
            body: '<div class="a-perm-groups">' + Object.keys(groups).map(function (g) {
              return '<fieldset class="a-fieldset"><legend>' + esc(g) + '</legend>' + groups[g].map(function (p) { return '<label class="a-check"><input type="checkbox" value="' + p.id + '"' + (role.permission_ids.indexOf(Number(p.id)) > -1 ? ' checked' : '') + '><span>' + esc(p.name) + ' <small class="a-ltr">' + esc(p.slug) + '</small></span></label>'; }).join('<br>') + '</fieldset>';
            }).join('') + '</div>',
            foot: '<button type="button" class="a-btn a-btn--primary" data-save-role>ذخیره مجوزها</button><button type="button" class="a-btn a-btn--light" data-close>انصراف</button>' });
          $('[data-save-role]', m.el).addEventListener('click', function () {
            var ids = $$('input[type="checkbox"]:checked', m.el).map(function (c) { return Number(c.value); });
            A.put('roles/' + role.id, { permission_ids: ids }).then(function () { m.close(); A.toast('مجوزهای نقش ذخیره شد.'); reload(); }).catch(A.fail);
          });
        }
      });
    }).catch(failView('مدیران و نقش‌ها'));
  });

  var ENTITY_LABELS = { product: 'محصول', order: 'سفارش', review: 'نظر', post: 'مقاله', settings: 'تنظیمات', user: 'مشتری', admin_user: 'مدیر', role: 'نقش', media: 'تصویر', homepage_section: 'صفحه اصلی', menu_item: 'منو', banners: 'بنر', coupons: 'کد تخفیف', redirects: 'ریدایرکت', blog_comment: 'دیدگاه' };
  page('#/activity', 'logs.view', function () {
    A.list({
      title: 'گزارش فعالیت‌ها', endpoint: 'activity',
      filters: [{ name: 'entity_type', label: 'همه بخش‌ها', options: opts(ENTITY_LABELS) }],
      columns: [{ label: 'زمان', render: function (x) { return esc(x.at); } }, { label: 'مدیر', render: function (x) { return esc(x.admin_name || 'سیستم'); } },
        { label: 'عملیات', render: function (x) { return ltr(x.action); } }, { label: 'شرح', render: function (x) { return esc(x.description) + (x.entity_type ? ' <small>(' + esc(ENTITY_LABELS[x.entity_type] || x.entity_type) + (x.entity_id ? ' #' + A.fa(x.entity_id) : '') + ')</small>' : ''); } },
        { label: 'IP', render: function (x) { return ltr(x.ip); } }],
      actions: function (x) { return x.changes ? btn('جزئیات تغییر', 'data-changes') : ''; },
      onRow: function (e, row) {
        if (!e.target.closest('[data-changes]')) return;
        var pretty;
        try { pretty = JSON.stringify(JSON.parse(row.changes), null, 2); } catch (err) { pretty = row.changes; }
        A.modal({ title: row.description, wide: true, body: '<pre class="a-code">' + esc(pretty) + '</pre>' });
      }
    });
  });

  A.boot();
})();
