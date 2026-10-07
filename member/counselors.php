<?php
// member/counselors.php：カウンセラー一覧（評価・口コミを、スカウトの有無に関わらず会員全員が見られるページ）

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('member');
$pdo = db();

// 運営の承認済みで、公開中のカウンセラーを全員対象にする（スカウトを受けたかどうかは問わない）
$stmt = $pdo->query(
    "SELECT c.user_id AS counselor_id, c.display_name, c.photo_path, c.mbti_type, a.agency_name, a.affiliation, c.prefecture, c.service_style,
            c.marriage_count, c.experience_years, c.entry_fee, c.monthly_fee, c.success_fee
     FROM counselors c JOIN agencies a ON a.id = c.agency_id
     WHERE a.approval_status = 'approved' AND c.is_active = 1
     ORDER BY c.display_name"
);
$counselors = $stmt->fetchAll();

$areaMap = load_counselor_areas($pdo, array_column($counselors, 'counselor_id'));

$tagMap = [];
if ($counselors) {
    $ids = array_values(array_unique(array_map('intval', array_column($counselors, 'counselor_id'))));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT ct.counselor_id, t.name FROM counselor_support_tags ct JOIN support_tags t ON t.id = ct.tag_id
                         WHERE ct.counselor_id IN ($in) AND t.category = 'concern' ORDER BY t.sort_order");
    $st->execute($ids);
    foreach ($st->fetchAll() as $r) {
        $tagMap[(int)$r['counselor_id']][] = $r['name'];
    }
}

$ratingMap = counselor_rating_summary($pdo, array_column($counselors, 'counselor_id'));

// 評価が高い順（評価がないカウンセラーは後ろ）、同点なら件数が多い順、それでも同じなら名前順
usort($counselors, function ($a, $b) use ($ratingMap) {
    $ra = $ratingMap[(int)$a['counselor_id']] ?? null;
    $rb = $ratingMap[(int)$b['counselor_id']] ?? null;
    if (($ra !== null) !== ($rb !== null)) {
        return $ra !== null ? -1 : 1;
    }
    if ($ra && $rb && $ra['avg'] !== $rb['avg']) {
        return $rb['avg'] <=> $ra['avg'];
    }
    if ($ra && $rb && $ra['count'] !== $rb['count']) {
        return $rb['count'] <=> $ra['count'];
    }
    return strcmp((string)$a['display_name'], (string)$b['display_name']);
});

$journeyBar = render_journey_bar(member_journey_step($pdo, $user['id']));
render_header('カウンセラーの評価・口コミ', 'member', 'page-scouts-wide', $journeyBar);
?>
<div class="card">
  <h1>カウンセラーの評価・口コミ</h1>
  <p class="muted">スカウトの有無に関わらず、すべてのカウンセラー・相談所の評価・口コミを見ることができます。</p>

  <?php if (!$counselors): ?>
    <p class="muted">まだ掲載中のカウンセラーはいません。</p>
  <?php else: ?>
    <?php
      $fmtAffiliation = function (?string $aff): string {
          if (!$aff) { return '—'; }
          if (preg_match('/^(.*?)（(.*)）$/u', $aff, $m)) {
              return h($m[1]) . '<br><span class="cell-sub">' . h($m[2]) . '</span>';
          }
          return h($aff);
      };
      $compareCols = [
        '評価' => [
          'width' => '8rem', 'wrap' => true, 'sm' => false,
          'render' => function ($c) use ($ratingMap) {
              $r = $ratingMap[(int)$c['counselor_id']] ?? null;
              return $r ? star_html($r['avg'], $r['count']) : '評価なし';
          },
        ],
        'MBTI' => [
          'width' => '6rem', 'wrap' => true, 'sm' => false,
          'render' => function ($c) {
              if (!$c['mbti_type']) { return '—'; }
              return h($c['mbti_type']) . '<br><span class="cell-sub">' . h(MBTI_LABELS[$c['mbti_type']] ?? '') . '</span>';
          },
        ],
        '加盟連盟' => [
          'width' => '8.5rem', 'wrap' => true, 'sm' => false,
          'render' => fn($c) => $fmtAffiliation($c['affiliation'] ?? null),
        ],
        '拠点' => [
          'width' => '5.5rem', 'wrap' => true, 'sm' => false,
          'render' => fn($c) => h($c['prefecture'] ?? '—'),
        ],
        '対応エリア' => [
          'width' => '5.5rem', 'wrap' => true, 'sm' => false,
          'render' => function ($c) use ($areaMap) {
              return h(format_areas($areaMap[(int)$c['counselor_id']] ?? [], $c['service_style']));
          },
        ],
        '面談方法' => [
          'width' => '8.5rem', 'wrap' => true, 'sm' => true,
          'render' => function ($c) {
              $label = SERVICE_STYLE_LABELS[$c['service_style']] ?? '';
              return $label === '対面・オンライン両方' ? '対面・オンライン<br>両方' : h($label);
          },
        ],
        '得意分野' => [
          'width' => '8rem', 'wrap' => true, 'sm' => true,
          'render' => function ($c) use ($tagMap) {
              $tags = $tagMap[(int)$c['counselor_id']] ?? [];
              if (!$tags) { return '—'; }
              return '<ul class="cell-taglist">' . implode('', array_map(fn($t) => '<li>' . h($t) . '</li>', $tags)) . '</ul>';
          },
        ],
        '経験年数' => [
          'width' => '4.5rem', 'wrap' => false, 'sm' => false,
          'render' => fn($c) => $c['experience_years'] !== null ? (int)$c['experience_years'] . '年' : '—',
        ],
        '成婚数' => [
          'width' => '4.5rem', 'wrap' => false, 'sm' => false,
          'render' => fn($c) => $c['marriage_count'] !== null ? (int)$c['marriage_count'] . '組' : '—',
        ],
        '入会金' => [
          'width' => '6.5rem', 'wrap' => false, 'sm' => false,
          'render' => fn($c) => number_format((int)$c['entry_fee']) . '円',
        ],
        '月会費' => [
          'width' => '6.5rem', 'wrap' => false, 'sm' => false,
          'render' => fn($c) => number_format((int)$c['monthly_fee']) . '円',
        ],
        '成婚料' => [
          'width' => '6.5rem', 'wrap' => false, 'sm' => false,
          'render' => fn($c) => number_format((int)$c['success_fee']) . '円',
        ],
      ];
    ?>
    <div class="sheet-wrap">
      <table class="sheet compare-sheet">
        <colgroup>
          <col style="width:13rem">
          <?php foreach ($compareCols as $col): ?><col style="width:<?= h($col['width']) ?>"><?php endforeach; ?>
        </colgroup>
        <thead>
          <tr>
            <th>カウンセラー</th>
            <?php foreach (array_keys($compareCols) as $label): ?><th><?= h($label) ?></th><?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($counselors as $c): $cid = (int)$c['counselor_id']; $href = url('member/counselor.php?id=' . $cid); ?>
            <tr class="row-link" data-href="<?= h($href) ?>">
              <td class="sheet-name-cell">
                <div class="sheet-name-inner">
                  <?php if (!empty($c['photo_path'])): ?>
                    <img src="<?= h(url('photo.php?cid=' . $cid)) ?>" alt="" class="counselor-photo-sm">
                  <?php else: ?>
                    <span class="counselor-photo-sm counselor-photo-placeholder" aria-hidden="true"></span>
                  <?php endif; ?>
                  <span class="sheet-name-text">
                    <a href="<?= h($href) ?>"><span class="sheet-name-agency"><?= h($c['agency_name']) ?></span><br><span class="sheet-name-counselor"><?= h($c['display_name']) ?></span></a>
                  </span>
                </div>
              </td>
              <?php foreach ($compareCols as $label => $col): ?>
                <td class="<?= $col['wrap'] ? 'wrap-cell' : '' ?> <?= $col['sm'] ? 'cell-sm' : '' ?>"><?= $col['render']($c) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint">成婚数などの実績は、カウンセラー本人の申告です。表は横にスクロールできます。行をタップすると、評価・口コミの詳細が見られます。</p>
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
