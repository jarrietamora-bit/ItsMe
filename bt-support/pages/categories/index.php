<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $cid    = (int)($_POST['cid'] ?? 0);
    if ($action === 'save') {
        $name   = trim($_POST['name']   ?? '');
        $parent = (int)($_POST['parent_id'] ?? 0) ?: null;
        $dept   = (int)($_POST['dept_id']   ?? 0) ?: null;
        if ($name) {
            if ($cid) {
                db()->prepare("UPDATE categories SET name=?,parent_id=?,department_id=? WHERE id=?")->execute([$name,$parent,$dept,$cid]);
            } else {
                db()->prepare("INSERT INTO categories (name,parent_id,department_id) VALUES (?,?,?)")->execute([$name,$parent,$dept]);
            }
            flash('success', t('settings_saved'));
        }
    }
    if ($action === 'delete' && $cid) {
        db()->prepare("DELETE FROM categories WHERE id=?")->execute([$cid]);
        flash('success','Categoría eliminada.');
    }
    if ($action === 'toggle' && $cid) {
        db()->prepare("UPDATE categories SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$cid]);
    }
    redirect(base_url('categories'));
}

$cats = db()->query("SELECT c.*, p.name as parent_name, d.name as dept_name FROM categories c LEFT JOIN categories p ON c.parent_id=p.id LEFT JOIN departments d ON c.department_id=d.id ORDER BY COALESCE(c.parent_id,c.id), c.id")->fetchAll();
$departments = db()->query("SELECT id,name FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$parents = db()->query("SELECT id,name FROM categories WHERE parent_id IS NULL AND status='active' ORDER BY name")->fetchAll();
$edit_id = (int)($_GET['edit'] ?? 0);
$edit_row = null;
if ($edit_id) { $s=db()->prepare("SELECT * FROM categories WHERE id=?"); $s->execute([$edit_id]); $edit_row=$s->fetch(); }

$page_title = t('categories');
include ROOT . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><?= t('categories') ?></h4>
</div>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><strong><?= $edit_row ? t('edit') : t('new_category') ?></strong></div>
      <div class="card-body p-4">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="cid" value="<?= $edit_row['id']??0 ?>">
          <div class="mb-3">
            <label class="form-label"><?= t('name') ?> *</label>
            <input type="text" name="name" class="form-control" value="<?= h($edit_row['name']??'') ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= t('parent_category') ?> <small class="text-muted">(opcional)</small></label>
            <select name="parent_id" class="form-select">
              <option value=""><?= t('none') ?> (categoría raíz)</option>
              <?php foreach ($parents as $p): ?>
                <?php if (!$edit_row || $p['id'] != $edit_row['id']): ?>
                <option value="<?= $p['id'] ?>" <?= ($edit_row['parent_id']??'')==$p['id']?'selected':'' ?>><?= h($p['name']) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= t('department') ?> <small class="text-muted">(opcional)</small></label>
            <select name="dept_id" class="form-select">
              <option value="">Todos</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= ($edit_row['department_id']??'')==$d['id']?'selected':'' ?>><?= h($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><?= $edit_row ? t('save') : t('create') ?></button>
            <?php if ($edit_row): ?><a href="<?= base_url('categories') ?>" class="btn btn-outline-secondary btn-sm"><?= t('cancel') ?></a><?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light"><tr><th><?= t('name') ?></th><th><?= t('parent_category') ?></th><th><?= t('department') ?></th><th><?= t('status') ?></th><th></th></tr></thead>
          <tbody>
            <?php if (empty($cats)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4"><?= t('no_results') ?></td></tr>
            <?php else: foreach ($cats as $c): ?>
            <tr>
              <td><?= $c['parent_id'] ? '<span class="text-muted me-2">↳</span>' : '' ?><?= h($c['name']) ?></td>
              <td><?= h($c['parent_name']??'—') ?></td>
              <td><?= h($c['dept_name']??'General') ?></td>
              <td><?= $c['status']==='active' ? '<span class="badge bg-success">'.t('active').'</span>' : '<span class="badge bg-secondary">'.t('inactive').'</span>' ?></td>
              <td>
                <a href="?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="cid" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-outline-warning py-0"><i class="bi bi-toggle-<?= $c['status']==='active'?'on':'off' ?>"></i></button></form>
                <form method="post" class="d-inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="cid" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button></form>
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
