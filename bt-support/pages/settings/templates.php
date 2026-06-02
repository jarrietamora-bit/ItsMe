<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $id      = (int)($_POST['tpl_id'] ?? 0);
    $sub_es  = trim($_POST['subject_es'] ?? '');
    $sub_en  = trim($_POST['subject_en'] ?? '');
    $body_es = $_POST['body_es'] ?? '';
    $body_en = $_POST['body_en'] ?? '';
    if ($id) {
        db()->prepare("UPDATE email_templates SET subject_es=?,subject_en=?,body_es=?,body_en=? WHERE id=?")
           ->execute([$sub_es,$sub_en,$body_es,$body_en,$id]);
        flash('success', t('settings_saved'));
    }
    redirect(base_url('settings/templates'));
}

// Ensure default templates exist
$defaults = [
    ['Ticket creado',   'ticket_created',  'Ticket #{{ticket_number}} creado — {{company_name}}', 'Ticket #{{ticket_number}} created — {{company_name}}',
     '<p>Hola <strong>{{name}}</strong>,</p><p>Tu ticket <strong>#{{ticket_number}}</strong> — <em>{{subject}}</em> fue creado exitosamente. Nuestro equipo lo atenderá a la brevedad.</p>',
     '<p>Hello <strong>{{name}}</strong>,</p><p>Your ticket <strong>#{{ticket_number}}</strong> — <em>{{subject}}</em> has been created. Our team will attend it shortly.</p>'],
    ['Nueva respuesta', 'ticket_reply',    'Nueva respuesta en ticket #{{ticket_number}}', 'New reply on ticket #{{ticket_number}}',
     '<p>Hola <strong>{{name}}</strong>,</p><p>Hay una nueva respuesta en tu ticket <strong>#{{ticket_number}}</strong>.</p><blockquote>{{reply_message}}</blockquote><p><a href="{{ticket_url}}">Ver ticket</a></p>',
     '<p>Hello <strong>{{name}}</strong>,</p><p>There is a new reply on your ticket <strong>#{{ticket_number}}</strong>.</p><blockquote>{{reply_message}}</blockquote><p><a href="{{ticket_url}}">View ticket</a></p>'],
    ['Ticket resuelto', 'ticket_resolved', 'Ticket #{{ticket_number}} resuelto', 'Ticket #{{ticket_number}} resolved',
     '<p>Hola <strong>{{name}}</strong>,</p><p>Tu ticket <strong>#{{ticket_number}}</strong> ha sido marcado como <strong>resuelto</strong>. Si el problema persiste, puedes reabrirlo.</p>',
     '<p>Hello <strong>{{name}}</strong>,</p><p>Your ticket <strong>#{{ticket_number}}</strong> has been marked as <strong>resolved</strong>. If the issue persists, you can reopen it.</p>'],
    ['Restablecer contraseña', 'password_reset', 'Restablecer contraseña', 'Reset your password',
     '<p>Hola <strong>{{name}}</strong>,</p><p>Haga clic para restablecer su contraseña (expira en 1 hora):</p><p><a href="{{reset_url}}">Restablecer contraseña</a></p>',
     '<p>Hello <strong>{{name}}</strong>,</p><p>Click to reset your password (expires in 1 hour):</p><p><a href="{{reset_url}}">Reset password</a></p>'],
];
foreach ($defaults as [$name,$slug,$ses,$sen,$bes,$ben]) {
    db()->prepare("INSERT IGNORE INTO email_templates (name,slug,subject_es,subject_en,body_es,body_en) VALUES (?,?,?,?,?,?)")
       ->execute([$name,$slug,$ses,$sen,$bes,$ben]);
}

$templates = db()->query("SELECT * FROM email_templates ORDER BY id")->fetchAll();
$edit_id   = (int)($_GET['id'] ?? 0);
$editing   = null;
if ($edit_id) {
    foreach ($templates as $tpl) { if ($tpl['id'] === $edit_id) { $editing = $tpl; break; } }
}

$page_title = t('email_templates');
include ROOT . '/templates/header.php';
?>
<h4 class="fw-bold mb-4"><?= t('email_templates') ?></h4>
<div class="alert alert-info small">
  <strong><?= t('email_templates_vars') ?></strong> <code>{{ticket_number}}</code>, <code>{{subject}}</code>, <code>{{name}}</code>, <code>{{reply_message}}</code>, <code>{{ticket_url}}</code>, <code>{{reset_url}}</code>, <code>{{company_name}}</code>
</div>
<div class="row g-3">
  <div class="col-lg-3">
    <div class="list-group shadow-sm">
      <?php foreach ($templates as $tpl): ?>
        <a href="?id=<?= $tpl['id'] ?>" class="list-group-item list-group-item-action <?= $edit_id==$tpl['id']?'active':'' ?>">
          <i class="bi bi-envelope me-2"></i><?= h($tpl['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-9">
    <?php if ($editing): ?>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><strong><?= h($editing['name']) ?></strong> <code class="ms-2"><?= h($editing['slug']) ?></code></div>
      <div class="card-body p-4">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="tpl_id" value="<?= $editing['id'] ?>">
          <ul class="nav nav-tabs mb-3" id="tplLangTabs">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#esTab">🇪🇸 Español</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#enTab">🇺🇸 English</a></li>
          </ul>
          <div class="tab-content">
            <div class="tab-pane fade show active" id="esTab">
              <div class="mb-3">
                <label class="form-label">Asunto (ES)</label>
                <input type="text" name="subject_es" class="form-control" value="<?= h($editing['subject_es']) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Cuerpo HTML (ES)</label>
                <textarea name="body_es" class="form-control font-monospace" rows="12" style="font-size:12px"><?= h($editing['body_es']) ?></textarea>
              </div>
            </div>
            <div class="tab-pane fade" id="enTab">
              <div class="mb-3">
                <label class="form-label">Subject (EN)</label>
                <input type="text" name="subject_en" class="form-control" value="<?= h($editing['subject_en']) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Body HTML (EN)</label>
                <textarea name="body_en" class="form-control font-monospace" rows="12" style="font-size:12px"><?= h($editing['body_en']) ?></textarea>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><?= t('select_template_hint') ?></div></div>
    <?php endif; ?>
  </div>
</div>
<?php include ROOT . '/templates/footer.php'; ?>
