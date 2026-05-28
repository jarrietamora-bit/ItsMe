<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin','supervisor']);

$user  = current_user();
$role  = current_role();
$uid   = $user['id'];
$from  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']   ?? '') ? $_GET['to']   : date('Y-m-d');
$dept  = (int)($_GET['dept'] ?? 0);

// Build dept filter
$dept_filter = '';
$dept_params = [];
if ($role === 'supervisor') {
    $dept_ids = array_column(get_user_departments($uid),'id');
    if ($dept_ids) $dept_filter = "AND t.department_id IN(" . implode(',',array_map('intval',$dept_ids)) . ")";
    else $dept_filter = "AND 0=1";
} elseif ($dept) {
    $dept_filter = "AND t.department_id = {$dept}";
}

// Overview stats
$stats = db()->query("SELECT
  COUNT(*) as total,
  SUM(status='open') as open_count,
  SUM(status='in_progress') as in_progress,
  SUM(status='resolved') as resolved,
  SUM(status='closed') as closed,
  SUM(sla_breached=1) as sla_breached,
  AVG(CASE WHEN first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,created_at,first_response_at) END) as avg_first_resp,
  AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,created_at,resolved_at) END) as avg_resolution
  FROM tickets t WHERE DATE(created_at) BETWEEN '{$from}' AND '{$to}' {$dept_filter}")->fetch();

// CSAT
$csat = db()->query("SELECT AVG(r.rating) as avg_rating, COUNT(*) as total_ratings FROM ratings r JOIN tickets t ON r.ticket_id=t.id WHERE DATE(t.created_at) BETWEEN '{$from}' AND '{$to}' {$dept_filter}")->fetch();

// By agent
$by_agent = db()->query("SELECT u.name, COUNT(t.id) as total, SUM(t.status='resolved') as resolved, SUM(t.status='closed') as closed, SUM(t.sla_breached) as breached, AVG(TIMESTAMPDIFF(MINUTE,t.created_at,t.first_response_at)) as avg_resp FROM tickets t JOIN users u ON t.assigned_to=u.id WHERE t.assigned_to IS NOT NULL AND DATE(t.created_at) BETWEEN '{$from}' AND '{$to}' {$dept_filter} GROUP BY t.assigned_to ORDER BY total DESC")->fetchAll();

// By department
$by_dept = db()->query("SELECT d.name, d.color, COUNT(t.id) as total, SUM(t.status IN('resolved','closed')) as resolved FROM tickets t JOIN departments d ON t.department_id=d.id WHERE DATE(t.created_at) BETWEEN '{$from}' AND '{$to}' {$dept_filter} GROUP BY t.department_id ORDER BY total DESC")->fetchAll();

// By category
$by_cat = db()->query("SELECT COALESCE(c.name,'Sin categoría') as name, COUNT(*) as total FROM tickets t LEFT JOIN categories c ON t.category_id=c.id WHERE DATE(t.created_at) BETWEEN '{$from}' AND '{$to}' {$dept_filter} GROUP BY t.category_id ORDER BY total DESC LIMIT 10")->fetchAll();

// Volume by day
$by_day = db()->query("SELECT DATE(created_at) as day, COUNT(*) as total FROM tickets t WHERE DATE(created_at) BETWEEN '{$from}' AND '{$to}' {$dept_filter} GROUP BY DATE(created_at) ORDER BY day")->fetchAll();

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="report_' . $from . '_' . $to . '.csv"');
    $out = fopen('php://output','w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM
    fputcsv($out, ['Agente','Total','Resueltos','Cerrados','SLA Incumplido','Resp. prom. (min)']);
    foreach ($by_agent as $a) fputcsv($out, [$a['name'],$a['total'],$a['resolved'],$a['closed'],$a['breached'],round($a['avg_resp']??0)]);
    fclose($out);
    exit;
}

$departments = is_admin() ? db()->query("SELECT id,name FROM departments WHERE status='active' ORDER BY name")->fetchAll() : [];
$page_title  = t('reports');
include ROOT . '/templates/header.php';

function fmt_time($min): string {
    if (!$min) return '—';
    $min = (int)$min;
    if ($min < 60) return "{$min} min";
    if ($min < 1440) return round($min/60,1) . " h";
    return round($min/1440,1) . " días";
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="fw-bold mb-0"><?= t('reports') ?></h4>
  <a href="?from=<?= $from ?>&to=<?= $to ?>&dept=<?= $dept ?>&export=csv" class="btn btn-outline-success btn-sm">
    <i class="bi bi-download me-1"></i><?= t('export_csv') ?>
  </a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-center">
      <div class="col-md-3">
        <div class="input-group input-group-sm">
          <span class="input-group-text"><?= t('date_from') ?></span>
          <input type="date" name="from" class="form-control" value="<?= $from ?>">
        </div>
      </div>
      <div class="col-md-3">
        <div class="input-group input-group-sm">
          <span class="input-group-text"><?= t('date_to') ?></span>
          <input type="date" name="to" class="form-control" value="<?= $to ?>">
        </div>
      </div>
      <?php if (is_admin() && $departments): ?>
      <div class="col-md-3">
        <select name="dept" class="form-select form-select-sm">
          <option value=""><?= t('all') ?> <?= t('departments') ?></option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $dept==$d['id']?'selected':'' ?>><?= h($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-auto">
        <button type="submit" class="btn btn-secondary btn-sm"><?= t('filter') ?></button>
        <a href="<?= base_url('reports') ?>" class="btn btn-outline-secondary btn-sm">✕</a>
      </div>
    </form>
  </div>
</div>

<!-- KPI cards -->
<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['total',      $stats['total'],      'collection',      'info',    'Total tickets'],
    ['open',       $stats['open_count'], 'envelope-open',   'primary', t('open_tickets')],
    ['resolved',   $stats['resolved'],   'check-circle',    'success', t('tickets_resolved')],
    ['sla_breach', $stats['sla_breached'],'alarm',          'danger',  t('sla_breached')],
    ['avg_resp',   fmt_time($stats['avg_first_resp']),   'stopwatch', 'warning', t('avg_first_response')],
    ['avg_res',    fmt_time($stats['avg_resolution']),   'hourglass', 'secondary',t('avg_resolution_time')],
  ];
  foreach ($cards as [$k,$v,$icon,$color,$label]):
  ?>
  <div class="col-6 col-md-4 col-lg-2">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body py-3">
        <i class="bi bi-<?= $icon ?> fs-3 text-<?= $color ?> d-block mb-1"></i>
        <div class="fs-4 fw-bold"><?= is_numeric($v) ? number_format((float)$v) : h($v) ?></div>
        <div class="text-muted small"><?= $label ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
  <!-- Volume chart -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><strong>Volumen diario</strong></div>
      <div class="card-body"><canvas id="volumeChart" height="120"></canvas></div>
    </div>
  </div>
  <!-- CSAT -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white"><strong><?= t('satisfaction') ?> (CSAT)</strong></div>
      <div class="card-body text-center py-4">
        <?php $avg = round($csat['avg_rating'] ?? 0, 1); ?>
        <div style="font-size:3.5rem;font-weight:700;color:<?= $avg>=4?'#198754':($avg>=3?'#ffc107':'#dc3545') ?>"><?= $avg ?: '—' ?></div>
        <div class="d-flex justify-content-center gap-1 my-2">
          <?php for($i=1;$i<=5;$i++): ?>
            <i class="bi bi-star-fill <?= $i<=$avg?'text-warning':'text-muted' ?> fs-5"></i>
          <?php endfor; ?>
        </div>
        <div class="text-muted small"><?= number_format($csat['total_ratings']??0) ?> calificaciones</div>
      </div>
    </div>
  </div>
</div>

<!-- By agent -->
<?php if (!empty($by_agent)): ?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white"><strong><?= t('report_by_agent') ?></strong></div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0 small">
      <thead class="table-light"><tr><th>Agente</th><th>Total</th><th>Resueltos</th><th>Cerrados</th><th>SLA incumplido</th><th>Resp. promedio</th><th>Tasa resolución</th></tr></thead>
      <tbody>
        <?php foreach ($by_agent as $a):
          $rate = $a['total'] > 0 ? round(($a['resolved']+$a['closed'])/$a['total']*100) : 0;
        ?>
        <tr>
          <td><?= h($a['name']) ?></td>
          <td><span class="badge bg-primary"><?= $a['total'] ?></span></td>
          <td><span class="badge bg-success"><?= $a['resolved'] ?></span></td>
          <td><span class="badge bg-secondary"><?= $a['closed'] ?></span></td>
          <td><span class="badge <?= $a['breached']?'bg-danger':'bg-light text-muted' ?>"><?= $a['breached'] ?></span></td>
          <td><?= fmt_time($a['avg_resp']) ?></td>
          <td>
            <div class="progress" style="height:16px;min-width:80px">
              <div class="progress-bar bg-<?= $rate>=80?'success':($rate>=50?'warning':'danger') ?>" style="width:<?= $rate ?>%"><?= $rate ?>%</div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="row g-4">
  <!-- By department -->
  <?php if (!empty($by_dept)): ?>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><strong><?= t('report_by_dept') ?></strong></div>
      <div class="card-body"><canvas id="deptChart" height="200"></canvas></div>
    </div>
  </div>
  <?php endif; ?>
  <!-- By category -->
  <?php if (!empty($by_cat)): ?>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><strong><?= t('report_by_category') ?></strong></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 small">
          <thead class="table-light"><tr><th>Categoría</th><th>Tickets</th></tr></thead>
          <tbody>
            <?php foreach ($by_cat as $c): ?>
            <tr><td><?= h($c['name']) ?></td><td><span class="badge bg-primary"><?= $c['total'] ?></span></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
// Volume chart
new Chart(document.getElementById('volumeChart'), {
  type:'line',
  data:{
    labels: <?= json_encode(array_column($by_day,'day')) ?>,
    datasets:[{label:'Tickets',data:<?= json_encode(array_column($by_day,'total')) ?>,fill:true,tension:.4,borderColor:'#0d6efd',backgroundColor:'rgba(13,110,253,.1)'}]
  },
  options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}
});
<?php if (!empty($by_dept)): ?>
// Dept chart
new Chart(document.getElementById('deptChart'), {
  type:'bar',
  data:{
    labels: <?= json_encode(array_column($by_dept,'name')) ?>,
    datasets:[{label:'Tickets',data:<?= json_encode(array_column($by_dept,'total')) ?>,backgroundColor:<?= json_encode(array_column($by_dept,'color')) ?>}]
  },
  options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}
});
<?php endif; ?>
</script>
<?php include ROOT . '/templates/footer.php'; ?>
