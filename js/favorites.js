/* Favourites (wishlist) persisted in localStorage (key: ap_favs). */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var KEY = 'ap_favs';

  function read() {
    var ids = u.store.get(KEY, []);
    if (!Array.isArray(ids)) return [];
    return ids.map(Number).filter(function (id) { return AP.catalog.product(id); });
  }

  function save(ids) {
    u.store.set(KEY, ids);
    u.emit('ap:favs', { ids: ids });
  }

  AP.favs = {
    list: read,
    count: function () { return read().length; },
    has: function (id) { return read().indexOf(Number(id)) > -1; },

    /** Toggles a product and returns true when it is now a favourite. */
    toggle: function (id) {
      id = Number(id);
      var ids = read();
      var idx = ids.indexOf(id);
      if (idx > -1) ids.splice(idx, 1); else ids.unshift(id);
      save(ids);
      return idx === -1;
    },

    remove: function (id) {
      save(read().filter(function (x) { return x !== Number(id); }));
    }
  };
})();
