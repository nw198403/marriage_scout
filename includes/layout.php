<?php
// includes/layout.php：共通のヘッダー・フッター
// 使い方：
//   render_header('ページ名', 'member');   // 'member' / 'counselor' / 'public'
//   ...本文...
//   render_footer();

require_once __DIR__ . '/helpers.php';

/* nav_link：ヘッダーのタブ用リンクを組み立てる。現在表示中のページと同じURLなら
   is-active クラスを付けて白抜きタブにする（#で始まるアンカー付きリンクは判定しない） */
function nav_link(string $href, string $label, string $badge = '', array $attrs = []): string
{
    $isActive = false;
    if (strpos($href, '#') === false) {
        $norm = static function (string $p): string {
            $p = rtrim($p, '/');
            if (substr($p, -10) === '/index.php') {
                $p = substr($p, 0, -10);
            }
            return $p;
        };
        $hrefPath = parse_url($href, PHP_URL_PATH) ?: '';
        $current = $_SERVER['SCRIPT_NAME'] ?? '';
        $isActive = $hrefPath !== '' && $norm($hrefPath) === $norm($current);
    }
    $isLong = (function_exists('mb_strlen') ? mb_strlen($label) : strlen($label)) > 5;
    $classes = trim('nav-link' . ($isLong ? ' nav-link-long' : '') . ($isActive ? ' is-active' : ''));
    $attrs['class'] = trim(($attrs['class'] ?? '') . ' ' . $classes);
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= ' ' . $k . '="' . h($v) . '"';
    }
    return '<a' . $attrStr . ' href="' . h($href) . '">' . $label . $badge . '</a>';
}

function render_header(string $title, string $layout = 'public', string $bodyClass = '', string $preMain = ''): void
{
    $user = null;
    $navUnread = 0;
    if (!empty($_SESSION['user_id'])) {
        require_once __DIR__ . '/auth_check.php';
        require_once __DIR__ . '/master.php';
        $user = current_user();
        if ($user) {
            $navUnread = unread_message_count(db(), $user['id'], $user['role']);
        }
    }
    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> | <?= h(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=M+PLUS+1:wght@300;400;500;700&display=swap">
<link rel="stylesheet" href="<?= h(url('assets/css/style.css')) ?>">
</head>
<body class="layout-<?= h($layout) ?> <?= h($bodyClass) ?>">
<header class="site-header">
  <div class="inner">
    <div class="site-brand">
      <a class="site-logo" href="<?= h(url($user ? ($user['role'] === 'counselor' ? 'counselor/' : 'member/') : '')) ?>"><?= h(APP_NAME) ?></a>
      <span class="site-tagline">自分に合う結婚相談所・カウンセラーが見つかる、婚活パートナー探し</span>
    </div>
    <nav class="site-nav">
    <?php
  $aboutHref = url('index.php') . '#about';
  $howHref = url('index.php') . '#how';
  ?>
  <?php if ($user): ?>
    <?php
    $msgHref = url(($user['role'] === 'counselor' ? 'counselor/' : 'member/') . 'messages.php');
    $msgBadge = $navUnread > 0 ? '<span class="nav-badge">' . $navUnread . '</span>' : '';
    ?>
    <?php if ($user['role'] === 'member'): ?>
      <?= nav_link(url('member/'), 'ホーム') ?>
      <?= nav_link($aboutHref, '私たちについて') ?>
      <?= nav_link($howHref, '使い方') ?>
      <?= nav_link(url('member/scouts.php'), 'スカウト') ?>
      <?= nav_link(url('member/counselors.php'), '評価・口コミ') ?>
      <?= nav_link($msgHref, 'メッセージ', $msgBadge) ?>
      <?php if (is_admin($user)): ?>
        <?= nav_link(url('admin/agencies.php'), '審査') ?>
        <?= nav_link(url('admin/contracts.php'), '契約確認') ?>
      <?php endif; ?>
    <?php else: ?>
      <?= nav_link(url('counselor/members.php'), '会員検索', '', ['id' => 'nav-anchor-members']) ?>
      <?= nav_link(url('counselor/scouts.php'), 'スカウト一覧') ?>
      <?= nav_link($msgHref, 'メッセージ', $msgBadge) ?>
      <?php if (is_admin($user)): ?>
        <?= nav_link(url('admin/contracts.php'), '契約確認') ?>
      <?php endif; ?>
      <?= nav_link(url('counselor/reviews.php'), '評価') ?>
      <?php if (is_admin($user)): ?>
        <?= nav_link(url('admin/agencies.php'), '審査') ?>
      <?php endif; ?>
    <?php endif; ?>
    <?= nav_link(url('logout.php'), 'ログアウト') ?>
  <?php else: ?>
    <?= nav_link($aboutHref, '私たちについて') ?>
    <?= nav_link($howHref, '使い方') ?>
    <?= nav_link(url('login.php'), 'ログイン') ?>
    <?= nav_link(url('register.php?role=member'), '登録（無料）') ?>
    <?php endif; ?>
    </nav>
  </div>
</header>
<script>
// ヘッダーのナビ項目（「私たちについて」など長いラベル）が、項目数や画面幅によって
// 窮屈に見えないよう、はみ出す分だけ自動でフォントサイズを縮める（CSSのclampを初期値に、実際の幅で微調整する）
(function () {
  var MIN_PX = 9.5;
  function fitNavLinks() {
    document.querySelectorAll('.site-nav a:not(.btn)').forEach(function (a) {
      a.style.fontSize = '';
      var size = parseFloat(getComputedStyle(a).fontSize);
      var guard = 0;
      while (a.scrollWidth > a.clientWidth + 1 && size > MIN_PX && guard < 24) {
        size -= 0.5;
        a.style.fontSize = size + 'px';
        guard++;
      }
    });
  }
  var t;
  function scheduleFit() { clearTimeout(t); t = setTimeout(fitNavLinks, 80); }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fitNavLinks);
  } else {
    fitNavLinks();
  }
  window.addEventListener('load', fitNavLinks);
  window.addEventListener('resize', scheduleFit);
})();
</script>
<?= $preMain ?>
<main class="container">
<?= flash_render() ?>
<?php
}

function render_footer(): void
{
    ?>
</main>
<footer class="site-footer">&copy; <?= date('Y') ?> <?= h(APP_NAME) ?></footer>
</body>
</html>
<?php
}
