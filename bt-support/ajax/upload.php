<?php
if (!defined('BTSUPPORT')) exit;
require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!csrf_verify()) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$ticket_id = (int)($_POST['ticket_id'] ?? 0);
if (!$ticket_id) {
    echo json_encode(['success' => false, 'error' => 'Missing ticket_id']);
    exit;
}

$chk = db()->prepare("SELECT id, created_by, assigned_to FROM tickets WHERE id=?");
$chk->execute([$ticket_id]);
$chk_ticket = $chk->fetch();
if (!$chk_ticket || !can_view_ticket($chk_ticket)) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['success' => false, 'error' => 'Upload error: ' . $err]);
    exit;
}

$uid      = (int)$_SESSION['uid'];
$orig     = $_FILES['file']['name'];
$size     = (int)$_FILES['file']['size'];
$mime     = $_FILES['file']['type'] ?? 'application/octet-stream';

$fname = upload_file($_FILES['file'], 'tickets/' . $ticket_id);
if (!$fname) {
    echo json_encode(['success' => false, 'error' => 'File upload failed']);
    exit;
}

db()->prepare("INSERT INTO ticket_attachments (ticket_id, reply_id, user_id, filename, original_name, file_size, mime_type) VALUES (?, NULL, ?, ?, ?, ?, ?)")
   ->execute([$ticket_id, $uid, $fname, $orig, $size, $mime]);

$att_id = (int)db()->lastInsertId();

echo json_encode([
    'success'       => true,
    'id'            => $att_id,
    'filename'      => $fname,
    'original_name' => $orig,
    'size_formatted'=> format_filesize($size),
]);
