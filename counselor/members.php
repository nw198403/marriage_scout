<?php
// counselor/members.php：カウンセラーが会員を探す（本気度の期限内の会員だけ）

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('counselor');
$pdo = db();

// 運営の承認が済んだ相談所のカウンセラーだけが、会員の情報を見られる
$stmt = $pdo->prepare('SELECT a.approval_status FROM counselors c JOIN agencies a ON a.id = c.agency_id WHERE c.user_id = ?');
$stmt->execute([$user['id']]);
if ($stmt->fetchColumn() !== 'approved') {
    flash('error', '運営の確認が済むと、会員を探せるようになります。相談所の情報をご確認ください。');
    redirect('counselor/');
}

$concernTags = $pdo->query("SELECT id, name FROM support_tags WHERE category = 'concern' ORDER BY sort_order")->fetchAll();

// 絞り込み条件（GET）
$fAge  = (string)($_GET['age'] ?? '');
$fPref = (string)($_GET['prefecture'] ?? '');
$fMar  = (string)($_GET['marital'] ?? '');
$fRdy  = (string)($_GET['readiness'] ?? '');
$fTag  = (int)($_GET['tag'] ?? 0);
$fInd  = (string)($_GET['industry'] ?? '');
$fEmp  = (string)($_GET['employment'] ?? '');
$fInc  = (int)($_GET['income'] ?? 0);
$page  = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$where = [SEARCHABLE_MEMBER_SQL];
$params = [];
if (isset(AGE_BANDS[$fAge])) {
    $where[] = MEMBER_AGE_SQL . ' BETWEEN ? AND ?';
    $params[] = AGE_BANDS[$fAge][1];
    $params[] = AGE_BANDS[$fAge][2];
} else {
    $fAge = '';
}
if (in_array($fPref, PREFECTURES, true)) {
    $where[] = 'm.prefecture = ?';
    $params[] = $fPref;
} else {
    $fPref = '';
}
if (array_key_exists($fMar, MARITAL_LABELS)) {
    $where[] = 'm.marital_history = ?';
    $params[] = $fMar;
} else {
    $fMar = '';
}
if (in_array($fRdy, ['now', 'within3m', 'within6m', 'info'], true)) {
    $where[] = 'm.readiness = ?';
    $params[] = $fRdy;
} else {
    $fRdy = '';
}
if (in_array($fInd, INDUSTRIES, true)) {
    $where[] = 'm.industry = ?';
    $params[] = $fInd;
} else {
    $fInd = '';
}
if (in_array($fEmp, EMPLOYMENT_TYPES, true)) {
    $where[] = 'm.employment_type = ?';
    $params[] = $fEmp;
} else {
    $fEmp = '';
}
if (isset(INCOME_BANDS[$fInc])) {
    $where[] = 'm.income_band >= ?';   // 年収帯は「以上」で絞り込む
    $params[] = $fInc;
} else {
    $fInc = 0;
}
if ($fTag > 0) {
    $where[] = 'EXISTS (SELECT 1 FROM member_support_tags t WHERE t.member_id = m.user_id AND t.tag_id = ?)';
    $params[] = $fTag;
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM members m WHERE $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

// 登録日時順（m.user_id は users.id をそのまま使っており、登録順に増えていくID）
$sql = "SELECT m.user_id, m.nickname, m.birth_year, m.birth_month, m.prefecture, m.marital_history, m.industry, m.employment_type, m.income_band, m.intro, m.readiness,
               s.status AS scout_status
        FROM members m
        LEFT JOIN scouts s ON s.member_id = m.user_id AND s.counselor_id = ?
        WHERE $whereSql
        ORDER BY m.user_id ASC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$user['id']], $params));
$members = $stmt->fetchAll();

// 表示する会員のタグをまとめて取得
$tagMap = [];
if ($members) {
    $ids = array_map('intval', array_column($members, 'user_id'));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT mt.member_id, t.name FROM member_support_tags mt JOIN support_tags t ON t.id = mt.tag_id
                           WHERE mt.member_id IN ($in) ORDER BY t.sort_order");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $r) {
        $tagMap[(int)$r['member_id']][] = $r['name'];
    }
}

function page_link(int $p): string
{
    $q = $_GET;
    $q['page'] = $p;
    return url('counselor/members.php') . '?' . http_build_query($q);
}

$journeyBar = render_journey_bar(counselor_journey_step($pdo, $user['id']), COUNSELOR_JOURNEY_STEPS);
render_header('会員を探す', 'counselor', '', $journeyBar);
?>
<div class="card">
  <h1>会員を探す</h1>

  <details class="filter-details" open>
    <summary class="filter-summary">検索条件</summary>
    <form method="get" action="<?= h(url('counselor/members.php')) ?>" class="filter-form">
    <div class="filter-grid">
      <label>年代
        <select name="age">
          <option value="">指定なし</option>
          <?php foreach (AGE_BANDS as $k => $b): ?>
            <option value="<?= h((string)$k) ?>" <?= $fAge === (string)$k ? 'selected' : '' ?>><?= h($b[0]) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>都道府県
        <select name="prefecture">
          <option value="">指定なし</option>
          <?php foreach (PREFECTURES as $p): ?>
            <option value="<?= h($p) ?>" <?= $fPref === $p ? 'selected' : '' ?>><?= h($p) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>婚姻歴
        <select name="marital">
          <option value="">指定なし</option>
          <?php foreach (MARITAL_LABELS as $k => $l): ?>
            <option value="<?= h($k) ?>" <?= $fMar === $k ? 'selected' : '' ?>><?= h($l) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>始める時期
        <select name="readiness">
          <option value="">指定なし</option>
          <?php foreach (['now', 'within3m', 'within6m', 'info'] as $k): ?>
            <option value="<?= h($k) ?>" <?= $fRdy === $k ? 'selected' : '' ?>><?= h(READINESS_LABELS[$k]) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>雇用形態
        <select name="employment">
          <option value="">指定なし</option>
          <?php foreach (EMPLOYMENT_TYPES as $e): ?>
            <option value="<?= h($e) ?>" <?= $fEmp === $e ? 'selected' : '' ?>><?= h($e) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>業種
        <select name="industry">
          <option value="">指定なし</option>
          <?php foreach (INDUSTRIES as $i): ?>
            <option value="<?= h($i) ?>" <?= $fInd === $i ? 'selected' : '' ?>><?= h($i) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>年収帯
        <select name="income">
          <option value="">指定なし</option>
          <?php foreach (INCOME_BANDS as $k => $l): ?>
            <option value="<?= $k ?>" <?= $fInc === $k ? 'selected' : '' ?>><?= h($l) ?>以上</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>不安なこと
        <select name="tag">
          <option value="">指定なし</option>
          <?php foreach ($concernTags as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $fTag === (int)$t['id'] ? 'selected' : '' ?>><?= h($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <button type="submit" class="btn">この条件で探す</button>
    <a href="<?= h(url('counselor/members.php')) ?>" class="filter-reset">条件をクリア</a>
    </form>
  </details>
</div>

<p class="muted result-count"><?= $total ?>人が見つかりました</p>

<?php if (!$members): ?>
  <div class="card"><p>条件に合う会員はいません。条件をゆるめてみてください。</p></div>
<?php endif; ?>

<?php if ($members): ?>
<div class="sheet-wrap">
  <table class="sheet">
    <thead>
      <tr><th>ニックネーム</th><th>年代</th><th>都道府県</th><th>婚姻歴</th><th>始める時期</th><th>雇用形態</th><th>業種</th><th>年収帯</th><th>不安なこと</th><th>詳細</th></tr>
    </thead>
    <tbody>
    <?php foreach ($members as $m): $href = url('counselor/member.php?id=' . (int)$m['user_id']); ?>
      <tr class="row-link" data-href="<?= h($href) ?>">
        <td>
          <?= h($m['nickname']) ?>
          <?php if ($m['scout_status']): ?><span class="badge badge-sent"><?= h(SCOUT_STATUS_LABELS[$m['scout_status']]) ?></span><?php endif; ?>
        </td>
        <td><?= calc_age((int)$m['birth_year'], (int)$m['birth_month']) ?>歳</td>
        <td><?= h($m['prefecture'] ?? '—') ?></td>
        <td><?= h(MARITAL_LABELS[$m['marital_history']] ?? '—') ?></td>
        <td><?= h(READINESS_LABELS[$m['readiness']] ?? '—') ?></td>
        <td><?= $m['employment_type'] ? h($m['employment_type']) : '—' ?></td>
        <td><?= $m['industry'] ? h($m['industry']) : '—' ?></td>
        <td><?= $m['income_band'] ? h(INCOME_BANDS[(int)$m['income_band']] ?? '') . '以上' : '—' ?></td>
        <td><?= !empty($tagMap[(int)$m['user_id']]) ? h(implode('、', $tagMap[(int)$m['user_id']])) : '—' ?></td>
        <td><a href="<?= h($href) ?>">詳細を見る</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($pages > 1): ?>
  <nav class="pager">
    <?php if ($page > 1): ?><a href="<?= h(page_link($page - 1)) ?>">‹ 前へ</a><?php endif; ?>
    <span><?= $page ?> / <?= $pages ?></span>
    <?php if ($page < $pages): ?><a href="<?= h(page_link($page + 1)) ?>">次へ ›</a><?php endif; ?>
  </nav>
<?php endif; ?>
<script>
document.querySelectorAll('tr.row-link').forEach(function (tr) {
  tr.addEventListener('click', function (e) {
    if (e.target.closest('a')) { return; }
    location.href = tr.getAttribute('data-href');
  });
});
</script>
<?php render_footer(); ?>
