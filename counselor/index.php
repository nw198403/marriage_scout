<?php
// counselor/index.php：カウンセラーのダッシュボード（仮）

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('counselor');

$stmt = db()->prepare(
    'SELECT c.display_name, c.is_active, c.bio, c.is_representative, a.agency_name, a.approval_status, a.review_note
     FROM counselors c JOIN agencies a ON a.id = c.agency_id
     WHERE c.user_id = ?'
);
$stmt->execute([$user['id']]);
$c = $stmt->fetch();

$fStatus = (string)($_GET['status'] ?? '');
$where = 's.counselor_id = ?';
$params = [$user['id']];
if ($fStatus !== '' && isset(SCOUT_STATUS_LABELS[$fStatus])) {
    $where .= ' AND s.status = ?';
    $params[] = $fStatus;
}

$stmt = db()->prepare(
    'SELECT s.id, s.status, s.closed_reason, s.sent_at, s.expires_at, m.nickname,
            COALESCE(slb.starts_at, slp.starts_at) AS starts_at,
            COALESCE(slb.ends_at, slp.ends_at) AS ends_at,
            COALESCE(slb.chosen_start_at, slp.chosen_start_at) AS chosen_start_at,
            COALESCE(slb.duration_min, slp.duration_min) AS duration_min,
            COALESCE(slb.meeting_type, slp.meeting_type) AS meeting_type,
            COALESCE(slb.location, slp.location) AS location
     FROM scouts s
     JOIN members m ON m.user_id = s.member_id
     LEFT JOIN scout_slots slb ON slb.id = s.booked_slot_id
     LEFT JOIN scout_slots slp ON slp.id = s.proposed_slot_id
     WHERE ' . $where . '
     ORDER BY s.sent_at ASC'
);
$stmt->execute($params);
$scoutRows = $stmt->fetchAll();

$unreadMessages = unread_message_count(db(), $user['id'], 'counselor');

$pendingContracts = 0;
$pendingAgencies = 0;
if (is_admin($user)) {
    $pendingContracts = (int)db()->query(
        "SELECT COUNT(*) FROM contracts WHERE status IN ('reported', 'confirming')"
    )->fetchColumn();
    $pendingAgencies = (int)db()->query(
        "SELECT COUNT(*) FROM agencies WHERE approval_status = 'pending'"
    )->fetchColumn();
}

$journeyBar = render_journey_bar(counselor_journey_step(db(), $user['id']), COUNSELOR_JOURNEY_STEPS);
render_header('ダッシュボード', 'counselor', '', $journeyBar);
?>
<?php if ($pendingContracts > 0): ?>
<div class="notice-banner">
  <div class="notice-banner-inner">
  <div class="notice-row">
    <div class="notice-row-text">
      <div class="notice-row-top">
        <span class="badge badge-important">重要</span>
        <strong class="notice-title">確認待ちの契約申告があります</strong>
      </div>
      <span class="notice-desc"><?= $pendingContracts ?>件の契約申告が確認待ちです。</span>
    </div>
    <a class="btn btn-small notice-row-btn" href="<?= h(url('admin/contracts.php')) ?>">確認する</a>
  </div>
  </div>
</div>
<?php endif; ?>
<?php if ($pendingAgencies > 0): ?>
<div class="notice-banner">
  <div class="notice-banner-inner">
  <div class="notice-row">
    <div class="notice-row-text">
      <div class="notice-row-top">
        <span class="badge badge-important">重要</span>
        <strong class="notice-title">確認待ちの相談所審査があります</strong>
      </div>
      <span class="notice-desc"><?= $pendingAgencies ?>件の相談所が審査待ちです。</span>
    </div>
    <a class="btn btn-small notice-row-btn" href="<?= h(url('admin/agencies.php')) ?>">確認する</a>
  </div>
  </div>
</div>
<?php endif; ?>
<?php if ($unreadMessages > 0): ?>
<div class="notice-banner">
  <div class="notice-banner-inner">
  <div class="notice-row">
    <div class="notice-row-text">
      <div class="notice-row-top">
        <span class="badge badge-important">重要</span>
        <strong class="notice-title">新着メッセージがあります</strong>
      </div>
      <span class="notice-desc"><?= $unreadMessages ?>件の未読メッセージがあります。</span>
    </div>
    <a class="btn btn-small notice-row-btn" href="<?= h(url('counselor/messages.php')) ?>">確認する</a>
  </div>
  </div>
</div>
<?php endif; ?>
<div class="card">
  <h1><?= h($c['agency_name'] ?? '') ?> の <?= h($c['display_name'] ?? '') ?> さん</h1>
  <div class="counselor-status-row">
    <span>相談所：<strong><?= h(APPROVAL_LABELS[$c['approval_status']] ?? '') ?></strong><?= $c['approval_status'] === 'rejected' && $c['review_note'] ? '（' . h($c['review_note']) . '）' : '' ?></span>
    <a class="btn btn-small btn-secondary" href="<?= h(url('counselor/agency.php')) ?>">情報編集</a>
  </div>
  <div class="counselor-status-row">
    <span>プロフィール：<?= empty($c['bio']) ? '未入力' : (!empty($c['is_active']) ? '公開中' : '非公開') ?></span>
    <a class="btn btn-small" href="<?= h(url('counselor/profile.php')) ?>">プロフィール編集</a>
  </div>
</div>

<div class="card">
  <h2>スカウト一覧</h2>
  <form method="get" action="<?= h(url('counselor/index.php')) ?>" class="scout-status-filter">
    <label>面談状況で絞り込み
      <select name="status" onchange="this.form.submit()">
        <option value="">すべて</option>
        <?php foreach (SCOUT_STATUS_LABELS as $k => $l): ?>
          <option value="<?= h($k) ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= h($l) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <noscript><button type="submit" class="btn btn-small">絞り込む</button></noscript>
    <?php if ($fStatus !== ''): ?><a href="<?= h(url('counselor/index.php')) ?>" class="filter-reset">絞り込みをクリア</a><?php endif; ?>
  </form>
  <?php if (!$scoutRows): ?>
    <p class="muted"><?= $fStatus !== '' ? 'この状況のスカウトはありません。' : 'まだスカウトを送っていません。<a href="' . h(url('counselor/members.php')) . '">会員を探す</a>' ?></p>
  <?php else: ?>
  <div class="sheet-wrap">
    <table class="sheet">
      <thead>
        <tr><th>会員氏名</th><th>状況</th><th>面談日時</th><th>面談方法</th><th>場所</th><th>詳細</th></tr>
      </thead>
      <tbody>
      <?php foreach ($scoutRows as $r): $ds = scout_display_status($r); $href = url('counselor/scout.php?id=' . (int)$r['id']); ?>
        <tr class="row-link" data-href="<?= h($href) ?>">
          <td><?= h($r['nickname']) ?> さん</td>
          <td><span class="badge <?= $r['status'] === 'booked' ? 'badge-now' : ($r['status'] === 'proposed' ? 'badge-pending' : 'badge-sent') ?>"><?= h($ds) ?></span></td>
          <td><?= $r['starts_at'] ? h(fmt_chosen_time($r)) : '—' ?></td>
          <td><?= $r['meeting_type'] ? h(MEETING_TYPE_LABELS[$r['meeting_type']] ?? '') : '—' ?></td>
          <td><?= $r['meeting_type'] && $r['meeting_type'] !== 'online' && $r['location'] ? h($r['location']) : '—' ?></td>
          <td><a href="<?= h($href) ?>">詳細を見る</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
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
