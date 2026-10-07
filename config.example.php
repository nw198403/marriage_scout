<?php
// config.example.php：設定ファイルのテンプレート。
// これをコピーして config.php を作り、本番（さくら）の値を書き換える。
// config.php は .gitignore に入れて、Gitに載せない。

$host = $_SERVER['SERVER_NAME'] ?? 'localhost';
$is_local = in_array($host, ['localhost', '127.0.0.1'], true);

if ($is_local) {
    // ローカル（XAMPP）
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'marriage_scout');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('BASE_URL', '/marriage_scout');          // htdocs 内のフォルダ名
    define('DEV_MODE', true);                       // 認証URLを画面に表示する
    define('UPLOAD_DIR', dirname(__DIR__) . '/marriage_scout_private'); // 公開領域の外
} else {
    // 本番（さくらインターネット）
    define('DB_HOST', 'mysqlXXXX.db.sakura.ne.jp'); // さくらのコントロールパネルで確認
    define('DB_NAME', 'ここにDB名');
    define('DB_USER', 'ここにDBユーザー名');
    define('DB_PASS', 'ここにDBパスワード');
    define('BASE_URL', '');                         // 公開直下に置く場合は空
    define('DEV_MODE', false);
    define('UPLOAD_DIR', dirname(__DIR__) . '/marriage_scout_private'); // www の1つ上。本番で要確認
}

// エラーは本番では画面に出さない
ini_set('display_errors', DEV_MODE ? '1' : '0');
error_reporting(E_ALL);

// 定数
define('APP_NAME', '婚活スカウト');

// 運営者（カウンセラーの審査ができる人）のメールアドレス。運営用に登録したアドレスを書く
define('ADMIN_EMAILS', ['admin@example.com']);
