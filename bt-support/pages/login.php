<?php
if (!defined('BTSUPPORT')) exit;

// Remember me auto-login
if (!is_logged_in() && isset($_COOKIE['remember_token'])) {
    $token_hash = hash('sha256', $_COOKIE['remember_token']);
    $st = db()->prepare("SELECT * FROM users WHERE remember_token = ? AND status = 'active'");
    $st->execute([$token_hash]);
    $ru = $st->fetch();
    if ($ru) {
        session_regenerate_id(true);
        $_SESSION['uid']  = $ru['id'];
        $_SESSION['role'] = $ru['role'];
        $_SESSION['lang'] = $ru['language'];
        $_SESSION['last_activity'] = time();
        db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$ru['id']]);
        redirect(base_url('dashboard'));
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $error = t('error'); }
    else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);
        if (login_user($email, $password, $remember)) {
            $u = current_user();
            load_lang();
            flash('success', t('login_success'));
            redirect(base_url('dashboard'));
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $st = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $st->execute([$email, $ip]);
            $error = (int)$st->fetchColumn() >= 5 ? t('account_blocked') : t('login_failed');
        }
    }
}

$company_name  = setting('company_name') ?: 'BT-Support';
$company_color = setting('company_color') ?: '#0d6efd';
$company_logo  = setting('company_logo');
$logo_url      = $company_logo ? base_url('uploads/logos/' . $company_logo) : '';
$allow_reg     = setting('allow_registration') === '1';
$lang          = current_lang();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= t('login') ?> — <?= h($company_name) ?></title>
<?php if ($favicon = setting('favicon')): ?>
<link rel="icon" type="image/x-icon" href="<?= base_url('uploads/logos/' . $favicon) ?>">
<?php endif; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
<style>
  :root{--brand:<?= h($company_color) ?>}
  body{background:linear-gradient(135deg,var(--brand) 0%,color-mix(in srgb,var(--brand) 70%,#000) 100%);min-height:100vh;display:flex;align-items:center;justify-content:center}
  .login-card{border-radius:16px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.25);max-width:420px;width:100%}
  .login-header{background:rgba(255,255,255,.1);backdrop-filter:blur(10px);padding:32px;text-align:center;color:#fff;border-bottom:1px solid rgba(255,255,255,.15)}
  .login-body{background:#fff;padding:32px}
  .btn-brand{background:var(--brand);border-color:var(--brand);color:#fff}
  .btn-brand:hover{filter:brightness(.9);color:#fff}
</style>
</head>
<body>
<div class="login-card">
  <div class="login-header">
    <?php if ($logo_url): ?>
      <img src="<?= h($logo_url) ?>" alt="<?= h($company_name) ?>" style="max-height:56px;margin-bottom:12px">
      <h5 class="mb-0"><?= h($company_name) ?></h5>
    <?php else: ?>
      <i class="bi bi-headset" style="font-size:3rem;opacity:.9"></i>
      <h3 class="mt-2 mb-0 fw-bold"><?= h($company_name) ?></h3>
    <?php endif; ?>
    <small class="opacity-75"><?= setting('company_slogan') ?: t('footer_text') ?></small>

    <!-- Language toggle -->
    <div class="mt-3">
      <a href="?lang=es" class="btn btn-sm <?= $lang==='es'?'btn-white text-dark':'btn-outline-light' ?> btn-sm py-0 px-2">ES</a>
      <a href="?lang=en" class="btn btn-sm <?= $lang==='en'?'btn-white text-dark':'btn-outline-light' ?> btn-sm py-0 px-2 ms-1">EN</a>
    </div>
  </div>
  <div class="login-body">
    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?= h($error) ?></div>
    <?php endif; ?>
    <?php foreach (get_flash() as $f): ?>
      <div class="alert alert-<?= h($f['type']) ?> py-2"><?= h($f['msg']) ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label fw-semibold"><?= t('email') ?></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" class="form-control" placeholder="usuario@empresa.com"
                 value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold"><?= t('password') ?></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" id="pwdField" class="form-control" required>
          <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()">
            <i class="bi bi-eye" id="pwdIcon"></i>
          </button>
        </div>
      </div>
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="remember" id="remember">
          <label class="form-check-label small" for="remember"><?= t('remember_me') ?></label>
        </div>
        <a href="<?= base_url('forgot-password') ?>" class="small text-decoration-none"><?= t('forgot_password') ?></a>
      </div>
      <button type="submit" class="btn btn-brand w-100 fw-semibold py-2">
        <i class="bi bi-box-arrow-in-right me-2"></i><?= t('login') ?>
      </button>
    </form>

    <?php if ($allow_reg): ?>
    <hr class="my-4">
    <div class="text-center small">
      ¿No tiene cuenta?
      <a href="<?= base_url('register') ?>" class="text-decoration-none fw-semibold"><?= t('register') ?></a>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
function togglePwd(){
  const f=document.getElementById('pwdField'),i=document.getElementById('pwdIcon');
  f.type=f.type==='password'?'text':'password';
  i.className=f.type==='password'?'bi bi-eye':'bi bi-eye-slash';
}
</script>
</body>
</html>
