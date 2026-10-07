<?php
// member/scout.php：スカウトの詳細（カウンセラーのプロフィール全体）と、辞退

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('member');
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// 自分宛てで、承認済み・公開中のカウンセラーからのスカウトだけ開ける
$stmt = $pdo->prepare(
    "SELECT s.*, c.display_name, c.mbti_type, c.photo_path, a.agency_name, a.affiliation, c.prefecture, c.service_style, c.bio,
            c.blog_url, c.sns_url, c.marriage_count, c.experience_years, c.entry_fee, c.monthly_fee, c.success_fee
     FROM scouts s JOIN counselors c ON c.user_id = s.counselor_id JOIN agencies a ON a.id = c.agency_id
     WHERE s.id = ? AND s.member_id = ? AND a.approval_status = 'approved' AND c.is_active = 1"
);
$stmt->execute([$id, $user['id']]);
$s = $stmt->fetch();
if (!$s) {
    http_response_code(404);
    render_header('見つかりません', 'member');
    echo '<div class="card"><p>このスカウトは表示できません。</p><p><a href="' . h(url('member/scouts.php')) . '">スカウト一覧に戻る</a></p></div>';
    render_footer();
    exit;
}

$stmt = $pdo->prepare('SELECT t.name, t.category FROM counselor_support_tags ct JOIN support_tags t ON t.id = ct.tag_id
                       WHERE ct.counselor_id = ? ORDER BY t.category, t.sort_order');
$stmt->execute([$s['counselor_id']]);
$tags = ['concern' => [], 'age' => [], 'marital' => [], 'service' => []];
foreach ($stmt->fetchAll() as $t) {
    $tags[$t['category']][] = $t['name'];
}

$ds = scout_display_status($s);
$canRespond = $s['status'] === 'sent' && $ds !== '期限切れ';
$badgeClass = $s['status'] === 'booked' ? 'badge-now' : ($s['status'] === 'proposed' ? 'badge-pending' : ($canRespond ? 'badge-sent' : ''));
$bookError = '';

$stmt = $pdo->prepare('SELECT * FROM scout_slots WHERE scout_id = ? ORDER BY starts_at');
$stmt->execute([$id]);
$slots = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM contracts WHERE scout_id = ? AND member_id = ?');
$stmt->execute([$id, $user['id']]);
$contract = $stmt->fetch();
$contractErrors = [];

$review = null;
if ($contract && $contract['status'] === 'confirmed') {
    $stmt = $pdo->prepare('SELECT * FROM reviews WHERE contract_id = ?');
    $stmt->execute([$contract['id']]);
    $review = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'propose' && $canRespond) {
        $slotId = (int)($_POST['slot_id'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));
        $chosenTime = (string)($_POST['chosen_time'] ?? '');
        if (mb_strlen($note) > SLOT_NOTE_MAX) {
            $bookError = 'メモは' . SLOT_NOTE_MAX . '文字以内で入力してください。';
        } else {
            $st2 = $pdo->prepare('SELECT * FROM scout_slots WHERE id = ? AND scout_id = ? AND ends_at > NOW()');
            $st2->execute([$slotId, $id]);
            $slot = $st2->fetch();
            if (!$slot) {
                $bookError = 'この日時は選べません。別の日時を選んでください。';
            } elseif (!in_array($chosenTime, slot_time_choice_options($slot), true)) {
                $bookError = '開始時刻を選んでください。';
            } else {
                $chosenStart = date('Y-m-d', strtotime($slot['starts_at'])) . ' ' . $chosenTime . ':00';
                $pdo->prepare('UPDATE scout_slots SET member_note = ?, chosen_start_at = ? WHERE id = ? AND scout_id = ?')
                    ->execute([$note === '' ? null : $note, $chosenStart, $slotId, $id]);
                $pdo->prepare("UPDATE scouts SET status = 'proposed', proposed_slot_id = ? WHERE id = ? AND member_id = ? AND status = 'sent'")
                    ->execute([$slotId, $id, $user['id']]);
                flash('ok', '希望する日時を伝えました。カウンセラーからの確定をお待ちください。');
                redirect('member/scout.php?id=' . $id);
            }
        }
    } elseif ($action === 'cancel_proposal' && $s['status'] === 'proposed') {
        $proposedId = (int)$s['proposed_slot_id'];
        $pdo->prepare("UPDATE scouts SET status = 'sent', proposed_slot_id = NULL WHERE id = ? AND member_id = ? AND status = 'proposed'")
            ->execute([$id, $user['id']]);
        if ($proposedId) {
            $pdo->prepare('UPDATE scout_slots SET member_note = NULL, chosen_start_at = NULL WHERE id = ? AND scout_id = ?')->execute([$proposedId, $id]);
        }
        flash('ok', '提案を取り消しました。');
        redirect('member/scout.php?id=' . $id);
    } elseif ($action === 'cancel_booking' && $s['status'] === 'booked') {
        $bookedId = (int)$s['booked_slot_id'];
        $pdo->prepare("UPDATE scouts SET status = 'sent', booked_slot_id = NULL WHERE id = ? AND member_id = ? AND status = 'booked'")->execute([$id, $user['id']]);
        if ($bookedId) {
            $pdo->prepare('UPDATE scout_slots SET member_note = NULL, chosen_start_at = NULL WHERE id = ? AND scout_id = ?')->execute([$bookedId, $id]);
        }
        flash('ok', '面談の予約を取り消しました。');
        redirect('member/scout.php?id=' . $id);
    }
    if ($action === 'report_contract' && $s['status'] === 'met' && !$contract) {
        // 契約の申告：運営が、カウンセラーと相談所にメールで確認する
        $d = DateTime::createFromFormat('Y-m-d', (string)($_POST['contract_date'] ?? ''));
        $metDay = $s['met_at'] ? date('Y-m-d', strtotime($s['met_at'])) : '2000-01-01';
        if (!$d || $d->format('Y-m-d') !== (string)$_POST['contract_date']) {
            $contractErrors[] = '契約日を正しく入力してください。';
        } elseif ($d->format('Y-m-d') > date('Y-m-d') || $d->format('Y-m-d') < $metDay) {
            $contractErrors[] = '契約日は、面談の日から今日までの間で入力してください。';
        }
        if (empty($_POST['agree'])) {
            $contractErrors[] = '「実際に契約しました」にチェックを入れてください。';
        }
        if (!$contractErrors) {
            try {
                $pdo->prepare('INSERT INTO contracts (scout_id, member_id, counselor_id, contract_date, entry_fee_snapshot, referral_fee)
                               VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$id, $user['id'], $s['counselor_id'], $d->format('Y-m-d'), (int)$s['entry_fee'],
                               calc_referral_fee()]);
                flash('ok', '契約の申告を受け付けました。運営が、カウンセラーと相談所に確認します。');
                redirect('member/scout.php?id=' . $id);
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                redirect('member/scout.php?id=' . $id);   // すでに申告済み
            }
        }
    } elseif ($action === 'cancel_report') {
        $pdo->prepare("DELETE FROM contracts WHERE scout_id = ? AND member_id = ? AND status = 'reported'")->execute([$id, $user['id']]);
        flash('ok', '契約の申告を取り消しました。');
        redirect('member/scout.php?id=' . $id);
    }
    if ($action === 'decline' && $canRespond) {
        $pdo->prepare("UPDATE scouts SET status = 'closed', closed_reason = 'member_declined' WHERE id = ? AND member_id = ? AND status = 'sent'")
            ->execute([$id, $user['id']]);
        flash('ok', 'このスカウトを辞退しました。');
        redirect('member/scouts.php');
    }
}

$ratingMap = counselor_rating_summary($pdo, [(int)$s['counselor_id']]);
$rating = $ratingMap[(int)$s['counselor_id']] ?? null;

$chatOk = chat_eligible($s);
$chatUnread = $chatOk ? unread_message_count_for_scout($pdo, $id, 'member') : 0;

$journeyBar = render_journey_bar(member_journey_step($pdo, $user['id']));
render_header($s['agency_name'], 'member', '', $journeyBar);
?>
<p><a href="<?= h(url('member/scouts.php')) ?>">‹ スカウト一覧に戻る</a></p>
<div class="card">
  <div class="counselor-detail-head">
    <?php if (!empty($s['photo_path'])): ?>
      <img src="<?= h(url('photo.php?cid=' . (int)$s['counselor_id'])) ?>" alt="" class="counselor-photo-lg">
    <?php else: ?>
      <span class="counselor-photo-lg counselor-photo-placeholder" aria-hidden="true"></span>
    <?php endif; ?>
    <div>
      <h1><?= h($s['agency_name']) ?></h1>
      <p><strong><?= h($s['display_name']) ?></strong> <span class="badge <?= $badgeClass ?>"><?= h($ds) ?></span></p>
      <p class="muted"><?= $rating ? star_html($rating['avg'], $rating['count']) : 'まだ評価はありません' ?></p>
    </div>
  </div>
  <table class="kv">
    <?php if ($s['mbti_type']): ?><tr><th>MBTI</th><td><?= h($s['mbti_type']) ?>（<?= h(MBTI_LABELS[$s['mbti_type']] ?? '') ?>・自己申告）</td></tr><?php endif; ?>
    <tr><th>加盟連盟</th><td><?= h($s['affiliation'] ?? '—') ?>（運営が加盟を確認済み）</td></tr>
    <tr><th>拠点</th><td><?= h($s['prefecture'] ?? '') ?></td></tr>
    <tr><th>対応エリア</th><td><?= h(format_areas(load_counselor_areas($pdo, [(int)$s['counselor_id']])[(int)$s['counselor_id']] ?? [], $s['service_style'])) ?></td></tr>
    <tr><th>面談の方法</th><td><?= h(SERVICE_STYLE_LABELS[$s['service_style']] ?? '') ?></td></tr>
    <tr><th>入会金</th><td><?= number_format((int)$s['entry_fee']) ?>円</td></tr>
    <tr><th>月会費</th><td><?= number_format((int)$s['monthly_fee']) ?>円</td></tr>
    <tr><th>成婚料</th><td><?= number_format((int)$s['success_fee']) ?>円</td></tr>
    <tr><th>経験年数</th><td><?= $s['experience_years'] !== null ? (int)$s['experience_years'] . '年' : '—' ?></td></tr>
    <tr><th>成婚数</th><td><?= $s['marriage_count'] !== null ? (int)$s['marriage_count'] . '組（自己申告）' : '—' ?></td></tr>
    <?php if ($s['blog_url']): ?><tr><th>ブログ</th><td><a href="<?= h($s['blog_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($s['blog_url']) ?></a></td></tr><?php endif; ?>
    <?php if ($s['sns_url']): ?><tr><th>SNS</th><td><a href="<?= h($s['sns_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($s['sns_url']) ?></a></td></tr><?php endif; ?>
  </table>
  <?php foreach (['concern' => '得意な不安・悩み', 'age' => '得意な年代', 'marital' => '得意な婚姻歴', 'service' => '提供できるサポート'] as $cat => $label): if ($tags[$cat]): ?>
    <p class="muted" style="margin-bottom:.2rem"><?= h($label) ?></p>
    <p class="tag-list" style="margin-top:0"><?php foreach ($tags[$cat] as $tn): ?><span class="tag"><?= h($tn) ?></span><?php endforeach; ?></p>
  <?php endif; endforeach; ?>
  <?php if ($s['bio']): ?><h2>自己紹介・サポート方針</h2><p><?= nl2br(h($s['bio'])) ?></p><?php endif; ?>
</div>

<?php if ($rating): ?>
<?php
$reviewCols = implode(', ', array_keys(REVIEW_LABELS));
$stmt = $pdo->prepare("SELECT $reviewCols, comment, created_at FROM reviews
                       WHERE counselor_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([(int)$s['counselor_id']]);
$recentReviews = $stmt->fetchAll();
?>
<div class="card">
  <h2>会員からの評価（<?= (int)$rating['count'] ?>件） <span class="review-disclaimer">評価は契約成立した会員による匿名の投稿です。</span></h2>
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
</div>
<?php endif; ?>

<div class="card">
  <h2><?= h($s['display_name']) ?>さんからのメッセージ</h2>
  <p class="muted"><?= h(date('Y年n月j日', strtotime($s['sent_at']))) ?> に届きました<?= $s['expires_at'] ? '（返答の期限：' . h(date('n月j日', strtotime($s['expires_at']))) . '）' : '' ?></p>
  <p><?= nl2br(h($s['message'])) ?></p>
  <?php if ($canRespond): ?>
    <div class="scout-actions" style="margin-top:1rem">
      <a href="#interview" class="btn">初回面談を設定</a>
      <form method="post" action="<?= h(url('member/scout.php')) ?>" onsubmit="return confirm('このスカウトを辞退しますか？');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" name="action" value="decline" class="btn btn-secondary">辞退する</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php if ($chatOk): ?>
<div class="card" id="chat">
  <h2>メッセージ</h2>
  <?php if ($chatUnread > 0): ?><p><span class="badge badge-important"><?= $chatUnread ?>件の新着メッセージ</span></p><?php endif; ?>
  <a class="btn btn-secondary" href="<?= h(url('member/chat.php?id=' . $id)) ?>">メッセージを見る</a>
</div>
<?php elseif ($s['status'] === 'sent'): ?>
<div class="card" id="chat">
  <h2>メッセージ</h2>
  <p class="muted">初回面談の候補を選ぶ（またはスカウトを受ける）と、メッセージのやり取りができるようになります。</p>
</div>
<?php endif; ?>

<?php if ($s['status'] === 'sent' || $s['status'] === 'proposed' || $s['status'] === 'booked'): ?>
<div class="card" id="interview">
  <h2>面談</h2>
  <?php if ($bookError): ?><div class="flash flash-error"><?= h($bookError) ?></div><?php endif; ?>
  <?php if ($s['status'] === 'booked'): foreach ($slots as $sl): if ((int)$sl['id'] === (int)$s['booked_slot_id']): ?>
    <p>面談を予約しました：<strong><?= h(fmt_chosen_time($sl)) ?></strong>（<?= h(slot_meeting_label($sl)) ?>）</p>
    <?php if ($sl['meeting_url']): ?><p class="muted">オンライン会議URL：<a href="<?= h($sl['meeting_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($sl['meeting_url']) ?></a></p><?php endif; ?>
    <?php if ($sl['member_note']): ?><p class="muted">伝えたメモ：<?= nl2br(h($sl['member_note'])) ?></p><?php endif; ?>
    <p><a class="btn btn-secondary btn-small" href="<?= h(url('member/ics.php?id=' . $id)) ?>">カレンダーに追加（.ics）</a></p>
    <form method="post" action="<?= h(url('member/scout.php')) ?>" onsubmit="return confirm('予約を取り消しますか？');">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <button type="submit" name="action" value="cancel_booking" class="btn btn-secondary">予約を取り消す</button>
    </form>
  <?php endif; endforeach; elseif ($s['status'] === 'proposed'): foreach ($slots as $sl): if ((int)$sl['id'] === (int)$s['proposed_slot_id']): ?>
    <p>希望を伝えました：<strong><?= h(fmt_chosen_time($sl)) ?></strong>（<?= h(slot_meeting_label($sl)) ?>）</p>
    <?php if ($sl['meeting_url']): ?><p class="muted">オンライン会議URL：<a href="<?= h($sl['meeting_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($sl['meeting_url']) ?></a></p><?php endif; ?>
    <?php if ($sl['member_note']): ?><p class="muted">伝えたメモ：<?= nl2br(h($sl['member_note'])) ?></p><?php endif; ?>
    <p class="muted">カウンセラーからの確定をお待ちください。</p>
    <form method="post" action="<?= h(url('member/scout.php')) ?>" onsubmit="return confirm('提案を取り消しますか？');">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <button type="submit" name="action" value="cancel_proposal" class="btn btn-secondary">提案を取り消す</button>
    </form>
  <?php endif; endforeach; elseif ($canRespond): ?>
    <?php $future = array_values(array_filter($slots, fn($sl) => strtotime($sl['ends_at']) > time())); ?>
    <?php if (!$future): ?>
      <p class="muted">面談の候補日時は、カウンセラーが出すとここに表示されます。</p>
    <?php else: ?>
      <p class="muted">希望の日時を選択し、開始時間を設定して「この候補にする」を押してください。</p>
      <?php foreach ($future as $sl): ?>
        <form method="post" action="<?= h(url('member/scout.php')) ?>" class="slot-row slot-row-wide">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="slot_id" value="<?= (int)$sl['id'] ?>">
          <input type="hidden" name="note" class="slot-note-hidden">
          <div>
            <span><?= h(fmt_slot_range($sl)) ?>（目安<?= (int)$sl['duration_min'] ?>分・<?= h(slot_meeting_label($sl)) ?>）</span>
            <?php if ($sl['counselor_note']): ?><p class="muted"><?= h($sl['counselor_note']) ?></p><?php endif; ?>
            <?php if ($sl['meeting_url']): ?><p class="muted">オンライン会議URL：<?= h($sl['meeting_url']) ?></p><?php endif; ?>
            <div class="slot-time-choice">
              <label>開始時刻
                <select name="chosen_time" class="chosen-time-select" data-duration="<?= (int)$sl['duration_min'] ?>">
                  <?php foreach (slot_time_choice_options($sl) as $t): ?><option value="<?= h($t) ?>"><?= h($t) ?></option><?php endforeach; ?>
                </select>
              </label>
              <span>〜</span>
              <span class="slot-end-preview"></span>
            </div>
            <button type="submit" name="action" value="propose" class="btn btn-small">この候補にする</button>
          </div>
        </form>
      <?php endforeach; ?>
      <div class="form-row">
        <label for="member-note-shared">その他（任意）
          <input type="text" id="member-note-shared" maxlength="<?= SLOT_NOTE_MAX ?>" placeholder="面談日時の要望など">
        </label>
      </div>
      <script>
      (function () {
        function addMinutes(hhmm, mins) {
          var parts = hhmm.split(':');
          var total = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10) + mins;
          total = ((total % (24 * 60)) + 24 * 60) % (24 * 60);
          var h = Math.floor(total / 60);
          var m = total % 60;
          return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
        }
        document.querySelectorAll('.chosen-time-select').forEach(function (sel) {
          var dur = parseInt(sel.getAttribute('data-duration'), 10) || 0;
          var preview = sel.closest('.slot-time-choice').querySelector('.slot-end-preview');
          function sync() {
            if (preview) { preview.textContent = addMinutes(sel.value, dur); }
          }
          sel.addEventListener('change', sync);
          sync();
        });
        var sharedNote = document.getElementById('member-note-shared');
        document.querySelectorAll('form.slot-row-wide').forEach(function (form) {
          form.addEventListener('submit', function () {
            var hidden = form.querySelector('.slot-note-hidden');
            if (hidden && sharedNote) { hidden.value = sharedNote.value; }
          });
        });
      })();
      </script>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php if ($s['status'] === 'met' || $s['status'] === 'contracted'): ?>
<div class="card" id="contract">
  <h2>契約</h2>
  <?php foreach ($contractErrors as $e): ?><div class="flash flash-error"><?= h($e) ?></div><?php endforeach; ?>
  <?php if ($contract): ?>
    <p><span class="badge <?= $contract['status'] === 'confirmed' ? 'badge-approved' : ($contract['status'] === 'rejected' ? 'badge-rejected' : 'badge-pending') ?>"><?= h(CONTRACT_STATUS_LABELS[$contract['status']]) ?></span>
       <span class="muted">契約日：<?= h(date('Y年n月j日', strtotime($contract['contract_date']))) ?></span></p>
    <?php if ($contract['status'] === 'reported'): ?>
      <p class="muted">運営が、カウンセラーと相談所に契約の確認メールを送ります。しばらくお待ちください。</p>
      <form method="post" action="<?= h(url('member/scout.php')) ?>" onsubmit="return confirm('申告を取り消しますか？');">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" name="action" value="cancel_report" class="btn btn-secondary">申告を取り消す</button>
      </form>
    <?php elseif ($contract['status'] === 'confirming'): ?>
      <p class="muted">運営が、カウンセラーと相談所に確認しています。確認が済むと、契約成立になります。</p>
    <?php elseif ($contract['status'] === 'confirmed'): ?>
      <p>契約が確認できました。これからの婚活を、応援しています。</p>
      <?php if ($review): ?>
        <p class="muted">レビュー投稿済み：<?= star_html(review_avg($review)) ?></p>
      <?php else: ?>
        <p><a class="btn btn-secondary" href="<?= h(url('member/review.php?contract_id=' . (int)$contract['id'])) ?>">レビューを書く</a></p>
      <?php endif; ?>
    <?php else: ?>
      <p class="muted">契約を確認できませんでした。心当たりがある場合は、運営にご連絡ください。</p>
    <?php endif; ?>
  <?php else: ?>
    <p class="muted">面談のあと、このカウンセラー（相談所）と契約したときは、ここから申告してください。運営が、カウンセラーと相談所にメールで確認します。</p>
    <form method="post" action="<?= h(url('member/scout.php')) ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <div class="form-row">
        <label for="contract_date">契約した日</label>
        <input type="date" id="contract_date" name="contract_date" value="<?= h(date('Y-m-d')) ?>" max="<?= h(date('Y-m-d')) ?>" required>
      </div>
      <div class="form-row">
        <label><input type="checkbox" name="agree" value="1"> 実際に、このカウンセラー（相談所）と契約しました</label>
      </div>
      <button type="submit" name="action" value="report_contract" class="btn">契約したと申告する</button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php render_footer(); ?>
