<?php
// register.php：会員・カウンセラーの新規登録（?role=member / ?role=counselor）
// カウンセラーは、新しい相談所を代表者として登録するか、招待コードで既存の相談所に参加するかを選ぶ

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/master.php';

$role = ($_GET['role'] ?? $_POST['role'] ?? 'member') === 'counselor' ? 'counselor' : 'member';
$errors = [];
$devUrl = null;
$done = false;

$input = ['email' => '', 'nickname' => '', 'display_name' => '', 'agency_name' => '', 'store_no' => '', 'invite_code' => ''];
$mode = ($_POST['mode'] ?? 'new') === 'join' ? 'join' : 'new';   // カウンセラーのみ：新規登録 / 招待コードで参加

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $input['email']        = trim((string)($_POST['email'] ?? ''));
    $input['nickname']     = trim((string)($_POST['nickname'] ?? ''));
    $input['display_name'] = trim((string)($_POST['display_name'] ?? ''));
    $input['agency_name']  = trim((string)($_POST['agency_name'] ?? ''));
    $input['store_no']     = strtoupper(trim((string)($_POST['store_no'] ?? '')));
    $input['invite_code']  = strtoupper(trim((string)($_POST['invite_code'] ?? '')));
    $password  = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($input['email']) > 255) {
        $errors[] = 'メールアドレスの形式が正しくありません。';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'パスワードは8文字以上で入力してください。';
    }
    if ($password !== $password2) {
        $errors[] = 'パスワード（確認）が一致しません。';
    }
    if ($role === 'member') {
        if ($input['nickname'] === '' || mb_strlen($input['nickname']) > 50) {
            $errors[] = 'ニックネームを50文字以内で入力してください。';
        }
    } else {
        if ($input['display_name'] === '' || mb_strlen($input['display_name']) > 50) {
            $errors[] = '担当者名を50文字以内で入力してください。';
        }
        if ($mode === 'new') {
            if ($input['agency_name'] === '' || mb_strlen($input['agency_name']) > 100) {
                $errors[] = '相談所名を100文字以内で入力してください。';
            }
        } else {
            if ($input['store_no'] === '') {
                $errors[] = '店番を入力してください。';
            }
            if ($input['invite_code'] === '') {
                $errors[] = '招待コードを入力してください。';
            }
        }
    }

    // 参加の場合は、先に相談所を確認しておく（見つからなければユーザーは作らない）
    $joinAgency = null;
    if (!$errors && $role === 'counselor' && $mode === 'join') {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM agencies WHERE store_no = ? AND invite_code = ?');
        $stmt->execute([$input['store_no'], $input['invite_code']]);
        $joinAgency = $stmt->fetch();
        if (!$joinAgency) {
            $errors[] = '店番または招待コードが正しくありません。相談所の代表者にご確認ください。';
        }
    }

    if (!$errors) {
        $token = bin2hex(random_bytes(32));
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO users (email, password_hash, role, verify_token, verify_token_expires_at)
                 VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
            );
            $stmt->execute([$input['email'], password_hash($password, PASSWORD_DEFAULT), $role, $token]);
            $userId = (int)$pdo->lastInsertId();

            if ($role === 'member') {
                $pdo->prepare('INSERT INTO members (user_id, nickname) VALUES (?, ?)')
                    ->execute([$userId, $input['nickname']]);
            } elseif ($mode === 'new') {
                // 新しい相談所を作り、この人が代表者になる
                $storeNo = next_store_no($pdo);
                $pdo->prepare(
                    'INSERT INTO agencies (store_no, agency_name, invite_code) VALUES (?, ?, ?)'
                )->execute([$storeNo, $input['agency_name'], gen_invite_code()]);
                $agencyId = (int)$pdo->lastInsertId();
                $pdo->prepare(
                    'INSERT INTO counselors (user_id, agency_id, is_representative, counselor_no, display_name) VALUES (?, ?, 1, 1, ?)'
                )->execute([$userId, $agencyId, $input['display_name']]);
            } else {
                // 招待コードで、既存の相談所に担当者として参加する
                $stmt = $pdo->prepare('SELECT next_counselor_no FROM agencies WHERE id = ? FOR UPDATE');
                $stmt->execute([(int)$joinAgency['id']]);
                $no = (int)$stmt->fetchColumn();
                $pdo->prepare('UPDATE agencies SET next_counselor_no = next_counselor_no + 1 WHERE id = ?')->execute([(int)$joinAgency['id']]);
                $pdo->prepare(
                    'INSERT INTO counselors (user_id, agency_id, is_representative, counselor_no, display_name) VALUES (?, ?, 0, ?, ?)'
                )->execute([$userId, (int)$joinAgency['id'], $no, $input['display_name']]);
            }
            $pdo->commit();

            $verifyUrl = send_verify_mail($input['email'], $token);
            $devUrl = DEV_MODE ? $verifyUrl : null;
            $done = true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() === '23000') { // メールアドレスの重複
                $errors[] = 'このメールアドレスでは登録できません。すでに登録済みの可能性があります。';
            } else {
                throw $e;
            }
        }
    }
}

$title = $role === 'member' ? '会員登録（無料）' : 'カウンセラー登録';
render_header($title, $role === 'member' ? 'member' : 'counselor');
?>
<?php if ($done): ?>
  <div class="card">
    <h1>確認メールを送りました</h1>
    <p>メールに記載のURLを開くと、登録が完了します（24時間有効）。</p>
    <?php if ($devUrl): ?>
      <div class="dev-box">
        【開発モード】メールは送らず、確認URLをここに表示しています：<br>
        <a href="<?= h($devUrl) ?>"><?= h($devUrl) ?></a>
      </div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="card">
    <h1><?= h($title) ?></h1>
    <?php foreach ($errors as $e): ?>
      <div class="flash flash-error"><?= h($e) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= h(url('register.php?role=' . $role)) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="role" value="<?= h($role) ?>">
      <?php if ($role === 'member'): ?>
        <div class="form-row">
          <label for="nickname">ニックネーム <span class="hint">（カウンセラーに表示されます）</span></label>
          <input type="text" id="nickname" name="nickname" maxlength="50" value="<?= h($input['nickname']) ?>" required>
        </div>
      <?php else: ?>
        <div class="form-row">
          <label>登録の種類</label>
          <div class="radio-group">
            <label><input type="radio" name="mode" value="new" id="mode-new" <?= $mode === 'new' ? 'checked' : '' ?>> 新しい相談所を登録する（代表者として）</label>
            <label><input type="radio" name="mode" value="join" id="mode-join" <?= $mode === 'join' ? 'checked' : '' ?>> 招待コードで、既存の相談所に参加する</label>
          </div>
          <p class="hint">同じ相談所の2人目以降の方は「招待コードで参加する」を選んでください。招待コードと店番は、代表者の画面（相談所の情報）で確認できます。</p>
        </div>
        <div id="mode-new-fields">
          <div class="form-row">
            <label for="agency_name">相談所名</label>
            <input type="text" id="agency_name" name="agency_name" maxlength="100" value="<?= h($input['agency_name']) ?>">
          </div>
        </div>
        <div id="mode-join-fields">
          <div class="form-row">
            <label for="store_no">店番 <span class="hint">（代表者から共有されたもの。例：AA0001）</span></label>
            <input type="text" id="store_no" name="store_no" maxlength="6" style="max-width:10rem;text-transform:uppercase;" value="<?= h($input['store_no']) ?>">
          </div>
          <div class="form-row">
            <label for="invite_code">招待コード</label>
            <input type="text" id="invite_code" name="invite_code" maxlength="20" style="max-width:14rem;text-transform:uppercase;" value="<?= h($input['invite_code']) ?>">
          </div>
        </div>
        <div class="form-row">
          <label for="display_name">担当者名</label>
          <input type="text" id="display_name" name="display_name" maxlength="50" value="<?= h($input['display_name']) ?>" required>
        </div>
      <?php endif; ?>
      <div class="form-row">
        <label for="email">メールアドレス</label>
        <input type="email" id="email" name="email" maxlength="255" value="<?= h($input['email']) ?>" required>
      </div>
      <div class="form-row">
        <label for="password">パスワード <span class="hint">（8文字以上）</span></label>
        <input type="password" id="password" name="password" minlength="8" required>
      </div>
      <div class="form-row">
        <label for="password2">パスワード（確認）</label>
        <input type="password" id="password2" name="password2" minlength="8" required>
      </div>
      <button type="submit" class="btn btn-block">登録する</button>
    </form>
    <p class="center muted">すでに登録済みの方は <a href="<?= h(url('login.php')) ?>">ログイン</a></p>
  </div>
<?php endif; ?>
<?php if ($role === 'counselor'): ?>
<script>
(function () {
  var newR = document.getElementById('mode-new'), joinR = document.getElementById('mode-join');
  var newBox = document.getElementById('mode-new-fields'), joinBox = document.getElementById('mode-join-fields');
  var agencyName = document.getElementById('agency_name'), storeNo = document.getElementById('store_no'), inviteCode = document.getElementById('invite_code');
  function sync() {
    var isNew = newR.checked;
    newBox.style.display = isNew ? '' : 'none';
    joinBox.style.display = isNew ? 'none' : '';
    agencyName.required = isNew;
    storeNo.required = !isNew;
    inviteCode.required = !isNew;
  }
  newR.addEventListener('change', sync);
  joinR.addEventListener('change', sync);
  sync();
})();
</script>
<?php endif; ?>
<?php render_footer(); ?>
