<?php $v = fn($k, $d = '') => e(old($k, $s[$k] ?? $d)); $isEdit = (bool)$s; ?>
<div class="page-head"><h1><?= e($title) ?></h1><?php if ($isEdit): ?><span class="badge text-bg-primary fs-6"><?= e($s['student_code']) ?></span><?php endif; ?></div>
<form method="post" action="<?= url($isEdit ? 'students/update' : 'students/store') ?>" enctype="multipart/form-data" id="studentForm">
  <?= csrf_field() ?><?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><?php endif; ?>
  <div class="row g-3">
    <div class="col-xl-9">
      <div class="card mb-3"><div class="card-header">Personal information</div><div class="card-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label req">Student name (English)</label><input name="name" class="form-control" value="<?= $v('name') ?>" required maxlength="150"></div>
        <div class="col-md-6"><label class="form-label">Student name (বাংলা)</label><input name="name_bn" class="form-control" value="<?= $v('name_bn') ?>" maxlength="150"></div>
        <div class="col-md-6"><label class="form-label req">Father's name</label><input name="father_name" class="form-control" value="<?= $v('father_name') ?>" required></div>
        <div class="col-md-6"><label class="form-label">Mother's name</label><input name="mother_name" class="form-control" value="<?= $v('mother_name') ?>"></div>
        <div class="col-md-4"><label class="form-label req">Date of birth</label><input type="date" name="date_of_birth" class="form-control" value="<?= $v('date_of_birth') ?>" required max="<?= date('Y-m-d') ?>"></div>
        <div class="col-md-4"><label class="form-label req">Gender</label><select name="gender_id" class="form-select" required><?= options($genders, old('gender_id', $s['gender_id'] ?? '')) ?></select></div>
        <div class="col-md-4"><label class="form-label">Occupation</label><input name="occupation" class="form-control" value="<?= $v('occupation') ?>" list="occList">
          <datalist id="occList"><option>Student</option><option>Unemployed</option><option>Housewife</option><option>Farmer</option><option>Day labourer</option><option>Small business</option><option>Service</option></datalist></div>
        <div class="col-md-6"><label class="form-label">National ID (10/13/17 digits)</label><input name="nid" class="form-control dupcheck" value="<?= $v('nid') ?>" inputmode="numeric"></div>
        <div class="col-md-6"><label class="form-label">Birth registration no. (17 digits)</label><input name="birth_reg_no" class="form-control dupcheck" value="<?= $v('birth_reg_no') ?>" inputmode="numeric"><div class="form-text">NID or birth registration is required.</div></div>
        <div class="col-12" id="dupBox" style="display:none"></div>
      </div></div></div>

      <div class="card mb-3"><div class="card-header">Contact & address</div><div class="card-body"><div class="row g-3">
        <div class="col-md-4"><label class="form-label req">Mobile</label><input name="mobile" class="form-control dupcheck" value="<?= $v('mobile') ?>" required placeholder="01XXXXXXXXX"></div>
        <div class="col-md-4"><label class="form-label">Alternative mobile</label><input name="alt_mobile" class="form-control" value="<?= $v('alt_mobile') ?>"></div>
        <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= $v('email') ?>"></div>
        <div class="col-md-6"><label class="form-label">Present address</label><textarea name="present_address" class="form-control" rows="2"><?= $v('present_address') ?></textarea></div>
        <div class="col-md-6"><label class="form-label">Permanent address <small class="ms-2"><label><input type="checkbox" name="same_address" value="1" id="sameAddr"> same as present</label></small></label><textarea name="permanent_address" id="permAddr" class="form-control" rows="2"><?= $v('permanent_address') ?></textarea></div>
        <div class="col-md-6"><label class="form-label">District</label><select name="district_id" id="district" class="form-select s2" data-child="#upazila" data-source="ajax/upazilas"><?= options($districts, old('district_id', $s['district_id'] ?? '')) ?></select></div>
        <div class="col-md-6"><label class="form-label">Upazila</label><select name="upazila_id" id="upazila" class="form-select s2" data-selected="<?= $v('upazila_id') ?>"><option value="">-- Select --</option></select></div>
        <div class="col-md-6"><label class="form-label">Emergency contact name</label><input name="emergency_contact_name" class="form-control" value="<?= $v('emergency_contact_name') ?>"></div>
        <div class="col-md-6"><label class="form-label">Emergency contact mobile</label><input name="emergency_contact_mobile" class="form-control" value="<?= $v('emergency_contact_mobile') ?>"></div>
      </div></div></div>

      <div class="card mb-3"><div class="card-header">Education & registration</div><div class="card-body"><div class="row g-3">
        <div class="col-md-4"><label class="form-label">Educational qualification</label><select name="education_level_id" class="form-select"><?= options($education, old('education_level_id', $s['education_level_id'] ?? '')) ?></select></div>
        <div class="col-md-8"><label class="form-label">Qualification details (group, institute, year)</label><input name="qualification_details" class="form-control" value="<?= $v('qualification_details') ?>"></div>
        <div class="col-md-4"><label class="form-label req">Registration center</label><select name="center_id" class="form-select" required><?= options($centers, old('center_id', $s['center_id'] ?? \App\Core\Auth::defaultCenterId())) ?></select></div>
        <div class="col-md-4"><label class="form-label req">Registration date</label><input type="date" name="registration_date" class="form-control" value="<?= $v('registration_date', date('Y-m-d')) ?>" required max="<?= date('Y-m-d') ?>"></div>
        <div class="col-md-4"><label class="form-label">Student status</label><select name="status" class="form-select"><?= kv_options($statuses, old('status', $s['status'] ?? 'active'), '') ?></select></div>
        <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2"><?= $v('remarks') ?></textarea></div>
      </div></div></div>
    </div>
    <div class="col-xl-3">
      <div class="card mb-3"><div class="card-header">Photograph</div><div class="card-body text-center">
        <img id="photoPrev" class="profile-photo mb-2<?= empty($s['photo']) ? ' d-none' : '' ?>" src="<?= e(file_url($s['photo'] ?? null)) ?>" alt="">
        <?php if (empty($s['photo'])): ?><div class="photo-ph mx-auto mb-2" id="photoPh"><i class="bi bi-person"></i></div><?php endif; ?>
        <input type="file" name="photo" accept="image/jpeg,image/png" class="form-control form-control-sm" data-preview="#photoPrev" onchange="document.getElementById('photoPh')?.remove()">
        <div class="form-text">JPG/PNG, max 2 MB, passport size.</div>
      </div></div>
      <div class="card"><div class="card-body d-grid gap-2">
        <div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_duplicate" value="1" id="cdup"><label class="form-check-label small" for="cdup">This is a different person (mobile number is shared)</label></div>
        <button class="btn btn-primary"><i class="bi bi-check2"></i> <?= e(t('Save')) ?></button>
        <?php if (!$isEdit && can('enrollments.create')): ?><button class="btn btn-success" name="then" value="enroll"><i class="bi bi-journal-plus"></i> Save & enroll in course</button><?php endif; ?>
        <a href="<?= $isEdit ? url('students/view', ['id' => $s['id']]) : url('students/index') ?>" class="btn btn-light"><?= e(t('Cancel')) ?></a>
      </div></div>
    </div>
  </div>
</form>
<?php ob_start(); ?>
<script>
$('#sameAddr').on('change', function(){ $('#permAddr').prop('readonly', this.checked); if (this.checked) $('#permAddr').val($('[name=present_address]').val()); });
let dupT;
$('.dupcheck').on('input blur', function () {
  clearTimeout(dupT);
  dupT = setTimeout(function () {
    const d = {r: 'students/checkdup', id: <?= (int)($s['id'] ?? 0) ?>, nid: $('[name=nid]').val(), birth_reg_no: $('[name=birth_reg_no]').val(), mobile: $('[name=mobile]').val()};
    if (!d.nid && !d.birth_reg_no && (d.mobile || '').length < 11) { $('#dupBox').hide(); return; }
    $.getJSON(APP.base + 'index.php', d).done(function (res) {
      if (!res.duplicates.length) { $('#dupBox').hide(); return; }
      const hard = res.duplicates.some(x => x.same_nid || x.same_brn);
      let h = '<div class="alert alert-' + (hard ? 'danger' : 'warning') + ' mb-0 py-2"><b>' + (hard ? 'This person is already registered.' : 'Possible duplicate (same mobile):') + '</b><ul class="mb-0 small">';
      res.duplicates.forEach(x => {
        const label = $('<div>').text(x.student_code + ' – ' + x.name + ' (father: ' + (x.father_name || '-') + ', ' + x.center + ')').html();
        h += '<li>' + (x.url ? '<a target="_blank" href="' + x.url + '">' + label + '</a>' : label) + '</li>';
      });
      h += '</ul>' + (hard ? '<div class="small mt-1">Use the existing lifetime Student ID and enroll them in the new course.</div>' : '') + '</div>';
      $('#dupBox').html(h).show();
    });
  }, 400);
});
</script>
<?php \App\Core\View::share('scripts', ob_get_clean()); ?>
