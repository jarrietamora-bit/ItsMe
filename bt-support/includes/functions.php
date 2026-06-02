<?php
function base_url(string $path = ''): string {
    static $cfg = null;
    if ($cfg === null) $cfg = require __DIR__ . '/../config/config.php';
    $configured = rtrim($cfg['app_url'] ?? '', '/');

    // Auto-detect if not configured or still pointing to localhost/127.0.0.1
    if (!$configured || str_contains($configured, 'localhost') || str_contains($configured, '127.0.0.1')) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Detect subfolder by looking at SCRIPT_NAME
        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $folder = rtrim(dirname($script), '/');
        $configured = $scheme . '://' . $host . $folder;
    }

    return $configured . ($path ? '/' . ltrim($path, '/') : '');
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function setting(string $key, string $default = ''): string {
    static $settings = null;
    if ($settings === null) {
        try {
            $rows = db()->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
            $settings = array_column($rows, 'setting_value', 'setting_key');
        } catch (\Throwable $e) {
            $settings = [];
        }
    }
    return $settings[$key] ?? $default;
}

function setting_set(string $key, string $value): void {
    $st = db()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $st->execute([$key, $value]);
}

function generate_ticket_number(): string {
    $year = date('Y');
    $prefix = 'TKT-' . $year . '-';
    $st = db()->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(ticket_number, '-', -1) AS UNSIGNED)) FROM tickets WHERE ticket_number LIKE ?");
    $st->execute([$prefix . '%']);
    $max  = (int)$st->fetchColumn();
    $next = $max + 1;
    return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
}

function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 0) return date('d/m/Y', strtotime($datetime));
    if ($diff < 60)      return t('just_now');
    if ($diff < 3600)    return floor($diff/60)   . ' ' . t('minutes_ago');
    if ($diff < 86400)   return floor($diff/3600)  . ' ' . t('hours_ago');
    if ($diff < 604800)  return floor($diff/86400)  . ' ' . t('days_ago');
    return date('d/m/Y', strtotime($datetime));
}

function format_datetime(string $datetime): string {
    return date('d/m/Y H:i', strtotime($datetime));
}

function format_filesize(int $bytes): string {
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1048576)    return round($bytes/1024, 1) . ' KB';
    return round($bytes/1048576, 1) . ' MB';
}

function status_badge(string $status): string {
    $map = [
        'open'        => ['primary',  t('status_open')],
        'in_progress' => ['warning',  t('status_in_progress')],
        'waiting'     => ['secondary',t('status_waiting')],
        'resolved'    => ['success',  t('status_resolved')],
        'closed'      => ['dark',     t('status_closed')],
    ];
    [$color, $label] = $map[$status] ?? ['light', $status];
    return "<span class=\"badge bg-{$color}\">{$label}</span>";
}

function priority_badge(string $name, string $color): string {
    return "<span class=\"badge\" style=\"background:{$color}\">" . h($name) . "</span>";
}

function role_label(string $role): string {
    $map = [
        'super_admin' => t('role_super_admin'),
        'admin'       => t('role_admin'),
        'supervisor'  => t('role_supervisor'),
        'agent'       => t('role_agent'),
        'client'      => t('role_client'),
    ];
    return $map[$role] ?? $role;
}

function role_badge(string $role): string {
    $colors = [
        'super_admin' => 'danger',
        'admin'       => 'warning',
        'supervisor'  => 'info',
        'agent'       => 'primary',
        'client'      => 'secondary',
    ];
    $c = $colors[$role] ?? 'secondary';
    return "<span class=\"badge bg-{$c}\">" . role_label($role) . "</span>";
}

function upload_file(array $file, string $dir, array $allowed_types = []): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $cfg = require __DIR__ . '/../config/config.php';
    $max_bytes = ($cfg['upload_max_mb'] ?? 10) * 1024 * 1024;
    if ($file['size'] > $max_bytes) return null;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($allowed_types && !in_array($ext, $allowed_types, true)) return null;

    $safe_types = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','zip','txt','csv'];
    if (!in_array($ext, $safe_types, true)) return null;

    $dest_dir = __DIR__ . '/../uploads/' . trim($dir, '/');
    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);

    $new_name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dest_dir . '/' . $new_name)) return null;
    return $new_name;
}

function send_notification(int $user_id, string $type, string $title, string $message = '', string $url = ''): void {
    try {
        db()->prepare("INSERT INTO notifications (user_id, type, title, message, url) VALUES (?,?,?,?,?)")
            ->execute([$user_id, $type, $title, $message, $url]);
    } catch (\Throwable $e) {}
}

function log_activity(string $action, string $entity_type = '', int $entity_id = 0, string $details = ''): void {
    $uid = $_SESSION['uid'] ?? null;
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
    try {
        db()->prepare("INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?,?,?,?,?,?)")
            ->execute([$uid, $action, $entity_type, $entity_id ?: null, $details, $ip]);
    } catch (\Throwable $e) {}
}

function paginate(int $total, int $per_page, int $current_page): array {
    if ($per_page <= 0) $per_page = 25;
    $total_pages = (int)ceil($total / $per_page);
    $offset = max(0, ($current_page - 1) * $per_page);
    return ['total' => $total, 'per_page' => $per_page, 'current' => $current_page, 'total_pages' => $total_pages, 'offset' => $offset];
}

function can_view_ticket(array $ticket): bool {
    $user = current_user();
    if (!$user) return false;
    $role = $user['role'];
    if (in_array($role, ['super_admin', 'admin'], true)) return true;
    if ($role === 'supervisor') {
        // Check if ticket dept belongs to supervisor's departments
        $st = db()->prepare("SELECT 1 FROM department_users WHERE user_id = ? AND department_id = ? AND is_supervisor = 1");
        $st->execute([$user['id'], $ticket['department_id']]);
        return (bool)$st->fetch();
    }
    if ($role === 'agent') return $ticket['assigned_to'] == $user['id'];
    if ($role === 'client') return $ticket['created_by'] == $user['id'];
    return false;
}

function get_user_departments(int $user_id): array {
    $st = db()->prepare("SELECT d.* FROM departments d JOIN department_users du ON d.id = du.department_id WHERE du.user_id = ? AND d.status = 'active'");
    $st->execute([$user_id]);
    return $st->fetchAll();
}

function _t_lang(string $key, string $lang, ...$args): string {
    static $cache = [];
    if (!isset($cache[$lang])) {
        $file = __DIR__ . "/../languages/{$lang}.php";
        $cache[$lang] = file_exists($file) ? (require $file) : [];
    }
    $str = $cache[$lang][$key] ?? $key;
    return $args ? vsprintf($str, $args) : $str;
}

function check_sla_breach(): void {
    // Find tickets that are NEWLY breaching (not yet marked)
    $st = db()->query(
        "SELECT t.id, t.ticket_number, t.subject, t.department_id, t.assigned_to
         FROM tickets t
         WHERE t.sla_due_at IS NOT NULL
           AND t.sla_due_at < NOW()
           AND t.status NOT IN ('resolved','closed')
           AND t.sla_breached = 0"
    );
    $newly_breached = $st->fetchAll();

    if (empty($newly_breached)) return;

    // Mark all as breached in one query
    $ids = implode(',', array_map('intval', array_column($newly_breached, 'id')));
    db()->exec("UPDATE tickets SET sla_breached = 1 WHERE id IN ({$ids})");

    // Send escalation notifications if enabled
    if (setting('sla_escalation_enabled') !== '1') return;

    $notify_whom = setting('sla_escalation_notify') ?: 'supervisor'; // supervisor | admin | both

    foreach ($newly_breached as $ticket) {
        $url  = base_url('tickets/view?id=' . $ticket['id']);
        $num  = $ticket['ticket_number'];
        $subj = $ticket['subject'];
        $title = sprintf(t('sla_breach_notif_title'), $num);

        $notified_ids = [];

        // Notify supervisors of the ticket's department
        if ($notify_whom !== 'admin' && $ticket['department_id']) {
            $sup_st = db()->prepare(
                "SELECT u.id, u.email, u.name, u.language FROM users u
                 JOIN department_users du ON u.id = du.user_id
                 WHERE du.department_id = ? AND du.is_supervisor = 1 AND u.status = 'active'"
            );
            $sup_st->execute([$ticket['department_id']]);
            foreach ($sup_st->fetchAll() as $sup) {
                send_notification($sup['id'], 'sla_breach', $title, $subj, $url);
                $notified_ids[] = $sup['id'];
                $lang = $sup['language'] ?? 'es';
                try {
                    mailer()->send(
                        $sup['email'],
                        _t_lang('sla_breach_email_subject', $lang, $num),
                        _t_lang('sla_breach_email_body_sup', $lang, $num, htmlspecialchars($subj), $url),
                        $sup['name']
                    );
                } catch (\Throwable $e) {}
            }
        }

        // Notify admins
        if ($notify_whom !== 'supervisor') {
            $adm_st = db()->query("SELECT id, email, name, language FROM users WHERE role IN ('super_admin','admin') AND status='active'");
            foreach ($adm_st->fetchAll() as $adm) {
                if (in_array($adm['id'], $notified_ids, true)) continue;
                send_notification($adm['id'], 'sla_breach', $title, $subj, $url);
                $lang = $adm['language'] ?? 'es';
                try {
                    mailer()->send(
                        $adm['email'],
                        _t_lang('sla_breach_email_subject', $lang, $num),
                        _t_lang('sla_breach_email_body_adm', $lang, $num, htmlspecialchars($subj), $url),
                        $adm['name']
                    );
                } catch (\Throwable $e) {}
            }
        }
    }
}

