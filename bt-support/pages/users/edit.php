<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin']);

$edit_id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT * FROM users WHERE id=?");
$st->execute([$edit_id]);
$u = $st->fetch();
if (!$u) { flash('error','Usuario no encontrado.'); redirect(base_url('users')); }

// Current departments
$user_depts = array_column(db()->prepare("SELECT department_id, is_supervisor FROM department_users WHERE user_id=?")->execute([$edit_id]) ? (function() use($edit_id) { $s=db()->prepare("SELECT department_id,is_supervisor FROM department_users WHERE user_id=?"); $s->execute([$edit_id]); return $s->fetchAll(); })() : [], 'department_id');
$is_sup_dept = (function() use($edit_id) { $s=db()->prepare("SELECT department_id FROM department_users WHERE user_id=? AND is_supervisor=1 LIMIT 1"); $s->execute([$edit_id]); $r=$s->fetch(); return $r['department_id']??0; })();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $name  = trim($_POST['name']   ?? '');
    $email = trim($_POST['email']  ?? '');
    $role  = $_POST['role']        ?? $u['role'];
    $phone = trim($_POST['phone']  ?? '');
    $lang  = $_POST['language']    ?? $u['language'];
    $depts = array_filter(array_map('intval', $_POST['departments'] ?? []));
    $supervisor_dept = (int)($_POST['supervisor_dept'] ?? 0);
    $new_pass = $_POST['new_password'] ?? '';

    if (!$name)  $errors[] = t('required') . ' (nombre)';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
    if (empty($errors)) {
        $chk = db()->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $chk->execute([$email,$edit_id]);
        if ($chk->fetch()) $errors[] = 'Email ya existe en otro usuario.';
    }
    if (empty($errors)) {
        db()->prepare("UPDATE users SET name=?,email=?,role=?,phone=?,language=?,updated_at=NOW() WHERE id=?")
           ->execute([$name,$email,$role,$phone,$lang,$edit_id]);
        if ($new_pass && strlen($new_pass) >= 8) {
            db()->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new_pass,PASSWORD_BCRYPT),$edit_id]);
        }
        // Departments: rebuild
        db()->prepare("DELETE FROM department_users WHERE user_id=?")->execute([$edit_id]);
        foreach ($depts as $did) {
            $is_sup = ($role === 'supervisor' && $did === $supervisor_dept) ? 1 : 0;
            db()->prepare("INSERT IGNORE INTO department_users (department_id,user_id,is_supervisor) VALUES (?,?,?)")
               ->execute([$did,$edit_id,$is_sup]);
        }
        // Avatar
        if (!empty($_FILES['avatar']['name'])) {
            $fname = upload_file($_FILES['avatar'],'avatars',['jpg','jpeg','png','gif']);
            if ($fname) db()->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$fname,$edit_id]);
        }
        flash('success',t('user_updated'));
        redirect(base_url('users'));
    }
} else {
    // Pre-populate from DB
    $_POST = $u;
}

$departments = db()->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$page_title  = t('edit_user');
include ROOT . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="fw-bold mb-0"><?= t('edit_user') ?></h4>
  <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i><?= t('back') ?></a>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e) echo "<li>".h($e)."</li>"; ?></ul></div><?php endif; ?>
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
          <label class="form-label"><?= t('role') ?></label>
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
          <label class="form-label"><?= t('new_password') ?> <small class="text-muted">(<?= t('optional') ?>)</small></label>
          <input type="password" name="new_password" class="form-control" minlength="8" placeholder="Dejar vacío para no cambiar">
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= t('avatar') ?></label>
          <?php if ($u['avatar']): ?>
            <img src="<?= base_url('uploads/avatars/'.$u['avatar']) ?>" class="rounded-circle mb-2 d-block" width="40" height="40" style="object-fit:cover">
          <?php endif; ?>
          <input type="file" name="avatar" class="form-control" accept="image/*">
        </div>

        <?php if ($departments): ?>
        <div class="col-12">
          <label class="form-label fw-semibold"><?= t('departments') ?></label>
          <div class="border rounded p-3" style="max-height:200px;overflow-y:auto">
            <?php
            $st4 = db()->prepare("SELECT department_id FROM department_users WHERE user_id=?");
            $st4->execute([$edit_id]);
            $current_depts = array_column($st4->fetchAll(),'department_id');
            foreach ($departments as $d): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="departments[]"
                     value="<?= $d['id'] ?>" id="dept<?= $d['id'] ?>"
                     <?= in_array($d['id'],$current_depts)?'checked':'' ?>>
              <label class="form-check-label" for="dept<?= $d['id'] ?>">
                <span class="badge me-1" style="background:<?= h($d['color']) ?>">&nbsp;</span>
                <?= h($d['name']) ?>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col-12" id="supDeptSection" style="display:none">
          <label class="form-label"><?= t('set_supervisor') ?> — Departamento principal</label>
          <select name="supervisor_dept" class="form-select">
            <option value=""><?= t('select') ?>...</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= $d['id'] ?>" <?= $d['id']==$is_sup_dept?'selected':'' ?>><?= h($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
        <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary"><?= t('cancel') ?></a>
      </div>
    </form>
  </div>
</div>
<script>
const roleSelect = document.getElementById('roleSelect');
const supSection = document.getElementById('supDeptSection');
function toggleSup(){ supSection.style.display = roleSelect.value==='supervisor'?'block':'none'; }
roleSelect.addEventListener('change', toggleSup); toggleSup();
</script>
<?php include ROOT . '/templates/footer.php'; ?>
