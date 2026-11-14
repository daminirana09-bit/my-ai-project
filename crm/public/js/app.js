// Small helpers only. No tracking, no outside requests.
document.addEventListener('click', function (e) {
  var side = document.querySelector('.sidebar');
  if (e.target.closest('[data-menu]')) { side && side.classList.toggle('open'); return; }
  if (side && side.classList.contains('open') && !e.target.closest('.sidebar')) side.classList.remove('open');
  var c = e.target.closest('[data-confirm]');
  if (c && !window.confirm(c.getAttribute('data-confirm'))) e.preventDefault();
});
document.addEventListener('keydown', function (e) {
  var side = document.querySelector('.sidebar');
  if (e.key === 'Escape' && side) side.classList.remove('open');
});
document.addEventListener('change', function (e) {
  if (e.target.matches('[data-autosubmit]')) e.target.form.submit();
});
// Quote lines: picking a product fills its rate and GST (only if those boxes are empty).
document.addEventListener('change', function (e) {
  var s = e.target;
  if (!s.matches('select[data-line]')) return;
  var o = s.options[s.selectedIndex], i = s.getAttribute('data-line');
  var r = document.getElementById('r' + i), g = document.getElementById('g' + i), q = document.getElementById('q' + i);
  if (o && o.dataset.rate && r && !r.value) r.value = o.dataset.rate;
  if (o && o.dataset.gst && g && !g.value) g.value = o.dataset.gst;
  if (q && !q.value) q.focus();
});
// Light / dark button: switch to the opposite of what is on screen now (even when following the device).
document.addEventListener('submit', function (e) {
  var f = e.target.closest('form[data-theme-toggle]');
  if (!f) return;
  var set = document.documentElement.getAttribute('data-theme');
  var dark = set ? set === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
  f.querySelector('input[name="theme"]').value = dark ? 'light' : 'dark';
});
// Copy buttons (links and ready messages)
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-copy]');
  if (!b) return;
  var text = b.getAttribute('data-copy'), done = function () { var t = b.textContent; b.textContent = '✓'; setTimeout(function () { b.textContent = t; }, 1200); };
  if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); return; }
  var ta = document.createElement('textarea'); ta.value = text; ta.setAttribute('readonly', ''); ta.className = 'sr'; document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); done(); } catch (x) {} document.body.removeChild(ta);
});
