<?php
// admin/contract.php：運営用。1件の契約を確認する
//   1) 確認メールを送る（カウンセラー本人と、所属先の相談所へ）  2) 回答を見て、契約成立にする／確認できなかったことにする

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';
require_once __DIR__ . '/../includes/mail.php';

require_admin();
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$load = function () use ($pdo, $id) {
    $stmt = $pdo->prepare(
        'SELECT ct.*, m.nickname, c.display_name, a.agency_name, a.agency_email, a.affiliation, a.affiliation_number, u.email AS counselor_email
         FROM contracts ct
         JOIN members m ON m.user_id = ct.member_id
         JOIN counselors c ON c.user_id = ct.counselor_id
         JOIN agencies a ON a.id = c.agency_id
         JOIN users u ON u.id = ct.counselor_id
         WHERE ct.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch();
};
$ct = $load();
if (!$ct) {
    http_response_code(404);
    exit('契約が見つかりません。');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'send_mail') {
        if (!in_array($ct['status'], ['reported', 'confirming'], true)) {
            $errors[] = 'この契約には、確認メールを送れません。';
        } elseif (!$ct['agency_email']) {
            $errors[] = '相談所の連絡先メールアドレスが未登録です。カウンセラーに、プロフィールへの入力を依頼してください。';
        } else {
            $cTok = $ct['counselor_token'] ?: bin2hex(random_bytes(16));
            $aTok = $ct['agency_token'] ?: bin2hex(random_bytes(16));
            $pdo->prepare("UPDATE contracts SET counselor_token = ?, agency_token = ?, status = 'confirming', mails_sent_at = NOW() WHERE id = ? AND status IN ('reported','confirming')")
                ->execute([$cTok, $aTok, $id]);
            $urlC = send_contract_confirm_mail($ct['counselor_email'], 'counselor', $ct, $cTok);
            $urlA = send_contract_confirm_mail($ct['agency_email'], 'agency', $ct, $aTok);
            if (DEV_MODE) {
                flash('ok', '（開発中のため、メールは送らず、URLだけ表示します）カウンセラー用：' . $urlC . '　相談所用：' . $urlA);
            } else {
                flash('ok', 'カウンセラーと相談所に、確認メールを送りました。');
            }
            redirect('admin/contract.php?id=' . $id);
        }
    } elseif ($action === 'confirm') {
        if ($ct['status'] !== 'confirming') {
            $errors[] = '確認メールを送ってから、確定してください。';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE contracts SET status = 'confirmed', decided_at = NOW(), admin_note = NULL WHERE id = ? AND status = 'confirming'")->execute([$id]);
                $pdo->prepare("UPDATE scouts SET status = 'contracted' WHERE id = ? AND status = 'met'")->execute([$ct['scout_id']]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            // 会員に、評価をお願いするメールを送る（DEV_MODEでは送らず、確認用のURLだけ表示）
            $memberEmail = null;
            $mstmt = $pdo->prepare('SELECT u.email FROM users u WHERE u.id = ?');
            $mstmt->execute([(int)$ct['member_id']]);
            $memberEmail = $mstmt->fetchColumn();
            $reviewUrl = null;
            if ($memberEmail) {
                $reviewUrl = send_review_request_mail($memberEmail, [
                    'contract_id' => $id,
                    'agency_name' => $ct['agency_name'],
                    'display_name' => $ct['display_name'],
                ]);
            }

            if (DEV_MODE) {
                flash('ok', '契約成立にしました。紹介手数料は ' . number_format((int)$ct['referral_fee']) . '円 です。（開発中のため、会員への評価依頼メールは送らず、確認用のURLだけ表示します）' . ($reviewUrl ?? ''));
            } else {
                flash('ok', '契約成立にしました。紹介手数料は ' . number_format((int)$ct['referral_fee']) . '円 です。会員に評価をお願いするメールを送りました。');
            }
            redirect('admin/contracts.php');
        }
    } elseif ($action === 'reject') {
        $note = trim((string)($_POST['admin_note'] ?? ''));
        if (!in_array($ct['status'], ['reported', 'confirming'], true)) {
            $errors[] = 'この契約は、すでに確定しています。';
        } elseif ($note === '' || mb_strlen($note) > 255) {
            $errors[] = '確認できなかった理由（記録用）を255文字以内で入力してください。';
        } else {
            $pdo->prepare("UPDATE contracts SET status = 'rejected', decided_at = NOW(), admin_note = ? WHERE id = ? AND status IN ('reported','confirming')")->execute([$note, $id]);
            flash('ok', '「確認できませんでした」にしました。');
            redirect('admin/contracts.php');
        }
    }
    $ct = $load();
}

$denied = $ct['counselor_response'] === 'denied' || $ct['agency_response'] === 'denied';
$fmt = fn($d) => $d ? date('n月j日 H:i', strtotime($d)) : '';

render_header('契約の確認', 'admin');
?>
<p><a href="<?= h(url('admin/contracts.php')) ?>">‹ 契約の一覧に戻る</a></p>
<div class="card">
  <h1><?= h($ct['agency_name']) ?>（<?= h($ct['display_name']) ?>） × <?= h($ct['nickname']) ?> さん</h1>
  <p><span class="badge <?= $ct['status'] === 'confirmed' ? 'badge-approved' : ($ct['status'] === 'rejected' ? 'badge-rejected' : 'badge-pending') ?>"><?= h(CONTRACT_STATUS_LABELS[$ct['status']]) ?></span>
     <?php if ($denied): ?><span class="badge badge-rejected">否認あり</span><?php endif; ?></p>
  <table class="kv">
    <tr><th>契約日（会員の申告）</th><td><?= h(date('Y年n月j日', strtotime($ct['contract_date']))) ?></td></tr>
    <tr><th>申告した日時</th><td><?= h(date('Y年n月j日 H:i', strtotime($ct['reported_at']))) ?></td></tr>
    <tr><th>入会金（申告時点）</th><td><?= number_format((int)$ct['entry_fee_snapshot']) ?>円</td></tr>
    <tr><th>紹介手数料（入会1件につき）</th><td><strong><?= number_format((int)$ct['referral_fee']) ?>円</strong></td></tr>
    <tr><th>加盟連盟／番号</th><td><?= h($ct['affiliation'] ?? '—') ?> ／ <?= h($ct['affiliation_number'] ?: '—') ?></td></tr>
    <tr><th>カウンセラーのメール</th><td><?= h($ct['counselor_email']) ?></td></tr>
    <tr><th>相談所の連絡先メール</th><td><?= h($ct['agency_email'] ?: '未登録') ?></td></tr>
  </table>
</div>

<div class="card">
  <h2>確認メールの回答</h2>
  <?php foreach ($errors as $e): ?><div class="flash flash-error"><?= h($e) ?></div><?php endforeach; ?>
  <table class="kv">
    <tr><th>メールを送った日時</th><td><?= $ct['mails_sent_at'] ? h(date('Y年n月j日 H:i', strtotime($ct['mails_sent_at']))) : '未送信' ?></td></tr>
    <tr><th>カウンセラー本人</th><td><?= h(CONTRACT_RESPONSE_LABELS[$ct['counselor_response']]) ?> <span class="muted"><?= h($fmt($ct['counselor_responded_at'])) ?></span></td></tr>
    <tr><th>所属の相談所</th><td><?= h(CONTRACT_RESPONSE_LABELS[$ct['agency_response']]) ?> <span class="muted"><?= h($fmt($ct['agency_responded_at'])) ?></span></td></tr>
  </table>
  <?php if ($denied): ?>
    <p class="muted">否認があります。会員が契約を申告しているため、それだけで契約なしとは判断せず、会員・カウンセラー・相談所に事情を確認してから決めてください。</p>
  <?php endif; ?>

  <?php if (in_array($ct['status'], ['reported', 'confirming'], true)): ?>
    <form method="post" action="<?= h(url('admin/contract.php')) ?>" style="margin-bottom:1rem">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <button type="submit" name="action" value="send_mail" class="btn btn-secondary"><?= $ct['status'] === 'reported' ? '確認メールを送る' : '確認メールを再送する' ?></button>
    </form>
    <form method="post" action="<?= h(url('admin/contract.php')) ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <div class="form-row">
        <label for="admin_note">確認できなかった場合の理由 <span class="hint">（運営の記録用。会員・カウンセラーには表示されません）</span></label>
        <input type="text" id="admin_note" name="admin_note" maxlength="255">
      </div>
      <button type="submit" name="action" value="confirm" class="btn" <?= $ct['status'] === 'confirming' ? '' : 'disabled' ?> onclick="return confirm('契約成立にしますか？');">契約成立にする</button>
      <button type="submit" name="action" value="reject" class="btn btn-secondary">確認できなかったことにする</button>
    </form>
  <?php elseif ($ct['admin_note']): ?>
    <p class="muted">記録：<?= h($ct['admin_note']) ?></p>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
