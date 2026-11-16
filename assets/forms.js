/* Printable forms: copies the PDF form once per serial number and prints the number on it (runs in the browser). */
(function () {
  'use strict';
  if (!window.PDFLib) return;
  var MM = 72 / 25.4;
  function build(cfg, serials, progress) {
    return fetch(cfg.pdf, { credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw new Error('The PDF could not be loaded.'); return r.arrayBuffer(); }).then(function (buf) {
      return Promise.all([PDFLib.PDFDocument.load(buf, { ignoreEncryption: true }), PDFLib.PDFDocument.create()]);
    }).then(function (d) {
      var tpl = d[0], out = d[1], idx = tpl.getPageIndices();
      return out.embedFont(PDFLib.StandardFonts.HelveticaBold).then(function (font) {
        var i = 0;
        function next() {
          if (i >= serials.length) return out.save();
          var serial = serials[i++];
          return out.copyPages(tpl, idx).then(function (pages) {
            pages.forEach(function (p, n) {
              out.addPage(p);
              if (n > 0 && !cfg.every) return;
              var text = (cfg.label ? cfg.label + ' ' : '') + serial, size = cfg.size || 12, w = font.widthOfTextAtSize(text, size);
              var box = p.getCropBox ? p.getCropBox() : { x: 0, y: 0, width: p.getWidth(), height: p.getHeight() };
              var x = box.x + cfg.x * MM, y = box.y + box.height - cfg.y * MM - size;
              if (cfg.box) p.drawRectangle({ x: x - 3, y: y - 3, width: w + 6, height: size + 6, color: PDFLib.rgb(1, 1, 1) });
              p.drawText(text, { x: x, y: y, size: size, font: font, color: PDFLib.rgb(0.04, 0.08, 0.19) });
            });
            if (progress) progress(i, serials.length);
            return next();
          });
        }
        return next();
      });
    });
  }
  function show(outEl, bytes, fileName) {
    var url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
    outEl.hidden = false;
    outEl.innerHTML = '<div class="alert ok">The PDF is ready.</div><div class="action-bar"><a class="btn big" target="_blank" rel="noopener" data-open>Open &amp; print</a><a class="btn ghost" data-dl>Download PDF</a></div>';
    var o = outEl.querySelector('[data-open]'), dl = outEl.querySelector('[data-dl]');
    o.href = url; dl.href = url; dl.download = fileName.replace(/[^A-Za-z0-9 _.-]+/g, '-') + '.pdf';
  }
  function fail(outEl, e) { outEl.hidden = false; outEl.innerHTML = '<div class="alert bad">The PDF could not be prepared: ' + String(e && e.message || e).replace(/[<>&]/g, '') + '</div>'; }
  function start() {
  var batch = document.querySelector('[data-print-batch]');
  if (batch) {
    var cfg = JSON.parse(batch.getAttribute('data-print-batch')), outEl = batch.querySelector('.print-out');
    build(cfg, cfg.serials, function (i, n) { outEl.innerHTML = '<p class="muted">Preparing the PDF… ' + i + ' / ' + n + '</p>'; })
      .then(function (bytes) { show(outEl, bytes, cfg.file); }).catch(function (e) { fail(outEl, e); });
    batch.scrollIntoView({ block: 'start' });
  }
  document.querySelectorAll('[data-print-test]').forEach(function (b) {
    b.addEventListener('click', function () {
      var cfg = JSON.parse(b.getAttribute('data-print-test')), outEl = b.closest('.form-card').querySelector('.print-out');
      outEl.hidden = false; outEl.innerHTML = '<p class="muted">Preparing a test copy…</p>';
      build(cfg, cfg.serials).then(function (bytes) { show(outEl, bytes, cfg.name + '-test'); }).catch(function (e) { fail(outEl, e); });
    });
  });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
