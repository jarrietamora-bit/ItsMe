<?php
if (!defined('BTSUPPORT')) exit;
require_login();

$user = current_user();
$uid  = $user['id'];
$role = current_role();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = t('error'); }
    else {
        $subject   = trim($_POST['subject']   ?? '');
        $message   = trim($_POST['message']   ?? '');
        $priority  = (int)($_POST['priority_id'] ?? 0) ?: null;
        $category  = (int)($_POST['category_id'] ?? 0) ?: null;
        $dept_id   = (int)($_POST['department_id'] ?? 0) ?: null;
        $assigned  = is_agent() ? ((int)($_POST['assigned_to'] ?? 0) ?: null) : null;

        if (!$subject) $errors[] = t('required') . ' (' . t('subject') . ')';
        if (!$message) $errors[] = t('required') . ' (Mensaje)';

        // Client can only pick departments, agent picks everything
        if ($role === 'client') {
            $dept_id   = (int)($_POST['department_id'] ?? 0) ?: null;
            $assigned  = null;
        }

        if (empty($errors)) {
            $ticket_num = generate_ticket_number();

            // Calculate SLA due date
            $sla_due = null;
            if ($priority) {
                $sla = db()->prepare("SELECT resolution_hours FROM sla_policies WHERE priority_id = ?");
                $sla->execute([$priority]);
                $sla_row = $sla->fetch();
                if ($sla_row) {
                    $sla_due = date('Y-m-d H:i:s', time() + (float)$sla_row['resolution_hours'] * 3600);
                }
            }

            $st = db()->prepare("INSERT INTO tickets (ticket_number,subject,priority_id,category_id,department_id,created_by,assigned_to,sla_due_at,status) VALUES (?,?,?,?,?,?,?,?,'open')");
            $st->execute([$ticket_num,$subject,$priority,$category,$dept_id,$uid,$assigned,$sla_due]);
            $ticket_id = (int)db()->lastInsertId();

            // First reply (message body)
            db()->prepare("INSERT INTO ticket_replies (ticket_id,user_id,message,is_internal) VALUES (?,?,?,0)")
               ->execute([$ticket_id,$uid,$message]);

            // Handle attachments
            if (!empty($_FILES['attachments']['name'][0])) {
                foreach ($_FILES['attachments']['name'] as $i => $orig_name) {
                    $file = [
                        'name'     => $_FILES['attachments']['name'][$i],
                        'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                        'error'    => $_FILES['attachments']['error'][$i],
                        'size'     => $_FILES['attachments']['size'][$i],
                    ];
                    $fname = upload_file($file, 'tickets/' . $ticket_id);
                    if ($fname) {
                        $reply_id = db()->query("SELECT MAX(id) FROM ticket_replies WHERE ticket_id={$ticket_id}")->fetchColumn();
                        db()->prepare("INSERT INTO ticket_attachments (ticket_id,reply_id,user_id,filename,original_name,file_size) VALUES (?,?,?,?,?,?)")
                           ->execute([$ticket_id,$reply_id,$uid,$fname,$orig_name,$file['size']]);
                    }
                }
            }

            // Auto-assign if department has agents (round-robin)
            if (!$assigned && $dept_id) {
                $st2 = db()->prepare("SELECT u.id FROM users u JOIN department_users du ON u.id=du.user_id WHERE du.department_id=? AND u.role IN('agent','supervisor') AND u.status='active' AND du.is_supervisor=0 ORDER BY (SELECT COUNT(*) FROM tickets WHERE assigned_to=u.id AND status NOT IN('resolved','closed')) ASC LIMIT 1");
                $st2->execute([$dept_id]);
                $auto_agent = $st2->fetchColumn();
                if ($auto_agent) {
                    db()->prepare("UPDATE tickets SET assigned_to=? WHERE id=?")->execute([$auto_agent,$ticket_id]);
                    $assigned = $auto_agent;
                }
            }

            // Notifications
            $ticket_row = db()->prepare("SELECT * FROM tickets WHERE id=?")->execute([$ticket_id]) ? db()->query("SELECT * FROM tickets WHERE id={$ticket_id}")->fetch() : [];

            // Email to client
            try { mailer()->sendTicketCreated(['ticket_number'=>$ticket_num,'subject'=>$subject,'name'=>$user['name'],'ticket_url'=>base_url('tickets/view?id='.$ticket_id)], $user); } catch(\Throwable $e){}

            // Email to assigned agent
            if ($assigned) {
                $agent = db()->prepare("SELECT * FROM users WHERE id=?")->execute([$assigned]) ? db()->query("SELECT * FROM users WHERE id={$assigned}")->fetch() : null;
                if ($agent) {
                    send_notification($assigned,'new_ticket',"Nuevo ticket #{$ticket_num}", h($subject), base_url('tickets/view?id='.$ticket_id));
                    try { mailer()->send($agent['email'],"Nuevo ticket asignado: #{$ticket_num}","<p>Se te asignó el ticket <strong>#{$ticket_num}</strong>: {$subject}</p>",$agent['name']); } catch(\Throwable $e){}
                }
            }

            // Notify supervisors of department
            if ($dept_id) {
                $sups = db()->prepare("SELECT u.* FROM users u JOIN department_users du ON u.id=du.user_id WHERE du.department_id=? AND du.is_supervisor=1");
                $sups->execute([$dept_id]);
                foreach ($sups->fetchAll() as $sup) {
                    if ($sup['id'] !== $uid) {
                        send_notification($sup['id'],'new_ticket',"Nuevo ticket en tu dpto: #{$ticket_num}", $subject, base_url('tickets/view?id='.$ticket_id));
                    }
                }
            }

            log_activity('create_ticket','ticket',$ticket_id,$ticket_num);
            flash('success', t('ticket_created'));
            redirect(base_url('tickets/view?id=' . $ticket_id));
        }
    }
}

$priorities  = db()->query("SELECT * FROM priorities ORDER BY level")->fetchAll();
$departments = db()->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$categories  = db()->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$agents      = is_agent() ? db()->query("SELECT id, name FROM users WHERE role IN('agent','supervisor') AND status='active' ORDER BY name")->fetchAll() : [];

$page_title = t('new_ticket');
include ROOT . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="fw-bold mb-0"><?= t('new_ticket') ?></h4>
  <a href="<?= base_url('tickets') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i><?= t('back') ?></a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e) echo "<li>".h($e)."</li>"; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <form method="post" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label fw-semibold"><?= t('subject') ?> <span class="text-danger">*</span></label>
            <input type="text" name="subject" class="form-control" value="<?= h($_POST['subject']??'') ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Descripción del problema <span class="text-danger">*</span></label>
            <textarea name="message" class="form-control" rows="8" required><?= h($_POST['message']??'') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold"><?= t('attachments') ?> <small class="text-muted fw-normal">(<?= t('optional') ?>)</small></label>
            <input type="file" name="attachments[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt,.csv">
            <small class="text-muted">Máx <?= setting('upload_max_mb') ?: 10 ?>MB por archivo</small>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-send me-2"></i><?= t('submit') ?></button>
            <a href="<?= base_url('tickets') ?>" class="btn btn-outline-secondary"><?= t('cancel') ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><strong><?= t('ticket_details') ?></strong></div>
      <div class="card-body">
        <form id="detailsForm">
          <div class="mb-3">
            <label class="form-label small fw-semibold"><?= t('department') ?></label>
            <select name="department_id" form="detailsForm" class="form-select form-select-sm" id="deptSelect"
                    onchange="loadCategories(this.value)">
              <option value=""><?= t('select') ?>...</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= ($_POST['department_id']??'')==$d['id']?'selected':'' ?>><?= h($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold"><?= t('category') ?></label>
            <select name="category_id" class="form-select form-select-sm" id="catSelect">
              <option value=""><?= t('select') ?>...</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" data-dept="<?= $c['department_id'] ?>" <?= ($_POST['category_id']??'')==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold"><?= t('priority') ?></label>
            <select name="priority_id" class="form-select form-select-sm">
              <option value=""><?= t('select') ?>...</option>
              <?php foreach ($priorities as $pr): ?>
                <option value="<?= $pr['id'] ?>" style="color:<?= h($pr['color']) ?>" <?= ($_POST['priority_id']??'')==$pr['id']?'selected':'' ?>>
                  <?= h($pr['name_es']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (is_agent() && $agents): ?>
          <div class="mb-3">
            <label class="form-label small fw-semibold"><?= t('assign_agent') ?></label>
            <select name="assigned_to" class="form-select form-select-sm">
              <option value=""><?= t('none') ?> (auto)</option>
              <?php foreach ($agents as $a): ?>
                <option value="<?= $a['id'] ?>" <?= ($_POST['assigned_to']??'')==$a['id']?'selected':'' ?>><?= h($a['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
        </form>
        <!-- The actual fields need to be in the main form -->
        <script>
        // Move select fields into main form
        document.addEventListener('DOMContentLoaded', () => {
          const mainForm = document.querySelector('form[method="post"]');
          document.querySelectorAll('#detailsForm select').forEach(sel => {
            sel.form && (sel.removeAttribute('form'));
            mainForm.appendChild(sel);
          });
        });
        </script>
      </div>
    </div>

    <!-- Knowledge base suggestions -->
    <div class="card border-0 shadow-sm mt-3">
      <div class="card-header bg-white"><strong><i class="bi bi-lightbulb me-2 text-warning"></i><?= t('knowledge_base') ?></strong></div>
      <div class="card-body p-2" id="kbSuggestions">
        <small class="text-muted ps-2">Escriba en el asunto para ver sugerencias...</small>
      </div>
    </div>
  </div>
</div>

<script>
function loadCategories(deptId) {
  const sel = document.getElementById('catSelect');
  Array.from(sel.options).forEach(opt => {
    if (!opt.value) return;
    opt.hidden = deptId && opt.dataset.dept && opt.dataset.dept !== deptId;
  });
}

// KB suggestions on subject change
const subjectInput = document.querySelector('input[name="subject"]');
let kbTimer;
subjectInput?.addEventListener('input', function() {
  clearTimeout(kbTimer);
  kbTimer = setTimeout(() => {
    const q = this.value.trim();
    if (q.length < 3) return;
    fetch(`${BASE_URL}/knowledge?ajax=1&q=${encodeURIComponent(q)}`)
      .then(r=>r.json()).then(data=>{
        const box = document.getElementById('kbSuggestions');
        if (!data.length) { box.innerHTML='<small class="text-muted ps-2">Sin sugerencias.</small>'; return; }
        box.innerHTML = data.map(a=>`<a href="${BASE_URL}/knowledge/article?id=${a.id}" target="_blank" class="d-block text-decoration-none p-2 rounded hover-bg-light small"><i class="bi bi-file-text me-1 text-muted"></i>${a.title}</a>`).join('');
      }).catch(()=>{});
  }, 500);
});
</script>
<?php include ROOT . '/templates/footer.php'; ?>
