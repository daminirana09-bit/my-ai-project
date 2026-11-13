// Small helpers only. No tracking, no outside requests.
document.addEventListener('click', function (e) {
  var t = e.target.closest('[data-menu]');
  if (t) { document.querySelector('.side').classList.toggle('open'); return; }
  if (e.target.classList && e.target.classList.contains('side')) e.target.classList.remove('open');
  var c = e.target.closest('[data-confirm]');
  if (c && !window.confirm(c.getAttribute('data-confirm'))) e.preventDefault();
});
document.addEventListener('change', function (e) {
  if (e.target.matches('[data-autosubmit]')) e.target.form.submit();
});
