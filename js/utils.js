/* Shared helpers: DOM, formatting, text normalisation, safe storage, events. */
(function () {
  'use strict';

  var AP = window.AP = window.AP || {};
  var faNumber = new Intl.NumberFormat('fa-IR');
  var FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
  var AR_DIGITS = '٠١٢٣٤٥٦٧٨٩';

  var utils = {
    $: function (selector, root) { return (root || document).querySelector(selector); },
    $$: function (selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); },

    /** Number with Persian digits and thousands separators. */
    num: function (n) { return faNumber.format(Math.round(Number(n) || 0)); },

    faDigits: function (value) {
      return String(value).replace(/\d/g, function (d) { return FA_DIGITS[d]; });
    },

    enDigits: function (value) {
      return String(value)
        .replace(/[۰-۹]/g, function (d) { return FA_DIGITS.indexOf(d); })
        .replace(/[٠-٩]/g, function (d) { return AR_DIGITS.indexOf(d); });
    },

    /** Normalises Persian text for matching: Arabic ye/kaf, digits, case. */
    normalize: function (value) {
      return utils.enDigits(value || '')
        .toLowerCase()
        .replace(/ي/g, 'ی').replace(/ى/g, 'ی').replace(/ك/g, 'ک').replace(/ة/g, 'ه')
        .replace(/[ً-ٟ]/g, '')
        .replace(/[‌‍‎‏]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
    },

    /** Normalised text without any spaces, so half-space vs. space never matters. */
    compact: function (value) { return utils.normalize(value).replace(/\s/g, ''); },

    escape: function (value) {
      return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    },

    debounce: function (fn, wait) {
      var timer;
      return function () {
        var args = arguments, ctx = this;
        clearTimeout(timer);
        timer = setTimeout(function () { fn.apply(ctx, args); }, wait);
      };
    },

    params: function () { return new URLSearchParams(window.location.search); },

    discount: function (product) {
      if (!product.oldPrice || product.oldPrice <= product.price) return 0;
      return Math.round((1 - product.price / product.oldPrice) * 100);
    },

    icon: function (name, cls) {
      return '<svg class="icon ' + (cls || '') + '" aria-hidden="true" focusable="false"><use href="assets/icons/icons.svg#i-' + name + '"></use></svg>';
    },

    stars: function (rating) {
      var html = '';
      for (var i = 1; i <= 5; i++) {
        var name = rating >= i ? 'star-filled' : (rating >= i - 0.5 ? 'star-half' : 'star');
        html += utils.icon(name, 'icon--star');
      }
      return '<span class="stars" role="img" aria-label="امتیاز ' + utils.faDigits(rating) + ' از ۵">' + html + '</span>';
    },

    /** localStorage wrapper that never throws (private mode, quota, corrupt JSON). */
    store: {
      get: function (key, fallback) {
        try {
          var raw = window.localStorage.getItem(key);
          if (raw === null) return fallback;
          var parsed = JSON.parse(raw);
          return parsed === null || parsed === undefined ? fallback : parsed;
        } catch (err) {
          return fallback;
        }
      },
      set: function (key, value) {
        try {
          window.localStorage.setItem(key, JSON.stringify(value));
          return true;
        } catch (err) {
          return false;
        }
      },
      remove: function (key) {
        try { window.localStorage.removeItem(key); } catch (err) { /* storage unavailable */ }
      }
    },

    emit: function (name, detail) {
      document.dispatchEvent(new CustomEvent(name, { detail: detail }));
    },

    isEmail: function (value) {
      return /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(String(value).trim());
    },

    isMobile: function (value) {
      return /^09\d{9}$/.test(utils.enDigits(String(value).trim()));
    }
  };

  AP.utils = utils;
})();
