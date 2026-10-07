<?php
// member/review.php：契約成立（confirmed）した相手を、会員が評価する（1契約につき1件・投稿後は変更不可）

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('member');
$pdo = db();
$contractId = (int)($_GET['contract_id'] ?? $_POST['contract_id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT ct.*, c.display_name, a.agency_name
     FROM contracts ct JOIN counselors c ON c.user_id = ct.counselor_id JOIN agencies a ON a.id = c.agency_id
     WHERE ct.id = ? AND ct.member_id = ?"
);
$stmt->execute([$contractId, $user['id']]);
$ct = $stmt->fetch();

if (!$ct || $ct['status'] !== 'confirmed') {
    http_response_code(404);
    render_header('見つかりません', 'member');
    echo '<div class="card"><p>このレビューは表示できません。契約が成立した相手のみ、レビューを書けます。</p><p><a href="' . h(url('member/scouts.php')) . '">スカウト一覧に戻る</a></p></div>';
    render_footer();
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM reviews WHERE contract_id = ?');
$stmt->execute([$contractId]);
$review = $stmt->fetch();

$errors = [];
if (!$review && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $scores = [];
    foreach (REVIEW_LABELS as $key => $label) {
        $v = (int)($_POST[$key] ?? 0);
        if ($v < 1 || $v > 5) {
            $errors[] = $label . 'を選択してください。';
        }
        $scores[$key] = $v;
    }
    $comment = trim((string)($_POST['comment'] ?? ''));
    if (mb_strlen($comment) > REVIEW_COMMENT_MAX) {
        $errors[] = 'コメントは' . REVIEW_COMMENT_MAX . '文字以内で入力してください。';
    }
    if (!$errors) {
        try {
            $cols = array_keys(REVIEW_LABELS);
            $colList = implode(', ', $cols);
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $params = [$contractId, $user['id'], $ct['counselor_id']];
            foreach ($cols as $c) {
                $params[] = $scores[$c];
            }
            $params[] = $comment === '' ? null : $comment;
            $pdo->prepare(
                "INSERT INTO reviews (contract_id, member_id, counselor_id, $colList, comment)
                 VALUES (?, ?, ?, $placeholders, ?)"
            )->execute($params);
            flash('ok', 'レビューを投稿しました。ありがとうございました。');
            redirect('member/review.php?contract_id=' . $contractId);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            redirect('member/review.php?contract_id=' . $contractId);   // 既に投稿済み（多重送信）
        }
    }
}

render_header('レビュー', 'member');
?>
<p><a href="<?= h(url('member/scouts.php')) ?>">‹ スカウト一覧に戻る</a></p>
<div class="card">
  <h1><?= h($ct['agency_name']) ?>（<?= h($ct['display_name']) ?>）へのレビュー</h1>
  <p class="hint">契約日：<?= h(date('Y年n月j日', strtotime($ct['contract_date']))) ?></p>

  <?php if ($review): ?>
    <p class="hint">投稿したレビューは変更できません。内容に誤りがある場合は運営までご連絡ください。</p>
    <p><?= star_html(review_avg($review)) ?></p>
    <table class="kv">
      <?php $i = 1; foreach (REVIEW_LABELS as $key => $label): ?>
        <tr><th><?= $i ?>. <?= h($label) ?></th><td><?= star_html((float)$review[$key]) ?></td></tr>
      <?php $i++; endforeach; ?>
    </table>
    <?php if ($review['comment']): ?>
      <p class="muted" style="margin-bottom:.2rem">コメント</p>
      <p><?= nl2br(h($review['comment'])) ?></p>
    <?php endif; ?>
  <?php else: ?>
    <?php foreach ($errors as $e): ?><div class="flash flash-error"><?= h($e) ?></div><?php endforeach; ?>
    <p class="hint">参考として匿名で表示されます。最も高い評価が5、最も低い評価が1となります。</p>
    <form method="post" action="<?= h(url('member/review.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="contract_id" value="<?= $contractId ?>">
      <?php $i = 1; foreach (REVIEW_LABELS as $key => $label): ?>
        <div class="form-row">
          <label><?= $i ?>. <?= h($label) ?> <span class="hint"><?= h(REVIEW_HINTS[$key] ?? '') ?></span></label>
          <div class="star-select radio-group">
            <?php for ($n = 1; $n <= 5; $n++): ?>
              <label><input type="radio" name="<?= h($key) ?>" value="<?= $n ?>" <?= (int)($_POST[$key] ?? 0) === $n ? 'checked' : '' ?> required> <?= $n ?></label>
            <?php endfor; ?>
          </div>
        </div>
      <?php $i++; endforeach; ?>
      <div class="form-row">
        <label for="comment">コメント <span class="hint">（任意・<?= REVIEW_COMMENT_MAX ?>文字まで）</span></label>
        <textarea id="comment" name="comment" rows="4" maxlength="<?= REVIEW_COMMENT_MAX ?>"><?= h((string)($_POST['comment'] ?? '')) ?></textarea>
      </div>
      <button type="submit" class="btn">レビューを投稿する</button>
    </form>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
