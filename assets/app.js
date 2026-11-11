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
    var txt = el.textContent, dec = (String(target).split('.')[1] || '').length, start = null;
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min(1, (ts - start) / 700), v = target * (1 - Math.pow(1 - p, 3));
      el.textContent = p < 1 ? v.toLocaleString(undefined, { maximumFractionDigits: dec ? 2 : 0 }) : txt;
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  });

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
  function beep(force) {
    if (!force && !soundOn()) return;
    var c = audio(); if (!c) return;
    try {
      if (c.state === 'suspended') c.resume();
      var t = c.currentTime;
      [[880, 0], [1175, 0.17], [880, 0.34], [1175, 0.51]].forEach(function (n) {
        var o = c.createOscillator(), g = c.createGain();
        o.type = 'sine'; o.frequency.value = n[0];
        g.gain.setValueAtTime(0.0001, t + n[1]);
        g.gain.exponentialRampToValueAtTime(0.28, t + n[1] + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, t + n[1] + 0.16);
        o.connect(g); g.connect(c.destination); o.start(t + n[1]); o.stop(t + n[1] + 0.18);
      });
    } catch (e) {}
  }
  function toast(text, link) {
    var box = document.getElementById('toasts'); if (!box) return;
    var a = document.createElement(link ? 'a' : 'div'); a.className = 'toast'; if (link) a.href = link;
    a.innerHTML = '<div><b>New</b><span></span></div>'; a.querySelector('span').textContent = text;
    box.appendChild(a); setTimeout(function () { if (a.parentNode) a.parentNode.removeChild(a); }, 12000);
  }
  syncSound();
  document.querySelectorAll('[data-sound-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      try { localStorage.setItem('rh-sound', soundOn() ? 'off' : 'on'); } catch (e) {}
      syncSound(); if (soundOn()) beep(true);
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
        beep(false);
        d.items.forEach(function (it) { toast(it.text, it.link); if (it.id > since) since = it.id; });
      }
    }).catch(function () {});
  }
  if (pollUrl) { setInterval(poll, 12000); document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); }); }

  // auto-hide success alerts
  setTimeout(function () { document.querySelectorAll('.alert.ok[role=status]').forEach(function (a) { a.style.transition = 'opacity .5s'; a.style.opacity = '0'; setTimeout(function () { a.remove(); }, 500); }); }, 5000);
})();
