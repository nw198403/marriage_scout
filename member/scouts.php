<?php
// member/scouts.php：届いたスカウトの一覧と、カウンセラーの比較表

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('member');
$pdo = db();

// 運営の承認済みで、公開中のカウンセラーからのスカウトだけ
$stmt = $pdo->prepare(
    "SELECT s.id, s.status, s.closed_reason, s.message, s.sent_at, s.expires_at,
            c.user_id AS counselor_id, c.display_name, c.photo_path, c.mbti_type, a.agency_name, a.affiliation, c.prefecture, c.service_style,
            c.marriage_count, c.experience_years, c.entry_fee, c.monthly_fee, c.success_fee
     FROM scouts s JOIN counselors c ON c.user_id = s.counselor_id JOIN agencies a ON a.id = c.agency_id
     WHERE s.member_id = ? AND a.approval_status = 'approved' AND c.is_active = 1
     ORDER BY (s.status = 'closed'), s.sent_at DESC"
);
$stmt->execute([$user['id']]);
$scouts = $stmt->fetchAll();

// カウンセラーの対応エリア
$areaMap = load_counselor_areas($pdo, array_column($scouts, 'counselor_id'));

// カウンセラーの得意分野
$tagMap = [];
if ($scouts) {
    $ids = array_values(array_unique(array_map('intval', array_column($scouts, 'counselor_id'))));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT ct.counselor_id, t.name FROM counselor_support_tags ct JOIN support_tags t ON t.id = ct.tag_id
                         WHERE ct.counselor_id IN ($in) AND t.category = 'concern' ORDER BY t.sort_order");
    $st->execute($ids);
    foreach ($st->fetchAll() as $r) {
        $tagMap[(int)$r['counselor_id']][] = $r['name'];
    }
}

$ratingMap = counselor_rating_summary($pdo, array_column($scouts, 'counselor_id'));

// スカウトごとの未読メッセージ件数（カウンセラーからの分）
$unreadMap = [];
if ($scouts) {
    $ids = array_column($scouts, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT scout_id, COUNT(*) FROM scout_messages WHERE scout_id IN ($in) AND sender_role = 'counselor' AND read_at IS NULL GROUP BY scout_id");
    $st->execute($ids);
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$sid, $cnt]) {
        $unreadMap[(int)$sid] = (int)$cnt;
    }
}

$active = array_values(array_filter($scouts, fn($s) => $s['status'] !== 'closed' && scout_display_status($s) !== '期限切れ'));

$journeyBar = render_journey_bar(member_journey_step($pdo, $user['id']));
render_header('スカウト一覧', 'member', 'page-scouts-wide', $journeyBar);
?>
<div class="card">
  <h1>スカウト一覧</h1>
  <p class="muted">得意分野・実績・料金を見比べて、話を聞きたいカウンセラーをお選びください。</p>

  <?php if (!$scouts): ?>
    <p class="muted">まだスカウトは届いていません。プロフィールを充実させて、婚活を始める時期の有効期限内にしておくと、カウンセラーの目に留まりやすくなります。</p>
  <?php else: ?>
    <?php
      // 各スカウトの表示用状態（期限切れ・辞退・終了は薄く表示）をあらかじめ計算しておく
      $dimMap = [];
      $dsMap = [];
      foreach ($scouts as $s) {
          $ds = scout_display_status($s);
          $dsMap[(int)$s['id']] = $ds;
          $dimMap[(int)$s['id']] = in_array($ds, ['期限切れ', '辞退しました', '終了'], true);
      }
    ?>
    <?php
      // 加盟連盟の表記「IBJ（日本結婚相談所連盟）」を、アルファベット（1行目）と正式名称（2行目・小さめ）に分ける
      $fmtAffiliation = function (?string $aff): string {
          if (!$aff) { return '—'; }
          if (preg_match('/^(.*?)（(.*)）$/u', $aff, $m)) {
              return h($m[1]) . '<br><span class="cell-sub">' . h($m[2]) . '</span>';
          }
          return h($aff);
      };
      // Excel風の表を90度回転：カウンセラーを列ではなく行にする（何人いても横にはみ出さず、縦に増えるだけにする）。
      // 各列は width（列幅）・wrap（折り返すか）・sm（フォントサイズを少し下げるか）・render（値の作り方）を持つ
      $compareCols = [
        '評価' => [
          'width' => '8rem', 'wrap' => true, 'sm' => false,
          'render' => function ($s) use ($ratingMap) {
              $r = $ratingMap[(int)$s['counselor_id']] ?? null;
              return $r ? star_html($r['avg'], $r['count']) : '評価なし';
          },
        ],
        'MBTI' => [
          'width' => '6rem', 'wrap' => true, 'sm' => false,
          'render' => function ($s) {
              if (!$s['mbti_type']) { return '—'; }
              return h($s['mbti_type']) . '<br><span class="cell-sub">' . h(MBTI_LABELS[$s['mbti_type']] ?? '') . '</span>';
          },
        ],
        '加盟連盟' => [
          'width' => '8.5rem', 'wrap' => true, 'sm' => false,
          'render' => fn($s) => $fmtAffiliation($s['affiliation'] ?? null),
        ],
        '拠点' => [
          'width' => '5.5rem', 'wrap' => true, 'sm' => false,
          'render' => fn($s) => h($s['prefecture'] ?? '—'),
        ],
        '対応エリア' => [
          'width' => '5.5rem', 'wrap' => true, 'sm' => false,
          'render' => function ($s) use ($areaMap) {
              return h(format_areas($areaMap[(int)$s['counselor_id']] ?? [], $s['service_style']));
          },
        ],
        '面談方法' => [
          'width' => '8.5rem', 'wrap' => true, 'sm' => true,
          'render' => function ($s) {
              $label = SERVICE_STYLE_LABELS[$s['service_style']] ?? '';
              return $label === '対面・オンライン両方' ? '対面・オンライン<br>両方' : h($label);
          },
        ],
        '得意分野' => [
          'width' => '8rem', 'wrap' => true, 'sm' => true,
          'render' => function ($s) use ($tagMap) {
              $tags = $tagMap[(int)$s['counselor_id']] ?? [];
              if (!$tags) { return '—'; }
              return '<ul class="cell-taglist">' . implode('', array_map(fn($t) => '<li>' . h($t) . '</li>', $tags)) . '</ul>';
          },
        ],
        '経験年数' => [
          'width' => '4.5rem', 'wrap' => false, 'sm' => false,
          'render' => fn($s) => $s['experience_years'] !== null ? (int)$s['experience_years'] . '年' : '—',
        ],
        '成婚数' => [
          'width' => '4.5rem', 'wrap' => false, 'sm' => false,
          'render' => fn($s) => $s['marriage_count'] !== null ? (int)$s['marriage_count'] . '組' : '—',
        ],
        '入会金' => [
          'width' => '6.5rem', 'wrap' => false, 'sm' => false,
          'render' => fn($s) => number_format((int)$s['entry_fee']) . '円',
        ],
        '月会費' => [
          'width' => '6.5rem', 'wrap' => false, 'sm' => false,
          'render' => fn($s) => number_format((int)$s['monthly_fee']) . '円',
        ],
        '成婚料' => [
          'width' => '6.5rem', 'wrap' => false, 'sm' => false,
          'render' => fn($s) => number_format((int)$s['success_fee']) . '円',
        ],
        'メッセージ' => [
          'width' => '20rem', 'wrap' => true, 'sm' => true,
          'render' => fn($s) => h(mb_strimwidth($s['message'], 0, 100, '…')),
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
          <?php foreach ($scouts as $s): $sid = (int)$s['id']; $dim = $dimMap[$sid]; $ds = $dsMap[$sid]; ?>
            <tr class="<?= $dim ? 'is-dim' : '' ?>">
              <td class="sheet-name-cell">
                <div class="sheet-name-inner">
                  <?php if (!empty($s['photo_path'])): ?>
                    <img src="<?= h(url('photo.php?cid=' . (int)$s['counselor_id'])) ?>" alt="" class="counselor-photo-sm">
                  <?php else: ?>
                    <span class="counselor-photo-sm counselor-photo-placeholder" aria-hidden="true"></span>
                  <?php endif; ?>
                  <span class="sheet-name-text">
                    <a href="<?= h(url('member/scout.php?id=' . $sid)) ?>"><span class="sheet-name-agency"><?= h($s['agency_name']) ?></span><br><span class="sheet-name-counselor"><?= h($s['display_name']) ?></span></a><br>
                    <span class="badge <?= $dim ? '' : ($s['status'] === 'booked' ? 'badge-now' : ($s['status'] === 'proposed' ? 'badge-pending' : 'badge-sent')) ?>"><?= h($ds) ?></span>
                    <?php if (!empty($unreadMap[$sid])): ?><span class="badge badge-important">新着<?= (int)$unreadMap[$sid] ?></span><?php endif; ?>
                  </span>
                </div>
              </td>
              <?php foreach ($compareCols as $label => $col): ?>
                <td class="<?= $col['wrap'] ? 'wrap-cell' : '' ?> <?= $col['sm'] ? 'cell-sm' : '' ?>"><?= $col['render']($s) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint">成婚数などの実績は、カウンセラー本人の申告です。表は横にスクロールできます。</p>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
