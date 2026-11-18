/* Yem'kaf Outils : confirmations avant suppression, menu utilisateur. */
(function () {
  'use strict';
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-confirm]');
    if (b && !window.confirm(b.getAttribute('data-confirm'))) e.preventDefault();
  });
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.menu-user[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });
})();
