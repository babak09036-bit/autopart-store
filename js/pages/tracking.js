/* Public order tracking: order number + buyer mobile (POST /api/orders/track). */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var esc = u.escape;
  var form = document.getElementById('track-form');
  var error = document.getElementById('track-error');
  var result = document.getElementById('track-result');
  var STEPS = [['pending_payment', 'در انتظار پرداخت'], ['paid', 'پرداخت‌شده'], ['processing', 'در حال پردازش'], ['ready_to_ship', 'آماده ارسال'], ['shipped', 'ارسال‌شده'], ['delivered', 'تحویل‌شده']];

  function normalize(v) {
    v = u.enDigits(v).trim().toUpperCase().replace(/\s/g, '');
    if (/^\d{6}$/.test(v)) v = 'AP-' + v;
    if (/^AP\d{6}$/.test(v)) v = 'AP-' + v.slice(2);
    return v;
  }

  function show(o) {
    var idx = STEPS.map(function (s) { return s[0]; }).indexOf(o.status);
    var line = o.status === 'cancelled' || o.status === 'refunded'
      ? '<p class="alert alert--error">این سفارش ' + esc(o.status_label) + ' است.</p>'
      : '<ol class="timeline timeline--6">' + STEPS.map(function (s, i) {
        var cls = i < idx ? 'is-done' : i === idx ? (i === STEPS.length - 1 ? 'is-done' : 'is-current') : '';
        return '<li class="' + cls + '"' + (i === idx ? ' aria-current="step"' : '') + '><span class="timeline__dot">' + u.icon(cls === 'is-done' ? 'check' : 'clock') + '</span><span>' + s[1] + '</span></li>';
      }).join('') + '</ol>';
    result.innerHTML = '<div class="track__meta"><span>کد سفارش: <strong dir="ltr">' + esc(o.number) + '</strong></span><span>تاریخ ثبت: ' + esc(o.placed_at_fa) + '</span>' +
      '<span>مبلغ کل: <strong>' + u.num(o.total) + ' تومان</strong></span></div>' + line +
      (o.shipment && o.shipment.tracking_code ? '<p>کد رهگیری مرسوله: <strong dir="ltr">' + esc(o.shipment.tracking_code) + '</strong></p>' : '') +
      '<ul class="track__items">' + o.items.map(function (i) { return '<li><span>' + esc(i.title) + ' × ' + u.num(i.quantity) + '</span><span>' + u.num(i.line_total) + ' تومان</span></li>'; }).join('') + '</ul>';
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var number = normalize(form.elements.number.value);
    var mobile = u.enDigits(form.elements.mobile.value.trim());
    form.elements.number.value = number;
    if (!/^AP-\d{6}$/.test(number)) { error.textContent = 'قالب کد سفارش درست نیست؛ نمونه درست: AP-204518'; return; }
    if (!u.isMobile(mobile)) { error.textContent = 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.'; return; }
    error.textContent = '';
    result.innerHTML = '<p class="field__hint">در حال جستجو…</p>';
    AP.api.post('orders/track', { number: number, mobile: mobile }).then(show).catch(function (err) {
      result.innerHTML = '';
      error.textContent = err.message;
    });
  });
})();
