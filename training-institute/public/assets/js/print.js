/* Print page helper (no inline scripts: strict CSP). */
(function () {
  'use strict';
  var btn = document.querySelector('[data-print]');
  if (btn) { btn.addEventListener('click', function () { window.print(); }); }
})();
