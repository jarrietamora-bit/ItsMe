<?php
define('ROOT', __DIR__);
define('BTSUPPORT', true);

// Check installation
if (!file_exists(ROOT . '/config/config.php')) {
    header('Location: install/');
    exit;
}

$cfg = require ROOT . '/config/config.php';
if (empty($cfg['installed'])) {
    header('Location: install/');
    exit;
}

// Set timezone
date_default_timezone_set($cfg['timezone'] ?? 'America/Costa_Rica');

// Autoload core
require ROOT . '/config/database.php';
require ROOT . '/includes/functions.php';
require ROOT . '/includes/auth.php';
require ROOT . '/includes/lang.php';
require ROOT . '/includes/mail.php';

// Start session & language
auth_start();
load_lang();

// SLA check (lightweight, runs once per request)
if (is_logged_in()) {
    try { check_sla_breach(); } catch (\Throwable $e) {}
}

// Language switcher
if (isset($_GET['lang'])) {
    switch_lang($_GET['lang']);
    $back = $_SERVER['HTTP_REFERER'] ?? base_url();
    redirect($back);
}

// Parse page from URL
$url  = trim($_GET['_url'] ?? $_GET['page'] ?? '', '/');
if ($url === '') $url = 'dashboard';

// Router map
$routes = [
    // Public
    'login'                   => 'pages/login.php',
    'logout'                  => 'pages/logout.php',
    'register'                => 'pages/register.php',
    'forgot-password'         => 'pages/forgot-password.php',
    'reset-password'          => 'pages/reset-password.php',
    'knowledge'               => 'pages/knowledge/index.php',
    'knowledge/article'       => 'pages/knowledge/article.php',

    // Authenticated
    'dashboard'               => 'pages/dashboard.php',
    'profile'                 => 'pages/profile.php',

    // Tickets
    'tickets'                 => 'pages/tickets/index.php',
    'tickets/create'          => 'pages/tickets/create.php',
    'tickets/view'            => 'pages/tickets/view.php',
    'tickets/edit'            => 'pages/tickets/edit.php',

    // Users (admin)
    'users'                   => 'pages/users/index.php',
    'users/create'            => 'pages/users/create.php',
    'users/edit'              => 'pages/users/edit.php',

    // Departments
    'departments'             => 'pages/departments/index.php',
    'departments/create'      => 'pages/departments/create.php',
    'departments/edit'        => 'pages/departments/edit.php',

    // Categories
    'categories'              => 'pages/categories/index.php',

    // Knowledge manage
    'knowledge/manage'        => 'pages/knowledge/manage.php',
    'knowledge/edit'          => 'pages/knowledge/edit.php',

    // Reports
    'reports'                 => 'pages/reports/index.php',

    // Settings
    'settings/general'        => 'pages/settings/general.php',
    'settings/email'          => 'pages/settings/email.php',
    'settings/sla'            => 'pages/settings/sla.php',
    'settings/templates'      => 'pages/settings/templates.php',
    'settings/canned'         => 'pages/settings/canned.php',

    // AJAX
    'ajax/notifications'      => 'ajax/notifications.php',
    'ajax/tickets'            => 'ajax/tickets.php',
    'ajax/upload'             => 'ajax/upload.php',
];

$page_file = $routes[$url] ?? null;

// Handle 404
if (!$page_file || !file_exists(ROOT . '/' . $page_file)) {
    http_response_code(404);
    include ROOT . '/pages/404.php';
    exit;
}

// Handle public pages (no auth needed)
$public = ['login','logout','register','forgot-password','reset-password','knowledge','knowledge/article'];

if (!in_array($url, $public, true) && !is_logged_in()) {
    redirect(base_url('login'));
}

// If logged in and trying to access login
if (is_logged_in() && $url === 'login') {
    redirect(base_url('dashboard'));
}

include ROOT . '/' . $page_file;
