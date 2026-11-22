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

  // <select data-autosubmit> submits its form on change.
  document.querySelectorAll('select[data-autosubmit]').forEach(function (sel) {
    sel.addEventListener('change', function () { sel.form.submit(); });
  });

  // Checkbox / date that re-submits its GET form (admission fee preview, attendance date).
  document.querySelectorAll('input[data-autosubmit-check], input[data-autosubmit-date]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });

  // Attendance: "all present" button.
  document.querySelectorAll('[data-mark-all]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var mark = btn.getAttribute('data-mark-all');
      document.querySelectorAll('input[data-mark="' + mark + '"]').forEach(function (r) { r.checked = true; });
    });
  });

  // District -> upazila dependent select: <select data-upazila-target="upazila_id">
  var base = (document.querySelector('a.navbar-brand') || { getAttribute: function () { return '/'; } }).getAttribute('href').replace(/\/$/, '');
  document.querySelectorAll('select[data-upazila-target]').forEach(function (district) {
    var target = document.getElementById(district.getAttribute('data-upazila-target'));
    if (!target) { return; }
    var placeholder = target.options.length ? target.options[0].text : '';
    var load = function (selected) {
      target.innerHTML = '';
      target.appendChild(new Option(placeholder, ''));
      if (!district.value) { return; }
      fetch(base + '/geo/upazila-options?district_id=' + encodeURIComponent(district.value), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : []; })
        .then(function (rows) {
          rows.forEach(function (row) {
            var opt = new Option(row.name, row.id);
            if (String(row.id) === String(selected)) { opt.selected = true; }
            target.appendChild(opt);
          });
        });
    };
    district.addEventListener('change', function () { load(''); });
    load(target.getAttribute('data-selected') || '');
  });

  // <select data-reload-param="center_id"> reloads the page with ?center_id=<value>
  document.querySelectorAll('select[data-reload-param]').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var url = new URL(window.location.href);
      url.searchParams.set(sel.getAttribute('data-reload-param'), sel.value);
      window.location.assign(url.toString());
    });
  });

  // Batch form: fill end date from course duration (months) when start date is set.
  var course = document.getElementById('course_id');
  var start = document.getElementById('start_date');
  var end = document.getElementById('end_date');
  if (course && start && end) {
    var fill = function () {
      var opt = course.options[course.selectedIndex];
      var months = opt ? parseFloat(opt.getAttribute('data-duration') || '0') : 0;
      if (!start.value || !months || end.dataset.touched === '1') { return; }
      var d = new Date(start.value + 'T00:00:00');
      var whole = Math.floor(months);
      d.setMonth(d.getMonth() + whole);
      d.setDate(d.getDate() + Math.round((months - whole) * 30) - 1);
      var pad = function (n) { return (n < 10 ? '0' : '') + n; };
      end.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    };
    end.addEventListener('input', function () { end.dataset.touched = '1'; });
    course.addEventListener('change', fill);
    start.addEventListener('change', fill);
  }
})();
