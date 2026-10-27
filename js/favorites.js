/* Favourites: stored in the account for logged-in users, in localStorage for guests (synced on login). */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var KEY = 'ap_favs';
  var serverIds = AP.boot && Array.isArray(AP.boot.wishlist) ? AP.boot.wishlist.slice() : null;
  var loggedIn = serverIds !== null;

  function localIds() {
    var ids = u.store.get(KEY, []);
    return Array.isArray(ids) ? ids.map(Number).filter(Boolean) : [];
  }

  function ids() { return loggedIn ? serverIds : localIds(); }

  function publish() { u.emit('ap:favs', { ids: ids() }); }

  AP.favs = {
    loggedIn: loggedIn,
    list: ids,
    count: function () { return ids().length; },
    has: function (id) { return ids().indexOf(Number(id)) > -1; },
    localIds: localIds,
    clearLocal: function () { u.store.remove(KEY); },

    /** Resolves with true when the product is now a favourite. */
    toggle: function (id) {
      id = Number(id);
      var on = !AP.favs.has(id);
      if (!loggedIn) {
        var list = localIds().filter(function (x) { return x !== id; });
        if (on) list.unshift(id);
        u.store.set(KEY, list);
        publish();
        return Promise.resolve(on);
      }
      var req = on ? AP.api.post('wishlist/items', { product_id: id }) : AP.api.del('wishlist/items/' + id);
      return req.then(function () {
        serverIds = serverIds.filter(function (x) { return x !== id; });
        if (on) serverIds.unshift(id);
        publish();
        return on;
      });
    },

    remove: function (id) {
      return AP.favs.has(id) ? AP.favs.toggle(id) : Promise.resolve(false);
    }
  };
})();
