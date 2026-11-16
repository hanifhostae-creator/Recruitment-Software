/* RH Management — small vanilla JS helpers (no framework). */
(function () {
  'use strict';
  var root = document.documentElement;

  // theme
  document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', t);
      try { localStorage.setItem('rh-theme', t); } catch (e) {}
    });
  });

  // mobile sidebar
  document.querySelectorAll('[data-open-side]').forEach(function (b) { b.addEventListener('click', function () { document.body.classList.add('side-open'); }); });
  document.querySelectorAll('[data-close-side]').forEach(function (b) { b.addEventListener('click', function () { document.body.classList.remove('side-open'); }); });

  // clickable rows
  document.addEventListener('click', function (ev) {
    var tr = ev.target.closest('tr[data-href]');
    if (!tr || ev.target.closest('a,button,input,select,label,form')) return;
    window.location = tr.getAttribute('data-href');
  });

  // "Other" option reveals a required detail field
  function syncOther(sel) {
    var box = document.getElementById(sel.getAttribute('data-other'));
    if (!box) return;
    var on = sel.value === 'Other';
    box.classList.toggle('show', on);
    box.required = on;
  }
  document.querySelectorAll('select[data-other]').forEach(function (s) { syncOther(s); s.addEventListener('change', function () { syncOther(s); }); });

  // fields shown only for some choices: data-switch="res" on a select, data-when="res=Selected paid=Yes" on a box
  function syncWhen(form) {
    form.querySelectorAll('[data-when]').forEach(function (box) {
      var ok = box.getAttribute('data-when').split(' ').every(function (c) {
        var kv = c.split('='), sel = form.querySelector('[data-switch="' + kv[0] + '"]');
        return !sel || sel.value === kv[1];
      });
      box.hidden = !ok;
    });
  }
  document.querySelectorAll('form').forEach(function (f) { if (f.querySelector('[data-switch]')) { syncWhen(f); f.addEventListener('change', function () { syncWhen(f); }); } });

  // auto-submit filters
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form.submit(); }); });

  // confirmation dialog for destructive actions
  var modal = document.getElementById('confirm'), pending = null;
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    var msg = f.getAttribute('data-confirm');
    if (!msg || f.dataset.ok) return;
    ev.preventDefault();
    if (!modal) { if (window.confirm(msg)) { f.dataset.ok = 1; f.submit(); } return; }
    pending = f;
    modal.querySelector('[data-msg]').textContent = msg;
    modal.hidden = false;
    modal.querySelector('[data-yes]').focus();
  }, true);
  if (modal) {
    modal.querySelector('[data-no]').addEventListener('click', function () { modal.hidden = true; pending = null; });
    modal.querySelector('[data-yes]').addEventListener('click', function () { modal.hidden = true; if (pending) { pending.dataset.ok = 1; pending.submit(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.hidden = true; });
  }

  // count-up on stat tiles
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduce) document.querySelectorAll('[data-count]').forEach(function (el) {
    var target = parseFloat(el.getAttribute('data-count'));
    if (isNaN(target) || target <= 0 || target > 1e9) return;
    var txt = el.textContent, pre = el.getAttribute('data-prefix') || '', fd = el.hasAttribute('data-dec') ? 2 : 0, start = null;
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min(1, (ts - start) / 900), v = target * (1 - Math.pow(1 - p, 3));
      el.textContent = p < 1 ? pre + v.toLocaleString(undefined, { minimumFractionDigits: fd, maximumFractionDigits: fd }) : txt;
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  });

  // click ripple on buttons, tiles, menu items and section tabs
  document.addEventListener('pointerdown', function (ev) {
    var el = ev.target.closest('.btn, .stat, .nav a, .sec-tabs a, .tabs a, .kcard, .vac');
    if (!el || reduce) return;
    var r = el.getBoundingClientRect(), d = Math.max(r.width, r.height), s = document.createElement('span');
    s.className = 'rip'; s.style.width = s.style.height = d + 'px';
    s.style.left = (ev.clientX - r.left - d / 2) + 'px'; s.style.top = (ev.clientY - r.top - d / 2) + 'px';
    el.appendChild(s); setTimeout(function () { if (s.parentNode) s.parentNode.removeChild(s); }, 650);
  });

  // 3D tilt + light glare on stat tiles (mouse only)
  var fine = window.matchMedia && window.matchMedia('(pointer: fine)').matches;
  if (fine && !reduce) document.querySelectorAll('.stat').forEach(function (t) {
    var g = document.createElement('i'); g.className = 'glare'; t.appendChild(g);
    t.addEventListener('mousemove', function (ev) {
      var r = t.getBoundingClientRect(), x = (ev.clientX - r.left) / r.width, y = (ev.clientY - r.top) / r.height;
      t.style.setProperty('--ry', ((x - .5) * 10).toFixed(2) + 'deg'); t.style.setProperty('--rx', ((.5 - y) * 10).toFixed(2) + 'deg');
      t.style.setProperty('--mx', (x * 100).toFixed(1) + '%'); t.style.setProperty('--my', (y * 100).toFixed(1) + '%');
    });
    t.addEventListener('mouseleave', function () { t.style.removeProperty('--rx'); t.style.removeProperty('--ry'); });
  });

  // sidebar: sliding highlight that follows the mouse and rests on the open page
  var nav = document.querySelector('.nav');
  if (nav) {
    var glide = document.createElement('span'); glide.className = 'nav-glide'; nav.appendChild(glide); nav.classList.add('has-glide');
    var on = nav.querySelector('a.on');
    var moveTo = function (a) { if (!a) { glide.style.opacity = 0; return; } glide.style.opacity = 1; glide.style.top = a.offsetTop + 'px'; glide.style.height = a.offsetHeight + 'px'; };
    nav.querySelectorAll('a').forEach(function (a, i) { a.style.setProperty('--i', i); a.addEventListener('mouseenter', function () { moveTo(a); }); });
    nav.addEventListener('mouseleave', function () { moveTo(on); });
    moveTo(on);
    if (on && on.scrollIntoView) { var nr = nav.getBoundingClientRect(), orr = on.getBoundingClientRect(); if (orr.bottom > nr.bottom) nav.scrollTop = on.offsetTop - nav.clientHeight / 2; }
  }

  // cards slide in as they scroll into view (charts start when visible)
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (es) { es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } }); }, { threshold: .08 });
    document.querySelectorAll('.content .card, .content .grid > .card').forEach(function (c) {
      var r = c.getBoundingClientRect();
      if (r.top > window.innerHeight) { c.classList.add('reveal'); io.observe(c); }
    });
  }

  // remaining amount on the "File done" form
  document.querySelectorAll('[data-pay-total]').forEach(function (t) {
    var f = t.form, p = f.querySelector('[data-pay-paid]'), out = f.querySelector('[data-pay-left]');
    var yes = f.querySelector('[name="paid_yes"]');
    function calc() { var a = parseFloat(t.value) || 0, b = yes && yes.value === 'No' ? 0 : (p ? parseFloat(p.value) || 0 : 0); out.textContent = a ? (a - b).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'; out.style.color = a - b < 0 ? 'var(--bad)' : ''; }
    t.addEventListener('input', calc); if (p) p.addEventListener('input', calc); if (yes) yes.addEventListener('change', calc); calc();
  });

  // chat message box: attach, camera photo, location, auto-growing text
  document.querySelectorAll('[data-composer]').forEach(function (f) {
    var file = f.querySelector('[data-comp-file]'), cam = f.querySelector('[data-comp-cam]'), txt = f.querySelector('[data-comp-text]'),
      bar = f.querySelector('.comp-file'), nameEl = f.querySelector('[data-comp-name]');
    function show() { var fl = file.files && file.files[0]; bar.hidden = !fl; if (fl) nameEl.textContent = (/^image\//.test(fl.type) ? '📷 ' : '📎 ') + fl.name; }
    file.addEventListener('change', show);
    if (cam) cam.addEventListener('change', function () {
      if (!cam.files || !cam.files[0]) return;
      try { var dt = new DataTransfer(); dt.items.add(new File([cam.files[0]], 'photo-' + Date.now() + '.jpg', { type: cam.files[0].type || 'image/jpeg' })); file.files = dt.files; }
      catch (e) { cam.name = 'file'; file.name = ''; nameEl.textContent = '📷 Photo'; bar.hidden = false; return; }
      show();
    });
    f.querySelector('[data-comp-clear]').addEventListener('click', function () { file.value = ''; if (cam) { cam.value = ''; cam.name = ''; file.name = 'file'; } show(); });
    var loc = f.querySelector('[data-comp-loc]');
    if (loc) loc.addEventListener('click', function () {
      if (!navigator.geolocation) { alert('Location is not available on this device.'); return; }
      loc.classList.add('busy');
      navigator.geolocation.getCurrentPosition(function (p) {
        loc.classList.remove('busy');
        var link = 'https://maps.google.com/?q=' + p.coords.latitude.toFixed(6) + ',' + p.coords.longitude.toFixed(6);
        txt.value = (txt.value ? txt.value + '\n' : '') + 'My location: ' + link; f.submit();
      }, function () { loc.classList.remove('busy'); alert('Please allow location access in your browser, then try again.'); }, { enableHighAccuracy: true, timeout: 15000 });
    });
    function grow() { txt.style.height = 'auto'; txt.style.height = Math.min(txt.scrollHeight, 140) + 'px'; }
    txt.addEventListener('input', grow);
    txt.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && window.matchMedia('(min-width: 821px)').matches) { e.preventDefault(); if (txt.value.trim() || (file.files && file.files.length)) f.submit(); } });
    f.addEventListener('submit', function (e) { if (!txt.value.trim() && !(file.files && file.files.length) && !(cam && cam.name)) e.preventDefault(); });
  });

  // CV camera: photograph a paper CV (several pages are joined into one image)
  document.querySelectorAll('[data-cam]').forEach(function (box) {
    var input = document.getElementById(box.getAttribute('data-cam')), cam = box.querySelector('.cam-box'), video = cam.querySelector('video'),
      strip = cam.querySelector('.cam-pages'), info = box.querySelector('[data-cam-info]'), nativeIn = box.querySelector('[data-cam-native]'), stream = null, pages = [];
    function stop() { if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; } cam.hidden = true; }
    function attach(blob, n) {
      try {
        var dt = new DataTransfer(); dt.items.add(new File([blob], 'cv-photo-' + Date.now() + '.jpg', { type: 'image/jpeg' })); input.files = dt.files;
        info.innerHTML = ''; var img = document.createElement('img'); img.className = 'cam-thumb'; img.src = URL.createObjectURL(blob); info.appendChild(img);
        info.appendChild(document.createTextNode(' Photo attached' + (n > 1 ? ' (' + n + ' pages)' : '') + ' · ' + Math.round(blob.size / 1024) + ' KB'));
      } catch (e) { info.textContent = 'This browser cannot attach a photo here. Please use the file button.'; }
    }
    function shot() {
      if (!video.videoWidth) return;
      var c = document.createElement('canvas'); c.width = video.videoWidth; c.height = video.videoHeight; c.getContext('2d').drawImage(video, 0, 0);
      pages.push(c); var t = document.createElement('canvas'); t.width = 90; t.height = Math.round(90 * c.height / c.width); t.getContext('2d').drawImage(c, 0, 0, t.width, t.height); strip.appendChild(t);
    }
    function join() {
      var W = 1240, H = 0, hs = pages.map(function (c) { var h = Math.round(c.height * W / c.width); H += h; return h; });
      var out = document.createElement('canvas'), g = out.getContext('2d'), y = 0; out.width = W; out.height = H; g.fillStyle = '#fff'; g.fillRect(0, 0, W, H);
      pages.forEach(function (c, i) { g.drawImage(c, 0, y, W, hs[i]); y += hs[i]; }); return out;
    }
    box.querySelector('[data-cam-open]').addEventListener('click', function () {
      if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 } }, audio: false }).then(function (s) {
          stream = s; video.srcObject = s; video.play(); pages = []; strip.innerHTML = ''; cam.hidden = false;
        }).catch(function () { nativeIn.click(); });
      } else nativeIn.click();
    });
    box.querySelector('[data-cam-shot]').addEventListener('click', function () { if (pages.length < 6) shot(); });
    box.querySelector('[data-cam-done]').addEventListener('click', function () { if (!pages.length) shot(); if (!pages.length) return; var n = pages.length; join().toBlob(function (b) { attach(b, n); }, 'image/jpeg', 0.82); stop(); });
    box.querySelector('[data-cam-cancel]').addEventListener('click', stop);
    nativeIn.addEventListener('change', function () { if (nativeIn.files[0]) attach(nativeIn.files[0], 1); });
    input.addEventListener('change', function () { if (input.files[0]) info.textContent = input.files[0].name; });
  });

  // print buttons (no inline handlers, allowed by the security policy)
  document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

  // copy buttons
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var inp = document.getElementById(b.getAttribute('data-copy'));
      if (!inp) return;
      inp.select();
      try { navigator.clipboard.writeText(inp.value); } catch (e) { document.execCommand('copy'); }
      var t = b.textContent; b.textContent = 'Copied'; setTimeout(function () { b.textContent = t; }, 1400);
    });
  });

  // journal / invoice line rows
  document.querySelectorAll('[data-add-line]').forEach(function (b) {
    b.addEventListener('click', function () {
      var tb = document.querySelector(b.getAttribute('data-add-line'));
      var tr = tb.querySelector('tr').cloneNode(true);
      tr.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      tb.appendChild(tr);
      sumLines();
    });
  });
  function sumLines() {
    var d = 0, c = 0;
    document.querySelectorAll('[name="debit[]"]').forEach(function (i) { d += parseFloat(i.value) || 0; });
    document.querySelectorAll('[name="credit[]"]').forEach(function (i) { c += parseFloat(i.value) || 0; });
    var out = document.getElementById('jtotal');
    if (out) { out.textContent = 'Debit ' + d.toFixed(2) + ' · Credit ' + c.toFixed(2) + (Math.abs(d - c) < 0.005 && d > 0 ? ' · Balanced ✓' : ' · Not balanced'); out.className = Math.abs(d - c) < 0.005 && d > 0 ? 'badge b-ok' : 'badge b-bad'; }
  }
  document.addEventListener('input', function (e) { if (e.target.name === 'debit[]' || e.target.name === 'credit[]') sumLines(); });
  sumLines();

  // live notifications: beep + pop-up, checked every 12 seconds (staff notifications, candidate chat)
  var pollUrl = document.body.getAttribute('data-poll');
  var since = parseInt(document.body.getAttribute('data-since') || '0', 10);
  var actx = null;
  function soundOn() { try { return localStorage.getItem('rh-sound') !== 'off'; } catch (e) { return true; } }
  function syncSound() { if (soundOn()) document.body.removeAttribute('data-muted'); else document.body.setAttribute('data-muted', '1'); }
  function audio() { if (!actx) { var C = window.AudioContext || window.webkitAudioContext; if (C) { try { actx = new C(); } catch (e) {} } } return actx; }
  function unlock() { var c = audio(); if (c && c.state === 'suspended') c.resume(); }
  ['click', 'keydown', 'touchstart'].forEach(function (ev) { document.addEventListener(ev, unlock, { passive: true }); });
  var SOUNDS = {
    chime: [[880, 0, .16, 'sine'], [1175, .17, .16, 'sine'], [880, .34, .16, 'sine'], [1175, .51, .2, 'sine']],
    bell: [[1318, 0, .9, 'triangle'], [1976, 0, .5, 'sine'], [1318, .45, .7, 'triangle']],
    ding: [[1046, 0, .25, 'sine'], [1568, .13, .45, 'sine']],
    pop: [[620, 0, .07, 'square'], [930, .08, .09, 'sine']],
    soft: [[523, 0, .2, 'sine'], [659, .16, .2, 'sine'], [784, .32, .3, 'sine']],
    alert: [[740, 0, .14, 'square'], [740, .2, .14, 'square'], [988, .4, .22, 'square']],
    cash: [[1568, 0, .08, 'square'], [2093, .09, .08, 'square'], [2637, .18, .35, 'triangle']]
  };
  function beep(force, name) {
    if ((!force && !soundOn()) || name === 'none') return;
    var c = audio(); if (!c) return;
    try {
      if (c.state === 'suspended') c.resume();
      var t = c.currentTime;
      (SOUNDS[name] || SOUNDS.chime).forEach(function (n) {
        var o = c.createOscillator(), g = c.createGain(), vol = n[3] === 'square' ? 0.08 : 0.25;
        o.type = n[3]; o.frequency.value = n[0];
        g.gain.setValueAtTime(0.0001, t + n[1]);
        g.gain.exponentialRampToValueAtTime(vol, t + n[1] + 0.015);
        g.gain.exponentialRampToValueAtTime(0.0001, t + n[1] + n[2]);
        o.connect(g); g.connect(c.destination); o.start(t + n[1]); o.stop(t + n[1] + n[2] + 0.02);
      });
    } catch (e) {}
  }
  document.querySelectorAll('[data-test-sound]').forEach(function (b) {
    b.addEventListener('click', function () { var sel = document.getElementById(b.getAttribute('data-test-sound')); unlock(); beep(true, sel ? sel.value : 'chime'); });
  });
  // spoken announcement (browser voice, English) — 10 styles chosen by the Super Admin
  var FEM = /female|woman|zira|susan|hazel|heera|kate|serena|libby|sonia|samantha|karen|moira|tessa|veena|catherine|aria|jenny|natasha|neerja|kalpana|swara|emma|amy|olivia|ava|allison|victoria|fiona|google us english|google uk english female/i,
    MAL = /male|man\b|david|mark|george|ravi|daniel|alex|fred|ryan|guy|rishi|james|william|thomas|prabhat|hemant|arthur|oliver|aaron|google uk english male/i;
  var STYLES = { 1: ['en-GB', 'f', .92, 1], 2: ['en-GB', 'm', .92, 1], 3: ['en-US', 'f', .95, 1.05], 4: ['en-US', 'm', .95, 1], 5: ['en-IN', 'f', .88, 1],
    6: ['en-IN', 'm', .88, 1], 7: ['en-GB', 'f', .78, 1, ''], 8: ['en-GB', 'm', .84, .88, 'Attention please. '], 9: ['en-AU', 'f', .92, 1.05], 10: ['en-US', 'f', .9, 1.18] };
  function pickVoice(st) {
    var vs = window.speechSynthesis ? window.speechSynthesis.getVoices() : [], lang = st[0].toLowerCase(), g = st[1], i, best = null, score = -1;
    for (i = 0; i < vs.length; i++) {
      var v = vs[i], l = (v.lang || '').toLowerCase().replace('_', '-'), sc = 0;
      if (l.indexOf('en') !== 0) continue;
      if (l === lang) sc += 10; else if (l.slice(0, 2) === 'en') sc += 2;
      var isF = FEM.test(v.name) && !/\bmale\b/i.test(v.name.replace(/female/i, '')), isM = MAL.test(v.name) && !isF;
      if ((g === 'f' && isF) || (g === 'm' && isM)) sc += 6;
      if (/natural|neural|online|google/i.test(v.name)) sc += 1;
      if (sc > score) { score = sc; best = v; }
    }
    return best;
  }
  function styleNo(n) { n = n || parseInt(document.body.getAttribute('data-voice') || '1', 10); return STYLES[n] ? n : 1; }
  function speak(text, force, style) {
    if (!text || (!force && !soundOn()) || !window.speechSynthesis || !window.SpeechSynthesisUtterance) return;
    try {
      var st = STYLES[styleNo(style)], v = pickVoice(st), say = (st[4] || '') + text;
      var u = new SpeechSynthesisUtterance(say);
      if (v) u.voice = v; u.lang = v ? v.lang : st[0]; u.rate = st[2]; u.pitch = st[3]; u.volume = 1;
      window.speechSynthesis.cancel();
      if (st.length > 4) setTimeout(function () { window.speechSynthesis.speak(u); }, 450); else window.speechSynthesis.speak(u);
    } catch (e) {}
  }
  function showVoiceNames() {
    document.querySelectorAll('[data-voice-name]').forEach(function (el) { var v = pickVoice(STYLES[el.getAttribute('data-voice-name')]); el.textContent = v ? 'This device: ' + v.name : 'This device: default voice'; });
  }
  if (window.speechSynthesis) { window.speechSynthesis.onvoiceschanged = showVoiceNames; setTimeout(showVoiceNames, 300); }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-voice-test]'); if (!b) return;
    ev.preventDefault(); unlock(); var n = parseInt(b.getAttribute('data-voice-test'), 10);
    var r = b.closest('label') && b.closest('label').querySelector('input[type=radio]'); if (r) r.checked = true;
    speak('New candidate entered: Ahmed Khan, applying for Driver.', true, n);
  });
  if (window.speechSynthesis) window.speechSynthesis.getVoices();
  function toast(text, link, label, iconHtml, say) {
    var box = document.getElementById('toasts'); if (!box) return;
    var w = document.createElement('div'); w.className = 'toast';
    var a = document.createElement(link ? 'a' : 'div'); a.className = 'toast-main'; if (link) a.href = link;
    a.innerHTML = '<i class="toast-i"></i><div><b></b><span></span></div>';
    a.querySelector('b').textContent = label || 'New';
    a.querySelector('span').textContent = text;
    if (iconHtml) a.querySelector('.toast-i').innerHTML = iconHtml; else a.querySelector('.toast-i').remove();
    w.appendChild(a);
    if (say) { var r = document.createElement('button'); r.type = 'button'; r.className = 'toast-rep'; r.title = 'Repeat the voice message'; r.setAttribute('aria-label', 'Repeat'); r.setAttribute('data-say', say); r.innerHTML = '&#8635;'; w.appendChild(r); }
    var x = document.createElement('button'); x.type = 'button'; x.className = 'toast-x'; x.setAttribute('aria-label', 'Close'); x.innerHTML = '&times;'; x.addEventListener('click', function () { w.remove(); }); w.appendChild(x);
    box.appendChild(w); setTimeout(function () { if (w.parentNode) w.parentNode.removeChild(w); }, 20000);
  }
  // "Repeat" buttons: read a notification again
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-say]');
    if (b) { ev.preventDefault(); ev.stopPropagation(); unlock(); speak(b.getAttribute('data-say'), true); b.classList.remove('spin'); void b.offsetWidth; b.classList.add('spin'); return; }
    var t = ev.target.closest('[data-say-input]');
    if (t) {
      var inp = document.getElementById(t.getAttribute('data-say-input')); if (!inp) return;
      var sample = { name: 'Ahmed Khan', position: 'Driver', amount: 'AED 1,500.00', party: 'Ahmed Khan', type: 'enquiry', seq: '1st', response: 'Selected', ref: 'RCT-0001' };
      var chosen = document.querySelector('input[name=voice_style]:checked');
      speak((inp.value || inp.placeholder).replace(/\{(\w+)\}/g, function (m, k) { return sample[k] || ''; }), true, chosen ? parseInt(chosen.value, 10) : 0);
    }
  }, true);
  var sayBar = document.querySelector('.say-bar[data-autosay]');
  if (sayBar) setTimeout(function () { speak(sayBar.getAttribute('data-autosay')); }, 300);
  syncSound();
  document.querySelectorAll('[data-sound-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      try { localStorage.setItem('rh-sound', soundOn() ? 'off' : 'on'); } catch (e) {}
      syncSound(); if (soundOn()) beep(true, 'chime');
    });
  });
  var baseTitle = document.title;
  function poll() {
    if (!pollUrl || document.hidden) return;
    fetch(pollUrl + '?since=' + since, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
      if (!d) return;
      var dot = document.querySelector('[data-bell-dot]');
      if (dot) { dot.textContent = d.count > 99 ? '99+' : d.count; if (d.count > 0) dot.removeAttribute('hidden'); else dot.setAttribute('hidden', ''); }
      document.querySelectorAll('[data-chat-badge]').forEach(function (b) { var n = d.chat !== undefined ? d.chat : d.count; b.textContent = n; if (n > 0) b.removeAttribute('hidden'); else b.setAttribute('hidden', ''); });
      document.title = (d.count + (d.chat || 0) > 0 ? '(' + (d.count + (d.chat || 0)) + ') ' : '') + baseTitle;
      if (d.items && d.items.length) {
        var loud = d.items.filter(function (it) { return !it.quiet; });
        if (loud.length) { beep(false, loud[0].sound); if (loud[0].say) setTimeout(function () { speak(loud[0].say); }, 900); }
        d.items.forEach(function (it) { if (!it.quiet) toast(it.text, it.link, it.label, it.icon, it.say || it.text); if (it.id > since) since = it.id; });
        if (window.rhLiveRefresh) window.rhLiveRefresh();
      }
    }).catch(function () {});
  }
  if (pollUrl) { setInterval(poll, 12000); document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); }); }

  // live dashboard tiles
  var live = document.querySelector('[data-live-src]');
  function animateTo(el, from, to, text) {
    var start = null;
    function st(ts) { if (!start) start = ts; var p = Math.min(1, (ts - start) / 600), v = from + (to - from) * (1 - Math.pow(1 - p, 3)); el.textContent = p < 1 ? Math.round(v).toLocaleString() : text; if (p < 1) requestAnimationFrame(st); }
    if (reduce || isNaN(from) || isNaN(to)) el.textContent = text; else requestAnimationFrame(st);
  }
  window.rhLiveRefresh = function () {
    if (!live || document.hidden) return;
    fetch(live.getAttribute('data-live-src'), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
      if (!d) return;
      Object.keys(d.tiles).forEach(function (k) {
        var tile = live.querySelector('[data-k="' + k + '"]'); if (!tile) return;
        var v = tile.querySelector('.stat-v'); if (v.textContent === d.tiles[k].v) return;
        var old = parseFloat((v.textContent || '').replace(/[^0-9.\-]/g, ''));
        if (d.tiles[k].n !== null && /^[0-9,]+$/.test(d.tiles[k].v)) animateTo(v, old, d.tiles[k].n, d.tiles[k].v); else v.textContent = d.tiles[k].v;
        tile.classList.remove('bump'); void tile.offsetWidth; tile.classList.add('bump');
      });
      var at = document.querySelector('[data-live-at]'); if (at) at.textContent = d.at;
    }).catch(function () {});
  };
  if (live) setInterval(window.rhLiveRefresh, 30000);

  // auto-hide success alerts
  setTimeout(function () { document.querySelectorAll('.alert.ok[role=status]').forEach(function (a) { a.style.transition = 'opacity .5s'; a.style.opacity = '0'; setTimeout(function () { a.remove(); }, 500); }); }, 5000);
})();
