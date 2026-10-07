<?php
// counselor/chat.php：会員とのメッセージのやり取り（スレッド）

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';
require_once __DIR__ . '/../includes/upload.php';

$user = require_login('counselor');
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT s.*, m.nickname FROM scouts s JOIN members m ON m.user_id = s.member_id WHERE s.id = ? AND s.counselor_id = ?');
$stmt->execute([$id, $user['id']]);
$s = $stmt->fetch();
if (!$s) {
    http_response_code(404);
    render_header('見つかりません', 'counselor');
    echo '<div class="card"><p>このメッセージは表示できません。</p><p><a href="' . h(url('counselor/messages.php')) . '">メッセージ一覧に戻る</a></p></div>';
    render_footer();
    exit;
}

$chatError = '';
if (!chat_eligible($s)) {
    http_response_code(404);
    render_header('見つかりません', 'counselor');
    echo '<div class="card"><p>このスカウトでは、まだメッセージのやり取りができません。</p><p><a href="' . h(url('counselor/scout.php?id=' . $id)) . '">スカウトの詳細を見る</a></p></div>';
    render_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $chatAction = (string)($_POST['action'] ?? '');
    if ($chatAction === 'send_message') {
        $body = trim((string)($_POST['body'] ?? ''));
        $hasFile = isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE;
        $attachment = null;
        if (mb_strlen($body) > CHAT_MESSAGE_MAX) {
            $chatError = 'メッセージは' . CHAT_MESSAGE_MAX . '文字以内で入力してください。';
        } elseif ($body === '' && !$hasFile) {
            $chatError = 'メッセージを入力するか、ファイルを添付してください。';
        } else {
            $chatError = '';
            if ($hasFile) {
                try {
                    $attachment = save_upload($_FILES['attachment'], 'chat', array_keys(UPLOAD_MIME_EXT));
                } catch (RuntimeException $e) {
                    $chatError = $e->getMessage();
                }
            }
            if ($chatError === '') {
                $pdo->prepare('INSERT INTO scout_messages (scout_id, sender_role, sender_user_id, body, attachment_path, attachment_original, attachment_mime, attachment_size)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$id, 'counselor', $user['id'], $body === '' ? null : $body,
                               $attachment['path'] ?? null, $attachment['original'] ?? null, $attachment['mime'] ?? null, $attachment['size'] ?? null]);
                redirect('counselor/chat.php?id=' . $id);
            }
        }
    } elseif ($chatAction === 'edit_message') {
        // 自分（counselor）が送ったメッセージの本文だけを書き換えられる（添付ファイルは編集対象外）
        $mid = (int)($_POST['mid'] ?? 0);
        $newBody = trim((string)($_POST['body'] ?? ''));
        if ($newBody === '') {
            $chatError = 'メッセージを入力してください。';
        } elseif (mb_strlen($newBody) > CHAT_MESSAGE_MAX) {
            $chatError = 'メッセージは' . CHAT_MESSAGE_MAX . '文字以内で入力してください。';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE scout_messages SET body = ?, edited_at = NOW()
                 WHERE id = ? AND scout_id = ? AND sender_role = ? AND sender_user_id = ? AND deleted_at IS NULL'
            );
            $stmt->execute([$newBody, $mid, $id, 'counselor', $user['id']]);
            if ($stmt->rowCount() === 0) {
                $chatError = 'このメッセージは編集できません。';
            } else {
                redirect('counselor/chat.php?id=' . $id);
            }
        }
    } elseif ($chatAction === 'delete_message') {
        // 自分（counselor）が送ったメッセージだけを削除できる（ソフトデリート）
        $mid = (int)($_POST['mid'] ?? 0);
        $pdo->prepare(
            'UPDATE scout_messages SET deleted_at = NOW()
             WHERE id = ? AND scout_id = ? AND sender_role = ? AND sender_user_id = ? AND deleted_at IS NULL'
        )->execute([$mid, $id, 'counselor', $user['id']]);
        redirect('counselor/chat.php?id=' . $id);
    }
}

$ds = scout_display_status($s);
$badgeClass = $s['status'] === 'booked' ? 'badge-now' : ($s['status'] === 'proposed' ? 'badge-pending' : 'badge-sent');
$messages = load_scout_messages($pdo, $id, 'counselor');

render_header($s['nickname'] . 'さんとのメッセージ', 'counselor');
?>
<p><a href="<?= h(url('counselor/messages.php')) ?>">‹ メッセージ一覧に戻る</a></p>
<div class="card">
  <div class="member-card-head">
    <span class="counselor-photo-sm counselor-photo-placeholder" aria-hidden="true"></span>
    <a class="member-card-name" href="<?= h(url('counselor/scout.php?id=' . $id)) ?>"><?= h($s['nickname']) ?> さん</a>
    <span class="badge <?= $badgeClass ?>"><?= h($ds) ?></span>
  </div>
  <p class="muted"><a href="<?= h(url('counselor/scout.php?id=' . $id)) ?>">スカウト・面談の詳細を見る ›</a></p>
</div>

<div class="card">
  <?php if ($chatError): ?><div class="flash flash-error"><?= h($chatError) ?></div><?php endif; ?>
  <div class="chat-thread" id="chat-thread">
    <?php if (!$messages): ?>
      <p class="muted">まだメッセージはありません。</p>
    <?php endif; ?>
    <?php $chatLastDate = null; ?>
    <?php foreach ($messages as $msg): ?>
      <?php
        $isMine = $msg['sender_role'] === 'counselor';
        $isDeleted = $msg['deleted_at'] !== null;
        $msgDate = date('Y-m-d', strtotime($msg['created_at']));
        if ($msgDate !== $chatLastDate) {
            echo '<div class="chat-date-sep">' . h(chat_date_label($msg['created_at'])) . '</div>';
            $chatLastDate = $msgDate;
        }
        // 相手（会員）のメッセージには、LINEのように左側にアバターを出す。会員には写真登録がないため、
        // 丸い写真の代わりにニックネームの頭文字＋「さん」のバッジ（例：「Nさん」）を表示する
        $memberInitial = $s['nickname'] !== '' ? mb_substr($s['nickname'], 0, 1) . 'さん' : '会員さん';
        $avatarHtml = $isMine ? '' : '<span class="chat-avatar-initial" aria-hidden="true">' . h($memberInitial) . '</span>';
      ?>
      <div class="chat-row <?= $isMine ? 'chat-row-mine' : 'chat-row-theirs' ?>">
        <?= $avatarHtml ?>
        <div class="chat-bubble <?= $isMine ? 'chat-bubble-mine' : 'chat-bubble-theirs' ?><?= $isDeleted ? ' chat-bubble-deleted' : '' ?>">
          <?php if ($isDeleted): ?>
            <p class="chat-bubble-deleted-text">このメッセージは削除されました</p>
          <?php else: ?>
            <?php if ($msg['body']): ?><p class="chat-bubble-body" id="chat_body_view_<?= (int)$msg['id'] ?>"><?= nl2br(h($msg['body'])) ?></p><?php endif; ?>
            <?php if ($msg['attachment_path']): ?>
              <p class="chat-bubble-attachment">
                📎 <a href="<?= h(url('chat_file.php?mid=' . (int)$msg['id'])) ?>" target="_blank" rel="noopener noreferrer"><?= h($msg['attachment_original'] ?? 'ファイル') ?></a>
                <span class="muted">（<?= h(format_file_size((int)$msg['attachment_size'])) ?>）</span>
              </p>
            <?php endif; ?>
            <?php if ($isMine && $msg['body']): ?>
              <form method="post" action="<?= h(url('counselor/chat.php')) ?>" class="chat-edit-form" id="chat_edit_form_<?= (int)$msg['id'] ?>" style="display:none;">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="mid" value="<?= (int)$msg['id'] ?>">
                <label for="chat_edit_ta_<?= (int)$msg['id'] ?>" class="sr-only">メッセージを編集</label>
                <textarea id="chat_edit_ta_<?= (int)$msg['id'] ?>" name="body" class="chat-edit-textarea" maxlength="<?= CHAT_MESSAGE_MAX ?>"><?= h($msg['body']) ?></textarea>
                <div class="chat-edit-actions">
                  <button type="submit" name="action" value="edit_message" class="btn btn-small">保存</button>
                  <button type="button" class="btn-link chat-edit-cancel" data-mid="<?= (int)$msg['id'] ?>">キャンセル</button>
                </div>
              </form>
            <?php endif; ?>
          <?php endif; ?>
          <p class="chat-bubble-time muted">
            <?php if ($isMine && $msg['read_at']): ?><span class="chat-read">既読</span><?php endif; ?>
            <?php if (!$isDeleted && $msg['edited_at']): ?><span class="chat-edited">（編集済み）</span><?php endif; ?>
            <?= h(date('n/j H:i', strtotime($msg['created_at']))) ?>
            <?php if ($isMine && !$isDeleted): ?>
              <?php if ($msg['body']): ?><button type="button" class="btn-link chat-edit-toggle" data-mid="<?= (int)$msg['id'] ?>">編集</button><?php endif; ?>
              <button type="submit" form="chat_delete_form_<?= (int)$msg['id'] ?>" class="btn-link chat-delete-btn">削除</button>
            <?php endif; ?>
          </p>
          <?php if ($isMine && !$isDeleted): ?>
            <form method="post" action="<?= h(url('counselor/chat.php')) ?>" id="chat_delete_form_<?= (int)$msg['id'] ?>" onsubmit="return confirm('このメッセージを削除しますか？');" style="display:none;">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $id ?>">
              <input type="hidden" name="mid" value="<?= (int)$msg['id'] ?>">
              <input type="hidden" name="action" value="delete_message">
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <script>
    (function () {
      var t = document.getElementById('chat-thread');
      if (t) { t.scrollTop = t.scrollHeight; }
    })();
  </script>
  <script>
    (function () {
      document.querySelectorAll('.chat-edit-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var mid = btn.getAttribute('data-mid');
          var view = document.getElementById('chat_body_view_' + mid);
          var form = document.getElementById('chat_edit_form_' + mid);
          if (view) { view.style.display = 'none'; }
          if (form) {
            form.style.display = '';
            var ta = form.querySelector('textarea');
            if (ta) { ta.focus(); }
          }
        });
      });
      document.querySelectorAll('.chat-edit-cancel').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var mid = btn.getAttribute('data-mid');
          var view = document.getElementById('chat_body_view_' + mid);
          var form = document.getElementById('chat_edit_form_' + mid);
          if (form) { form.style.display = 'none'; }
          if (view) { view.style.display = ''; }
        });
      });
    })();
  </script>
  <form method="post" action="<?= h(url('counselor/chat.php')) ?>" enctype="multipart/form-data" class="chat-form">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="form-row chat-input-row">
      <button type="button" class="chat-attach-btn" id="chat_attach_btn" aria-label="ファイルを添付" title="ファイルを添付（JPG・PNG・PDF、5MBまで）">+</button>
      <input type="file" name="attachment" id="chat_attachment" class="chat-file-input" accept=".jpg,.jpeg,.png,.pdf" aria-label="ファイルを添付（JPG・PNG・PDF、5MBまで）">
      <label for="chat_body" class="sr-only">メッセージ</label>
      <textarea id="chat_body" name="body" rows="3" maxlength="<?= CHAT_MESSAGE_MAX ?>" placeholder="メッセージを入力"><?= h((string)($_POST['body'] ?? '')) ?></textarea>
      <button type="submit" name="action" value="send_message" class="btn btn-small chat-send-btn">送信</button>
    </div>
    <p class="chat-attach-preview" id="chat_attach_preview" style="display:none;">
      📎 <span id="chat_attach_name"></span>
      <button type="button" class="btn-link" id="chat_attach_remove">削除</button>
    </p>
    <p class="hint">添付できるファイル：JPG・PNG・PDF（5MBまで）</p>
  </form>
  <script>
    (function () {
      var attachBtn = document.getElementById('chat_attach_btn');
      var fileInput = document.getElementById('chat_attachment');
      var preview = document.getElementById('chat_attach_preview');
      var nameEl = document.getElementById('chat_attach_name');
      var removeBtn = document.getElementById('chat_attach_remove');
      if (!attachBtn || !fileInput) { return; }
      attachBtn.addEventListener('click', function () { fileInput.click(); });
      fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files.length) {
          nameEl.textContent = fileInput.files[0].name;
          preview.style.display = '';
          attachBtn.classList.add('is-active');
        } else {
          preview.style.display = 'none';
          attachBtn.classList.remove('is-active');
        }
      });
      if (removeBtn) {
        removeBtn.addEventListener('click', function () {
          fileInput.value = '';
          preview.style.display = 'none';
          attachBtn.classList.remove('is-active');
        });
      }
    })();
  </script>
</div>
<?php render_footer(); ?>
