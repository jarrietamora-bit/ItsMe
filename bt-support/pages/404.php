<?php
if (!defined('BTSUPPORT')) exit;
$page_title = t('404_title');
if (is_logged_in()) include ROOT . '/templates/header.php';
else { $c = setting('company_color','#0d6efd'); echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'></head><body style='background:#f4f6f9'>"; }
?>
<div class="d-flex flex-column align-items-center justify-content-center py-5">
  <div class="text-muted mb-3" style="font-size:6rem;font-weight:900;line-height:1">404</div>
  <h4><?= t('404_title') ?></h4>
  <p class="text-muted"><?= t('404_msg') ?></p>
  <a href="<?= base_url(is_logged_in() ? 'dashboard' : 'login') ?>" class="btn btn-primary"><?= t('back') ?></a>
</div>
<?php if (is_logged_in()) include ROOT . '/templates/footer.php';
else echo "</body></html>"; ?>
