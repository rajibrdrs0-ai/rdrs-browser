/* RTTI — small progressive enhancements (no inline scripts: strict CSP) */
(function () {
  'use strict';

  // Confirm dangerous actions: <form data-confirm="Are you sure?">
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });

  // User form: show center or district fields depending on the role's scope.
  var roleSelect = document.getElementById('role_id');
  if (roleSelect) {
    var update = function () {
      var option = roleSelect.options[roleSelect.selectedIndex];
      var scope = option ? option.getAttribute('data-scope') : '';
      document.querySelectorAll('[data-scope-show]').forEach(function (el) {
        var scopes = el.getAttribute('data-scope-show').split(',');
        var show = scopes.indexOf(scope) !== -1;
        el.classList.toggle('d-none', !show);
        el.querySelectorAll('select').forEach(function (s) { s.disabled = !show; });
      });
    };
    roleSelect.addEventListener('change', update);
    update();
  }

  // Role edit: "select all in module" toggles.
  document.querySelectorAll('[data-module-toggle]').forEach(function (box) {
    box.addEventListener('change', function () {
      var group = document.querySelectorAll('input[data-module="' + box.getAttribute('data-module-toggle') + '"]');
      group.forEach(function (input) { input.checked = box.checked; });
    });
  });
})();
