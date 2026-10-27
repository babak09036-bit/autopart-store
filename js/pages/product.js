/* Product page: gallery, variants, quantity, add to cart, tabs, reviews (list + submit) and questions. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var esc = u.escape;
  var P = (AP.boot && AP.boot.product) || {};

  /* ---------------- Gallery ---------------- */
  var main = document.getElementById('gallery-main');
  var img = document.getElementById('gallery-img');
  u.$$('.gallery__thumb').forEach(function (btn) {
    btn.addEventListener('click', function () {
      u.$$('.gallery__thumb').forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); });
      img.src = btn.dataset.src;
      img.alt = btn.dataset.alt;
    });
  });
  if (main) {
    main.addEventListener('mousemove', function (e) {
      var r = main.getBoundingClientRect();
      img.style.transformOrigin = ((e.clientX - r.left) / r.width * 100) + '% ' + ((e.clientY - r.top) / r.height * 100) + '%';
    });
    main.addEventListener('mouseenter', function () { main.classList.add('is-zoom'); });
    main.addEventListener('mouseleave', function () { main.classList.remove('is-zoom'); img.style.transformOrigin = ''; });
  }

  /* ---------------- Vehicle fitment check (answer comes from the server) ---------------- */
  var fit = document.getElementById('fitment-form');
  if (fit && AP.VehicleSelector) {
    var fitOut = fit.querySelector('.fitment__result');
    AP.api.get('vehicles').then(function (vehicles) {
      if (!vehicles.length) { fit.hidden = true; return; }
      AP.VehicleSelector(fit, vehicles, function (sel) {
        fitOut.innerHTML = '<p class="field__hint">در حال بررسی…</p>';
        AP.api.get('products/' + fit.dataset.product + '/fitment', { vehicle: sel.model, year: sel.year }).then(function (r) {
          var car = '<strong>' + esc(r.vehicle.brand + ' ' + r.vehicle.label) + '</strong>';
          var years = r.match ? u.faDigits(r.match.year_from) + ' تا ' + u.faDigits(r.match.year_to) : '';
          var msg = {
            fits: [true, 'این کالا با ' + car + ' سازگار است.' + (r.match && r.match.note ? ' <span>(' + esc(r.match.note) + ')</span>' : '')],
            universal: [true, 'این کالا عمومی است و با ' + car + ' هم سازگار است.'],
            year: [false, 'این کالا برای ' + esc(r.vehicle.brand + ' ' + r.vehicle.name) + ' فقط مدل‌های ' + years + ' مناسب است، نه ' + car + '.'],
            no: [false, 'این کالا با ' + car + ' سازگار نیست. محصولات مناسب خودروی خود را <a href="products?vehicle=' + encodeURIComponent(sel.model) + '&amp;year=' + sel.year + '">اینجا</a> ببینید.'],
            unknown: [null, 'اطلاعات سازگاری این کالا ثبت نشده است؛ پیش از خرید با پشتیبانی تماس بگیرید.']
          }[r.status];
          fitOut.innerHTML = '<p class="fitment__msg fitment__msg--' + (msg[0] === true ? 'ok' : msg[0] === false ? 'no' : 'unknown') + '">' +
            icon(msg[0] === true ? 'check' : 'alert') + '<span>' + msg[1] + '</span></p>';
        }).catch(function (err) { fitOut.innerHTML = '<p class="form-error">' + esc(err.message) + '</p>'; });
      }, AP.garage.get());
      var saved = AP.garage.get();
      if (saved && fit.elements.year.value) fit.requestSubmit();
    }).catch(function () { fit.hidden = true; });
  }
  function icon(name) { return '<svg class="icon icon--sm" aria-hidden="true"><use href="assets/icons/icons.svg#i-' + name + '"></use></svg>'; }

  /* ---------------- Variant + quantity ---------------- */
  var variant = document.getElementById('variant');
  var qty = document.getElementById('qty');
  function maxQty() {
    if (variant) {
      var opt = variant.options[variant.selectedIndex];
      return Number(opt && opt.dataset.stock) || 0;
    }
    return Number(P.stock) || 0;
  }
  function setQty(n) {
    if (!qty) return;
    n = Math.max(1, Math.min(maxQty() || 1, Math.floor(n) || 1));
    qty.value = u.faDigits(n);
    var dec = u.$('[data-step="-1"]'), inc = u.$('[data-step="1"]');
    if (dec) dec.disabled = n <= 1;
    if (inc) inc.disabled = n >= maxQty();
  }
  if (variant) {
    variant.addEventListener('change', function () {
      var opt = variant.options[variant.selectedIndex];
      document.getElementById('buy-price-now').textContent = u.num(opt.dataset.price);
      setQty(1);
    });
  }
  u.$$('[data-step]').forEach(function (b) {
    b.addEventListener('click', function () { setQty(Number(u.enDigits(qty.value)) + Number(b.dataset.step)); });
  });
  if (qty) {
    qty.addEventListener('change', function () { setQty(Number(u.enDigits(qty.value))); });
    setQty(1);
  }
  var addBtn = document.getElementById('add-to-cart');
  if (addBtn) {
    addBtn.addEventListener('click', function () {
      addBtn.disabled = true;
      AP.cart.add(addBtn.dataset.product, Number(u.enDigits(qty.value)) || 1, variant ? Number(variant.value) : 0).then(function (res) {
        addBtn.disabled = false;
        AP.toast(res.ok ? '«' + P.title + '» به سبد خرید اضافه شد.' : res.message, res.ok ? 'success' : 'error');
      });
    });
  }

  /* ---------------- Tabs ---------------- */
  var tabs = u.$$('.tabs-panel__tab');
  var loaded = {};
  function selectTab(tab, focus) {
    tabs.forEach(function (t) {
      var on = t === tab;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
    });
    if (focus) tab.focus();
    if (tab.id === 'tab-reviews' && !loaded.reviews) { loaded.reviews = true; loadReviews(); }
    if (tab.id === 'tab-questions' && !loaded.questions) { loaded.questions = true; loadQuestions(); }
  }
  tabs.forEach(function (t, i) {
    t.addEventListener('click', function () { selectTab(t, false); });
    t.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') selectTab(tabs[(i + 1) % tabs.length], true);
      if (e.key === 'ArrowRight') selectTab(tabs[(i - 1 + tabs.length) % tabs.length], true);
    });
  });
  if (location.hash === '#reviews') selectTab(document.getElementById('tab-reviews'), false);

  /* ---------------- Reviews ---------------- */
  var box = document.getElementById('reviews');
  var rq = { sort: 'newest', rating: '', page: 1 };

  function starsInput() {
    return '<div class="star-input" role="radiogroup" aria-label="امتیاز شما">' + [5, 4, 3, 2, 1].map(function (n) {
      return '<input type="radio" id="star-' + n + '" name="rating" value="' + n + '"><label for="star-' + n + '" title="' + u.faDigits(n) + ' ستاره">' + u.icon('star-filled') + '<span class="visually-hidden">' + u.faDigits(n) + ' ستاره</span></label>';
    }).join('') + '</div>';
  }

  function formHTML(mine) {
    if (!AP.boot.user) {
      return '<div class="review-cta"><p>برای ثبت نظر ابتدا وارد حساب کاربری خود شوید.</p><a class="btn btn--primary btn--sm" href="login?next=' + encodeURIComponent(location.pathname) + '">ورود و ثبت نظر</a></div>';
    }
    if (mine) {
      var label = { pending: 'در انتظار بررسی', approved: 'منتشر شده', rejected: 'تأیید نشده' }[mine.status];
      return '<div class="review-cta review-cta--status"><p>نظر شما با عنوان «' + esc(mine.title) + '» ثبت شده است — وضعیت: <strong>' + label + '</strong>' +
        (mine.rejection_reason ? '<br>دلیل: ' + esc(mine.rejection_reason) : '') + '</p></div>';
    }
    return '<form class="review-form card-surface" id="review-form" novalidate enctype="multipart/form-data">' +
      '<h3>نظر خود را درباره این کالا بنویسید</h3>' +
      '<div class="field"><span class="field__label">امتیاز شما</span>' + starsInput() + '<p class="field__error" data-error-for="rating"></p></div>' +
      '<div class="field"><label for="rv-title">عنوان نظر</label><input class="input" id="rv-title" name="title" maxlength="120"><p class="field__error" data-error-for="title"></p></div>' +
      '<div class="field"><label for="rv-body">متن نظر</label><textarea class="textarea" id="rv-body" name="body" rows="4" maxlength="2000"></textarea><p class="field__hint">حداقل ۱۰ و حداکثر ۲۰۰۰ کاراکتر</p><p class="field__error" data-error-for="body"></p></div>' +
      '<div class="field"><label for="rv-images">تصاویر (اختیاری، حداکثر ۳ تصویر JPG/PNG/WebP تا ۲ مگابایت)</label><input id="rv-images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple><p class="field__error" data-error-for="images"></p></div>' +
      '<p class="field__hint">نظر شما پس از بررسی منتشر می‌شود. نشان «خریدار تأییدشده» فقط برای سفارش‌های تحویل‌شده نمایش داده می‌شود.</p>' +
      '<button type="submit" class="btn btn--primary">ثبت نظر</button></form>';
  }

  function summaryHTML(s) {
    var rows = [5, 4, 3, 2, 1].map(function (n) {
      var c = s.distribution[n] || 0;
      var pct = s.count ? Math.round(c / s.count * 100) : 0;
      return '<button type="button" class="dist-row" data-rating="' + n + '" aria-pressed="' + (String(rq.rating) === String(n)) + '"><span>' + u.faDigits(n) + ' ستاره</span>' +
        '<span class="dist-row__bar"><span style="width:' + pct + '%"></span></span><span>' + u.num(c) + '</span></button>';
    }).join('');
    return '<div class="review-summary card-surface"><div class="review-summary__avg"><strong>' + (s.count ? u.faDigits(s.average.toFixed(1)) : '—') + '</strong>' +
      (s.count ? u.stars(s.average) : '') + '<span>از ' + u.num(s.count) + ' نظر تأییدشده</span></div><div class="review-summary__dist">' + rows + '</div></div>';
  }

  function itemHTML(r) {
    return '<article class="review-item"><div class="review-item__head"><div><strong>' + esc(r.title) + '</strong> ' + u.stars(r.rating) + '</div>' +
      '<span>' + esc(r.author) + ' · ' + esc(r.created_at_fa) + (r.verified_buyer ? ' · <span class="badge badge--green">' + u.icon('check', 'icon--sm') + 'خریدار تأییدشده</span>' : '') + '</span></div>' +
      '<p>' + esc(r.body).replace(/\n/g, '<br>') + '</p>' +
      (r.images.length ? '<div class="review-item__images">' + r.images.map(function (p) { return '<a href="' + esc(p) + '" target="_blank" rel="noopener"><img src="' + esc(p) + '" alt="تصویر ارسالی کاربر" width="80" height="80" loading="lazy"></a>'; }).join('') + '</div>' : '') +
      (r.reply ? '<div class="review-item__reply"><strong>پاسخ فروشگاه</strong><p>' + esc(r.reply.body) + '</p></div>' : '') + '</article>';
  }

  function loadReviews() {
    box.setAttribute('aria-busy', 'true');
    AP.api.get('products/' + box.dataset.product + '/reviews', rq).then(function (d) {
      var p = d.pagination;
      var pager = '';
      if (p.pages > 1) {
        for (var i = 1; i <= p.pages; i++) pager += '<button type="button" class="pager__num" data-rpage="' + i + '"' + (i === p.page ? ' aria-current="page"' : '') + '>' + u.faDigits(i) + '</button>';
        pager = '<nav class="pager" aria-label="صفحه‌بندی نظرات">' + pager + '</nav>';
      }
      box.innerHTML = summaryHTML(d.summary) +
        '<div class="review-tools"><label for="rv-sort">مرتب‌سازی:</label><select class="select select--inline" id="rv-sort">' +
        [['newest', 'جدیدترین'], ['oldest', 'قدیمی‌ترین'], ['highest', 'بیشترین امتیاز'], ['lowest', 'کمترین امتیاز']].map(function (o) {
          return '<option value="' + o[0] + '"' + (rq.sort === o[0] ? ' selected' : '') + '>' + o[1] + '</option>';
        }).join('') + '</select>' + (rq.rating ? '<button type="button" class="chip" data-rating="">حذف فیلتر امتیاز ' + u.icon('close') + '</button>' : '') + '</div>' +
        '<div class="review-list">' + (d.items.length ? d.items.map(itemHTML).join('') : '<p class="field__hint">' + (rq.rating ? 'نظری با این امتیاز وجود ندارد.' : 'هنوز نظری برای این کالا منتشر نشده است. اولین نفر باشید!') + '</p>') + '</div>' +
        pager + formHTML(d.mine);
      box.removeAttribute('aria-busy');
    }).catch(function (err) { box.innerHTML = '<p class="form-error">' + esc(err.message) + '</p>'; });
  }

  if (box) {
    box.addEventListener('change', function (e) {
      if (e.target.id === 'rv-sort') { rq.sort = e.target.value; rq.page = 1; loadReviews(); }
    });
    box.addEventListener('click', function (e) {
      var r = e.target.closest('[data-rating]');
      if (r) { rq.rating = r.dataset.rating; rq.page = 1; loadReviews(); return; }
      var pg = e.target.closest('[data-rpage]');
      if (pg) { rq.page = Number(pg.dataset.rpage); loadReviews(); }
    });
    box.addEventListener('submit', function (e) {
      if (e.target.id !== 'review-form') return;
      e.preventDefault();
      var form = e.target;
      var files = form.elements['images[]'].files;
      var local = {};
      if (!form.querySelector('input[name="rating"]:checked')) local.rating = 'امتیاز را انتخاب کنید.';
      if (form.elements.title.value.trim().length < 3) local.title = 'عنوان باید حداقل ۳ کاراکتر باشد.';
      if (form.elements.body.value.trim().length < 10) local.body = 'متن نظر باید حداقل ۱۰ کاراکتر باشد.';
      if (files.length > 3) local.images = 'حداکثر ۳ تصویر مجاز است.';
      Array.prototype.forEach.call(files, function (f) {
        if (f.size > 2 * 1024 * 1024) local.images = 'حجم هر تصویر باید کمتر از ۲ مگابایت باشد.';
        if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) === -1) local.images = 'فقط تصاویر JPG، PNG و WebP مجاز است.';
      });
      if (Object.keys(local).length) { AP.api.showErrors(form, { errors: local }); return; }
      var btn = form.querySelector('[type="submit"]');
      btn.disabled = true;
      AP.api.post('products/' + box.dataset.product + '/reviews', new FormData(form)).then(function () {
        AP.toast('نظر شما ثبت شد و پس از بررسی منتشر می‌شود.');
        loadReviews();
      }).catch(function (err) {
        btn.disabled = false;
        AP.api.showErrors(form, err);
        AP.toast(err.message, 'error');
      });
    });
  }

  /* ---------------- Questions ---------------- */
  var qbox = document.getElementById('questions');
  function loadQuestions() {
    AP.api.get('products/' + qbox.dataset.product + '/questions').then(function (list) {
      qbox.innerHTML = (list.length ? '<div class="faq">' + list.map(function (q) {
        return '<details><summary>' + esc(q.question) + '</summary><p>' + (q.answer ? esc(q.answer) : 'به‌زودی پاسخ داده می‌شود.') + '</p></details>';
      }).join('') + '</div>' : '<p class="field__hint">هنوز پرسشی ثبت نشده است.</p>') +
        (AP.boot.user ? '<form class="review-form card-surface" id="question-form" novalidate><h3>پرسش خود را بپرسید</h3>' +
          '<div class="field"><label for="q-text">پرسش</label><textarea class="textarea" id="q-text" name="question" rows="3" maxlength="1000"></textarea><p class="field__error" data-error-for="question"></p></div>' +
          '<button type="submit" class="btn btn--primary btn--sm">ارسال پرسش</button></form>'
          : '<p class="field__hint"><a href="login?next=' + encodeURIComponent(location.pathname) + '">وارد شوید</a> تا پرسش خود را ثبت کنید.</p>');
    });
  }
  if (qbox) {
    qbox.addEventListener('submit', function (e) {
      e.preventDefault();
      var form = e.target;
      AP.api.post('products/' + qbox.dataset.product + '/questions', { question: form.elements.question.value }).then(function () {
        AP.toast('پرسش شما ثبت شد.');
        form.reset();
      }).catch(function (err) { AP.api.showErrors(form, err); });
    });
  }
})();
