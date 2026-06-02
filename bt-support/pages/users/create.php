<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin']);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $name  = trim($_POST['name']   ?? '');
    $email = trim($_POST['email']  ?? '');
    $pass  = $_POST['password']    ?? '';
    $role  = $_POST['role']        ?? 'client';
    $phone = trim($_POST['phone']  ?? '');
    $lang  = $_POST['language']    ?? 'es';
    $depts = array_filter(array_map('intval', $_POST['departments'] ?? []));
    $supervisor_dept = (int)($_POST['supervisor_dept'] ?? 0);

    if (!$name)  $errors[] = t('required') . ' (' . t('name') . ')';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('email_invalid');
    if (strlen($pass) < 8) $errors[] = t('password_min_8');
    if (!in_array($role,['super_admin','admin','supervisor','agent','client'])) $errors[] = t('role_invalid');

    if (empty($errors)) {
        $chk = db()->prepare("SELECT id FROM users WHERE email=?");
        $chk->execute([$email]);
        if ($chk->fetch()) $errors[] = t('email_taken');
    }

    if (empty($errors)) {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        db()->prepare("INSERT INTO users (name,email,password,role,phone,language) VALUES (?,?,?,?,?,?)")
           ->execute([$name,$email,$hash,$role,$phone,$lang]);
        $new_uid = (int)db()->lastInsertId();

        // Assign departments
        foreach ($depts as $did) {
            $is_sup = ($role === 'supervisor' && $did === $supervisor_dept) ? 1 : 0;
            db()->prepare("INSERT IGNORE INTO department_users (department_id,user_id,is_supervisor) VALUES (?,?,?)")
               ->execute([$did,$new_uid,$is_sup]);
        }

        // Handle avatar
        if (!empty($_FILES['avatar']['name'])) {
            $fname = upload_file($_FILES['avatar'],'avatars',['jpg','jpeg','png','gif']);
            if ($fname) db()->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$fname,$new_uid]);
        }

        log_activity('create_user','user',$new_uid,$email);
        flash('success', t('user_created'));
        redirect(base_url('users'));
    }
}

$departments = db()->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$page_title  = t('new_user');
include ROOT . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="fw-bold mb-0"><?= t('new_user') ?></h4>
  <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i><?= t('back') ?></a>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e) echo "<li>".h($e)."</li>"; ?></ul></div><?php endif; ?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <form method="post" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label"><?= t('name') ?> *</label>
              <input type="text" name="name" class="form-control" value="<?= h($_POST['name']??'') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('email') ?> *</label>
              <input type="email" name="email" class="form-control" value="<?= h($_POST['email']??'') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('phone') ?></label>
              <input type="text" name="phone" class="form-control" value="<?= h($_POST['phone']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('password') ?> * (mín. 8)</label>
              <input type="password" name="password" class="form-control" minlength="8" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('role') ?> *</label>
              <select name="role" class="form-select" id="roleSelect">
                <?php foreach (['client','agent','supervisor','admin','super_admin'] as $r): ?>
                  <option value="<?= $r ?>" <?= ($_POST['role']??'')===$r?'selected':'' ?>><?= role_label($r) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('language') ?></label>
              <select name="language" class="form-select">
                <option value="es" <?= ($_POST['language']??'es')==='es'?'selected':'' ?>><?= t('lang_es') ?></option>
                <option value="en" <?= ($_POST['language']??'')==='en'?'selected':'' ?>><?= t('lang_en') ?></option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('avatar') ?></label>
              <input type="file" name="avatar" class="form-control" accept="image/*">
            </div>

            <!-- Departments -->
            <?php if ($departments): ?>
            <div class="col-12" id="deptsSection">
              <label class="form-label fw-semibold"><?= t('departments') ?></label>
              <div class="border rounded p-3" style="max-height:200px;overflow-y:auto">
                <?php foreach ($departments as $d): ?>
                <div class="form-check">
                  <input class="form-check-input dept-check" type="checkbox" name="departments[]"
                         value="<?= $d['id'] ?>" id="dept<?= $d['id'] ?>"
                         <?= in_array($d['id'], (array)($_POST['departments']??[]))?'checked':'' ?>>
                  <label class="form-check-label" for="dept<?= $d['id'] ?>">
                    <span class="badge me-1" style="background:<?= h($d['color']) ?>">&nbsp;</span>
                    <?= h($d['name']) ?>
                  </label>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Supervisor dept (shown only when role=supervisor) -->
            <div class="col-12" id="supDeptSection" style="display:none">
              <label class="form-label"><?= t('set_supervisor') ?> — <?= t('department') ?></label>
              <select name="supervisor_dept" class="form-select">
                <option value=""><?= t('select') ?>...</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= $d['id'] ?>"><?= h($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Elige el departamento principal del cual será supervisor.</small>
            </div>
            <?php endif; ?>
          </div>
          <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= t('create') ?></button>
            <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary"><?= t('cancel') ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Role info -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><strong>Permisos por rol</strong></div>
      <div class="card-body small">
        <?php foreach (['super_admin'=>['#dc3545','Control total del sistema'],'admin'=>['#fd7e14','Gestión global, usuarios, reportes'],'supervisor'=>['#0dcaf0','Ve y gestiona tickets de su departamento, reasigna agentes'],'agent'=>['#0d6efd','Responde tickets asignados a él'],'client'=>['#6c757d','Crea y ve sus propios tickets']] as $r=>[$color,$desc]): ?>
        <div class="d-flex gap-2 mb-2">
          <span class="badge" style="background:<?= $color ?>"><?= role_label($r) ?></span>
          <span class="text-muted"><?= $desc ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
const roleSelect = document.getElementById('roleSelect');
const supSection = document.getElementById('supDeptSection');
function toggleSupDept() {
  supSection.style.display = roleSelect.value === 'supervisor' ? 'block' : 'none';
}
roleSelect.addEventListener('change', toggleSupDept);
toggleSupDept();
</script>
<?php include ROOT . '/templates/footer.php'; ?>
