/* Home page sections. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var all = AP.data.products;

  function put(id, html) {
    var el = document.getElementById(id);
    if (el) el.innerHTML = html;
    return el;
  }

  function sortBy(list, mode) {
    var copy = list.slice();
    var by = {
      discount: function (a, b) { return u.discount(b) - u.discount(a); },
      cheap: function (a, b) { return a.price - b.price; },
      expensive: function (a, b) { return b.price - a.price; },
      popular: function (a, b) { return b.rating * Math.log(b.reviewsCount + 1) - a.rating * Math.log(a.reviewsCount + 1); }
    };
    return copy.sort(by[mode] || by.discount);
  }

  /* 4. Hero */
  var hero = u.$('[data-hero]');
  if (hero) AP.HeroSlider(hero, { interval: 5000 });

  /* 5. Quick categories */
  put('quick-cats', AP.data.categories.map(function (c) {
    return '<li><a class="quick-cat" href="category.html?cat=' + c.slug + '">' +
      '<img src="' + c.image + '" alt="" width="240" height="240" loading="lazy"><span>' + c.short + '</span></a></li>';
  }).join(''));

  /* 6. Amazing offers + countdown to midnight */
  put('amazing-track', AP.cards(all.filter(function (p) { return p.isAmazing; }), 'amazing'));
  (function countdown() {
    var parts = { h: u.$('[data-cd="h"]'), m: u.$('[data-cd="m"]'), s: u.$('[data-cd="s"]') };
    if (!parts.h) return;
    function pad(n) { return u.faDigits(String(n).padStart(2, '0')); }
    function tick() {
      var now = new Date();
      var end = new Date(now); end.setHours(24, 0, 0, 0);
      var left = Math.max(0, Math.floor((end - now) / 1000));
      parts.h.textContent = pad(Math.floor(left / 3600));
      parts.m.textContent = pad(Math.floor(left % 3600 / 60));
      parts.s.textContent = pad(left % 60);
    }
    tick();
    setInterval(tick, 1000);
  })();

  /* 8. Visual categories */
  put('visual-cats', AP.data.groups.map(function (g) {
    return '<a class="visual-cat" href="products.html?cat=' + g.cats.join(',') + '">' +
      '<img src="' + g.image + '" alt="" width="600" height="360" loading="lazy">' +
      '<span class="visual-cat__text"><strong>' + g.name + '</strong><span>' + g.desc + '</span></span></a>';
  }).join(''));

  /* 9. Best sellers (ranked) */
  put('bestsellers', AP.cards(sortBy(all.filter(function (p) { return p.isBestseller; }), 'popular'), 'ranked'));

  /* 10. Brands */
  put('brands', AP.data.brands.map(function (b) {
    var count = all.filter(function (p) { return p.brand === b.slug; }).length;
    return '<a class="brand-card" href="products.html?brand=' + b.slug + '">' +
      '<img src="' + b.logo + '" alt="لوگوی ' + b.name + '" width="240" height="120" loading="lazy">' +
      '<span>' + u.num(count) + ' محصول</span></a>';
  }).join(''));

  /* 11. Newest */
  put('newest', AP.cards(all.filter(function (p) { return p.isNew; }).slice(0, 12)));

  /* 12. Cleaning */
  put('cleaning', AP.cards(AP.catalog.bySlugs(['car-shampoo', 'body-wax', 'polish', 'microfiber-cloth', 'cleaning-brush', 'cleaner-spray', 'cleaning-foam', 'wash-kit'])));

  /* 13. Electronics (independent carousel) */
  put('electronics', AP.cards(AP.catalog.bySlugs(['dash-cam', 'parking-sensor', 'car-charger', 'phone-holder', 'bluetooth-transmitter', 'gps-tracker', 'led-headlight', 'car-display'])));

  /* 14. Interior */
  put('interior', AP.cards(AP.catalog.bySlugs(['floor-mat', 'seat-cover', 'car-organizer', 'headrest-pillow', 'cup-holder', 'sunshade', 'air-freshener', 'decor-accessory']), 'compact'));

  /* 15. Workshop tools: featured product + grid */
  (function tools() {
    var list = AP.catalog.bySlugs(['air-compressor', 'wrench-set', 'toolbox', 'socket-wrench', 'battery-tester', 'battery-cables', 'work-light', 'emergency-kit', 'car-tool-kit']);
    var lead = list[0];
    var off = u.discount(lead);
    var featured = '<article class="mosaic__lead">' +
      '<span class="badge badge--red">' + u.icon('award', 'icon--sm') + 'پیشنهاد ویژه کارگاه</span>' +
      '<a href="product-details.html?id=' + lead.id + '" tabindex="-1" aria-hidden="true"><img src="' + lead.image + '" alt="' + u.escape(lead.title) + '" width="400" height="400" loading="lazy"></a>' +
      '<h3><a href="product-details.html?id=' + lead.id + '">' + u.escape(lead.title) + '</a></h3>' +
      '<p>' + u.escape(lead.description) + '</p>' +
      '<div class="mosaic__price">' + (off ? '<del>' + u.num(lead.oldPrice) + '</del><span class="badge badge--red">' + u.num(off) + '٪</span>' : '') +
      '<strong>' + u.num(lead.price) + ' <small>تومان</small></strong></div>' +
      '<button type="button" class="btn btn--primary" data-add="' + lead.id + '">' + u.icon('cart', 'icon--sm') + 'افزودن به سبد</button></article>';
    put('tools', featured + AP.cards(list.slice(1)));
  })();

  /* 16. Oil & filters with tabs */
  (function oil() {
    var groups = {
      oil: ['engine-oil', 'gearbox-oil'],
      filter: ['oil-filter', 'air-filter', 'cabin-filter'],
      fluid: ['coolant', 'brake-fluid']
    };
    var everything = groups.oil.concat(groups.filter, groups.fluid);
    var tabs = u.$$('[data-oil-tab]');
    function show(key) {
      put('oil', AP.cards(AP.catalog.bySlugs(key === 'all' ? everything : groups[key])));
      tabs.forEach(function (t) {
        var on = t.dataset.oilTab === key;
        t.setAttribute('aria-selected', String(on));
        t.classList.toggle('is-active', on);
      });
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.oilTab); }); });
    show('all');
  })();

  /* 17. Economy deals with sort */
  (function economy() {
    var select = document.getElementById('economy-sort');
    var deals = all.filter(function (p) { return u.discount(p) > 0; });
    function render() { put('economy', AP.cards(sortBy(deals, select.value).slice(0, 12))); }
    select.addEventListener('change', render);
    render();
  })();

  /* 18. Vehicle selector */
  (function vehicle() {
    var form = document.getElementById('vehicle-form');
    var out = document.getElementById('vehicle-result');
    AP.VehicleSelector(form, function (sel) {
      var specific = all.filter(function (p) { return Array.isArray(p.compatibleVehicles) && p.compatibleVehicles.indexOf(sel.model) > -1; });
      var universal = all.filter(function (p) { return p.compatibleVehicles === 'universal'; });
      var total = specific.length + universal.length;
      var shown = specific.concat(sortBy(universal, 'popular')).slice(0, 12);
      out.innerHTML = '<div class="vehicle__summary">' +
        '<p>برای <strong>' + sel.modelName + ' مدل ' + u.faDigits(sel.year) + '</strong> ' + u.num(total) + ' محصول سازگار پیدا شد' +
        (specific.length ? '؛ ' + u.num(specific.length) + ' قطعه اختصاصی این خودرو در ابتدای فهرست است.' : '.') + '</p>' +
        '<a class="btn btn--light btn--sm" href="products.html?vehicle=' + sel.model + '">مشاهده همه محصولات سازگار</a></div>' +
        '<div class="grid-products">' + AP.cards(shown) + '</div>';
      out.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  })();

  /* 19. Expert picks */
  put('expert', AP.cards(all.filter(function (p) { return p.isExpertPick; }).slice(0, 8), 'default', { action: 'details' }));

  /* 20. Buying guides */
  var guides = [
    { img: 'guide-compressor', title: 'راهنمای خرید کمپرسور باد', text: 'حداکثر فشار، سرعت باد دهی و قطع خودکار سه معیار اصلی انتخاب کمپرسور هستند.',
      tips: ['برای خودروی سواری فشار ۱۰۰ PSI کافی است؛ ۱۵۰ PSI برای شاسی‌بلندها مناسب‌تر است.', 'مدل‌های دیجیتال با قطع خودکار از باد بیش از حد لاستیک جلوگیری می‌کنند.', 'طول کابل باید به لاستیک‌های عقب برسد؛ حداقل ۳ متر.'],
      link: 'search.html?q=کمپرسور' },
    { img: 'guide-dashcam', title: 'راهنمای خرید دوربین خودرو', text: 'کیفیت تصویر در شب و سنسور ضربه از وضوح اسمی مهم‌ترند.',
      tips: ['زاویه دید ۱۴۰ تا ۱۷۰ درجه هر دو لاین را پوشش می‌دهد.', 'سنسور G فیلم لحظه تصادف را از پاک شدن محافظت می‌کند.', 'از کارت حافظه مخصوص ضبط مداوم (High Endurance) استفاده کنید.'],
      link: 'category.html?cat=camera' },
    { img: 'guide-travel', title: 'راهنمای تجهیزات ضروری سفر', text: 'پیش از هر سفر جاده‌ای این چک‌لیست ساده را مرور کنید.',
      tips: ['کپسول آتش‌نشانی، مثلث خطر و کیف کمک‌های اولیه را همیشه همراه داشته باشید.', 'جامپ استارتر یا کابل باتری در زمستان ضروری است.', 'کمپرسور فندکی شما را از پنچرگیری کنار جاده بی‌نیاز می‌کند.'],
      link: 'products.html?cat=safety,travel' },
    { img: 'guide-cleaning', title: 'راهنمای نظافت خودرو', text: 'شست‌وشوی درست بدنه از خط‌وخش‌های ریز جلوگیری می‌کند.',
      tips: ['هرگز زیر آفتاب مستقیم بدنه را نشویید؛ لکه آب می‌ماند.', 'روش دو سطل: یک سطل کف و یک سطل آب تمیز برای آب کشیدن دستکش.', 'پس از خشک کردن با مایکروفایبر، هر سه ماه یک بار واکس بزنید.'],
      link: 'category.html?cat=cleaning' }
  ];
  put('guide-list', guides.map(function (g) {
    return '<article class="guide">' +
      '<img src="assets/images/banners/' + g.img + '.svg" alt="" width="640" height="360" loading="lazy">' +
      '<div class="guide__body"><h3>' + g.title + '</h3><p>' + g.text + '</p>' +
      '<details><summary>ادامه مطلب</summary><ul>' + g.tips.map(function (t) { return '<li>' + t + '</li>'; }).join('') + '</ul></details>' +
      '<a class="link-more" href="' + g.link + '">مشاهده محصولات مرتبط' + u.icon('chevron-left') + '</a></div></article>';
  }).join(''));

  /* 22. Testimonials (sample data) */
  var reviews = [
    { name: 'سارا م.', city: 'تهران', avatar: 1, rating: 5, product: 3, text: 'کیفیت تصویر دوربین در شب واقعاً خوب است و نصبش ده دقیقه طول کشید. بسته‌بندی هم سالم رسید.' },
    { name: 'محمد ر.', city: 'اصفهان', avatar: 3, rating: 4.5, product: 1, text: 'کمپرسور را برای سفر شمال خریدم؛ سریع باد می‌کند و قطع خودکارش خیال را راحت می‌کند.' },
    { name: 'نگار ک.', city: 'شیراز', avatar: 2, rating: 5, product: 13, text: 'واکس کارنوبا براقیت خیلی خوبی داد. مشاوره کارشناس پشتیبانی هم برای انتخاب پولیش کمک کرد.' },
    { name: 'علی ح.', city: 'مشهد', avatar: 4, rating: 4, product: 37, text: 'روغن موتور اصل بود و کد اصالتش استعلام شد. ارسال دو روزه به مشهد رسید.' },
    { name: 'مریم ص.', city: 'تبریز', avatar: 2, rating: 5, product: 4, text: 'جامپ استارتر در سرمای صبح زمستان ماشین را با یک بار استارت روشن کرد. خرید به‌صرفه‌ای بود.' }
  ];
  put('reviews', reviews.map(function (r) {
    var p = AP.catalog.product(r.product);
    return '<figure class="review">' +
      '<div class="review__head"><img src="assets/images/ui/avatar-' + r.avatar + '.svg" alt="" width="56" height="56" loading="lazy">' +
      '<figcaption><strong>' + r.name + '</strong><span>' + r.city + '</span></figcaption>' + u.stars(r.rating) + '</div>' +
      '<blockquote>' + r.text + '</blockquote>' +
      '<a class="review__product" href="product-details.html?id=' + p.id + '">' + u.icon('package-track', 'icon--sm') + p.title + '</a></figure>';
  }).join(''));

  /* 23. Newsletter (stored locally, no external service) */
  (function newsletter() {
    var form = document.getElementById('newsletter-form');
    var input = form.elements.email;
    var msg = document.getElementById('newsletter-msg');
    function say(text, ok) {
      msg.textContent = text;
      msg.className = 'newsletter__msg ' + (ok ? 'is-success' : 'is-error');
      input.setAttribute('aria-invalid', String(!ok));
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var email = input.value.trim().toLowerCase();
      if (!email) return say('لطفاً آدرس ایمیل خود را وارد کنید.', false);
      if (!u.isEmail(email)) return say('آدرس ایمیل معتبر نیست؛ نمونه درست: name@example.com', false);
      var list = u.store.get('ap_newsletter', []);
      if (!Array.isArray(list)) list = [];
      if (list.indexOf(email) > -1) return say('این ایمیل قبلاً در خبرنامه عضو شده است.', false);
      list.push(email);
      if (!u.store.set('ap_newsletter', list)) return say('ذخیره‌سازی در مرورگر ممکن نیست؛ لطفاً دوباره تلاش کنید.', false);
      say('عضویت شما با موفقیت انجام شد. از همراهی‌تان ممنونیم!', true);
      form.reset();
      input.removeAttribute('aria-invalid');
    });
  })();

  AP.initCarousels();
})();
