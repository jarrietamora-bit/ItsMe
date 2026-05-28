<?php
if (!defined('BTSUPPORT')) exit;
$page_title = t('403_title');
include ROOT . '/templates/header.php'; ?>
<div class="d-flex flex-column align-items-center justify-content-center py-5">
  <i class="bi bi-shield-x text-danger mb-3" style="font-size:5rem"></i>
  <h4><?= t('403_title') ?></h4>
  <p class="text-muted"><?= t('403_msg') ?></p>
  <a href="<?= base_url('dashboard') ?>" class="btn btn-primary"><?= t('back') ?></a>
</div>
<?php include ROOT . '/templates/footer.php'; ?>
