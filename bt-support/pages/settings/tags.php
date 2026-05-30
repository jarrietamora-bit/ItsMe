<?php
if (!defined('BTSUPPORT')) exit;
require_role(['super_admin','admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $tid    = (int)($_POST['tid'] ?? 0);

    if ($action === 'save') {
        $name  = trim($_POST['name']  ?? '');
        $color = trim($_POST['color'] ?? '#6c757d');
        // Validate hex color
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#6c757d';
        if ($name) {
            if ($tid) {
                db()->prepare("UPDATE tags SET name=?, color=? WHERE id=?")->execute([$name, $color, $tid]);
            } else {
                db()->prepare("INSERT INTO tags (name, color) VALUES (?, ?)")->execute([$name, $color]);
            }
            flash('success', t('settings_saved'));
        }
    }

    if ($action === 'delete' && $tid) {
        db()->prepare("DELETE FROM ticket_tags WHERE tag_id=?")->execute([$tid]);
        db()->prepare("DELETE FROM tags WHERE id=?")->execute([$tid]);
        flash('success', t('deleted'));
    }

    if ($action === 'toggle' && $tid) {
        db()->prepare("UPDATE tags SET active=IF(active=1,0,1) WHERE id=?")->execute([$tid]);
    }

    redirect(base_url('settings/tags'));
}

$tags = db()->query("SELECT * FROM tags ORDER BY name")->fetchAll();
$edit_id  = (int)($_GET['edit'] ?? 0);
$edit_row = null;
if ($edit_id) {
    $s = db()->prepare("SELECT * FROM tags WHERE id=?");
    $s->execute([$edit_id]);
    $edit_row = $s->fetch();
}

$page_title = t('tags');
include ROOT . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><?= t('tags') ?></h4>
</div>

<div class="row g-4">
  <!-- Form -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><strong><?= $edit_row ? t('edit') : t('new_tag') ?></strong></div>
      <div class="card-body p-4">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="tid" value="<?= $edit_row['id'] ?? 0 ?>">

          <div class="mb-3">
            <label class="form-label"><?= t('name') ?> *</label>
            <input type="text" name="name" class="form-control" value="<?= h($edit_row['name'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label"><?= t('color') ?></label>
            <div class="input-group">
              <input type="color" name="color" class="form-control form-control-color" value="<?= h($edit_row['color'] ?? '#6c757d') ?>">
              <input type="text" id="colorHex" class="form-control" value="<?= h($edit_row['color'] ?? '#6c757d') ?>" readonly>
            </div>
            <div class="form-text"><?= t('hex_color_hint') ?></div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><?= $edit_row ? t('save') : t('create') ?></button>
            <?php if ($edit_row): ?>
              <a href="<?= base_url('settings/tags') ?>" class="btn btn-outline-secondary btn-sm"><?= t('cancel') ?></a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th><?= t('name') ?></th>
              <th><?= t('color') ?></th>
              <th><?= t('status') ?></th>
              <th><?= t('created_at') ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($tags)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4"><?= t('no_results') ?></td></tr>
            <?php else: foreach ($tags as $tag): ?>
            <tr>
              <td>
                <span class="badge" style="background:<?= h($tag['color']) ?>"><?= h($tag['name']) ?></span>
              </td>
              <td>
                <span class="d-inline-flex align-items-center gap-2">
                  <span style="display:inline-block;width:18px;height:18px;border-radius:3px;background:<?= h($tag['color']) ?>;border:1px solid rgba(0,0,0,.1)"></span>
                  <code><?= h($tag['color']) ?></code>
                </span>
              </td>
              <td>
                <?php if (($tag['active'] ?? 1) == 1): ?>
                  <span class="badge bg-success"><?= t('active') ?></span>
                <?php else: ?>
                  <span class="badge bg-secondary"><?= t('inactive') ?></span>
                <?php endif; ?>
              </td>
              <td class="text-muted"><?= format_datetime($tag['created_at']) ?></td>
              <td class="text-end">
                <a href="?edit=<?= $tag['id'] ?>" class="btn btn-sm btn-outline-secondary py-0">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="post" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="tid" value="<?= $tag['id'] ?>">
                  <button class="btn btn-sm btn-outline-warning py-0" title="<?= t('toggle_status') ?>">
                    <i class="bi bi-toggle-<?= ($tag['active'] ?? 1) == 1 ? 'on' : 'off' ?>"></i>
                  </button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="tid" value="<?= $tag['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger py-0">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
// Keep hex text in sync with color picker
document.querySelector('input[name="color"]')?.addEventListener('input', function () {
  document.getElementById('colorHex').value = this.value;
});
</script>
<?php include ROOT . '/templates/footer.php'; ?>
