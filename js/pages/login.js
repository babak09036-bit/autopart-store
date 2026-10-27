/* Demo login / register. Only the display name and mobile are stored; passwords never are. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var main = document.getElementById('auth-main');
  var alertBox = document.getElementById('auth-alert');

  function showAlert(text, ok) {
    alertBox.className = 'alert ' + (ok ? 'alert--success' : 'alert--error');
    alertBox.innerHTML = u.icon(ok ? 'check' : 'alert', 'icon--sm') + '<span>' + u.escape(text) + '</span>';
    alertBox.hidden = false;
  }

  function setError(input, message) {
    var el = document.getElementById(input.id + '-error') || document.getElementById(input.getAttribute('aria-describedby'));
    if (el) el.textContent = message || '';
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
    return !message;
  }

  function maskMobile(m) { return u.faDigits(m.slice(0, 4) + '***' + m.slice(-4)); }

  function renderUser(user) {
    main.innerHTML = '<div class="auth__user">' +
      '<img src="assets/images/ui/avatar-3.svg" alt="" width="96" height="96">' +
      '<h2>' + u.escape(user.name) + '</h2><p class="field__hint">' + maskMobile(user.mobile) + '</p>' +
      '<a class="btn btn--primary btn--block" href="tracking.html">' + u.icon('package-track', 'icon--sm') + 'پیگیری سفارش‌ها</a>' +
      '<a class="btn btn--light btn--block" href="favorites.html">' + u.icon('heart', 'icon--sm') + 'علاقه‌مندی‌ها</a>' +
      '<button type="button" class="btn btn--ghost btn--block" data-logout>' + u.icon('logout', 'icon--sm') + 'خروج از حساب</button></div>';
  }

  var stored = u.store.get('ap_user', null);
  if (stored && stored.name && stored.mobile) {
    renderUser(stored);
  }

  main.addEventListener('click', function (e) {
    if (e.target.closest('[data-logout]')) {
      u.store.remove('ap_user');
      location.reload();
    }
  });

  if (!document.getElementById('login-form')) return;

  /* Tabs */
  var tabs = u.$$('[role="tab"]', main);
  function selectTab(tab) {
    tabs.forEach(function (t) {
      var on = t === tab;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
    });
    alertBox.hidden = true;
  }
  tabs.forEach(function (t) {
    t.addEventListener('click', function () { selectTab(t); });
    t.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
        var next = tabs[(tabs.indexOf(t) + 1) % tabs.length];
        selectTab(next); next.focus();
      }
    });
  });

  /* Show / hide password */
  u.$$('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = btn.parentNode.querySelector('input');
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', String(show));
      btn.setAttribute('aria-label', show ? 'پنهان کردن رمز عبور' : 'نمایش رمز عبور');
    });
  });

  function finish(user, message) {
    u.store.set('ap_user', user);
    var label = u.$('.header-action--account span');
    if (label) label.textContent = user.name;
    showAlert(message, true);
    AP.toast(message);
    setTimeout(function () { renderUser(user); }, 900);
  }

  document.getElementById('login-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var f = e.target.elements;
    var mobile = u.enDigits(f.mobile.value.trim());
    var ok = setError(f.mobile, !mobile ? 'شماره موبایل را وارد کنید.' : !u.isMobile(mobile) ? 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.' : '');
    ok = setError(f.password, !f.password.value ? 'رمز عبور را وارد کنید.' : f.password.value.length < 8 ? 'رمز عبور حداقل ۸ کاراکتر است.' : '') && ok;
    if (!ok) {
      showAlert('لطفاً خطاهای فرم را برطرف کنید.', false);
      e.target.querySelector('[aria-invalid="true"]').focus();
      return;
    }
    finish({ name: 'کاربر ' + u.faDigits(mobile.slice(-4)), mobile: mobile }, 'با موفقیت وارد شدید.');
  });

  document.getElementById('register-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var f = e.target.elements;
    var name = f.name.value.trim();
    var mobile = u.enDigits(f.mobile.value.trim());
    var ok = setError(f.name, name.length < 3 ? 'نام و نام خانوادگی را کامل وارد کنید.' : '');
    ok = setError(f.mobile, !u.isMobile(mobile) ? 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.' : '') && ok;
    ok = setError(f.password, f.password.value.length < 8 ? 'رمز عبور باید حداقل ۸ کاراکتر باشد.' : '') && ok;
    ok = setError(f.confirm, f.confirm.value !== f.password.value || !f.confirm.value ? 'تکرار رمز عبور با رمز عبور یکسان نیست.' : '') && ok;
    var termsError = document.getElementById('reg-terms-error');
    termsError.textContent = f.terms.checked ? '' : 'برای ثبت‌نام باید قوانین را بپذیرید.';
    ok = f.terms.checked && ok;
    if (!ok) {
      showAlert('لطفاً خطاهای فرم را برطرف کنید.', false);
      var first = e.target.querySelector('[aria-invalid="true"]') || f.terms;
      first.focus();
      return;
    }
    finish({ name: name, mobile: mobile }, 'حساب کاربری شما ساخته شد.');
  });
})();
