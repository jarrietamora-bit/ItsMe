<?php
if (!defined('BTSUPPORT')) exit;
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $email = trim($_POST['email'] ?? '');
    $st = db()->prepare("SELECT id, name, language FROM users WHERE email = ? AND status = 'active'");
    $st->execute([$email]);
    $user = $st->fetch();
    if ($user) {
        $token = bin2hex(random_bytes(32));
        db()->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        db()->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))")
            ->execute([$email, $token]);
        $reset_url = base_url('reset-password') . '?token=' . $token;
        mailer()->sendPasswordReset($email, $user['name'], $reset_url, $user['language']);
    }
    // Always show success (prevent email enumeration)
    $msg = t('reset_sent');
}
$company_name  = setting('company_name') ?: 'BT-Support';
$company_color = setting('company_color') ?: '#0d6efd';
?>
<!DOCTYPE html><html lang="<?= current_lang() ?>"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= t('forgot_password') ?> — <?= h($company_name) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
  :root{--brand:<?= h($company_color) ?>}
  body{background:linear-gradient(135deg,var(--brand),color-mix(in srgb,var(--brand) 70%,#000));min-height:100vh;display:flex;align-items:center;justify-content:center}
  .card{border-radius:14px;max-width:420px;width:100%;box-shadow:0 10px 32px rgba(0,0,0,.2)}
  .btn-brand{background:var(--brand);border-color:var(--brand);color:#fff}
</style></head><body>
<div class="card border-0 overflow-hidden">
  <div class="p-4 text-white text-center" style="background:rgba(0,0,0,.2)">
    <i class="bi bi-key fs-1"></i>
    <h4 class="mt-2"><?= t('forgot_password') ?></h4>
  </div>
  <div class="card-body p-4" style="background:#fff">
    <?php if ($msg): ?>
      <div class="alert alert-success"><?= h($msg) ?></div>
    <?php else: ?>
    <p class="text-muted small">Ingrese su correo y le enviaremos un enlace para restablecer su contraseña.</p>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label"><?= t('email') ?></label>
        <input type="email" name="email" class="form-control" required autofocus>
      </div>
      <button type="submit" class="btn btn-brand w-100"><?= t('submit') ?></button>
    </form>
    <?php endif; ?>
    <div class="text-center mt-3 small">
      <a href="<?= base_url('login') ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= t('login') ?></a>
    </div>
  </div>
</div>
</body></html>
