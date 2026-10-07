<?php
// admin/agency.php：運営用。1つの相談所を確認して、承認・却下する。所属する担当者の一覧も見られる

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

require_admin();
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM agencies WHERE id = ?');
$stmt->execute([$id]);
$a = $stmt->fetch();
if (!$a) {
    http_response_code(404);
    exit('相談所が見つかりません。');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($a['withdrawn_at'])) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $note = trim((string)($_POST['review_note'] ?? ''));

    if ($action === 'approve') {
        $pdo->prepare("UPDATE agencies SET approval_status = 'approved', approved_at = NOW(), review_note = NULL WHERE id = ?")->execute([$id]);
        flash('ok', '承認しました。');
        redirect('admin/agencies.php');
    } elseif ($action === 'reject') {
        if ($note === '' || mb_strlen($note) > 255) {
            $errors[] = '却下の理由を255文字以内で入力してください（相談所に表示されます）。';
        } else {
            // 却下したら、所属する担当者全員の公開も止める
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE agencies SET approval_status = 'rejected', approved_at = NULL, review_note = ? WHERE id = ?")->execute([$note, $id]);
            $pdo->prepare('UPDATE counselors SET is_active = 0 WHERE agency_id = ?')->execute([$id]);
            $pdo->commit();
            flash('ok', '却下しました。');
            redirect('admin/agencies.php');
        }
    }
}

$stmt = $pdo->prepare('SELECT user_id, counselor_no, display_name, is_representative, is_active, bio FROM counselors WHERE agency_id = ? ORDER BY counselor_no');
$stmt->execute([$id]);
$members = $stmt->fetchAll();

render_header('審査：' . $a['agency_name'], 'admin');
?>
<div class="card admin-detail">
  <p class="back-link"><a href="<?= h(url('admin/agencies.php')) ?>">‹ 審査の一覧に戻る</a></p>
  <h1><?= h($a['agency_name']) ?> <span class="badge badge-<?= h($a['approval_status']) ?>"><?= h(APPROVAL_LABELS[$a['approval_status']]) ?></span><?php if (!empty($a['withdrawn_at'])): ?> <span class="badge badge-withdrawn">退会済み（<?= h(date('Y/n/j', strtotime($a['withdrawn_at']))) ?>）</span><?php endif; ?>
    <span class="info-tip" tabindex="0">
      <span class="info-tip-icon">?</span>
      <span class="info-tip-bubble">提出書類の内容と、加盟番号・相談所名が一致するかを見ます。連盟の公開情報（加盟相談所の検索など）でも実在を確認してから、承認してください。承認・却下は、この相談所に所属する担当者全員に共通で適用されます。</span>
    </span>
  </h1>
  <div class="sheet-wrap">
  <table class="sheet sheet-detail">
    <tr><th>店番</th><td><?= h($a['store_no']) ?></td><th>相談所の連絡先メール</th><td><?= h($a['agency_email'] ?: '未登録') ?></td></tr>
    <tr><th>加盟連盟</th><td><?= h($a['affiliation'] ?? '未入力') ?></td><th>加盟番号</th><td><?= h($a['affiliation_number'] ?: '—') ?></td></tr>
    <tr><th>提出書類</th><td colspan="3"><?= $a['affiliation_doc_path'] ? '<a href="' . h(url('admin/file.php?aid=' . $id)) . '" target="_blank" rel="noopener">開く</a>' : '未提出' ?></td></tr>
    <tr><th>登録日</th><td colspan="3"><?= h(date('Y年n月j日', strtotime($a['created_at']))) ?></td></tr>
    <?php if ($a['review_note']): ?><tr><th>前回の却下理由</th><td colspan="3"><?= h($a['review_note']) ?></td></tr><?php endif; ?>
  </table>
  </div>

  <div class="review-box">
    <?php foreach ($errors as $e): ?><div class="flash flash-error"><?= h($e) ?></div><?php endforeach; ?>
    <?php if (!empty($a['withdrawn_at'])): ?>
    <p class="muted">この相談所は退会済みのため、承認・却下の操作はできません。</p>
    <?php else: ?>
    <form method="post" action="<?= h(url('admin/agency.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="review-buttons">
        <button type="submit" name="action" value="approve" class="btn">承認する</button>
        <button type="submit" name="action" value="reject" class="btn btn-secondary">却下する</button>
      </div>
      <div class="form-row">
        <label for="review_note">却下する場合の理由 <span class="hint">（相談所に表示されます）</span></label>
        <input type="text" id="review_note" name="review_note" maxlength="255">
      </div>
    </form>
    <?php endif; ?>
  </div>

  <h2>所属する担当者（<?= count($members) ?>人）</h2>
  <div class="sheet-wrap">
    <table class="sheet">
      <thead><tr><th>担当者ID</th><th>表示名</th><th>役割</th><th>プロフィール</th></tr></thead>
      <tbody>
      <?php foreach ($members as $mm): $mhref = url('admin/counselor.php?id=' . (int)$mm['user_id']); ?>
        <tr class="row-link" data-href="<?= h($mhref) ?>">
          <td><?= h(counselor_display_id($a['store_no'], (int)$mm['counselor_no'])) ?></td>
          <td><a href="<?= h($mhref) ?>"><?= h($mm['display_name']) ?></a></td>
          <td><?= $mm['is_representative'] ? '代表者' : '担当者' ?></td>
          <td><?= empty($mm['bio']) ? '未入力' : (!empty($mm['is_active']) ? '公開中' : '非公開') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
document.querySelectorAll('tr.row-link').forEach(function (tr) {
  tr.addEventListener('click', function (e) {
    if (e.target.closest('a')) { return; }
    location.href = tr.getAttribute('data-href');
  });
});
</script>
<?php render_footer(); ?>
