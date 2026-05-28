<?php
function base_url(string $path = ''): string {
    static $cfg = null;
    if ($cfg === null) $cfg = require __DIR__ . '/../config/config.php';
    $base = rtrim($cfg['app_url'], '/');
    return $base . ($path ? '/' . ltrim($path, '/') : '');
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
    $st = db()->prepare("SELECT COUNT(*) FROM tickets WHERE YEAR(created_at) = ?");
    $st->execute([$year]);
    $count = (int)$st->fetchColumn() + 1;
    return 'TKT-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
}

function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
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
    $total_pages = (int)ceil($total / $per_page);
    $offset = ($current_page - 1) * $per_page;
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

function check_sla_breach(): void {
    db()->exec("UPDATE tickets SET sla_breached = 1 WHERE sla_due_at IS NOT NULL AND sla_due_at < NOW() AND status NOT IN ('resolved','closed') AND sla_breached = 0");
}
