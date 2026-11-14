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
