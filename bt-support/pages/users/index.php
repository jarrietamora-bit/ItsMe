<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin']);

$search = trim($_GET['q']     ?? '');
$f_role = $_GET['role']       ?? '';
$f_dept = (int)($_GET['dept'] ?? 0);
$page_n = max(1,(int)($_GET['p'] ?? 1));
$per_p  = 20;

// Handle quick actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['uid'] ?? 0);
    if ($action === 'deactivate' && $user_id && $user_id !== (int)$_SESSION['uid']) {
        db()->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$user_id]);
        flash('success', t('user_deactivated'));
    }
    if ($action === 'activate' && $user_id) {
        db()->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$user_id]);
        flash('success', t('user_updated'));
    }
    if ($action === 'delete' && $user_id && $user_id !== (int)$_SESSION['uid']) {
        $st_utc = db()->prepare("SELECT COUNT(*) FROM tickets WHERE created_by=?");
        $st_utc->execute([$user_id]);
        $ticket_count = (int)$st_utc->fetchColumn();
        if ($ticket_count > 0) {
            flash('error', sprintf(t('cant_delete_user_tickets') ?? "Cannot delete: user has {$ticket_count} ticket(s). Deactivate instead.", $ticket_count));
            redirect(base_url('users'));
        }
        db()->prepare("DELETE FROM users WHERE id=?")->execute([$user_id]);
        flash('success', t('user_deleted'));
    }
    redirect(base_url('users'));
}

$where = ['1=1'];
$params = [];
if ($search)  { $where[] = '(u.name LIKE ? OR u.email LIKE ?)'; $like='%'.$search.'%'; $params[]=$like; $params[]=$like; }
if ($f_role)  { $where[] = 'u.role = ?'; $params[] = $f_role; }
if ($f_dept)  { $where[] = 'EXISTS(SELECT 1 FROM department_users du WHERE du.user_id=u.id AND du.department_id=?)'; $params[]=$f_dept; }

$w = implode(' AND ', $where);

$count = db()->prepare("SELECT COUNT(*) FROM users u WHERE $w");
$count->execute($params);
$total = (int)$count->fetchColumn();

$offset = ($page_n-1)*$per_p;
$st = db()->prepare("SELECT u.*, GROUP_CONCAT(d.name SEPARATOR ', ') as depts FROM users u LEFT JOIN department_users du ON u.id=du.user_id LEFT JOIN departments d ON du.department_id=d.id WHERE $w GROUP BY u.id ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
$st->execute(array_merge($params,[$per_p,$offset]));
$users = $st->fetchAll();

$departments = db()->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$total_pages = (int)ceil($total/$per_p);

$page_title = t('users');
include ROOT . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="fw-bold mb-0"><?= t('users') ?></h4>
  <a href="<?= base_url('users/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i><?= t('new_user') ?></a>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-center">
      <div class="col-md-4">
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="<?= t('search') ?>..." value="<?= h($search) ?>">
        </div>
      </div>
      <div class="col-md-2">
        <select name="role" class="form-select form-select-sm">
          <option value=""><?= t('all') ?> <?= t('role') ?></option>
          <?php foreach (['super_admin','admin','supervisor','agent','client'] as $r): ?>
            <option value="<?= $r ?>" <?= $f_role===$r?'selected':'' ?>><?= role_label($r) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select name="dept" class="form-select form-select-sm">
          <option value=""><?= t('all') ?> <?= t('departments') ?></option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $f_dept==$d['id']?'selected':'' ?>><?= h($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-secondary btn-sm"><?= t('filter') ?></button>
        <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary btn-sm">✕</a>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 small">
      <thead class="table-light">
        <tr>
          <th><?= t('name') ?></th>
          <th><?= t('email') ?></th>
          <th><?= t('role') ?></th>
          <th><?= t('departments') ?></th>
          <th><?= t('status') ?></th>
          <th><?= t('last_login') ?></th>
          <th><?= t('actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4"><?= t('no_results') ?></td></tr>
        <?php else: foreach ($users as $u): ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                   style="width:32px;height:32px;background:var(--brand-color,#0d6efd);font-size:13px">
                <?= strtoupper(substr($u['name'],0,1)) ?>
              </div>
              <?= h($u['name']) ?>
            </div>
          </td>
          <td><?= h($u['email']) ?></td>
          <td><?= role_badge($u['role']) ?></td>
          <td><span class="text-muted"><?= h($u['depts'] ?: '—') ?></span></td>
          <td>
            <?php if ($u['status']==='active'): ?>
              <span class="badge bg-success"><?= t('active') ?></span>
            <?php elseif ($u['status']==='inactive'): ?>
              <span class="badge bg-secondary"><?= t('inactive') ?></span>
            <?php else: ?>
              <span class="badge bg-danger"><?= t('blocked') ?></span>
            <?php endif; ?>
          </td>
          <td class="text-muted"><?= $u['last_login'] ? format_datetime($u['last_login']) : '—' ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="<?= base_url('users/edit?id='.$u['id']) ?>" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-pencil"></i></a>
              <?php if ($u['id'] !== (int)$_SESSION['uid']): ?>
              <form method="post" class="d-inline">
                <?= csrf_field() ?><input type="hidden" name="uid" value="<?= $u['id'] ?>">
                <?php if ($u['status']==='active'): ?>
                  <input type="hidden" name="action" value="deactivate">
                  <button class="btn btn-sm btn-outline-warning py-0" title="<?= t('inactive') ?>"><i class="bi bi-pause"></i></button>
                <?php else: ?>
                  <input type="hidden" name="action" value="activate">
                  <button class="btn btn-sm btn-outline-success py-0" title="<?= t('active') ?>"><i class="bi bi-play"></i></button>
                <?php endif; ?>
              </form>
              <form method="post" class="d-inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
                <?= csrf_field() ?><input type="hidden" name="uid" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="delete">
                <button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($total_pages > 1): ?>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center">
    <small class="text-muted"><?= $total ?> <?= t('results') ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for ($i=1;$i<=$total_pages;$i++): ?>
        <li class="page-item <?= $i===$page_n?'active':'' ?>">
          <a class="page-link" href="?<?= http_build_query(array_filter(['q'=>$search,'role'=>$f_role,'dept'=>$f_dept,'p'=>$i])) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
<?php include ROOT . '/templates/footer.php'; ?>
