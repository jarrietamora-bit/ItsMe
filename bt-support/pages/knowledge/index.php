<?php
if (!defined('BTSUPPORT')) exit;
$lang  = current_lang();
$q     = trim($_GET['q'] ?? '');
$cat   = (int)($_GET['cat'] ?? 0);
$is_public = !is_logged_in() || current_role() === 'client';

// AJAX suggestions
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $q2 = '%' . $q . '%';
    $title_col = "title_{$lang}";
    $st = db()->prepare("SELECT id, {$title_col} as title FROM kb_articles WHERE status='published' AND (is_public=1 OR is_public=0) AND ({$title_col} LIKE ? OR content_{$lang} LIKE ?) LIMIT 5");
    $st->execute([$q2,$q2]);
    echo json_encode($st->fetchAll());
    exit;
}

$cats = db()->query("SELECT * FROM kb_categories ORDER BY sort_order, name_es")->fetchAll();

$where = ["a.status='published'"];
$params = [];
if ($is_public) { $where[] = "a.is_public=1"; }
if ($cat) { $where[] = "a.category_id=?"; $params[] = $cat; }
if ($q) {
    $where[] = "(a.title_{$lang} LIKE ? OR a.content_{$lang} LIKE ?)";
    $lk = "%{$q}%"; $params[] = $lk; $params[] = $lk;
}
$w = implode(' AND ', $where);
$st = db()->prepare("SELECT a.*, u.name as author_name FROM kb_articles a LEFT JOIN users u ON a.created_by=u.id WHERE {$w} ORDER BY a.views DESC, a.created_at DESC");
$st->execute($params);
$articles = $st->fetchAll();

$page_title = t('knowledge_base');
if (!is_logged_in()) {
    // Public KB - show without sidebar
    $company_name = setting('company_name') ?: 'BT-Support'; ?>
<!DOCTYPE html><html lang="<?= $lang ?>"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= t('knowledge_base') ?> — <?= h($company_name) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>:root{--brand:<?= h(setting('company_color','#0d6efd')) ?>}</style>
</head><body style="background:#f4f6f9">
<nav class="navbar" style="background:var(--brand)">
  <div class="container"><a class="navbar-brand text-white fw-bold" href="#"><?= h($company_name) ?></a>
  <a href="<?= base_url('login') ?>" class="btn btn-sm btn-outline-light"><?= t('login') ?></a></div>
</nav>
<div class="container py-4">
<?php } else { include ROOT . '/templates/header.php'; } ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="bi bi-book me-2"></i><?= t('knowledge_base') ?></h4>
  <?php if (is_admin()): ?>
  <a href="<?= base_url('knowledge/manage') ?>" class="btn btn-sm btn-outline-primary"><?= t('edit') ?></a>
  <?php endif; ?>
</div>

<!-- Search -->
<div class="mb-4">
  <form method="get" class="d-flex gap-2">
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input type="text" name="q" class="form-control" placeholder="<?= t('search') ?> en la base de conocimiento..." value="<?= h($q) ?>">
    </div>
    <button type="submit" class="btn btn-primary"><?= t('search') ?></button>
    <?php if ($q): ?><a href="<?= base_url('knowledge') ?>" class="btn btn-outline-secondary">✕</a><?php endif; ?>
  </form>
</div>

<div class="row g-3">
  <!-- Categories sidebar -->
  <div class="col-md-3">
    <div class="list-group shadow-sm">
      <a href="<?= base_url('knowledge') ?>" class="list-group-item list-group-item-action <?= !$cat?'active':'' ?>">
        <i class="bi bi-grid me-2"></i><?= t('all') ?>
      </a>
      <?php foreach ($cats as $c): ?>
        <a href="?cat=<?= $c['id'] ?>" class="list-group-item list-group-item-action <?= $cat==$c['id']?'active':'' ?>">
          <i class="bi <?= h($c['icon']) ?> me-2"></i><?= h($c["name_{$lang}"]) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <!-- Articles -->
  <div class="col-md-9">
    <?php if (empty($articles)): ?>
      <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">
        <i class="bi bi-search fs-1 d-block opacity-25 mb-2"></i><?= t('no_results') ?>
      </div></div>
    <?php else: foreach ($articles as $art): ?>
    <div class="card border-0 shadow-sm mb-2">
      <div class="card-body py-3">
        <a href="<?= base_url('knowledge/article?id='.$art['id']) ?>" class="h6 text-decoration-none d-block mb-1">
          <i class="bi bi-file-text me-2 text-muted"></i><?= h($art["title_{$lang}"]) ?>
        </a>
        <div class="text-muted small text-truncate" style="max-width:600px">
          <?= h(strip_tags(substr($art["content_{$lang}"],0,150))) ?>...
        </div>
        <div class="text-muted small mt-1">
          <i class="bi bi-eye me-1"></i><?= $art['views'] ?> &nbsp;
          <i class="bi bi-hand-thumbs-up me-1"></i><?= $art['helpful_yes'] ?>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php if (!is_logged_in()): ?></div></body></html>
<?php else: include ROOT . '/templates/footer.php'; endif; ?>
