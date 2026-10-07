<?php
// member/messages.php：カウンセラーとのメッセージ一覧（スレッド一覧）

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('member');
$pdo = db();

$stmt = $pdo->prepare(
    "SELECT s.id, s.status, s.sent_at, c.user_id AS counselor_id, c.display_name, c.photo_path, a.agency_name
     FROM scouts s JOIN counselors c ON c.user_id = s.counselor_id JOIN agencies a ON a.id = c.agency_id
     WHERE s.member_id = ? AND a.approval_status = 'approved' AND c.is_active = 1
       AND s.status IN ('proposed', 'booked', 'met', 'contracted')
     ORDER BY s.sent_at DESC"
);
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

$rows = attach_message_summaries($pdo, $rows, 'member');
usort($rows, function ($a, $b) {
    $ta = strtotime($a['last_message_time'] ?? $a['sent_at']);
    $tb = strtotime($b['last_message_time'] ?? $b['sent_at']);
    return $tb <=> $ta;
});

render_header('メッセージ', 'member');
?>
<div class="card">
  <h1>メッセージ</h1>
  <p class="muted">初回面談の候補を選ぶ（またはスカウトを受ける）と、カウンセラーとメッセージのやり取りができるようになります。</p>
</div>

<?php if (!$rows): ?>
  <div class="card"><p class="muted">まだメッセージのやり取りができるカウンセラーはいません。<a href="<?= h(url('member/scouts.php')) ?>">届いたスカウトを見る</a></p></div>
<?php endif; ?>

<?php foreach ($rows as $r): ?>
  <a class="message-row <?= $r['unread_count'] > 0 ? 'is-unread' : '' ?>" href="<?= h(url('member/chat.php?id=' . (int)$r['id'])) ?>">
    <?php if (!empty($r['photo_path'])): ?>
      <img src="<?= h(url('photo.php?cid=' . (int)$r['counselor_id'])) ?>" alt="" class="counselor-photo-sm">
    <?php else: ?>
      <span class="counselor-photo-sm counselor-photo-placeholder" aria-hidden="true"></span>
    <?php endif; ?>
    <div class="message-row-body">
      <div class="message-row-top">
        <span class="message-row-name"><?= h($r['agency_name']) ?>（<?= h($r['display_name']) ?>）</span>
        <span class="message-row-time"><?= h(chat_list_time($r['last_message_time'] ?? $r['sent_at'])) ?></span>
      </div>
      <p class="message-row-preview"><?php
        if ($r['last_message_preview'] === null) {
            echo 'まだメッセージはありません';
        } elseif ($r['last_message_preview'] === '') {
            echo '（ファイルを送信しました）';
        } else {
            echo h($r['last_message_preview']);
        }
      ?></p>
    </div>
    <?php if ($r['unread_count'] > 0): ?><span class="badge badge-important message-row-badge"><?= (int)$r['unread_count'] ?></span><?php endif; ?>
  </a>
<?php endforeach; ?>
<?php render_footer(); ?>
