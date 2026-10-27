/* Shared shell built from DB-managed menus/settings (window.AP_BOOT): header, mega menu, drawer, footer, toasts, product card. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var icon = u.icon;
  var boot = AP.boot || {};
  var S = boot.settings || {};
  var menus = boot.menus || {};

  function link(url) { return u.escape(url === '/' ? '' : String(url || '').replace(/^\//, '')); }

  /* ---------------- Header ---------------- */
  function headerHTML() {
    var user = boot.user;
    var topbar = (Array.isArray(S.topbar_items) ? S.topbar_items : []).map(function (t) {
      var inner = icon(u.escape(t.icon || 'check'), 'icon--sm') + u.escape(t.text || '');
      return t.url ? '<a href="' + link(t.url) + '">' + inner + '</a>' : '<span>' + inner + '</span>';
    }).join('');
    var navItems = (menus.header || []).map(function (item, i) {
      var id = 'mega-' + i;
      var has = item.children && item.children.length;
      return '<li class="mainnav__item' + (has ? ' has-mega' : '') + '">' +
        '<a class="mainnav__link' + (item.highlight ? ' mainnav__link--accent' : '') + '" href="' + link(item.url) + '"' + (has ? ' aria-haspopup="true" aria-controls="' + id + '"' : '') + '>' +
        (item.highlight ? icon('discount', 'icon--sm') : '') + u.escape(item.title) + (has ? icon('chevron-down', 'icon--sm') : '') + '</a>' +
        (has ? '<div class="mega" id="' + id + '"><div class="mega__inner' + (item.image ? '' : ' mega__inner--noimg') + '">' +
          '<div class="mega__links"><a class="mega__all" href="' + link(item.url) + '">همه محصولات ' + u.escape(item.title) + icon('chevron-left', 'icon--sm') + '</a><ul>' +
          item.children.map(function (c) { return '<li><a href="' + link(c.url) + '">' + u.escape(c.title) + '</a></li>'; }).join('') + '</ul></div>' +
          (item.image ? '<a class="mega__promo" href="' + link(item.url) + '" tabindex="-1"><img src="' + u.escape(item.image) + '" alt="" width="600" height="360" loading="lazy"></a>' : '') +
          '</div></div>' : '') + '</li>';
    }).join('');

    return (topbar ? '<div class="topbar"><div class="container topbar__inner">' + topbar + '</div></div>' : '') +
      '<div class="header-main"><div class="container header-main__inner">' +
      '<button type="button" class="icon-btn header-main__menu" aria-label="باز کردن منو" aria-controls="mobile-drawer" aria-expanded="false" data-drawer-open>' + icon('menu') + '</button>' +
      '<a class="header-main__logo" href=""><img src="' + u.escape(S.logo || 'assets/images/ui/logo.svg') + '" alt="' + u.escape(S.store_name || '') + '" width="176" height="45"></a>' +
      '<form class="header-search" action="search" method="get" role="search">' +
      '<label class="visually-hidden" for="header-q">جستجوی محصولات</label>' +
      '<input id="header-q" name="q" type="search" placeholder="جستجو در لوازم جانبی و قطعات خودرو…" autocomplete="off" aria-autocomplete="list" aria-controls="search-suggest" aria-expanded="false" enterkeyhint="search">' +
      '<button type="submit" class="header-search__btn" aria-label="جستجو">' + icon('search') + '</button>' +
      '<div class="search-suggest" id="search-suggest" aria-label="پیشنهادهای جستجو" hidden></div></form>' +
      '<div class="header-actions">' +
      '<a class="header-action header-action--account" href="' + (user ? 'account' : 'login') + '">' + icon('user') + '<span>' + (user ? u.escape(user.name) : 'ورود | ثبت‌نام') + '</span></a>' +
      '<a class="header-action" href="favorites" aria-label="علاقه‌مندی‌ها">' + icon('heart') + '<span class="count-badge" data-count="favs" hidden>۰</span></a>' +
      '<a class="header-action header-action--cart" href="cart" aria-label="سبد خرید">' + icon('cart') + '<span class="count-badge" data-count="cart" hidden>۰</span></a>' +
      '</div></div></div>' +
      '<nav class="mainnav" aria-label="منوی اصلی"><div class="container"><ul class="mainnav__list">' + navItems + '</ul></div></nav>';
  }

  function drawerHTML() {
    var items = (menus.mobile || menus.header || []).map(function (item, i) {
      if (!item.children || !item.children.length) return '<li><a class="drawer__link" href="' + link(item.url) + '">' + u.escape(item.title) + '</a></li>';
      var id = 'drawer-sub-' + i;
      return '<li><button type="button" class="drawer__link drawer__toggle" aria-expanded="false" aria-controls="' + id + '">' + u.escape(item.title) + icon('chevron-down', 'icon--sm') + '</button>' +
        '<ul class="drawer__sub" id="' + id + '" hidden><li><a href="' + link(item.url) + '">همه ' + u.escape(item.title) + '</a></li>' +
        item.children.map(function (c) { return '<li><a href="' + link(c.url) + '">' + u.escape(c.title) + '</a></li>'; }).join('') + '</ul></li>';
    }).join('');
    return '<div class="drawer" id="mobile-drawer" hidden><div class="drawer__overlay" data-drawer-close></div>' +
      '<div class="drawer__panel" role="dialog" aria-modal="true" aria-label="منوی دسته‌بندی‌ها">' +
      '<div class="drawer__head"><img src="' + u.escape(S.logo || 'assets/images/ui/logo.svg') + '" alt="' + u.escape(S.store_name || '') + '" width="140" height="36">' +
      '<button type="button" class="icon-btn" aria-label="بستن منو" data-drawer-close>' + icon('close') + '</button></div>' +
      '<nav aria-label="منوی موبایل"><ul class="drawer__list">' + items + '</ul></nav>' +
      '<div class="drawer__foot"><a href="tracking">' + icon('package-track', 'icon--sm') + 'پیگیری سفارش</a><a href="contact">' + icon('headset', 'icon--sm') + 'تماس با ما</a></div></div></div>';
  }

  function bottomNavHTML() {
    var page = document.body.dataset.page;
    return '<nav class="bottom-nav" aria-label="ناوبری پایین صفحه">' + [
      ['home', 'خانه', '', 'home'], ['grid', 'محصولات', 'products', 'products'], ['cart', 'سبد خرید', 'cart', 'cart'],
      ['heart', 'علاقه‌مندی', 'favorites', 'favorites'], ['user', 'حساب', boot.user ? 'account' : 'login', 'account']
    ].map(function (it) {
      var badge = it[3] === 'cart' ? '<span class="count-badge" data-count="cart" hidden>۰</span>' : it[3] === 'favorites' ? '<span class="count-badge" data-count="favs" hidden>۰</span>' : '';
      return '<a href="' + it[2] + '"' + (page === it[3] || (it[3] === 'account' && page === 'login') ? ' aria-current="page"' : '') + '>' + icon(it[0]) + badge + '<span>' + it[1] + '</span></a>';
    }).join('') + '</nav>';
  }

  /* ---------------- Footer ---------------- */
  function footerHTML() {
    var col = function (title, key) {
      var list = menus[key] || [];
      return list.length ? '<div class="footer-col"><h2 class="footer-col__title">' + title + '</h2><ul>' +
        list.map(function (m) { return '<li><a href="' + link(m.url) + '">' + u.escape(m.title) + '</a></li>'; }).join('') + '</ul></div>' : '';
    };
    var socials = [['instagram', 'اینستاگرام'], ['telegram', 'تلگرام'], ['whatsapp', 'واتس‌اپ'], ['linkedin', 'لینکدین'], ['x', 'ایکس']]
      .filter(function (s) { return S['social_' + s[0]]; })
      .map(function (s) {
        return '<a class="social" href="' + u.escape(S['social_' + s[0]]) + '" target="_blank" rel="noopener noreferrer" aria-label="' + s[1] + '">' + icon(s[0] === 'x' ? 'x-social' : s[0]) + '</a>';
      }).join('');
    var legal = (menus.footer_legal || []).map(function (m) { return '<a href="' + link(m.url) + '">' + u.escape(m.title) + '</a>'; }).join('');
    return '<div class="footer-features"><div class="container footer-features__inner">' +
      [['shield', 'ضمانت اصالت کالا'], ['truck', 'ارسال سریع'], ['headset', 'پشتیبانی تخصصی'], ['return', 'بازگشت کالا طبق شرایط'], ['card', 'پرداخت امن']]
        .map(function (f) { return '<div class="footer-feature">' + icon(f[0], 'icon--lg') + '<span>' + f[1] + '</span></div>'; }).join('') + '</div></div>' +
      '<div class="container footer-grid">' +
      '<div class="footer-col footer-col--about"><img src="' + u.escape(S.logo || 'assets/images/ui/logo.svg') + '" alt="' + u.escape(S.store_name || '') + '" width="176" height="45" loading="lazy">' +
      '<p>' + u.escape(S.footer_about || '') + '</p>' +
      '<div class="trust"><img src="assets/images/ui/trust-1.svg" alt="نماد اعتماد نمونه" width="72" height="72" loading="lazy"><img src="assets/images/ui/trust-2.svg" alt="نشان امنیت پرداخت نمونه" width="72" height="72" loading="lazy"></div></div>' +
      col('راهنمای خرید', 'footer_guide') + col('خدمات مشتریان', 'footer_service') + col('دسته‌بندی محصولات', 'footer_categories') +
      '<div class="footer-col footer-col--contact"><h2 class="footer-col__title">تماس با ما</h2><ul>' +
      (S.phone ? '<li>' + icon('phone', 'icon--sm') + '<a href="tel:' + u.escape(S.phone_link || '') + '" dir="ltr">' + u.escape(S.phone) + '</a></li>' : '') +
      (S.email ? '<li>' + icon('mail', 'icon--sm') + '<a href="mailto:' + u.escape(S.email) + '" dir="ltr">' + u.escape(S.email) + '</a></li>' : '') +
      (S.address ? '<li>' + icon('location', 'icon--sm') + '<span>' + u.escape(S.address) + '</span></li>' : '') +
      (S.support_hours ? '<li>' + icon('clock', 'icon--sm') + '<span>' + u.escape(S.support_hours) + '</span></li>' : '') + '</ul>' +
      (socials ? '<h2 class="footer-col__title footer-col__title--social">ما را دنبال کنید</h2><div class="socials">' + socials + '</div>' : '') + '</div></div>' +
      '<div class="footer-bottom"><div class="container footer-bottom__inner"><p>© ' + u.faDigits(new Intl.DateTimeFormat('en-u-ca-persian', { year: 'numeric' }).format(new Date()).replace(/\D/g, '')) + ' ' +
      u.escape(S.copyright_text || '') + '</p><nav aria-label="پیوندهای حقوقی">' + legal + '</nav></div></div>';
  }

  /* ---------------- Toasts ---------------- */
  var toastRegion;
  AP.toast = function (message, type) {
    if (!toastRegion) return;
    var el = document.createElement('div');
    el.className = 'toast toast--' + (type || 'success');
    el.innerHTML = icon(type === 'error' ? 'alert' : 'check') + '<span>' + u.escape(message) + '</span>';
    toastRegion.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('is-visible'); });
    setTimeout(function () {
      el.classList.remove('is-visible');
      setTimeout(function () { el.remove(); }, 300);
    }, 3400);
  };

  /* ---------------- Product card (API product DTO) ---------------- */
  function priceHTML(p) {
    return '<div class="price">' +
      (p.discountPercent ? '<div class="price__old"><span class="visually-hidden">قیمت قبلی:</span><del>' + u.num(p.oldPrice) + '</del><span class="badge badge--red">' + u.num(p.discountPercent) + '٪</span></div>'
        : '<div class="price__old" aria-hidden="true"></div>') +
      '<div class="price__now"><strong>' + u.num(p.price) + '</strong><span>تومان</span></div></div>';
  }

  function stockHTML(p) {
    if (!p.inStock) return '<span class="stock stock--out">ناموجود</span>';
    if (p.stock <= 10) return '<span class="stock stock--low">تنها ' + u.num(p.stock) + ' عدد در انبار</span>';
    return '<span class="stock stock--in">' + icon('check', 'icon--sm') + 'موجود در انبار</span>';
  }

  function ratingHTML(p) {
    return p.reviewsCount > 0
      ? '<div class="pcard__rating">' + icon('star-filled', 'icon--sm') + '<span>' + u.faDigits(p.rating.toFixed(1)) + '</span><span class="pcard__reviews">(' + u.num(p.reviewsCount) + ' نظر)</span></div>'
      : '<div class="pcard__rating pcard__rating--none">بدون امتیاز</div>';
  }

  function favHTML(p) {
    var on = AP.favs.has(p.id);
    return '<button type="button" class="fav-btn" data-fav="' + p.id + '" aria-pressed="' + on + '" aria-label="' + (on ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها') + '">' + icon(on ? 'heart-filled' : 'heart') + '</button>';
  }

  function addHTML(p, compact) {
    if (!p.inStock) return '<button type="button" class="btn btn--block btn--sm" disabled>ناموجود</button>';
    if (compact) return '<button type="button" class="icon-btn add-mini" data-add="' + p.id + '" aria-label="افزودن ' + u.escape(p.title) + ' به سبد خرید">' + icon('plus') + '</button>';
    return '<button type="button" class="btn btn--primary btn--block btn--sm" data-add="' + p.id + '" aria-label="افزودن ' + u.escape(p.title) + ' به سبد خرید">' + icon('cart', 'icon--sm') + '<span>افزودن به سبد</span></button>';
  }

  function img(p, size) {
    return '<img src="' + u.escape(p.image) + '" alt="' + u.escape(p.imageAlt || p.title) + '" width="' + size + '" height="' + size + '" loading="lazy" decoding="async">';
  }

  /** variant: default | amazing | compact | ranked ; opts.rank, opts.action ('details'), opts.extra */
  AP.card = function (p, variant, opts) {
    variant = variant || 'default';
    opts = opts || {};
    var url = u.escape(p.url);
    var title = '<h3 class="pcard__title"><a href="' + url + '">' + u.escape(p.title) + '</a></h3>';
    var brand = p.brand ? '<a class="pcard__brand" href="brands/' + u.escape(p.brand.slug) + '">' + u.escape(p.brand.name) + '</a>' : '';

    if (variant === 'ranked') {
      return '<article class="pcard pcard--ranked"><a class="pcard__media" href="' + url + '" tabindex="-1" aria-hidden="true">' + img(p, 88) + '</a>' +
        '<span class="pcard__rank" aria-label="رتبه ' + u.faDigits(opts.rank) + '">' + u.faDigits(opts.rank) + '</span>' +
        '<div class="pcard__body">' + title + ratingHTML(p) + '<div class="price__now"><strong>' + u.num(p.price) + '</strong><span>تومان</span></div></div></article>';
    }
    if (variant === 'compact') {
      return '<article class="pcard pcard--compact"><a class="pcard__media" href="' + url + '" tabindex="-1" aria-hidden="true">' + img(p, 110) + '</a>' +
        '<div class="pcard__body">' + brand + title + stockHTML(p) + '<div class="pcard__foot">' + priceHTML(p) + addHTML(p, true) + '</div></div></article>';
    }
    var badges = '';
    var stock = stockHTML(p);
    if (variant === 'amazing') {
      badges = '<span class="badge badge--soft">' + icon('flash', 'icon--sm') + 'شگفت‌انگیز</span>';
      if (p.inStock && p.stock <= 30) {
        var pct = Math.max(8, Math.round(p.stock / 30 * 100));
        stock = '<div class="stockbar"><div class="stockbar__label"><span>باقی‌مانده</span><strong>' + u.num(p.stock) + ' عدد</strong></div>' +
          '<div class="stockbar__track" role="progressbar" aria-label="موجودی باقی‌مانده" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + pct + '"><span style="width:' + pct + '%"></span></div></div>';
      }
    } else {
      if (p.isNew) badges += '<span class="badge badge--new">جدید</span>';
      if (opts.action === 'details' && p.isExpertPick) badges += '<span class="badge badge--expert">' + icon('award', 'icon--sm') + 'منتخب کارشناس</span>';
    }
    var action = opts.action === 'details' ? '<a class="btn btn--ghost btn--block btn--sm" href="' + url + '">' + icon('eye', 'icon--sm') + 'مشاهده جزئیات</a>' : addHTML(p);
    return '<article class="pcard pcard--' + variant + '"><div class="pcard__media"><a href="' + url + '" tabindex="-1" aria-hidden="true">' + img(p, 400) + '</a>' +
      '<div class="pcard__badges">' + badges + '</div>' + favHTML(p) + '</div>' +
      '<div class="pcard__body">' + brand + title + ratingHTML(p) + stock + priceHTML(p) + action + (opts.extra || '') + '</div></article>';
  };

  AP.cards = function (list, variant, opts) {
    return (list || []).map(function (p, i) { return AP.card(p, variant, Object.assign({ rank: i + 1 }, opts || {})); }).join('');
  };

  /* ---------------- Live state ---------------- */
  function updateCounts() {
    var map = { cart: AP.cart.count(), favs: AP.favs.count() };
    u.$$('[data-count]').forEach(function (el) {
      var n = map[el.dataset.count];
      el.textContent = u.num(n);
      el.hidden = !n;
    });
    u.$$('.header-action--cart').forEach(function (a) { a.setAttribute('aria-label', 'سبد خرید، ' + u.num(map.cart) + ' کالا'); });
  }

  function updateFavButtons() {
    u.$$('[data-fav]').forEach(function (btn) {
      var on = AP.favs.has(btn.dataset.fav);
      btn.setAttribute('aria-pressed', String(on));
      if (btn.classList.contains('fav-btn')) btn.setAttribute('aria-label', on ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها');
      var use = btn.querySelector('use');
      if (use) use.setAttribute('href', 'assets/icons/icons.svg#i-' + (on ? 'heart-filled' : 'heart'));
    });
  }

  function bindGlobalEvents() {
    document.addEventListener('click', function (e) {
      var add = e.target.closest('[data-add]');
      if (add) {
        if (add.disabled) return;
        add.disabled = true;
        AP.cart.add(add.dataset.add, 1).then(function (res) {
          add.disabled = false;
          AP.toast(res.message, res.ok ? 'success' : 'error');
          if (res.ok) {
            add.classList.add('is-added');
            setTimeout(function () { add.classList.remove('is-added'); }, 700);
          }
        });
        return;
      }
      var fav = e.target.closest('[data-fav]');
      if (fav) {
        AP.favs.toggle(fav.dataset.fav).then(function (on) {
          AP.toast(on ? 'به علاقه‌مندی‌ها اضافه شد.' : 'از علاقه‌مندی‌ها حذف شد.', 'info');
        }).catch(function (err) { AP.toast(err.message, 'error'); });
      }
    });

    document.addEventListener('ap:cart', updateCounts);
    document.addEventListener('ap:favs', function () { updateCounts(); updateFavButtons(); });
    window.addEventListener('storage', function (e) { if (e.key === 'ap_favs') u.emit('ap:favs', {}); });

    document.addEventListener('error', function (e) {
      var t = e.target;
      if (t && t.tagName === 'IMG' && !t.dataset.fallback) {
        t.dataset.fallback = '1';
        t.src = 'assets/images/ui/placeholder.svg';
      }
    }, true);

    // Newsletter forms anywhere on the page (home section, blog sidebar)
    document.addEventListener('submit', function (e) {
      var form = e.target.closest('[data-newsletter]');
      if (!form) return;
      e.preventDefault();
      var msg = form.querySelector('.newsletter__msg');
      var email = form.elements.email.value.trim();
      if (!u.isEmail(email)) {
        msg.textContent = 'آدرس ایمیل معتبر نیست؛ نمونه درست: name@example.com';
        msg.className = 'newsletter__msg is-error';
        return;
      }
      AP.api.post('newsletter', { email: email }).then(function () {
        msg.textContent = 'عضویت شما با موفقیت انجام شد.';
        msg.className = 'newsletter__msg is-success';
        form.reset();
      }).catch(function (err) {
        msg.textContent = err.message;
        msg.className = 'newsletter__msg is-error';
      });
    });
  }

  function bindDrawer() {
    var drawer = document.getElementById('mobile-drawer');
    var openBtn = u.$('[data-drawer-open]');
    var lastFocus = null;
    function open() {
      lastFocus = document.activeElement;
      drawer.hidden = false;
      requestAnimationFrame(function () { drawer.classList.add('is-open'); });
      openBtn.setAttribute('aria-expanded', 'true');
      document.body.classList.add('no-scroll');
      drawer.querySelector('.icon-btn[data-drawer-close]').focus();
    }
    function close() {
      drawer.classList.remove('is-open');
      openBtn.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('no-scroll');
      setTimeout(function () { drawer.hidden = true; }, 250);
      if (lastFocus) lastFocus.focus();
    }
    openBtn.addEventListener('click', open);
    drawer.addEventListener('click', function (e) {
      if (e.target.closest('[data-drawer-close]')) close();
      var toggle = e.target.closest('.drawer__toggle');
      if (toggle) {
        var sub = document.getElementById(toggle.getAttribute('aria-controls'));
        var expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!expanded));
        sub.hidden = expanded;
      }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !drawer.hidden) close(); });
  }

  function bindMegaMenu() {
    u.$$('.mainnav__item.has-mega').forEach(function (item) {
      var a = item.querySelector('.mainnav__link');
      function set(open) { item.classList.toggle('is-open', open); a.setAttribute('aria-expanded', String(open)); }
      set(false);
      item.addEventListener('mouseenter', function () { set(true); });
      item.addEventListener('mouseleave', function () { set(false); });
      item.addEventListener('focusin', function () { set(true); });
      item.addEventListener('focusout', function (e) { if (!item.contains(e.relatedTarget)) set(false); });
      item.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { set(false); a.focus(); }
        if (e.key === 'ArrowDown' && document.activeElement === a) {
          e.preventDefault();
          set(true);
          var first = item.querySelector('.mega a');
          if (first) first.focus();
        }
      });
    });
  }

  function markActiveNav() {
    var base = (boot.base || '/').replace(/\/$/, '');
    var here = location.pathname.replace(base, '').replace(/^\//, '') + location.search;
    u.$$('.mainnav__link').forEach(function (a) {
      if (a.getAttribute('href') === here) a.setAttribute('aria-current', 'page');
    });
  }

  function init() {
    var header = document.getElementById('site-header');
    var footer = document.getElementById('site-footer');
    if (header) header.innerHTML = headerHTML();
    if (footer) footer.innerHTML = footerHTML();
    document.body.insertAdjacentHTML('beforeend', drawerHTML() + bottomNavHTML() + '<div class="toast-region" role="status" aria-live="polite"></div>');
    toastRegion = u.$('.toast-region');
    var search = u.$('.header-search');
    if (search) {
      AP.bindLiveSearch(search);
      var q = u.params().get('q');
      if (q && document.body.dataset.page === 'search') search.elements.q.value = q;
    }
    bindDrawer();
    bindMegaMenu();
    markActiveNav();
    bindGlobalEvents();
    updateCounts();
    updateFavButtons();
    AP.initCarousels && AP.initCarousels();
  }

  try {
    init();
  } catch (err) {
    if (window.console) console.error('AutoPart: failed to build the page shell', err);
  }
})();
