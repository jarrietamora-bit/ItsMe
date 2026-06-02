<?php
if (!defined('BTSUPPORT')) exit;
require_login();

$user = current_user();
$role = current_role();
$uid  = $user['id'];
$lang = in_array(current_lang(), ['es','en']) ? current_lang() : 'es';

// Stats based on role
if (in_array($role, ['super_admin','admin'])) {
    // Full system stats
    $stats = [
        'open'        => db()->query("SELECT COUNT(*) FROM tickets WHERE status='open'")->fetchColumn(),
        'in_progress' => db()->query("SELECT COUNT(*) FROM tickets WHERE status='in_progress'")->fetchColumn(),
        'resolved'    => db()->query("SELECT COUNT(*) FROM tickets WHERE status='resolved' AND DATE(resolved_at)=CURDATE()")->fetchColumn(),
        'overdue'     => db()->query("SELECT COUNT(*) FROM tickets WHERE sla_breached=1 AND status NOT IN('resolved','closed')")->fetchColumn(),
        'unassigned'  => db()->query("SELECT COUNT(*) FROM tickets WHERE assigned_to IS NULL AND status NOT IN('resolved','closed')")->fetchColumn(),
        'total'       => db()->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    ];

    // Tickets by status for chart
    $by_status = db()->query("SELECT status, COUNT(*) as cnt FROM tickets GROUP BY status")->fetchAll();

    // Agent performance (top 5)
    $agents = db()->query("
        SELECT u.name, COUNT(t.id) as total,
               SUM(t.status='resolved') as resolved,
               AVG(TIMESTAMPDIFF(MINUTE,t.created_at,t.first_response_at)) as avg_response
        FROM tickets t JOIN users u ON t.assigned_to=u.id
        WHERE t.assigned_to IS NOT NULL
        GROUP BY t.assigned_to ORDER BY total DESC LIMIT 5")->fetchAll();

    // Recent tickets
    $recent = db()->query("
        SELECT t.*, u.name as client_name, p.name_{$lang} as priority_name, p.color as priority_color,
               d.name as dept_name, a.name as agent_name
        FROM tickets t
        JOIN users u ON t.created_by=u.id
        LEFT JOIN priorities p ON t.priority_id=p.id
        LEFT JOIN departments d ON t.department_id=d.id
        LEFT JOIN users a ON t.assigned_to=a.id
        ORDER BY t.updated_at DESC, t.created_at DESC LIMIT 10")->fetchAll();

} elseif ($role === 'supervisor') {
    // Supervisor: only their departments
    $dept_ids = array_column(get_user_departments($uid), 'id');
    $dids_str = $dept_ids ? implode(',', array_map('intval', $dept_ids)) : '0';

    $in_placeholders = implode(',', array_fill(0, count($dept_ids), '?'));
    $dids_params = $dept_ids ?: [0];
    $in_placeholders = implode(',', array_fill(0, count($dids_params), '?'));

    $st_so = db()->prepare("SELECT COUNT(*) FROM tickets WHERE status='open' AND department_id IN({$in_placeholders})");
    $st_so->execute($dids_params);
    $st_si = db()->prepare("SELECT COUNT(*) FROM tickets WHERE status='in_progress' AND department_id IN({$in_placeholders})");
    $st_si->execute($dids_params);
    $st_sr = db()->prepare("SELECT COUNT(*) FROM tickets WHERE status='resolved' AND DATE(resolved_at)=CURDATE() AND department_id IN({$in_placeholders})");
    $st_sr->execute($dids_params);
    $st_sov = db()->prepare("SELECT COUNT(*) FROM tickets WHERE sla_breached=1 AND status NOT IN('resolved','closed') AND department_id IN({$in_placeholders})");
    $st_sov->execute($dids_params);
    $st_sun = db()->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to IS NULL AND status NOT IN('resolved','closed') AND department_id IN({$in_placeholders})");
    $st_sun->execute($dids_params);
    $st_st = db()->prepare("SELECT COUNT(*) FROM tickets WHERE department_id IN({$in_placeholders})");
    $st_st->execute($dids_params);
    $stats = [
        'open'        => (int)$st_so->fetchColumn(),
        'in_progress' => (int)$st_si->fetchColumn(),
        'resolved'    => (int)$st_sr->fetchColumn(),
        'overdue'     => (int)$st_sov->fetchColumn(),
        'unassigned'  => (int)$st_sun->fetchColumn(),
        'total'       => (int)$st_st->fetchColumn(),
    ];

    $st_recent = db()->prepare("
        SELECT t.*, u.name as client_name, p.name_{$lang} as priority_name, p.color as priority_color,
               d.name as dept_name, a.name as agent_name
        FROM tickets t JOIN users u ON t.created_by=u.id
        LEFT JOIN priorities p ON t.priority_id=p.id
        LEFT JOIN departments d ON t.department_id=d.id
        LEFT JOIN users a ON t.assigned_to=a.id
        WHERE t.department_id IN({$in_placeholders})
        ORDER BY t.updated_at DESC LIMIT 10");
    $st_recent->execute($dids_params);
    $recent = $st_recent->fetchAll();

    // Workload per agent in department
    $st_agents = db()->prepare("
        SELECT u.name, COUNT(t.id) as total, SUM(t.status='resolved') as resolved
        FROM tickets t JOIN users u ON t.assigned_to=u.id
        JOIN department_users du ON du.user_id=u.id
        WHERE du.department_id IN({$in_placeholders}) AND t.status NOT IN('resolved','closed')
        GROUP BY t.assigned_to ORDER BY total DESC LIMIT 8");
    $st_agents->execute($dids_params);
    $agents = $st_agents->fetchAll();
    $by_status = [];

} elseif ($role === 'agent') {
    $st_open = db()->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to=? AND status='open'");
    $st_open->execute([$uid]);
    $st_inp = db()->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to=? AND status='in_progress'");
    $st_inp->execute([$uid]);
    $st_res = db()->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to=? AND status='resolved' AND DATE(resolved_at)=CURDATE()");
    $st_res->execute([$uid]);
    $st_ov = db()->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to=? AND sla_breached=1 AND status NOT IN('resolved','closed')");
    $st_ov->execute([$uid]);
    $st_tot = db()->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to=?");
    $st_tot->execute([$uid]);
    $stats = [
        'open'        => (int)$st_open->fetchColumn(),
        'in_progress' => (int)$st_inp->fetchColumn(),
        'resolved'    => (int)$st_res->fetchColumn(),
        'overdue'     => (int)$st_ov->fetchColumn(),
        'unassigned'  => 0,
        'total'       => (int)$st_tot->fetchColumn(),
    ];
    $st_rec = db()->prepare("
        SELECT t.*, u.name as client_name, p.name_{$lang} as priority_name, p.color as priority_color, d.name as dept_name
        FROM tickets t JOIN users u ON t.created_by=u.id
        LEFT JOIN priorities p ON t.priority_id=p.id
        LEFT JOIN departments d ON t.department_id=d.id
        WHERE t.assigned_to=? ORDER BY t.updated_at DESC LIMIT 10");
    $st_rec->execute([$uid]);
    $recent = $st_rec->fetchAll();
    $agents = $by_status = [];

} else {
    // Client
    $st_co = db()->prepare("SELECT COUNT(*) FROM tickets WHERE created_by=? AND status='open'");
    $st_co->execute([$uid]);
    $st_ci = db()->prepare("SELECT COUNT(*) FROM tickets WHERE created_by=? AND status='in_progress'");
    $st_ci->execute([$uid]);
    $st_cr = db()->prepare("SELECT COUNT(*) FROM tickets WHERE created_by=? AND status='resolved'");
    $st_cr->execute([$uid]);
    $st_ct = db()->prepare("SELECT COUNT(*) FROM tickets WHERE created_by=?");
    $st_ct->execute([$uid]);
    $stats = [
        'open'        => (int)$st_co->fetchColumn(),
        'in_progress' => (int)$st_ci->fetchColumn(),
        'resolved'    => (int)$st_cr->fetchColumn(),
        'overdue'     => 0,
        'unassigned'  => 0,
        'total'       => (int)$st_ct->fetchColumn(),
    ];
    $st_crec = db()->prepare("
        SELECT t.*, p.name_{$lang} as priority_name, p.color as priority_color, d.name as dept_name, a.name as agent_name
        FROM tickets t
        LEFT JOIN priorities p ON t.priority_id=p.id
        LEFT JOIN departments d ON t.department_id=d.id
        LEFT JOIN users a ON t.assigned_to=a.id
        WHERE t.created_by=? ORDER BY t.updated_at DESC LIMIT 10");
    $st_crec->execute([$uid]);
    $recent = $st_crec->fetchAll();
    $agents = $by_status = [];
}

$page_title = t('dashboard');
include ROOT . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="mb-0 fw-bold"><?= t('dashboard') ?></h4>
    <small class="text-muted"><?= t('welcome') ?>, <?= h($user['name']) ?>! &nbsp;<?= role_badge($role) ?></small>
  </div>
  <?php if ($role !== 'client'): ?>
  <a href="<?= base_url('tickets/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle me-1"></i><?= t('new_ticket') ?>
  </a>
  <?php endif; ?>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['open',       'bi-envelope-open',    'primary', t('open_tickets')],
    ['in_progress','bi-gear-wide-connected','warning', t('in_progress_tickets')],
    ['resolved',   'bi-check-circle',     'success', t('resolved_tickets')],
    ['overdue',    'bi-alarm',            'danger',  t('overdue_tickets')],
  ];
  if (is_admin()) {
      $cards[] = ['unassigned','bi-person-x','secondary', t('unassigned_tickets')];
      $cards[] = ['total',     'bi-collection','info',    t('total')];
  }
  foreach ($cards as [$key,$icon,$color,$label]):
  ?>
  <div class="col-6 col-md-4 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 bg-<?= $color ?> bg-opacity-10 text-<?= $color ?>" style="width:48px;height:48px">
          <i class="bi <?= $icon ?> fs-5"></i>
        </div>
        <div>
          <div class="fs-4 fw-bold lh-1"><?= number_format((int)($stats[$key] ?? 0)) ?></div>
          <div class="text-muted small"><?= $label ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <!-- Recent tickets -->
  <div class="col-lg-<?= (is_admin() && $by_status) ? '8' : '12' ?>">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong><?= $role === 'client' ? t('my_tickets') : t('recent_activity') ?></strong>
        <a href="<?= base_url('tickets') ?>" class="btn btn-sm btn-outline-primary"><?= t('view_all_tickets') ?></a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th><?= t('ticket_number') ?></th>
              <th><?= t('subject') ?></th>
              <th><?= t('status') ?></th>
              <th><?= t('priority') ?></th>
              <?php if ($role !== 'client'): ?><th><?= t('created_by') ?></th><?php endif; ?>
              <?php if (is_supervisor()): ?><th><?= t('assigned_to') ?></th><?php endif; ?>
              <th><?= t('updated_at') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent)): ?>
              <tr><td colspan="10" class="text-center text-muted py-4"><?= t('no_results') ?></td></tr>
            <?php else: foreach ($recent as $t_row): ?>
            <tr class="<?= $t_row['sla_breached'] ? 'table-danger' : '' ?>">
              <td><a href="<?= base_url('tickets/view?id='.$t_row['id']) ?>" class="fw-bold text-decoration-none"><?= h($t_row['ticket_number']) ?></a></td>
              <td class="text-truncate" style="max-width:200px"><?= h($t_row['subject']) ?></td>
              <td><?= status_badge($t_row['status']) ?></td>
              <td><?= $t_row['priority_name'] ? priority_badge($t_row['priority_name'], $t_row['priority_color']) : '—' ?></td>
              <?php if ($role !== 'client'): ?><td><?= h($t_row['client_name'] ?? '—') ?></td><?php endif; ?>
              <?php if (is_supervisor()): ?><td><?= h($t_row['agent_name'] ?? '—') ?></td><?php endif; ?>
              <td class="text-muted"><?= time_ago($t_row['updated_at'] ?? $t_row['created_at']) ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Charts / Agents column -->
  <?php if (is_admin() && !empty($by_status)): ?>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white"><strong><?= t('quick_stats') ?></strong></div>
      <div class="card-body">
        <canvas id="statusChart" height="200"></canvas>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Agent workload (supervisor/admin) -->
  <?php if (!empty($agents) && is_supervisor()): ?>
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><strong><?= t('workload') ?></strong></div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
          <thead class="table-light">
            <tr><th><?= t('name') ?></th><th><?= t('active_tickets') ?></th><th><?= t('tickets_resolved') ?></th></tr>
          </thead>
          <tbody>
            <?php foreach ($agents as $ag): ?>
            <tr>
              <td><?= h($ag['name']) ?></td>
              <td><span class="badge bg-primary"><?= $ag['total'] ?></span></td>
              <td><span class="badge bg-success"><?= $ag['resolved'] ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if (is_admin() && !empty($by_status)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('statusChart');
const colors = {'open':'#0d6efd','in_progress':'#ffc107','waiting':'#6c757d','resolved':'#198754','closed':'#343a40'};
const labels = <?= json_encode(array_column($by_status,'status')) ?>;
const data   = <?= json_encode(array_column($by_status,'cnt')) ?>;
new Chart(ctx,{type:'doughnut',data:{labels:labels,datasets:[{data:data,backgroundColor:labels.map(s=>colors[s]||'#aaa'),borderWidth:0}]},options:{plugins:{legend:{position:'bottom'}},cutout:'65%'}});
</script>
<?php endif; ?>

<?php include ROOT . '/templates/footer.php'; ?>
