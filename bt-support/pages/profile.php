<?php
if (!defined('BTSUPPORT')) exit;
require_login();
$user   = current_user();
$uid    = $user['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $tab = $_POST['tab'] ?? 'info';
    if ($tab === 'info') {
        $name  = trim($_POST['name']  ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $lang  = $_POST['language']   ?? $user['language'];
        if (!$name) $errors[] = t('required') . ' (nombre)';
        if (empty($errors)) {
            db()->prepare("UPDATE users SET name=?,phone=?,language=?,updated_at=NOW() WHERE id=?")->execute([$name,$phone,$lang,$uid]);
            if (!empty($_FILES['avatar']['name'])) {
                $fname = upload_file($_FILES['avatar'],'avatars',['jpg','jpeg','png','gif']);
                if ($fname) {
                    if ($user['avatar'] && file_exists(ROOT.'/uploads/avatars/'.$user['avatar'])) @unlink(ROOT.'/uploads/avatars/'.$user['avatar']);
                    db()->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$fname,$uid]);
                }
            }
            $_SESSION['lang'] = $lang;
            flash('success', t('user_updated'));
            redirect(base_url('profile'));
        }
    }
    if ($tab === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $new2    = $_POST['new_password2']    ?? '';
        if (!password_verify($current, $user['password'])) $errors[] = 'Contraseña actual incorrecta.';
        if (strlen($new) < 8) $errors[] = 'Nueva contraseña mínimo 8 caracteres.';
        if ($new !== $new2) $errors[] = t('passwords_not_match');
        if (empty($errors)) {
            db()->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT),$uid]);
            flash('success', t('change_password') . ' ✓');
            redirect(base_url('profile'));
        }
    }
}
$user      = current_user();
$depts     = get_user_departments($uid);
$page_title = t('profile');
include ROOT . '/templates/header.php';
?>
<h4 class="fw-bold mb-4"><?= t('profile') ?></h4>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body p-4">
        <?php if ($user['avatar']): ?>
          <img src="<?= base_url('uploads/avatars/'.$user['avatar']) ?>" class="rounded-circle mb-3" width="80" height="80" style="object-fit:cover">
        <?php else: ?>
          <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold mx-auto mb-3 fs-2" style="width:80px;height:80px;background:var(--brand-color,#0d6efd)"><?= strtoupper(substr($user['name'],0,1)) ?></div>
        <?php endif; ?>
        <h5 class="mb-0"><?= h($user['name']) ?></h5>
        <div class="mt-1"><?= role_badge($user['role']) ?></div>
        <div class="text-muted small mt-2"><?= h($user['email']) ?></div>
        <?php if ($user['phone']): ?><div class="text-muted small"><?= h($user['phone']) ?></div><?php endif; ?>
        <hr>
        <div class="text-muted small"><?= t('member_since') ?>: <?= format_datetime($user['created_at']) ?></div>
        <?php if ($user['last_login']): ?><div class="text-muted small"><?= t('last_login') ?>: <?= format_datetime($user['last_login']) ?></div><?php endif; ?>
        <?php if ($depts): ?>
        <hr>
        <div class="small">
          <strong><?= t('departments') ?>:</strong>
          <?php foreach ($depts as $d): ?>
            <span class="badge me-1" style="background:<?= h($d['color']) ?>"><?= h($d['name']) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e) echo "<li>".h($e)."</li>"; ?></ul></div><?php endif; ?>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs">
          <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#infoTab"><?= t('profile') ?></a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pwdTab"><?= t('change_password') ?></a></li>
        </ul>
      </div>
      <div class="card-body p-4">
        <div class="tab-content">
          <div class="tab-pane fade show active" id="infoTab">
            <form method="post" enctype="multipart/form-data">
              <?= csrf_field() ?><input type="hidden" name="tab" value="info">
              <div class="mb-3"><label class="form-label"><?= t('name') ?> *</label>
                <input type="text" name="name" class="form-control" value="<?= h($user['name']) ?>" required></div>
              <div class="mb-3"><label class="form-label"><?= t('phone') ?></label>
                <input type="text" name="phone" class="form-control" value="<?= h($user['phone']??'') ?>"></div>
              <div class="mb-3"><label class="form-label"><?= t('language') ?></label>
                <select name="language" class="form-select">
                  <option value="es" <?= $user['language']==='es'?'selected':'' ?>><?= t('lang_es') ?></option>
                  <option value="en" <?= $user['language']==='en'?'selected':'' ?>><?= t('lang_en') ?></option>
                </select></div>
              <div class="mb-3"><label class="form-label"><?= t('avatar') ?></label>
                <input type="file" name="avatar" class="form-control" accept="image/*"></div>
              <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
            </form>
          </div>
          <div class="tab-pane fade" id="pwdTab">
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="tab" value="password">
              <div class="mb-3"><label class="form-label"><?= t('current_password') ?></label>
                <input type="password" name="current_password" class="form-control" required></div>
              <div class="mb-3"><label class="form-label"><?= t('new_password') ?></label>
                <input type="password" name="new_password" class="form-control" minlength="8" required></div>
              <div class="mb-3"><label class="form-label"><?= t('confirm_password') ?></label>
                <input type="password" name="new_password2" class="form-control" required></div>
              <button type="submit" class="btn btn-primary"><?= t('change_password') ?></button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include ROOT . '/templates/footer.php'; ?>
