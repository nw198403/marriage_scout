<?php
// verify.php：メールのURLから届く確認（?token=...）

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$token = (string)($_GET['token'] ?? '');

if (preg_match('/^[0-9a-f]{64}$/', $token)) {
    $stmt = db()->prepare(
        'UPDATE users SET email_verified_at = NOW(), verify_token = NULL, verify_token_expires_at = NULL
         WHERE verify_token = ? AND verify_token_expires_at > NOW() AND email_verified_at IS NULL'
    );
    $stmt->execute([$token]);
    if ($stmt->rowCount() === 1) {
        flash('ok', 'メールアドレスを確認しました。ログインしてください。');
        redirect('login.php');
    }
}

flash('error', 'このURLは無効か、有効期限が切れています。もう一度登録をお試しください。');
redirect('login.php');
