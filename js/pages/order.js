/* Order confirmation / status page (owner or the guest session that placed it). Includes the sandbox payment step. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var esc = u.escape;
  var root = document.getElementById('order-root');
  var number = root.dataset.number;
  var STEPS = [['pending_payment', 'در انتظار پرداخت'], ['paid', 'پرداخت‌شده'], ['processing', 'در حال پردازش'], ['ready_to_ship', 'آماده ارسال'], ['shipped', 'ارسال‌شده'], ['delivered', 'تحویل‌شده']];

  function timeline(status) {
    if (status === 'cancelled' || status === 'refunded') {
      return '<p class="alert alert--error">' + u.icon('alert', 'icon--sm') + 'این سفارش ' + (status === 'cancelled' ? 'لغو' : 'مسترد') + ' شده است.</p>';
    }
    var idx = STEPS.map(function (s) { return s[0]; }).indexOf(status);
    return '<ol class="timeline timeline--6">' + STEPS.map(function (s, i) {
      var cls = i < idx ? 'is-done' : i === idx ? (i === STEPS.length - 1 ? 'is-done' : 'is-current') : '';
      return '<li class="' + cls + '"' + (i === idx ? ' aria-current="step"' : '') + '><span class="timeline__dot">' + u.icon(i < idx || cls === 'is-done' ? 'check' : 'clock') + '</span><span>' + s[1] + '</span></li>';
    }).join('') + '</ol>';
  }

  function render(o) {
    var pay = '';
    if (o.test_payment_available) {
      pay = '<div class="pay-box card-surface"><h2>پرداخت آزمایشی</h2>' +
        '<p>این فروشگاه در حالت آزمایشی اجرا می‌شود؛ هیچ پولی جابه‌جا نمی‌شود و هیچ درگاه بانکی واقعی متصل نیست. برای ادامه، نتیجه پرداخت را شبیه‌سازی کنید.</p>' +
        '<div class="fav-actions"><button type="button" class="btn btn--primary" data-pay="success">شبیه‌سازی پرداخت موفق</button>' +
        '<button type="button" class="btn btn--light" data-pay="cancel">شبیه‌سازی لغو پرداخت</button></div></div>';
    } else if (o.status === 'pending_payment') {
      pay = '<div class="pay-box card-surface"><p>سفارش شما ثبت شد و در انتظار پرداخت است. درگاه پرداخت آنلاین هنوز فعال نشده؛ کارشناسان برای هماهنگی پرداخت با شما تماس می‌گیرند.</p></div>';
    }
    root.innerHTML = '<div class="order-done card-surface">' + u.icon(o.status === 'pending_payment' ? 'clock' : 'check') +
      '<h2>' + (o.status === 'pending_payment' ? 'سفارش ثبت شد' : 'وضعیت سفارش: ' + esc(o.status_label)) + '</h2>' +
      '<span class="order-code">' + esc(o.number) + '</span><p class="field__hint">ثبت در ' + esc(o.placed_at_fa) + '</p></div>' +
      pay + '<section class="track__result">' + timeline(o.status) +
      (o.shipment && o.shipment.tracking_code ? '<p>کد رهگیری مرسوله: <strong dir="ltr">' + esc(o.shipment.tracking_code) + '</strong>' + (o.shipment.carrier ? ' (' + esc(o.shipment.carrier) + ')' : '') + '</p>' : '') +
      '<ul class="track__items">' + o.items.map(function (i) {
        return '<li><span>' + (i.url ? '<a href="' + esc(i.url) + '">' + esc(i.title) + '</a>' : esc(i.title)) + (i.variant ? ' (' + esc(i.variant) + ')' : '') + ' × ' + u.num(i.quantity) + '</span><span>' + u.num(i.line_total) + ' تومان</span></li>';
      }).join('') +
      (o.discount ? '<li><span>تخفیف</span><span>−' + u.num(o.discount) + ' تومان</span></li>' : '') +
      '<li><span>هزینه ارسال</span><span>' + (o.shipping ? u.num(o.shipping) + ' تومان' : 'رایگان') + '</span></li>' +
      '<li><strong>مبلغ کل</strong><strong>' + u.num(o.total) + ' تومان</strong></li></ul></section>' +
      '<div class="fav-actions"><a class="btn btn--light" href="products">ادامه خرید</a>' + (AP.boot.user ? '<a class="btn btn--light" href="account">سفارش‌های من</a>' : '<a class="btn btn--light" href="tracking">پیگیری سفارش</a>') + '</div>';
  }

  function load() {
    AP.api.get('orders/' + encodeURIComponent(number)).then(render).catch(function (err) {
      root.innerHTML = '<div class="empty-state empty-state--panel"><h2>' + esc(err.message) + '</h2><p>برای مشاهده سفارش‌های قبلی وارد حساب شوید یا از صفحه پیگیری سفارش استفاده کنید.</p><a class="btn btn--primary" href="tracking">پیگیری سفارش</a></div>';
    });
  }

  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-pay]');
    if (!b) return;
    b.disabled = true;
    AP.api.post('orders/' + encodeURIComponent(number) + '/test-payment', { result: b.dataset.pay }).then(function (res) {
      AP.toast(res.success ? 'پرداخت آزمایشی ثبت شد.' : 'پرداخت لغو شد.', res.success ? 'success' : 'info');
      load();
    }).catch(function (err) { b.disabled = false; AP.toast(err.message, 'error'); });
  });

  AP.cart.load();
  load();
})();
