<?php
if (!defined('BTSUPPORT')) exit;
require_login();
header('Content-Type: application/json');

$uid    = (int)$_SESSION['uid'];
$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $st = db()->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
    $st->execute([$uid]);
    $notifs = $st->fetchAll();
    $out = [];
    foreach ($notifs as $n) {
        $out[] = [
            'id'         => $n['id'],
            'type'       => $n['type'],
            'title'      => $n['title'],
            'message'    => $n['message'],
            'url'        => $n['url'],
            'is_read'    => (bool)$n['is_read'],
            'created_at' => time_ago($n['created_at']),
        ];
    }
    echo json_encode(['notifications' => $out]);
    exit;
}

if ($action === 'mark_read' && isset($_GET['id'])) {
    $nid = (int)$_GET['id'];
    db()->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$nid,$uid]);
    echo json_encode(['ok'=>true]);
    exit;
}

if ($action === 'mark_all_read') {
    db()->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
    echo json_encode(['ok'=>true]);
    exit;
}

if ($action === 'count') {
    $cnt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $cnt->execute([$uid]);
    echo json_encode(['count' => (int)$cnt->fetchColumn()]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
