<?php
// member/index.php：会員ホーム。プロフィールの登録状況を表示する

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('member');

$stmt = db()->prepare('SELECT nickname, gender, birth_year, birth_month, readiness, readiness_expires_at FROM members WHERE user_id = ?');
$stmt->execute([$user['id']]);
$m = $stmt->fetch();

$profileDone = !empty($m['gender']) && !empty($m['birth_year']) && !empty($m['birth_month']);
$expired = $profileDone && $m['readiness_expires_at'] !== null && strtotime($m['readiness_expires_at']) < time();

$stmt = db()->prepare("SELECT COUNT(*) FROM scouts s JOIN counselors c ON c.user_id = s.counselor_id JOIN agencies a ON a.id = c.agency_id
    WHERE s.member_id = ? AND s.status = 'sent' AND s.expires_at > NOW() AND a.approval_status = 'approved' AND c.is_active = 1
    AND NOT EXISTS (SELECT 1 FROM scout_slots sl WHERE sl.scout_id = s.id)");
$stmt->execute([$user['id']]);
$newScouts = (int)$stmt->fetchColumn();

// カウンセラーから面談の候補日程が届いている（まだこちらが候補を選んでいない）もの
$stmt = db()->prepare("SELECT COUNT(*) FROM scouts s JOIN counselors c ON c.user_id = s.counselor_id JOIN agencies a ON a.id = c.agency_id
    WHERE s.member_id = ? AND s.status = 'sent' AND s.expires_at > NOW() AND a.approval_status = 'approved' AND c.is_active = 1
    AND EXISTS (SELECT 1 FROM scout_slots sl WHERE sl.scout_id = s.id)");
$stmt->execute([$user['id']]);
$proposedSchedules = (int)$stmt->fetchColumn();

$stmt = db()->prepare("SELECT s.id AS scout_id, c.display_name, a.agency_name, sl.*
    FROM scouts s JOIN counselors c ON c.user_id = s.counselor_id JOIN agencies a ON a.id = c.agency_id
    JOIN scout_slots sl ON sl.id = s.booked_slot_id
    WHERE s.member_id = ? AND s.status = 'booked'
    ORDER BY COALESCE(sl.chosen_start_at, sl.starts_at)");
$stmt->execute([$user['id']]);
$bookedInterviews = $stmt->fetchAll();

// 重要なお知らせ（5種類）：見逃されやすいので、ヘッダーの直下に横長のお知らせ帯でまとめて出す
$notices = [];

// (1) 契約成立（confirmed）だが、まだレビューを書いていないもの
$stmt = db()->prepare("SELECT ct.id AS contract_id, c.display_name, a.agency_name
    FROM contracts ct JOIN counselors c ON c.user_id = ct.counselor_id JOIN agencies a ON a.id = c.agency_id
    LEFT JOIN reviews r ON r.contract_id = ct.id
    WHERE ct.member_id = ? AND ct.status = 'confirmed' AND r.id IS NULL
    ORDER BY ct.decided_at");
$stmt->execute([$user['id']]);
foreach ($stmt->fetchAll() as $pr) {
    $notices[] = [
        'title' => h($pr['agency_name']) . 'への評価をお願いします',
        'desc'  => '契約が成立しました。評価にご協力ください（所要時間：1分）。',
        'url'   => url('member/review.php?contract_id=' . (int)$pr['contract_id']),
        'button' => '評価',
    ];
}

// (2) 面談実施済み（met）だが、まだ契約の申告をしていないもの
$stmt = db()->prepare("SELECT s.id AS scout_id, c.display_name, a.agency_name
    FROM scouts s JOIN counselors c ON c.user_id = s.counselor_id JOIN agencies a ON a.id = c.agency_id
    LEFT JOIN contracts ct ON ct.scout_id = s.id AND ct.member_id = s.member_id
    WHERE s.member_id = ? AND s.status = 'met' AND ct.id IS NULL
    ORDER BY s.met_at");
$stmt->execute([$user['id']]);
foreach ($stmt->fetchAll() as $mc) {
    $notices[] = [
        'title' => h($mc['agency_name']) . 'との契約を確認してください',
        'desc'  => '面談後、契約された場合はご申告をお願いします。',
        'url'   => url('member/scout.php?id=' . (int)$mc['scout_id'] . '#contract'),
        'button' => '確認',
    ];
}

// (3) 面談の候補日程が届いている（件数をまとめて1件のお知らせにする）
if ($proposedSchedules > 0) {
    $notices[] = [
        'title' => '面談の候補日程が届いています',
        'desc'  => $proposedSchedules . '件のスカウトで、面談の候補日程が届いています。ご都合のよい日時をお選びください。',
        'url'   => url('member/scouts.php'),
        'button' => '確認する',
    ];
}

// (4) 新しいスカウトの受領（まだ候補日程は届いていないもの。件数をまとめて1件のお知らせにする）
if ($newScouts > 0) {
    $notices[] = [
        'title' => '新しいスカウトが届いています',
        'desc'  => $newScouts . '件のスカウトが届いています。ぜひご確認ください。',
        'url'   => url('member/scouts.php'),
        'button' => '確認する',
    ];
}

// (5) カウンセラーからの新着メッセージ（件数をまとめて1件のお知らせにする）
$unreadMessages = unread_message_count(db(), $user['id'], 'member');
if ($unreadMessages > 0) {
    $notices[] = [
        'title' => '新着メッセージがあります',
        'desc'  => $unreadMessages . '件の未読メッセージがあります。',
        'url'   => url('member/messages.php'),
        'button' => '確認する',
    ];
}

// 重要なお知らせは、ヘッダーの直下に画面幅いっぱいで出す（本文の.containerより外側なので、render_header()のpreMain引数として渡す）
$preMain = '';
if ($notices) {
    ob_start();
    ?>
<div class="notice-banner">
  <div class="notice-banner-inner">
  <?php foreach ($notices as $n): ?>
    <div class="notice-row">
      <div class="notice-row-text">
        <div class="notice-row-top">
          <span class="badge badge-important">重要</span>
          <strong class="notice-title"><?= $n['title'] ?></strong>
        </div>
        <span class="notice-desc"><?= h($n['desc']) ?></span>
      </div>
      <a class="btn btn-small notice-row-btn" href="<?= h($n['url']) ?>"><?= h($n['button']) ?></a>
    </div>
  <?php endforeach; ?>
  </div>
</div>
    <?php
    $preMain = ob_get_clean();
}

$preMain = render_journey_bar(member_journey_step($pdo ?? db(), $user['id'])) . $preMain;
render_header('会員ホーム', 'member', '', $preMain);
?>
<div class="card">
  <h1>ようこそ、<?= h($m['nickname'] ?? '') ?>さん</h1>

  <?php if (!$profileDone): ?>
    <div class="flash flash-error">プロフィールが未入力です。入力すると、カウンセラーからスカウトが届くようになります。</div>
    <a class="btn" href="<?= h(url('member/profile.php')) ?>">プロフィールを入力する</a>
  <?php else: ?>
    <p>
      婚活を始める時期：<strong><?= h(READINESS_LABELS[$m['readiness']] ?? '') ?></strong><br>
      <span class="muted">
        有効期限：<?= h(date('Y年n月j日', strtotime($m['readiness_expires_at']))) ?>まで
        <?= $expired ? '（期限切れです。プロフィールを保存し直すと再び表示されます）' : '' ?>
      </span>
    </p>
    <a class="btn btn-secondary" href="<?= h(url('member/profile.php')) ?>">プロフィールを編集する</a>
  <?php endif; ?>
</div>
<?php if ($bookedInterviews): ?>
<div class="card">
  <h2>確定した面談</h2>
  <?php foreach ($bookedInterviews as $bi): ?>
    <div class="slot-row">
      <div>
        <p>
          <strong><?= h($bi['agency_name']) ?></strong>　<?= h($bi['display_name']) ?>さん<br>
          <?= h(fmt_chosen_time($bi)) ?>（<?= h(slot_meeting_label($bi)) ?>）
          <?php if ($bi['meeting_url']): ?><br>オンライン会議URL：<a href="<?= h($bi['meeting_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($bi['meeting_url']) ?></a><?php endif; ?>
        </p>
      </div>
      <a class="btn btn-secondary btn-small" href="<?= h(url('member/scout.php?id=' . (int)$bi['scout_id'])) ?>">詳細を見る</a>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="card">
  <h2>届いたスカウト</h2>
  <?php if ($newScouts > 0): ?><p><strong><?= $newScouts ?>件</strong>の新しいスカウトが届いています。</p><?php else: ?><p class="muted">新しいスカウトはありません。</p><?php endif; ?>
  <a class="btn btn-secondary" href="<?= h(url('member/scouts.php')) ?>">スカウトを見る</a>
</div>
<?php render_footer(); ?>
