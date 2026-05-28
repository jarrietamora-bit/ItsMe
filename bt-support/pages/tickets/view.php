<?php
if (!defined('BTSUPPORT')) exit;
require_login();

$user   = current_user();
$uid    = $user['id'];
$role   = current_role();
$t_id   = (int)($_GET['id'] ?? 0);

$st = db()->prepare("SELECT t.*, u.name as client_name, u.email as client_email, u.phone as client_phone,
       p.name_es as priority_name, p.name_en as priority_name_en, p.color as priority_color,
       d.name as dept_name, d.color as dept_color,
       cat.name as cat_name, a.name as agent_name
FROM tickets t
JOIN users u ON t.created_by=u.id
LEFT JOIN priorities p ON t.priority_id=p.id
LEFT JOIN departments d ON t.department_id=d.id
LEFT JOIN categories cat ON t.category_id=cat.id
LEFT JOIN users a ON t.assigned_to=a.id
WHERE t.id=?");
$st->execute([$t_id]);
$ticket = $st->fetch();

if (!$ticket || !can_view_ticket($ticket)) {
    http_response_code(404);
    include ROOT . '/pages/404.php';
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    // Add reply
    if ($action === 'reply') {
        $message  = trim($_POST['message'] ?? '');
        $internal = is_agent() && !empty($_POST['is_internal']) ? 1 : 0;
        if ($message) {
            db()->prepare("INSERT INTO ticket_replies (ticket_id,user_id,message,is_internal) VALUES (?,?,?,?)")
               ->execute([$t_id, $uid, $message, $internal]);
            $reply_id = (int)db()->lastInsertId();

            // Attachments
            if (!empty($_FILES['attachments']['name'][0])) {
                foreach ($_FILES['attachments']['name'] as $i => $orig) {
                    $file = ['name'=>$_FILES['attachments']['name'][$i],'tmp_name'=>$_FILES['attachments']['tmp_name'][$i],'error'=>$_FILES['attachments']['error'][$i],'size'=>$_FILES['attachments']['size'][$i]];
                    $fname = upload_file($file, 'tickets/' . $t_id);
                    if ($fname) {
                        db()->prepare("INSERT INTO ticket_attachments (ticket_id,reply_id,user_id,filename,original_name,file_size) VALUES (?,?,?,?,?,?)")
                           ->execute([$t_id,$reply_id,$uid,$fname,$orig,$file['size']]);
                    }
                }
            }

            // Record first response time (agent replying)
            if (is_agent() && !$ticket['first_response_at']) {
                db()->prepare("UPDATE tickets SET first_response_at=NOW(), status='in_progress', updated_at=NOW() WHERE id=? AND first_response_at IS NULL")
                   ->execute([$t_id]);
            }
            db()->prepare("UPDATE tickets SET updated_at=NOW() WHERE id=?")->execute([$t_id]);

            // Notify the other party
            if (!$internal) {
                if (is_agent() && $ticket['created_by'] != $uid) {
                    // Agent replied → notify client
                    $client = db()->query("SELECT * FROM users WHERE id={$ticket['created_by']}")->fetch();
                    if ($client) {
                        send_notification($client['id'],'ticket_reply',"Nueva respuesta en #{$ticket['ticket_number']}",$message,base_url('tickets/view?id='.$t_id));
                        try { mailer()->sendTicketReply($ticket, $client, ['message'=>$message]); } catch(\Throwable $e){}
                    }
                } elseif ($role === 'client' && $ticket['assigned_to']) {
                    // Client replied → notify agent
                    $agent = db()->query("SELECT * FROM users WHERE id={$ticket['assigned_to']}")->fetch();
                    if ($agent) {
                        send_notification($agent['id'],'ticket_reply',"Cliente respondió #{$ticket['ticket_number']}",$message,base_url('tickets/view?id='.$t_id));
                        try { mailer()->send($agent['email'],"Cliente respondió: #{$ticket['ticket_number']}","<p>{$message}</p>",$agent['name']); } catch(\Throwable $e){}
                    }
                }
            }
            log_activity('reply_ticket','ticket',$t_id);
            flash('success', t('reply_added'));
        }
    }

    // Assign / Reassign
    if ($action === 'assign' && is_supervisor()) {
        $new_agent = (int)($_POST['assign_to'] ?? 0) ?: null;
        db()->prepare("UPDATE tickets SET assigned_to=?, updated_at=NOW() WHERE id=?")->execute([$new_agent, $t_id]);
        if ($new_agent) {
            send_notification($new_agent,'assigned',"Ticket asignado: #{$ticket['ticket_number']}","",base_url('tickets/view?id='.$t_id));
        }
        flash('success', t('ticket_updated'));
        log_activity('assign_ticket','ticket',$t_id);
    }

    // Transfer department
    if ($action === 'transfer' && is_supervisor()) {
        $new_dept = (int)($_POST['transfer_dept'] ?? 0) ?: null;
        db()->prepare("UPDATE tickets SET department_id=?, assigned_to=NULL, updated_at=NOW() WHERE id=?")->execute([$new_dept, $t_id]);
        flash('success', t('ticket_updated'));
        log_activity('transfer_ticket','ticket',$t_id,"dept:{$new_dept}");
    }

    // Status changes
    if ($action === 'resolve' && is_agent()) {
        db()->prepare("UPDATE tickets SET status='resolved', resolved_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$t_id]);
        $client = db()->query("SELECT * FROM users WHERE id={$ticket['created_by']}")->fetch();
        if ($client) {
            send_notification($client['id'],'resolved',"Ticket #{$ticket['ticket_number']} resuelto",'',base_url('tickets/view?id='.$t_id));
            try { mailer()->sendTicketResolved($ticket, $client); } catch(\Throwable $e){}
        }
        flash('success', t('ticket_resolved'));
        log_activity('resolve_ticket','ticket',$t_id);
    }
    if ($action === 'close' && is_agent()) {
        db()->prepare("UPDATE tickets SET status='closed', closed_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$t_id]);
        flash('success', t('ticket_closed'));
    }
    if ($action === 'reopen') {
        db()->prepare("UPDATE tickets SET status='open', resolved_at=NULL, closed_at=NULL, updated_at=NOW() WHERE id=?")->execute([$t_id]);
        flash('success', t('ticket_reopened'));
    }

    // Rating (client)
    if ($action === 'rate' && $role === 'client' && $ticket['status'] === 'resolved') {
        $rating  = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['rating_comment'] ?? '');
        if ($rating >= 1 && $rating <= 5) {
            db()->prepare("INSERT INTO ratings (ticket_id,user_id,rating,comment) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating),comment=VALUES(comment)")
               ->execute([$t_id,$uid,$rating,$comment]);
            flash('success','¡Gracias por tu calificación!');
        }
    }

    // Priority/dept update (admin)
    if ($action === 'update_meta' && is_agent()) {
        $priority = (int)($_POST['priority_id'] ?? 0) ?: null;
        $dept     = (int)($_POST['department_id'] ?? 0) ?: null;
        $category = (int)($_POST['category_id']   ?? 0) ?: null;
        db()->prepare("UPDATE tickets SET priority_id=?,department_id=?,category_id=?,updated_at=NOW() WHERE id=?")
           ->execute([$priority,$dept,$category,$t_id]);
        flash('success', t('ticket_updated'));
    }

    // Add tag (agents only)
    if ($action === 'add_tag' && is_agent()) {
        $tag_name = trim($_POST['tag_name'] ?? '');
        if ($tag_name !== '') {
            db()->prepare("INSERT IGNORE INTO tags (name) VALUES (?)")->execute([$tag_name]);
            $tag_id_row = db()->prepare("SELECT id FROM tags WHERE name=?");
            $tag_id_row->execute([$tag_name]);
            $tag_id = (int)$tag_id_row->fetchColumn();
            if ($tag_id) {
                db()->prepare("INSERT IGNORE INTO ticket_tags (ticket_id, tag_id) VALUES (?,?)")->execute([$t_id, $tag_id]);
            }
        }
        flash('success', t('ticket_updated'));
    }

    // Remove tag (agents only)
    if ($action === 'remove_tag' && is_agent()) {
        $tag_id = (int)($_POST['tag_id'] ?? 0);
        if ($tag_id) {
            db()->prepare("DELETE FROM ticket_tags WHERE ticket_id=? AND tag_id=?")->execute([$t_id, $tag_id]);
        }
        flash('success', t('ticket_updated'));
    }

    // Add related ticket (agents only)
    if ($action === 'add_related' && is_agent()) {
        $rel_number = trim($_POST['related_number'] ?? '');
        if ($rel_number !== '') {
            $rel_st = db()->prepare("SELECT id FROM tickets WHERE ticket_number=?");
            $rel_st->execute([$rel_number]);
            $rel_ticket_id = (int)$rel_st->fetchColumn();
            if ($rel_ticket_id && $rel_ticket_id !== $t_id) {
                $id1 = min($t_id, $rel_ticket_id);
                $id2 = max($t_id, $rel_ticket_id);
                db()->prepare("INSERT IGNORE INTO related_tickets (ticket_id, related_id) VALUES (?,?)")->execute([$t_id, $rel_ticket_id]);
            }
        }
        flash('success', t('ticket_updated'));
    }

    // Remove related ticket (agents only)
    if ($action === 'remove_related' && is_agent()) {
        $rel_id = (int)($_POST['related_id'] ?? 0);
        if ($rel_id) {
            db()->prepare("DELETE FROM related_tickets WHERE (ticket_id=? AND related_id=?) OR (ticket_id=? AND related_id=?)")->execute([$t_id,$rel_id,$rel_id,$t_id]);
        }
        flash('success', t('ticket_updated'));
    }

    redirect(base_url('tickets/view?id='.$t_id));
}

// Reload ticket after possible updates
$st->execute([$t_id]);
$ticket = $st->fetch();

// Replies
$replies = db()->prepare("SELECT r.*, u.name as author_name, u.role as author_role, u.avatar as author_avatar FROM ticket_replies r JOIN users u ON r.user_id=u.id WHERE r.ticket_id=? ORDER BY r.created_at ASC");
$replies->execute([$t_id]);
$replies = $replies->fetchAll();

// Attachments indexed by reply_id
$att_st = db()->prepare("SELECT * FROM ticket_attachments WHERE ticket_id=?");
$att_st->execute([$t_id]);
$attachments = [];
foreach ($att_st->fetchAll() as $att) {
    $attachments[$att['reply_id'] ?? 0][] = $att;
}

// Canned responses for this dept
$canned = [];
if (is_agent()) {
    $st2 = db()->prepare("SELECT * FROM canned_responses WHERE department_id=? OR department_id IS NULL ORDER BY name");
    $st2->execute([$ticket['department_id']]);
    $canned = $st2->fetchAll();
}

// Agents for assignment (supervisor/admin)
$dept_agents = [];
if (is_supervisor()) {
    $st3 = db()->prepare("SELECT u.id,u.name FROM users u JOIN department_users du ON u.id=du.user_id WHERE du.department_id=? AND u.role IN('agent','supervisor') AND u.status='active' ORDER BY u.name");
    $st3->execute([$ticket['department_id']]);
    $dept_agents = $st3->fetchAll();
}

$departments_list = is_supervisor() ? db()->query("SELECT id,name FROM departments WHERE status='active' ORDER BY name")->fetchAll() : [];
$priorities_list  = db()->query("SELECT * FROM priorities ORDER BY level")->fetchAll();
$categories_list  = db()->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();

// Tags
$tag_st = db()->prepare("SELECT t.id, t.name FROM tags t JOIN ticket_tags tt ON t.id=tt.tag_id WHERE tt.ticket_id=? ORDER BY t.name");
$tag_st->execute([$t_id]);
$ticket_tags = $tag_st->fetchAll();

// Related tickets
$related_st = db()->prepare(
    "SELECT tk.id, tk.ticket_number, tk.subject, tk.status
     FROM related_tickets rt
     JOIN tickets tk ON CASE WHEN rt.ticket_id=? THEN rt.related_id ELSE rt.ticket_id END = tk.id
     WHERE rt.ticket_id=? OR rt.related_id=?"
);
$related_st->execute([$t_id, $t_id, $t_id]);
$related = $related_st->fetchAll();

// Existing rating
$rating_st = db()->prepare("SELECT * FROM ratings WHERE ticket_id=?");
$rating_st->execute([$t_id]);
$rating_row = $rating_st->fetch() ?: null;

$page_title = 'Ticket #' . $ticket['ticket_number'];
include ROOT . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><?= h($ticket['ticket_number']) ?></h4>
    <small class="text-muted"><?= t('created_at') ?>: <?= format_datetime($ticket['created_at']) ?></small>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= base_url('tickets') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i><?= t('back') ?></a>
    <?php if (is_agent() && !in_array($ticket['status'],['resolved','closed'])): ?>
      <form method="post" class="d-inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="resolve">
        <button class="btn btn-success btn-sm"><?= t('mark_resolved') ?></button>
      </form>
      <form method="post" class="d-inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="close">
        <button class="btn btn-secondary btn-sm"><?= t('close_ticket') ?></button>
      </form>
    <?php endif; ?>
    <?php if (in_array($ticket['status'],['resolved','closed'])): ?>
      <form method="post" class="d-inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="reopen">
        <button class="btn btn-outline-primary btn-sm"><?= t('reopen_ticket') ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <!-- Main conversation -->
  <div class="col-lg-8">
    <!-- Ticket subject -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h5><?= h($ticket['subject']) ?></h5>
        <div class="d-flex gap-2 flex-wrap">
          <?= status_badge($ticket['status']) ?>
          <?= $ticket['priority_name'] ? priority_badge($ticket['priority_name'],$ticket['priority_color']) : '' ?>
          <?php if ($ticket['dept_name']): ?><span class="badge" style="background:<?= h($ticket['dept_color']??'#6c757d') ?>"><?= h($ticket['dept_name']) ?></span><?php endif; ?>
          <?php if ($ticket['sla_breached']): ?><span class="badge bg-danger"><i class="bi bi-alarm me-1"></i><?= t('sla_breached') ?></span><?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Replies -->
    <div class="mb-3" id="conversation">
      <?php foreach ($replies as $reply):
        $is_mine = $reply['user_id'] == $uid;
        $is_staff = in_array($reply['author_role'],['super_admin','admin','supervisor','agent']);
        $is_internal = (bool)$reply['is_internal'];
        if ($is_internal && $role === 'client') continue;
      ?>
      <div class="card border-0 shadow-sm mb-2 <?= $is_internal ? 'border-start border-warning border-3' : '' ?>">
        <div class="card-header bg-<?= $is_staff?'light':'white' ?> py-2 d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                 style="width:32px;height:32px;background:<?= $is_staff?'var(--brand-color,#0d6efd)':'#6c757d' ?>;font-size:13px">
              <?= strtoupper(substr($reply['author_name'],0,1)) ?>
            </div>
            <div>
              <strong class="small"><?= h($reply['author_name']) ?></strong>
              <?= role_badge($reply['author_role']) ?>
              <?php if ($is_internal): ?><span class="badge bg-warning text-dark ms-1"><i class="bi bi-eye-slash me-1"></i><?= t('internal_note') ?></span><?php endif; ?>
            </div>
          </div>
          <small class="text-muted"><?= format_datetime($reply['created_at']) ?></small>
        </div>
        <div class="card-body py-3">
          <div style="white-space:pre-wrap;line-height:1.7"><?= nl2br(h($reply['message'])) ?></div>
          <?php if (!empty($attachments[$reply['id']])): ?>
          <div class="mt-2 d-flex gap-2 flex-wrap">
            <?php foreach ($attachments[$reply['id']] as $att): ?>
              <a href="<?= base_url('uploads/tickets/'.$t_id.'/'.$att['filename']) ?>" target="_blank"
                 class="btn btn-outline-secondary btn-sm py-1">
                <i class="bi bi-paperclip me-1"></i><?= h($att['original_name']) ?>
                <span class="text-muted ms-1">(<?= format_filesize($att['file_size']) ?>)</span>
              </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Reply form -->
    <?php if (!in_array($ticket['status'],['closed']) || is_agent()): ?>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" id="replyTabs">
          <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#publicTab"><?= t('public_reply') ?></a></li>
          <?php if (is_agent()): ?>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#noteTab"><i class="bi bi-lock me-1"></i><?= t('internal_note') ?></a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reply">
          <input type="hidden" name="is_internal" id="isInternalField" value="0">
          <div class="tab-content">
            <div class="tab-pane fade show active" id="publicTab">
              <?php if (!empty($canned)): ?>
              <div class="mb-2">
                <select id="cannedSelect" class="form-select form-select-sm" onchange="insertCanned(this.value)">
                  <option value=""><?= t('canned_response') ?>...</option>
                  <?php foreach ($canned as $cr): ?>
                    <option value="<?= h($cr['content']) ?>"><?= h($cr['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endif; ?>
              <textarea name="message" class="form-control" rows="5" placeholder="Escriba su respuesta..." id="replyMsg"></textarea>
              <div class="mt-2">
                <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
              </div>
            </div>
            <?php if (is_agent()): ?>
            <div class="tab-pane fade" id="noteTab">
              <textarea name="message" class="form-control" rows="5" placeholder="Nota interna (solo visible para agentes)..." id="noteMsg"></textarea>
            </div>
            <?php endif; ?>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i><?= t('submit') ?></button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Rating (client, resolved ticket) -->
    <?php if ($role === 'client' && $ticket['status'] === 'resolved'): ?>
    <div class="card border-0 shadow-sm mt-3 border-top border-success border-3">
      <div class="card-body">
        <h6><?= t('rate_ticket') ?></h6>
        <?php if ($rating_row): ?>
          <div class="d-flex gap-1">
            <?php for ($i=1;$i<=5;$i++): ?>
              <i class="bi bi-star-fill <?= $i<=$rating_row['rating']?'text-warning':'text-muted' ?>"></i>
            <?php endfor; ?>
          </div>
          <?php if ($rating_row['comment']): ?><p class="small text-muted mt-1">"<?= h($rating_row['comment']) ?>"</p><?php endif; ?>
        <?php else: ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="rate">
          <div class="d-flex gap-2 mb-2" id="starRating">
            <?php for ($i=1;$i<=5;$i++): ?>
              <i class="bi bi-star fs-4 text-muted star-btn" data-val="<?= $i ?>" style="cursor:pointer"></i>
            <?php endfor; ?>
          </div>
          <input type="hidden" name="rating" id="ratingVal" value="">
          <input type="text" name="rating_comment" class="form-control form-control-sm mb-2" placeholder="Comentario (opcional)">
          <button type="submit" class="btn btn-warning btn-sm"><?= t('submit') ?></button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Sidebar info -->
  <div class="col-lg-4">
    <!-- Client info -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white"><strong><?= t('client_info') ?></strong></div>
      <div class="card-body small">
        <div class="mb-1"><i class="bi bi-person me-2 text-muted"></i><?= h($ticket['client_name']) ?></div>
        <div class="mb-1"><i class="bi bi-envelope me-2 text-muted"></i><?= h($ticket['client_email']) ?></div>
        <?php if ($ticket['client_phone']): ?>
        <div class="mb-1"><i class="bi bi-telephone me-2 text-muted"></i><?= h($ticket['client_phone']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Ticket meta -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white d-flex justify-content-between">
        <strong><?= t('ticket_details') ?></strong>
        <?php if (is_agent()): ?>
        <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#metaModal"><?= t('edit') ?></button>
        <?php endif; ?>
      </div>
      <div class="card-body small">
        <div class="row g-2">
          <div class="col-5 text-muted"><?= t('status') ?></div>
          <div class="col-7"><?= status_badge($ticket['status']) ?></div>
          <div class="col-5 text-muted"><?= t('priority') ?></div>
          <div class="col-7"><?= $ticket['priority_name'] ? priority_badge($ticket['priority_name'],$ticket['priority_color']) : '—' ?></div>
          <div class="col-5 text-muted"><?= t('department') ?></div>
          <div class="col-7"><?= h($ticket['dept_name'] ?? '—') ?></div>
          <div class="col-5 text-muted"><?= t('category') ?></div>
          <div class="col-7"><?= h($ticket['cat_name'] ?? '—') ?></div>
          <div class="col-5 text-muted"><?= t('assigned_to') ?></div>
          <div class="col-7">
            <?= h($ticket['agent_name'] ?? '—') ?>
            <?php if (is_supervisor() && $dept_agents): ?>
            <button class="btn btn-link btn-sm p-0 ms-1" data-bs-toggle="modal" data-bs-target="#assignModal">
              <i class="bi bi-person-check"></i>
            </button>
            <?php endif; ?>
          </div>
          <?php if ($ticket['sla_due_at']): ?>
          <div class="col-5 text-muted"><?= t('sla_due') ?></div>
          <div class="col-7 <?= $ticket['sla_breached']?'text-danger fw-bold':'' ?>"><?= format_datetime($ticket['sla_due_at']) ?></div>
          <?php endif; ?>
          <?php if ($ticket['first_response_at']): ?>
          <div class="col-5 text-muted"><?= t('first_response') ?></div>
          <div class="col-7"><?= format_datetime($ticket['first_response_at']) ?></div>
          <?php endif; ?>
          <?php if ($ticket['resolved_at']): ?>
          <div class="col-5 text-muted"><?= t('resolved_at') ?></div>
          <div class="col-7"><?= format_datetime($ticket['resolved_at']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Transfer dept (supervisor) -->
    <?php if (is_supervisor() && $departments_list): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white"><strong><?= t('transfer_dept') ?></strong></div>
      <div class="card-body">
        <form method="post" class="d-flex gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="transfer">
          <select name="transfer_dept" class="form-select form-select-sm">
            <?php foreach ($departments_list as $d): ?>
              <option value="<?= $d['id'] ?>" <?= $d['id']==$ticket['department_id']?'selected':'' ?>><?= h($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm btn-outline-secondary flex-shrink-0"><?= t('submit') ?></button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Tags -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white"><strong>Etiquetas</strong></div>
      <div class="card-body">
        <?php if (!empty($ticket_tags)): ?>
        <div class="d-flex flex-wrap gap-1 mb-2">
          <?php foreach ($ticket_tags as $tag): ?>
          <span class="badge bg-secondary d-inline-flex align-items-center gap-1">
            <?= h($tag['name']) ?>
            <?php if (is_agent()): ?>
            <form method="post" class="d-inline m-0 p-0">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove_tag">
              <input type="hidden" name="tag_id" value="<?= (int)$tag['id'] ?>">
              <button type="submit" class="btn-close btn-close-white p-0" style="font-size:0.55rem" aria-label="Remove"></button>
            </form>
            <?php endif; ?>
          </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (is_agent()): ?>
        <form method="post" class="d-flex gap-2 mt-1">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_tag">
          <input type="text" name="tag_name" class="form-control form-control-sm" placeholder="Nueva etiqueta..." autocomplete="off" id="tagInput">
          <button type="submit" class="btn btn-sm btn-outline-secondary flex-shrink-0">+</button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Related tickets -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white"><strong>Tickets relacionados</strong></div>
      <div class="card-body">
        <?php if (!empty($related)): ?>
        <ul class="list-unstyled mb-2">
          <?php foreach ($related as $rel): ?>
          <li class="d-flex align-items-center justify-content-between gap-1 mb-1">
            <a href="<?= base_url('tickets/view?id='.$rel['id']) ?>" class="text-decoration-none small fw-semibold">
              <?= h($rel['ticket_number']) ?>
            </a>
            <span class="text-truncate small text-muted flex-grow-1 mx-1" style="max-width:100px" title="<?= h($rel['subject']) ?>"><?= h($rel['subject']) ?></span>
            <?= status_badge($rel['status']) ?>
            <?php if (is_agent()): ?>
            <form method="post" class="d-inline m-0 p-0">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove_related">
              <input type="hidden" name="related_id" value="<?= (int)$rel['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" style="line-height:1.2" title="Quitar"><i class="bi bi-x"></i></button>
            </form>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if (is_agent()): ?>
        <form method="post" class="d-flex gap-2 mt-1">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_related">
          <input type="text" name="related_number" class="form-control form-control-sm" placeholder="Número de ticket...">
          <button type="submit" class="btn btn-sm btn-outline-secondary flex-shrink-0">+</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Assign Modal -->
<?php if (is_supervisor() && $dept_agents): ?>
<div class="modal fade" id="assignModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><?= t('reassign') ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="assign">
        <div class="modal-body">
          <select name="assign_to" class="form-select">
            <option value=""><?= t('none') ?></option>
            <?php foreach ($dept_agents as $ag): ?>
              <option value="<?= $ag['id'] ?>" <?= $ag['id']==$ticket['assigned_to']?'selected':'' ?>><?= h($ag['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer"><button class="btn btn-primary btn-sm"><?= t('save') ?></button></div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Meta edit modal (agent) -->
<?php if (is_agent()): ?>
<div class="modal fade" id="metaModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><?= t('ticket_details') ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="update_meta">
        <div class="modal-body row g-3">
          <div class="col-12">
            <label class="form-label"><?= t('priority') ?></label>
            <select name="priority_id" class="form-select">
              <option value=""><?= t('none') ?></option>
              <?php foreach ($priorities_list as $pr): ?>
                <option value="<?= $pr['id'] ?>" <?= $pr['id']==$ticket['priority_id']?'selected':'' ?>><?= h($pr['name_es']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label"><?= t('department') ?></label>
            <select name="department_id" class="form-select">
              <option value=""><?= t('none') ?></option>
              <?php foreach ($departments_list as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $d['id']==$ticket['department_id']?'selected':'' ?>><?= h($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label"><?= t('category') ?></label>
            <select name="category_id" class="form-select">
              <option value=""><?= t('none') ?></option>
              <?php foreach ($categories_list as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $c['id']==$ticket['category_id']?'selected':'' ?>><?= h($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary"><?= t('save') ?></button></div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// Canned response
function insertCanned(text) {
  if (!text) return;
  const ta = document.getElementById('replyMsg');
  if (ta) ta.value = text;
  document.getElementById('cannedSelect').value = '';
}

// Star rating
document.querySelectorAll('.star-btn').forEach(star => {
  star.addEventListener('click', function(){
    const val = this.dataset.val;
    document.getElementById('ratingVal').value = val;
    document.querySelectorAll('.star-btn').forEach((s,i) => {
      s.className = 'bi fs-4 star-btn ' + (i < val ? 'bi-star-fill text-warning' : 'bi-star text-muted');
    });
  });
  star.addEventListener('mouseover', function(){
    const val = this.dataset.val;
    document.querySelectorAll('.star-btn').forEach((s,i) => {
      s.className = 'bi fs-4 star-btn ' + (i < val ? 'bi-star-fill text-warning' : 'bi-star text-muted');
    });
  });
});

// Active tab switches textarea name and is_internal flag
document.querySelectorAll('#replyTabs a').forEach(tab => {
  tab.addEventListener('shown.bs.tab', e => {
    const isNote = e.target.getAttribute('href') === '#noteTab';
    document.getElementById('replyMsg')?.setAttribute('name', isNote ? '' : 'message');
    document.getElementById('noteMsg')?.setAttribute('name', isNote ? 'message' : '');
    const field = document.getElementById('isInternalField');
    if (field) field.value = isNote ? '1' : '0';
  });
});

// Scroll to bottom of conversation
const conv = document.getElementById('conversation');
if (conv) conv.scrollTop = conv.scrollHeight;
</script>
<?php include ROOT . '/templates/footer.php'; ?>
