<?php
// counselor/scout.php：送ったスカウトの詳細。面談の候補日時を出す／会員の提案を確定・差し戻す／面談を実施済みにする

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';
require_once __DIR__ . '/../includes/mail.php';

$user = require_login('counselor');
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$load = function () use ($pdo, $id, $user) {
    $stmt = $pdo->prepare('SELECT s.*, m.nickname FROM scouts s JOIN members m ON m.user_id = s.member_id WHERE s.id = ? AND s.counselor_id = ?');
    $stmt->execute([$id, $user['id']]);
    return $stmt->fetch();
};
$s = $load();
if (!$s) {
    http_response_code(404);
    exit('スカウトが見つかりません。');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $ds = scout_display_status($s);

    if ($action === 'save_slots') {
        if ($s['status'] !== 'sent' || $ds === '期限切れ') {
            $errors[] = 'このスカウトでは、候補日時を追加・編集できません。';
        } else {
            $sharedNote = trim((string)($_POST['note'] ?? ''));
            $sharedLocation = trim((string)($_POST['location'] ?? ''));   // 場所・メモは候補ごとではなく、まとめて1つだけ入力する（既存の候補にも適用される）
            $needsLocation = false;

            // 既存の候補（編集・削除）
            $existingRows = $_POST['existing'] ?? [];
            $toUpdate = [];
            $toDelete = [];
            foreach ($existingRows as $slotId => $row) {
                $slotId = (int)$slotId;
                if (!empty($row['delete'])) {
                    $toDelete[] = $slotId;
                    continue;
                }
                $dateStr = trim((string)($row['date'] ?? ''));
                $timeFrom = (string)($row['time_from'] ?? '');
                $timeTo = (string)($row['time_to'] ?? '');
                $dur = (int)($row['duration_min'] ?? 0);
                $meetingType = (string)($row['meeting_type'] ?? 'online');
                $dtFrom = DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $timeFrom);
                $dtTo = DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $timeTo);

                if (!$dtFrom || $dtFrom->format('Y-m-d') !== $dateStr || $dtFrom->format('H:i') !== $timeFrom) {
                    $errors[] = '候補日時を正しく入力してください。';
                } elseif (!$dtTo || $dtTo->format('H:i') !== $timeTo) {
                    $errors[] = '候補日時を正しく入力してください。';
                } elseif ($dtTo->getTimestamp() <= $dtFrom->getTimestamp()) {
                    $errors[] = '終了時刻は、開始時刻より後にしてください。';
                } elseif (!isset(SLOT_DURATIONS[$dur])) {
                    $errors[] = '面談の長さを選んでください。';
                } elseif (!isset(MEETING_TYPE_LABELS[$meetingType])) {
                    $errors[] = '面談の方法を選んでください。';
                } else {
                    if ($meetingType === 'offline') {
                        $needsLocation = true;
                    }
                    $toUpdate[] = [$slotId, $dtFrom, $dtTo, $dur, $meetingType];
                }
            }

            // 新しく追加する候補
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM scout_slots WHERE scout_id = ?');
            $stmt->execute([$id]);
            $existingCount = (int)$stmt->fetchColumn();

            $rows = $_POST['slot'] ?? [];
            $toInsert = [];
            foreach ($rows as $i => $row) {
                $rowNum = (int)$i + 1;
                $dateStr = trim((string)($row['date'] ?? ''));
                if ($dateStr === '') {
                    continue;   // 空行はスキップ（すべて埋める必要はない）
                }
                $timeFrom = (string)($row['time_from'] ?? '');
                $timeTo = (string)($row['time_to'] ?? '');
                $dur = (int)($row['duration_min'] ?? 0);
                $meetingType = (string)($row['meeting_type'] ?? 'online');
                $dtFrom = DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $timeFrom);
                $dtTo = DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $timeTo);

                if (!$dtFrom || $dtFrom->format('Y-m-d') !== $dateStr || $dtFrom->format('H:i') !== $timeFrom) {
                    $errors[] = "候補{$rowNum}：日時を正しく入力してください。";
                } elseif (!$dtTo || $dtTo->format('H:i') !== $timeTo) {
                    $errors[] = "候補{$rowNum}：日時を正しく入力してください。";
                } elseif ($dtTo->getTimestamp() <= $dtFrom->getTimestamp()) {
                    $errors[] = "候補{$rowNum}：終了時刻は、開始時刻より後にしてください。";
                } elseif ($dtFrom->getTimestamp() < time() + 3600) {
                    $errors[] = "候補{$rowNum}：候補日時は、いまから1時間以降で入力してください。";
                } elseif (!isset(SLOT_DURATIONS[$dur])) {
                    $errors[] = "候補{$rowNum}：面談の長さを選んでください。";
                } elseif (!isset(MEETING_TYPE_LABELS[$meetingType])) {
                    $errors[] = "候補{$rowNum}：面談の方法を選んでください。";
                } else {
                    if ($meetingType === 'offline') {
                        $needsLocation = true;
                    }
                    $toInsert[] = [$dtFrom, $dtTo, $dur, $meetingType];
                }
            }

            if (!$errors && $needsLocation && $sharedLocation === '') {
                $errors[] = '対面の候補があります。場所を入力してください。';
            }
            if (!$errors && $sharedLocation !== '' && mb_strlen($sharedLocation) > SLOT_LOCATION_MAX) {
                $errors[] = '場所は' . SLOT_LOCATION_MAX . '文字以内で入力してください。';
            }
            if (!$errors && $sharedNote !== '' && mb_strlen($sharedNote) > SLOT_NOTE_MAX) {
                $errors[] = 'メモは' . SLOT_NOTE_MAX . '文字以内で入力してください。';
            }
            if (!$errors && !$toUpdate && !$toInsert && !$toDelete) {
                $errors[] = '候補日時を、少なくとも1つ入力してください。';
            }
            if (!$errors && ($existingCount - count($toDelete) + count($toInsert)) > SLOT_MAX_PER_SCOUT) {
                $errors[] = '候補日時は' . SLOT_MAX_PER_SCOUT . 'つまでです。';
            }

            if (!$errors) {
                if ($toDelete) {
                    $del = $pdo->prepare('DELETE FROM scout_slots WHERE id = ? AND scout_id = ?');
                    foreach ($toDelete as $sid) {
                        $del->execute([$sid, $id]);
                    }
                }
                if ($toUpdate) {
                    $upd = $pdo->prepare('UPDATE scout_slots SET starts_at = ?, ends_at = ?, duration_min = ?, meeting_type = ?, location = ?, counselor_note = ? WHERE id = ? AND scout_id = ?');
                    foreach ($toUpdate as [$sid, $dtFrom, $dtTo, $dur, $meetingType]) {
                        $rowLocation = ($meetingType !== 'online' && $sharedLocation !== '') ? $sharedLocation : null;
                        $upd->execute([$dtFrom->format('Y-m-d H:i:00'), $dtTo->format('Y-m-d H:i:00'), $dur, $meetingType, $rowLocation, $sharedNote === '' ? null : $sharedNote, $sid, $id]);
                    }
                }
                if ($toInsert) {
                    $ins = $pdo->prepare('INSERT INTO scout_slots (scout_id, starts_at, ends_at, duration_min, meeting_type, location, counselor_note) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    foreach ($toInsert as [$dtFrom, $dtTo, $dur, $meetingType]) {
                        $rowLocation = ($meetingType !== 'online' && $sharedLocation !== '') ? $sharedLocation : null;
                        $ins->execute([$id, $dtFrom->format('Y-m-d H:i:00'), $dtTo->format('Y-m-d H:i:00'), $dur, $meetingType, $rowLocation, $sharedNote === '' ? null : $sharedNote]);
                    }
                }

                // 新しい候補を追加したときだけ、会員にメールで知らせる（DEV_MODEでは送らず、内容確認用のURLだけ表示）
                $notifyUrl = null;
                if ($toInsert) {
                    $mstmt = $pdo->prepare('SELECT u.email FROM users u JOIN members m ON m.user_id = u.id WHERE m.user_id = ?');
                    $mstmt->execute([(int)$s['member_id']]);
                    $memberEmail = $mstmt->fetchColumn();
                    $cstmt = $pdo->prepare('SELECT c.display_name, a.agency_name FROM counselors c JOIN agencies a ON a.id = c.agency_id WHERE c.user_id = ?');
                    $cstmt->execute([$user['id']]);
                    $ca = $cstmt->fetch();
                    if ($memberEmail && $ca) {
                        $notifyUrl = send_interview_proposed_mail($memberEmail, [
                            'scout_id' => $id,
                            'agency_name' => $ca['agency_name'],
                            'display_name' => $ca['display_name'],
                            'count' => count($toInsert),
                            'note' => $sharedNote,
                        ]);
                    }
                }

                if ($toInsert && DEV_MODE) {
                    flash('ok', '候補日時を更新しました。（開発中のため、新しい候補について会員へのメールは送らず、確認用のURLだけ表示します）' . ($notifyUrl ?? ''));
                } elseif ($toInsert) {
                    flash('ok', '候補日時を更新しました。新しい候補について会員にメールでお知らせしました。');
                } else {
                    flash('ok', '候補日時を更新しました。');
                }
                redirect('counselor/scout.php?id=' . $id);
            }
        }
    } elseif ($action === 'confirm_slot') {
        if ($s['status'] === 'proposed' && $s['proposed_slot_id']) {
            try {
                $pdo->beginTransaction();
                // カウンセラーの行をロックして、面談予約の数を数える（同時に複数確定しても上限を超えない）
                $st = $pdo->prepare('SELECT user_id FROM counselors WHERE user_id = ? FOR UPDATE');
                $st->execute([$user['id']]);
                $st = $pdo->prepare("SELECT COUNT(*) FROM scouts WHERE counselor_id = ? AND status = 'booked'");
                $st->execute([$user['id']]);
                if ((int)$st->fetchColumn() >= INTERVIEW_LIMIT_PER_COUNSELOR) {
                    $pdo->rollBack();
                    $errors[] = 'いま面談の予約がいっぱいです。既存の面談を実施済みにしてから確定してください。';
                } else {
                    $bookedSlotId = (int)$s['proposed_slot_id'];
                    $pdo->prepare("UPDATE scouts SET status = 'booked', booked_slot_id = proposed_slot_id, proposed_slot_id = NULL WHERE id = ? AND counselor_id = ? AND status = 'proposed'")
                        ->execute([$id, $user['id']]);
                    $pdo->commit();

                    // 会員に、面談の日時が確定したことをメールで知らせる（DEV_MODEでは送らず、内容確認用のURLだけ表示）
                    $notifyUrl = null;
                    $mstmt = $pdo->prepare('SELECT u.email FROM users u JOIN members m ON m.user_id = u.id WHERE m.user_id = ?');
                    $mstmt->execute([(int)$s['member_id']]);
                    $memberEmail = $mstmt->fetchColumn();
                    $cstmt = $pdo->prepare('SELECT c.display_name, a.agency_name FROM counselors c JOIN agencies a ON a.id = c.agency_id WHERE c.user_id = ?');
                    $cstmt->execute([$user['id']]);
                    $ca = $cstmt->fetch();
                    $sstmt = $pdo->prepare('SELECT * FROM scout_slots WHERE id = ?');
                    $sstmt->execute([$bookedSlotId]);
                    $bookedSlot = $sstmt->fetch();
                    if ($memberEmail && $ca && $bookedSlot) {
                        $notifyUrl = send_interview_booked_mail($memberEmail, [
                            'scout_id' => $id,
                            'agency_name' => $ca['agency_name'],
                            'display_name' => $ca['display_name'],
                            'when' => fmt_chosen_time($bookedSlot),
                            'meeting_label' => slot_meeting_label($bookedSlot),
                            'meeting_url' => $bookedSlot['meeting_url'],
                        ]);
                    }

                    if (DEV_MODE) {
                        flash('ok', '面談の日時を確定しました。（開発中のため、会員へのメールは送らず、確認用のURLだけ表示します）' . ($notifyUrl ?? ''));
                    } else {
                        flash('ok', '面談の日時を確定しました。会員にメールでお知らせしました。');
                    }
                    redirect('counselor/scout.php?id=' . $id);
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        } else {
            redirect('counselor/scout.php?id=' . $id);
        }
    } elseif ($action === 'decline_proposal') {
        if ($s['status'] === 'proposed') {
            $proposedId = (int)$s['proposed_slot_id'];
            $pdo->prepare("UPDATE scouts SET status = 'sent', proposed_slot_id = NULL WHERE id = ? AND counselor_id = ? AND status = 'proposed'")
                ->execute([$id, $user['id']]);
            if ($proposedId) {
                $pdo->prepare('UPDATE scout_slots SET member_note = NULL, chosen_start_at = NULL WHERE id = ? AND scout_id = ?')->execute([$proposedId, $id]);
            }
            flash('ok', '会員に、候補を選び直してもらうようにしました。');
        }
        redirect('counselor/scout.php?id=' . $id);
    } elseif ($action === 'mark_met') {
        if ($s['status'] === 'booked') {
            $pdo->prepare("UPDATE scouts SET status = 'met', met_at = NOW() WHERE id = ? AND counselor_id = ? AND status = 'booked'")->execute([$id, $user['id']]);
            flash('ok', '面談を実施済みにしました。');
        }
        redirect('counselor/scout.php?id=' . $id);
    }
}

$stmt = $pdo->prepare('SELECT * FROM scout_slots WHERE scout_id = ? ORDER BY starts_at');
$stmt->execute([$id]);
$slots = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT * FROM contracts WHERE scout_id = ? AND counselor_id = ?');
$stmt->execute([$id, $user['id']]);
$contract = $stmt->fetch();
$ds = scout_display_status($s);
$minDate = date('Y-m-d');   // 候補日の下限（今日）。時刻側は送信時にサーバーで「1時間以降」をチェックする
$badgeClass = $s['status'] === 'booked' ? 'badge-now' : ($s['status'] === 'proposed' ? 'badge-pending' : 'badge-sent');

$chatOk = chat_eligible($s);
$chatUnread = $chatOk ? unread_message_count_for_scout($pdo, $id, 'counselor') : 0;

$journeyBar = render_journey_bar(counselor_journey_step($pdo, $user['id']), COUNSELOR_JOURNEY_STEPS);
render_header($s['nickname'] . ' さんへのスカウト', 'counselor', '', $journeyBar);
?>
<p><a href="<?= h(url('counselor/scouts.php')) ?>">‹ 送ったスカウトに戻る</a></p>
<div class="card">
  <h1><?= h($s['nickname']) ?> さん</h1>
  <p><span class="badge <?= $badgeClass ?>"><?= h($ds) ?></span>
     <span class="muted"><?= h(date('Y年n月j日', strtotime($s['sent_at']))) ?> 送信<?= $s['expires_at'] ? '（返答の期限：' . h(date('n月j日', strtotime($s['expires_at']))) . '）' : '' ?></span></p>
  <p class="muted">送ったメッセージ</p>
  <p><?= nl2br(h($s['message'])) ?></p>
  <p><a href="<?= h(url('counselor/member.php?id=' . (int)$s['member_id'])) ?>">会員のプロフィールを見る</a></p>
</div>

<?php if ($chatOk): ?>
<div class="card" id="chat">
  <h2>メッセージ</h2>
  <?php if ($chatUnread > 0): ?><p><span class="badge badge-important"><?= $chatUnread ?>件の新着メッセージ</span></p><?php endif; ?>
  <a class="btn btn-secondary" href="<?= h(url('counselor/chat.php?id=' . $id)) ?>">メッセージを見る</a>
</div>
<?php endif; ?>

<div class="card">
  <h2>面談</h2>
  <?php foreach ($errors as $e): ?><div class="flash flash-error"><?= h($e) ?></div><?php endforeach; ?>

  <?php if ($s['status'] === 'proposed'): ?>
    <?php foreach ($slots as $sl): if ((int)$sl['id'] === (int)$s['proposed_slot_id']): ?>
      <p>会員から日程の提案がありました：<strong><?= h(fmt_chosen_time($sl)) ?></strong>（<?= h(slot_meeting_label($sl)) ?>）</p>
      <?php if ($sl['meeting_url']): ?><p class="muted">オンライン会議URL：<a href="<?= h($sl['meeting_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($sl['meeting_url']) ?></a></p><?php endif; ?>
      <?php if ($sl['counselor_note']): ?><p class="muted">出したメモ：<?= nl2br(h($sl['counselor_note'])) ?></p><?php endif; ?>
      <?php if ($sl['member_note']): ?><p class="muted">会員からのメモ：<?= nl2br(h($sl['member_note'])) ?></p><?php endif; ?>
    <?php endif; endforeach; ?>
    <div class="scout-actions">
      <form method="post" action="<?= h(url('counselor/scout.php')) ?>" onsubmit="return confirm('この内容で面談を確定しますか？');">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" name="action" value="confirm_slot" class="btn">この内容で確定する</button>
      </form>
      <form method="post" action="<?= h(url('counselor/scout.php')) ?>" onsubmit="return confirm('会員に、候補を選び直してもらいますか？');">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" name="action" value="decline_proposal" class="btn btn-secondary">選び直してもらう</button>
      </form>
    </div>
  <?php elseif ($s['status'] === 'booked'): ?>
    <?php foreach ($slots as $sl): if ((int)$sl['id'] === (int)$s['booked_slot_id']): ?>
      <p>面談の予約が入っています：<strong><?= h(fmt_chosen_time($sl)) ?></strong>（<?= h(slot_meeting_label($sl)) ?>）</p>
      <?php if ($sl['meeting_url']): ?><p class="muted">オンライン会議URL：<a href="<?= h($sl['meeting_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($sl['meeting_url']) ?></a></p><?php endif; ?>
      <?php if ($sl['member_note']): ?><p class="muted">会員からのメモ：<?= nl2br(h($sl['member_note'])) ?></p><?php endif; ?>
      <p><a class="btn btn-secondary btn-small" href="<?= h(url('counselor/ics.php?id=' . $id)) ?>">カレンダーに追加（.ics）</a></p>
    <?php endif; endforeach; ?>
    <form method="post" action="<?= h(url('counselor/scout.php')) ?>" onsubmit="return confirm('面談を実施済みにしますか？');">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <button type="submit" name="action" value="mark_met" class="btn">面談を実施済みにする</button>
    </form>
  <?php elseif ($s['status'] === 'sent' && $ds !== '期限切れ'): ?>
    <div class="interview-card<?= $slots ? '' : ' manage-open' ?>">
    <p class="muted">会員との面談の候補日時を出してください（<?= SLOT_MAX_PER_SCOUT ?>つまで）。会員が候補を選んだ後に、カウンセラーが確定する流れになっています。</p>
    <?php
      $remaining = SLOT_MAX_PER_SCOUT - count($slots);
      $rowsToShow = min(5, $remaining);
      $circled = ['①', '②', '③', '④', '⑤'];
      $hasOptional = ($rowsToShow > 0) && (count($slots) + $rowsToShow > 3);
      $existingLocation = '';
      $existingNote = '';
      foreach ($slots as $sl) {
          if ($existingLocation === '' && !empty($sl['location'])) { $existingLocation = $sl['location']; }
          if ($existingNote === '' && !empty($sl['counselor_note'])) { $existingNote = $sl['counselor_note']; }
      }
    ?>
    <form method="post" action="<?= h(url('counselor/scout.php')) ?>" class="slot-manage-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <?php foreach ($slots as $sl): ?>
        <?php
          $slDate = date('Y-m-d', strtotime($sl['starts_at']));
          $slFrom = date('H:i', strtotime($sl['starts_at']));
          $slTo = date('H:i', strtotime($sl['ends_at']));
          $slMeeting = (string)$sl['meeting_type'];
          $sid = (int)$sl['id'];
        ?>
        <div class="slot-row">
          <span class="slot-row-summary">
            <?= h(fmt_slot_range($sl)) ?>（<?= h(slot_meeting_label($sl)) ?>）
            <?php if ($sl['meeting_url']): ?><br><span class="muted">会議URL：<?= h($sl['meeting_url']) ?></span><?php endif; ?>
            <?php if ($sl['counselor_note']): ?><br><span class="muted"><?= h($sl['counselor_note']) ?></span><?php endif; ?>
          </span>
          <div class="slot-row-edit-fields">
            <input type="date" name="existing[<?= $sid ?>][date]" value="<?= h($slDate) ?>" min="<?= h($minDate) ?>">
            <div class="time-range">
              <select name="existing[<?= $sid ?>][time_from]">
                <?php foreach (time_slot_options() as $t): ?><option value="<?= h($t) ?>" <?= $t === $slFrom ? 'selected' : '' ?>><?= h($t) ?></option><?php endforeach; ?>
              </select>
              <span>〜</span>
              <select name="existing[<?= $sid ?>][time_to]">
                <?php foreach (time_slot_options() as $t): ?><option value="<?= h($t) ?>" <?= $t === $slTo ? 'selected' : '' ?>><?= h($t) ?></option><?php endforeach; ?>
              </select>
            </div>
            <select name="existing[<?= $sid ?>][duration_min]">
              <?php foreach (SLOT_DURATIONS as $k => $l): ?><option value="<?= $k ?>" <?= $k === (int)$sl['duration_min'] ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?>
            </select>
            <div class="radio-group">
              <label><input type="radio" name="existing[<?= $sid ?>][meeting_type]" value="online" <?= $slMeeting !== 'offline' && $slMeeting !== 'either' ? 'checked' : '' ?>> オンライン</label>
              <label><input type="radio" name="existing[<?= $sid ?>][meeting_type]" value="offline" <?= $slMeeting === 'offline' ? 'checked' : '' ?>> 対面</label>
              <label><input type="radio" name="existing[<?= $sid ?>][meeting_type]" value="either" <?= $slMeeting === 'either' ? 'checked' : '' ?>> どちらでも</label>
            </div>
            <label class="slot-row-delete"><input type="checkbox" name="existing[<?= $sid ?>][delete]" value="1"> この候補を取り消す</label>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if ($slots): ?>
      <p class="slot-toggle-row"><button type="button" id="slot-manage-toggle" class="btn btn-secondary btn-small">候補の追加・取り消しを行う</button></p>
      <?php endif; ?>
      <?php if ($rowsToShow > 0): ?>
      <div class="sheet-wrap slot-shared-fields">
      <table class="slot-table">
        <thead>
          <tr><th></th><th>日付</th><th>時間帯</th><th>所要時間</th><th>方法</th></tr>
        </thead>
        <tbody>
        <?php $optOpened = false; ?>
        <?php for ($i = 0; $i < $rowsToShow; $i++): ?>
          <?php
            $row = $_POST['slot'][$i] ?? [];
            $rowMeeting = (string)($row['meeting_type'] ?? 'online');
            $candNum = count($slots) + $i + 1;
            $isOptional = $candNum >= 4;
          ?>
          <?php if ($isOptional && !$optOpened): ?></tbody><tbody class="slot-optional-body" id="slot-optional-body"><?php $optOpened = true; endif; ?>
          <tr>
            <th>候補<?= $circled[$candNum - 1] ?></th>
            <td><input type="date" id="slot_date_<?= $i ?>" name="slot[<?= $i ?>][date]" min="<?= h($minDate) ?>" value="<?= h((string)($row['date'] ?? '')) ?>"></td>
            <td>
              <div class="time-range">
                <select id="slot_from_<?= $i ?>" name="slot[<?= $i ?>][time_from]">
                  <?php foreach (time_slot_options() as $t): ?><option value="<?= h($t) ?>" <?= $t === ($row['time_from'] ?? '10:00') ? 'selected' : '' ?>><?= h($t) ?></option><?php endforeach; ?>
                </select>
                <span>〜</span>
                <select id="slot_to_<?= $i ?>" name="slot[<?= $i ?>][time_to]">
                  <?php foreach (time_slot_options() as $t): ?><option value="<?= h($t) ?>" <?= $t === ($row['time_to'] ?? '18:00') ? 'selected' : '' ?>><?= h($t) ?></option><?php endforeach; ?>
                </select>
              </div>
            </td>
            <td>
              <select id="slot_dur_<?= $i ?>" name="slot[<?= $i ?>][duration_min]">
                <?php foreach (SLOT_DURATIONS as $k => $l): ?><option value="<?= $k ?>" <?= $k === (int)($row['duration_min'] ?? 60) ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?>
              </select>
            </td>
            <td>
              <div class="radio-group">
                <label><input type="radio" name="slot[<?= $i ?>][meeting_type]" value="online" class="meeting-radio" data-row="<?= $i ?>" <?= $rowMeeting !== 'offline' && $rowMeeting !== 'either' ? 'checked' : '' ?>> オンライン</label>
                <label><input type="radio" name="slot[<?= $i ?>][meeting_type]" value="offline" class="meeting-radio" data-row="<?= $i ?>" <?= $rowMeeting === 'offline' ? 'checked' : '' ?>> 対面</label>
                <label><input type="radio" name="slot[<?= $i ?>][meeting_type]" value="either" class="meeting-radio" data-row="<?= $i ?>" <?= $rowMeeting === 'either' ? 'checked' : '' ?>> どちらでも</label>
              </div>
            </td>
          </tr>
        <?php endfor; ?>
        </tbody>
      </table>
      </div>
      <?php if ($hasOptional): ?>
      <p class="slot-toggle-row slot-shared-fields"><button type="button" id="slot-toggle-more" class="btn btn-secondary btn-small">候補④⑤の欄も表示する</button></p>
      <?php endif; ?>
      <?php endif; ?>
      <div class="form-row slot-location-row slot-shared-fields">
        <label for="slot_location">場所<span class="hint">（対面の候補がある場合に入力。カフェなど自由に・候補すべてに共通です。既存の候補にも、この内容がまとめて適用されます）</span></label>
        <input type="text" id="slot_location" name="location" maxlength="<?= SLOT_LOCATION_MAX ?>" value="<?= h((string)($_POST['location'] ?? $existingLocation)) ?>">
      </div>
      <div class="form-row slot-note-row slot-shared-fields">
        <label for="slot_note">会員へのメモ<span class="hint">（任意・既存の候補・新しく追加する候補すべてに共通で表示されます）</span></label>
        <textarea id="slot_note" name="note" maxlength="<?= SLOT_NOTE_MAX ?>" rows="2" placeholder="例：できれば午後だと助かります"><?= h((string)($_POST['note'] ?? $existingNote)) ?></textarea>
      </div>
      <p class="hint hint-tight slot-shared-fields">日付を入力した行だけが、新しい候補として追加されます。空欄のままでかまいません。下の「変更を保存する」を押すと、ここまでの編集・取り消し・追加がすべて反映されます（新しい候補を追加した場合のみ、会員にもメールで伝わります）。</p>
      <button type="submit" name="action" value="save_slots" class="btn slot-shared-fields">変更を保存する</button>
    </form>
    <script>
    (function () {
      var toggleBtn = document.getElementById('slot-toggle-more');
      var optBody = document.getElementById('slot-optional-body');
      if (toggleBtn && optBody) {
        toggleBtn.addEventListener('click', function () {
          var willOpen = !optBody.classList.contains('is-open');
          optBody.classList.toggle('is-open', willOpen);
          toggleBtn.textContent = willOpen ? '候補④⑤の欄を隠す' : '候補④⑤の欄も表示する';
        });
      }
      var manageToggle = document.getElementById('slot-manage-toggle');
      if (manageToggle) {
        manageToggle.addEventListener('click', function () {
          var card = manageToggle.closest('.interview-card');
          if (!card) { return; }
          var willOpen = !card.classList.contains('manage-open');
          card.classList.toggle('manage-open', willOpen);
          manageToggle.textContent = willOpen ? '候補の追加・取り消しを閉じる' : '候補の追加・取り消しを行う';
        });
      }
    })();
    </script>
    </div>
  <?php elseif ($s['status'] === 'met'): ?>
    <p>面談は実施済みです。</p>
  <?php else: ?>
    <p class="muted">このスカウトでは、いま面談の操作はありません。</p>
  <?php endif; ?>
</div>
<?php if ($contract): ?>
<div class="card">
  <h2>契約</h2>
  <p><span class="badge <?= $contract['status'] === 'confirmed' ? 'badge-approved' : ($contract['status'] === 'rejected' ? 'badge-rejected' : 'badge-pending') ?>"><?= h(CONTRACT_STATUS_LABELS[$contract['status']]) ?></span></p>
  <table class="kv">
    <tr><th>契約日（会員の申告）</th><td><?= h(date('Y年n月j日', strtotime($contract['contract_date']))) ?></td></tr>
    <tr><th>入会金</th><td><?= number_format((int)$contract['entry_fee_snapshot']) ?>円</td></tr>
    <tr><th>紹介手数料（入会1件につき）</th><td><strong><?= number_format((int)$contract['referral_fee']) ?>円</strong></td></tr>
  </table>
  <?php if ($contract['status'] === 'reported' || $contract['status'] === 'confirming'): ?>
    <p class="muted">会員から契約の申告がありました。運営から、確認のメールが届きます（カウンセラーの登録メールと、相談所の連絡先メール）。</p>
  <?php elseif ($contract['status'] === 'rejected'): ?>
    <p class="muted">運営の確認で、契約を確認できませんでした。</p>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php render_footer(); ?>
