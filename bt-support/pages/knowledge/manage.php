<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin','agent']);
$lang = current_lang();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $aid    = (int)($_POST['aid'] ?? 0);
    $uid    = (int)$_SESSION['uid'];

    if ($action === 'save') {
        $title_es = trim($_POST['title_es'] ?? '');
        $title_en = trim($_POST['title_en'] ?? '');
        $body_es  = $_POST['body_es']  ?? '';
        $body_en  = $_POST['body_en']  ?? '';
        $cat_id   = (int)($_POST['cat_id'] ?? 0) ?: null;
        $status   = $_POST['status']   ?? 'draft';
        $public   = !empty($_POST['is_public']) ? 1 : 0;

        if ($aid) {
            db()->prepare("UPDATE kb_articles SET title_es=?,title_en=?,content_es=?,content_en=?,category_id=?,status=?,is_public=?,updated_at=NOW() WHERE id=?")
               ->execute([$title_es,$title_en,$body_es,$body_en,$cat_id,$status,$public,$aid]);
        } else {
            db()->prepare("INSERT INTO kb_articles (title_es,title_en,content_es,content_en,category_id,status,is_public,created_by) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$title_es,$title_en,$body_es,$body_en,$cat_id,$status,$public,$uid]);
        }
        flash('success', t('settings_saved'));
    }
    if ($action === 'delete' && $aid) {
        db()->prepare("DELETE FROM kb_articles WHERE id=?")->execute([$aid]);
        flash('success','Artículo eliminado.');
    }
    redirect(base_url('knowledge/manage'));
}

$articles = db()->query("SELECT a.*, u.name as author, kc.name_{$lang} as cat_name FROM kb_articles a LEFT JOIN users u ON a.created_by=u.id LEFT JOIN kb_categories kc ON a.category_id=kc.id ORDER BY a.created_at DESC")->fetchAll();
$cats     = db()->query("SELECT * FROM kb_categories ORDER BY name_es")->fetchAll();
$edit_id  = (int)($_GET['edit'] ?? 0);
$edit_art = null;
if ($edit_id) { $s=db()->prepare("SELECT * FROM kb_articles WHERE id=?"); $s->execute([$edit_id]); $edit_art=$s->fetch(); }

$page_title = t('knowledge_base') . ' — ' . t('edit');
include ROOT . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="fw-bold mb-0"><?= t('knowledge_base') ?></h4>
  <a href="?edit=0" class="btn btn-primary btn-sm"><i class="bi bi-plus me-1"></i><?= t('new_article') ?></a>
</div>

<?php if ($edit_art !== null || isset($_GET['edit'])): ?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white"><strong><?= $edit_art ? t('edit_article') : t('new_article') ?></strong></div>
  <div class="card-body p-4">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="aid" value="<?= $edit_art['id']??0 ?>">
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Título (ES) *</label>
          <input type="text" name="title_es" class="form-control" value="<?= h($edit_art['title_es']??'') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Title (EN) *</label>
          <input type="text" name="title_en" class="form-control" value="<?= h($edit_art['title_en']??'') ?>">
        </div>
      </div>
      <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#esArticle">🇪🇸 Español</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#enArticle">🇺🇸 English</a></li>
      </ul>
      <div class="tab-content mb-3">
        <div class="tab-pane fade show active" id="esArticle">
          <textarea name="body_es" class="form-control" rows="10"><?= h($edit_art['content_es']??'') ?></textarea>
          <small class="text-muted">Acepta HTML. Use &lt;p&gt;, &lt;h3&gt;, &lt;ul&gt;, &lt;code&gt;, &lt;img&gt;, etc.</small>
        </div>
        <div class="tab-pane fade" id="enArticle">
          <textarea name="body_en" class="form-control" rows="10"><?= h($edit_art['content_en']??'') ?></textarea>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label"><?= t('kb_category') ?></label>
          <select name="cat_id" class="form-select">
            <option value="">Sin categoría</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($edit_art['category_id']??'')==$c['id']?'selected':'' ?>><?= h($c["name_{$lang}"]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label"><?= t('status') ?></label>
          <select name="status" class="form-select">
            <option value="draft" <?= ($edit_art['status']??'draft')==='draft'?'selected':'' ?>><?= t('draft') ?></option>
            <option value="published" <?= ($edit_art['status']??'')==='published'?'selected':'' ?>><?= t('published') ?></option>
          </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_public" value="1" id="isPublic" <?= ($edit_art['is_public']??1)?'checked':'' ?>>
            <label class="form-check-label" for="isPublic"><?= t('public') ?></label>
          </div>
        </div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><?= t('save') ?></button>
        <a href="<?= base_url('knowledge/manage') ?>" class="btn btn-outline-secondary btn-sm"><?= t('cancel') ?></a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 small">
      <thead class="table-light"><tr><th>Título</th><th>Categoría</th><th><?= t('status') ?></th><th><?= t('views') ?></th><th>Útil</th><th><?= t('actions') ?></th></tr></thead>
      <tbody>
        <?php if (empty($articles)): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= t('no_results') ?></td></tr>
        <?php else: foreach ($articles as $a): ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= h($a["title_{$lang}"]) ?></div>
            <small class="text-muted"><?= h($a['author']) ?></small>
          </td>
          <td><?= h($a['cat_name']??'—') ?></td>
          <td><?= $a['status']==='published'?'<span class="badge bg-success">'.t('published').'</span>':'<span class="badge bg-secondary">'.t('draft').'</span>' ?>
            <?= !$a['is_public']?'<span class="badge bg-warning text-dark ms-1"><i class="bi bi-lock"></i></span>':'' ?></td>
          <td><?= $a['views'] ?></td>
          <td><i class="bi bi-hand-thumbs-up text-success me-1"></i><?= $a['helpful_yes'] ?> / <i class="bi bi-hand-thumbs-down text-danger me-1"></i><?= $a['helpful_no'] ?></td>
          <td>
            <a href="?edit=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-pencil"></i></a>
            <a href="<?= base_url('knowledge/article?id='.$a['id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye"></i></a>
            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="aid" value="<?= $a['id'] ?>">
              <button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include ROOT . '/templates/footer.php'; ?>
