<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $did    = (int)($_POST['did'] ?? 0);
    if ($action === 'delete' && $did) {
        $st_tc = db()->prepare("SELECT COUNT(*) FROM tickets WHERE department_id=? AND status NOT IN('resolved','closed')");
        $st_tc->execute([$did]);
        $ticket_count = (int)$st_tc->fetchColumn();
        if ($ticket_count > 0) {
            flash('error', "No se puede eliminar: el departamento tiene {$ticket_count} ticket(s) activo(s).");
            redirect(base_url('departments'));
        }
        db()->prepare("DELETE FROM departments WHERE id=?")->execute([$did]);
        flash('success', t('dept_deleted'));
    }
    if ($action === 'toggle' && $did) {
        db()->prepare("UPDATE departments SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$did]);
        flash('success', t('dept_updated'));
    }
    redirect(base_url('departments'));
}

$depts = db()->query("SELECT d.*, COUNT(DISTINCT du.user_id) as member_count, COUNT(DISTINCT t.id) as ticket_count FROM departments d LEFT JOIN department_users du ON d.id=du.department_id LEFT JOIN tickets t ON d.id=t.department_id AND t.status NOT IN('resolved','closed') GROUP BY d.id ORDER BY d.name")->fetchAll();

$page_title = t('departments');
include ROOT . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="fw-bold mb-0"><?= t('departments') ?></h4>
  <a href="<?= base_url('departments/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus me-1"></i><?= t('new_department') ?></a>
</div>
<div class="row g-3">
  <?php foreach ($depts as $d): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:<?= h($d['color']) ?>22;border-top:4px solid <?= h($d['color']) ?>">
        <div class="d-flex align-items-center gap-2">
          <span class="rounded-circle d-inline-block" style="width:12px;height:12px;background:<?= h($d['color']) ?>"></span>
          <strong><?= h($d['name']) ?></strong>
        </div>
        <?php if ($d['status']==='active'): ?>
          <span class="badge bg-success"><?= t('active') ?></span>
        <?php else: ?>
          <span class="badge bg-secondary"><?= t('inactive') ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if ($d['description']): ?>
          <p class="text-muted small mb-2"><?= h($d['description']) ?></p>
        <?php endif; ?>
        <?php if ($d['email']): ?>
          <div class="small mb-1"><i class="bi bi-envelope me-1 text-muted"></i><?= h($d['email']) ?></div>
        <?php endif; ?>
        <div class="d-flex gap-3 mt-2">
          <div class="text-center">
            <div class="fs-5 fw-bold"><?= $d['member_count'] ?></div>
            <div class="text-muted small"><?= t('dept_members') ?></div>
          </div>
          <div class="text-center">
            <div class="fs-5 fw-bold"><?= $d['ticket_count'] ?></div>
            <div class="text-muted small"><?= t('active_tickets') ?></div>
          </div>
        </div>
      </div>
      <div class="card-footer bg-white d-flex gap-2">
        <a href="<?= base_url('departments/edit?id='.$d['id']) ?>" class="btn btn-sm btn-outline-primary flex-fill">
          <i class="bi bi-pencil me-1"></i><?= t('edit') ?>
        </a>
        <form method="post" class="d-inline">
          <?= csrf_field() ?><input type="hidden" name="did" value="<?= $d['id'] ?>"><input type="hidden" name="action" value="toggle">
          <button class="btn btn-sm btn-outline-secondary" title="<?= $d['status']==='active'?t('inactive'):t('active') ?>">
            <i class="bi bi-<?= $d['status']==='active'?'pause':'play' ?>"></i>
          </button>
        </form>
        <form method="post" class="d-inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
          <?= csrf_field() ?><input type="hidden" name="did" value="<?= $d['id'] ?>"><input type="hidden" name="action" value="delete">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($depts)): ?>
  <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><?= t('no_results') ?></div></div></div>
  <?php endif; ?>
</div>
<?php include ROOT . '/templates/footer.php'; ?>
