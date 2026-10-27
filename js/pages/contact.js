/* Contact form validation (no network request; shows a local success message). */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var form = document.getElementById('contact-form');
  var alertBox = document.getElementById('contact-alert');

  function check(input, message) {
    document.getElementById(input.id + '-error').textContent = message || '';
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
    if (!input.getAttribute('aria-describedby')) input.setAttribute('aria-describedby', input.id + '-error');
    return !message;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var f = form.elements;
    var contact = u.enDigits(f.contact.value.trim());
    var ok = check(f.name, f.name.value.trim().length < 3 ? 'نام خود را وارد کنید.' : '');
    ok = check(f.contact, !contact ? 'موبایل یا ایمیل را وارد کنید.'
      : !(u.isMobile(contact) || u.isEmail(contact)) ? 'یک شماره موبایل (۰۹...) یا ایمیل معتبر وارد کنید.' : '') && ok;
    ok = check(f.subject, !f.subject.value ? 'موضوع پیام را انتخاب کنید.' : '') && ok;
    ok = check(f.message, f.message.value.trim().length < 10 ? 'متن پیام باید حداقل ۱۰ کاراکتر باشد.' : '') && ok;

    alertBox.hidden = false;
    if (!ok) {
      alertBox.className = 'alert alert--error';
      alertBox.innerHTML = u.icon('alert', 'icon--sm') + '<span>لطفاً خطاهای فرم را برطرف کنید.</span>';
      form.querySelector('[aria-invalid="true"]').focus();
      return;
    }
    alertBox.className = 'alert alert--success';
    alertBox.innerHTML = u.icon('check', 'icon--sm') + '<span>پیام شما ثبت شد. کارشناسان ما حداکثر تا یک روز کاری پاسخ می‌دهند. (نمایشی)</span>';
    form.reset();
    u.$$('[aria-invalid]', form).forEach(function (el) { el.removeAttribute('aria-invalid'); });
  });
})();
