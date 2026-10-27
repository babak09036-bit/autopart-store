/* Home page: renders the admin-managed sections (window.AP_HOME, from GET /api/homepage) in their saved order. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var esc = u.escape;
  var icon = u.icon;
  var root = document.getElementById('home-sections');
  var sections = window.AP_HOME || [];

  function head(s, extra, light) {
    if (!s.title) return '';
    return '<div class="section-head"><h2 class="section-head__title"' + (light ? ' style="color:#fff"' : '') + ' id="sec-' + s.id + '">' + esc(s.title) +
      (s.subtitle && s.type !== 'product_grid' ? ' <span class="section-head__sub">' + esc(s.subtitle) + '</span>' : '') + '</h2>' + (extra || '') +
      (s.settings.link ? '<a class="link-more' + (light ? ' link-more--light' : '') + '" href="' + esc(s.settings.link) + '">مشاهده همه' + icon('chevron-left') + '</a>' : '') + '</div>';
  }

  function carousel(inner, cls, autoplay) {
    return '<div class="carousel" data-carousel' + (autoplay ? ' data-autoplay' : '') + '>' +
      '<button type="button" class="carousel__btn carousel__btn--prev" data-prev aria-label="قبلی">' + icon('chevron-right') + '</button>' +
      '<div class="carousel__track ' + (cls || '') + '">' + inner + '</div>' +
      '<button type="button" class="carousel__btn carousel__btn--next" data-next aria-label="بعدی">' + icon('chevron-left') + '</button></div>';
  }

  function wrap(s, inner, cls, full) {
    var label = s.title ? ' aria-labelledby="sec-' + s.id + '"' : ' aria-label="' + esc(s.type) + '"';
    return '<section class="' + (full ? '' : 'container ') + 'section ' + (cls || '') + '"' + label + ' data-section="' + s.id + '">' + inner + '</section>';
  }

  function bannerTheme(b) { return b.theme === 'dark' ? 'light' : 'dark'; }

  function bannerImg(b, w, h) {
    return '<picture><source media="(max-width: 767px)" srcset="' + esc(b.image_mobile) + '"><img src="' + esc(b.image) + '" alt="' + esc(b.alt) + '" width="' + w + '" height="' + h + '" loading="lazy"></picture>';
  }

  function promoCard(b) {
    return '<a class="promo promo--' + (b.theme === 'dark' ? 'light' : 'dark') + '" href="' + esc(b.button_url || '') + '"' + (b.bg_color ? ' style="--promo-bg:' + esc(b.bg_color) + '"' : '') + '>' +
      bannerImg(b, 800, 360) + '<div class="promo__content">' +
      (b.eyebrow ? '<span class="promo__eyebrow">' + esc(b.eyebrow) + '</span>' : '') +
      '<h2 class="promo__title">' + esc(b.title) + '</h2>' + (b.description ? '<p>' + esc(b.description) + '</p>' : '') +
      (b.button_text ? '<span class="btn ' + (b.theme === 'dark' ? 'btn--primary' : 'btn--white') + ' btn--sm">' + esc(b.button_text) + '</span>' : '') + '</div></a>';
  }

  var R = {
    hero_slider: function (s) {
      var slides = s.data.banners.map(function (b, i) {
        return '<div class="hero__slide hero__slide--' + bannerTheme(b) + '" role="group" aria-roledescription="slide" aria-label="' + u.faDigits(i + 1) + ' از ' + u.faDigits(s.data.banners.length) + '"' +
          (b.bg_color ? ' style="background:' + esc(b.bg_color) + '"' : '') + '>' +
          '<picture><source media="(max-width: 767px)" srcset="' + esc(b.image_mobile) + '"><img src="' + esc(b.image) + '" alt="' + esc(b.alt) + '" width="1200" height="400"' + (i ? '' : ' fetchpriority="high"') + '></picture>' +
          '<div class="hero__content">' + (b.eyebrow ? '<span class="hero__eyebrow">' + esc(b.eyebrow) + '</span>' : '') +
          '<h2 class="hero__title">' + esc(b.title) + '</h2>' + (b.description ? '<p class="hero__text">' + esc(b.description) + '</p>' : '') +
          (b.button_text ? '<a class="btn ' + (b.theme === 'dark' ? 'btn--primary' : (b.bg_color && b.bg_color.toLowerCase() === '#a80f16' ? 'btn--white' : 'btn--primary')) + '" href="' + esc(b.button_url || '') + '">' + esc(b.button_text) + '</a>' : '') +
          '</div></div>';
      }).join('');
      return wrap(s, '<div class="hero" data-hero data-interval="' + (s.settings.interval || 5000) + '" aria-roledescription="carousel"><div class="hero__viewport"><div class="hero__track">' + slides + '</div></div>' +
        '<button type="button" class="hero__nav hero__nav--prev" data-prev aria-label="اسلاید قبلی">' + icon('chevron-right') + '</button>' +
        '<button type="button" class="hero__nav hero__nav--next" data-next aria-label="اسلاید بعدی">' + icon('chevron-left') + '</button><div class="hero__dots"></div></div>', 'hero-wrap');
    },

    quick_categories: function (s) {
      return wrap(s, (s.title ? '<h2 class="visually-hidden" id="sec-' + s.id + '">' + esc(s.title) + '</h2>' : '') + '<ul class="quick-cats">' + s.data.categories.map(function (c) {
        return '<li><a class="quick-cat" href="' + esc(c.url) + '"><img src="' + esc(c.image || 'assets/images/ui/placeholder.svg') + '" alt="" width="240" height="240" loading="lazy"><span>' + esc(c.name) + '</span></a></li>';
      }).join('') + '</ul>');
    },

    amazing: function (s) {
      return wrap(s, '<div class="amazing"' + (s.bg_color ? ' style="background:' + esc(s.bg_color) + '"' : '') + '><div class="amazing__intro">' +
        '<h2 class="amazing__title" id="sec-' + s.id + '">' + esc(s.title || 'پیشنهادهای شگفت‌انگیز') + '</h2><svg class="amazing__flash" aria-hidden="true"><use href="assets/icons/icons.svg#i-flash"></use></svg>' +
        '<div class="countdown" dir="ltr" role="timer" aria-label="زمان باقی‌مانده تا پایان پیشنهادها"><span data-cd="h">۰۰</span><b>:</b><span data-cd="m">۰۰</span><b>:</b><span data-cd="s">۰۰</span></div>' +
        (s.settings.link ? '<a class="amazing__all" href="' + esc(s.settings.link) + '">مشاهده همه' + icon('chevron-left', 'icon--sm') + '</a>' : '') + '</div>' +
        '<div class="amazing__carousel">' + carousel(AP.cards(s.data.products, 'amazing')) + '</div></div>');
    },

    promo_banners: function (s) { return wrap(s, '<div class="promo-grid">' + s.data.banners.map(promoCard).join('') + '</div>'); },
    two_banners: function (s) { return wrap(s, head(s) + '<div class="promo-grid promo-grid--2">' + s.data.banners.map(promoCard).join('') + '</div>'); },
    big_banner: function (s) { return wrap(s, head(s) + '<div class="promo-grid promo-grid--1">' + s.data.banners.map(promoCard).join('') + '</div>'); },

    visual_categories: function (s) {
      return wrap(s, head(s) + '<div class="visual-cats">' + s.data.categories.map(function (c) {
        return '<a class="visual-cat" href="' + esc(c.url) + '"><img src="' + esc(c.image || 'assets/images/ui/placeholder.svg') + '" alt="" width="600" height="360" loading="lazy">' +
          '<span class="visual-cat__text"><strong>' + esc(c.full_name || c.name) + '</strong>' + (c.description ? '<span>' + esc(c.description) + '</span>' : '') + '</span></a>';
      }).join('') + '</div>');
    },

    ranked_products: function (s) { return wrap(s, '<div class="panel">' + head(s) + '<div class="ranked-grid">' + AP.cards(s.data.products, 'ranked') + '</div></div>'); },

    brands: function (s) {
      return wrap(s, head(s) + carousel(s.data.brands.map(function (b) {
        return '<a class="brand-card" href="brands/' + esc(b.slug) + '"><img src="' + esc(b.logo || 'assets/images/ui/placeholder.svg') + '" alt="لوگوی ' + esc(b.name) + '" width="240" height="120" loading="lazy"><span>' + u.num(b.count) + ' محصول</span></a>';
      }).join(''), 'brand-track'));
    },

    product_grid: function (s) {
      var cols = s.settings.columns === 4 ? ' grid-products--4' : '';
      var cards = AP.cards(s.data.products, 'default', { action: s.settings.action });
      if (s.settings.layout === 'split') {
        return wrap(s, '<div class="feature-split"' + (s.bg_color ? ' style="background:' + esc(s.bg_color) + '"' : '') + '><div class="feature-split__intro">' +
          '<h2 id="sec-' + s.id + '">' + esc(s.title || '') + '</h2>' + (s.subtitle ? '<p>' + esc(s.subtitle) + '</p>' : '') +
          (s.settings.image ? '<img src="' + esc(s.settings.image) + '" alt="" width="600" height="360" loading="lazy">' : '') +
          (s.settings.link ? '<a class="btn btn--primary" href="' + esc(s.settings.link) + '">مشاهده همه</a>' : '') +
          '</div><div class="feature-split__grid">' + cards + '</div></div>');
      }
      var dark = s.bg_color && /^#(1|0|2)/.test(s.bg_color);
      return wrap(s, '<div class="' + (dark ? 'newest' : 'panel') + '"' + (s.bg_color ? ' style="background:' + esc(s.bg_color) + '"' : '') + '>' +
        (dark ? head(s, '', true).replace('section-head"', 'section-head newest__head"') : head(s)) +
        (s.subtitle && !s.title ? '' : '') + '<div class="grid-products' + cols + '">' + cards + '</div></div>');
    },

    product_slider: function (s) {
      return '<section class="band section" style="background:' + esc(s.bg_color || '#0d1b2e') + '" aria-labelledby="sec-' + s.id + '" data-section="' + s.id + '"><div class="container">' +
        head(s, '', true) + carousel(AP.cards(s.data.products)) + '</div></section>';
    },

    compact_grid: function (s) { return wrap(s, '<div class="panel">' + head(s) + '<div class="compact-grid">' + AP.cards(s.data.products, 'compact') + '</div></div>'); },

    mosaic: function (s) {
      var list = s.data.products;
      var lead = list[0];
      var featured = '<article class="mosaic__lead"><span class="badge badge--red">' + icon('award', 'icon--sm') + 'پیشنهاد ویژه</span>' +
        '<a href="' + esc(lead.url) + '" tabindex="-1" aria-hidden="true"><img src="' + esc(lead.image) + '" alt="' + esc(lead.imageAlt) + '" width="400" height="400" loading="lazy"></a>' +
        '<h3><a href="' + esc(lead.url) + '">' + esc(lead.title) + '</a></h3>' +
        '<div class="mosaic__price">' + (lead.discountPercent ? '<del>' + u.num(lead.oldPrice) + '</del><span class="badge badge--red">' + u.num(lead.discountPercent) + '٪</span>' : '') +
        '<strong>' + u.num(lead.price) + ' <small>تومان</small></strong></div>' +
        (lead.inStock ? '<button type="button" class="btn btn--primary" data-add="' + lead.id + '">' + icon('cart', 'icon--sm') + 'افزودن به سبد</button>' : '<button class="btn" disabled>ناموجود</button>') + '</article>';
      return wrap(s, head(s) + '<div class="mosaic">' + featured + AP.cards(list.slice(1)) + '</div>');
    },

    tabbed_products: function (s) {
      var tabs = s.data.tabs.map(function (t, i) {
        return '<button type="button" class="chip" role="tab" aria-selected="' + (i === 0) + '" data-tab="' + i + '">' + esc(t.label) + '</button>';
      }).join('');
      return wrap(s, '<div class="panel"' + (s.bg_color ? ' style="background:' + esc(s.bg_color) + '"' : '') + '>' +
        head(s, '<div class="tabs" role="tablist">' + tabs + '</div>') +
        '<div class="grid-products" role="tabpanel" data-tabpanel>' + AP.cards(s.data.tabs[0].products) + '</div></div>', 'js-tabs');
    },

    discounted: function (s) {
      var b = s.data.banner;
      return wrap(s, '<div class="economy">' + (b ? '<div class="economy__banner">' + bannerImg(b, 1200, 240) +
        '<div class="economy__text"><h2 id="sec-' + s.id + '">' + esc(s.title || b.title) + '</h2>' + (s.subtitle || b.description ? '<p>' + esc(s.subtitle || b.description) + '</p>' : '') + '</div></div>'
        : head(s)) +
        '<div class="economy__tools"><label for="eco-sort-' + s.id + '">مرتب‌سازی:</label><select class="select select--inline" id="eco-sort-' + s.id + '" data-eco-sort>' +
        '<option value="discount">بیشترین تخفیف</option><option value="cheap">ارزان‌ترین</option><option value="expensive">گران‌ترین</option><option value="popular">محبوب‌ترین</option></select>' +
        (s.settings.link ? '<a class="link-more" href="' + esc(s.settings.link) + '">مشاهده همه محصولات تخفیف‌دار' + icon('chevron-left') + '</a>' : '') + '</div>' +
        '<div class="grid-products" data-eco-grid>' + AP.cards(s.data.products) + '</div></div>', 'js-economy');
    },

    vehicle_selector: function (s) {
      return wrap(s, '<div class="vehicle"><div class="vehicle__head"><svg class="vehicle__icon" aria-hidden="true"><use href="assets/icons/icons.svg#i-car"></use></svg>' +
        '<div><h2 id="sec-' + s.id + '">' + esc(s.title || 'انتخاب خودرو') + '</h2>' + (s.subtitle ? '<p>' + esc(s.subtitle) + '</p>' : '') + '</div></div>' +
        '<form class="vehicle__form" id="vehicle-form" novalidate>' +
        '<div class="field"><label for="v-brand">برند خودرو</label><select class="select" id="v-brand" name="brand"></select></div>' +
        '<div class="field"><label for="v-model">مدل</label><select class="select" id="v-model" name="model"></select></div>' +
        '<div class="field"><label for="v-year">سال تولید</label><select class="select" id="v-year" name="year"></select></div>' +
        '<button type="submit" class="btn btn--primary vehicle__submit">مشاهده محصولات سازگار</button><p class="form-error" role="alert"></p></form>' +
        '<div class="vehicle__result" id="vehicle-result" aria-live="polite"></div></div>', 'js-vehicle');
    },

    text: function (s) {
      return wrap(s, '<div class="panel rich-text"' + (s.bg_color ? ' style="background:' + esc(s.bg_color) + '"' : '') + '>' + head(s) + (s.settings.body || '') + '</div>');
    },

    blog_posts: function (s) {
      return wrap(s, head(s) + '<div class="guides">' + s.data.posts.map(function (p) {
        return '<article class="guide"><img src="' + esc(p.image) + '" alt="' + esc(p.image_alt) + '" width="640" height="360" loading="lazy">' +
          '<div class="guide__body"><h3><a href="' + esc(p.url) + '">' + esc(p.title) + '</a></h3>' + (p.excerpt ? '<p>' + esc(p.excerpt) + '</p>' : '') +
          '<a class="link-more" href="' + esc(p.url) + '">ادامه مطلب' + icon('chevron-left') + '</a></div></article>';
      }).join('') + '</div>');
    },

    benefits: function (s) {
      return wrap(s, '<h2 class="visually-hidden" id="sec-' + s.id + '">' + esc(s.title || 'مزایای فروشگاه') + '</h2><ul class="benefits">' + (s.settings.items || []).map(function (b) {
        return '<li class="benefit"><span class="benefit__icon">' + icon(esc(b.icon), 'icon--lg') + '</span><div><h3>' + esc(b.title) + '</h3><p>' +
          (b.url ? '<a href="' + esc(b.url) + '">' + esc(b.text) + '</a>' : esc(b.text)) + '</p></div></li>';
      }).join('') + '</ul>');
    },

    testimonials: function (s) {
      return wrap(s, head(s) + carousel(s.data.testimonials.map(function (r) {
        return '<figure class="review"><div class="review__head"><img src="' + esc(r.avatar || 'assets/images/ui/avatar-1.svg') + '" alt="" width="56" height="56" loading="lazy">' +
          '<figcaption><strong>' + esc(r.name) + '</strong><span>' + esc(r.city) + '</span></figcaption>' + u.stars(r.rating) + '</div>' +
          '<blockquote>' + esc(r.text) + '</blockquote>' + (r.sample ? '<p class="review__sample">نمونه نمایشی — نظر واقعی مشتری نیست</p>' : '') +
          (r.product ? '<a class="review__product" href="' + esc(r.product.url) + '">' + icon('package-track', 'icon--sm') + esc(r.product.title) + '</a>' : '') + '</figure>';
      }).join(''), 'review-track', true));
    },

    newsletter: function (s) {
      return wrap(s, '<div class="newsletter"><div class="newsletter__text"><svg class="newsletter__icon" aria-hidden="true"><use href="assets/icons/icons.svg#i-mail"></use></svg>' +
        '<div><h2 id="sec-' + s.id + '">' + esc(s.title || 'خبرنامه') + '</h2>' + (s.subtitle ? '<p>' + esc(s.subtitle) + '</p>' : '') + '</div></div>' +
        '<form class="newsletter__form" data-newsletter novalidate><label class="visually-hidden" for="newsletter-email">آدرس ایمیل</label>' +
        '<input class="input" id="newsletter-email" name="email" type="email" dir="ltr" placeholder="you@example.com" autocomplete="email">' +
        '<button type="submit" class="btn btn--dark">عضویت</button><p class="newsletter__msg" role="status"></p></form></div>');
    }
  };

  root.innerHTML = sections.map(function (s) {
    try {
      return R[s.type] ? R[s.type](s) : '';
    } catch (err) {
      if (window.console) console.error('Section render failed', s.type, err);
      return '';
    }
  }).join('');
  root.removeAttribute('aria-busy');

  /* ---------------- Behaviour ---------------- */
  u.$$('[data-hero]', root).forEach(function (el) { AP.HeroSlider(el, { interval: Number(el.dataset.interval) || 5000 }); });

  (function countdown() {
    var parts = { h: u.$('[data-cd="h"]'), m: u.$('[data-cd="m"]'), s: u.$('[data-cd="s"]') };
    if (!parts.h) return;
    function pad(n) { return u.faDigits(String(n).padStart(2, '0')); }
    function tick() {
      // Offers reset at local midnight (Tehran time = the visitor's clock for most customers).
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

  u.$$('.js-tabs', root).forEach(function (sec) {
    var data = sections.filter(function (s) { return String(s.id) === sec.dataset.section; })[0];
    var panel = sec.querySelector('[data-tabpanel]');
    sec.addEventListener('click', function (e) {
      var tab = e.target.closest('[data-tab]');
      if (!tab) return;
      u.$$('[data-tab]', sec).forEach(function (t) { t.setAttribute('aria-selected', String(t === tab)); });
      panel.innerHTML = AP.cards(data.data.tabs[Number(tab.dataset.tab)].products);
    });
  });

  u.$$('.js-economy', root).forEach(function (sec) {
    var data = sections.filter(function (s) { return String(s.id) === sec.dataset.section; })[0];
    var grid = sec.querySelector('[data-eco-grid]');
    sec.querySelector('[data-eco-sort]').addEventListener('change', function (e) {
      var list = data.data.products.slice();
      var by = {
        discount: function (a, b) { return b.discountPercent - a.discountPercent; },
        cheap: function (a, b) { return a.price - b.price; },
        expensive: function (a, b) { return b.price - a.price; },
        popular: function (a, b) { return (b.rating * Math.log(b.reviewsCount + 1)) - (a.rating * Math.log(a.reviewsCount + 1)) || Number(b.isBestseller) - Number(a.isBestseller); }
      };
      grid.innerHTML = AP.cards(list.sort(by[e.target.value]));
    });
  });

  u.$$('.js-vehicle', root).forEach(function (sec) {
    var data = sections.filter(function (s) { return String(s.id) === sec.dataset.section; })[0];
    var out = sec.querySelector('#vehicle-result');
    AP.VehicleSelector(sec.querySelector('form'), data.data.vehicles, function (sel) {
      out.innerHTML = '<p class="field__hint">در حال جستجو…</p>';
      AP.api.get('products', { vehicle: sel.model, per_page: 12, sort: 'popular' }).then(function (res) {
        out.innerHTML = '<div class="vehicle__summary"><p>برای <strong>' + esc(sel.modelName) + ' مدل ' + u.faDigits(sel.year) + '</strong> ' +
          u.num(res.pagination.total) + ' محصول سازگار پیدا شد.</p><a class="btn btn--light btn--sm" href="products?vehicle=' + encodeURIComponent(sel.model) + '">مشاهده همه محصولات سازگار</a></div>' +
          '<div class="grid-products">' + AP.cards(res.items) + '</div>';
        out.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }).catch(function (err) { out.innerHTML = '<p class="form-error">' + esc(err.message) + '</p>'; });
    });
  });

  AP.initCarousels(root);
})();
