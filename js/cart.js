/* Server-side cart (session/cookie based). Totals are always computed by the API. */
(function () {
  'use strict';

  var AP = window.AP;
  var count = Number((AP.boot && AP.boot.cartCount) || 0);

  function publish(summary) {
    count = summary && summary.totals ? summary.totals.count : count;
    AP.utils.emit('ap:cart', { summary: summary, count: count });
    return summary;
  }

  AP.cart = {
    count: function () { return count; },
    load: function () { return AP.api.get('cart').then(publish); },
    /** Adds a product; resolves with { ok, message }. */
    add: function (productId, qty, variantId) {
      return AP.api.post('cart/items', { product_id: Number(productId), quantity: qty || 1, variant_id: variantId || 0 })
        .then(function (summary) { publish(summary); return { ok: true, message: 'به سبد خرید اضافه شد.' }; })
        .catch(function (err) { return { ok: false, message: err.message, errors: err.errors }; });
    },
    setQty: function (itemId, qty) { return AP.api.put('cart/items/' + itemId, { quantity: qty }).then(publish); },
    remove: function (itemId) { return AP.api.del('cart/items/' + itemId).then(publish); },
    applyCoupon: function (code) { return AP.api.post('cart/coupon', { code: code }).then(publish); },
    removeCoupon: function () { return AP.api.del('cart/coupon').then(publish); }
  };
})();
