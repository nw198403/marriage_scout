<?php
// admin/counselor.php：運営用。1人の担当者のプロフィールを確認する（参考情報。承認・却下は相談所単位＝admin/agency.php）

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

require_admin();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT c.*, u.email, a.id AS agency_id, a.store_no, a.agency_name, a.approval_status
     FROM counselors c JOIN users u ON u.id = c.user_id JOIN agencies a ON a.id = c.agency_id
     WHERE c.user_id = ?'
);
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) {
    http_response_code(404);
    exit('担当者が見つかりません。');
}

$id4 = counselor_display_id($c['store_no'], (int)$c['counselor_no']);
render_header('担当者：' . $c['display_name'], 'admin');
?>
<div class="card admin-detail">
  <p class="back-link"><a href="<?= h(url('admin/agency.php?id=' . (int)$c['agency_id'])) ?>">‹ <?= h($c['agency_name']) ?> に戻る</a></p>
  <h1><?= h($c['display_name']) ?> <span class="badge badge-<?= h($c['approval_status']) ?>"><?= h(APPROVAL_LABELS[$c['approval_status']]) ?></span></h1>
  <div class="sheet-wrap">
  <table class="sheet sheet-detail">
    <tr><th>担当者ID</th><td><?= h($id4) ?></td><th>役割</th><td><?= $c['is_representative'] ? '代表者' : '担当者' ?></td></tr>
    <tr><th>ログインメール</th><td colspan="3"><?= h($c['email']) ?></td></tr>
    <tr><th>拠点</th><td><?= h($c['prefecture'] ?? '') ?></td><th>対応エリア</th><td><?= h(format_areas(load_counselor_areas($pdo, [$id])[$id] ?? [], $c['service_style'])) ?></td></tr>
    <tr><th>入会金／月会費／成婚料</th><td colspan="3" class="nowrap"><?= number_format((int)$c['entry_fee']) ?>円 ／ <?= number_format((int)$c['monthly_fee']) ?>円 ／ <?= number_format((int)$c['success_fee']) ?>円</td></tr>
    <tr><th>ブログ</th><td><?= h($c['blog_url'] ?: '—') ?></td><th>SNS</th><td><?= h($c['sns_url'] ?: '—') ?></td></tr>
    <tr><th>公開状況</th><td colspan="3"><?= empty($c['bio']) ? '未入力' : (!empty($c['is_active']) ? '公開中' : '非公開') ?></td></tr>
    <tr><th>自己紹介</th><td colspan="3"><?= $c['bio'] ? nl2br(h($c['bio'])) : '—' ?></td></tr>
  </table>
  </div>
  <p class="hint">連盟・加盟番号・書類の審査は相談所単位です。<a href="<?= h(url('admin/agency.php?id=' . (int)$c['agency_id'])) ?>">相談所の審査画面</a>で確認・承認してください。</p>
</div>
<?php render_footer(); ?>
