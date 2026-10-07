<?php
// counselor/member.php：会員の詳細＋スカウト送信

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('counselor');
$pdo = db();

// 運営の承認が済んだ相談所のカウンセラーだけが、会員の情報を見られる
$stmt = $pdo->prepare('SELECT a.approval_status FROM counselors c JOIN agencies a ON a.id = c.agency_id WHERE c.user_id = ?');
$stmt->execute([$user['id']]);
if ($stmt->fetchColumn() !== 'approved') {
    flash('error', '運営の確認が済むと、会員を探せるようになります。相談所の情報をご確認ください。');
    redirect('counselor/');
}
$memberId = (int)($_GET['id'] ?? $_POST['member_id'] ?? 0);

// 検索対象の会員だけ開ける（期限切れ・情報収集のみ・プロフィール未入力は404）
$stmt = $pdo->prepare('SELECT m.* FROM members m WHERE m.user_id = ? AND ' . SEARCHABLE_MEMBER_SQL);
$stmt->execute([$memberId]);
$m = $stmt->fetch();
if (!$m) {
    http_response_code(404);
    render_header('見つかりません', 'counselor');
    echo '<div class="card"><p>この会員は表示できません（期限切れ、または非公開です）。</p><p><a href="' . h(url('counselor/members.php')) . '">会員検索に戻る</a></p></div>';
    render_footer();
    exit;
}

$stmt = $pdo->prepare('SELECT c.*, a.approval_status FROM counselors c JOIN agencies a ON a.id = c.agency_id WHERE c.user_id = ?');
$stmt->execute([$user['id']]);
$c = $stmt->fetch();
$canScout = $c && $c['approval_status'] === 'approved' && !empty($c['is_active']) && !empty($c['bio']);

$stmt = $pdo->prepare('SELECT * FROM scouts WHERE counselor_id = ? AND member_id = ?');
$stmt->execute([$user['id'], $memberId]);
$scout = $stmt->fetch();

// 会社名・役職は、会員が面談を予約したあとだけ見せる
$showCompany = $scout && in_array($scout['status'], COMPANY_VISIBLE_STATUSES, true);

$stmt = $pdo->prepare('SELECT t.name FROM member_support_tags mt JOIN support_tags t ON t.id = mt.tag_id WHERE mt.member_id = ? ORDER BY t.sort_order');
$stmt->execute([$memberId]);
$tags = array_column($stmt->fetchAll(), 'name');

$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $message = trim((string)($_POST['message'] ?? ''));

    if (!$canScout) {
        $errors[] = 'スカウトを送るには、プロフィールを入力して「公開する」にチェックを入れてください。';
    }
    if ($scout) {
        $errors[] = 'この会員には、すでにスカウトを送っています。';
    }
    if ($message === '' || mb_strlen($message) > SCOUT_MESSAGE_MAX) {
        $errors[] = 'メッセージを' . SCOUT_MESSAGE_MAX . '文字以内で入力してください。';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            // 月のスカウト上限：カウンセラーの行をロックして数えるので、同時送信でも超えない
            $stmt = $pdo->prepare('SELECT monthly_scout_limit FROM counselors WHERE user_id = ? FOR UPDATE');
            $stmt->execute([$user['id']]);
            $limit = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM scouts WHERE counselor_id = ? AND sent_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
            $stmt->execute([$user['id']]);
            $sentThisMonth = (int)$stmt->fetchColumn();

            if ($sentThisMonth >= $limit) {
                $pdo->rollBack();
                $errors[] = "今月のスカウト上限（{$limit}通）に達しています。";
            } else {
                $pdo->prepare(
                    'INSERT INTO scouts (counselor_id, member_id, message, expires_at)
                     VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . SCOUT_VALID_DAYS . ' DAY))'
                )->execute([$user['id'], $memberId, $message]);
                $pdo->commit();
                flash('ok', 'スカウトを送りました。');
                redirect('counselor/member.php?id=' . $memberId);
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() === '23000') {   // 重複（同時に2回送信）
                $errors[] = 'この会員には、すでにスカウトを送っています。';
            } else {
                throw $e;
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM scouts WHERE counselor_id = ? AND sent_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
$stmt->execute([$user['id']]);
$sentThisMonth = (int)$stmt->fetchColumn();

render_header($m['nickname'], 'counselor');
?>
<p><a href="<?= h(url('counselor/members.php')) ?>">‹ 会員検索に戻る</a></p>
<div class="card">
  <h1><?= h($m['nickname']) ?> さん</h1>
  <p>
    <span class="badge badge-<?= h($m['readiness']) ?>"><?= h(READINESS_LABELS[$m['readiness']]) ?></span>
    <span class="muted">（回答の有効期限：<?= h(date('Y年n月j日', strtotime($m['readiness_expires_at']))) ?>）</span>
  </p>
  <p class="muted"><?= calc_age((int)$m['birth_year'], (int)$m['birth_month']) ?>歳 ／ <?= h($m['gender']) ?> ／ <?= h($m['prefecture']) ?> ／ <?= h(MARITAL_LABELS[$m['marital_history']] ?? '') ?></p>

  <table class="kv">
    <?php if ($m['mbti_type']): ?><tr><th>MBTI</th><td><?= h($m['mbti_type']) ?>（<?= h(MBTI_LABELS[$m['mbti_type']] ?? '') ?>）</td></tr><?php endif; ?>
    <tr><th>雇用形態</th><td><?= h($m['employment_type'] ?: '—') ?></td></tr>
    <tr><th>業種</th><td><?= h($m['industry'] ?: '—') ?></td></tr>
    <tr><th>年収</th><td><?= $m['income_band'] ? h(INCOME_BANDS[(int)$m['income_band']] ?? '—') : '—' ?></td></tr>
    <?php if ($showCompany): ?>
      <tr><th>会社名</th><td><?= h($m['company_name'] ?: '—') ?></td></tr>
      <tr><th>役職</th><td><?= h($m['job_title'] ?: '—') ?></td></tr>
    <?php else: ?>
      <tr><th>会社名・役職</th><td class="muted">会員が面談を予約すると表示されます</td></tr>
    <?php endif; ?>
    <?php if ($m['hobbies']): ?><tr><th>趣味・休日</th><td><?= h($m['hobbies']) ?></td></tr><?php endif; ?>
  </table>

  <?php if ($tags): ?>
    <h2>不安なこと</h2>
    <p class="tag-list"><?php foreach ($tags as $tn): ?><span class="tag"><?= h($tn) ?></span><?php endforeach; ?></p>
  <?php endif; ?>
  <?php if ($m['intro']): ?>
    <h2>自己紹介</h2>
    <p><?= nl2br(h($m['intro'])) ?></p>
  <?php endif; ?>
  <?php if ($m['desired_conditions']): ?>
    <h2>希望条件・カウンセラーに求めること</h2>
    <p><?= nl2br(h($m['desired_conditions'])) ?></p>
  <?php endif; ?>
  <p class="hint">氏名・メールアドレス・電話番号は表示されません。</p>
</div>

<div class="card">
  <h2>スカウト</h2>
  <?php foreach ($errors as $e): ?>
    <div class="flash flash-error"><?= h($e) ?></div>
  <?php endforeach; ?>

  <?php if ($scout): ?>
    <p><span class="badge badge-sent"><?= h(SCOUT_STATUS_LABELS[$scout['status']]) ?></span>
       <span class="muted"><?= h(date('Y年n月j日', strtotime($scout['sent_at']))) ?> に送信</span></p>
    <p><a class="btn btn-secondary" href="<?= h(url('counselor/scout.php?id=' . (int)$scout['id'])) ?>">面談の候補日時を出す・確認する</a></p>
    <p class="muted">送ったメッセージ</p>
    <p><?= nl2br(h($scout['message'])) ?></p>
  <?php elseif (!$canScout): ?>
    <p>スカウトを送るには、先に<a href="<?= h(url('counselor/profile.php')) ?>">プロフィール</a>を入力して、公開にしてください。</p>
  <?php else: ?>
    <p class="muted">今月の送信数：<?= $sentThisMonth ?> / <?= (int)$c['monthly_scout_limit'] ?> 通。返答の期限は送信から<?= SCOUT_VALID_DAYS ?>日です。</p>
    <form method="post" action="<?= h(url('counselor/member.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="member_id" value="<?= (int)$memberId ?>">
      <div class="form-row">
        <label for="message">メッセージ <span class="hint">（<?= SCOUT_MESSAGE_MAX ?>文字まで）この会員のどこに惹かれたか、どうサポートできるかを書くと返信されやすくなります</span></label>
        <textarea id="message" name="message" rows="7" maxlength="<?= SCOUT_MESSAGE_MAX ?>" required><?= h($message) ?></textarea>
      </div>
      <button type="submit" class="btn btn-block">スカウトを送る</button>
    </form>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
