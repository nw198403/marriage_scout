<?php
// counselor/agency.php：相談所の情報（店番・連盟・書類など）を管理する
// 編集できるのは代表者のみ。代表者以外は、確認用に読み取りだけできる

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';
require_once __DIR__ . '/../includes/upload.php';

$user = require_login('counselor');
$pdo = db();

$stmt = $pdo->prepare('SELECT c.agency_id, c.is_representative, c.display_name FROM counselors c WHERE c.user_id = ?');
$stmt->execute([$user['id']]);
$me = $stmt->fetch();
if (!$me) {
    http_response_code(404);
    exit('カウンセラー情報が見つかりません。');
}
$isRep = !empty($me['is_representative']);

$stmt = $pdo->prepare('SELECT * FROM agencies WHERE id = ?');
$stmt->execute([(int)$me['agency_id']]);
$a = $stmt->fetch();
if ($a && isset(AFFILIATION_LEGACY[(string)$a['affiliation']])) {
    $a['affiliation'] = AFFILIATION_LEGACY[$a['affiliation']];
}

$stmt = $pdo->prepare('SELECT display_name, counselor_no, is_representative FROM counselors WHERE agency_id = ? ORDER BY counselor_no');
$stmt->execute([(int)$me['agency_id']]);
$members = $stmt->fetchAll();

$errors = [];

if ($isRep && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'withdraw_agency') {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE agencies SET withdrawn_at = NOW() WHERE id = ?')->execute([(int)$a['id']]);
        $pdo->prepare('UPDATE counselors SET is_active = 0 WHERE agency_id = ?')->execute([(int)$a['id']]);
        $pdo->prepare(
            "UPDATE users u JOIN counselors c ON c.user_id = u.id SET u.status = 'withdrawn' WHERE c.agency_id = ?"
        )->execute([(int)$a['id']]);
        $pdo->commit();
        logout_user();
        flash('ok', '相談所を退会しました。ご利用ありがとうございました。');
        redirect('index.php');
    }

    $in = [
        'agency_name'        => trim((string)($_POST['agency_name'] ?? '')),
        'agency_email'       => trim((string)($_POST['agency_email'] ?? '')),
        'affiliation'        => (string)($_POST['affiliation'] ?? ''),
        'affiliation_number' => trim((string)($_POST['affiliation_number'] ?? '')),
    ];
    $draft = isset($_POST['draft']);   // 途中保存：必須項目が空でも保存できる（審査には出さない）

    if ($in['agency_name'] === '' || mb_strlen($in['agency_name']) > 100) {
        $errors[] = '相談所名を100文字以内で入力してください。';
    }
    if (!($draft && $in['agency_email'] === '') && ($in['agency_email'] === '' || mb_strlen($in['agency_email']) > 255 || !filter_var($in['agency_email'], FILTER_VALIDATE_EMAIL))) {
        $errors[] = '相談所の連絡先メールアドレスを正しく入力してください。';
    }
    if (!($draft && $in['affiliation'] === '') && !in_array($in['affiliation'], AFFILIATION_OPTIONS, true)) {
        $errors[] = '加盟連盟を選んでください。';
    }
    if ($in['affiliation'] !== '無所属' && ((!$draft && $in['affiliation_number'] === '') || mb_strlen($in['affiliation_number']) > 50)) {
        $errors[] = '連盟の加盟番号（会員番号）を50文字以内で入力してください。';
    }
    $hasNewDoc = !empty($_FILES['affiliation_doc']['name']);
    if (!$draft && !$hasNewDoc && empty($a['affiliation_doc_path'])) {
        $errors[] = '加盟を確認できる書類（加盟証明書など）をアップロードしてください。';
    }

    if (!$errors) {
        $newDoc = null;
        try {
            if ($hasNewDoc) {
                $newDoc = save_upload($_FILES['affiliation_doc'], 'counselor_docs', ['image/jpeg', 'image/png', 'application/pdf']);
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        // 相談所名・連盟・番号・書類を変えたら、もう一度、運営の確認が必要
        $identityChanged = $newDoc !== null
            || $in['agency_name'] !== (string)$a['agency_name']
            || $in['affiliation'] !== (string)$a['affiliation']
            || $in['affiliation_number'] !== (string)$a['affiliation_number'];
        $status = $a['approval_status'];
        if (!$draft && $identityChanged && $status !== 'pending') {
            $status = 'pending';
            flash('ok', '連盟の情報が変わったため、運営の確認をもう一度受けます。確認が済むまで、公開とスカウトはできません。');
        }
        $docPath = $newDoc['path'] ?? $a['affiliation_doc_path'];

        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                'UPDATE agencies SET agency_name = ?, agency_email = ?, affiliation = ?, affiliation_number = ?, affiliation_doc_path = ?,
                        approval_status = ?, approved_at = ?, review_note = ?
                 WHERE id = ?'
            )->execute([
                $in['agency_name'], $in['agency_email'] === '' ? null : $in['agency_email'], $in['affiliation'] === '' ? null : $in['affiliation'],
                $in['affiliation'] === '無所属' && $in['affiliation_number'] === '' ? null : $in['affiliation_number'],
                $docPath,
                $status,
                $status === 'approved' ? $a['approved_at'] : null,
                ($status === 'approved' || !$identityChanged) ? $a['review_note'] : null,
                (int)$a['id'],
            ]);
            $pdo->commit();
            if ($newDoc !== null) {
                delete_upload($a['affiliation_doc_path']);
            }
            flash('ok', $draft ? '途中まで保存しました（審査には出しません）。未入力の項目と加盟書類をそろえて「保存する」を押すと、運営の確認に進めます。' : '相談所の情報を保存しました。');
            redirect('counselor/agency.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($newDoc !== null) {
                delete_upload($newDoc['path']);
            }
            throw $e;
        }
    }

    $a = array_merge($a, $in);
}

render_header('相談所の情報', 'counselor');
?>
<div class="card">
  <h1>相談所の情報</h1>
  <p class="muted">店番：<strong><?= h($a['store_no']) ?></strong>　所属する担当者：<?= count($members) ?>人</p>

  <?php $ast = $a['approval_status'] ?? 'pending'; ?>
  <div class="approval approval-<?= h($ast) ?>">
    <strong>運営の確認：<?= h(APPROVAL_LABELS[$ast]) ?></strong>
    <?php if ($ast === 'pending'): ?>
      <p>連盟の加盟番号と書類を、運営が確認します。確認が済むまで、会員への公開とスカウトはできません（相談所内の全員に共通です）。</p>
    <?php elseif ($ast === 'rejected'): ?>
      <p>確認できませんでした。<?= $a['review_note'] ? '理由：' . h($a['review_note']) : '' ?>内容を直して保存すると、もう一度確認を受けられます。</p>
    <?php else: ?>
      <p>相談所名・連盟・加盟番号・書類を変更すると、もう一度確認が必要になります。</p>
    <?php endif; ?>
  </div>

  <?php if (!$isRep): ?>
    <p class="hint">相談所の情報は、代表者（<?php foreach ($members as $mm): if ($mm['is_representative']): ?><?= h($mm['display_name']) ?><?php endif; endforeach; ?>）が管理しています。内容の変更が必要なときは、代表者にご連絡ください。</p>
  <?php endif; ?>

  <?php foreach ($errors as $e): ?>
    <div class="flash flash-error"><?= h($e) ?></div>
  <?php endforeach; ?>

  <?php if ($isRep): ?>
  <form method="post" action="<?= h(url('counselor/agency.php')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-row">
      <label for="agency_name">相談所名</label>
      <input type="text" id="agency_name" name="agency_name" maxlength="100" value="<?= h($a['agency_name'] ?? '') ?>" required>
    </div>
    <div class="form-row">
      <label for="agency_email">相談所の連絡先メールアドレス <span class="hint">（会員には表示されません。契約が成立したときの確認メールを、運営がこのアドレスにお送りします）</span></label>
      <input type="email" id="agency_email" name="agency_email" maxlength="255" value="<?= h($a['agency_email'] ?? '') ?>" required>
    </div>
    <div class="form-row">
      <label for="affiliation">加盟連盟</label>
      <select id="affiliation" name="affiliation" required>
        <option value="">選んでください</option>
        <?php foreach (AFFILIATION_OPTIONS as $af): ?>
          <option value="<?= h($af) ?>" <?= ($a['affiliation'] ?? '') === $af ? 'selected' : '' ?>><?= h($af) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <label for="affiliation_number">連盟の加盟番号（会員番号） <span class="hint">（「無所属」の場合は空欄でOK）</span></label>
      <input type="text" id="affiliation_number" name="affiliation_number" maxlength="50" value="<?= h($a['affiliation_number'] ?? '') ?>">
    </div>
    <div class="form-row">
      <label for="affiliation_doc">加盟を確認できる書類 <span class="hint">（加盟証明書など。「無所属」の場合は開業届など、事業を確認できる書類。JPG・PNG・PDF、5MBまで）</span></label>
      <input type="file" id="affiliation_doc" name="affiliation_doc" accept=".jpg,.jpeg,.png,.pdf">
      <?php if (!empty($a['affiliation_doc_path'])): ?><p class="hint">提出済みです。差し替える場合だけ選んでください。</p><?php endif; ?>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn"><?= !empty($a['affiliation']) ? '上書き保存する' : '保存する' ?></button>
      <button type="submit" name="draft" value="1" formnovalidate class="btn btn-secondary">途中まで保存する（下書き）</button>
    </div>
    <p class="hint form-actions-note">加盟書類など、まだそろっていない項目があるときは、途中まで保存を選択してください。</p>
  </form>

  <h2>担当者を招待する</h2>
  <p class="muted">同じ相談所で働く担当者は、以下の店番と招待コードを使って「カウンセラー登録」から参加できます。人には教えず、担当者にだけ共有してください。</p>
  <table class="kv">
    <tr><th>店番</th><td><strong><?= h($a['store_no']) ?></strong></td></tr>
    <tr><th>招待コード</th><td><strong><?= h($a['invite_code']) ?></strong></td></tr>
  </table>
  <?php else: ?>
  <table class="kv">
    <tr><th>相談所名</th><td><?= h($a['agency_name'] ?? '') ?></td></tr>
    <tr><th>加盟連盟</th><td><?= h($a['affiliation'] ?? '未入力') ?></td></tr>
    <tr><th>加盟番号</th><td><?= h($a['affiliation_number'] ?: '—') ?></td></tr>
  </table>
  <?php endif; ?>

  <h2>所属する担当者</h2>
  <table class="kv">
    <?php foreach ($members as $mm): ?>
      <tr><th><?= h(counselor_display_id($a['store_no'], (int)$mm['counselor_no'])) ?></th>
          <td><?= h($mm['display_name']) ?><?= $mm['is_representative'] ? '（代表者）' : '' ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($isRep): ?>
<div class="card">
  <h2>相談所の退会</h2>
  <p class="muted">相談所を退会すると、所属する担当者全員（<?= count($members) ?>人）がログインできなくなり、プロフィールもすべて非公開になります。この操作は取り消せません。</p>
  <form method="post" action="<?= h(url('counselor/agency.php')) ?>" id="withdraw-agency-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="withdraw_agency">
    <button type="submit" class="btn btn-secondary">相談所を退会する</button>
  </form>
</div>
<script>
document.getElementById('withdraw-agency-form').addEventListener('submit', function (e) {
  if (!confirm('本当に相談所を退会しますか？所属する担当者全員が退会扱いになり、この操作は取り消せません。')) {
    e.preventDefault();
  }
});
</script>
<?php endif; ?>
<?php render_footer(); ?>
