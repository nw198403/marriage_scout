<?php
// counselor/scouts.php：送ったスカウトの一覧

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('counselor');
$pdo = db();

$stmt = $pdo->prepare(
    'SELECT s.id, s.status, s.closed_reason, s.sent_at, s.expires_at, m.nickname,
            COALESCE(slb.starts_at, slp.starts_at) AS starts_at
     FROM scouts s
     JOIN members m ON m.user_id = s.member_id
     LEFT JOIN scout_slots slb ON slb.id = s.booked_slot_id
     LEFT JOIN scout_slots slp ON slp.id = s.proposed_slot_id
     WHERE s.counselor_id = ?
     ORDER BY FIELD(s.status, \'proposed\', \'booked\', \'sent\', \'met\', \'contracted\', \'closed\'), s.sent_at DESC'
);
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM scouts WHERE counselor_id = ? AND status = 'booked'");
$stmt->execute([$user['id']]);
$booked = (int)$stmt->fetchColumn();

// スカウトごとの未読メッセージ件数（会員からの分）
$unreadMap = [];
if ($rows) {
    $ids = array_column($rows, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT scout_id, COUNT(*) FROM scout_messages WHERE scout_id IN ($in) AND sender_role = 'member' AND read_at IS NULL GROUP BY scout_id");
    $st->execute($ids);
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$sid, $cnt]) {
        $unreadMap[(int)$sid] = (int)$cnt;
    }
}

$journeyBar = render_journey_bar(counselor_journey_step($pdo, $user['id']), COUNSELOR_JOURNEY_STEPS);
render_header('送ったスカウト', 'counselor', '', $journeyBar);
?>
<div class="card">
  <h1>送ったスカウト</h1>
  <p class="muted">面談の予約が入っている件数：<?= $booked ?> / <?= INTERVIEW_LIMIT_PER_COUNSELOR ?>（上限に達すると、新しい面談の予約は入りません）</p>
</div>
<?php if (!$rows): ?>
  <div class="card"><p>まだスカウトを送っていません。<a href="<?= h(url('counselor/members.php')) ?>">会員を探す</a></p></div>
<?php endif; ?>
<?php foreach ($rows as $r): $ds = scout_display_status($r); ?>
  <div class="card member-card">
    <div class="member-card-head">
      <a class="member-card-name" href="<?= h(url('counselor/scout.php?id=' . (int)$r['id'])) ?>"><?= h($r['nickname']) ?> さん</a>
      <span class="badge <?= $r['status'] === 'booked' ? 'badge-now' : ($r['status'] === 'proposed' ? 'badge-pending' : 'badge-sent') ?>"><?= h($ds) ?></span>
      <?php if (!empty($unreadMap[(int)$r['id']])): ?><span class="badge badge-important">新着メッセージ<?= (int)$unreadMap[(int)$r['id']] ?></span><?php endif; ?>
    </div>
    <p class="muted">送信：<?= h(date('n月j日', strtotime($r['sent_at']))) ?><?= $r['starts_at'] ? ' ／ 面談：' . h(fmt_dt($r['starts_at'])) : '' ?></p>
  </div>
<?php endforeach; ?>
<?php render_footer(); ?>
