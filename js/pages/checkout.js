/* Checkout: buyer details + address, then POST /api/orders with an Idempotency-Key (one per attempt, survives double clicks). */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var esc = u.escape;
  var root = document.getElementById('checkout-root');
  var KEY = 'ap_checkout_key';

  function idempotencyKey() {
    var k = u.store.get(KEY, null);
    if (!k) {
      k = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now().toString(16) + Math.random().toString(16).slice(2) + Math.random().toString(16).slice(2));
      u.store.set(KEY, k);
    }
    return k;
  }

  function field(name, label, attrs, full) {
    return '<div class="field' + (full ? ' field--full' : '') + '"><label for="co-' + name + '">' + label + '</label>' +
      (attrs.textarea ? '<textarea class="textarea" id="co-' + name + '" name="' + name + '" rows="3">' + esc(attrs.value || '') + '</textarea>'
        : '<input class="input" id="co-' + name + '" name="' + name + '" ' + (attrs.extra || '') + ' value="' + esc(attrs.value || '') + '">') +
      '<p class="field__error" data-error-for="' + name + '"></p></div>';
  }

  function render(cart, profile, addresses) {
    if (!cart.items.length) {
      root.innerHTML = '<div class="empty-state empty-state--panel"><img src="assets/images/ui/empty-cart.svg" alt="" width="320" height="240"><h2>سبد خرید شما خالی است</h2><a class="btn btn--primary" href="products">مشاهده محصولات</a></div>';
      return;
    }
    var t = cart.totals;
    var addr = addresses.filter(function (a) { return Number(a.is_default); })[0] || addresses[0] || {};
    root.innerHTML = '<div class="cart-layout"><form class="checkout-form card-surface" id="checkout-form" novalidate>' +
      (addresses.length ? '<div class="field field--full"><label for="co-saved">نشانی‌های ذخیره‌شده</label><select class="select" id="co-saved">' +
        addresses.map(function (a, i) { return '<option value="' + i + '"' + (a === addr ? ' selected' : '') + '>' + esc(a.title + ' — ' + a.city + '، ' + a.address_line) + '</option>'; }).join('') + '</select></div>' : '') +
      '<h2 class="field--full">اطلاعات گیرنده</h2>' +
      field('customer_name', 'نام و نام خانوادگی', { value: addr.receiver_name || (profile && profile.full_name) || '', extra: 'autocomplete="name"' }) +
      field('customer_mobile', 'شماره موبایل', { value: addr.receiver_mobile || (profile && profile.mobile) || '', extra: 'type="tel" dir="ltr" inputmode="numeric" autocomplete="tel" placeholder="09xxxxxxxxx"' }) +
      field('customer_email', 'ایمیل (اختیاری)', { value: (profile && profile.email) || '', extra: 'type="email" dir="ltr" autocomplete="email"' }, true) +
      '<h2 class="field--full">نشانی ارسال</h2>' +
      field('province', 'استان', { value: addr.province || '', extra: 'autocomplete="address-level1"' }) +
      field('city', 'شهر', { value: addr.city || '', extra: 'autocomplete="address-level2"' }) +
      field('address_line', 'نشانی کامل', { textarea: true, value: addr.address_line || '' }, true) +
      field('postal_code', 'کد پستی ۱۰ رقمی (اختیاری)', { value: addr.postal_code || '', extra: 'dir="ltr" inputmode="numeric" autocomplete="postal-code"' }) +
      field('customer_note', 'توضیحات سفارش (اختیاری)', { textarea: true }, true) +
      (AP.boot.user && !addresses.length ? '<label class="check field--full"><input type="checkbox" name="save_address" value="1" checked><span>ذخیره این نشانی در حساب کاربری</span></label>' : '') +
      (!AP.boot.user ? '<p class="field__hint field--full">بدون ثبت‌نام هم می‌توانید خرید کنید؛ برای دیدن سفارش‌ها در حساب کاربری، <a href="login?next=checkout">وارد شوید</a>.</p>' : '') +
      '<div class="alert field--full" role="alert" hidden></div>' +
      '<button type="submit" class="btn btn--primary btn--block field--full">ثبت سفارش و پرداخت</button></form>' +
      '<aside class="summary" aria-label="خلاصه سفارش"><h2>خلاصه سفارش</h2><ul class="checkout-lines">' + cart.items.filter(function (i) { return i.available; }).map(function (i) {
        return '<li><span>' + esc(i.title) + (i.variant_name ? ' (' + esc(i.variant_name) + ')' : '') + ' × ' + u.num(i.quantity) + '</span><span>' + u.num(i.line_total) + '</span></li>';
      }).join('') + '</ul>' +
      '<div class="summary__row"><span>جمع کالاها</span><span>' + u.num(t.subtotal) + ' <small>تومان</small></span></div>' +
      (cart.coupon ? '<div class="summary__row summary__row--discount"><span>کد تخفیف</span><span>' + u.num(cart.coupon.discount) + ' <small>تومان</small></span></div>' : '') +
      '<div class="summary__row"><span>هزینه ارسال</span><span>' + (t.shipping ? u.num(t.shipping) + ' <small>تومان</small>' : 'رایگان') + '</span></div>' +
      '<div class="summary__row summary__row--total"><span>مبلغ قابل پرداخت</span><span>' + u.num(t.grand_total) + ' <small>تومان</small></span></div>' +
      '<a class="link-more" href="cart">ویرایش سبد خرید</a></aside></div>';

    var saved = document.getElementById('co-saved');
    if (saved) saved.addEventListener('change', function () {
      var a = addresses[Number(saved.value)];
      var f = document.getElementById('checkout-form').elements;
      f.customer_name.value = a.receiver_name; f.customer_mobile.value = a.receiver_mobile; f.province.value = a.province;
      f.city.value = a.city; f.address_line.value = a.address_line; f.postal_code.value = a.postal_code || '';
    });
  }

  root.addEventListener('submit', function (e) {
    if (e.target.id !== 'checkout-form') return;
    e.preventDefault();
    var form = e.target;
    var data = {};
    ['customer_name', 'customer_mobile', 'customer_email', 'province', 'city', 'address_line', 'postal_code', 'customer_note'].forEach(function (k) { data[k] = u.enDigits(form.elements[k].value.trim()); });
    if (form.elements.save_address) data.save_address = form.elements.save_address.checked;
    var local = {};
    if (data.customer_name.length < 3) local.customer_name = 'نام و نام خانوادگی را کامل وارد کنید.';
    if (!u.isMobile(data.customer_mobile)) local.customer_mobile = 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.';
    if (!data.province) local.province = 'استان را وارد کنید.';
    if (!data.city) local.city = 'شهر را وارد کنید.';
    if (data.address_line.length < 10) local.address_line = 'نشانی کامل را وارد کنید (حداقل ۱۰ کاراکتر).';
    if (data.postal_code && !/^\d{10}$/.test(data.postal_code)) local.postal_code = 'کد پستی باید ۱۰ رقم باشد.';
    if (Object.keys(local).length) { AP.api.showErrors(form, { errors: local }); return; }
    var btn = form.querySelector('[type="submit"]');
    var alertBox = form.querySelector('.alert');
    btn.disabled = true;
    btn.textContent = 'در حال ثبت سفارش…';
    AP.api.post('orders', data, { headers: { 'Idempotency-Key': idempotencyKey() } }).then(function (res) {
      u.store.remove(KEY);
      var number = res.order.number;
      if (res.payment && res.payment.redirect_url) {
        location.href = 'orders/' + encodeURIComponent(number) + '?pay=1';
      } else {
        location.href = 'orders/' + encodeURIComponent(number);
      }
    }).catch(function (err) {
      btn.disabled = false;
      btn.textContent = 'ثبت سفارش و پرداخت';
      AP.api.showErrors(form, err);
      alertBox.className = 'alert alert--error field--full';
      alertBox.innerHTML = u.icon('alert', 'icon--sm') + '<span>' + esc(err.message) + '</span>' + (err.status === 409 ? ' <a href="cart">بازبینی سبد خرید</a>' : '');
      alertBox.hidden = false;
    });
  });

  var profileReq = AP.boot.user ? AP.api.get('account/profile').catch(function () { return null; }) : Promise.resolve(null);
  var addrReq = AP.boot.user ? AP.api.get('account/addresses').catch(function () { return []; }) : Promise.resolve([]);
  Promise.all([AP.api.get('cart'), profileReq, addrReq]).then(function (r) { render(r[0], r[1], r[2]); })
    .catch(function (err) { root.innerHTML = '<p class="form-error">' + esc(err.message) + '</p>'; });
})();
