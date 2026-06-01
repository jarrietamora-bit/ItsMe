/* BT-Support Main JS */
'use strict';

// Sidebar toggle (mobile)
document.addEventListener('DOMContentLoaded', () => {
  const toggle  = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');

  // Create overlay
  const overlay = document.createElement('div');
  overlay.className = 'sidebar-overlay';
  document.body.appendChild(overlay);

  if (toggle && sidebar) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('show');
    });
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('show');
    });
  }

  // Load notifications
  loadNotifications();

  // Notification polling every 30s
  setInterval(loadNotifications, 30000);

  // Mark all read
  document.getElementById('markAllRead')?.addEventListener('click', e => {
    e.preventDefault();
    fetch(`${BASE_URL}/ajax/notifications?action=mark_all_read`)
      .then(() => loadNotifications());
  });

  // Auto-dismiss alerts after 5s
  document.querySelectorAll('.alert.alert-success, .alert.alert-info').forEach(a => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(a);
      bsAlert?.close();
    }, 5000);
  });

  // Delete confirmations
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });
});

// Load notifications dropdown
function loadNotifications() {
  fetch(`${BASE_URL}/ajax/notifications?action=list`)
    .then(r => r.json())
    .then(data => {
      const list = document.getElementById('notifList');
      if (!list) return;

      const items = data.notifications || [];
      if (!items.length) {
        list.innerHTML = `<div class="text-center text-muted p-3 small">${typeof t !== 'undefined' ? '' : 'No hay notificaciones nuevas.'}</div>`;
        return;
      }

      list.innerHTML = items.map(n => `
        <a href="${n.url || '#'}" class="d-block text-decoration-none border-bottom px-3 py-2 ${n.is_read ? 'text-muted' : 'fw-semibold'}"
           onclick="markRead(${n.id})">
          <div class="d-flex align-items-start gap-2">
            <i class="bi bi-${notifIcon(n.type)} mt-1 flex-shrink-0 ${n.is_read ? 'text-muted' : 'text-primary'}"></i>
            <div>
              <div class="small">${escHtml(n.title)}</div>
              <div class="text-muted" style="font-size:11px">${n.created_at}</div>
            </div>
            ${!n.is_read ? '<span class="ms-auto mt-1 rounded-circle bg-primary flex-shrink-0" style="width:8px;height:8px"></span>' : ''}
          </div>
        </a>`).join('');

      // Update unread badge
      const unread = items.filter(n => !n.is_read).length;
      const badge  = document.querySelector('#notifBtn .badge');
      if (badge) {
        badge.textContent = unread > 99 ? '99+' : unread;
        badge.style.display = unread ? '' : 'none';
      }
    })
    .catch(() => {});
}

function markRead(id) {
  fetch(`${BASE_URL}/ajax/notifications?action=mark_read&id=${id}`).catch(() => {});
}

function notifIcon(type) {
  const icons = {
    new_ticket:  'ticket-detailed',
    ticket_reply:'chat-dots',
    assigned:    'person-check',
    resolved:    'check-circle',
    sla_warning: 'alarm',
  };
  return icons[type] || 'bell';
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Confirm before delete forms
document.addEventListener('submit', e => {
  const form = e.target;
  if (form.dataset.confirm) {
    if (!confirm(form.dataset.confirm)) e.preventDefault();
  }
});
