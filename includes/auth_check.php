<?php
// includes/auth_check.php：ログイン状態の確認とログイン・ログアウト
// 使い方（各ページの冒頭）：
//   require_once __DIR__ . '/../includes/auth_check.php';
//   $user = require_login('member');      // 会員専用ページ
//   $user = require_login('counselor');   // カウンセラー専用ページ

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// ログイン中のユーザー（未ログインなら null）
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = false;
    if ($user === false) {
        $stmt = db()->prepare('SELECT id, email, role, email_verified_at, status FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

// ログイン必須。$role を指定すると、その役割以外は入れない
function require_login(?string $role = null): array
{
    $user = current_user();
    if ($user === null) {
        flash('error', 'ログインしてください。');
        redirect('login.php');
    }
    if ($user['email_verified_at'] === null) {
        flash('error', 'メールアドレスの確認が済んでいません。');
        redirect('login.php');
    }
    if (($user['status'] ?? 'active') === 'withdrawn') {
        logout_user();
        flash('error', 'このアカウントは退会済みです。');
        redirect('login.php');
    }
    if ($role !== null && $user['role'] !== $role) {
        http_response_code(403);
        exit('このページを見る権限がありません。');
    }
    return $user;
}

// ログイン成功時に呼ぶ。セッションIDを作り直して、セッション固定攻撃を防ぐ
function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

// ログアウト：セッションの中身を空にして、セッションIDも作り直す
function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

// 運営者かどうか（config.php の ADMIN_EMAILS に書いたメールアドレスのユーザー）
function is_admin(?array $user): bool
{
    if ($user === null || !defined('ADMIN_EMAILS')) {
        return false;
    }
    return in_array(strtolower($user['email']), array_map('strtolower', ADMIN_EMAILS), true);
}

function require_admin(): array
{
    $user = require_login();
    if (!is_admin($user)) {
        http_response_code(403);
        exit('このページを見る権限がありません。');
    }
    return $user;
}
