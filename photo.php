<?php
// photo.php：カウンセラーのプロフィール写真を配信する（ログイン中の会員・カウンセラーなら誰でも閲覧可）
// 書類（admin/file.php）と違い、会員がカウンセラーを選ぶ判断材料として見せる想定のため、運営に限定していない

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/upload.php';

require_login(); // 会員・カウンセラーどちらでもログインしていればよい

$cid = (int)($_GET['cid'] ?? 0);
$stmt = db()->prepare('SELECT photo_path FROM counselors WHERE user_id = ?');
$stmt->execute([$cid]);
$rel = $stmt->fetchColumn();

$full = $rel ? UPLOAD_DIR . '/' . $rel : '';
if (!$rel || str_contains($rel, '..') || !is_file($full)) {
    http_response_code(404);
    exit('写真が見つかりません。');
}
header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($full));
header('Content-Length: ' . filesize($full));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
header('Content-Disposition: inline');
readfile($full);
