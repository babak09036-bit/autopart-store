/* Shared shell: header, main menu, mobile drawer, bottom nav, footer, toasts and the product card. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var icon = u.icon;

  /* ---------------- Navigation config ---------------- */
  var NAV = [
    { label: 'صفحه اصلی', href: 'index.html' },
    { label: 'لوازم مصرفی', href: 'products.html?cat=engine-oil,filters', image: 'assets/images/categories/maintenance.svg', links: [
      ['روغن موتور', 'search.html?q=روغن موتور'], ['روغن گیربکس', 'search.html?q=روغن گیربکس'], ['فیلتر روغن', 'search.html?q=فیلتر روغن'],
      ['فیلتر هوا', 'search.html?q=فیلتر هوا'], ['فیلتر کابین', 'search.html?q=فیلتر کابین'], ['ضدیخ و ضدجوش', 'search.html?q=ضدیخ'],
      ['روغن ترمز', 'search.html?q=روغن ترمز'], ['همه فیلترها', 'category.html?cat=filters']] },
    { label: 'لوازم جانبی', href: 'products.html?cat=interior,decor,travel', image: 'assets/images/categories/camping.svg', links: [
      ['لوازم داخلی', 'category.html?cat=interior'], ['لوازم تزئینی', 'category.html?cat=decor'], ['تجهیزات سفر', 'category.html?cat=travel'],
      ['کف‌پوش', 'search.html?q=کفپوش'], ['روکش صندلی', 'search.html?q=روکش صندلی'], ['نظم‌دهنده', 'search.html?q=نظم‌دهنده'],
      ['خوشبوکننده', 'search.html?q=خوشبوکننده'], ['روکش فرمان', 'search.html?q=روکش فرمان']] },
    { label: 'تجهیزات الکترونیکی', href: 'category.html?cat=electronics', image: 'assets/images/categories/electronics-big.svg', links: [
      ['دوربین ثبت وقایع', 'search.html?q=دوربین ثبت وقایع'], ['دوربین دنده عقب', 'search.html?q=دوربین دنده عقب'], ['شارژر فندکی', 'search.html?q=شارژر فندکی'],
      ['نگهدارنده موبایل', 'search.html?q=نگهدارنده موبایل'], ['پخش بلوتوث', 'search.html?q=بلوتوث'], ['ردیاب خودرو', 'search.html?q=ردیاب'],
      ['مانیتور خودرو', 'search.html?q=مانیتور'], ['سنسور پارک', 'search.html?q=سنسور پارک']] },
    { label: 'نظافت خودرو', href: 'category.html?cat=cleaning', image: 'assets/images/categories/beauty.svg', links: [
      ['شامپو خودرو', 'search.html?q=شامپو'], ['واکس بدنه', 'search.html?q=واکس'], ['پولیش', 'search.html?q=پولیش'],
      ['دستمال مایکروفایبر', 'search.html?q=مایکروفایبر'], ['جاروبرقی خودرو', 'search.html?q=جاروبرقی'], ['اسپری و فوم', 'search.html?q=اسپری'],
      ['کیت شست‌وشو', 'search.html?q=کیت شست‌وشو'], ['برس نظافت', 'search.html?q=برس']] },
    { label: 'ابزار خودرو', href: 'category.html?cat=tools', image: 'assets/images/categories/workshop.svg', links: [
      ['جعبه ابزار', 'search.html?q=جعبه ابزار'], ['مجموعه آچار', 'search.html?q=آچار'], ['کیف ابزار', 'search.html?q=کیف ابزار'],
      ['تستر باتری', 'search.html?q=تستر باتری'], ['کمپرسور باد', 'search.html?q=کمپرسور'], ['کابل باتری', 'search.html?q=کابل باتری'],
      ['چراغ کار', 'search.html?q=پروژکتور'], ['جامپ استارتر', 'search.html?q=جامپ استارتر']] },
    { label: 'تجهیزات ایمنی', href: 'products.html?cat=safety,camera', image: 'assets/images/categories/security.svg', links: [
      ['کپسول آتش‌نشانی', 'search.html?q=کپسول'], ['کمک‌های اولیه', 'search.html?q=کمک‌های اولیه'], ['ردیاب خودرو', 'search.html?q=ردیاب'],
      ['سنسور پارک', 'search.html?q=سنسور پارک'], ['دوربین دنده عقب', 'search.html?q=دوربین دنده عقب'], ['همه تجهیزات ایمنی', 'category.html?cat=safety']] },
    { label: 'روغن و روانکارها', href: 'category.html?cat=engine-oil', image: 'assets/images/categories/engine-oil.svg', links: [
      ['روغن موتور', 'search.html?q=روغن موتور'], ['روغن گیربکس', 'search.html?q=روغن گیربکس'], ['روغن ترمز', 'search.html?q=روغن ترمز'], ['ضدیخ', 'search.html?q=ضدیخ']] },
    { label: 'تخفیف‌ها', href: 'products.html?discount=1', accent: true },
    { label: 'راهنمای خرید', href: 'index.html#guides' }
  ];

  var FOOTER_CATS = ['engine-oil', 'filters', 'cleaning', 'electronics', 'tools', 'safety'];

  /* ---------------- Header ---------------- */
  function headerHTML() {
    var user = u.store.get('ap_user', null);
    var navItems = NAV.map(function (item, i) {
      var id = 'mega-' + i;
      var hasMenu = !!item.links;
      return '<li class="mainnav__item' + (hasMenu ? ' has-mega' : '') + '">' +
        '<a class="mainnav__link' + (item.accent ? ' mainnav__link--accent' : '') + '" href="' + item.href + '"' +
        (hasMenu ? ' aria-haspopup="true" aria-controls="' + id + '"' : '') + '>' +
        (item.accent ? icon('discount', 'icon--sm') : '') + item.label + (hasMenu ? icon('chevron-down', 'icon--sm') : '') + '</a>' +
        (hasMenu ? '<div class="mega" id="' + id + '"><div class="mega__inner">' +
          '<div class="mega__links"><a class="mega__all" href="' + item.href + '">همه محصولات ' + item.label + icon('chevron-left', 'icon--sm') + '</a><ul>' +
          item.links.map(function (l) { return '<li><a href="' + l[1] + '">' + l[0] + '</a></li>'; }).join('') +
          '</ul></div><a class="mega__promo" href="' + item.href + '" tabindex="-1"><img src="' + item.image + '" alt="" width="600" height="360" loading="lazy"></a></div></div>' : '') +
        '</li>';
    }).join('');

    return '<div class="topbar"><div class="container topbar__inner">' +
      '<span>' + icon('truck', 'icon--sm') + 'ارسال سریع به سراسر کشور</span>' +
      '<span>' + icon('shield', 'icon--sm') + 'ضمانت اصالت کالا</span>' +
      '<span>' + icon('headset', 'icon--sm') + 'پشتیبانی تخصصی خودرو</span>' +
      '<a href="products.html?discount=1">' + icon('flash', 'icon--sm') + 'پیشنهادهای ویژه این هفته</a>' +
      '</div></div>' +
      '<div class="header-main"><div class="container header-main__inner">' +
      '<button type="button" class="icon-btn header-main__menu" aria-label="باز کردن منو" aria-controls="mobile-drawer" aria-expanded="false" data-drawer-open>' + icon('menu') + '</button>' +
      '<a class="header-main__logo" href="index.html"><img src="assets/images/ui/logo.svg" alt="اتوپارت؛ فروشگاه لوازم جانبی خودرو" width="176" height="45"></a>' +
      '<form class="header-search" action="search.html" method="get" role="search">' +
      '<label class="visually-hidden" for="header-q">جستجوی محصولات</label>' +
      '<input id="header-q" name="q" type="search" placeholder="جستجو در لوازم جانبی و قطعات خودرو…" autocomplete="off" aria-autocomplete="list" aria-controls="search-suggest" aria-expanded="false" enterkeyhint="search">' +
      '<button type="submit" class="header-search__btn" aria-label="جستجو">' + icon('search') + '</button>' +
      '<div class="search-suggest" id="search-suggest" aria-label="پیشنهادهای جستجو" hidden></div>' +
      '</form>' +
      '<div class="header-actions">' +
      '<a class="header-action header-action--account" href="login.html">' + icon('user') + '<span>' + (user && user.name ? u.escape(user.name) : 'ورود | ثبت‌نام') + '</span></a>' +
      '<a class="header-action" href="favorites.html" aria-label="علاقه‌مندی‌ها">' + icon('heart') + '<span class="count-badge" data-count="favs" hidden>۰</span></a>' +
      '<a class="header-action header-action--cart" href="cart.html" aria-label="سبد خرید">' + icon('cart') + '<span class="count-badge" data-count="cart" hidden>۰</span></a>' +
      '</div></div></div>' +
      '<nav class="mainnav" aria-label="منوی اصلی"><div class="container"><ul class="mainnav__list">' + navItems + '</ul></div></nav>';
  }

  function drawerHTML() {
    var items = NAV.map(function (item, i) {
      if (!item.links) return '<li><a class="drawer__link" href="' + item.href + '">' + item.label + '</a></li>';
      var id = 'drawer-sub-' + i;
      return '<li><button type="button" class="drawer__link drawer__toggle" aria-expanded="false" aria-controls="' + id + '">' + item.label + icon('chevron-down', 'icon--sm') + '</button>' +
        '<ul class="drawer__sub" id="' + id + '" hidden><li><a href="' + item.href + '">همه محصولات ' + item.label + '</a></li>' +
        item.links.map(function (l) { return '<li><a href="' + l[1] + '">' + l[0] + '</a></li>'; }).join('') + '</ul></li>';
    }).join('');
    return '<div class="drawer" id="mobile-drawer" hidden>' +
      '<div class="drawer__overlay" data-drawer-close></div>' +
      '<div class="drawer__panel" role="dialog" aria-modal="true" aria-label="منوی دسته‌بندی‌ها">' +
      '<div class="drawer__head"><img src="assets/images/ui/logo.svg" alt="اتوپارت" width="140" height="36">' +
      '<button type="button" class="icon-btn" aria-label="بستن منو" data-drawer-close>' + icon('close') + '</button></div>' +
      '<nav aria-label="منوی موبایل"><ul class="drawer__list">' + items + '</ul></nav>' +
      '<div class="drawer__foot"><a href="tracking.html">' + icon('package-track', 'icon--sm') + 'پیگیری سفارش</a><a href="contact.html">' + icon('headset', 'icon--sm') + 'تماس با ما</a></div>' +
      '</div></div>';
  }

  function bottomNavHTML() {
    var page = document.body.dataset.page;
    var items = [
      ['home', 'خانه', 'index.html', 'home'],
      ['grid', 'دسته‌ها', 'products.html', 'products'],
      ['cart', 'سبد خرید', 'cart.html', 'cart'],
      ['heart', 'علاقه‌مندی', 'favorites.html', 'favorites'],
      ['user', 'حساب', 'login.html', 'login']
    ];
    return '<nav class="bottom-nav" aria-label="ناوبری پایین صفحه">' + items.map(function (it) {
      var count = it[3] === 'cart' ? '<span class="count-badge" data-count="cart" hidden>۰</span>' :
        it[3] === 'favorites' ? '<span class="count-badge" data-count="favs" hidden>۰</span>' : '';
      return '<a href="' + it[2] + '"' + (page === it[3] ? ' aria-current="page"' : '') + '>' + icon(it[0]) + count + '<span>' + it[1] + '</span></a>';
    }).join('') + '</nav>';
  }

  /* ---------------- Footer ---------------- */
  function footerHTML() {
    var features = [
      ['shield', 'ضمانت اصالت کالا'], ['truck', 'ارسال سریع'], ['headset', 'پشتیبانی تخصصی'], ['return', '۷ روز ضمانت بازگشت'], ['card', 'پرداخت امن']
    ];
    var cats = FOOTER_CATS.map(function (slug) {
      var c = AP.catalog.category(slug);
      return '<li><a href="category.html?cat=' + slug + '">' + c.short + '</a></li>';
    }).join('');
    return '<div class="footer-features"><div class="container footer-features__inner">' +
      features.map(function (f) { return '<div class="footer-feature">' + icon(f[0], 'icon--lg') + '<span>' + f[1] + '</span></div>'; }).join('') +
      '</div></div>' +
      '<div class="container footer-grid">' +
      '<div class="footer-col footer-col--about"><img src="assets/images/ui/logo.svg" alt="اتوپارت" width="176" height="45" loading="lazy">' +
      '<p>اتوپارت فروشگاه اینترنتی تخصصی لوازم جانبی، قطعات مصرفی و تجهیزات نگهداری خودرو است. همه کالاها با ضمانت اصالت و مشاوره رایگان کارشناسان خودرو عرضه می‌شوند.</p>' +
      '<div class="trust"><img src="assets/images/ui/trust-1.svg" alt="نماد اعتماد نمونه" width="72" height="72" loading="lazy"><img src="assets/images/ui/trust-2.svg" alt="نشان امنیت پرداخت نمونه" width="72" height="72" loading="lazy"></div></div>' +
      '<div class="footer-col"><h2 class="footer-col__title">راهنمای خرید</h2><ul>' +
      '<li><a href="contact.html#faq">نحوه ثبت سفارش</a></li><li><a href="contact.html#faq">شیوه‌های پرداخت</a></li><li><a href="contact.html#faq">شرایط ارسال</a></li>' +
      '<li><a href="index.html#guides">راهنمای انتخاب کالا</a></li><li><a href="tracking.html">پیگیری سفارش</a></li></ul></div>' +
      '<div class="footer-col"><h2 class="footer-col__title">خدمات مشتریان</h2><ul>' +
      '<li><a href="contact.html#faq">پرسش‌های متداول</a></li><li><a href="contact.html#returns">رویه بازگرداندن کالا</a></li>' +
      '<li><a href="contact.html#terms">قوانین و مقررات</a></li><li><a href="contact.html#privacy">حریم خصوصی</a></li><li><a href="contact.html">تماس با ما</a></li></ul></div>' +
      '<div class="footer-col"><h2 class="footer-col__title">دسته‌بندی محصولات</h2><ul>' + cats + '</ul></div>' +
      '<div class="footer-col footer-col--contact"><h2 class="footer-col__title">تماس با ما</h2><ul>' +
      '<li>' + icon('phone', 'icon--sm') + '<a href="tel:02100000000" dir="ltr">۰۲۱-۰۰۰۰۰۰۰۰</a></li>' +
      '<li>' + icon('mail', 'icon--sm') + '<a href="mailto:info@autopart.example" dir="ltr">info@autopart.example</a></li>' +
      '<li>' + icon('location', 'icon--sm') + '<span>تهران، خیابان نمونه، پلاک ۰</span></li>' +
      '<li>' + icon('clock', 'icon--sm') + '<span>شنبه تا پنجشنبه، ۹ تا ۱۸</span></li></ul>' +
      '<h2 class="footer-col__title footer-col__title--social">ما را دنبال کنید</h2><div class="socials">' +
      [['instagram', 'اینستاگرام'], ['telegram', 'تلگرام'], ['whatsapp', 'واتس‌اپ'], ['linkedin', 'لینکدین'], ['x-social', 'ایکس']].map(function (s) {
        return '<a class="social" href="contact.html#social" aria-label="' + s[1] + ' اتوپارت">' + icon(s[0]) + '</a>';
      }).join('') + '</div></div></div>' +
      '<div class="footer-bottom"><div class="container footer-bottom__inner">' +
      '<p>این وب‌سایت یک فروشگاه نمونه است و اطلاعات آن نمایشی است. © ' + u.faDigits(1405) + ' اتوپارت</p>' +
      '<nav aria-label="پیوندهای حقوقی"><a href="contact.html#terms">قوانین و مقررات</a><a href="contact.html#privacy">حریم خصوصی</a></nav>' +
      '</div></div>';
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
    }, 3200);
  };

  /* ---------------- Product card ---------------- */
  function priceHTML(p) {
    var off = u.discount(p);
    return '<div class="price">' +
      (off ? '<div class="price__old"><span class="visually-hidden">قیمت قبلی:</span><del>' + u.num(p.oldPrice) + '</del><span class="badge badge--red">' + u.num(off) + '٪</span></div>'
        : '<div class="price__old" aria-hidden="true"></div>') +
      '<div class="price__now"><strong>' + u.num(p.price) + '</strong><span>تومان</span></div></div>';
  }

  function stockHTML(p) {
    if (p.stock < 1) return '<span class="stock stock--out">ناموجود</span>';
    if (p.stock <= 10) return '<span class="stock stock--low">تنها ' + u.num(p.stock) + ' عدد در انبار</span>';
    return '<span class="stock stock--in">' + icon('check', 'icon--sm') + 'موجود در انبار</span>';
  }

  function favHTML(p) {
    var on = AP.favs.has(p.id);
    return '<button type="button" class="fav-btn" data-fav="' + p.id + '" aria-pressed="' + on + '" aria-label="' +
      (on ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها') + '">' + icon(on ? 'heart-filled' : 'heart') + '</button>';
  }

  function addHTML(p, compact) {
    if (p.stock < 1) return '<button type="button" class="btn btn--block btn--sm" disabled>ناموجود</button>';
    if (compact) return '<button type="button" class="icon-btn add-mini" data-add="' + p.id + '" aria-label="افزودن ' + u.escape(p.title) + ' به سبد خرید">' + icon('plus') + '</button>';
    return '<button type="button" class="btn btn--primary btn--block btn--sm" data-add="' + p.id + '" aria-label="افزودن ' + u.escape(p.title) + ' به سبد خرید">' + icon('cart', 'icon--sm') + '<span>افزودن به سبد</span></button>';
  }

  function img(p, size) {
    return '<img src="' + p.image + '" alt="' + u.escape(p.title) + '" width="' + size + '" height="' + size + '" loading="lazy" decoding="async">';
  }

  /**
   * Renders a product card. variant: default | amazing | compact | ranked
   * opts.rank (ranked), opts.action ('details' swaps the cart button for a details link), opts.extra (HTML appended to body)
   */
  AP.card = function (p, variant, opts) {
    variant = variant || 'default';
    opts = opts || {};
    var url = 'product-details.html?id=' + p.id;
    var title = '<h3 class="pcard__title"><a href="' + url + '">' + u.escape(p.title) + '</a></h3>';
    var brand = '<a class="pcard__brand" href="products.html?brand=' + p.brand + '">' + AP.catalog.brandName(p.brand) + '</a>';
    var rating = '<div class="pcard__rating">' + icon('star-filled', 'icon--sm') + '<span>' + u.faDigits(p.rating.toFixed(1)) + '</span><span class="pcard__reviews">(' + u.num(p.reviewsCount) + ' نظر)</span></div>';

    if (variant === 'ranked') {
      return '<article class="pcard pcard--ranked">' +
        '<a class="pcard__media" href="' + url + '" tabindex="-1" aria-hidden="true">' + img(p, 88) + '</a>' +
        '<span class="pcard__rank" aria-label="رتبه ' + u.faDigits(opts.rank) + '">' + u.faDigits(opts.rank) + '</span>' +
        '<div class="pcard__body">' + title + rating + '<div class="price__now"><strong>' + u.num(p.price) + '</strong><span>تومان</span></div></div></article>';
    }

    if (variant === 'compact') {
      return '<article class="pcard pcard--compact">' +
        '<a class="pcard__media" href="' + url + '" tabindex="-1" aria-hidden="true">' + img(p, 110) + '</a>' +
        '<div class="pcard__body">' + brand + title + stockHTML(p) +
        '<div class="pcard__foot">' + priceHTML(p) + addHTML(p, true) + '</div></div></article>';
    }

    var badges = '';
    if (variant !== 'amazing') {
      if (p.isNew) badges += '<span class="badge badge--new">جدید</span>';
      if (opts.action === 'details' && p.isExpertPick) badges += '<span class="badge badge--expert">' + icon('award', 'icon--sm') + 'منتخب کارشناس</span>';
    } else {
      badges += '<span class="badge badge--soft">' + icon('flash', 'icon--sm') + 'شگفت‌انگیز</span>';
    }

    var stock = stockHTML(p);
    if (variant === 'amazing') {
      var sold = 20 + (p.reviewsCount % 50);
      var pct = Math.max(8, Math.round(p.stock / (p.stock + sold) * 100));
      stock = '<div class="stockbar"><div class="stockbar__label"><span>باقی‌مانده</span><strong>' + u.num(p.stock) + ' عدد</strong></div>' +
        '<div class="stockbar__track" role="progressbar" aria-label="موجودی باقی‌مانده" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + pct + '"><span style="width:' + pct + '%"></span></div></div>';
    }

    var action = opts.action === 'details'
      ? '<a class="btn btn--ghost btn--block btn--sm" href="' + url + '">' + icon('eye', 'icon--sm') + 'مشاهده جزئیات</a>'
      : addHTML(p);

    return '<article class="pcard pcard--' + variant + '">' +
      '<div class="pcard__media"><a href="' + url + '" tabindex="-1" aria-hidden="true">' + img(p, 400) + '</a>' +
      '<div class="pcard__badges">' + badges + '</div>' + favHTML(p) + '</div>' +
      '<div class="pcard__body">' + brand + title + rating + stock + priceHTML(p) + action + (opts.extra || '') + '</div></article>';
  };

  AP.cards = function (list, variant, opts) {
    return list.map(function (p, i) {
      return AP.card(p, variant, Object.assign({ rank: i + 1 }, opts || {}));
    }).join('');
  };

  /* ---------------- Live state ---------------- */
  function updateCounts() {
    var map = { cart: AP.cart.count(), favs: AP.favs.count() };
    u.$$('[data-count]').forEach(function (el) {
      var n = map[el.dataset.count];
      el.textContent = u.num(n);
      el.hidden = n === 0;
    });
    u.$$('.header-action--cart').forEach(function (a) { a.setAttribute('aria-label', 'سبد خرید، ' + u.num(map.cart) + ' کالا'); });
  }

  function updateFavButtons() {
    u.$$('[data-fav]').forEach(function (btn) {
      var on = AP.favs.has(btn.dataset.fav);
      btn.setAttribute('aria-pressed', String(on));
      btn.setAttribute('aria-label', on ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها');
      var use = btn.querySelector('use');
      if (use) use.setAttribute('href', 'assets/icons/icons.svg#i-' + (on ? 'heart-filled' : 'heart'));
    });
  }

  function bindGlobalEvents() {
    document.addEventListener('click', function (e) {
      var add = e.target.closest('[data-add]');
      if (add) {
        var qtyInput = add.dataset.qtyFrom ? document.getElementById(add.dataset.qtyFrom) : null;
        var res = AP.cart.add(add.dataset.add, qtyInput ? Number(u.enDigits(qtyInput.value)) : 1);
        AP.toast(res.message, res.ok ? 'success' : 'error');
        if (res.ok) {
          add.classList.add('is-added');
          setTimeout(function () { add.classList.remove('is-added'); }, 700);
        }
        return;
      }
      var fav = e.target.closest('[data-fav]');
      if (fav) {
        var nowFav = AP.favs.toggle(fav.dataset.fav);
        AP.toast(nowFav ? 'به علاقه‌مندی‌ها اضافه شد.' : 'از علاقه‌مندی‌ها حذف شد.', 'info');
      }
    });

    document.addEventListener('ap:cart', updateCounts);
    document.addEventListener('ap:favs', function () { updateCounts(); updateFavButtons(); });

    /* Keep other open tabs in sync. */
    window.addEventListener('storage', function (e) {
      if (e.key === 'ap_cart') u.emit('ap:cart', {});
      if (e.key === 'ap_favs') u.emit('ap:favs', {});
    });

    /* Missing or broken image -> local placeholder. */
    document.addEventListener('error', function (e) {
      var t = e.target;
      if (t && t.tagName === 'IMG' && !t.dataset.fallback) {
        t.dataset.fallback = '1';
        t.src = 'assets/images/ui/placeholder.svg';
      }
    }, true);
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
      drawer.querySelector('[data-drawer-close].icon-btn').focus();
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
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !drawer.hidden) close();
    });
  }

  function bindMegaMenu() {
    u.$$('.mainnav__item.has-mega').forEach(function (item) {
      var link = item.querySelector('.mainnav__link');
      function set(open) {
        item.classList.toggle('is-open', open);
        link.setAttribute('aria-expanded', String(open));
      }
      set(false);
      item.addEventListener('mouseenter', function () { set(true); });
      item.addEventListener('mouseleave', function () { set(false); });
      item.addEventListener('focusin', function () { set(true); });
      item.addEventListener('focusout', function (e) { if (!item.contains(e.relatedTarget)) set(false); });
      item.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { set(false); link.focus(); }
        if (e.key === 'ArrowDown' && document.activeElement === link) {
          e.preventDefault();
          set(true);
          var first = item.querySelector('.mega a');
          if (first) first.focus();
        }
      });
    });
  }

  function markActiveNav() {
    var here = location.pathname.split('/').pop() + location.search;
    u.$$('.mainnav__link').forEach(function (a) {
      var href = a.getAttribute('href');
      if (href === here || (href === 'index.html' && (here === '' || here === 'index.html'))) a.setAttribute('aria-current', 'page');
    });
  }

  /* ---------------- Boot ---------------- */
  function init() {
    var header = document.getElementById('site-header');
    var footer = document.getElementById('site-footer');
    if (header) header.innerHTML = headerHTML();
    if (footer) footer.innerHTML = footerHTML();
    document.body.insertAdjacentHTML('beforeend', drawerHTML() + bottomNavHTML() +
      '<div class="toast-region" role="status" aria-live="polite"></div>');
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
  }

  try {
    init();
  } catch (err) {
    console.error('AutoPart: failed to build the page shell', err);
  }
})();
