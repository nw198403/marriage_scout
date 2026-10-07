<?php
// contract_confirm.php：契約の確認メールのURLから開く回答ページ（ログイン不要・URLの秘密のトークンで本人確認）
// 回答は記録するだけ。契約成立にするかどうかは、運営が最終的に決める。

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/master.php';

$pdo = db();
$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');

$ct = null;
$role = null;
if (preg_match('/^[0-9a-f]{32}$/', $token)) {
    $stmt = $pdo->prepare(
        'SELECT ct.*, m.nickname, c.display_name, a.agency_name
         FROM contracts ct JOIN members m ON m.user_id = ct.member_id
         JOIN counselors c ON c.user_id = ct.counselor_id JOIN agencies a ON a.id = c.agency_id
         WHERE ct.counselor_token = ? OR ct.agency_token = ?'
    );
    $stmt->execute([$token, $token]);
    $ct = $stmt->fetch();
    if ($ct) {
        $role = hash_equals((string)$ct['counselor_token'], $token) ? 'counselor' : 'agency';
    }
}

$valid = $ct && $ct['status'] === 'confirming' && $ct['mails_sent_at']
    && strtotime($ct['mails_sent_at']) + CONTRACT_TOKEN_DAYS * 86400 > time();

if (!$ct) {
    http_response_code(404);
    render_header('確認できません', 'public');
    echo '<div class="card"><h1>このURLは無効です</h1><p class="muted">URLをご確認ください。</p></div>';
    render_footer();
    exit;
}

$done = '';
$responseCol = $role . '_response';
$respondedCol = $role . '_responded_at';
if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST' && $ct[$responseCol] === 'none') {
    csrf_check();
    $answer = (string)($_POST['answer'] ?? '');
    if (in_array($answer, ['confirmed', 'denied'], true)) {
        $pdo->prepare("UPDATE contracts SET {$responseCol} = ?, {$respondedCol} = NOW() WHERE id = ? AND {$responseCol} = 'none' AND status = 'confirming'")
            ->execute([$answer, $ct['id']]);
        $ct[$responseCol] = $answer;
        $done = $answer === 'confirmed' ? '「契約を確認しました」と回答しました。ご協力ありがとうございました。' : '「契約していません」と回答しました。運営が内容を確認します。';
    }
}

render_header('契約の確認', 'public');
?>
<div class="card">
  <h1>契約の確認</h1>
  <?php if (!$valid): ?>
    <p class="muted">この確認は、すでに終了したか、有効期限が切れています。</p>
  <?php else: ?>
    <p><?= $role === 'agency' ? '貴相談所' : 'ご担当のカウンセラー様' ?>と、会員の「<?= h($ct['nickname']) ?>」さんの契約について、確認をお願いします。</p>
    <table class="kv">
      <tr><th>相談所</th><td><?= h($ct['agency_name']) ?></td></tr>
      <tr><th>カウンセラー</th><td><?= h($ct['display_name']) ?></td></tr>
      <tr><th>会員</th><td><?= h($ct['nickname']) ?> さん</td></tr>
      <tr><th>契約日（会員の申告）</th><td><?= h(date('Y年n月j日', strtotime($ct['contract_date']))) ?></td></tr>
      <tr><th>入会金</th><td><?= number_format((int)$ct['entry_fee_snapshot']) ?>円</td></tr>
    </table>
    <?php if ($done): ?>
      <div class="flash flash-ok"><?= h($done) ?></div>
    <?php elseif ($ct[$responseCol] !== 'none'): ?>
      <p class="muted">このメールへの回答は、すでに受け付けています（<?= h(CONTRACT_RESPONSE_LABELS[$ct[$responseCol]]) ?>）。</p>
    <?php else: ?>
      <p class="muted">内容に相違がなければ「契約を確認しました」を、契約の事実がない場合は「契約していません」を選んでください。回答は運営が確認のうえ、最終的に判断します。</p>
      <form method="post" action="<?= h(url('contract_confirm.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="t" value="<?= h($token) ?>">
        <button type="submit" name="answer" value="confirmed" class="btn">契約を確認しました</button>
        <button type="submit" name="answer" value="denied" class="btn btn-secondary" onclick="return confirm('「契約していません」と回答しますか？');">契約していません</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
