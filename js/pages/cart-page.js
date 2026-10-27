/* Cart page — all prices, discounts and shipping come from GET /api/cart. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var esc = u.escape;
  var root = document.getElementById('cart-root');
  var countEl = document.getElementById('cart-count');

  function money(n) { return u.num(n) + ' <small>تومان</small>'; }

  function itemHTML(it) {
    return '<li class="cart-item' + (it.available ? '' : ' is-unavailable') + '" data-id="' + it.id + '">' +
      '<a href="' + esc(it.url) + '" tabindex="-1" aria-hidden="true"><img src="' + esc(it.image) + '" alt="" width="110" height="110" loading="lazy"></a>' +
      '<div class="cart-item__info"><h2 class="cart-item__title"><a href="' + esc(it.url) + '">' + esc(it.title) + '</a></h2>' +
      '<span class="cart-item__meta">' + esc(it.brand || '') + (it.variant_name ? ' · ' + esc(it.variant_name) : '') + ' · ' + u.num(it.unit_price) + ' تومان برای هر عدد</span>' +
      (!it.available ? '<span class="stock stock--out">قابل خرید نیست</span>' : it.stock <= 10 ? '<span class="stock stock--low">تنها ' + u.num(it.stock) + ' عدد در انبار</span>' : '<span class="stock stock--in">' + u.icon('check', 'icon--sm') + 'موجود در انبار</span>') +
      '</div><div class="cart-item__side">' +
      '<div class="cart-item__price">' + (it.line_original > it.line_total ? '<del>' + u.num(it.line_original) + '</del>' : '') + '<strong>' + money(it.line_total) + '</strong></div>' +
      (it.available ? '<div class="qty" role="group" aria-label="تعداد ' + esc(it.title) + '">' +
        '<button type="button" data-inc aria-label="افزایش تعداد"' + (it.quantity >= it.stock ? ' disabled' : '') + '>' + u.icon('plus', 'icon--sm') + '</button>' +
        '<input type="text" inputmode="numeric" value="' + u.faDigits(it.quantity) + '" aria-label="تعداد" data-qty>' +
        (it.quantity > 1 ? '<button type="button" data-dec aria-label="کاهش تعداد">' + u.icon('minus', 'icon--sm') + '</button>' : '<button type="button" data-remove-item aria-label="حذف از سبد">' + u.icon('trash', 'icon--sm') + '</button>') +
        '</div>' : '') +
      '<button type="button" class="cart-item__remove" data-remove-item>' + u.icon('trash', 'icon--sm') + 'حذف</button></div></li>';
  }

  function render(cart) {
    var t = cart.totals;
    countEl.textContent = t.count ? '(' + u.num(t.count) + ' کالا)' : '';
    if (!cart.items.length) {
      root.innerHTML = '<div class="empty-state empty-state--panel"><img src="assets/images/ui/empty-cart.svg" alt="" width="320" height="240">' +
        '<h2>سبد خرید شما خالی است</h2><p>می‌توانید برای مشاهده محصولات بیشتر به صفحات زیر بروید.</p>' +
        '<div class="fav-actions"><a class="btn btn--primary" href="products">مشاهده محصولات</a><a class="btn btn--light" href="products?amazing=1">پیشنهادهای شگفت‌انگیز</a></div></div>';
      return;
    }
    var remaining = t.free_shipping_threshold - (t.subtotal - t.coupon_discount);
    var progress = t.free_shipping_threshold ? Math.min(100, Math.round((t.subtotal - t.coupon_discount) / t.free_shipping_threshold * 100)) : 100;
    root.innerHTML = (cart.notices.length ? '<div class="alert alert--error">' + u.icon('alert', 'icon--sm') + '<div>' + cart.notices.map(esc).join('<br>') + '</div></div>' : '') +
      '<div class="cart-layout"><section aria-label="کالاهای سبد"><ul class="cart-list">' + cart.items.map(itemHTML).join('') + '</ul>' +
      '<div class="fav-actions cart-actions"><a class="btn btn--light btn--sm" href="products">ادامه خرید</a></div></section>' +
      '<aside class="summary" aria-label="خلاصه سفارش">' +
      '<div class="summary__row"><span>قیمت کالاها (' + u.num(t.count) + ')</span><span>' + money(t.subtotal_original) + '</span></div>' +
      (t.product_discount ? '<div class="summary__row summary__row--discount"><span>تخفیف کالاها</span><span>' + money(t.product_discount) + '</span></div>' : '') +
      (cart.coupon ? '<div class="summary__row summary__row--discount"><span>کد تخفیف <b dir="ltr">' + esc(cart.coupon.code) + '</b> <button type="button" class="link-btn" data-remove-coupon>حذف</button></span><span>' + money(cart.coupon.discount) + '</span></div>' : '') +
      '<div class="summary__row"><span>هزینه ارسال</span><span>' + (t.shipping ? money(t.shipping) : '<strong class="stock--in">رایگان</strong>') + '</span></div>' +
      '<div class="summary__row summary__row--total"><span>مبلغ قابل پرداخت</span><span>' + money(t.grand_total) + '</span></div>' +
      (t.free_shipping_threshold ? '<div class="ship-progress">' + (remaining > 0 ? '<span>' + u.num(remaining) + ' تومان دیگر تا ارسال رایگان</span>' : '<span>' + u.icon('truck', 'icon--sm') + ' ارسال این سفارش رایگان است</span>') +
        '<div class="stockbar__track" role="progressbar" aria-label="پیشرفت تا ارسال رایگان" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + progress + '"><span style="width:' + progress + '%"></span></div></div>' : '') +
      (!cart.coupon ? '<form class="coupon-form" id="coupon-form" novalidate><label class="visually-hidden" for="coupon-code">کد تخفیف</label>' +
        '<input class="input" id="coupon-code" name="code" dir="ltr" placeholder="کد تخفیف" autocomplete="off"><button type="submit" class="btn btn--light btn--sm">اعمال</button>' +
        '<p class="field__error" data-error-for="code"></p></form>' : '') +
      (t.meets_minimum ? '<a class="btn btn--primary btn--block" href="checkout">ادامه فرایند خرید</a>'
        : '<p class="form-error">حداقل مبلغ سفارش ' + u.num(t.min_order_amount) + ' تومان است.</p><button class="btn btn--block" disabled>ادامه فرایند خرید</button>') +
      '<p class="summary__note">قیمت نهایی هنگام ثبت سفارش دوباره از سمت سرور محاسبه و موجودی کالاها بررسی می‌شود.</p></aside></div>';
  }

  function fail(err) { AP.toast(err.message, 'error'); AP.cart.load(); }

  root.addEventListener('click', function (e) {
    var item = e.target.closest('.cart-item');
    var id = item && Number(item.dataset.id);
    var qtyNow = item ? Number(u.enDigits(item.querySelector('[data-qty]') ? item.querySelector('[data-qty]').value : '1')) : 0;
    if (e.target.closest('[data-inc]')) AP.cart.setQty(id, qtyNow + 1).catch(fail);
    else if (e.target.closest('[data-dec]')) AP.cart.setQty(id, qtyNow - 1).catch(fail);
    else if (e.target.closest('[data-remove-item]')) AP.cart.remove(id).then(function () { AP.toast('کالا از سبد خرید حذف شد.', 'info'); }).catch(fail);
    else if (e.target.closest('[data-remove-coupon]')) AP.cart.removeCoupon().catch(fail);
  });
  root.addEventListener('change', function (e) {
    if (!e.target.matches('[data-qty]')) return;
    var n = Math.floor(Number(u.enDigits(e.target.value)));
    AP.cart.setQty(Number(e.target.closest('.cart-item').dataset.id), n > 0 ? n : 1).catch(fail);
  });
  root.addEventListener('submit', function (e) {
    if (e.target.id !== 'coupon-form') return;
    e.preventDefault();
    var form = e.target;
    AP.cart.applyCoupon(form.elements.code.value.trim()).then(function () { AP.toast('کد تخفیف اعمال شد.'); })
      .catch(function (err) { AP.api.showErrors(form, err.errors && err.errors.code ? err : { errors: { code: err.message } }); });
  });

  document.addEventListener('ap:cart', function (e) { if (e.detail && e.detail.summary) render(e.detail.summary); });
  AP.cart.load().catch(function (err) { root.innerHTML = '<p class="form-error">' + esc(err.message) + '</p>'; });

  AP.api.get('products', { best: 1, instock: 1, per_page: 10 }).then(function (d) {
    if (!d.items.length) return;
    document.getElementById('suggest-track').innerHTML = AP.cards(d.items);
    document.getElementById('cart-suggest').hidden = false;
    AP.initCarousels();
  });
})();
