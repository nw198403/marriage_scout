<?php
// member/counselor.php：カウンセラーのプロフィールと評価・口コミ（スカウトの有無に関わらず、会員なら誰でも閲覧できる）

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('member');
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

// 承認済み・公開中のカウンセラーだけ見られる
$stmt = $pdo->prepare(
    "SELECT c.user_id AS counselor_id, c.display_name, c.mbti_type, c.photo_path, a.agency_name, a.affiliation, c.prefecture, c.service_style, c.bio,
            c.blog_url, c.sns_url, c.marriage_count, c.experience_years, c.entry_fee, c.monthly_fee, c.success_fee
     FROM counselors c JOIN agencies a ON a.id = c.agency_id
     WHERE c.user_id = ? AND a.approval_status = 'approved' AND c.is_active = 1"
);
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) {
    http_response_code(404);
    render_header('見つかりません', 'member');
    echo '<div class="card"><p>このカウンセラーは表示できません。</p><p><a href="' . h(url('member/counselors.php')) . '">カウンセラー一覧に戻る</a></p></div>';
    render_footer();
    exit;
}

$stmt = $pdo->prepare('SELECT t.name, t.category FROM counselor_support_tags ct JOIN support_tags t ON t.id = ct.tag_id
                       WHERE ct.counselor_id = ? ORDER BY t.category, t.sort_order');
$stmt->execute([$id]);
$tags = ['concern' => [], 'age' => [], 'marital' => [], 'service' => []];
foreach ($stmt->fetchAll() as $t) {
    $tags[$t['category']][] = $t['name'];
}

$ratingMap = counselor_rating_summary($pdo, [$id]);
$rating = $ratingMap[$id] ?? null;

$recentReviews = [];
if ($rating) {
    $reviewCols = implode(', ', array_keys(REVIEW_LABELS));
    $stmt = $pdo->prepare("SELECT $reviewCols, comment, created_at FROM reviews
                           WHERE counselor_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$id]);
    $recentReviews = $stmt->fetchAll();
}

render_header($c['agency_name'], 'member');
?>
<p><a href="<?= h(url('member/counselors.php')) ?>">‹ カウンセラー一覧に戻る</a></p>
<div class="card">
  <div class="counselor-detail-head">
    <?php if (!empty($c['photo_path'])): ?>
      <img src="<?= h(url('photo.php?cid=' . $id)) ?>" alt="" class="counselor-photo-lg">
    <?php else: ?>
      <span class="counselor-photo-lg counselor-photo-placeholder" aria-hidden="true"></span>
    <?php endif; ?>
    <div>
      <h1><?= h($c['agency_name']) ?></h1>
      <p><strong><?= h($c['display_name']) ?></strong></p>
      <p class="muted"><?= $rating ? star_html($rating['avg'], $rating['count']) : 'まだ評価はありません' ?></p>
    </div>
  </div>
  <table class="kv">
    <?php if ($c['mbti_type']): ?><tr><th>MBTI</th><td><?= h($c['mbti_type']) ?>（<?= h(MBTI_LABELS[$c['mbti_type']] ?? '') ?>・自己申告）</td></tr><?php endif; ?>
    <tr><th>加盟連盟</th><td><?= h($c['affiliation'] ?? '—') ?>（運営が加盟を確認済み）</td></tr>
    <tr><th>拠点</th><td><?= h($c['prefecture'] ?? '') ?></td></tr>
    <tr><th>対応エリア</th><td><?= h(format_areas(load_counselor_areas($pdo, [$id])[$id] ?? [], $c['service_style'])) ?></td></tr>
    <tr><th>面談の方法</th><td><?= h(SERVICE_STYLE_LABELS[$c['service_style']] ?? '') ?></td></tr>
    <tr><th>入会金</th><td><?= number_format((int)$c['entry_fee']) ?>円</td></tr>
    <tr><th>月会費</th><td><?= number_format((int)$c['monthly_fee']) ?>円</td></tr>
    <tr><th>成婚料</th><td><?= number_format((int)$c['success_fee']) ?>円</td></tr>
    <tr><th>経験年数</th><td><?= $c['experience_years'] !== null ? (int)$c['experience_years'] . '年' : '—' ?></td></tr>
    <tr><th>成婚数</th><td><?= $c['marriage_count'] !== null ? (int)$c['marriage_count'] . '組（自己申告）' : '—' ?></td></tr>
    <?php if ($c['blog_url']): ?><tr><th>ブログ</th><td><a href="<?= h($c['blog_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($c['blog_url']) ?></a></td></tr><?php endif; ?>
    <?php if ($c['sns_url']): ?><tr><th>SNS</th><td><a href="<?= h($c['sns_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($c['sns_url']) ?></a></td></tr><?php endif; ?>
  </table>
  <?php foreach (['concern' => '得意な不安・悩み', 'age' => '得意な年代', 'marital' => '得意な婚姻歴', 'service' => '提供できるサポート'] as $cat => $label): if ($tags[$cat]): ?>
    <p class="muted" style="margin-bottom:.2rem"><?= h($label) ?></p>
    <p class="tag-list" style="margin-top:0"><?php foreach ($tags[$cat] as $tn): ?><span class="tag"><?= h($tn) ?></span><?php endforeach; ?></p>
  <?php endif; endforeach; ?>
  <?php if ($c['bio']): ?><h2>自己紹介・サポート方針</h2><p><?= nl2br(h($c['bio'])) ?></p><?php endif; ?>
</div>

<div class="card">
  <h2>会員からの評価<?= $rating ? '（' . (int)$rating['count'] . '件）' : '' ?><?php if ($rating): ?> <span class="review-disclaimer">評価は契約成立した会員による匿名の投稿です。</span><?php endif; ?></h2>
  <?php if (!$rating): ?>
    <p class="muted">まだ評価はありません。契約が成立した会員から、レビューが届くとここに表示されます。</p>
  <?php else: ?>
    <p><?= star_html($rating['avg'], $rating['count']) ?></p>
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
