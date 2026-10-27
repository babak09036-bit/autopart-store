/* Favourites page: saved products, move to cart, clear list. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var grid = document.getElementById('fav-grid');

  function render() {
    var items = AP.favs.list().map(AP.catalog.product);
    document.getElementById('fav-count').textContent = items.length ? '(' + u.num(items.length) + ' کالا)' : '';
    document.getElementById('fav-actions').hidden = !items.length;
    document.getElementById('fav-empty').hidden = items.length > 0;
    grid.hidden = !items.length;
    grid.innerHTML = items.map(function (p) {
      var move = p.stock > 0
        ? '<button type="button" class="btn btn--ghost btn--block btn--sm move-cart" data-move="' + p.id + '">انتقال به سبد و حذف از فهرست</button>'
        : '';
      return AP.card(p, 'default', { extra: move });
    }).join('');
  }

  document.addEventListener('click', function (e) {
    var move = e.target.closest('[data-move]');
    if (move) {
      var res = AP.cart.add(move.dataset.move, 1);
      AP.toast(res.message, res.ok ? 'success' : 'error');
      if (res.ok) AP.favs.remove(move.dataset.move);
      return;
    }
    if (e.target.closest('[data-all-to-cart]')) {
      var added = 0;
      AP.favs.list().forEach(function (id) { if (AP.cart.add(id, 1).ok) added++; });
      AP.toast(added ? u.num(added) + ' کالا به سبد خرید اضافه شد.' : 'کالای قابل افزودنی وجود ندارد.', added ? 'success' : 'error');
      return;
    }
    if (e.target.closest('[data-clear-favs]') && window.confirm('همه کالاها از فهرست علاقه‌مندی‌ها حذف شوند؟')) {
      AP.favs.list().forEach(AP.favs.remove);
    }
  });

  document.addEventListener('ap:favs', render);
  render();
})();
