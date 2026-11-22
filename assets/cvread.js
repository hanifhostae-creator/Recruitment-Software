/* Read a CV in the browser (PDF text, or text recognition for photos / scans) and fill the candidate form.
   Nothing leaves the computer: the libraries and the language file are served from this website. */
(function () {
  'use strict';
  var BASE = 'assets/vendor/ocr/';
  var loaded = {};
  function load(src) {
    if (!loaded[src]) loaded[src] = new Promise(function (ok, fail) {
      var s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = function () { fail(new Error('Could not load ' + src)); }; document.head.appendChild(s);
    });
    return loaded[src];
  }

  // ---- text from a PDF (real text first; scanned PDFs are rendered and read like photos)
  function pdfText(file, progress) {
    return load(BASE + 'pdf.min.js').then(function () {
      var lib = window.pdfjsLib; lib.GlobalWorkerOptions.workerSrc = BASE + 'pdf.worker.min.js';
      return file.arrayBuffer().then(function (buf) { return lib.getDocument({ data: buf, isEvalSupported: false }).promise; });
    }).then(function (pdf) {
      var n = Math.min(pdf.numPages, 4), pages = [], i, chain = Promise.resolve();
      for (i = 1; i <= n; i++) (function (k) {
        chain = chain.then(function () { return pdf.getPage(k); }).then(function (pg) {
          return pg.getTextContent().then(function (tc) {
            var lines = [], last = null, line = '';
            tc.items.forEach(function (it) {
              var y = Math.round(it.transform[5]);
              if (last !== null && Math.abs(y - last) > 3) { lines.push(line); line = ''; }
              line += (line && !/\s$/.test(line) ? ' ' : '') + it.str; last = y;
            });
            lines.push(line); pages.push({ page: pg, text: lines.join('\n') });
          });
        });
      })(i);
      return chain.then(function () {
        var text = pages.map(function (p) { return p.text; }).join('\n');
        if (text.replace(/\s/g, '').length > 80) return text;
        // scanned PDF: draw the pages and read them
        if (progress) progress('Scanned PDF, reading the pages…', 10);
        return Promise.all(pages.map(function (p) {
          var vp = p.page.getViewport({ scale: 2 }), c = document.createElement('canvas'); c.width = vp.width; c.height = vp.height;
          return p.page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise.then(function () { return c; });
        })).then(function (canvases) { return ocr(canvases, progress); });
      });
    });
  }

  // ---- text recognition for photos
  var workerP = null;
  function ocr(images, progress) {
    return load(BASE + 'tesseract.min.js').then(function () {
      if (!workerP) workerP = Tesseract.createWorker('eng', 1, {
        workerPath: BASE + 'worker.min.js', corePath: BASE + 'core', langPath: BASE + 'lang', gzip: false, workerBlobURL: false,
        logger: function (m) { if (progress && m.status === 'recognizing text') progress('Reading the CV…', Math.round(20 + m.progress * 75)); else if (progress && /loading/.test(m.status)) progress('Preparing the reader (first time only)…', 8); }
      });
      return workerP;
    }).then(function (w) {
      var out = [], chain = Promise.resolve();
      images.forEach(function (img) { chain = chain.then(function () { return w.recognize(img); }).then(function (r) { out.push(r.data.text); }); });
      return chain.then(function () { return out.join('\n'); });
    });
  }
  function readFile(file, progress) {
    if (/pdf$/i.test(file.type) || /\.pdf$/i.test(file.name)) return pdfText(file, progress);
    if (/^image\//.test(file.type) || /\.(jpe?g|png)$/i.test(file.name)) return ocr([file], progress);
    return Promise.reject(new Error('Word files cannot be read automatically. Please type the details, or upload a PDF or photo of the CV.'));
  }

  // ---- understand the text
  var DEMONYM = { indian: 'India', pakistani: 'Pakistan', bangladeshi: 'Bangladesh', filipino: 'Philippines', filipina: 'Philippines', nepali: 'Nepal', nepalese: 'Nepal', 'sri lankan': 'Sri Lanka',
    kenyan: 'Kenya', nigerian: 'Nigeria', ugandan: 'Uganda', ghanaian: 'Ghana', ethiopian: 'Ethiopia', egyptian: 'Egypt', sudanese: 'Sudan', indonesian: 'Indonesia', cameroonian: 'Cameroon',
    tanzanian: 'Tanzania', rwandan: 'Rwanda', zimbabwean: 'Zimbabwe', moroccan: 'Morocco', tunisian: 'Tunisia', jordanian: 'Jordan', syrian: 'Syria', lebanese: 'Lebanon', afghan: 'Afghanistan', burmese: 'Myanmar', vietnamese: 'Vietnam' };
  var MONTHS = { jan: 1, feb: 2, mar: 3, apr: 4, may: 5, jun: 6, jul: 7, aug: 8, sep: 9, oct: 10, nov: 11, dec: 12 };
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function toDate(s) {
    var m = s.match(/(\d{1,2})[\/\-. ]+([A-Za-z]{3,9}|\d{1,2})[\/\-. ,]+(\d{2,4})/); if (!m) { m = s.match(/(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})/); if (m) return m[1] + '-' + pad(+m[2]) + '-' + pad(+m[3]); return ''; }
    var d = +m[1], mo = isNaN(+m[2]) ? MONTHS[m[2].slice(0, 3).toLowerCase()] : +m[2], y = +m[3]; if (y < 100) y += y > 30 ? 1900 : 2000;
    if (!mo || mo > 12 || d > 31) return ''; return y + '-' + pad(mo) + '-' + pad(d);
  }
  function section(text, heads) {
    var re = new RegExp('^\\s*(' + heads + ')\\s*[:\\-]?\\s*$', 'im'), m = re.exec(text); if (!m) return '';
    var rest = text.slice(m.index + m[0].length).split('\n'), out = [];
    for (var i = 0; i < rest.length && out.length < 12; i++) {
      var l = rest[i].trim();
      if (/^(education|qualification|experience|work history|employment|skills|languages|personal|references|declaration|objective|profile|summary|contact|hobbies|certifications?)\b.{0,20}$/i.test(l) && out.length) break;
      if (l) out.push(l);
    }
    return out.join('\n').slice(0, 700);
  }
  function optionMatch(sel, text) {
    if (!sel) return '';
    var t = ' ' + text.toLowerCase().replace(/\s+/g, ' ') + ' ', best = '';
    Array.prototype.forEach.call(sel.options, function (o) {
      var v = (o.value || '').trim(); if (!v || v === 'Other') return;
      var re = new RegExp('[^a-z]' + v.toLowerCase().replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '[^a-z]');
      if (re.test(t) && v.length > best.length) best = v;
    });
    return best;
  }
  function parse(text, form) {
    var r = {}, T = text.replace(/\r/g, ''), m;
    if ((m = T.match(/[\w.+-]+@[\w-]+\.[\w.-]+/))) r.email = m[0].toLowerCase().replace(/\.$/, '');
    var phones = (T.match(/(?:\+|00)?\d[\d \-()]{7,16}\d/g) || []).map(function (p) { return p.replace(/[^\d+]/g, ''); }).filter(function (p) { var d = p.replace(/\D/g, ''); return d.length >= 9 && d.length <= 15 && !/^(19|20)\d{6}$/.test(d); });
    if (phones.length) r.phone = phones[0];
    var wa = T.match(/whats\s*app[^\d+]{0,12}((?:\+|00)?\d[\d \-]{7,16}\d)/i); if (wa) r.whatsapp = wa[1].replace(/[^\d+]/g, ''); else if (phones[1]) r.whatsapp = phones[1];
    if ((m = T.match(/(?:full\s*name|name)\s*[:\-]\s*([A-Za-z][A-Za-z .'-]{2,60})/i))) r.full_name = m[1].trim();
    else {
      var lines = T.split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
      for (var i = 0; i < Math.min(lines.length, 8); i++) {
        var l = lines[i].replace(/[^A-Za-z .'-]/g, '').trim();
        if (/[:@\d]/.test(lines[i]) || /curriculum|resume|vitae|\bcv\b|profile|objective|contact|address|phone|mobile|email|nationality|birth|passport|visa|experience|skills|education|qualification|personal|details/i.test(lines[i])) continue;
        if (/^[A-Za-z][A-Za-z.'-]+( [A-Za-z][A-Za-z.'-]+){1,3}$/.test(l) && l.length <= 40) { r.full_name = l; break; }
      }
    }
    if (r.full_name) r.full_name = r.full_name.toLowerCase().replace(/\b[a-z]/g, function (c) { return c.toUpperCase(); }).replace(/\s+/g, ' ');
    var nat = form.querySelector('[name=nationality]');
    if ((m = T.match(/nationality\s*[:\-]?\s*([A-Za-z ]{3,25})/i))) {
      var n = m[1].trim().split(/\s{2,}|\n/)[0].toLowerCase(); r.nationality = DEMONYM[n] || DEMONYM[n.split(' ')[0]] || optionMatch(nat, n) || '';
    }
    if (!r.nationality && nat) { r.nationality = optionMatch(nat, T); if (!r.nationality) Object.keys(DEMONYM).some(function (k) { if (new RegExp('\\b' + k + '\\b', 'i').test(T)) { r.nationality = DEMONYM[k]; return true; } return false; }); }
    if ((m = T.match(/passport\s*(?:no\.?|number|#)?\s*[:\-]?\s*([A-Z]{1,2}\s?\d{6,8})/i))) r.passport_no = m[1].replace(/\s/g, '').toUpperCase();
    if ((m = T.match(/(?:date\s*of\s*birth|d\.?\s?o\.?\s?b\.?|birth\s*date|born)\s*[:\-]?\s*([^\n]{6,22})/i))) r.dob = toDate(m[1]);
    if ((m = T.match(/visa\s*(?:status|type)?\s*[:\-]?\s*(visit|cancel(?:led)?|employment|family|sponsor)/i))) r.visa_type = { cancelled: 'Cancel' }[m[1].toLowerCase()] || (m[1][0].toUpperCase() + m[1].slice(1).toLowerCase());
    var pos = form.querySelector('[name=applied_position]');
    var near = (T.match(/(?:position|post|applying\s*for|objective|job\s*title|desired)[^\n]{0,80}/i) || [''])[0];
    r.applied_position = optionMatch(pos, near) || optionMatch(pos, T);
    r.experience = section(T, 'work experience|experience|employment history|work history|professional experience');
    if (!r.experience && (m = T.match(/(\d{1,2})\+?\s*years?\s*(?:of\s*)?(?:experience|exp)[^\n]{0,80}/i))) r.experience = m[0];
    r.skills = section(T, 'skills|key skills|technical skills|core competencies').replace(/\n/g, ', ');
    var edu = section(T, 'education|educational qualifications?|qualifications?|academic'); if (edu) r.qualification = edu.split('\n')[0].slice(0, 120);
    if ((m = T.match(/(?:expected|desired)\s*salary\s*[:\-]?\s*(?:aed|dhs?)?\s*([\d,]{3,7})/i))) r.expected_salary = m[1].replace(/,/g, '');
    return r;
  }
  function fill(form, data) {
    var n = 0, more = false;
    Object.keys(data).forEach(function (k) {
      var v = data[k], el = form.querySelector('[name="' + k + '"]'); if (!v || !el || (el.value && el.value.trim())) return;
      if (el.tagName === 'SELECT') { if (!Array.prototype.some.call(el.options, function (o) { return o.value === v; })) return; }
      el.value = v; el.classList.add('autofilled'); el.dispatchEvent(new Event('change', { bubbles: true })); n++;
      var d = el.closest('details'); if (d) { d.open = true; more = true; }
    });
    return n;
  }
  window.RHCV = { readFile: readFile, ocr: ocr, parse: parse, fill: fill };
})();
