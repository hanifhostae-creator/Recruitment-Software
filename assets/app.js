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

  // auto-hide success alerts
  setTimeout(function () { document.querySelectorAll('.alert.ok[role=status]').forEach(function (a) { a.style.transition = 'opacity .5s'; a.style.opacity = '0'; setTimeout(function () { a.remove(); }, 500); }); }, 5000);
})();
