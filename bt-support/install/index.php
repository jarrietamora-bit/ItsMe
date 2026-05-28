<?php
/**
 * BT-Support Installer
 * Run this wizard once to configure the system
 */

define('INSTALLER', true);
$root = dirname(__DIR__);

// If already installed, block access
if (file_exists($root . '/config/config.php')) {
    $cfg = include $root . '/config/config.php';
    if (!empty($cfg['installed'])) {
        die('<h2 style="font-family:sans-serif;text-align:center;margin-top:60px">BT-Support ya está instalado.<br><a href="../">Ir al sistema</a></h2>');
    }
}

$step = (int)($_GET['step'] ?? 1);
$errors = [];
$success = '';

// ---- Step processing ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($step === 1) {
        // Requirements check — just advance
        header('Location: ?step=2');
        exit;
    }

    if ($step === 2) {
        // Test DB connection
        $db_host = trim($_POST['db_host'] ?? 'localhost');
        $db_name = trim($_POST['db_name'] ?? '');
        $db_user = trim($_POST['db_user'] ?? '');
        $db_pass = $_POST['db_pass'] ?? '';
        $db_port = trim($_POST['db_port'] ?? '3306');

        if (!$db_name) { $errors[] = 'El nombre de la base de datos es obligatorio.'; }

        if (empty($errors)) {
            try {
                $dsn = "mysql:host={$db_host};port={$db_port};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$db_name}`");
                // Run SQL
                $sql = file_get_contents(__DIR__ . '/install.sql');
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
                    if ($query) $pdo->exec($query);
                }
                // Save to session
                session_start();
                $_SESSION['install'] = compact('db_host','db_name','db_user','db_pass','db_port');
                header('Location: ?step=3');
                exit;
            } catch (\Throwable $e) {
                $errors[] = 'Error de conexión: ' . $e->getMessage();
            }
        }
    }

    if ($step === 3) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $name  = trim($_POST['admin_name']  ?? '');
        $email = trim($_POST['admin_email'] ?? '');
        $pass  = $_POST['admin_pass']  ?? '';
        $pass2 = $_POST['admin_pass2'] ?? '';

        if (!$name)  $errors[] = 'El nombre es obligatorio.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
        if (strlen($pass) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($pass !== $pass2) $errors[] = 'Las contraseñas no coinciden.';

        // If session lost, try to read from hidden POST fields
        if (empty($_SESSION['install'])) {
            $db_host = trim($_POST['db_host_s'] ?? 'localhost');
            $db_name = trim($_POST['db_name_s'] ?? '');
            $db_user = trim($_POST['db_user_s'] ?? '');
            $db_pass = $_POST['db_pass_s'] ?? '';
            $db_port = trim($_POST['db_port_s'] ?? '3306');
            if ($db_name && $db_user) {
                $_SESSION['install'] = compact('db_host','db_name','db_user','db_pass','db_port');
            } else {
                $errors[] = 'Sesión expirada. <a href="?step=2">Vuelva al Paso 2</a> e intente de nuevo.';
            }
        }

        if (empty($errors)) {
            try {
                $db = $_SESSION['install'];
                $pdo = new PDO("mysql:host={$db['db_host']};port={$db['db_port']};dbname={$db['db_name']};charset=utf8mb4",
                               $db['db_user'], $db['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $st = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?,?,?,'super_admin','active')");
                $st->execute([$name, $email, $hash]);
                $uid = $pdo->lastInsertId();
                // Assign to default department only if it exists
                $dep = $pdo->query("SELECT id FROM departments LIMIT 1")->fetch();
                if ($dep) {
                    $pdo->prepare("INSERT IGNORE INTO department_users (department_id, user_id, is_supervisor) VALUES (?,?,1)")->execute([$dep['id'], $uid]);
                }
                $_SESSION['install']['admin'] = compact('name','email');
                header('Location: ?step=4');
                exit;
            } catch (\Throwable $e) {
                $errors[] = 'Error al crear el administrador: ' . $e->getMessage();
            }
        }
    }

    if ($step === 4) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['install'])) {
            $db_host = trim($_POST['db_host_s'] ?? 'localhost');
            $db_name = trim($_POST['db_name_s'] ?? '');
            $db_user = trim($_POST['db_user_s'] ?? '');
            $db_pass = $_POST['db_pass_s'] ?? '';
            $db_port = trim($_POST['db_port_s'] ?? '3306');
            if ($db_name && $db_user) {
                $_SESSION['install'] = compact('db_host','db_name','db_user','db_pass','db_port');
            } else {
                die('<div style="font-family:sans-serif;text-align:center;margin-top:60px"><h3>Sesión expirada</h3><p><a href="?step=2">Volver al Paso 2</a></p></div>');
            }
        }
        $db = $_SESSION['install'];
        $pdo = new PDO("mysql:host={$db['db_host']};port={$db['db_port']};dbname={$db['db_name']};charset=utf8mb4",
                       $db['db_user'], $db['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $company = trim($_POST['company_name'] ?? 'BT-Support');
        $lang    = $_POST['default_language'] ?? 'es';
        $tz      = $_POST['timezone'] ?? 'America/Costa_Rica';
        $color   = $_POST['company_color'] ?? '#0d6efd';
        $url     = rtrim(trim($_POST['app_url'] ?? ''), '/');

        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'company_name'")->execute([$company]);
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'default_language'")->execute([$lang]);
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'timezone'")->execute([$tz]);
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'company_color'")->execute([$color]);
        $pdo->prepare("UPDATE users SET language = ?")->execute([$lang]);

        // Write config.php
        $config_content = "<?php\nreturn [\n" .
            "    'db_host'        => " . var_export($db['db_host'], true) . ",\n" .
            "    'db_name'        => " . var_export($db['db_name'], true) . ",\n" .
            "    'db_user'        => " . var_export($db['db_user'], true) . ",\n" .
            "    'db_pass'        => " . var_export($db['db_pass'], true) . ",\n" .
            "    'db_port'        => " . var_export($db['db_port'], true) . ",\n" .
            "    'app_url'        => " . var_export($url, true) . ",\n" .
            "    'app_name'       => 'BT-Support',\n" .
            "    'app_lang'       => " . var_export($lang, true) . ",\n" .
            "    'timezone'       => " . var_export($tz, true) . ",\n" .
            "    'installed'      => true,\n" .
            "    'smtp_host'      => '',\n" .
            "    'smtp_port'      => 587,\n" .
            "    'smtp_user'      => '',\n" .
            "    'smtp_pass'      => '',\n" .
            "    'smtp_from'      => '',\n" .
            "    'smtp_from_name' => 'BT-Support',\n" .
            "    'smtp_secure'    => 'tls',\n" .
            "    'mail_method'    => 'php',\n" .
            "    'upload_max_mb'  => 10,\n" .
            "    'session_timeout'=> 120,\n" .
            "];\n";

        file_put_contents($root . '/config/config.php', $config_content);
        header('Location: ?step=5');
        exit;
    }
}

if ($step >= 2 || $step === 3 || $step === 4) {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

// ---- Requirements check ----
$req = [
    'PHP >= 7.4'              => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO MySQL'               => extension_loaded('pdo_mysql'),
    'OpenSSL'                 => extension_loaded('openssl'),
    'Fileinfo'                => extension_loaded('fileinfo'),
    'Directorio uploads/ escribible' => is_writable($root . '/uploads') || @mkdir($root . '/uploads', 0755, true),
    'Directorio config/ escribible'  => is_writable($root . '/config'),
];

$all_ok = !in_array(false, $req, true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BT-Support — Instalador</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body{background:#f0f4f8;font-family:'Segoe UI',sans-serif}
  .installer-card{max-width:640px;margin:40px auto;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1)}
  .installer-header{background:linear-gradient(135deg,#0d6efd,#0054b6);color:#fff;padding:28px 32px}
  .installer-header h1{font-size:1.6rem;margin:0}
  .installer-header small{opacity:.8}
  .installer-body{background:#fff;padding:32px}
  .step-indicator{display:flex;gap:8px;margin-bottom:28px}
  .step-dot{width:32px;height:32px;border-radius:50%;background:#e9ecef;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;color:#6c757d;flex-shrink:0}
  .step-dot.active{background:#0d6efd;color:#fff}
  .step-dot.done{background:#198754;color:#fff}
  .step-line{flex:1;height:2px;background:#e9ecef;margin-top:15px}
  .step-line.done{background:#198754}
  .req-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f0f0f0}
</style>
</head>
<body>
<div class="installer-card">
  <div class="installer-header">
    <h1><i class="bi bi-headset me-2"></i>BT-Support</h1>
    <small>Asistente de instalación</small>
  </div>
  <div class="installer-body">

    <!-- Step indicator -->
    <div class="step-indicator">
      <?php
      $steps = ['Requisitos','Base de datos','Admin','Empresa','Listo'];
      for ($i = 1; $i <= 5; $i++):
        $cls = $i < $step ? 'done' : ($i === $step ? 'active' : '');
        echo "<div class=\"step-dot {$cls}\">" . ($i < $step ? '<i class="bi bi-check"></i>' : $i) . "</div>";
        if ($i < 5) echo "<div class=\"step-line " . ($i < $step ? 'done' : '') . "\"></div>";
      endfor; ?>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul></div>
    <?php endif; ?>

    <!-- Step 1: Requirements -->
    <?php if ($step === 1): ?>
    <h4 class="mb-4">Verificación de requisitos</h4>
    <?php foreach ($req as $label => $ok): ?>
      <div class="req-item">
        <i class="bi <?= $ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i>
        <span><?= htmlspecialchars($label) ?></span>
        <span class="ms-auto badge <?= $ok ? 'bg-success' : 'bg-danger' ?>"><?= $ok ? 'OK' : 'Fallo' ?></span>
      </div>
    <?php endforeach; ?>
    <form method="post" class="mt-4">
      <button type="submit" class="btn btn-primary" <?= $all_ok ? '' : 'disabled' ?>>
        Continuar <i class="bi bi-arrow-right ms-1"></i>
      </button>
      <?php if (!$all_ok): ?>
        <div class="text-danger mt-2 small">Solucione los requisitos fallidos antes de continuar.</div>
      <?php endif; ?>
    </form>

    <!-- Step 2: Database -->
    <?php elseif ($step === 2): ?>
    <h4 class="mb-4">Configuración de base de datos</h4>
    <form method="post">
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Servidor MySQL</label>
          <input type="text" name="db_host" class="form-control" value="localhost" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Puerto</label>
          <input type="text" name="db_port" class="form-control" value="3306">
        </div>
        <div class="col-12">
          <label class="form-label">Nombre de la base de datos</label>
          <input type="text" name="db_name" class="form-control" placeholder="bt_support" required>
          <small class="text-muted">Se creará automáticamente si no existe.</small>
        </div>
        <div class="col-md-6">
          <label class="form-label">Usuario MySQL</label>
          <input type="text" name="db_user" class="form-control" placeholder="root" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Contraseña MySQL</label>
          <input type="password" name="db_pass" class="form-control">
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-4">
        Conectar y crear tablas <i class="bi bi-arrow-right ms-1"></i>
      </button>
    </form>

    <!-- Step 3: Admin account -->
    <?php elseif ($step === 3): ?>
    <h4 class="mb-4">Cuenta de administrador</h4>
    <form method="post">
      <?php if (!empty($_SESSION['install'])): $db = $_SESSION['install']; ?>
      <input type="hidden" name="db_host_s" value="<?= htmlspecialchars($db['db_host']) ?>">
      <input type="hidden" name="db_name_s" value="<?= htmlspecialchars($db['db_name']) ?>">
      <input type="hidden" name="db_user_s" value="<?= htmlspecialchars($db['db_user']) ?>">
      <input type="hidden" name="db_pass_s" value="<?= htmlspecialchars($db['db_pass']) ?>">
      <input type="hidden" name="db_port_s" value="<?= htmlspecialchars($db['db_port']) ?>">
      <?php endif; ?>
      <div class="mb-3">
        <label class="form-label">Nombre completo</label>
        <input type="text" name="admin_name" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Correo electrónico</label>
        <input type="email" name="admin_email" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Contraseña (mín. 8 caracteres)</label>
        <input type="password" name="admin_pass" class="form-control" minlength="8" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Confirmar contraseña</label>
        <input type="password" name="admin_pass2" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary">Crear cuenta <i class="bi bi-arrow-right ms-1"></i></button>
    </form>

    <!-- Step 4: Company info -->
    <?php elseif ($step === 4): ?>
    <h4 class="mb-4">Información de la empresa</h4>
    <form method="post">
      <?php if (!empty($_SESSION['install'])): $db = $_SESSION['install']; ?>
      <input type="hidden" name="db_host_s" value="<?= htmlspecialchars($db['db_host']) ?>">
      <input type="hidden" name="db_name_s" value="<?= htmlspecialchars($db['db_name']) ?>">
      <input type="hidden" name="db_user_s" value="<?= htmlspecialchars($db['db_user']) ?>">
      <input type="hidden" name="db_pass_s" value="<?= htmlspecialchars($db['db_pass']) ?>">
      <input type="hidden" name="db_port_s" value="<?= htmlspecialchars($db['db_port']) ?>">
      <?php endif; ?>
      <div class="mb-3">
        <label class="form-label">Nombre de la empresa</label>
        <input type="text" name="company_name" class="form-control" value="BT-Support" required>
      </div>
      <div class="mb-3">
        <label class="form-label">URL del sistema</label>
        <input type="url" name="app_url" class="form-control" value="<?= htmlspecialchars('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname(dirname($_SERVER['REQUEST_URI'] ?? ''))) ?>" required>
        <small class="text-muted">URL base donde estará instalado BT-Support.</small>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Idioma predeterminado</label>
          <select name="default_language" class="form-select">
            <option value="es">Español</option>
            <option value="en">English</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Zona horaria</label>
          <select name="timezone" class="form-select">
            <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
              <option value="<?= $tz ?>" <?= $tz === 'America/Costa_Rica' ? 'selected' : '' ?>><?= $tz ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Color principal</label>
          <input type="color" name="company_color" class="form-control form-control-color w-100" value="#0d6efd">
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-4">Finalizar instalación <i class="bi bi-check-lg ms-1"></i></button>
    </form>

    <!-- Step 5: Done -->
    <?php elseif ($step === 5): ?>
    <div class="text-center py-3">
      <i class="bi bi-check-circle-fill text-success" style="font-size:4rem"></i>
      <h4 class="mt-3 mb-2">¡BT-Support instalado correctamente!</h4>
      <p class="text-muted">Su sistema de soporte está listo para usarse.</p>
      <div class="alert alert-warning text-start mt-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Importante:</strong> Por seguridad, elimine o renombre la carpeta <code>install/</code> de su servidor.
      </div>
      <a href="../" class="btn btn-primary btn-lg mt-2">
        <i class="bi bi-box-arrow-in-right me-2"></i>Ir al sistema
      </a>
    </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
