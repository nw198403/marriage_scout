<?php
// counselor/reviews.php：会員からの評価（星評価・コメント）

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('counselor');

$ratingMap = counselor_rating_summary(db(), [(int)$user['id']]);
$rating = $ratingMap[(int)$user['id']] ?? null;
$recentReviews = [];
if ($rating) {
    $cols = implode(', ', array_keys(REVIEW_LABELS));
    $stmt = db()->prepare("SELECT $cols, comment, created_at FROM reviews
                           WHERE counselor_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user['id']]);
    $recentReviews = $stmt->fetchAll();
}

render_header('会員からの評価', 'counselor');
?>
<div class="card">
  <h1>会員からの評価</h1>
  <?php if (!$rating): ?>
    <p class="muted">まだ評価はありません。契約が成立した会員から、レビューが届くとここに表示されます。</p>
  <?php else: ?>
    <p><?= star_html($rating['avg'], $rating['count']) ?> <span class="review-disclaimer">評価は契約成立した会員による匿名の投稿です。</span></p>
    <?php foreach ($recentReviews as $rv): ?>
      <div class="review-item">
        <p class="muted review-item-date"><?= h(date('Y年n月j日', strtotime($rv['created_at']))) ?></p>
        <div class="review-item-body">
          <div class="review-item-scores">
            <?php foreach (REVIEW_LABELS as $key => $label): ?>
              <p class="review-stat-row"><span class="review-stat-label"><?= h($label) ?></span><?= star_html((float)$rv[$key]) ?></p>
            <?php endforeach; ?>
          </div>
          <div class="review-item-comment">
            <?php if ($rv['comment']): ?><p><?= nl2br(h($rv['comment'])) ?></p><?php else: ?><p class="muted">コメントはありません。</p><?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
