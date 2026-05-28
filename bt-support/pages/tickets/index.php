<?php
if (!defined('BTSUPPORT')) exit;
require_login();

$user = current_user();
$role = current_role();
$uid  = $user['id'];

// Filters
$f_status   = $_GET['status']   ?? '';
$f_priority = $_GET['priority'] ?? '';
$f_dept     = $_GET['dept']     ?? '';
$f_search   = trim($_GET['q']   ?? '');
$f_assigned = $_GET['assigned'] ?? '';
$page_num   = max(1,(int)($_GET['p'] ?? 1));
$per_page   = (int)(setting('tickets_per_page') ?: 25);

// Build WHERE clause based on role
$where = ['1=1'];
$params = [];

if ($role === 'client') {
    $where[] = 't.created_by = ?';
    $params[] = $uid;
} elseif ($role === 'agent') {
    $where[] = 't.assigned_to = ?';
    $params[] = $uid;
} elseif ($role === 'supervisor') {
    $dept_ids = array_column(get_user_departments($uid),'id');
    if ($dept_ids) {
        $where[] = 't.department_id IN(' . implode(',',array_map('intval',$dept_ids)) . ')';
    } else {
        $where[] = '0=1';
    }
}

if ($f_status)   { $where[] = 't.status = ?';      $params[] = $f_status; }
if ($f_priority) { $where[] = 't.priority_id = ?'; $params[] = $f_priority; }
if ($f_dept && is_admin()) { $where[] = 't.department_id = ?'; $params[] = $f_dept; }
if ($f_assigned && is_supervisor()) { $where[] = 't.assigned_to = ?'; $params[] = $f_assigned; }
if ($f_search)   {
    $where[] = '(t.ticket_number LIKE ? OR t.subject LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $like = '%' . $f_search . '%';
    array_push($params, $like, $like, $like, $like);
}

$where_sql = implode(' AND ', $where);

// Bulk action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && is_agent()) {
    $ids  = array_filter(array_map('intval', $_POST['ids'] ?? []));
    $bulk = $_POST['bulk_action'] ?? '';
    if ($ids && in_array($bulk, ['close','resolve','open','in_progress','waiting'], true)) {
        $new_status = $bulk === 'close' ? 'closed' : ($bulk === 'resolve' ? 'resolved' : $bulk);
        $in = implode(',', $ids);
        $resolved_at = in_array($new_status,['resolved','closed']) ? ", resolved_at = NOW()" : '';
        db()->exec("UPDATE tickets SET status = '{$new_status}'{$resolved_at} WHERE id IN({$in})");
        flash('success', t('ticket_updated'));
    }
    if ($ids && $bulk === 'assign_me' && is_agent()) {
        $in = implode(',', $ids);
        db()->prepare("UPDATE tickets SET assigned_to = ? WHERE id IN({$in})")->execute([$uid]);
        flash('success', t('ticket_updated'));
    }
    redirect(base_url('tickets') . '?' . http_build_query(array_filter(['status'=>$f_status,'priority'=>$f_priority,'q'=>$f_search])));
}

// Count & fetch
$count_sql = "SELECT COUNT(*) FROM tickets t JOIN users u ON t.created_by=u.id WHERE {$where_sql}";
$st = db()->prepare($count_sql);
$st->execute($params);
$total = (int)$st->fetchColumn();

$pag = paginate($total, $per_page, $page_num);

$sql = "SELECT t.*, u.name as client_name, u.email as client_email,
               p.name_es as priority_name, p.color as priority_color,
               d.name as dept_name, a.name as agent_name
        FROM tickets t
        JOIN users u ON t.created_by=u.id
        LEFT JOIN priorities p ON t.priority_id=p.id
        LEFT JOIN departments d ON t.department_id=d.id
        LEFT JOIN users a ON t.assigned_to=a.id
        WHERE {$where_sql}
        ORDER BY t.sla_breached DESC, t.updated_at DESC
        LIMIT ? OFFSET ?";
$p2 = array_merge($params, [$per_page, $pag['offset']]);
$st = db()->prepare($sql);
$st->execute($p2);
$tickets = $st->fetchAll();

// Filter options
$priorities = db()->query("SELECT * FROM priorities ORDER BY level")->fetchAll();
$departments = is_admin() ? db()->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll() : [];

$page_title = t('tickets');
include ROOT . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="fw-bold mb-0"><?= t('tickets') ?></h4>
  <a href="<?= base_url('tickets/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle me-1"></i><?= t('new_ticket') ?>
  </a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-center">
      <div class="col-md-4">
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="<?= t('search') ?>..." value="<?= h($f_search) ?>">
        </div>
      </div>
      <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value=""><?= t('all') ?> <?= t('status') ?></option>
          <?php foreach (['open','in_progress','waiting','resolved','closed'] as $s): ?>
            <option value="<?= $s ?>" <?= $f_status===$s?'selected':'' ?>><?= t('status_'.$s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="priority" class="form-select form-select-sm">
          <option value=""><?= t('all') ?> <?= t('priority') ?></option>
          <?php foreach ($priorities as $pr): ?>
            <option value="<?= $pr['id'] ?>" <?= $f_priority==$pr['id']?'selected':'' ?>><?= h($pr['name_es']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (is_admin() && $departments): ?>
      <div class="col-md-2">
        <select name="dept" class="form-select form-select-sm">
          <option value=""><?= t('all') ?> <?= t('departments') ?></option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $f_dept==$d['id']?'selected':'' ?>><?= h($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-auto">
        <button type="submit" class="btn btn-secondary btn-sm"><?= t('filter') ?></button>
        <a href="<?= base_url('tickets') ?>" class="btn btn-outline-secondary btn-sm">✕</a>
      </div>
    </form>
  </div>
</div>

<!-- Ticket list -->
<div class="card border-0 shadow-sm">
  <form method="post" id="bulkForm">
    <?= csrf_field() ?>
    <?php if (is_agent()): ?>
    <div class="card-header bg-white d-flex gap-2 align-items-center py-2">
      <select name="bulk_action" class="form-select form-select-sm" style="max-width:180px">
        <option value=""><?= t('bulk_action') ?></option>
        <?php if (is_agent()): ?>
        <option value="assign_me">Asignarme</option>
        <option value="in_progress"><?= t('status_in_progress') ?></option>
        <option value="waiting"><?= t('status_waiting') ?></option>
        <option value="resolve"><?= t('status_resolved') ?></option>
        <option value="close"><?= t('status_closed') ?></option>
        <?php endif; ?>
      </select>
      <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('¿Aplicar acción?')"><?= t('submit') ?></button>
      <span class="text-muted small ms-2"><?= $total ?> <?= t('results') ?></span>
    </div>
    <?php endif; ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <?php if (is_agent()): ?>
            <th style="width:32px"><input type="checkbox" id="selectAll" class="form-check-input"></th>
            <?php endif; ?>
            <th><?= t('ticket_number') ?></th>
            <th><?= t('subject') ?></th>
            <th><?= t('status') ?></th>
            <th><?= t('priority') ?></th>
            <?php if ($role !== 'client'): ?><th><?= t('created_by') ?></th><?php endif; ?>
            <?php if (is_supervisor()): ?><th><?= t('assigned_to') ?></th><th><?= t('department') ?></th><?php endif; ?>
            <?php if (is_admin()): ?><th><?= t('assigned_to') ?></th><th><?= t('department') ?></th><?php endif; ?>
            <th><?= t('sla_due') ?></th>
            <th><?= t('updated_at') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tickets)): ?>
          <tr><td colspan="12" class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i><?= t('no_results') ?>
          </td></tr>
          <?php else: foreach ($tickets as $tk): ?>
          <tr class="<?= $tk['sla_breached'] ? 'table-danger' : '' ?>">
            <?php if (is_agent()): ?>
            <td><input type="checkbox" name="ids[]" value="<?= $tk['id'] ?>" class="form-check-input ticket-cb"></td>
            <?php endif; ?>
            <td>
              <a href="<?= base_url('tickets/view?id='.$tk['id']) ?>" class="fw-semibold text-decoration-none">
                <?= h($tk['ticket_number']) ?>
              </a>
            </td>
            <td style="max-width:220px">
              <div class="text-truncate"><?= h($tk['subject']) ?></div>
              <?php if ($tk['sla_breached']): ?>
                <span class="badge bg-danger" style="font-size:10px"><i class="bi bi-alarm me-1"></i><?= t('sla_breached') ?></span>
              <?php endif; ?>
            </td>
            <td><?= status_badge($tk['status']) ?></td>
            <td><?= $tk['priority_name'] ? priority_badge($tk['priority_name'], $tk['priority_color']) : '<span class="text-muted">—</span>' ?></td>
            <?php if ($role !== 'client'): ?><td><?= h($tk['client_name']) ?></td><?php endif; ?>
            <?php if (is_supervisor() || is_admin()): ?>
              <td><?= h($tk['agent_name'] ?? '—') ?></td>
              <td><?= h($tk['dept_name'] ?? '—') ?></td>
            <?php endif; ?>
            <td class="text-muted small"><?= $tk['sla_due_at'] ? date('d/m H:i', strtotime($tk['sla_due_at'])) : '—' ?></td>
            <td class="text-muted small"><?= time_ago($tk['updated_at'] ?? $tk['created_at']) ?></td>
            <td>
              <a href="<?= base_url('tickets/view?id='.$tk['id']) ?>" class="btn btn-sm btn-outline-primary btn-sm py-0">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </form>

  <!-- Pagination -->
  <?php if ($pag['total_pages'] > 1): ?>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center">
    <small class="text-muted"><?= t('showing') ?> <?= $pag['offset']+1 ?>–<?= min($pag['offset']+$per_page,$total) ?> <?= t('of') ?> <?= $total ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for ($i=1;$i<=$pag['total_pages'];$i++): ?>
        <li class="page-item <?= $i===$page_num?'active':'' ?>">
          <a class="page-link" href="?<?= http_build_query(array_filter(['status'=>$f_status,'priority'=>$f_priority,'q'=>$f_search,'p'=>$i])) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>

<script>
document.getElementById('selectAll')?.addEventListener('change',function(){
  document.querySelectorAll('.ticket-cb').forEach(cb=>cb.checked=this.checked);
});
</script>
<?php include ROOT . '/templates/footer.php'; ?>
