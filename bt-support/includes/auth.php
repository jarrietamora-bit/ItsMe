<?php
require_once __DIR__ . '/../config/database.php';

function auth_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Use app-local session storage to avoid cPanel /tmp permission issues
        $sess_dir = __DIR__ . '/../storage/sessions';
        if (!is_dir($sess_dir)) @mkdir($sess_dir, 0700, true);
        if (is_writable($sess_dir)) session_save_path($sess_dir);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    // Remember-me auto-login
    if (!isset($_SESSION['uid']) && isset($_COOKIE['remember_token'])) {
        $token_hash = hash('sha256', $_COOKIE['remember_token']);
        $st_rm = db()->prepare("SELECT * FROM users WHERE remember_token = ? AND status = 'active'");
        $st_rm->execute([$token_hash]);
        $rm_user = $st_rm->fetch();
        $secure  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        if ($rm_user) {
            $new_token = bin2hex(random_bytes(32));
            setcookie('remember_token', $new_token, time() + 30 * 86400, '/', '', $secure, true);
            db()->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([hash('sha256', $new_token), $rm_user['id']]);
            session_regenerate_id(true);
            $_SESSION['uid']  = $rm_user['id'];
            $_SESSION['role'] = $rm_user['role'];
            $_SESSION['lang'] = $rm_user['language'];
            $_SESSION['last_activity'] = time();
        } else {
            setcookie('remember_token', '', time() - 3600, '/', '', $secure, true);
        }
    }
    // Check session timeout
    $cfg = require __DIR__ . '/../config/config.php';
    $timeout = ($cfg['session_timeout'] ?? 120) * 60;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        session_start();
    }
    if (isset($_SESSION['uid'])) {
        $_SESSION['last_activity'] = time();
    }
}

function is_logged_in(): bool {
    return isset($_SESSION['uid']);
}

function current_user(): ?array {
    if (!isset($_SESSION['uid'])) return null;
    static $user = null;
    if ($user === null) {
        $st = db()->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
        $st->execute([$_SESSION['uid']]);
        $user = $st->fetch() ?: null;
    }
    return $user;
}

function current_role(): string {
    return $_SESSION['role'] ?? '';
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . base_url('login'));
        exit;
    }
}

function require_role(array $roles): void {
    require_login();
    if (!in_array(current_role(), $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../pages/403.php';
        exit;
    }
}

function is_admin(): bool {
    return in_array(current_role(), ['super_admin', 'admin'], true);
}

function is_supervisor(): bool {
    return in_array(current_role(), ['super_admin', 'admin', 'supervisor'], true);
}

function is_agent(): bool {
    return in_array(current_role(), ['super_admin', 'admin', 'supervisor', 'agent'], true);
}

function login_user(string $email, string $password, bool $remember = false): bool {
    // Rate limiting
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $st = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $st->execute([$email, $ip]);
    if ((int)$st->fetchColumn() >= 5) return false;

    $st = db()->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $st->execute([$email]);
    $user = $st->fetch();

    // Log attempt
    db()->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?,?)")->execute([$email, $ip]);

    if (!$user || !password_verify($password, $user['password'])) return false;

    // Clear old attempts on success
    db()->prepare("DELETE FROM login_attempts WHERE email = ?")->execute([$email]);
    db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

    session_regenerate_id(true);
    $_SESSION['uid']  = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['lang'] = $user['language'];
    $_SESSION['last_activity'] = time();

    if ($remember) {
        $token  = bin2hex(random_bytes(32));
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('remember_token', $token, time() + 30 * 86400, '/', '', $secure, true);
        db()->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([hash('sha256', $token), $user['id']]);
    }
    return true;
}

function logout_user(): void {
    if (isset($_COOKIE['remember_token'])) {
        db()->prepare("UPDATE users SET remember_token = NULL WHERE id = ?")->execute([$_SESSION['uid'] ?? 0]);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('remember_token', '', time() - 3600, '/', '', $secure, true);
    }
    session_unset();
    session_destroy();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): bool {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}
