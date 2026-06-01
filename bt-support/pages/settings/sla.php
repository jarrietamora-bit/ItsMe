<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_escalation') {
        $enabled = !empty($_POST['sla_escalation_enabled']) ? '1' : '0';
        $notify  = in_array($_POST['sla_escalation_notify'] ?? '', ['supervisor','admin','both']) ? $_POST['sla_escalation_notify'] : 'supervisor';
        db()->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('sla_escalation_enabled',?) ON DUPLICATE KEY UPDATE setting_value=?")
            ->execute([$enabled,$enabled]);
        db()->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('sla_escalation_notify',?) ON DUPLICATE KEY UPDATE setting_value=?")
            ->execute([$notify,$notify]);
        flash('success', t('settings_saved'));
        redirect(base_url('settings/sla'));
    }

    if ($action === 'save') {
        $ids       = $_POST['sla_id']        ?? [];
        $names     = $_POST['sla_name']      ?? [];
        $prio_ids  = $_POST['sla_priority']  ?? [];
        $first_r   = $_POST['sla_first_r']   ?? [];
        $resol     = $_POST['sla_resolution'] ?? [];
        $bh        = $_POST['sla_bh']        ?? [];

        foreach ($ids as $i => $sid) {
            $sid   = (int)$sid;
            $name  = trim($names[$i]     ?? '');
            $prid  = (int)($prio_ids[$i] ?? 0);
            $fr    = (float)($first_r[$i] ?? 24);
            $res   = (float)($resol[$i]   ?? 72);
            $bho   = isset($bh[$i]) ? 1 : 0;
            if (!$name || !$prid) continue;
            if ($sid) {
                db()->prepare("UPDATE sla_policies SET name=?,priority_id=?,first_response_hours=?,resolution_hours=?,business_hours_only=? WHERE id=?")
                   ->execute([$name,$prid,$fr,$res,$bho,$sid]);
            } else {
                db()->prepare("INSERT INTO sla_policies (name,priority_id,first_response_hours,resolution_hours,business_hours_only) VALUES (?,?,?,?,?)")
                   ->execute([$name,$prid,$fr,$res,$bho]);
            }
        }
        flash('success', t('settings_saved'));
    }
    if ($action === 'delete') {
        $del_id = (int)($_POST['del_id'] ?? 0);
        if ($del_id) db()->prepare("DELETE FROM sla_policies WHERE id=?")->execute([$del_id]);
        flash('success', 'Política SLA eliminada.');
    }
    redirect(base_url('settings/sla'));
}

$slas       = db()->query("SELECT s.*,p.name_es,p.color FROM sla_policies s JOIN priorities p ON s.priority_id=p.id ORDER BY p.level")->fetchAll();
$priorities = db()->query("SELECT * FROM priorities ORDER BY level")->fetchAll();
$page_title = t('sla_settings');
include ROOT . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><?= t('sla_settings') ?></h4>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white"><strong>Políticas SLA actuales</strong></div>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th>Nombre</th>
            <th><?= t('priority') ?></th>
            <th><?= t('first_response_h') ?></th>
            <th><?= t('resolution_h') ?></th>
            <th><?= t('business_hours') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody id="slaRows">
          <?php foreach ($slas as $i => $s): ?>
          <tr>
            <td><input type="hidden" name="sla_id[]" value="<?= $s['id'] ?>"><input type="text" name="sla_name[]" class="form-control form-control-sm" value="<?= h($s['name']) ?>"></td>
            <td>
              <select name="sla_priority[]" class="form-select form-select-sm">
                <?php foreach ($priorities as $p): ?>
                  <option value="<?= $p['id'] ?>" <?= $p['id']==$s['priority_id']?'selected':'' ?>><?= h($p['name_es']) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="number" name="sla_first_r[]" class="form-control form-control-sm" value="<?= $s['first_response_hours'] ?>" step="0.5" min="0.5"></td>
            <td><input type="number" name="sla_resolution[]" class="form-control form-control-sm" value="<?= $s['resolution_hours'] ?>" step="0.5" min="0.5"></td>
            <td class="text-center"><input type="checkbox" name="sla_bh[<?= $i ?>]" class="form-check-input" <?= $s['business_hours_only']?'checked':'' ?>></td>
            <td>
              <button class="btn btn-sm btn-outline-danger py-0" onclick="submitDeleteSla(<?= $s['id'] ?>)" type="button"><i class="bi bi-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
          <!-- New row template -->
          <tr id="newRow">
            <td><input type="hidden" name="sla_id[]" value="0"><input type="text" name="sla_name[]" class="form-control form-control-sm" placeholder="Nueva política"></td>
            <td>
              <select name="sla_priority[]" class="form-select form-select-sm">
                <option value=""><?= t('select') ?>...</option>
                <?php foreach ($priorities as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= h($p['name_es']) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="number" name="sla_first_r[]" class="form-control form-control-sm" value="24" step="0.5" min="0.5"></td>
            <td><input type="number" name="sla_resolution[]" class="form-control form-control-sm" value="72" step="0.5" min="0.5"></td>
            <td class="text-center"><input type="checkbox" name="sla_bh[new]" class="form-check-input"></td>
            <td></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white">
      <button type="submit" class="btn btn-primary btn-sm"><?= t('save') ?></button>
    </div>
  </form>
</div>

<form method="post" id="deleteSlaForm" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="delete_policy">
  <input type="hidden" name="del_id" id="deleteSlaId" value="">
</form>
<script>
function submitDeleteSla(id) {
  if (!confirm('¿Eliminar?')) return;
  document.getElementById('deleteSlaId').value = id;
  var form = document.getElementById('deleteSlaForm');
  form.querySelector('input[name="action"]').value = 'delete';
  form.submit();
}
</script>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white"><strong>Prioridades del sistema</strong></div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0 small">
      <thead class="table-light"><tr><th>Nivel</th><th>ES</th><th>EN</th><th>Color</th></tr></thead>
      <tbody>
        <?php foreach ($priorities as $p): ?>
        <tr>
          <td><?= $p['level'] ?></td>
          <td><?= priority_badge($p['name_es'],$p['color']) ?></td>
          <td><?= h($p['name_en']) ?></td>
          <td><span class="badge" style="background:<?= h($p['color']) ?>"><?= h($p['color']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$esc_enabled = setting('sla_escalation_enabled') === '1';
$esc_notify  = setting('sla_escalation_notify') ?: 'supervisor';
?>
<div class="card border-0 shadow-sm mt-4">
  <div class="card-header bg-white d-flex align-items-center gap-2">
    <i class="bi bi-bell-fill text-warning"></i>
    <strong>Escalación automática por SLA vencido</strong>
  </div>
  <div class="card-body p-4">
    <p class="text-muted small mb-3">
      Cuando un ticket supera su tiempo de resolución SLA sin estar resuelto o cerrado,
      el sistema puede notificar automáticamente a los responsables una sola vez.
    </p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_escalation">
      <div class="mb-3">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="sla_escalation_enabled"
                 id="escEnabled" value="1" <?= $esc_enabled ? 'checked' : '' ?>
                 onchange="document.getElementById('escOptions').style.display=this.checked?'block':'none'">
          <label class="form-check-label fw-semibold" for="escEnabled">
            Activar notificaciones de escalación SLA
          </label>
        </div>
      </div>

      <div id="escOptions" style="display:<?= $esc_enabled ? 'block' : 'none' ?>">
        <div class="mb-3">
          <label class="form-label fw-semibold">¿A quién notificar?</label>
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="sla_escalation_notify"
                     id="notifySup" value="supervisor" <?= $esc_notify==='supervisor'?'checked':'' ?>>
              <label class="form-check-label" for="notifySup">
                <i class="bi bi-person-badge me-1 text-warning"></i>Solo supervisores del departamento
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="sla_escalation_notify"
                     id="notifyAdm" value="admin" <?= $esc_notify==='admin'?'checked':'' ?>>
              <label class="form-check-label" for="notifyAdm">
                <i class="bi bi-shield me-1 text-danger"></i>Solo administradores
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="sla_escalation_notify"
                     id="notifyBoth" value="both" <?= $esc_notify==='both'?'checked':'' ?>>
              <label class="form-check-label" for="notifyBoth">
                <i class="bi bi-people me-1 text-primary"></i>Supervisores y administradores
              </label>
            </div>
          </div>
        </div>

        <div class="alert alert-info py-2 small mb-3">
          <i class="bi bi-info-circle me-1"></i>
          La notificación se envía <strong>una sola vez</strong> por ticket en el momento exacto en que se detecta el vencimiento.
          Se envía tanto como alerta en el sistema (campana) como por correo electrónico (si el SMTP está configurado).
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-save me-1"></i>Guardar configuración de escalación
      </button>
    </form>
  </div>
</div>
<?php include ROOT . '/templates/footer.php'; ?>
