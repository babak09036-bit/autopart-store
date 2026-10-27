/* Product details page. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var root = document.getElementById('product-root');
  var product = AP.catalog.product(u.params().get('id'));

  if (!product) {
    document.title = 'محصول یافت نشد | اتوپارت';
    root.innerHTML = '<div class="empty-state empty-state--panel">' +
      '<img src="assets/images/ui/no-results.svg" alt="" width="320" height="240">' +
      '<h1>محصول یافت نشد</h1><p>ممکن است نشانی اشتباه باشد یا این کالا از فروشگاه حذف شده باشد.</p>' +
      '<a class="btn btn--primary" href="products.html">بازگشت به فهرست محصولات</a></div>';
    return;
  }

  var p = product;
  var cat = AP.catalog.category(p.category);
  var brandName = AP.catalog.brandName(p.brand);
  var off = u.discount(p);
  document.title = p.title + ' | اتوپارت';

  document.getElementById('breadcrumb').insertAdjacentHTML('beforeend',
    '<li><a href="category.html?cat=' + cat.slug + '">' + cat.short + '</a></li><li aria-current="page">' + u.escape(p.title) + '</li>');

  /* Sample reviews, picked deterministically from a pool so each product is stable. */
  var POOL = [
    ['رضا ک.', 5, 'کیفیت ساخت عالی است و دقیقاً مطابق توضیحات بود. ارسال هم سریع انجام شد.'],
    ['الهام ن.', 4, 'برای این قیمت خیلی خوب است. فقط بسته‌بندی می‌توانست محکم‌تر باشد.'],
    ['حسین ت.', 5, 'دومین بار است که از اتوپارت خرید می‌کنم؛ اصالت کالا را استعلام کردم و اصل بود.'],
    ['فاطمه ج.', 4, 'کارشناس پشتیبانی برای انتخاب مدل درست راهنمایی خوبی کرد.'],
    ['امیر س.', 3, 'کار راه‌انداز است ولی انتظار داشتم کمی قوی‌تر باشد.'],
    ['زهرا ب.', 5, 'نصبش ساده بود و در سفر خیلی به کارم آمد. پیشنهاد می‌کنم.']
  ];
  var reviews = [0, 1, 2].map(function (k) { return POOL[(p.id + k * 2) % POOL.length]; });

  var specsList = Object.keys(p.specs).slice(0, 4).map(function (k) {
    return '<li><span>' + k + ':</span> ' + p.specs[k] + '</li>';
  }).join('');

  var compat;
  if (p.compatibleVehicles === 'universal') {
    compat = '<div class="compat"><strong>سازگاری</strong>' + u.icon('check', 'icon--sm') + ' مناسب همه خودروهای سواری</div>';
  } else {
    compat = '<div class="compat"><strong>خودروهای سازگار</strong>' + p.compatibleVehicles.map(function (m) {
      var found = AP.catalog.model(m);
      return '<a class="chip" href="products.html?vehicle=' + m + '">' + found.model.name + '</a>';
    }).join('') + '</div>';
  }

  var stock = p.stock < 1 ? '<span class="stock stock--out">ناموجود</span>'
    : p.stock <= 10 ? '<span class="stock stock--low">تنها ' + u.num(p.stock) + ' عدد در انبار باقی مانده</span>'
      : '<span class="stock stock--in">' + u.icon('check', 'icon--sm') + 'موجود در انبار اتوپارت</span>';

  var fav = AP.favs.has(p.id);

  root.innerHTML =
    '<article class="product">' +
    '<div class="gallery">' +
    '<div class="gallery__main" id="gallery-main"><img id="gallery-img" src="' + p.image + '" alt="' + u.escape(p.title) + '" width="400" height="400"></div>' +
    '<div class="gallery__thumbs" role="group" aria-label="نماهای تصویر">' +
    [['', 'نمای اصلی'], ['view-flip', 'نمای قرینه'], ['view-close', 'نمای نزدیک']].map(function (v, i) {
      return '<button type="button" class="gallery__thumb ' + v[0] + '" data-view="' + v[0] + '" aria-pressed="' + (i === 0) + '" aria-label="' + v[1] + '">' +
        '<img src="' + p.image + '" alt="" width="72" height="72"></button>';
    }).join('') + '</div></div>' +

    '<div class="product__info">' +
    '<a class="product__brand" href="products.html?brand=' + p.brand + '">' + brandName + '</a>' +
    '<h1>' + u.escape(p.title) + '</h1>' +
    '<div class="product__meta">' + u.stars(p.rating) + '<span>' + u.faDigits(p.rating.toFixed(1)) + ' از ' + u.num(p.reviewsCount) + ' نظر</span>' +
    '<span>کد کالا: AP-' + u.faDigits(String(p.id).padStart(4, '0')) + '</span>' +
    '<a href="category.html?cat=' + cat.slug + '">' + cat.short + '</a>' +
    (p.isNew ? '<span class="badge badge--new">جدید</span>' : '') +
    (p.isExpertPick ? '<span class="badge badge--expert">' + u.icon('award', 'icon--sm') + 'منتخب کارشناس</span>' : '') + '</div>' +
    '<p>' + u.escape(p.description) + '</p>' +
    '<h2 class="visually-hidden">ویژگی‌های اصلی</h2><ul class="spec-list">' + specsList + '</ul>' + compat +
    '</div>' +

    '<aside class="buybox" aria-label="خرید">' +
    stock +
    '<div class="price">' + (off ? '<div class="price__old"><span class="visually-hidden">قیمت قبلی:</span><del>' + u.num(p.oldPrice) + '</del><span class="badge badge--red">' + u.num(off) + '٪ تخفیف</span></div>' : '') +
    '<div class="price__now"><strong>' + u.num(p.price) + '</strong><span>تومان</span></div></div>' +
    (p.stock > 0 ? '<div class="buybox__qty"><span>تعداد:</span><div class="qty">' +
      '<button type="button" data-step="1" aria-label="افزایش تعداد">' + u.icon('plus', 'icon--sm') + '</button>' +
      '<input id="qty" type="text" inputmode="numeric" value="۱" aria-label="تعداد">' +
      '<button type="button" data-step="-1" aria-label="کاهش تعداد">' + u.icon('minus', 'icon--sm') + '</button></div></div>' +
      '<button type="button" class="btn btn--primary btn--block" data-add="' + p.id + '" data-qty-from="qty">' + u.icon('cart', 'icon--sm') + 'افزودن به سبد خرید</button>'
      : '<button type="button" class="btn btn--block" disabled>ناموجود</button>') +
    '<button type="button" class="btn btn--light btn--block" data-fav="' + p.id + '" aria-pressed="' + fav + '">' + u.icon(fav ? 'heart-filled' : 'heart', 'icon--sm') + '<span>علاقه‌مندی</span></button>' +
    '<div class="buybox__row">' + u.icon('truck') + 'ارسال از ۱ روز کاری؛ رایگان بالای ' + u.num(AP.cart.FREE_SHIPPING_FROM) + ' تومان</div>' +
    '<div class="buybox__row">' + u.icon('shield') + 'ضمانت اصالت و سلامت فیزیکی کالا</div>' +
    '<div class="buybox__row">' + u.icon('return') + '۷ روز ضمانت بازگشت طبق شرایط</div>' +
    '</aside></article>' +

    '<section class="tabs-panel" aria-label="اطلاعات تکمیلی">' +
    '<div class="tabs-panel__list" role="tablist">' +
    [['desc', 'توضیحات'], ['specs', 'مشخصات فنی'], ['reviews', 'نظرات (' + u.num(p.reviewsCount) + ')']].map(function (t, i) {
      return '<button type="button" class="tabs-panel__tab" role="tab" id="tab-' + t[0] + '" aria-controls="panel-' + t[0] + '" aria-selected="' + (i === 0) + '" tabindex="' + (i === 0 ? 0 : -1) + '">' + t[1] + '</button>';
    }).join('') + '</div>' +
    '<div class="tabs-panel__body" role="tabpanel" id="panel-desc" aria-labelledby="tab-desc"><p>' + u.escape(p.description) + '</p>' +
    '<p>' + u.escape(p.title) + ' از برند ' + brandName + ' در دسته ' + cat.name + ' عرضه می‌شود. پیش از خرید، بخش خودروهای سازگار و مشخصات فنی را بررسی کنید و در صورت نیاز با کارشناسان اتوپارت مشورت کنید.</p></div>' +
    '<div class="tabs-panel__body" role="tabpanel" id="panel-specs" aria-labelledby="tab-specs" hidden><table class="spec-table"><tbody>' +
    '<tr><th scope="row">برند</th><td>' + brandName + '</td></tr>' +
    Object.keys(p.specs).map(function (k) { return '<tr><th scope="row">' + k + '</th><td>' + p.specs[k] + '</td></tr>'; }).join('') +
    '</tbody></table></div>' +
    '<div class="tabs-panel__body" role="tabpanel" id="panel-reviews" aria-labelledby="tab-reviews" hidden><div class="review-list">' +
    reviews.map(function (r) {
      return '<div class="review-item"><div class="review-item__head"><strong>' + r[0] + '</strong>' + u.stars(r[1]) + '</div><p>' + r[2] + '</p></div>';
    }).join('') + '<p class="field__hint">نظرات بالا نمونه و نمایشی هستند.</p></div></div>' +
    '</section>' +

    '<section class="section" aria-labelledby="related-title"><div class="section-head"><h2 id="related-title" class="section-head__title">محصولات مرتبط</h2>' +
    '<a class="link-more" href="category.html?cat=' + cat.slug + '">مشاهده همه' + u.icon('chevron-left') + '</a></div>' +
    '<div class="carousel" data-carousel>' +
    '<button type="button" class="carousel__btn carousel__btn--prev" data-prev aria-label="قبلی">' + u.icon('chevron-right') + '</button>' +
    '<div class="carousel__track" id="related"></div>' +
    '<button type="button" class="carousel__btn carousel__btn--next" data-next aria-label="بعدی">' + u.icon('chevron-left') + '</button></div></section>';

  /* Related: same category first, then same brand, then best sellers. */
  var related = [];
  function addAll(list) {
    list.forEach(function (x) { if (x.id !== p.id && related.indexOf(x) === -1) related.push(x); });
  }
  addAll(AP.data.products.filter(function (x) { return x.category === p.category; }));
  addAll(AP.data.products.filter(function (x) { return x.brand === p.brand; }));
  addAll(AP.data.products.filter(function (x) { return x.isBestseller; }));
  document.getElementById('related').innerHTML = AP.cards(related.slice(0, 10));
  AP.initCarousels();

  /* Gallery: views + hover zoom */
  var main = document.getElementById('gallery-main');
  var img = document.getElementById('gallery-img');
  u.$$('.gallery__thumb').forEach(function (btn) {
    btn.addEventListener('click', function () {
      u.$$('.gallery__thumb').forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); });
      main.classList.remove('view-flip', 'view-close');
      if (btn.dataset.view) main.classList.add(btn.dataset.view);
    });
  });
  main.addEventListener('mousemove', function (e) {
    var r = main.getBoundingClientRect();
    img.style.transformOrigin = ((e.clientX - r.left) / r.width * 100) + '% ' + ((e.clientY - r.top) / r.height * 100) + '%';
  });
  main.addEventListener('mouseenter', function () { main.classList.add('is-zoom'); });
  main.addEventListener('mouseleave', function () { main.classList.remove('is-zoom'); img.style.transformOrigin = ''; });

  /* Quantity stepper (bounded by stock) */
  var qty = document.getElementById('qty');
  if (qty) {
    var setQty = function (n) {
      n = Math.max(1, Math.min(p.stock, Math.floor(n) || 1));
      qty.value = u.faDigits(n);
      u.$('[data-step="-1"]').disabled = n <= 1;
      u.$('[data-step="1"]').disabled = n >= p.stock;
    };
    u.$$('[data-step]').forEach(function (b) {
      b.addEventListener('click', function () { setQty(Number(u.enDigits(qty.value)) + Number(b.dataset.step)); });
    });
    qty.addEventListener('change', function () { setQty(Number(u.enDigits(qty.value))); });
    setQty(1);
  }

  /* Tabs with arrow-key support */
  var tabs = u.$$('.tabs-panel__tab');
  function selectTab(tab) {
    tabs.forEach(function (t) {
      var on = t === tab;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
    });
    tab.focus();
  }
  tabs.forEach(function (t, i) {
    t.addEventListener('click', function () { selectTab(t); });
    t.addEventListener('keydown', function (e) {
      // RTL: left arrow moves to the next tab
      if (e.key === 'ArrowLeft') selectTab(tabs[(i + 1) % tabs.length]);
      if (e.key === 'ArrowRight') selectTab(tabs[(i - 1 + tabs.length) % tabs.length]);
    });
  });
})();
