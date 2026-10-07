<?php
// admin/agencies.php：運営用。相談所の掲載審査の一覧（Excelのような表。確認待ちが先）

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

require_admin();

$rows = db()->query(
    "SELECT a.id, a.store_no, a.agency_name, a.affiliation, a.affiliation_number, a.approval_status, a.withdrawn_at, a.created_at,
            COUNT(c.user_id) AS counselor_count
     FROM agencies a LEFT JOIN counselors c ON c.agency_id = a.id
     GROUP BY a.id
     ORDER BY FIELD(a.approval_status, 'pending', 'rejected', 'approved'), a.store_no DESC"
)->fetchAll();

$pending = count(array_filter($rows, fn($r) => $r['approval_status'] === 'pending'));

render_header('相談所の審査', 'admin');
?>
<div class="card">
  <h1>相談所の審査</h1>
  <p class="muted">行を選ぶと、詳細を開いて、承認・却下できます。確認待ち：<strong><?= $pending ?>件</strong> ／ 全<?= count($rows) ?>件</p>
  <?php if (!$rows): ?>
    <p>登録された相談所はいません。</p>
  <?php else: ?>
  <div class="sheet-wrap">
    <table class="sheet">
      <thead>
        <tr><th>店番</th><th>相談所名</th><th>ステータス</th><th>担当者数</th><th>加盟連盟</th><th>加盟番号</th><th>登録日</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): $href = url('admin/agency.php?id=' . (int)$r['id']); ?>
        <tr class="row-link" data-href="<?= h($href) ?>">
          <td><?= h($r['store_no']) ?></td>
          <td><a href="<?= h($href) ?>"><?= h($r['agency_name']) ?></a></td>
          <td><span class="badge badge-<?= h($r['approval_status']) ?>"><?= h(APPROVAL_LABELS[$r['approval_status']]) ?></span><?php if (!empty($r['withdrawn_at'])): ?> <span class="badge badge-withdrawn">退会済み</span><?php endif; ?></td>
          <td><?= (int)$r['counselor_count'] ?>人</td>
          <td><?= h($r['affiliation'] ?? '未入力') ?></td>
          <td><?= h($r['affiliation_number'] ?: '—') ?></td>
          <td><?= h(date('Y/n/j', strtotime($r['created_at']))) ?></td>
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
