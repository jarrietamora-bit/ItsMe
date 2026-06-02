<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $fields = ['company_name','company_slogan','company_address','company_phone','company_website','company_color','default_language','timezone','allow_registration','require_email_verify','auto_close_days','tickets_per_page'];
    foreach ($fields as $key) {
        setting_set($key, trim($_POST[$key] ?? ''));
    }

    // Handle logo upload
    if (!empty($_FILES['company_logo']['name'])) {
        $old = setting('company_logo');
        $fname = upload_file($_FILES['company_logo'],'logos',['jpg','jpeg','png','gif','svg']);
        if ($fname) {
            setting_set('company_logo', $fname);
            if ($old && file_exists(ROOT.'/uploads/logos/'.$old)) @unlink(ROOT.'/uploads/logos/'.$old);
            flash('success', t('logo_updated'));
        }
    }

    // Remove logo
    if (!empty($_POST['remove_logo'])) {
        $old = setting('company_logo');
        if ($old && file_exists(ROOT.'/uploads/logos/'.$old)) @unlink(ROOT.'/uploads/logos/'.$old);
        setting_set('company_logo','');
    }

    // Handle favicon upload
    if (!empty($_FILES['favicon']['name'])) {
        $fname = upload_file($_FILES['favicon'],'logos',['ico','png','gif']);
        if ($fname) setting_set('favicon', $fname);
    }

    log_activity('update_settings','settings',0,'general');
    flash('success', t('settings_saved'));
    redirect(base_url('settings/general'));
}

$page_title = t('general_settings');
include ROOT . '/templates/header.php';
$logo = setting('company_logo');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><?= t('general_settings') ?></h4>
</div>

<form method="post" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <div class="row g-4">

    <!-- Company Branding -->
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><strong><i class="bi bi-building me-2"></i><?= t('company_name') ?></strong></div>
        <div class="card-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><?= t('company_name') ?> *</label>
              <input type="text" name="company_name" class="form-control" value="<?= h(setting('company_name')) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('company_slogan') ?></label>
              <input type="text" name="company_slogan" class="form-control" value="<?= h(setting('company_slogan')) ?>">
            </div>
            <div class="col-md-8">
              <label class="form-label"><?= t('company_address') ?></label>
              <input type="text" name="company_address" class="form-control" value="<?= h(setting('company_address')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('company_phone') ?></label>
              <input type="text" name="company_phone" class="form-control" value="<?= h(setting('company_phone')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('company_website') ?></label>
              <input type="url" name="company_website" class="form-control" value="<?= h(setting('company_website')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('company_color') ?></label>
              <div class="input-group">
                <input type="color" name="company_color" class="form-control form-control-color" value="<?= h(setting('company_color','#0d6efd')) ?>" id="colorPicker">
                <input type="text" class="form-control" id="colorHex" value="<?= h(setting('company_color','#0d6efd')) ?>" placeholder="#0d6efd">
              </div>
              <small class="text-muted"><?= t('color_hint') ?></small>
            </div>
          </div>
        </div>
      </div>

      <!-- System settings -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><strong><i class="bi bi-sliders me-2"></i><?= t('system_section') ?></strong></div>
        <div class="card-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><?= t('default_language') ?></label>
              <select name="default_language" class="form-select">
                <option value="es" <?= setting('default_language')==='es'?'selected':'' ?>><?= t('lang_es') ?></option>
                <option value="en" <?= setting('default_language')==='en'?'selected':'' ?>><?= t('lang_en') ?></option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('timezone') ?></label>
              <select name="timezone" class="form-select">
                <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
                  <option value="<?= $tz ?>" <?= setting('timezone')===$tz?'selected':'' ?>><?= $tz ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('auto_close_days') ?></label>
              <input type="number" name="auto_close_days" class="form-control" value="<?= h(setting('auto_close_days','7')) ?>" min="1">
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('per_page') ?></label>
              <select name="tickets_per_page" class="form-select">
                <?php foreach ([10,25,50,100] as $n): ?>
                  <option value="<?= $n ?>" <?= setting('tickets_per_page')==$n?'selected':'' ?>><?= $n ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="allow_registration" value="1" id="allowReg" <?= setting('allow_registration')==='1'?'checked':'' ?>>
                <label class="form-check-label" for="allowReg"><?= t('allow_registration') ?> <?= t('public_reg_hint') ?></label>
              </div>
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="require_email_verify" value="1" id="emailVerify" <?= setting('require_email_verify')==='1'?'checked':'' ?>>
                <label class="form-check-label" for="emailVerify"><?= t('require_email_verify') ?></label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Logo & Favicon -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong><i class="bi bi-image me-2"></i><?= t('company_logo') ?></strong></div>
        <div class="card-body text-center">
          <?php if ($logo): ?>
            <img src="<?= base_url('uploads/logos/'.$logo) ?>" alt="Logo" class="img-fluid mb-3 rounded" style="max-height:100px">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="removeLogo">
              <label class="form-check-label text-danger small" for="removeLogo"><?= t('remove_logo') ?></label>
            </div>
          <?php else: ?>
            <div class="border rounded d-flex align-items-center justify-content-center mb-3" style="height:100px;background:#f8f9fa">
              <div class="text-muted"><i class="bi bi-image fs-1 d-block"></i><small><?= t('no_logo') ?></small></div>
            </div>
          <?php endif; ?>
          <input type="file" name="company_logo" class="form-control form-control-sm" accept="image/*">
          <small class="text-muted d-block mt-1">JPG, PNG, SVG — <?= t('logo_size_hint') ?></small>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong><?= t('favicon') ?></strong></div>
        <div class="card-body">
          <input type="file" name="favicon" class="form-control form-control-sm" accept=".ico,.png,.gif">
          <small class="text-muted"><?= t('favicon_hint') ?></small>
        </div>
      </div>

      <!-- Preview -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong><?= t('color_preview') ?></strong></div>
        <div class="card-body p-3">
          <div id="colorPreview" class="rounded p-3 text-white text-center fw-bold" style="background:<?= h(setting('company_color','#0d6efd')) ?>">
            <?= h(setting('company_name','BT-Support')) ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-2">
    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i><?= t('save') ?></button>
  </div>
</form>

<script>
const cp = document.getElementById('colorPicker');
const ch = document.getElementById('colorHex');
const prev = document.getElementById('colorPreview');
cp.addEventListener('input', () => { ch.value = cp.value; prev.style.background = cp.value; });
ch.addEventListener('input', () => { if (/^#[0-9A-Fa-f]{6}$/.test(ch.value)) { cp.value = ch.value; prev.style.background = ch.value; } });
// Sync hidden color field
cp.addEventListener('change', () => { cp.name = 'company_color'; });
</script>
<?php include ROOT . '/templates/footer.php'; ?>
