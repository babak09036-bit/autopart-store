/* Favourites page: product data is always fetched from the API by id. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var grid = document.getElementById('fav-grid');

  function render() {
    var ids = AP.favs.list();
    document.getElementById('fav-guest-note').hidden = AP.favs.loggedIn || !ids.length;
    if (!ids.length) {
      document.getElementById('fav-count').textContent = '';
      document.getElementById('fav-actions').hidden = true;
      document.getElementById('fav-empty').hidden = false;
      grid.hidden = true;
      return;
    }
    AP.api.get('products', { ids: ids, per_page: 60 }).then(function (d) {
      document.getElementById('fav-count').textContent = '(' + u.num(d.items.length) + ' کالا)';
      document.getElementById('fav-actions').hidden = !d.items.length;
      document.getElementById('fav-empty').hidden = d.items.length > 0;
      grid.hidden = !d.items.length;
      grid.innerHTML = d.items.map(function (p) {
        var move = p.inStock ? '<button type="button" class="btn btn--ghost btn--block btn--sm move-cart" data-move="' + p.id + '">انتقال به سبد و حذف از فهرست</button>' : '';
        return AP.card(p, 'default', { extra: move });
      }).join('');
    });
  }

  document.addEventListener('click', function (e) {
    var move = e.target.closest('[data-move]');
    if (move) {
      AP.cart.add(move.dataset.move, 1).then(function (res) {
        AP.toast(res.message, res.ok ? 'success' : 'error');
        if (res.ok) AP.favs.remove(move.dataset.move);
      });
      return;
    }
    if (e.target.closest('[data-all-to-cart]')) {
      var ids = AP.favs.list();
      Promise.all(ids.map(function (id) { return AP.cart.add(id, 1); })).then(function (results) {
        var added = results.filter(function (r) { return r.ok; }).length;
        AP.toast(added ? u.num(added) + ' کالا به سبد خرید اضافه شد.' : 'کالای قابل افزودنی وجود ندارد.', added ? 'success' : 'error');
      });
      return;
    }
    if (e.target.closest('[data-clear-favs]') && window.confirm('همه کالاها از فهرست علاقه‌مندی‌ها حذف شوند؟')) {
      AP.favs.list().slice().reduce(function (p, id) { return p.then(function () { return AP.favs.remove(id); }); }, Promise.resolve());
    }
  });

  document.addEventListener('ap:favs', render);
  render();
})();
