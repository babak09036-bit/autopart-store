/* Contact form → POST /api/contact (stored in contact_messages, visible in the admin panel). */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var form = document.getElementById('contact-form');
  var alertBox = document.getElementById('contact-alert');
  if (!form) return;

  function say(text, ok) {
    alertBox.className = 'alert ' + (ok ? 'alert--success' : 'alert--error');
    alertBox.innerHTML = u.icon(ok ? 'check' : 'alert', 'icon--sm') + '<span>' + u.escape(text) + '</span>';
    alertBox.hidden = false;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var f = form.elements;
    var data = { name: f.name.value.trim(), contact: u.enDigits(f.contact.value.trim()), subject: f.subject.value, message: f.message.value.trim(), website: f.website.value };
    var local = {};
    if (data.name.length < 3) local.name = 'نام خود را وارد کنید.';
    if (!u.isMobile(data.contact) && !u.isEmail(data.contact)) local.contact = 'یک شماره موبایل (۰۹...) یا ایمیل معتبر وارد کنید.';
    if (!data.subject) local.subject = 'موضوع پیام را انتخاب کنید.';
    if (data.message.length < 10) local.message = 'متن پیام باید حداقل ۱۰ کاراکتر باشد.';
    if (Object.keys(local).length) { AP.api.showErrors(form, { errors: local }); say('لطفاً خطاهای فرم را برطرف کنید.', false); return; }
    var btn = form.querySelector('[type="submit"]');
    btn.disabled = true;
    AP.api.post('contact', data).then(function () {
      say('پیام شما ثبت شد. کارشناسان ما حداکثر تا یک روز کاری پاسخ می‌دهند.', true);
      form.reset();
      btn.disabled = false;
    }).catch(function (err) {
      btn.disabled = false;
      AP.api.showErrors(form, err);
      say(err.message, false);
    });
  });
})();
