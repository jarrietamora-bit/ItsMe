<?php
/**
 * BT-Support Installer
 * Session-free design: DB credentials travel in hidden form fields between steps.
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

$step   = (int)($_GET['step'] ?? 1);
$errors = [];

// Helper: pull DB credentials from POST hidden fields
function db_from_post(): array {
    return [
        'db_host' => trim($_POST['_db_host'] ?? 'localhost'),
        'db_name' => trim($_POST['_db_name'] ?? ''),
        'db_user' => trim($_POST['_db_user'] ?? ''),
        'db_pass' => $_POST['_db_pass'] ?? '',
        'db_port' => trim($_POST['_db_port'] ?? '3306'),
    ];
}

function make_pdo(array $db, bool $with_dbname = true): PDO {
    $dsn = $with_dbname
        ? "mysql:host={$db['db_host']};port={$db['db_port']};dbname={$db['db_name']};charset=utf8mb4"
        : "mysql:host={$db['db_host']};port={$db['db_port']};charset=utf8mb4";
    return new PDO($dsn, $db['db_user'], $db['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

// ---- Step processing ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Step 1 → 2
    if ($step === 1) {
        header('Location: ?step=2'); exit;
    }

    // Step 2: connect DB + run SQL → go to step 3
    if ($step === 2) {
        $db = [
            'db_host' => trim($_POST['db_host'] ?? 'localhost'),
            'db_name' => trim($_POST['db_name'] ?? ''),
            'db_user' => trim($_POST['db_user'] ?? ''),
            'db_pass' => $_POST['db_pass'] ?? '',
            'db_port' => trim($_POST['db_port'] ?? '3306'),
        ];

        if (!$db['db_name']) $errors[] = 'El nombre de la base de datos es obligatorio.';
        if (!$db['db_user']) $errors[] = 'El usuario MySQL es obligatorio.';

        if (empty($errors)) {
            try {
                $pdo = make_pdo($db, false);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$db['db_name']}`");

                // Run SQL — ignore errors for already-existing tables/rows
                $sql = file_get_contents(__DIR__ . '/install.sql');
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
                    try { $pdo->exec($query); } catch (\Throwable $ignored) {}
                }

                // Pass credentials to step 3 via URL-encoded hidden fields (no session needed)
                $qs = http_build_query(['step' => 3] + array_combine(
                    ['_db_host','_db_name','_db_user','_db_pass','_db_port'],
                    [$db['db_host'],$db['db_name'],$db['db_user'],$db['db_pass'],$db['db_port']]
                ));
                header('Location: ?' . $qs); exit;
            } catch (\Throwable $e) {
                $errors[] = 'Error de conexión: ' . $e->getMessage();
            }
        }
    }

    // Step 3: create admin account
    if ($step === 3) {
        $db    = db_from_post();
        $name  = trim($_POST['admin_name']  ?? '');
        $email = trim($_POST['admin_email'] ?? '');
        $pass  = $_POST['admin_pass']  ?? '';
        $pass2 = $_POST['admin_pass2'] ?? '';

        if (!$name)  $errors[] = 'El nombre es obligatorio.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
        if (strlen($pass) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($pass !== $pass2) $errors[] = 'Las contraseñas no coinciden.';
        if (!$db['db_name'] || !$db['db_user']) $errors[] = 'Faltan credenciales de base de datos. Vuelva al Paso 2.';

        if (empty($errors)) {
            try {
                $pdo = make_pdo($db);
                // Check if admin already exists
                $existing = $pdo->prepare("SELECT id FROM users WHERE role='super_admin' LIMIT 1");
                $existing->execute();
                if ($existing->fetch()) {
                    $errors[] = 'Ya existe un administrador. La base de datos ya fue configurada. Continúe al <a href="?step=4&' . http_build_query(array_combine(['_db_host','_db_name','_db_user','_db_pass','_db_port'], array_values($db))) . '">Paso 4</a>.';
                } else {
                    $hash = password_hash($pass, PASSWORD_BCRYPT);
                    $st = $pdo->prepare("INSERT INTO users (name, email, password, role, status, language) VALUES (?,?,?,'super_admin','active','es')");
                    $st->execute([$name, $email, $hash]);
                    $uid = $pdo->lastInsertId();
                    $dep = $pdo->query("SELECT id FROM departments LIMIT 1")->fetch();
                    if ($dep) {
                        $pdo->prepare("INSERT IGNORE INTO department_users (department_id, user_id, is_supervisor) VALUES (?,?,1)")->execute([$dep['id'], $uid]);
                    }
                    $qs = http_build_query(['step' => 4] + array_combine(
                        ['_db_host','_db_name','_db_user','_db_pass','_db_port'],
                        array_values($db)
                    ));
                    header('Location: ?' . $qs); exit;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Error al crear el administrador: ' . $e->getMessage();
            }
        }
    }

    // Step 4: company info + write config
    if ($step === 4) {
        $db      = db_from_post();
        $company = trim($_POST['company_name'] ?? 'BT-Support');
        $lang    = $_POST['default_language'] ?? 'es';
        $tz      = $_POST['timezone'] ?? 'America/Costa_Rica';
        $color   = $_POST['company_color'] ?? '#0d6efd';
        $url     = rtrim(trim($_POST['app_url'] ?? ''), '/');

        if (!$db['db_name'] || !$db['db_user']) {
            $errors[] = 'Faltan credenciales de base de datos. Vuelva al Paso 2.';
        }

        if (empty($errors)) {
            try {
                $pdo = make_pdo($db);
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'company_name'")->execute([$company]);
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'default_language'")->execute([$lang]);
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'timezone'")->execute([$tz]);
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'company_color'")->execute([$color]);
                $pdo->prepare("UPDATE users SET language = ? WHERE role = 'super_admin'")->execute([$lang]);

                $cfg = "<?php\nreturn [\n" .
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

                file_put_contents($root . '/config/config.php', $cfg);
                header('Location: ?step=5'); exit;
            } catch (\Throwable $e) {
                $errors[] = 'Error al guardar la configuración: ' . $e->getMessage();
            }
        }
    }
}

// Carry DB credentials from GET parameters (step 3/4 redirect)
$carried = [];
foreach (['_db_host','_db_name','_db_user','_db_pass','_db_port'] as $k) {
    if (isset($_GET[$k])) $carried[$k] = $_GET[$k];
    elseif (isset($_POST[$k])) $carried[$k] = $_POST[$k];
}

// Requirements check
$req = [
    'PHP >= 7.4'                       => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO MySQL'                        => extension_loaded('pdo_mysql'),
    'OpenSSL'                          => extension_loaded('openssl'),
    'Fileinfo'                         => extension_loaded('fileinfo'),
    'Directorio uploads/ escribible'   => is_writable($root . '/uploads') || @mkdir($root . '/uploads', 0755, true),
    'Directorio config/ escribible'    => is_writable($root . '/config'),
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

    <div class="step-indicator">
      <?php
      $labels = ['Requisitos','Base de datos','Admin','Empresa','Listo'];
      for ($i = 1; $i <= 5; $i++):
        $cls = $i < $step ? 'done' : ($i === $step ? 'active' : '');
        echo "<div class=\"step-dot {$cls}\">" . ($i < $step ? '<i class="bi bi-check"></i>' : $i) . "</div>";
        if ($i < 5) echo "<div class=\"step-line " . ($i < $step ? 'done' : '') . "\"></div>";
      endfor; ?>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0">
        <?php foreach ($errors as $e) echo "<li>{$e}</li>"; ?>
      </ul></div>
    <?php endif; ?>

    <?php // ===== STEP 1 ===== ?>
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

    <?php // ===== STEP 2 ===== ?>
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
          <input type="text" name="db_name" class="form-control" placeholder="betterte_bt_support" required>
          <small class="text-muted">En cPanel incluya el prefijo de su cuenta (ej. <code>usuario_bt_support</code>).</small>
        </div>
        <div class="col-md-6">
          <label class="form-label">Usuario MySQL</label>
          <input type="text" name="db_user" class="form-control" placeholder="betterte_devweb" required>
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

    <?php // ===== STEP 3 ===== ?>
    <?php elseif ($step === 3): ?>
    <h4 class="mb-4">Cuenta de administrador</h4>
    <form method="post">
      <?php foreach ($carried as $k => $v): ?>
        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
      <?php endforeach; ?>
      <div class="mb-3">
        <label class="form-label">Nombre completo</label>
        <input type="text" name="admin_name" class="form-control" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Correo electrónico</label>
        <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
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

    <?php // ===== STEP 4 ===== ?>
    <?php elseif ($step === 4): ?>
    <h4 class="mb-4">Información de la empresa</h4>
    <form method="post">
      <?php foreach ($carried as $k => $v): ?>
        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
      <?php endforeach; ?>
      <div class="mb-3">
        <label class="form-label">Nombre de la empresa</label>
        <input type="text" name="company_name" class="form-control" value="BT-Support" required>
      </div>
      <div class="mb-3">
        <label class="form-label">URL del sistema</label>
        <?php
          $guessed_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
              . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
              . rtrim(dirname(dirname($_SERVER['REQUEST_URI'] ?? '')), '/');
        ?>
        <input type="url" name="app_url" class="form-control" value="<?= htmlspecialchars($guessed_url) ?>" required>
        <small class="text-muted">URL base donde está instalado BT-Support (sin barra final).</small>
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

    <?php // ===== STEP 5 ===== ?>
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
