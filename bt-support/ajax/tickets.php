<?php
if (!defined('BTSUPPORT')) exit;
require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'kb_suggest') {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        echo json_encode([]);
        exit;
    }
    $lang  = current_lang();
    $col   = $lang === 'en' ? 'title_en' : 'title_es';
    $like  = '%' . $q . '%';
    $st    = db()->prepare("SELECT id, {$col} AS title FROM kb_articles WHERE status='published' AND {$col} LIKE ? LIMIT 5");
    $st->execute([$like]);
    $rows  = $st->fetchAll(PDO::FETCH_ASSOC);
    $out   = [];
    foreach ($rows as $r) {
        $out[] = ['id' => (int)$r['id'], 'title' => $r['title']];
    }
    echo json_encode($out);
    exit;
}

if ($action === 'tag_search') {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        echo json_encode([]);
        exit;
    }
    $like = '%' . $q . '%';
    $st   = db()->prepare("SELECT name FROM tags WHERE name LIKE ? ORDER BY name LIMIT 10");
    $st->execute([$like]);
    $names = $st->fetchAll(PDO::FETCH_COLUMN, 0);
    echo json_encode($names);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
