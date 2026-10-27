/* Shopping cart persisted in localStorage (key: ap_cart). */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var KEY = 'ap_cart';
  var FREE_SHIPPING_FROM = 2000000;
  var SHIPPING_COST = 85000;

  function read() {
    var items = u.store.get(KEY, []);
    if (!Array.isArray(items)) return [];
    return items.filter(function (it) {
      var p = it && AP.catalog.product(it.id);
      return p && p.stock > 0 && it.qty > 0;
    }).map(function (it) {
      var p = AP.catalog.product(it.id);
      return { id: p.id, qty: Math.min(Math.floor(it.qty), p.stock) };
    });
  }

  function save(items) {
    var ok = u.store.set(KEY, items);
    u.emit('ap:cart', { items: items });
    return ok;
  }

  AP.cart = {
    FREE_SHIPPING_FROM: FREE_SHIPPING_FROM,

    items: read,

    count: function () {
      return read().reduce(function (sum, it) { return sum + it.qty; }, 0);
    },

    qtyOf: function (id) {
      var it = read().filter(function (x) { return x.id === Number(id); })[0];
      return it ? it.qty : 0;
    },

    /** Adds qty of a product; returns { ok, message }. */
    add: function (id, qty) {
      var product = AP.catalog.product(id);
      qty = Math.max(1, Math.floor(qty || 1));
      if (!product) return { ok: false, message: 'محصول یافت نشد.' };
      if (product.stock < 1) return { ok: false, message: 'این کالا در حال حاضر ناموجود است.' };
      var items = read();
      var existing = items.filter(function (x) { return x.id === product.id; })[0];
      var current = existing ? existing.qty : 0;
      if (current >= product.stock) {
        return { ok: false, message: 'بیشتر از موجودی انبار (' + u.num(product.stock) + ' عدد) قابل سفارش نیست.' };
      }
      var next = Math.min(current + qty, product.stock);
      if (existing) existing.qty = next; else items.push({ id: product.id, qty: next });
      if (!save(items)) return { ok: false, message: 'ذخیره‌سازی در مرورگر ممکن نیست.' };
      return { ok: true, message: '«' + product.title + '» به سبد خرید اضافه شد.' };
    },

    setQty: function (id, qty) {
      var product = AP.catalog.product(id);
      if (!product) return;
      var items = read();
      var clamped = Math.max(0, Math.min(Math.floor(qty), product.stock));
      items = items.map(function (x) { return x.id === product.id ? { id: x.id, qty: clamped } : x; })
        .filter(function (x) { return x.qty > 0; });
      save(items);
    },

    remove: function (id) {
      save(read().filter(function (x) { return x.id !== Number(id); }));
    },

    clear: function () { save([]); },

    detailed: function () {
      return read().map(function (it) {
        var p = AP.catalog.product(it.id);
        return { product: p, qty: it.qty, line: p.price * it.qty, lineOld: (p.oldPrice || p.price) * it.qty };
      });
    },

    totals: function () {
      var lines = AP.cart.detailed();
      var subtotalOld = 0, subtotal = 0, count = 0;
      lines.forEach(function (l) { subtotalOld += l.lineOld; subtotal += l.line; count += l.qty; });
      var shipping = count === 0 || subtotal >= FREE_SHIPPING_FROM ? 0 : SHIPPING_COST;
      return {
        count: count,
        subtotalOld: subtotalOld,
        subtotal: subtotal,
        discount: subtotalOld - subtotal,
        shipping: shipping,
        payable: subtotal + shipping
      };
    }
  };
})();
