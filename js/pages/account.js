/* Customer account: orders, profile + password, addresses, my reviews, notifications. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var esc = u.escape;
  var panel = document.getElementById('account-panel');

  function err(e) { panel.innerHTML = '<p class="form-error">' + esc(e.message) + '</p>'; }

  var views = {
    orders: function () {
      return AP.api.get('account/orders').then(function (list) {
        panel.innerHTML = '<h2>سفارش‌های من</h2>' + (list.length ? '<div class="table-wrap"><table class="data-table"><thead><tr><th>کد سفارش</th><th>تاریخ</th><th>مبلغ</th><th>وضعیت</th><th></th></tr></thead><tbody>' +
          list.map(function (o) {
            return '<tr><td dir="ltr">' + esc(o.number) + '</td><td>' + esc(o.placed_at_fa) + '</td><td>' + u.num(o.total) + ' تومان</td><td><span class="status status--' + esc(o.status) + '">' + esc(o.status_label) + '</span></td>' +
              '<td><a class="link-more" href="orders/' + encodeURIComponent(o.number) + '">جزئیات</a></td></tr>';
          }).join('') + '</tbody></table></div>' : '<p class="field__hint">هنوز سفارشی ثبت نکرده‌اید. <a href="products">مشاهده محصولات</a></p>');
      });
    },
    profile: function () {
      return AP.api.get('account/profile').then(function (p) {
        panel.innerHTML = '<h2>اطلاعات حساب</h2><p class="field__hint">عضو از ' + esc(p.member_since) + ' · ' + u.num(p.orders_count) + ' سفارش</p>' +
          '<form class="account-form" id="profile-form" novalidate>' +
          '<div class="field"><label for="pf-name">نام و نام خانوادگی</label><input class="input" id="pf-name" name="full_name" value="' + esc(p.full_name) + '"><p class="field__error" data-error-for="full_name"></p></div>' +
          '<div class="field"><label>شماره موبایل</label><input class="input" value="' + esc(p.mobile || '') + '" dir="ltr" disabled></div>' +
          '<div class="field"><label for="pf-email">ایمیل</label><input class="input" id="pf-email" name="email" type="email" dir="ltr" value="' + esc(p.email || '') + '"><p class="field__error" data-error-for="email"></p></div>' +
          '<button type="submit" class="btn btn--primary">ذخیره</button></form>' +
          '<h2>تغییر رمز عبور</h2><form class="account-form" id="password-form" novalidate>' +
          '<div class="field"><label for="pw-cur">رمز عبور فعلی</label><input class="input" id="pw-cur" name="current_password" type="password" dir="ltr" autocomplete="current-password"><p class="field__error" data-error-for="current_password"></p></div>' +
          '<div class="field"><label for="pw-new">رمز عبور جدید</label><input class="input" id="pw-new" name="password" type="password" dir="ltr" autocomplete="new-password"><p class="field__error" data-error-for="password"></p></div>' +
          '<div class="field"><label for="pw-conf">تکرار رمز عبور جدید</label><input class="input" id="pw-conf" name="password_confirmation" type="password" dir="ltr" autocomplete="new-password"><p class="field__error" data-error-for="password_confirmation"></p></div>' +
          '<button type="submit" class="btn btn--primary">تغییر رمز</button></form>';
      });
    },
    addresses: function () {
      return AP.api.get('account/addresses').then(function (list) {
        panel.innerHTML = '<h2>نشانی‌ها</h2><div class="address-list">' + (list.length ? list.map(function (a) {
          return '<div class="address-card card-surface" data-id="' + a.id + '"><strong>' + esc(a.title) + (Number(a.is_default) ? ' <span class="badge badge--green">پیش‌فرض</span>' : '') + '</strong>' +
            '<p>' + esc(a.province + '، ' + a.city + '، ' + a.address_line) + '</p><p class="field__hint">' + esc(a.receiver_name) + ' · <span dir="ltr">' + esc(a.receiver_mobile) + '</span></p>' +
            '<button type="button" class="link-btn" data-del-address="' + a.id + '">حذف</button></div>';
        }).join('') : '<p class="field__hint">نشانی ثبت نشده است.</p>') + '</div>' +
          '<h3>افزودن نشانی</h3><form class="account-form account-form--grid" id="address-form" novalidate>' +
          [['title', 'عنوان (مثلاً خانه)'], ['receiver_name', 'نام گیرنده'], ['receiver_mobile', 'موبایل گیرنده'], ['province', 'استان'], ['city', 'شهر'], ['postal_code', 'کد پستی (اختیاری)']].map(function (f) {
            return '<div class="field"><label for="ad-' + f[0] + '">' + f[1] + '</label><input class="input" id="ad-' + f[0] + '" name="' + f[0] + '"' + (/mobile|postal/.test(f[0]) ? ' dir="ltr" inputmode="numeric"' : '') + '><p class="field__error" data-error-for="' + f[0] + '"></p></div>';
          }).join('') +
          '<div class="field field--full"><label for="ad-line">نشانی کامل</label><textarea class="textarea" id="ad-line" name="address_line" rows="2"></textarea><p class="field__error" data-error-for="address_line"></p></div>' +
          '<label class="check field--full"><input type="checkbox" name="is_default" value="1"><span>نشانی پیش‌فرض</span></label>' +
          '<button type="submit" class="btn btn--primary">ذخیره نشانی</button></form>';
      });
    },
    reviews: function () {
      return AP.api.get('account/reviews').then(function (list) {
        panel.innerHTML = '<h2>نظرات من</h2>' + (list.length ? '<ul class="my-reviews">' + list.map(function (r) {
          return '<li class="card-surface"><a href="' + esc(r.product.url) + '">' + esc(r.product.title) + '</a> ' + u.stars(r.rating) +
            '<p><strong>' + esc(r.title) + '</strong></p><p class="field__hint">' + esc(r.created_at_fa) + ' · وضعیت: ' + esc(r.status_label) +
            (r.rejection_reason ? ' — دلیل: ' + esc(r.rejection_reason) : '') + '</p></li>';
        }).join('') + '</ul>' : '<p class="field__hint">هنوز نظری ثبت نکرده‌اید.</p>');
      });
    },
    notifications: function () {
      return AP.api.get('account/notifications').then(function (list) {
        panel.innerHTML = '<h2>اعلان‌ها</h2>' + (list.length ? '<ul class="my-reviews">' + list.map(function (n) {
          return '<li class="card-surface' + (n.unread ? ' is-unread' : '') + '"><strong>' + esc(n.title) + '</strong>' + (n.body ? '<p>' + esc(n.body) + '</p>' : '') + '<p class="field__hint">' + esc(n.at) + '</p></li>';
        }).join('') + '</ul>' : '<p class="field__hint">اعلانی ندارید.</p>');
      });
    }
  };

  function show(tab) {
    u.$$('.account__nav [data-tab]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.tab === tab)); });
    panel.innerHTML = '<p class="field__hint">در حال بارگذاری…</p>';
    (views[tab] || views.orders)().catch(err);
    history.replaceState(null, '', location.pathname + '#' + tab);
  }

  document.querySelector('.account__nav').addEventListener('click', function (e) {
    var b = e.target.closest('[data-tab]');
    if (b) { show(b.dataset.tab); return; }
    if (e.target.closest('[data-logout]')) {
      AP.api.post('auth/logout').then(function () { location.href = ''; });
    }
  });

  panel.addEventListener('submit', function (e) {
    e.preventDefault();
    var form = e.target;
    var data = {};
    u.$$('input[name], textarea[name]', form).forEach(function (i) { data[i.name] = i.type === 'checkbox' ? i.checked : u.enDigits(i.value.trim()); });
    var req = form.id === 'profile-form' ? AP.api.put('account/profile', data)
      : form.id === 'password-form' ? AP.api.put('account/password', data)
        : AP.api.post('account/addresses', data);
    req.then(function () {
      AP.toast('ذخیره شد.');
      if (form.id === 'address-form') show('addresses');
      if (form.id === 'password-form') form.reset();
    }).catch(function (e2) { AP.api.showErrors(form, e2); AP.toast(e2.message, 'error'); });
  });
  panel.addEventListener('click', function (e) {
    var del = e.target.closest('[data-del-address]');
    if (del && window.confirm('این نشانی حذف شود؟')) {
      AP.api.del('account/addresses/' + del.dataset.delAddress).then(function () { show('addresses'); }).catch(function (e2) { AP.toast(e2.message, 'error'); });
    }
  });

  show((location.hash || '#orders').slice(1));
})();
