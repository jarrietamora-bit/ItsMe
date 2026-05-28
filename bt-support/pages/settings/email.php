<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin']);
$cfg_file = ROOT . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'test') {
        // Send test email
        $to = trim($_POST['test_email_to'] ?? current_user()['email']);
        try {
            $m = mailer();
            $ok = $m->send($to, 'Test BT-Support', '<h2>✓ Correo de prueba</h2><p>La configuración SMTP funciona correctamente.</p>', '');
            flash($ok ? 'success' : 'danger', $ok ? t('test_email_sent') : t('test_email_failed'));
        } catch(\Throwable $e) {
            flash('danger', t('test_email_failed') . ': ' . $e->getMessage());
        }
        redirect(base_url('settings/email'));
    }

    // Save SMTP config to config.php
    $cfg     = require $cfg_file;
    $cfg['smtp_host']      = trim($_POST['smtp_host']      ?? '');
    $cfg['smtp_port']      = (int)($_POST['smtp_port']     ?? 587);
    $cfg['smtp_user']      = trim($_POST['smtp_user']      ?? '');
    $cfg['smtp_from']      = trim($_POST['smtp_from']      ?? '');
    $cfg['smtp_from_name'] = trim($_POST['smtp_from_name'] ?? 'BT-Support');
    $cfg['smtp_secure']    = $_POST['smtp_secure']         ?? 'tls';
    $cfg['mail_method']    = $_POST['mail_method']         ?? 'php';
    if (!empty($_POST['smtp_pass'])) {
        $cfg['smtp_pass']  = $_POST['smtp_pass'];
    }

    $content = "<?php\nreturn " . var_export($cfg, true) . ";\n";
    file_put_contents($cfg_file, $content);

    flash('success', t('settings_saved'));
    redirect(base_url('settings/email'));
}

$cfg = require $cfg_file;
$page_title = t('email_settings');
include ROOT . '/templates/header.php';
?>
<h4 class="fw-bold mb-4"><?= t('email_settings') ?></h4>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white"><strong><i class="bi bi-envelope-gear me-2"></i>Configuración SMTP</strong></div>
      <div class="card-body p-4">
        <form method="post" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <div class="mb-3">
            <label class="form-label">Método de envío</label>
            <select name="mail_method" class="form-select" id="mailMethod" onchange="toggleSmtp()">
              <option value="php" <?= ($cfg['mail_method']??'php')==='php'?'selected':'' ?>>PHP mail() — Servidor de hosting</option>
              <option value="smtp" <?= ($cfg['mail_method']??'')==='smtp'?'selected':'' ?>>SMTP personalizado</option>
            </select>
          </div>
          <div id="smtpFields">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label"><?= t('smtp_host') ?></label>
                <input type="text" name="smtp_host" class="form-control" value="<?= h($cfg['smtp_host']??'') ?>" placeholder="smtp.gmail.com">
              </div>
              <div class="col-md-4">
                <label class="form-label"><?= t('smtp_port') ?></label>
                <input type="number" name="smtp_port" class="form-control" value="<?= h($cfg['smtp_port']??587) ?>">
              </div>
              <div class="col-12">
                <label class="form-label"><?= t('smtp_user') ?></label>
                <input type="email" name="smtp_user" class="form-control" value="<?= h($cfg['smtp_user']??'') ?>">
              </div>
              <div class="col-12">
                <label class="form-label"><?= t('smtp_pass') ?> <small class="text-muted">(dejar vacío para no cambiar)</small></label>
                <input type="password" name="smtp_pass" class="form-control" autocomplete="new-password">
              </div>
              <div class="col-md-6">
                <label class="form-label"><?= t('smtp_from') ?></label>
                <input type="email" name="smtp_from" class="form-control" value="<?= h($cfg['smtp_from']??'') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label"><?= t('smtp_from_name') ?></label>
                <input type="text" name="smtp_from_name" class="form-control" value="<?= h($cfg['smtp_from_name']??'BT-Support') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label"><?= t('smtp_secure') ?></label>
                <select name="smtp_secure" class="form-select">
                  <option value="tls" <?= ($cfg['smtp_secure']??'tls')==='tls'?'selected':'' ?>>TLS (puerto 587)</option>
                  <option value="ssl" <?= ($cfg['smtp_secure']??'')==='ssl'?'selected':'' ?>>SSL (puerto 465)</option>
                  <option value=""   <?= ($cfg['smtp_secure']??'')===''?'selected':'' ?>>Ninguno (puerto 25)</option>
                </select>
              </div>
            </div>
          </div>
          <div class="mt-4">
            <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Test & Info -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white"><strong><i class="bi bi-send me-2"></i><?= t('test_email') ?></strong></div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="test">
          <div class="mb-3">
            <label class="form-label">Enviar a</label>
            <input type="email" name="test_email_to" class="form-control" value="<?= h(current_user()['email']) ?>">
          </div>
          <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-send me-1"></i><?= t('test_email') ?></button>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><strong>Proveedores comunes</strong></div>
      <div class="card-body small">
        <table class="table table-sm">
          <tr><td><strong>Gmail</strong></td><td>smtp.gmail.com:587 TLS</td></tr>
          <tr><td><strong>Outlook</strong></td><td>smtp-mail.outlook.com:587 TLS</td></tr>
          <tr><td><strong>Yahoo</strong></td><td>smtp.mail.yahoo.com:587 TLS</td></tr>
          <tr><td><strong>cPanel</strong></td><td>mail.tudominio.com:587</td></tr>
        </table>
        <div class="alert alert-info py-2 small">
          <i class="bi bi-info-circle me-1"></i>
          Para Gmail necesita <strong>contraseña de app</strong> (activar 2FA primero).
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleSmtp() {
  const v = document.getElementById('mailMethod').value;
  document.getElementById('smtpFields').style.display = v === 'smtp' ? 'block' : 'none';
}
toggleSmtp();
</script>
<?php include ROOT . '/templates/footer.php'; ?>
