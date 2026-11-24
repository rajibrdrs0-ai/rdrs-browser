/* RTTI front-end helpers */
(function ($) {
  'use strict';
  const csrf = window.APP.csrf;
  $.ajaxSetup({headers: {'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest'}});

  window.loading = (on) => document.getElementById('loading').classList.toggle('show', !!on);

  // Select2 on any select.s2
  function initSelect2(ctx) {
    $('select.s2', ctx).each(function () {
      const $s = $(this);
      const opts = {width: '100%', allowClear: !$s.prop('required'), placeholder: $s.data('placeholder') || '-- Select --'};
      if ($s.closest('.modal').length) opts.dropdownParent = $s.closest('.modal');
      if ($s.data('ajax')) {
        opts.minimumInputLength = 1;
        opts.ajax = {url: $s.data('ajax'), dataType: 'json', delay: 250, data: p => ({q: p.term}), processResults: d => ({results: d.results || []})};
      }
      $s.select2(opts);
    });
  }
  initSelect2(document);
  window.initSelect2 = initSelect2;

  // Dependent dropdowns: <select data-child="#district" data-source="ajax/districts">
  $(document).on('change', 'select[data-child]', function () {
    const $child = $($(this).data('child'));
    const src = $(this).data('source');
    const val = $(this).val();
    $child.empty().append('<option value="">-- Select --</option>');
    $child.trigger('change');
    if (!val) return;
    $.getJSON(window.APP.base + 'index.php', {r: src, parent: val}).done(function (rows) {
      rows.forEach(r => $child.append(new Option(r.name, r.id)));
      if ($child.data('selected')) { $child.val(String($child.data('selected'))); $child.data('selected', ''); }
      $child.trigger('change');
    });
  });
  $('select[data-child]').each(function () { if ($(this).val()) $(this).trigger('change'); });

  // Confirmation for dangerous actions: <form data-confirm="..."> or <a data-confirm>
  $(document).on('submit', 'form[data-confirm]', function (e) {
    const form = this;
    if (form.dataset.confirmed) return true;
    e.preventDefault();
    const needReason = form.dataset.reason !== undefined;
    Swal.fire({
      title: form.dataset.confirm, icon: 'warning', showCancelButton: true,
      confirmButtonText: 'Yes, proceed', confirmButtonColor: '#c8102e',
      input: needReason ? 'text' : undefined, inputPlaceholder: 'Reason (required)',
      inputValidator: needReason ? (v) => (!v || v.trim().length < 3) && 'Please enter a reason' : undefined
    }).then(r => {
      if (!r.isConfirmed) return;
      if (needReason) {
        let inp = form.querySelector('input[name=reason]');
        if (!inp) { inp = document.createElement('input'); inp.type = 'hidden'; inp.name = 'reason'; form.appendChild(inp); }
        inp.value = r.value;
      }
      form.dataset.confirmed = '1';
      loading(true);
      form.submit();
    });
  });

  // Loading indicator on normal form submit
  $(document).on('submit', 'form:not([data-confirm]):not(.no-loading)', function () {
    if (this.method.toLowerCase() === 'post') { loading(true); setTimeout(() => loading(false), 15000); }
  });

  // Client-side DataTable for small reference tables
  $('table.dt').each(function () {
    $(this).DataTable({pageLength: 25, order: [], language: {search: '', searchPlaceholder: 'Search…'}});
  });

  // Global search suggestions
  const $gs = $('#globalSearch'), $box = $('#gsearchResults');
  let timer = null, xhr = null;
  $gs.on('input', function () {
    clearTimeout(timer);
    const q = this.value.trim();
    if (q.length < 2) { $box.removeClass('show').empty(); return; }
    timer = setTimeout(() => {
      if (xhr) xhr.abort();
      xhr = $.getJSON(window.APP.base + 'index.php', {r: 'search/suggest', q}).done(function (d) {
        $box.empty();
        if (!d.groups || !d.groups.length) { $box.html('<div class="p-3 text-muted small">No matches</div>').addClass('show'); return; }
        d.groups.forEach(g => {
          $box.append($('<div class="grp">').text(g.label));
          g.items.forEach(it => $box.append($('<a>').attr('href', it.url).append($('<div>').text(it.title)).append($('<small>').text(it.sub || ''))));
        });
        $box.append($('<a class="text-center small">').attr('href', window.APP.base + 'index.php?r=search/index&q=' + encodeURIComponent(q)).text('See all results →'));
        $box.addClass('show');
      });
    }, 250);
  });
  $(document).on('click', e => { if (!$(e.target).closest('.gsearch').length) $box.removeClass('show'); });
  $gs.on('keydown', function (e) {
    const $a = $box.find('a'); let i = $a.index($a.filter('.hl'));
    if (e.key === 'ArrowDown') { i = Math.min($a.length - 1, i + 1); } else if (e.key === 'ArrowUp') { i = Math.max(0, i - 1); }
    else if (e.key === 'Enter' && i >= 0) { e.preventDefault(); location.href = $a.eq(i).attr('href'); return; } else return;
    e.preventDefault(); $a.removeClass('hl').eq(i).addClass('hl');
  });

  // Money helpers
  window.num = v => { const n = parseFloat(String(v || '0').replace(/,/g, '')); return isNaN(n) ? 0 : n; };
  window.fmt = n => n.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

  // Image preview for file inputs with data-preview
  $(document).on('change', 'input[type=file][data-preview]', function () {
    const f = this.files[0]; const img = document.querySelector(this.dataset.preview);
    if (f && img && f.type.startsWith('image/')) { img.src = URL.createObjectURL(f); img.classList.remove('d-none'); }
  });
})(jQuery);
