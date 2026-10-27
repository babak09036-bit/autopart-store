/* Login, register, forgot-password and reset-password forms (POST /api/auth/*). */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var alertBox = document.getElementById('auth-alert');

  function showAlert(text, ok) {
    if (!alertBox) return;
    alertBox.className = 'alert ' + (ok ? 'alert--success' : 'alert--error');
    alertBox.innerHTML = u.icon(ok ? 'check' : 'alert', 'icon--sm') + '<span>' + u.escape(text) + '</span>';
    alertBox.hidden = false;
  }

  /** Only same-site paths are accepted (no open redirects to other hosts). */
  function nextUrl() {
    var next = u.params().get('next') || 'account';
    if (/^\/(?![\/\\])[\w\-\/.%]*$/.test(next) || /^[\w\-][\w\-\/]*$/.test(next)) return next;
    return 'account';
  }

  /** After login, guest favourites saved in the browser are merged into the account. */
  function afterLogin(message) {
    showAlert(message, true);
    var local = AP.favs.localIds();
    var sync = local.length ? AP.api.post('wishlist/sync', { ids: local }).then(function () { AP.favs.clearLocal(); }).catch(function () {}) : Promise.resolve();
    sync.then(function () { location.href = nextUrl(); });
  }

  function submit(form, path, data, onOk) {
    var btn = form.querySelector('[type="submit"]');
    btn.disabled = true;
    AP.api.post(path, data).then(function (res) { onOk(res); }).catch(function (err) {
      btn.disabled = false;
      AP.api.showErrors(form, err);
      showAlert(err.message, false);
    });
  }

  var tabs = u.$$('.auth__tabs [role="tab"]');
  function selectTab(tab) {
    tabs.forEach(function (t) {
      var on = t === tab;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
    });
    if (alertBox) alertBox.hidden = true;
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

  u.$$('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = btn.parentNode.querySelector('input');
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', String(show));
      btn.setAttribute('aria-label', show ? 'پنهان کردن رمز عبور' : 'نمایش رمز عبور');
    });
  });

  var login = document.getElementById('login-form');
  if (login) login.addEventListener('submit', function (e) {
    e.preventDefault();
    var f = login.elements;
    var local = {};
    if (!f.mobile.value.trim()) local.mobile = 'شماره موبایل یا ایمیل را وارد کنید.';
    if (!f.password.value) local.password = 'رمز عبور را وارد کنید.';
    if (Object.keys(local).length) { AP.api.showErrors(login, { errors: local }); return; }
    submit(login, 'auth/login', { mobile: u.enDigits(f.mobile.value.trim()), password: f.password.value }, function () { afterLogin('با موفقیت وارد شدید.'); });
  });

  var register = document.getElementById('register-form');
  if (register) register.addEventListener('submit', function (e) {
    e.preventDefault();
    var f = register.elements;
    var local = {};
    if (f.full_name.value.trim().length < 3) local.full_name = 'نام و نام خانوادگی را کامل وارد کنید.';
    if (!u.isMobile(f.mobile.value)) local.mobile = 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.';
    if (f.email.value.trim() && !u.isEmail(f.email.value)) local.email = 'ایمیل معتبر نیست.';
    if (f.password.value.length < 8) local.password = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
    if (f.password_confirmation.value !== f.password.value) local.password_confirmation = 'تکرار رمز عبور با رمز عبور یکسان نیست.';
    if (!f.terms.checked) local.terms = 'برای ثبت‌نام باید قوانین را بپذیرید.';
    if (Object.keys(local).length) { AP.api.showErrors(register, { errors: local }); showAlert('لطفاً خطاهای فرم را برطرف کنید.', false); return; }
    submit(register, 'auth/register', {
      full_name: f.full_name.value.trim(), mobile: u.enDigits(f.mobile.value.trim()), email: f.email.value.trim(),
      password: f.password.value, password_confirmation: f.password_confirmation.value, terms: f.terms.checked
    }, function () { afterLogin('حساب کاربری شما ساخته شد.'); });
  });

  var forgot = document.getElementById('forgot-form');
  if (forgot) forgot.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!u.isEmail(forgot.elements.email.value)) { AP.api.showErrors(forgot, { errors: { email: 'ایمیل معتبر نیست.' } }); return; }
    submit(forgot, 'auth/forgot-password', { email: forgot.elements.email.value.trim() }, function () {
      showAlert('اگر حسابی با این ایمیل وجود داشته باشد، پیوند بازیابی برای آن ارسال می‌شود.', true);
      forgot.reset();
      forgot.querySelector('[type="submit"]').disabled = false;
    });
  });

  var reset = document.getElementById('reset-form');
  if (reset) reset.addEventListener('submit', function (e) {
    e.preventDefault();
    var f = reset.elements;
    if (!f.token.value) { showAlert('پیوند بازیابی نامعتبر است.', false); return; }
    submit(reset, 'auth/reset-password', { token: f.token.value, password: f.password.value, password_confirmation: f.password_confirmation.value }, function () {
      showAlert('رمز عبور شما تغییر کرد؛ اکنون می‌توانید وارد شوید.', true);
      setTimeout(function () { location.href = 'login'; }, 1500);
    });
  });
})();
