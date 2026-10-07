<?php
date_default_timezone_set('Asia/Tokyo');   // 日時はすべて日本時間で扱う
// includes/helpers.php：セッション開始・エスケープ・CSRF・リダイレクト・フラッシュメッセージ

require_once __DIR__ . '/../config.php';

// セッション開始（Cookieの属性を安全側に）
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

// HTMLエスケープ（画面に出す値は必ず h() を通す）
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// サイト内リンク用のURL
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

// CSRFトークン
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

// POSTの先頭で呼ぶ。トークンが合わなければ処理を止める
function csrf_check(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    $saved = $_SESSION['csrf_token'] ?? '';
    // 保存済みトークンが空（セッションなし）のときも不合格にする
    if (!is_string($sent) || $saved === '' || !hash_equals($saved, $sent)) {
        http_response_code(400);
        exit('不正なリクエストです。画面を開き直してもう一度お試しください。');
    }
}

// フラッシュメッセージ（次の画面で1回だけ表示）
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_render(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $out .= '<div class="flash flash-' . h($f['type']) . '">' . h($f['message']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $out;
}
