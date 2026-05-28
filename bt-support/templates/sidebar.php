<?php
if (!defined('BTSUPPORT')) exit;
$role = current_role();
$cur  = $url ?? '';
$company_color = setting('company_color') ?: '#0d6efd';

function nav_item(string $icon, string $label, string $page, string $cur): string {
    $active = ($cur === $page || str_starts_with($cur, $page . '/') ) ? 'active' : '';
    return '<li class="nav-item"><a class="nav-link ' . $active . '" href="' . base_url($page) . '"><i class="bi bi-' . $icon . ' me-2"></i>' . $label . '</a></li>';
}
?>
<aside class="sidebar d-flex flex-column flex-shrink-0 shadow-sm" id="sidebar" style="width:240px;background:#fff;border-right:1px solid #e9ecef">
  <div class="overflow-auto flex-grow-1 py-3 px-2">
    <ul class="nav nav-pills flex-column gap-1">

      <!-- Dashboard -->
      <?= nav_item('speedometer2', t('dashboard'), 'dashboard', $cur) ?>

      <!-- Tickets -->
      <li class="nav-item mt-2">
        <small class="text-uppercase text-muted fw-semibold ps-2" style="font-size:10px;letter-spacing:.5px"><?= t('tickets') ?></small>
      </li>
      <?= nav_item('ticket-detailed', t('tickets'), 'tickets', $cur) ?>
      <?= nav_item('plus-circle', t('new_ticket'), 'tickets/create', $cur) ?>

      <?php if (is_agent()): ?>
      <!-- Knowledge Base -->
      <li class="nav-item mt-2">
        <small class="text-uppercase text-muted fw-semibold ps-2" style="font-size:10px;letter-spacing:.5px"><?= t('knowledge_base') ?></small>
      </li>
      <?= nav_item('book', t('knowledge_base'), 'knowledge', $cur) ?>
      <?php endif; ?>

      <?php if (is_supervisor()): ?>
      <!-- Supervisor: Reports -->
      <li class="nav-item mt-2">
        <small class="text-uppercase text-muted fw-semibold ps-2" style="font-size:10px;letter-spacing:.5px"><?= t('reports') ?></small>
      </li>
      <?= nav_item('bar-chart', t('reports'), 'reports', $cur) ?>
      <?php endif; ?>

      <?php if (is_admin()): ?>
      <!-- Admin section -->
      <li class="nav-item mt-2">
        <small class="text-uppercase text-muted fw-semibold ps-2" style="font-size:10px;letter-spacing:.5px"><?= t('users') ?></small>
      </li>
      <?= nav_item('people', t('users'), 'users', $cur) ?>
      <?= nav_item('diagram-3', t('departments'), 'departments', $cur) ?>
      <?= nav_item('tags', t('categories'), 'categories', $cur) ?>
      <?= nav_item('book-half', t('knowledge_base'), 'knowledge/manage', $cur) ?>

      <!-- Settings -->
      <li class="nav-item mt-2">
        <small class="text-uppercase text-muted fw-semibold ps-2" style="font-size:10px;letter-spacing:.5px"><?= t('settings') ?></small>
      </li>

      <!-- Settings dropdown -->
      <li class="nav-item">
        <a class="nav-link <?= str_starts_with($cur,'settings') ? 'active' : '' ?> d-flex justify-content-between"
           data-bs-toggle="collapse" href="#settingsMenu">
          <span><i class="bi bi-gear me-2"></i><?= t('settings') ?></span>
          <i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse <?= str_starts_with($cur,'settings') ? 'show' : '' ?>" id="settingsMenu">
          <ul class="nav flex-column ps-3">
            <li><?= nav_item('building', t('general_settings'), 'settings/general', $cur) ?></li>
            <li><?= nav_item('envelope', t('email_settings'), 'settings/email', $cur) ?></li>
            <li><?= nav_item('clock-history', t('sla_settings'), 'settings/sla', $cur) ?></li>
            <li><?= nav_item('file-text', t('email_templates'), 'settings/templates', $cur) ?></li>
            <li><?= nav_item('chat-right-text', t('canned_responses'), 'settings/canned', $cur) ?></li>
          </ul>
        </div>
      </li>
      <?php endif; ?>

    </ul>
  </div>

  <!-- Sidebar footer -->
  <div class="p-3 border-top">
    <div class="d-flex align-items-center gap-2">
      <?php if ($u = current_user()): ?>
        <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
             style="width:32px;height:32px;background:var(--brand-color,#0d6efd);font-size:13px">
          <?= strtoupper(substr($u['name'],0,1)) ?>
        </div>
        <div class="overflow-hidden">
          <div class="fw-semibold text-truncate small"><?= h($u['name']) ?></div>
          <div class="text-muted" style="font-size:11px"><?= role_label($u['role']) ?></div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</aside>
