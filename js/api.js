/* REST API client: JSON envelope, CSRF header, one automatic retry after an expired token. */
(function () {
  'use strict';

  var AP = window.AP = window.AP || {};
  var boot = window.AP_BOOT || {};

  function ApiError(message, status, errors) {
    this.message = message;
    this.status = status;
    this.errors = errors || {};
  }

  function request(method, path, body, opts, retried) {
    opts = opts || {};
    var headers = { 'Accept': 'application/json', 'X-CSRF-Token': boot.csrf || '' };
    var payload;
    if (body instanceof FormData) {
      payload = body;
    } else if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
      payload = JSON.stringify(body);
    }
    Object.keys(opts.headers || {}).forEach(function (k) { headers[k] = opts.headers[k]; });
    return fetch('api/' + path.replace(/^\//, ''), { method: method, headers: headers, body: payload, credentials: 'same-origin' })
      .then(function (res) {
        return res.json().catch(function () { return { success: false, message: 'پاسخ نامعتبر از سرور دریافت شد.' }; })
          .then(function (json) {
            if (res.status === 403 && json.errors && json.errors.csrf && !retried) {
              return request('GET', 'boot').then(function (d) {
                boot.csrf = d.csrf;
                return request(method, path, body, opts, true);
              });
            }
            if (!res.ok || !json.success) {
              throw new ApiError(json.message || 'خطایی رخ داده است.', res.status, json.errors);
            }
            if (json.data && json.data.csrf) boot.csrf = json.data.csrf;
            return json.data;
          });
      }, function () {
        throw new ApiError('ارتباط با سرور برقرار نشد؛ اتصال اینترنت را بررسی کنید.', 0);
      });
  }

  /** Builds a query string, skipping empty values. */
  function qs(params) {
    var parts = [];
    Object.keys(params || {}).forEach(function (k) {
      var v = params[k];
      if (v === undefined || v === null || v === '' || v === false || (Array.isArray(v) && !v.length)) return;
      parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(Array.isArray(v) ? v.join(',') : (v === true ? '1' : v)));
    });
    return parts.length ? '?' + parts.join('&') : '';
  }

  /** Shows field errors from a 422 response next to inputs marked data-error-for="field". */
  function showErrors(form, err) {
    AP.utils.$$('[data-error-for]', form).forEach(function (el) { el.textContent = ''; });
    AP.utils.$$('[aria-invalid]', form).forEach(function (el) { el.removeAttribute('aria-invalid'); });
    var first = null;
    Object.keys((err && err.errors) || {}).forEach(function (field) {
      var slot = form.querySelector('[data-error-for="' + field + '"]');
      var input = form.elements[field];
      if (slot) slot.textContent = err.errors[field];
      if (input && input.setAttribute) {
        input.setAttribute('aria-invalid', 'true');
        first = first || input;
      }
    });
    if (first && first.focus) first.focus();
  }

  AP.api = {
    get: function (path, params) { return request('GET', path + qs(params)); },
    post: function (path, body, opts) { return request('POST', path, body === undefined ? {} : body, opts); },
    put: function (path, body) { return request('PUT', path, body || {}); },
    patch: function (path, body) { return request('PATCH', path, body || {}); },
    del: function (path) { return request('DELETE', path, {}); },
    qs: qs,
    showErrors: showErrors,
    Error: ApiError
  };
  AP.boot = boot;
})();
