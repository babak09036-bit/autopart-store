/* Order tracking: looks up demo orders saved by the cart page plus three fixed sample codes. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var STEPS = [['check', 'ثبت سفارش'], ['package-track', 'در حال پردازش'], ['truck', 'تحویل به پست'], ['home', 'تحویل به مشتری']];
  var DAY = 864e5;

  var SAMPLES = [
    { code: 'AP-100245', createdAt: new Date(Date.now() - 6 * DAY).toISOString(), status: 3, items: [{ id: 37, qty: 1, price: 1650000 }, { id: 39, qty: 1, price: 185000 }], shipping: 85000 },
    { code: 'AP-100318', createdAt: new Date(Date.now() - 2 * DAY).toISOString(), status: 2, items: [{ id: 3, qty: 1, price: 2450000 }], shipping: 0 },
    { code: 'AP-100402', createdAt: new Date(Date.now() - 3 * 36e5).toISOString(), status: 1, items: [{ id: 12, qty: 2, price: 245000 }, { id: 15, qty: 1, price: 189000 }], shipping: 85000 }
  ];

  var form = document.getElementById('track-form');
  var input = form.elements.code;
  var error = document.getElementById('track-error');
  var result = document.getElementById('track-result');
  var dateFmt = new Intl.DateTimeFormat('fa-IR', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });

  function saved() {
    var list = u.store.get('ap_orders', []);
    return Array.isArray(list) ? list : [];
  }

  /* Saved demo orders advance with time so the timeline is visible. */
  function statusOf(order) {
    if (typeof order.status === 'number') return order.status;
    var age = Date.now() - new Date(order.createdAt).getTime();
    return age < 5 * 6e4 ? 0 : age < 60 * 6e4 ? 1 : age < DAY ? 2 : 3;
  }

  function normalize(value) {
    var v = u.enDigits(value).trim().toUpperCase().replace(/\s/g, '');
    if (/^\d{6}$/.test(v)) v = 'AP-' + v;
    if (/^AP\d{6}$/.test(v)) v = 'AP-' + v.slice(2);
    return v;
  }

  function find(code) {
    return saved().concat(SAMPLES).filter(function (o) { return o.code === code; })[0] || null;
  }

  function show(order) {
    var status = statusOf(order);
    var total = order.items.reduce(function (s, it) { return s + it.price * it.qty; }, 0) + (order.shipping || 0);
    result.innerHTML = '<div class="track__meta">' +
      '<span>کد سفارش: <strong dir="ltr">' + order.code + '</strong></span>' +
      '<span>تاریخ ثبت: ' + dateFmt.format(new Date(order.createdAt)) + '</span>' +
      '<span>مبلغ کل: <strong>' + u.num(total) + ' تومان</strong></span></div>' +
      '<ol class="timeline">' + STEPS.map(function (s, i) {
        var cls = i < status ? 'is-done' : i === status ? (status === STEPS.length - 1 ? 'is-done' : 'is-current') : '';
        return '<li class="' + cls + '"' + (i === status ? ' aria-current="step"' : '') + '><span class="timeline__dot">' + u.icon(s[0]) + '</span><span>' + s[1] + '</span></li>';
      }).join('') + '</ol>' +
      '<h2 class="visually-hidden">اقلام سفارش</h2><ul class="track__items">' + order.items.map(function (it) {
        var p = AP.catalog.product(it.id);
        return '<li><a href="product-details.html?id=' + it.id + '">' + (p ? u.escape(p.title) : 'کالا') + '</a><span>' + u.num(it.qty) + ' × ' + u.num(it.price) + ' تومان</span></li>';
      }).join('') + '</ul>';
  }

  function lookup(raw) {
    var code = normalize(raw);
    input.value = code;
    if (!code) {
      error.textContent = 'کد پیگیری را وارد کنید.';
      input.setAttribute('aria-invalid', 'true');
      result.innerHTML = '';
      return;
    }
    if (!/^AP-\d{6}$/.test(code)) {
      error.textContent = 'قالب کد پیگیری درست نیست؛ نمونه درست: AP-100245';
      input.setAttribute('aria-invalid', 'true');
      result.innerHTML = '';
      return;
    }
    var order = find(code);
    if (!order) {
      error.textContent = 'سفارشی با این کد پیدا نشد. کد را بررسی کنید.';
      input.setAttribute('aria-invalid', 'true');
      result.innerHTML = '';
      return;
    }
    error.textContent = '';
    input.setAttribute('aria-invalid', 'false');
    show(order);
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    lookup(input.value);
  });

  var codes = saved().slice(0, 3).map(function (o) { return o.code; }).concat(SAMPLES.map(function (o) { return o.code; }));
  document.getElementById('track-samples').innerHTML = '<span>' + (saved().length ? 'سفارش‌های شما و کدهای نمونه:' : 'کدهای نمونه برای امتحان:') + '</span>' +
    codes.map(function (c) { return '<button type="button" data-code="' + c + '">' + c + '</button>'; }).join('');
  form.addEventListener('click', function (e) {
    var b = e.target.closest('[data-code]');
    if (b) lookup(b.dataset.code);
  });

  var fromURL = u.params().get('code');
  if (fromURL) lookup(fromURL);
})();
