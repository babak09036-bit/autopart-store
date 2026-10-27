/* Cart page: line items, quantity controls, totals and a demo checkout. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var root = document.getElementById('cart-root');
  var countEl = document.getElementById('cart-count');
  var lastOrder = null;

  function money(n) { return u.num(n) + ' <small>تومان</small>'; }

  function itemHTML(line) {
    var p = line.product;
    return '<li class="cart-item" data-id="' + p.id + '">' +
      '<a href="product-details.html?id=' + p.id + '" tabindex="-1" aria-hidden="true"><img src="' + p.image + '" alt="" width="110" height="110" loading="lazy"></a>' +
      '<div class="cart-item__info">' +
      '<h2 class="cart-item__title"><a href="product-details.html?id=' + p.id + '">' + u.escape(p.title) + '</a></h2>' +
      '<span class="cart-item__meta">' + AP.catalog.brandName(p.brand) + ' · ' + u.num(p.price) + ' تومان برای هر عدد</span>' +
      (p.stock <= 10 ? '<span class="stock stock--low">تنها ' + u.num(p.stock) + ' عدد در انبار</span>' : '<span class="stock stock--in">' + u.icon('check', 'icon--sm') + 'موجود در انبار</span>') +
      '</div>' +
      '<div class="cart-item__side">' +
      '<div class="cart-item__price">' + (line.lineOld > line.line ? '<del>' + u.num(line.lineOld) + '</del>' : '') + '<strong>' + money(line.line) + '</strong></div>' +
      '<div class="qty" role="group" aria-label="تعداد ' + u.escape(p.title) + '">' +
      '<button type="button" data-inc aria-label="افزایش تعداد"' + (line.qty >= p.stock ? ' disabled' : '') + '>' + u.icon('plus', 'icon--sm') + '</button>' +
      '<input type="text" inputmode="numeric" value="' + u.faDigits(line.qty) + '" aria-label="تعداد" data-qty>' +
      (line.qty > 1 ? '<button type="button" data-dec aria-label="کاهش تعداد">' + u.icon('minus', 'icon--sm') + '</button>'
        : '<button type="button" data-remove-item aria-label="حذف از سبد">' + u.icon('trash', 'icon--sm') + '</button>') +
      '</div>' +
      '<button type="button" class="cart-item__remove" data-remove-item>' + u.icon('trash', 'icon--sm') + 'حذف</button>' +
      '</div></li>';
  }

  function render() {
    var lines = AP.cart.detailed();
    var t = AP.cart.totals();
    countEl.textContent = t.count ? '(' + u.num(t.count) + ' کالا)' : '';
    document.getElementById('cart-suggest').hidden = false;

    if (lastOrder) {
      root.innerHTML = '<div class="order-done" role="status">' + u.icon('check') +
        '<h2>سفارش شما با موفقیت ثبت شد</h2>' +
        '<p>کد پیگیری سفارش:</p><span class="order-code">' + lastOrder.code + '</span>' +
        '<p class="summary__note">این فروشگاه نمایشی است و هیچ پرداخت واقعی انجام نشده است.</p>' +
        '<div class="fav-actions"><a class="btn btn--primary" href="tracking.html?code=' + lastOrder.code + '">' + u.icon('package-track', 'icon--sm') + 'پیگیری سفارش</a>' +
        '<a class="btn btn--light" href="products.html">ادامه خرید</a></div></div>';
      return;
    }

    if (!lines.length) {
      root.innerHTML = '<div class="empty-state empty-state--panel">' +
        '<img src="assets/images/ui/empty-cart.svg" alt="" width="320" height="240">' +
        '<h2>سبد خرید شما خالی است</h2><p>می‌توانید برای مشاهده محصولات بیشتر به صفحات زیر بروید.</p>' +
        '<div class="fav-actions"><a class="btn btn--primary" href="products.html">مشاهده محصولات</a>' +
        '<a class="btn btn--light" href="products.html?amazing=1">پیشنهادهای شگفت‌انگیز</a></div></div>';
      return;
    }

    var remaining = AP.cart.FREE_SHIPPING_FROM - t.subtotal;
    var progress = Math.min(100, Math.round(t.subtotal / AP.cart.FREE_SHIPPING_FROM * 100));

    root.innerHTML = '<div class="cart-layout">' +
      '<section aria-label="کالاهای سبد"><ul class="cart-list">' + lines.map(itemHTML).join('') + '</ul>' +
      '<div class="fav-actions cart-actions"><button type="button" class="btn btn--light btn--sm" data-clear>' + u.icon('trash', 'icon--sm') + 'حذف همه کالاها</button>' +
      '<a class="btn btn--light btn--sm" href="products.html">ادامه خرید</a></div></section>' +
      '<aside class="summary" aria-label="خلاصه سفارش">' +
      '<div class="summary__row"><span>قیمت کالاها (' + u.num(t.count) + ')</span><span>' + money(t.subtotalOld) + '</span></div>' +
      (t.discount ? '<div class="summary__row summary__row--discount"><span>سود شما از خرید</span><span>' + money(t.discount) + '</span></div>' : '') +
      '<div class="summary__row"><span>هزینه ارسال</span><span>' + (t.shipping ? money(t.shipping) : '<strong class="stock--in">رایگان</strong>') + '</span></div>' +
      '<div class="summary__row summary__row--total"><span>مبلغ قابل پرداخت</span><span>' + money(t.payable) + '</span></div>' +
      '<div class="ship-progress">' +
      (remaining > 0 ? '<span>' + u.num(remaining) + ' تومان دیگر تا ارسال رایگان</span>' : '<span>' + u.icon('truck', 'icon--sm') + ' ارسال این سفارش رایگان است</span>') +
      '<div class="stockbar__track" role="progressbar" aria-label="پیشرفت تا ارسال رایگان" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + progress + '"><span style="width:' + progress + '%"></span></div></div>' +
      '<button type="button" class="btn btn--primary btn--block" data-checkout>ثبت سفارش</button>' +
      '<p class="summary__note">این فروشگاه نمایشی است؛ با ثبت سفارش پرداخت واقعی انجام نمی‌شود و فقط یک کد پیگیری نمونه ساخته می‌شود.</p>' +
      '</aside></div>';
  }

  function makeCode() {
    var orders = u.store.get('ap_orders', []);
    var code;
    do { code = 'AP-' + (200000 + Math.floor(Math.random() * 800000)); }
    while (Array.isArray(orders) && orders.some(function (o) { return o.code === code; }));
    return code;
  }

  function checkout() {
    var lines = AP.cart.detailed();
    if (!lines.length) return;
    var t = AP.cart.totals();
    var order = {
      code: makeCode(),
      createdAt: new Date().toISOString(),
      items: lines.map(function (l) { return { id: l.product.id, qty: l.qty, price: l.product.price }; }),
      payable: t.payable,
      shipping: t.shipping
    };
    var orders = u.store.get('ap_orders', []);
    if (!Array.isArray(orders)) orders = [];
    orders.unshift(order);
    if (!u.store.set('ap_orders', orders.slice(0, 20))) {
      AP.toast('ثبت سفارش در مرورگر ممکن نیست.', 'error');
      return;
    }
    lastOrder = order;
    AP.cart.clear();
    AP.toast('سفارش ' + order.code + ' ثبت شد.');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  root.addEventListener('click', function (e) {
    var item = e.target.closest('.cart-item');
    var id = item && Number(item.dataset.id);
    if (e.target.closest('[data-inc]')) AP.cart.setQty(id, AP.cart.qtyOf(id) + 1);
    else if (e.target.closest('[data-dec]')) AP.cart.setQty(id, AP.cart.qtyOf(id) - 1);
    else if (e.target.closest('[data-remove-item]')) {
      AP.cart.remove(id);
      AP.toast('کالا از سبد خرید حذف شد.', 'info');
    } else if (e.target.closest('[data-clear]')) {
      if (window.confirm('همه کالاها از سبد خرید حذف شوند؟')) AP.cart.clear();
    } else if (e.target.closest('[data-checkout]')) checkout();
  });

  root.addEventListener('change', function (e) {
    if (!e.target.matches('[data-qty]')) return;
    var id = Number(e.target.closest('.cart-item').dataset.id);
    var p = AP.catalog.product(id);
    var n = Math.floor(Number(u.enDigits(e.target.value)));
    if (!n || n < 1) n = 1;
    if (n > p.stock) AP.toast('حداکثر موجودی این کالا ' + u.num(p.stock) + ' عدد است.', 'error');
    AP.cart.setQty(id, n);
  });

  document.addEventListener('ap:cart', render);

  var suggestions = AP.data.products.filter(function (p) { return p.isBestseller && p.stock > 0; });
  document.getElementById('suggest-track').innerHTML = AP.cards(suggestions);
  render();
  AP.initCarousels();
})();
