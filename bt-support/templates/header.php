<?php
if (!defined('BTSUPPORT')) { http_response_code(403); exit; }

$page_title    = $page_title ?? t('app_name');
$company_name  = setting('company_name') ?: 'BT-Support';
$company_color = setting('company_color') ?: '#0d6efd';
$company_logo  = setting('company_logo');
$logo_url      = $company_logo ? base_url('uploads/logos/' . $company_logo) : '';
$user          = current_user();
$lang          = current_lang();

// Unread notifications
$unread_count = 0;
if ($user) {
    $st = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $st->execute([$user['id']]);
    $unread_count = (int)$st->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title) ?> — <?= h($company_name) ?></title>
<?php if ($favicon = setting('favicon')): ?>
<link rel="icon" type="image/x-icon" href="<?= base_url('uploads/logos/' . $favicon) ?>">
<?php endif; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
<style>
  :root { --bs-primary: <?= h($company_color) ?>; --brand-color: <?= h($company_color) ?>; }
  .navbar-brand-logo { max-height: 36px; }
  .topbar { background: var(--brand-color) !important; }
</style>
</head>
<body>

<!-- Top navbar -->
<nav class="navbar navbar-expand-lg topbar navbar-dark shadow-sm px-3 py-0" style="min-height:56px">
  <button class="btn btn-link text-white me-2 d-lg-none" id="sidebarToggle">
    <i class="bi bi-list fs-5"></i>
  </button>

  <a class="navbar-brand d-flex align-items-center gap-2" href="<?= base_url('dashboard') ?>">
    <?php if ($logo_url): ?>
      <img src="<?= h($logo_url) ?>" alt="<?= h($company_name) ?>" class="navbar-brand-logo">
    <?php else: ?>
      <i class="bi bi-headset fs-4"></i>
      <span class="fw-bold"><?= h($company_name) ?></span>
    <?php endif; ?>
  </a>

  <div class="ms-auto d-flex align-items-center gap-2">
    <!-- Language switcher -->
    <div class="dropdown">
      <button class="btn btn-link text-white-50 text-decoration-none btn-sm" data-bs-toggle="dropdown">
        <i class="bi bi-translate"></i> <?= strtoupper($lang) ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item <?= $lang==='es'?'active':'' ?>" href="?lang=es"><span class="fi fi-cr me-2"></span><?= t('lang_es') ?></a></li>
        <li><a class="dropdown-item <?= $lang==='en'?'active':'' ?>" href="?lang=en"><span class="fi fi-us me-2"></span><?= t('lang_en') ?></a></li>
      </ul>
    </div>

    <!-- Notifications -->
    <div class="dropdown">
      <button class="btn btn-link text-white position-relative" data-bs-toggle="dropdown" id="notifBtn">
        <i class="bi bi-bell fs-5"></i>
        <?php if ($unread_count > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:10px">
            <?= $unread_count > 99 ? '99+' : $unread_count ?>
          </span>
        <?php endif; ?>
      </button>
      <div class="dropdown-menu dropdown-menu-end p-0 shadow" style="min-width:320px;max-height:400px;overflow-y:auto" id="notifDropdown">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
          <strong><?= t('notifications') ?></strong>
          <a href="#" class="text-decoration-none small" id="markAllRead"><?= t('mark_all_read') ?></a>
        </div>
        <div id="notifList"><div class="text-center text-muted p-3 small"><?= t('loading') ?></div></div>
      </div>
    </div>

    <!-- User menu -->
    <div class="dropdown">
      <button class="btn btn-link text-white d-flex align-items-center gap-2 text-decoration-none" data-bs-toggle="dropdown">
        <?php if ($user && $user['avatar']): ?>
          <img src="<?= base_url('uploads/avatars/' . h($user['avatar'])) ?>" class="rounded-circle" width="30" height="30" style="object-fit:cover">
        <?php else: ?>
          <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center fw-bold" style="width:30px;height:30px;font-size:13px">
            <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
          </div>
        <?php endif; ?>
        <span class="d-none d-md-inline small"><?= h($user['name'] ?? '') ?></span>
        <i class="bi bi-chevron-down small"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow">
        <li><span class="dropdown-item-text small text-muted"><?= role_badge($user['role'] ?? '') ?></span></li>
        <li><hr class="dropdown-divider m-0"></li>
        <li><a class="dropdown-item" href="<?= base_url('profile') ?>"><i class="bi bi-person me-2"></i><?= t('profile') ?></a></li>
        <li><hr class="dropdown-divider m-0"></li>
        <li><a class="dropdown-item text-danger" href="<?= base_url('logout') ?>"><i class="bi bi-box-arrow-right me-2"></i><?= t('logout') ?></a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="d-flex" style="min-height:calc(100vh - 56px)">
<?php include ROOT . '/templates/sidebar.php'; ?>
<main class="flex-grow-1 p-4" style="background:#f4f6f9;min-width:0">
<?php foreach (get_flash() as $f): ?>
  <div class="alert alert-<?= h($f['type']) ?> alert-dismissible fade show" role="alert">
    <?= h($f['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endforeach; ?>
