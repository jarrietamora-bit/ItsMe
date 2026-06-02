<?php
if (!defined('BTSUPPORT')) exit;
$token = $_GET['token'] ?? '';
$err = $ok = '';
// Validate token
$st = db()->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
$st->execute([$token]);
$row = $st->fetch();
if (!$row) { $err = t('reset_invalid'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $row && csrf_verify()) {
    $pass  = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';
    if (strlen($pass) < 8) { $err = t('password_min_8'); }
    elseif ($pass !== $pass2) { $err = t('passwords_not_match'); }
    else {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        db()->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hash, $row['email']]);
        db()->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$row['email']]);
        flash('success', t('reset_success'));
        redirect(base_url('login'));
    }
}
$company_color = setting('company_color') ?: '#0d6efd';
?>
<!DOCTYPE html><html lang="<?= current_lang() ?>"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= t('reset_password') ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
  :root{--brand:<?= h($company_color) ?>}
  body{background:linear-gradient(135deg,var(--brand),color-mix(in srgb,var(--brand) 70%,#000));min-height:100vh;display:flex;align-items:center;justify-content:center}
  .card{border-radius:14px;max-width:420px;width:100%;box-shadow:0 10px 32px rgba(0,0,0,.2)}
  .btn-brand{background:var(--brand);border-color:var(--brand);color:#fff}
</style></head><body>
<div class="card border-0 overflow-hidden">
  <div class="p-4 text-white text-center" style="background:rgba(0,0,0,.2)">
    <i class="bi bi-shield-lock fs-1"></i>
    <h4 class="mt-2"><?= t('reset_password') ?></h4>
  </div>
  <div class="card-body p-4" style="background:#fff">
    <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
    <?php if (!$row): ?>
      <div class="text-center"><a href="<?= base_url('forgot-password') ?>"><?= t('forgot_password') ?></a></div>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label"><?= t('new_password') ?></label>
        <input type="password" name="password" class="form-control" minlength="8" required>
      </div>
      <div class="mb-4">
        <label class="form-label"><?= t('confirm_password') ?></label>
        <input type="password" name="password2" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-brand w-100"><?= t('save') ?></button>
    </form>
    <?php endif; ?>
  </div>
</div>
</body></html>
