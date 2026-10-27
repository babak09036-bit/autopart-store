/* Blog: comment form (moderated), reply/report buttons and copy-link. Articles themselves are server-rendered. */
(function () {
  'use strict';

  var AP = window.AP;
  var u = AP.utils;
  var form = document.getElementById('comment-form');

  if (form) {
    var parent = form.elements.parent_id;
    var replying = form.querySelector('.comment-form__replying');
    var status = form.querySelector('.alert');
    if (AP.boot.user) {
      form.elements.author_name.value = AP.boot.user.name;
      form.elements.author_name.readOnly = true;
    }
    document.addEventListener('click', function (e) {
      var reply = e.target.closest('[data-reply]');
      if (reply) {
        parent.value = reply.dataset.reply;
        replying.hidden = false;
        form.elements.body.focus();
        return;
      }
      if (e.target.closest('[data-cancel-reply]')) { parent.value = ''; replying.hidden = true; return; }
      var report = e.target.closest('[data-report]');
      if (report) {
        AP.api.post('blog/comments/' + report.dataset.report + '/report').then(function () { AP.toast('گزارش شما ثبت شد.', 'info'); report.disabled = true; })
          .catch(function (err) { AP.toast(err.message, 'error'); });
      }
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var f = form.elements;
      var data = { author_name: f.author_name.value.trim(), author_email: f.author_email.value.trim(), body: f.body.value.trim(), parent_id: f.parent_id.value || null, website: f.website.value };
      var local = {};
      if (!AP.boot.user && data.author_name.length < 2) local.author_name = 'نام خود را وارد کنید.';
      if (data.author_email && !u.isEmail(data.author_email)) local.author_email = 'ایمیل معتبر نیست.';
      if (data.body.length < 5) local.body = 'متن دیدگاه کوتاه است.';
      if (Object.keys(local).length) { AP.api.showErrors(form, { errors: local }); return; }
      AP.api.post('blog/posts/' + encodeURIComponent(form.dataset.slug) + '/comments', data).then(function () {
        status.className = 'alert alert--success';
        status.textContent = 'دیدگاه شما ثبت شد و پس از بررسی منتشر می‌شود.';
        status.hidden = false;
        f.body.value = '';
        parent.value = '';
        replying.hidden = true;
      }).catch(function (err) {
        AP.api.showErrors(form, err);
        status.className = 'alert alert--error';
        status.textContent = err.message;
        status.hidden = false;
      });
    });
  }

  document.addEventListener('click', function (e) {
    var copy = e.target.closest('[data-copy]');
    if (!copy) return;
    (navigator.clipboard ? navigator.clipboard.writeText(copy.dataset.copy) : Promise.reject()).then(function () {
      AP.toast('پیوند مقاله کپی شد.');
    }).catch(function () { window.prompt('پیوند مقاله:', copy.dataset.copy); });
  });
})();
