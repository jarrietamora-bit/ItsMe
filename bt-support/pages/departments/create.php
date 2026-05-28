<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $name  = trim($_POST['name']  ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $color = $_POST['color'] ?? '#0d6efd';
    $email = trim($_POST['email'] ?? '');
    $members  = array_filter(array_map('intval', $_POST['members'] ?? []));
    $supervisor_id = (int)($_POST['supervisor_id'] ?? 0);

    if (!$name) $errors[] = t('required') . ' (nombre)';
    if (empty($errors)) {
        db()->prepare("INSERT INTO departments (name,description,color,email) VALUES (?,?,?,?)")
           ->execute([$name,$desc,$color,$email]);
        $dept_id = (int)db()->lastInsertId();

        foreach ($members as $mid) {
            $is_sup = ($mid === $supervisor_id) ? 1 : 0;
            db()->prepare("INSERT IGNORE INTO department_users (department_id,user_id,is_supervisor) VALUES (?,?,?)")
               ->execute([$dept_id,$mid,$is_sup]);
        }
        // Ensure supervisor is also a member
        if ($supervisor_id && !in_array($supervisor_id,$members)) {
            db()->prepare("INSERT IGNORE INTO department_users (department_id,user_id,is_supervisor) VALUES (?,?,1)")
               ->execute([$dept_id,$supervisor_id]);
        }

        log_activity('create_dept','department',$dept_id,$name);
        flash('success', t('dept_created'));
        redirect(base_url('departments'));
    }
}

$agents = db()->query("SELECT id,name,role FROM users WHERE role IN('agent','supervisor') AND status='active' ORDER BY name")->fetchAll();
$page_title = t('new_department');
include ROOT . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="fw-bold mb-0"><?= t('new_department') ?></h4>
  <a href="<?= base_url('departments') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i><?= t('back') ?></a>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e) echo "<li>".h($e)."</li>"; ?></ul></div><?php endif; ?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
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
            <label class="form-label fw-semibold"><?= t('assign_members') ?> <small class="fw-normal text-muted">(agentes)</small></label>
            <div class="border rounded p-3" style="max-height:200px;overflow-y:auto">
              <?php foreach ($agents as $a): ?>
              <div class="form-check">
                <input class="form-check-input member-check" type="checkbox" name="members[]"
                       value="<?= $a['id'] ?>" id="m<?= $a['id'] ?>"
                       <?= in_array($a['id'],(array)($_POST['members']??[]))?'checked':'' ?>>
                <label class="form-check-label" for="m<?= $a['id'] ?>">
                  <?= h($a['name']) ?> <?= role_badge($a['role']) ?>
                </label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold"><?= t('set_supervisor') ?></label>
            <select name="supervisor_id" class="form-select">
              <option value=""><?= t('none') ?></option>
              <?php foreach ($agents as $a): ?>
                <option value="<?= $a['id'] ?>" <?= ($_POST['supervisor_id']??'')==$a['id']?'selected':'' ?>><?= h($a['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">El supervisor puede ver y reasignar todos los tickets del departamento.</small>
          </div>
          <?php endif; ?>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= t('create') ?></button>
            <a href="<?= base_url('departments') ?>" class="btn btn-outline-secondary"><?= t('cancel') ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include ROOT . '/templates/footer.php'; ?>
