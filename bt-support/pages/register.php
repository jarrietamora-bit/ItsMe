<?php
if (!defined('BTSUPPORT')) exit;

if (setting('allow_registration') !== '1') {
    redirect(base_url('login'));
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = t('error'); }
    else {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password']  ?? '';
        $pass2 = $_POST['password2'] ?? '';
        if (!$name)  $errors[] = t('required') . ' (' . t('name') . ')';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
        if (strlen($pass) < 8) $errors[] = 'Contraseña mínimo 8 caracteres.';
        if ($pass !== $pass2) $errors[] = t('passwords_not_match');
        if (empty($errors)) {
            $st = db()->prepare("SELECT id FROM users WHERE email = ?");
            $st->execute([$email]);
            if ($st->fetch()) { $errors[] = 'Este email ya está registrado.'; }
        }
        if (empty($errors)) {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            db()->prepare("INSERT INTO users (name, email, password, role, language) VALUES (?,?,?,'client',?)")
               ->execute([$name, $email, $hash, current_lang()]);
            flash('success', '¡Cuenta creada! Inicia sesión.');
            redirect(base_url('login'));
        }
    }
}

$company_name  = setting('company_name') ?: 'BT-Support';
$company_color = setting('company_color') ?: '#0d6efd';
$logo_url      = ($l = setting('company_logo')) ? base_url('uploads/logos/'.$l) : '';
?>
<!DOCTYPE html><html lang="<?= current_lang() ?>"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= t('register') ?> — <?= h($company_name) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  :root{--brand:<?= h($company_color) ?>}
  body{background:linear-gradient(135deg,var(--brand),color-mix(in srgb,var(--brand) 70%,#000));min-height:100vh;display:flex;align-items:center;justify-content:center}
  .card{border-radius:16px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.25);max-width:460px;width:100%}
  .card-header{background:rgba(0,0,0,.15);color:#fff;text-align:center;padding:28px}
  .btn-brand{background:var(--brand);border-color:var(--brand);color:#fff}
</style></head><body>
<div class="card border-0">
  <div class="card-header">
    <?php if ($logo_url): ?><img src="<?= h($logo_url) ?>" style="max-height:48px;margin-bottom:10px"><br><?php endif; ?>
    <h4 class="mb-0"><?= h($company_name) ?></h4>
    <small class="opacity-75"><?= t('register') ?></small>
  </div>
  <div class="card-body p-4" style="background:#fff">
    <?php if ($errors): ?>
      <div class="alert alert-danger py-2"><ul class="mb-0"><?php foreach($errors as $e) echo "<li>".h($e)."</li>"; ?></ul></div>
    <?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label"><?= t('name') ?></label>
        <input type="text" name="name" class="form-control" value="<?= h($_POST['name']??'') ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label"><?= t('email') ?></label>
        <input type="email" name="email" class="form-control" value="<?= h($_POST['email']??'') ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label"><?= t('password') ?></label>
        <input type="password" name="password" class="form-control" minlength="8" required>
      </div>
      <div class="mb-4">
        <label class="form-label"><?= t('confirm_password') ?></label>
        <input type="password" name="password2" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-brand w-100"><?= t('register') ?></button>
    </form>
    <div class="text-center mt-3 small">
      <a href="<?= base_url('login') ?>" class="text-decoration-none"><?= t('login') ?></a>
    </div>
  </div>
</div>
</body></html>
