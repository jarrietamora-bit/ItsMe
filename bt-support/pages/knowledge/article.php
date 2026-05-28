<?php
if (!defined('BTSUPPORT')) exit;
$lang  = current_lang();
$id    = (int)($_GET['id'] ?? 0);
$st    = db()->prepare("SELECT a.*, u.name as author_name FROM kb_articles a LEFT JOIN users u ON a.created_by=u.id WHERE a.id=? AND a.status='published'");
$st->execute([$id]);
$art   = $st->fetch();
if (!$art) { include ROOT . '/pages/404.php'; exit; }
if (!$art['is_public'] && !is_agent()) { http_response_code(403); exit; }

// Increment views
db()->prepare("UPDATE kb_articles SET views=views+1 WHERE id=?")->execute([$id]);

// Helpful vote
if (isset($_GET['helpful']) && is_logged_in()) {
    $v = $_GET['helpful'] === 'yes' ? 'helpful_yes' : 'helpful_no';
    db()->prepare("UPDATE kb_articles SET {$v}={$v}+1 WHERE id=?")->execute([$id]);
    flash('success','¡Gracias por tu calificación!');
    redirect(base_url('knowledge/article?id='.$id));
}

$page_title = $art["title_{$lang}"];
if (!is_logged_in()) {
    $company_name = setting('company_name') ?: 'BT-Support'; ?>
<!DOCTYPE html><html lang="<?= $lang ?>"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($art["title_{$lang}"]) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head><body style="background:#f4f6f9">
<nav class="navbar navbar-dark" style="background:<?= h(setting('company_color','#0d6efd')) ?>">
  <div class="container"><a class="navbar-brand fw-bold" href="<?= base_url('knowledge') ?>"><?= h($company_name) ?></a></div>
</nav>
<div class="container py-4" style="max-width:800px">
<?php } else { include ROOT . '/templates/header.php'; } ?>

<div style="max-width:800px">
  <a href="<?= base_url('knowledge') ?>" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left me-1"></i><?= t('knowledge_base') ?></a>
  <div class="card border-0 shadow-sm mt-2">
    <div class="card-body p-4">
      <h3><?= h($art["title_{$lang}"]) ?></h3>
      <div class="text-muted small mb-3">
        <i class="bi bi-person me-1"></i><?= h($art['author_name']) ?> &nbsp;
        <i class="bi bi-calendar me-1"></i><?= format_datetime($art['created_at']) ?> &nbsp;
        <i class="bi bi-eye me-1"></i><?= $art['views'] ?> vistas
        <?php if (!$art['is_public']): ?><span class="badge bg-warning text-dark ms-2"><?= t('private') ?></span><?php endif; ?>
      </div>
      <div class="article-content" style="line-height:1.8"><?= $art["content_{$lang}"] ?></div>
    </div>
    <div class="card-footer bg-white">
      <div class="d-flex align-items-center gap-3">
        <span class="text-muted small"><?= t('was_helpful') ?></span>
        <a href="?id=<?= $id ?>&helpful=yes" class="btn btn-sm btn-outline-success">
          <i class="bi bi-hand-thumbs-up me-1"></i><?= t('helpful_yes') ?> (<?= $art['helpful_yes'] ?>)
        </a>
        <a href="?id=<?= $id ?>&helpful=no" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-hand-thumbs-down me-1"></i><?= t('helpful_no') ?> (<?= $art['helpful_no'] ?>)
        </a>
      </div>
    </div>
  </div>
</div>

<?php if (!is_logged_in()): ?></div></body></html>
<?php else: include ROOT . '/templates/footer.php'; endif; ?>
