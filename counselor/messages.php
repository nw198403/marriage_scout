<?php
// counselor/messages.php：会員とのメッセージ一覧（スレッド一覧）

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('counselor');
$pdo = db();

$stmt = $pdo->prepare(
    "SELECT s.id, s.status, s.sent_at, m.user_id AS member_id, m.nickname
     FROM scouts s JOIN members m ON m.user_id = s.member_id
     WHERE s.counselor_id = ? AND s.status IN ('proposed', 'booked', 'met', 'contracted')
     ORDER BY s.sent_at DESC"
);
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

$rows = attach_message_summaries($pdo, $rows, 'counselor');
usort($rows, function ($a, $b) {
    $ta = strtotime($a['last_message_time'] ?? $a['sent_at']);
    $tb = strtotime($b['last_message_time'] ?? $b['sent_at']);
    return $tb <=> $ta;
});

render_header('メッセージ', 'counselor');
?>
<div class="card">
  <h1>メッセージ</h1>
  <p class="muted">会員がスカウトに反応する（初回面談の候補を選ぶ、またはスカウトを受ける）と、メッセージのやり取りができるようになります。</p>
</div>

<?php if (!$rows): ?>
  <div class="card"><p class="muted">まだメッセージのやり取りができる会員はいません。<a href="<?= h(url('counselor/scouts.php')) ?>">送ったスカウトを見る</a></p></div>
<?php endif; ?>

<?php foreach ($rows as $r): ?>
  <a class="message-row <?= $r['unread_count'] > 0 ? 'is-unread' : '' ?>" href="<?= h(url('counselor/chat.php?id=' . (int)$r['id'])) ?>">
    <span class="counselor-photo-sm counselor-photo-placeholder" aria-hidden="true"></span>
    <div class="message-row-body">
      <div class="message-row-top">
        <span class="message-row-name"><?= h($r['nickname']) ?> さん</span>
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
