<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin']);

$did = (int)($_GET['id'] ?? 0);
$st  = db()->prepare("SELECT * FROM departments WHERE id=?");
$st->execute([$did]);
$dept = $st->fetch();
if (!$dept) { flash('error','Departamento no encontrado.'); redirect(base_url('departments')); }

// Current members
$cur_members = array_column((function() use($did){ $s=db()->prepare("SELECT user_id,is_supervisor FROM department_users WHERE department_id=?"); $s->execute([$did]); return $s->fetchAll(); })(),'user_id');
$sup_id_st   = db()->prepare("SELECT user_id FROM department_users WHERE department_id=? AND is_supervisor=1 LIMIT 1");
$sup_id_st->execute([$did]); $sup_row = $sup_id_st->fetch();
$current_sup = $sup_row['user_id'] ?? 0;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $name  = trim($_POST['name']  ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $color = $_POST['color'] ?? $dept['color'];
    $email = trim($_POST['email'] ?? '');
    $members  = array_filter(array_map('intval', $_POST['members'] ?? []));
    $supervisor_id = (int)($_POST['supervisor_id'] ?? 0);
    if (!$name) $errors[] = t('required') . ' (nombre)';
    if (empty($errors)) {
        db()->prepare("UPDATE departments SET name=?,description=?,color=?,email=? WHERE id=?")
           ->execute([$name,$desc,$color,$email,$did]);
        db()->prepare("DELETE FROM department_users WHERE department_id=?")->execute([$did]);
        foreach ($members as $mid) {
            $is_sup = ($mid === $supervisor_id) ? 1 : 0;
            db()->prepare("INSERT IGNORE INTO department_users (department_id,user_id,is_supervisor) VALUES (?,?,?)")
               ->execute([$did,$mid,$is_sup]);
        }
        if ($supervisor_id && !in_array($supervisor_id,$members)) {
            db()->prepare("INSERT IGNORE INTO department_users (department_id,user_id,is_supervisor) VALUES (?,?,1)")->execute([$did,$supervisor_id]);
        }
        flash('success', t('dept_updated'));
        redirect(base_url('departments'));
    }
} else {
    $_POST = array_merge($dept, ['members' => $cur_members, 'supervisor_id' => $current_sup]);
}

$agents = db()->query("SELECT id,name,role FROM users WHERE role IN('agent','supervisor') AND status='active' ORDER BY name")->fetchAll();
$page_title = t('edit_department');
include ROOT . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="fw-bold mb-0"><?= t('edit_department') ?></h4>
  <a href="<?= base_url('departments') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i><?= t('back') ?></a>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e) echo "<li>".h($e)."</li>"; ?></ul></div><?php endif; ?>
<div class="card border-0 shadow-sm" style="max-width:700px">
  <div class="card-body p-4">
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label"><?= t('name') ?> *</label>
        <input type="text" name="name" class="form-control" value="<?= h($_POST['name']??'') ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label"><?= t('description') ?></label>
        <textarea name="description" class="form-control" rows="3"><?= h($_POST['description']??'') ?></textarea>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label"><?= t('dept_email') ?></label>
          <input type="email" name="email" class="form-control" value="<?= h($_POST['email']??'') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= t('color') ?></label>
          <input type="color" name="color" class="form-control form-control-color w-100" value="<?= h($_POST['color']??'#0d6efd') ?>">
        </div>
      </div>
      <?php if ($agents): ?>
      <div class="mb-3">
        <label class="form-label fw-semibold"><?= t('dept_members') ?></label>
        <div class="border rounded p-3" style="max-height:200px;overflow-y:auto">
          <?php foreach ($agents as $a): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="members[]" value="<?= $a['id'] ?>" id="m<?= $a['id'] ?>"
                   <?= in_array($a['id'],(array)($_POST['members']??[]))?'checked':'' ?>>
            <label class="form-check-label" for="m<?= $a['id'] ?>"><?= h($a['name']) ?> <?= role_badge($a['role']) ?></label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold"><?= t('dept_supervisor') ?></label>
        <select name="supervisor_id" class="form-select">
          <option value=""><?= t('none') ?></option>
          <?php foreach ($agents as $a): ?>
            <option value="<?= $a['id'] ?>" <?= ($_POST['supervisor_id']??'')==$a['id']?'selected':'' ?>><?= h($a['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
        <a href="<?= base_url('departments') ?>" class="btn btn-outline-secondary"><?= t('cancel') ?></a>
      </div>
    </form>
  </div>
</div>
<?php include ROOT . '/templates/footer.php'; ?>
