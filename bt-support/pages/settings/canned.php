<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action  = $_POST['action'] ?? '';
    $id      = (int)($_POST['id'] ?? 0);
    $uid     = (int)$_SESSION['uid'];
    if ($action === 'save') {
        $name    = trim($_POST['name']    ?? '');
        $content = trim($_POST['content'] ?? '');
        $dept    = (int)($_POST['dept_id'] ?? 0) ?: null;
        if ($name && $content) {
            if ($id) {
                db()->prepare("UPDATE canned_responses SET name=?,content=?,department_id=? WHERE id=?")->execute([$name,$content,$dept,$id]);
            } else {
                db()->prepare("INSERT INTO canned_responses (name,content,department_id,created_by) VALUES (?,?,?,?)")->execute([$name,$content,$dept,$uid]);
            }
            flash('success', t('settings_saved'));
        }
    }
    if ($action === 'delete' && $id) {
        db()->prepare("DELETE FROM canned_responses WHERE id=?")->execute([$id]);
        flash('success', t('response_deleted'));
    }
    redirect(base_url('settings/canned'));
}

$canned      = db()->query("SELECT cr.*, d.name as dept_name FROM canned_responses cr LEFT JOIN departments d ON cr.department_id=d.id ORDER BY cr.name")->fetchAll();
$departments = db()->query("SELECT id,name FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$edit_id     = (int)($_GET['edit'] ?? 0);
$edit_row    = null;
if ($edit_id) {
    $st = db()->prepare("SELECT * FROM canned_responses WHERE id=?"); $st->execute([$edit_id]); $edit_row = $st->fetch();
}

$page_title = t('canned_responses');
include ROOT . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><?= t('canned_responses') ?></h4>
</div>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><strong><?= $edit_row ? t('edit') : t('new_canned') ?></strong></div>
      <div class="card-body p-4">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit_row['id']??0 ?>">
          <div class="mb-3">
            <label class="form-label"><?= t('name') ?> *</label>
            <input type="text" name="name" class="form-control" value="<?= h($edit_row['name']??'') ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= t('content_label') ?></label>
            <textarea name="content" class="form-control" rows="6" required><?= h($edit_row['content']??'') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= t('department') ?> <small class="text-muted"><?= t('optional_dept_hint') ?></small></label>
            <select name="dept_id" class="form-select">
              <option value=""><?= t('all_departments') ?></option>
              <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= ($edit_row['department_id']??'')==$d['id']?'selected':'' ?>><?= h($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><?= $edit_row ? t('save') : t('create') ?></button>
            <?php if ($edit_row): ?><a href="<?= base_url('settings/canned') ?>" class="btn btn-outline-secondary btn-sm"><?= t('cancel') ?></a><?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light"><tr><th><?= t('name') ?></th><th><?= t('department') ?></th><th><?= t('actions') ?></th></tr></thead>
          <tbody>
            <?php if (empty($canned)): ?>
            <tr><td colspan="3" class="text-center text-muted py-4"><?= t('no_results') ?></td></tr>
            <?php else: foreach ($canned as $cr): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= h($cr['name']) ?></div>
                <div class="text-muted text-truncate" style="max-width:280px"><?= h(substr($cr['content'],0,80)) ?>...</div>
              </td>
              <td><?= h($cr['dept_name'] ?: t('all_departments')) ?></td>
              <td>
                <a href="?edit=<?= $cr['id'] ?>" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('<?= t('confirm_delete_short') ?>')"  >
                  <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $cr['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include ROOT . '/templates/footer.php'; ?>
