/* Local rich-text editor (no dependencies), Google result preview and a simple SEO analyser.
   The editor only produces a small set of tags; the server sanitises everything again with an allow-list. */
(function () {
  'use strict';

  var A = window.A;

  var TOOLS = [
    ['h2', 'H2', 'تیتر ۲'], ['h3', 'H3', 'تیتر ۳'], ['p', '¶', 'پاراگراف'], ['bold', 'B', 'پررنگ'], ['italic', 'I', 'مورب'],
    ['ul', '•', 'فهرست نشانه‌دار'], ['ol', '۱.', 'فهرست شماره‌دار'], ['quote', '❝', 'نقل‌قول'], ['link', '🔗', 'پیوند'], ['unlink', '⛓', 'حذف پیوند'],
    ['table', '▦', 'جدول'], ['image', '🖼', 'تصویر با کپشن'], ['button', '▭', 'دکمه'], ['product', '🛒', 'درج محصول'],
    ['note', 'ℹ', 'باکس نکته'], ['warning', '⚠', 'باکس هشدار'], ['source', '</>', 'ویرایش HTML'], ['preview', '👁', 'پیش‌نمایش']
  ];

  function exec(cmd, val) { document.execCommand(cmd, false, val); }

  function insertHTML(area, html) {
    area.focus();
    exec('insertHTML', html);
  }

  var Editor = {
    mount: function (slot, textarea) {
      slot.innerHTML = '<div class="a-editor"><div class="a-editor__bar" role="toolbar" aria-label="ابزار ویرایش">' + TOOLS.map(function (t) {
        return '<button type="button" data-cmd="' + t[0] + '" title="' + t[2] + '" aria-label="' + t[2] + '">' + t[1] + '</button>';
      }).join('') + '</div><div class="a-editor__area rich" contenteditable="true" role="textbox" aria-multiline="true" aria-label="محتوا"></div>' +
        '<textarea class="a-editor__source" dir="ltr" hidden aria-label="کد HTML"></textarea></div>';
      var area = slot.querySelector('.a-editor__area');
      var source = slot.querySelector('.a-editor__source');
      area.innerHTML = textarea.value || '<p></p>';
      exec('defaultParagraphSeparator', 'p');
      slot._area = area;
      slot._source = source;
      var sync = function () { textarea.value = source.hidden ? area.innerHTML : source.value; textarea.dispatchEvent(new Event('input', { bubbles: true })); };
      area.addEventListener('input', sync);
      source.addEventListener('input', sync);
      // Paste as plain text to avoid foreign styles/markup.
      area.addEventListener('paste', function (e) {
        e.preventDefault();
        var text = (e.clipboardData || window.clipboardData).getData('text/plain');
        exec('insertText', text);
      });
      slot.querySelector('.a-editor__bar').addEventListener('click', function (e) {
        var b = e.target.closest('[data-cmd]');
        if (!b) return;
        var cmd = b.dataset.cmd;
        if (cmd !== 'source' && cmd !== 'preview') area.focus();
        switch (cmd) {
          case 'h2': case 'h3': case 'p': exec('formatBlock', '<' + cmd + '>'); break;
          case 'bold': exec('bold'); break;
          case 'italic': exec('italic'); break;
          case 'ul': exec('insertUnorderedList'); break;
          case 'ol': exec('insertOrderedList'); break;
          case 'quote': exec('formatBlock', '<blockquote>'); break;
          case 'unlink': exec('unlink'); break;
          case 'link':
            var url = window.prompt('نشانی پیوند (داخلی مثل products/abc یا خارجی با https://):', '');
            if (url) {
              if (/^\s*(javascript|data):/i.test(url)) { A.toast('این نوع پیوند مجاز نیست.', 'error'); break; }
              var external = /^https?:\/\//i.test(url);
              var sel = window.getSelection().toString() || url;
              insertHTML(area, '<a href="' + A.esc(url) + '"' + (external ? ' target="_blank" rel="noopener noreferrer"' : '') + '>' + A.esc(sel) + '</a>');
            }
            break;
          case 'table':
            var rows = Math.max(1, Math.min(20, Number(A.en(window.prompt('تعداد ردیف‌ها:', '3'))) || 3));
            var cols = Math.max(1, Math.min(8, Number(A.en(window.prompt('تعداد ستون‌ها:', '2'))) || 2));
            var html = '<table><thead><tr>' + new Array(cols + 1).join('<th>عنوان</th>') + '</tr></thead><tbody>' + new Array(rows + 1).join('<tr>' + new Array(cols + 1).join('<td>—</td>') + '</tr>') + '</tbody></table><p></p>';
            insertHTML(area, html);
            break;
          case 'image':
            var range = window.getSelection().rangeCount ? window.getSelection().getRangeAt(0) : null;
            A.pickMedia('blog').then(function (path) {
              if (!path) return;
              var alt = window.prompt('متن جایگزین تصویر (Alt):', '') || '';
              var cap = window.prompt('کپشن تصویر (اختیاری):', '') || '';
              area.focus();
              if (range) { var s = window.getSelection(); s.removeAllRanges(); s.addRange(range); }
              insertHTML(area, '<figure><img src="' + A.esc(path) + '" alt="' + A.esc(alt) + '" loading="lazy">' + (cap ? '<figcaption>' + A.esc(cap) + '</figcaption>' : '') + '</figure><p></p>');
            });
            break;
          case 'button':
            var bt = window.prompt('متن دکمه:', 'مشاهده محصولات');
            var bu = bt && window.prompt('نشانی دکمه:', 'products');
            if (bt && bu && !/^\s*(javascript|data):/i.test(bu)) insertHTML(area, '<p><a class="cta-button" href="' + A.esc(bu) + '">' + A.esc(bt) + '</a></p>');
            break;
          case 'product':
            var pid = A.en(window.prompt('شناسه (ID) محصول را وارد کنید:', ''));
            if (/^\d+$/.test(pid)) insertHTML(area, '<div class="product-embed" data-product-id="' + pid + '"></div><p></p>');
            break;
          case 'note': insertHTML(area, '<div class="note-box"><p>متن نکته…</p></div><p></p>'); break;
          case 'warning': insertHTML(area, '<div class="warning-box"><p>متن هشدار…</p></div><p></p>'); break;
          case 'source':
            if (source.hidden) { source.value = area.innerHTML; source.hidden = false; area.hidden = true; }
            else { area.innerHTML = source.value; source.hidden = true; area.hidden = false; }
            b.setAttribute('aria-pressed', String(!source.hidden));
            break;
          case 'preview':
            A.modal({ title: 'پیش‌نمایش محتوا', wide: true, body: '<div class="rich preview-rich">' + (source.hidden ? area.innerHTML : source.value).replace(/<script[\s\S]*?<\/script>/gi, '') + '</div>' +
              '<p class="a-hint">نمایش نهایی پس از پاک‌سازی امن در سرور انجام می‌شود.</p>' });
            break;
        }
        sync();
      });
    },
    sync: function (form, name) {
      var slot = form.querySelector('[data-editor="' + name + '"]');
      var ta = form.querySelector('textarea[name="' + name + '"]');
      if (slot && slot._area) ta.value = slot._source.hidden ? slot._area.innerHTML : slot._source.value;
    }
  };
  A.Editor = Editor;

  /* ---------------- Google result preview ---------------- */
  A.serpPreview = function (title, url, description) {
    var t = title || '(بدون عنوان)';
    var d = description || 'توضیحات متا تعیین نشده است؛ گوگل ممکن است بخشی از متن صفحه را نمایش دهد.';
    return '<div class="a-serp" aria-label="پیش‌نمایش تقریبی نتیجه گوگل"><span class="a-serp__url" dir="ltr">' + A.esc(decodeURI(url || '')) + '</span>' +
      '<span class="a-serp__title">' + A.esc(t) + '</span><span class="a-serp__desc">' + A.esc(d) + '</span></div>' +
      '<p class="a-hint">طول عنوان: ' + A.fa(t.length) + ' کاراکتر ' + lengthHint(t.length, 30, 60) + ' — طول توضیحات: ' + A.fa((description || '').length) + ' کاراکتر ' + lengthHint((description || '').length, 70, 160) +
      '. این پیش‌نمایش تقریبی است و متن قطع نمی‌شود.</p>';
  };
  function lengthHint(n, min, max) {
    if (!n) return '(خالی)';
    if (n < min) return '(کوتاه)';
    if (n > max) return '(ممکن است در نتایج کوتاه نمایش داده شود)';
    return '(مناسب)';
  }

  /* ---------------- SEO analyser (rough guide only) ---------------- */
  function words(text) { return (text.match(/[\p{L}\p{N}]+/gu) || []); }
  function norm(s) { return A.en(String(s || '')).toLowerCase().replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/‌/g, ' ').replace(/\s+/g, ' ').trim(); }

  /**
   * input: { title, seoTitle, description, slug, html, keyword, related, image, siteHost }
   * returns { score, checks: [{status: good|warn|bad, text}] }
   */
  A.analyzeSeo = function (input) {
    var doc = new DOMParser().parseFromString('<div>' + (input.html || '') + '</div>', 'text/html');
    var text = doc.body.textContent || '';
    var wc = words(text).length;
    var h1 = doc.querySelectorAll('h1').length;
    var h2 = doc.querySelectorAll('h2').length;
    var h3 = doc.querySelectorAll('h3').length;
    var imgs = doc.querySelectorAll('img');
    var noAlt = Array.prototype.filter.call(imgs, function (i) { return !(i.getAttribute('alt') || '').trim(); }).length;
    var links = Array.prototype.map.call(doc.querySelectorAll('a[href]'), function (a) { return a.getAttribute('href'); });
    var external = links.filter(function (h) { return /^https?:\/\//i.test(h) && h.indexOf(input.siteHost) === -1; });
    var internal = links.filter(function (h) { return external.indexOf(h) === -1; });
    var broken = links.filter(function (h) { return !h || h === '#' || /\s/.test(h) || /^javascript:/i.test(h); });
    var productLinks = links.filter(function (h) { return /(^|\/)products\//.test(h); }).length + doc.querySelectorAll('.product-embed').length;
    var title = input.seoTitle || input.title || '';
    var kw = norm(input.keyword);
    var nText = norm(text);
    var kwCount = kw ? nText.split(kw).length - 1 : 0;
    var density = kw && wc ? (kwCount * words(kw).length / wc) * 100 : 0;
    var sentences = text.split(/[.!؟?\n]+/).map(function (s) { return words(s).length; }).filter(Boolean);
    var avgSentence = sentences.length ? sentences.reduce(function (a, b) { return a + b; }, 0) / sentences.length : 0;
    var paras = Array.prototype.map.call(doc.querySelectorAll('p'), function (p) { return words(p.textContent).length; });
    var longParas = paras.filter(function (n) { return n > 150; }).length;
    var h2First = doc.body.firstElementChild && doc.body.firstElementChild.firstElementChild;

    var checks = [];
    function add(status, msg) { checks.push({ status: status, text: msg }); }
    add(title ? 'good' : 'bad', title ? 'عنوان وجود دارد.' : 'عنوان صفحه خالی است.');
    add(title.length >= 30 && title.length <= 65 ? 'good' : 'warn', 'طول عنوان ' + A.fa(title.length) + ' کاراکتر است (پیشنهاد: حدود ۳۰ تا ۶۵).');
    add(input.description ? 'good' : 'warn', input.description ? 'توضیحات متا تعیین شده است.' : 'توضیحات متا خالی است؛ یک خلاصه جذاب بنویسید.');
    if (input.description) add(input.description.length >= 70 && input.description.length <= 165 ? 'good' : 'warn', 'طول توضیحات متا ' + A.fa(input.description.length) + ' کاراکتر است (پیشنهاد: ۷۰ تا ۱۶۵).');
    add(h1 === 0 ? 'good' : 'warn', h1 === 0 ? 'تیتر H1 از عنوان مقاله ساخته می‌شود و در متن تکرار نشده است.' : 'متن شامل H1 است؛ هر صفحه فقط یک H1 (عنوان) داشته باشد.');
    add(h2 > 0 ? 'good' : 'warn', h2 > 0 ? 'ساختار متن تیتر H2 دارد (' + A.fa(h2) + ' مورد، ' + A.fa(h3) + ' H3).' : 'از تیترهای H2 و H3 برای بخش‌بندی متن استفاده کنید.');
    if (h3 && !h2) add('warn', 'H3 بدون H2 استفاده شده است؛ سلسله‌مراتب تیترها را رعایت کنید.');
    add(wc >= 300 ? 'good' : wc >= 150 ? 'warn' : 'bad', 'طول محتوا ' + A.fa(wc) + ' کلمه است' + (wc < 300 ? '؛ محتوای کامل‌تر معمولاً مفیدتر است.' : '.'));
    add(input.image ? 'good' : 'warn', input.image ? 'تصویر شاخص انتخاب شده است.' : 'تصویر شاخص ندارد.');
    add(noAlt === 0 ? 'good' : 'bad', noAlt === 0 ? 'همه تصاویر متن جایگزین (Alt) دارند.' : A.fa(noAlt) + ' تصویر بدون متن جایگزین (Alt) است.');
    add(internal.length >= 2 ? 'good' : 'warn', A.fa(internal.length) + ' پیوند داخلی' + (internal.length < 2 ? '؛ به مقالات و دسته‌های مرتبط پیوند دهید.' : ''));
    add('good', A.fa(external.length) + ' پیوند خارجی');
    add(broken.length === 0 ? 'good' : 'bad', broken.length === 0 ? 'پیوند خالی یا نامعتبری پیدا نشد.' : A.fa(broken.length) + ' پیوند خالی یا نامعتبر وجود دارد.');
    add(productLinks > 0 ? 'good' : 'warn', productLinks > 0 ? 'به ' + A.fa(productLinks) + ' محصول فروشگاه پیوند داده شده است.' : 'به محصولات مرتبط فروشگاه پیوند دهید.');
    if (kw) {
      add(norm(title).indexOf(kw) > -1 ? 'good' : 'warn', norm(title).indexOf(kw) > -1 ? 'عبارت کلیدی در عنوان آمده است.' : 'عبارت کلیدی اصلی در عنوان نیامده است.');
      add(kwCount > 0 ? (density > 3 ? 'warn' : 'good') : 'warn', kwCount ? 'عبارت کلیدی ' + A.fa(kwCount) + ' بار در متن آمده است' + (density > 3 ? '؛ تکرار زیاد است، طبیعی بنویسید.' : '.') : 'عبارت کلیدی در متن استفاده نشده است.');
      add(norm(input.description).indexOf(kw) > -1 ? 'good' : 'warn', norm(input.description).indexOf(kw) > -1 ? 'عبارت کلیدی در توضیحات متا آمده است.' : 'عبارت کلیدی را به‌صورت طبیعی در توضیحات متا بیاورید.');
      (input.related || '').split(/[،,]/).map(norm).filter(Boolean).forEach(function (r) {
        add(nText.indexOf(r) > -1 ? 'good' : 'warn', 'عبارت مرتبط «' + r + '» ' + (nText.indexOf(r) > -1 ? 'در متن آمده است.' : 'در متن نیامده است.'));
      });
    } else {
      add('warn', 'عبارت کلیدی اصلی تعریف نشده است.');
    }
    var slug = input.slug || '';
    add(slug && slug.length <= 75 && !/--/.test(slug) ? 'good' : 'warn', slug ? 'اسلاگ «' + slug + '»' + (slug.length > 75 ? ' طولانی است.' : ' مناسب است.') : 'اسلاگ خالی است (از عنوان ساخته می‌شود).');
    add(avgSentence && avgSentence <= 25 && !longParas ? 'good' : 'warn', 'میانگین طول جمله ' + A.fa(Math.round(avgSentence)) + ' کلمه' + (longParas ? '، ' + A.fa(longParas) + ' پاراگراف طولانی' : '') + (avgSentence > 25 || longParas ? '؛ جمله‌ها و پاراگراف‌ها را کوتاه‌تر کنید.' : '.'));

    var pts = checks.reduce(function (s, c) { return s + (c.status === 'good' ? 2 : c.status === 'warn' ? 1 : 0); }, 0);
    return { score: Math.round(pts / (checks.length * 2) * 100), checks: checks };
  };

  A.renderSeoChecks = function (result) {
    return '<div class="a-seo-score"><strong>' + A.fa(result.score) + '</strong><span>امتیاز تقریبی سئو — راهنمایی برای نوشتن بهتر است، نه تضمین رتبه در گوگل.</span></div>' +
      '<ul class="a-seo-checks">' + result.checks.map(function (c) { return '<li class="is-' + c.status + '">' + A.esc(c.text) + '</li>'; }).join('') + '</ul>';
  };
})();
