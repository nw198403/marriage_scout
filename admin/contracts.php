<?php
// admin/contracts.php：運営用。会員から申告された契約の一覧（対応が必要なものが先）

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

require_admin();

$rows = db()->query(
    "SELECT ct.id, ct.status, ct.contract_date, ct.entry_fee_snapshot, ct.referral_fee, ct.reported_at,
            ct.counselor_response, ct.agency_response, m.nickname, c.display_name, a.agency_name
     FROM contracts ct
     JOIN members m ON m.user_id = ct.member_id
     JOIN counselors c ON c.user_id = ct.counselor_id
     JOIN agencies a ON a.id = c.agency_id
     ORDER BY FIELD(ct.status, 'reported', 'confirming', 'confirmed', 'rejected'), ct.reported_at DESC"
)->fetchAll();

render_header('契約の確認', 'admin');
?>
<div class="card">
  <h1>契約の確認</h1>
  <p class="muted">会員が「契約した」と申告すると、ここに表示されます。カウンセラーと相談所に確認メールを送り、内容を確認したうえで、契約成立にするかを決めます。</p>
</div>
<?php if (!$rows): ?>
  <div class="card"><p>契約の申告はまだありません。</p></div>
<?php endif; ?>
<?php foreach ($rows as $r): $denied = $r['counselor_response'] === 'denied' || $r['agency_response'] === 'denied'; ?>
  <div class="card member-card">
    <div class="member-card-head">
      <a class="member-card-name" href="<?= h(url('admin/contract.php?id=' . (int)$r['id'])) ?>"><?= h($r['agency_name']) ?>（<?= h($r['display_name']) ?>） × <?= h($r['nickname']) ?> さん</a>
      <span class="badge <?= $r['status'] === 'confirmed' ? 'badge-approved' : ($r['status'] === 'rejected' ? 'badge-rejected' : 'badge-pending') ?>"><?= h(CONTRACT_STATUS_LABELS[$r['status']]) ?></span>
      <?php if ($denied): ?><span class="badge badge-rejected">否認あり</span><?php endif; ?>
    </div>
    <p class="muted">契約日：<?= h(date('Y年n月j日', strtotime($r['contract_date']))) ?> ／ 入会金 <?= number_format((int)$r['entry_fee_snapshot']) ?>円 ／ 紹介手数料 <?= number_format((int)$r['referral_fee']) ?>円 ／ 申告：<?= h(date('n月j日 H:i', strtotime($r['reported_at']))) ?></p>
  </div>
<?php endforeach; ?>
<?php render_footer(); ?>
