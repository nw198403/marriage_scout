<?php
// chat_file.php：スカウトのメッセージに添付されたファイルを、当事者（その会員・そのカウンセラー）だけに見せる

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/upload.php';

$user = require_login();
$pdo = db();

$mid = (int)($_GET['mid'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT sm.attachment_path, sm.attachment_original, sm.deleted_at, s.member_id, s.counselor_id
     FROM scout_messages sm JOIN scouts s ON s.id = sm.scout_id WHERE sm.id = ?'
);
$stmt->execute([$mid]);
$row = $stmt->fetch();

$allowed = $row && $row['deleted_at'] === null && (
    ($user['role'] === 'member' && (int)$row['member_id'] === (int)$user['id']) ||
    ($user['role'] === 'counselor' && (int)$row['counselor_id'] === (int)$user['id'])
);

$rel = $allowed ? ($row['attachment_path'] ?? '') : '';
$full = $rel ? UPLOAD_DIR . '/' . $rel : '';
if (!$rel || str_contains($rel, '..') || !is_file($full)) {
    http_response_code(404);
    exit('ファイルが見つかりません。');
}

$original = $row['attachment_original'] ?: 'file';
$asciiName = str_replace('"', '', preg_replace('/[^\x20-\x7E]/', '_', $original));

header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($full));
header('Content-Length: ' . filesize($full));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Content-Disposition: inline; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($original));
readfile($full);
