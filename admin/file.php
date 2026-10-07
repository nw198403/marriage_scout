<?php
// admin/file.php：運営用。相談所が提出した書類を表示する窓口

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/upload.php';

require_admin();
$stmt = db()->prepare('SELECT affiliation_doc_path FROM agencies WHERE id = ?');
$stmt->execute([(int)($_GET['aid'] ?? 0)]);
$rel = $stmt->fetchColumn();

$full = $rel ? UPLOAD_DIR . '/' . $rel : '';
if (!$rel || str_contains($rel, '..') || !is_file($full)) {
    http_response_code(404);
    exit('ファイルが見つかりません。');
}
header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($full));
header('Content-Length: ' . filesize($full));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Content-Disposition: inline');
readfile($full);
