/* Local rich-text editor (no dependencies), Google result preview and a simple SEO analyser.
   The editor only produces a small set of tags; the server sanitises everything again with an allow-list. */
(function () {
  'use strict';

  var A = window.A;

  /* Stroke icons (24×24), drawn inline so the toolbar needs no extra requests. */
  var ICONS = {
    undo: '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
    redo: '<path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/>',
    bold: '<path d="M6 12h9a4 4 0 0 1 0 8H6V4h8a4 4 0 0 1 0 8"/>',
    italic: '<path d="M19 4h-9M14 20H5M15 4 9 20"/>',
    ul: '<path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4" cy="6" r="1.2"/><circle cx="4" cy="12" r="1.2"/><circle cx="4" cy="18" r="1.2"/>',
    ol: '<path d="M10 6h11M10 12h11M10 18h11"/><path d="M4 4.5h1.2V9M3.6 9h2.4M3.6 14.5c.4-.6 2.4-.8 2.4.5 0 1-2.4 2-2.4 3.5h2.4"/>',
    quote: '<path d="M6 7h4v4c0 3-1.5 5-4 6M14 7h4v4c0 3-1.5 5-4 6"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
    unlink: '<path d="m18.8 12.2 1.7-1.7a5 5 0 0 0-7-7l-1.7 1.7M5.2 11.8l-1.7 1.7a5 5 0 0 0 7 7l1.7-1.7M8 2v3M2 8h3M16 22v-3M22 16h-3"/>',
    image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
    table: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M12 3v18"/>',
    button: '<rect x="3" y="7" width="18" height="10" rx="3"/><path d="M8 12h8"/>',
    product: '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2 2h2l2.7 12.4a2 2 0 0 0 2 1.6h9.8a2 2 0 0 0 2-1.6L22 7H5.1"/>',
    note: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    warning: '<path d="m21.7 18-8-14a2 2 0 0 0-3.5 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3"/><path d="M12 9v4M12 17h.01"/>',
    source: '<path d="m16 18 6-6-6-6M8 6l-6 6 6 6"/>',
    preview: '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>'
  };
  function svg(name) { return '<svg class="a-cmd__icon" viewBox="0 0 24 24" aria-hidden="true">' + ICONS[name] + '</svg>'; }

  /* [command, label shown, accessible name (+ shortcut)] — groups are separated in the toolbar. */
  var GROUPS = [
    [['undo', svg('undo'), 'واگرد (Ctrl+Z)'], ['redo', svg('redo'), 'انجام دوباره (Ctrl+Y)']],
    [['p', 'متن', 'پاراگراف'], ['h2', 'H2', 'تیتر اصلی بخش (H2)'], ['h3', 'H3', 'زیرتیتر (H3)']],
    [['bold', svg('bold'), 'پررنگ (Ctrl+B)'], ['italic', svg('italic'), 'مورب (Ctrl+I)']],
    [['ul', svg('ul'), 'فهرست نشانه‌دار'], ['ol', svg('ol'), 'فهرست شماره‌دار'], ['quote', svg('quote'), 'نقل‌قول']],
    [['link', svg('link'), 'افزودن پیوند (Ctrl+K)'], ['unlink', svg('unlink'), 'حذف پیوند']],
    [['image', svg('image') + '<span>تصویر</span>', 'درج تصویر'], ['table', svg('table'), 'جدول']],
    [['button', svg('button'), 'دکمه'], ['product', svg('product'), 'درج کارت محصول'], ['note', svg('note'), 'باکس نکته'], ['warning', svg('warning'), 'باکس هشدار']],
    [['source', svg('source'), 'ویرایش کد HTML'], ['preview', svg('preview'), 'پیش‌نمایش']]
  ];
  var SIZES = [['img-full', 'تمام عرض'], ['img-medium', 'متوسط'], ['img-small', 'کوچک']];
  var ALIGNS = [['img-center', 'وسط'], ['img-start', 'راست (متن کنارش)'], ['img-end', 'چپ (متن کنارش)']];
  var UNSAFE_URL = /^\s*(javascript|data|vbscript):/i;

  function exec(cmd, val) { document.execCommand(cmd, false, val); }
  function wordCount(text) { return (text.match(/[\p{L}\p{N}]+/gu) || []).length; }

  /** Reads an image's natural size so width/height attributes can be written (avoids layout shift). */
  function dims(src) {
    return new Promise(function (resolve) {
      var img = new Image();
      img.onload = function () { resolve({ w: img.naturalWidth, h: img.naturalHeight }); };
      img.onerror = function () { resolve(null); };
      img.src = src;
    });
  }

  function figureHTML(o, size) {
    var cls = [o.size || 'img-full', o.size === 'img-full' ? 'img-center' : (o.align || 'img-center')].join(' ');
    return '<figure class="' + cls + '"><img src="' + A.esc(o.src) + '" alt="' + A.esc(o.alt || '') + '"' +
      (size ? ' width="' + size.w + '" height="' + size.h + '"' : '') + ' loading="lazy">' +
      (o.caption ? '<figcaption>' + A.esc(o.caption) + '</figcaption>' : '') + '</figure>';
  }

  var Editor = {
    mount: function (slot, textarea) {
      var folder = slot.dataset.folder || 'blog';
      slot.innerHTML = '<div class="a-editor">' +
        '<div class="a-editor__bar" role="toolbar" aria-label="ابزار ویرایش">' + GROUPS.map(function (g) {
          return '<div class="a-editor__group">' + g.map(function (t) {
            return '<button type="button" class="a-cmd' + (t[0] === 'image' ? ' a-cmd--wide' : '') + '" data-cmd="' + t[0] + '" title="' + t[2] + '" aria-label="' + t[2] + '">' + t[1] + '</button>';
          }).join('') + '</div>';
        }).join('') + '</div>' +
        '<div class="a-editor__area rich" contenteditable="true" role="textbox" aria-multiline="true" aria-label="محتوا" data-placeholder="متن را اینجا بنویسید… برای افزودن تصویر بین متن، روی «تصویر» بزنید یا فایل تصویر را اینجا بکشید یا Paste کنید."></div>' +
        '<textarea class="a-editor__source" dir="ltr" hidden aria-label="کد HTML"></textarea>' +
        '<div class="a-editor__foot"><span data-stats></span><span class="a-hint">نکته: روی هر تصویر کلیک کنید تا اندازه، جای قرارگیری، متن جایگزین یا کپشن آن را تغییر دهید.</span></div></div>';
      var editor = slot.querySelector('.a-editor');
      var bar = slot.querySelector('.a-editor__bar');
      var area = slot.querySelector('.a-editor__area');
      var source = slot.querySelector('.a-editor__source');
      var stats = slot.querySelector('[data-stats]');
      area.innerHTML = textarea.value || '<p><br></p>';
      exec('defaultParagraphSeparator', 'p');
      slot._area = area;
      slot._source = source;

      var saved = null;
      function saveRange() {
        var s = window.getSelection();
        if (s.rangeCount && area.contains(s.getRangeAt(0).commonAncestorContainer)) saved = s.getRangeAt(0).cloneRange();
      }
      function restoreRange() {
        area.focus();
        if (!saved) {
          var r = document.createRange();
          r.selectNodeContents(area);
          r.collapse(false);
          saved = r;
        }
        var s = window.getSelection();
        s.removeAllRanges();
        s.addRange(saved);
      }
      function insertHTML(html) { restoreRange(); exec('insertHTML', html); saveRange(); }
      /** Inserts a block (figure, table, box) as its own block followed by an empty paragraph to keep typing. */
      function insertBlock(html) { insertHTML(html + '<p><br></p>'); }

      function refresh() {
        var empty = !area.textContent.trim() && !area.querySelector('img, table, .product-embed');
        area.classList.toggle('is-empty', empty);
        stats.textContent = A.fa(wordCount(area.textContent)) + ' کلمه · ' + A.fa(area.querySelectorAll('img').length) + ' تصویر';
        if (source.hidden) {
          var block = '';
          try { block = String(document.queryCommandValue('formatBlock') || '').toLowerCase(); } catch (e) { /* not focused */ }
          A.$$('[data-cmd]', bar).forEach(function (b) {
            var c = b.dataset.cmd, on = false;
            try {
              if (c === 'bold' || c === 'italic') on = document.queryCommandState(c);
              else if (c === 'ul') on = document.queryCommandState('insertUnorderedList');
              else if (c === 'ol') on = document.queryCommandState('insertOrderedList');
              else if (c === 'h2' || c === 'h3' || c === 'p') on = block === c;
              else if (c === 'quote') on = block === 'blockquote';
            } catch (e) { on = false; }
            if (b.dataset.toggle !== undefined || ['bold', 'italic', 'ul', 'ol', 'h2', 'h3', 'p', 'quote'].indexOf(c) > -1) b.setAttribute('aria-pressed', String(on));
          });
        }
      }
      function sync() {
        textarea.value = source.hidden ? area.innerHTML : source.value;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        refresh();
      }

      area.addEventListener('input', sync);
      source.addEventListener('input', sync);
      // not on focus: focusing back after a dialog would move the saved caret to the start
      ['keyup', 'mouseup', 'input'].forEach(function (ev) { area.addEventListener(ev, function () { saveRange(); refresh(); }); });
      // Toolbar clicks must not steal the caret from the text.
      bar.addEventListener('mousedown', function (e) { if (e.target.closest('[data-cmd]')) e.preventDefault(); });
      area.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); saveRange(); openLink(); }
      });

      /* ---------- images: upload, dialog, paste, drag & drop ---------- */
      function upload(file) {
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { A.toast('فقط تصاویر JPG، PNG و WebP قابل بارگذاری است.', 'error'); return Promise.reject(); }
        var fd = new FormData();
        fd.append('file', file);
        fd.append('folder', folder);
        return A.post('media', fd).then(function (m) { return m.path; }).catch(function (err) { A.fail(err); throw err; });
      }
      function uploadInto(files) {
        files.forEach(function (file) {
          var id = 'up' + Date.now() + Math.random().toString(36).slice(2, 6);
          insertBlock('<figure class="img-full img-center a-uploading" id="' + id + '"><img src="assets/images/ui/placeholder.svg" alt=""><figcaption>در حال بارگذاری «' + A.esc(file.name) + '»…</figcaption></figure>');
          upload(file).then(function (path) {
            return dims(path).then(function (d) {
              var el = document.getElementById(id);
              if (!el) return;
              var tmp = document.createElement('div');
              tmp.innerHTML = figureHTML({ src: path, alt: '', size: 'img-full' }, d);
              el.replaceWith(tmp.firstChild);
              sync();
              A.toast('تصویر درج شد؛ برای نوشتن متن جایگزین (Alt) روی آن کلیک کنید.');
            });
          }).catch(function () { var el = document.getElementById(id); if (el) el.remove(); sync(); });
        });
      }

      /** Image dialog for a new image (figure = null) or an existing one. */
      function imageDialog(figure) {
        var img = figure && figure.querySelector('img');
        var cls = figure ? figure.className : 'img-full img-center';
        var cur = {
          src: img ? img.getAttribute('src') : '', alt: img ? img.getAttribute('alt') || '' : '', caption: figure && figure.querySelector('figcaption') ? figure.querySelector('figcaption').textContent : '',
          size: (SIZES.filter(function (s) { return cls.indexOf(s[0]) > -1; })[0] || SIZES[0])[0],
          align: (ALIGNS.filter(function (s) { return cls.indexOf(s[0]) > -1; })[0] || ALIGNS[0])[0]
        };
        function radios(name, list, value) {
          return '<div class="a-seg" role="radiogroup">' + list.map(function (o) {
            return '<label><input type="radio" name="' + name + '" value="' + o[0] + '"' + (o[0] === value ? ' checked' : '') + '><span>' + o[1] + '</span></label>';
          }).join('') + '</div>';
        }
        var m = A.modal({ title: figure ? 'ویرایش تصویر' : 'درج تصویر بین متن', wide: true,
          body: '<form class="a-imgdlg" novalidate><div class="a-imgdlg__preview" data-prev></div><div class="a-imgdlg__fields">' +
            '<div class="a-form-actions"><button type="button" class="a-btn a-btn--light a-btn--sm" data-lib>' + svg('image') + 'انتخاب از کتابخانه</button>' +
            '<label class="a-btn a-btn--light a-btn--sm a-upload">بارگذاری از رایانه<input type="file" accept="image/jpeg,image/png,image/webp" hidden data-file></label></div>' +
            '<div class="a-field"><label for="img-src">مسیر تصویر</label><input class="a-input" id="img-src" name="src" dir="ltr" placeholder="uploads/... یا assets/..." value="' + A.esc(cur.src) + '"></div>' +
            '<div class="a-field"><label for="img-alt">متن جایگزین (Alt) <b class="a-req">*</b></label><input class="a-input" id="img-alt" name="alt" value="' + A.esc(cur.alt) + '" placeholder="مثلاً: نصب لنت ترمز روی چرخ جلو پژو ۲۰۶">' +
              '<p class="a-hint">توضیح کوتاه آنچه در تصویر دیده می‌شود؛ برای نابینایان و سئو لازم است.</p></div>' +
            '<div class="a-field"><label for="img-cap">کپشن زیر تصویر (اختیاری)</label><input class="a-input" id="img-cap" name="caption" value="' + A.esc(cur.caption) + '"></div>' +
            '<div class="a-field"><span>اندازه</span>' + radios('size', SIZES, cur.size) + '</div>' +
            '<div class="a-field" data-align-field><span>جای قرارگیری</span>' + radios('align', ALIGNS, cur.align) + '<p class="a-hint">در موبایل همه تصاویر تمام‌عرض نمایش داده می‌شوند.</p></div>' +
            '<p class="a-error" data-err></p></div></form>',
          foot: '<button type="button" class="a-btn a-btn--primary" data-ok>' + (figure ? 'ذخیره تغییرات' : 'درج در متن') + '</button>' +
            (figure ? '<button type="button" class="a-btn a-btn--danger-ghost" data-remove>حذف تصویر</button>' : '') +
            '<button type="button" class="a-btn a-btn--light" data-close>انصراف</button>' });
        var f = m.el.querySelector('form');
        var prev = m.el.querySelector('[data-prev]');
        function draw() {
          var size = f.elements.size.value;
          m.el.querySelector('[data-align-field]').hidden = size === 'img-full';
          prev.innerHTML = f.elements.src.value.trim() ? '<img src="' + A.esc(f.elements.src.value.trim()) + '" alt="">' + (f.elements.caption.value ? '<span>' + A.esc(f.elements.caption.value) + '</span>' : '')
            : '<div class="a-empty">هنوز تصویری انتخاب نشده است.</div>';
        }
        f.addEventListener('input', draw);
        f.addEventListener('change', function (e) {
          if (e.target.matches('[data-file]') && e.target.files[0]) {
            prev.innerHTML = '<div class="a-loading">در حال بارگذاری…</div>';
            upload(e.target.files[0]).then(function (path) { f.elements.src.value = path; draw(); }, draw);
          } else draw();
        });
        m.el.querySelector('[data-lib]').addEventListener('click', function () {
          A.pickMedia(folder).then(function (path) { if (path) { f.elements.src.value = path; draw(); } });
        });
        draw();
        if (figure) {
          m.el.querySelector('[data-remove]').addEventListener('click', function () { figure.remove(); m.close(); sync(); });
        }
        m.el.querySelector('[data-ok]').addEventListener('click', function () {
          var o = { src: f.elements.src.value.trim(), alt: f.elements.alt.value.trim(), caption: f.elements.caption.value.trim(), size: f.elements.size.value, align: f.elements.align.value };
          var err = !o.src ? 'یک تصویر انتخاب یا بارگذاری کنید.' : !/^(uploads|assets)\//.test(o.src) ? 'فقط تصاویر کتابخانه سایت (uploads/ یا assets/) مجاز است.' : !o.alt ? 'متن جایگزین تصویر را بنویسید.' : '';
          var errBox = m.el.querySelector('[data-err]');
          errBox.textContent = err;
          if (err) {
            var bad = !o.src || !/^(uploads|assets)\//.test(o.src) ? f.elements.src : f.elements.alt;
            bad.focus();
            errBox.scrollIntoView({ block: 'nearest' });
            return;
          }
          dims(o.src).then(function (d) {
            m.close();
            if (figure) {
              var tmp = document.createElement('div');
              tmp.innerHTML = figureHTML(o, d);
              figure.replaceWith(tmp.firstChild);
              sync();
            } else {
              insertBlock(figureHTML(o, d));
              sync();
            }
          });
        });
      }

      area.addEventListener('click', function (e) {
        var fig = e.target.closest('figure');
        if (fig && e.target.tagName === 'IMG' && area.contains(fig)) {
          A.$$('figure.is-selected', area).forEach(function (x) { x.classList.remove('is-selected'); });
          fig.classList.add('is-selected');
          imageDialog(fig);
        }
      });
      area.addEventListener('paste', function (e) {
        var cd = e.clipboardData || window.clipboardData;
        var files = Array.prototype.filter.call(cd.files || [], function (f) { return /^image\//.test(f.type); });
        e.preventDefault();
        saveRange();
        if (files.length) { uploadInto(files); return; }
        // Text is pasted plain, so no foreign styles or markup come in.
        exec('insertText', cd.getData('text/plain'));
      });
      area.addEventListener('dragover', function (e) {
        if (Array.prototype.some.call(e.dataTransfer.types || [], function (t) { return t === 'Files'; })) { e.preventDefault(); editor.classList.add('is-dragover'); }
      });
      area.addEventListener('dragleave', function () { editor.classList.remove('is-dragover'); });
      area.addEventListener('drop', function (e) {
        editor.classList.remove('is-dragover');
        var files = Array.prototype.filter.call(e.dataTransfer.files || [], function (f) { return /^image\//.test(f.type); });
        if (!files.length) return;
        e.preventDefault();
        var r = document.caretRangeFromPoint ? document.caretRangeFromPoint(e.clientX, e.clientY) : null;
        if (r && area.contains(r.startContainer)) saved = r;
        uploadInto(files);
      });

      /* ---------- small dialogs for link / table / button / product ---------- */
      function openLink() {
        var text = saved ? saved.toString() : '';
        A.formModal({ title: 'افزودن پیوند', saveText: 'افزودن',
          fields: [{ name: 'url', label: 'نشانی', dir: 'ltr', full: true, required: true, placeholder: 'products/... یا https://...', help: 'پیوند داخلی بدون دامنه بنویسید (مثلاً categories/brake-pads).' },
            { name: 'text', label: 'متن پیوند', full: true }],
          data: { text: text },
          save: function (d) {
            if (!d.url || UNSAFE_URL.test(d.url)) return Promise.reject({ message: 'نشانی معتبر نیست.', errors: { url: 'نشانی معتبر نیست.' } });
            var external = /^https?:\/\//i.test(d.url);
            insertHTML('<a href="' + A.esc(d.url) + '"' + (external ? ' target="_blank" rel="noopener noreferrer"' : '') + '>' + A.esc(d.text || d.url) + '</a>');
            sync();
            return Promise.resolve();
          } });
      }

      bar.addEventListener('click', function (e) {
        var b = e.target.closest('[data-cmd]');
        if (!b) return;
        var cmd = b.dataset.cmd;
        if (!source.hidden && cmd !== 'source' && cmd !== 'preview') { A.toast('ابتدا از حالت ویرایش HTML خارج شوید.', 'error'); return; }
        if (cmd !== 'source' && cmd !== 'preview') restoreRange();
        switch (cmd) {
          case 'undo': case 'redo': exec(cmd); break;
          case 'h2': case 'h3': case 'p': exec('formatBlock', '<' + cmd + '>'); break;
          case 'bold': case 'italic': exec(cmd); break;
          case 'ul': exec('insertUnorderedList'); break;
          case 'ol': exec('insertOrderedList'); break;
          case 'quote': exec('formatBlock', '<blockquote>'); break;
          case 'unlink': exec('unlink'); break;
          case 'link': saveRange(); openLink(); break;
          case 'image': saveRange(); imageDialog(null); break;
          case 'table':
            saveRange();
            A.formModal({ title: 'درج جدول', saveText: 'درج', fields: [{ name: 'rows', label: 'تعداد ردیف', type: 'number', step: 1 }, { name: 'cols', label: 'تعداد ستون', type: 'number', step: 1 }], data: { rows: 3, cols: 2 },
              save: function (d) {
                var rows = Math.max(1, Math.min(20, Number(d.rows) || 3)), cols = Math.max(1, Math.min(8, Number(d.cols) || 2));
                insertBlock('<table><thead><tr>' + new Array(cols + 1).join('<th>عنوان</th>') + '</tr></thead><tbody>' + new Array(rows + 1).join('<tr>' + new Array(cols + 1).join('<td>—</td>') + '</tr>') + '</tbody></table>');
                sync();
                return Promise.resolve();
              } });
            break;
          case 'button':
            saveRange();
            A.formModal({ title: 'درج دکمه', saveText: 'درج', fields: [{ name: 'text', label: 'متن دکمه', required: true }, { name: 'url', label: 'نشانی', dir: 'ltr', required: true }], data: { text: 'مشاهده محصولات', url: 'products' },
              save: function (d) {
                if (!d.text || !d.url || UNSAFE_URL.test(d.url)) return Promise.reject({ message: 'متن و نشانی معتبر لازم است.', errors: { url: 'نشانی معتبر نیست.' } });
                insertBlock('<p><a class="cta-button" href="' + A.esc(d.url) + '">' + A.esc(d.text) + '</a></p>');
                sync();
                return Promise.resolve();
              } });
            break;
          case 'product':
            saveRange();
            A.formModal({ title: 'درج کارت محصول', saveText: 'درج', fields: [{ name: 'ids', label: 'محصول (جستجو کنید)', type: 'multi', endpoint: 'api/admin/products', full: true }],
              save: function (d) {
                if (!d.ids.length) return Promise.reject({ message: 'یک محصول انتخاب کنید.', errors: {} });
                insertBlock(d.ids.map(function (id) { return '<div class="product-embed" data-product-id="' + Number(id) + '"></div>'; }).join(''));
                sync();
                return Promise.resolve();
              } });
            break;
          case 'note': insertBlock('<div class="note-box"><p>متن نکته…</p></div>'); break;
          case 'warning': insertBlock('<div class="warning-box"><p>متن هشدار…</p></div>'); break;
          case 'source':
            if (source.hidden) { source.value = area.innerHTML; source.hidden = false; area.hidden = true; }
            else { area.innerHTML = source.value; source.hidden = true; area.hidden = false; }
            b.setAttribute('aria-pressed', String(!source.hidden));
            break;
          case 'preview':
            A.modal({ title: 'پیش‌نمایش محتوا', wide: true, body: '<div class="rich-text preview-rich">' + (source.hidden ? area.innerHTML : source.value).replace(/<script[\s\S]*?<\/script>/gi, '') + '</div>' +
              '<p class="a-hint">نمایش نهایی پس از پاک‌سازی امن در سرور انجام می‌شود.</p>' });
            break;
        }
        sync();
      });
      refresh();
    },
    sync: function (form, name) {
      var slot = form.querySelector('[data-editor="' + name + '"]');
      var ta = form.querySelector('textarea[name="' + name + '"]');
      if (slot && slot._area) {
        A.$$('figure.is-selected, figure.a-uploading', slot._area).forEach(function (x) { if (x.classList.contains('a-uploading')) x.remove(); else x.classList.remove('is-selected'); });
        ta.value = slot._source.hidden ? slot._area.innerHTML : slot._source.value;
      }
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
