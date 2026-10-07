<?php
// login.php：ログイン

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/layout.php';

// ログイン済みなら、それぞれのホームへ
$current = current_user();
if ($current !== null && $current['email_verified_at'] !== null) {
    redirect($current['role'] === 'counselor' ? 'counselor/' : 'member/');
}

$error = null;
$email = '';
$role = 'member';
$storeNo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = ($_POST['role'] ?? 'member') === 'counselor' ? 'counselor' : 'member';
    $storeNo = strtoupper(trim((string)($_POST['store_no'] ?? '')));

    $stmt = db()->prepare('SELECT id, password_hash, role, email_verified_at, status FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // ユーザーがいなくてもハッシュ照合を行い、応答時間の差でメールの有無が分からないようにする
    $hash = $user['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
    $ok = password_verify($password, $hash) && $user;

    if (!$ok) {
        $error = 'メールアドレスまたはパスワードが正しくありません。';
    } elseif ($user['email_verified_at'] === null) {
        $error = 'メールアドレスの確認が済んでいません。届いたメールのURLを開いてください。';
    } elseif (($user['status'] ?? 'active') === 'withdrawn') {
        $error = 'このアカウントは退会済みです。';
    } elseif ($user['role'] !== $role) {
        $error = $role === 'counselor'
            ? 'カウンセラーとして登録されたメールアドレスではありません。「会員」タブからログインしてください。'
            : '会員として登録されたメールアドレスではありません。「カウンセラー」タブからログインしてください。';
    } elseif ($role === 'counselor' && $storeNo === '') {
        $error = '店番を入力してください。';
    } elseif ($role === 'counselor') {
        $stmt2 = db()->prepare(
            'SELECT a.store_no FROM counselors c JOIN agencies a ON a.id = c.agency_id WHERE c.user_id = ?'
        );
        $stmt2->execute([(int)$user['id']]);
        $actualStoreNo = (string)$stmt2->fetchColumn();
        if ($actualStoreNo === '' || strtoupper($actualStoreNo) !== $storeNo) {
            $error = '店番が正しくありません。相談所の情報画面でご確認ください。';
        }
    }

    if ($error === null) {
        login_user((int)$user['id']);
        redirect($role === 'counselor' ? 'counselor/' : 'member/');
    }
}

render_header('ログイン', 'public');
?>
<div class="card">
  <h1>ログイン</h1>
  <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
  <div class="role-tabs">
    <button type="button" class="role-tab <?= $role === 'member' ? 'active' : '' ?>" id="role-tab-member" data-role="member">会員</button>
    <button type="button" class="role-tab <?= $role === 'counselor' ? 'active' : '' ?>" id="role-tab-counselor" data-role="counselor">カウンセラー</button>
  </div>
  <form method="post" action="<?= h(url('login.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" id="role_input" name="role" value="<?= h($role) ?>">
    <div class="form-row">
      <label for="email">メールアドレス</label>
      <input type="email" id="email" name="email" value="<?= h($email) ?>" required>
    </div>
    <div class="form-row">
      <label for="password">パスワード</label>
      <input type="password" id="password" name="password" required>
    </div>
    <div class="form-row" id="store_no_field" style="<?= $role === 'counselor' ? '' : 'display:none;' ?>">
      <label for="store_no">店番 <span class="hint">（相談所の情報画面で確認できます。例：AA0001）</span></label>
      <input type="text" id="store_no" name="store_no" maxlength="6" style="max-width:10rem;text-transform:uppercase;" value="<?= h($storeNo) ?>" <?= $role === 'counselor' ? 'required' : '' ?>>
    </div>
    <button type="submit" class="btn btn-login">ログイン</button>
  </form>
  <p class="center muted">
    はじめての方：<a href="<?= h(url('register.php?role=member')) ?>">会員登録（無料）</a>
    ／ <a href="<?= h(url('register.php?role=counselor')) ?>">カウンセラー登録</a>
  </p>
</div>
<script>
(function () {
  var memberTab = document.getElementById('role-tab-member');
  var counselorTab = document.getElementById('role-tab-counselor');
  var roleInput = document.getElementById('role_input');
  var storeNoField = document.getElementById('store_no_field');
  var storeNoInput = document.getElementById('store_no');
  function sync(role) {
    roleInput.value = role;
    memberTab.classList.toggle('active', role === 'member');
    counselorTab.classList.toggle('active', role === 'counselor');
    storeNoField.style.display = role === 'counselor' ? '' : 'none';
    storeNoInput.required = role === 'counselor';
  }
  memberTab.addEventListener('click', function () { sync('member'); });
  counselorTab.addEventListener('click', function () { sync('counselor'); });
})();
</script>
<?php render_footer(); ?>
