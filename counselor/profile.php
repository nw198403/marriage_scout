<?php
// counselor/profile.php：カウンセラー本人のプロフィール入力・編集
// 相談所そのものの情報（相談所名・連盟・書類など）は counselor/agency.php で管理する

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';
require_once __DIR__ . '/../includes/upload.php';

$user = require_login('counselor');
$pdo = db();

$stmt = $pdo->prepare(
    'SELECT c.*, a.store_no, a.agency_name, a.approval_status, a.review_note
     FROM counselors c JOIN agencies a ON a.id = c.agency_id
     WHERE c.user_id = ?'
);
$stmt->execute([$user['id']]);
$c = $stmt->fetch();
if (!$c) {
    http_response_code(404);
    exit('カウンセラー情報が見つかりません。');
}

// タグ（得意な不安・悩み / 得意な年代 / 得意な婚姻歴 / 提供できるサポート）
$tagsByCat = ['concern' => [], 'age' => [], 'marital' => [], 'service' => []];
foreach ($pdo->query('SELECT id, name, category FROM support_tags ORDER BY category, sort_order')->fetchAll() as $t) {
    $tagsByCat[$t['category']][(int)$t['id']] = $t['name'];
}
$allTagIds = [];
foreach ($tagsByCat as $list) {
    $allTagIds = array_merge($allTagIds, array_keys($list));
}

$stmt = $pdo->prepare('SELECT tag_id FROM counselor_support_tags WHERE counselor_id = ?');
$stmt->execute([$user['id']]);
$selectedTags = array_map('intval', array_column($stmt->fetchAll(), 'tag_id'));

$selectedAreas = array_map(fn($r) => $r['prefecture'], (function () use ($pdo, $user) {
    $st = $pdo->prepare('SELECT prefecture FROM counselor_areas WHERE counselor_id = ?');
    $st->execute([$user['id']]);
    return $st->fetchAll();
})());

$errors = [];

function int_or_null(string $v): ?int
{
    return $v === '' ? null : (int)$v;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'withdraw') {
        if (!empty($c['is_representative'])) {
            flash('error', '代表者は「相談所の情報」画面から、相談所ごと退会してください。');
            redirect('counselor/profile.php');
        }
        $pdo->prepare("UPDATE users SET status = 'withdrawn' WHERE id = ?")->execute([$user['id']]);
        $pdo->prepare('UPDATE counselors SET is_active = 0 WHERE user_id = ?')->execute([$user['id']]);
        logout_user();
        flash('ok', '退会しました。ご利用ありがとうございました。');
        redirect('index.php');
    }

    $in = [
        'display_name'        => trim((string)($_POST['display_name'] ?? '')),
        'mbti_type'           => (string)($_POST['mbti_type'] ?? ''),
        'prefecture'          => (string)($_POST['prefecture'] ?? ''),
        'service_style'       => (string)($_POST['service_style'] ?? ''),
        'experience_years'    => trim((string)($_POST['experience_years'] ?? '')),
        'marriage_count'      => trim((string)($_POST['marriage_count'] ?? '')),
        'entry_fee'           => trim((string)($_POST['entry_fee'] ?? '')),
        'monthly_fee'         => trim((string)($_POST['monthly_fee'] ?? '')),
        'success_fee'         => trim((string)($_POST['success_fee'] ?? '')),
        'max_members'         => trim((string)($_POST['max_members'] ?? '')),
        'monthly_scout_limit' => trim((string)($_POST['monthly_scout_limit'] ?? '')),
        'bio'                 => trim((string)($_POST['bio'] ?? '')),
        'blog_url'            => trim((string)($_POST['blog_url'] ?? '')),
        'sns_url'             => trim((string)($_POST['sns_url'] ?? '')),
        'is_active'           => isset($_POST['is_active']) ? 1 : 0,
    ];
    $postedTags = array_values(array_intersect(array_map('intval', (array)($_POST['tags'] ?? [])), $allTagIds));
    $draft = isset($_POST['draft']);   // 途中保存：必須項目が空でも保存できる（公開はされない）
    $removePhoto = ($_POST['remove_photo'] ?? '') === '1';

    if ($in['display_name'] === '' || mb_strlen($in['display_name']) > 50) {
        $errors[] = '表示名を50文字以内で入力してください。';
    }
    if ($in['mbti_type'] !== '' && !in_array($in['mbti_type'], MBTI_TYPES, true)) {
        $errors[] = 'MBTIタイプの指定が正しくありません。';
    }
    if (!($draft && $in['prefecture'] === '') && !in_array($in['prefecture'], PREFECTURES, true)) {
        $errors[] = '拠点の都道府県を選んでください。';
    }
    $postedAreas = array_values(array_intersect(PREFECTURES, array_map('strval', (array)($_POST['areas'] ?? []))));
    if (!$draft && !$postedAreas && $in['service_style'] !== 'online') {
        $errors[] = '対応エリアを1つ以上選んでください（対面で会える都道府県）。';
    }
    if (!array_key_exists($in['service_style'], SERVICE_STYLE_LABELS)) {
        $errors[] = '面談の方法を選んでください。';
    }
    // 数値項目（空欄可のもの／必須のもの）
    foreach ([
        'experience_years' => ['経験年数', 0, 60, false],
        'marriage_count'   => ['成婚数', 0, 100000, false],
        'entry_fee'        => ['入会金', 0, 5000000, true],
        'monthly_fee'      => ['月会費', 0, 500000, true],
        'success_fee'      => ['成婚料', 0, 5000000, true],
        'max_members'      => ['担当上限数', 1, 10000, true],
        'monthly_scout_limit' => ['月のスカウト上限', 1, 1000, true],
    ] as $key => [$label, $min, $max, $required]) {
        $v = $in[$key];
        if ($draft) {
            $min = 0;   // 途中保存では、まだ決まっていない数値（0）も保存できる
        }
        if ($v === '') {
            if ($required && !$draft) {
                $errors[] = "{$label}を入力してください。";
            }
        } elseif (!is_numeric($v) || (float)$v < $min || (float)$v > $max) {
            $errors[] = "{$label}は {$min}〜{$max} の数字で入力してください。";
        }
    }
    if ((!$draft && $in['bio'] === '') || mb_strlen($in['bio']) > 2000) {
        $errors[] = '自己紹介・サポート方針を2000文字以内で入力してください。';
    }
    foreach (['blog_url' => 'ブログURL', 'sns_url' => 'SNSのURL'] as $key => $label) {
        if ($in[$key] !== '' && (!preg_match('#^https?://#i', $in[$key]) || !filter_var($in[$key], FILTER_VALIDATE_URL) || mb_strlen($in[$key]) > 255)) {
            $errors[] = "{$label}は http:// か https:// で始まる正しいURLを入力してください。";
        }
    }
    // 得意な年代は最大2つ
    $ageCount = count(array_intersect($postedTags, array_keys($tagsByCat['age'])));
    if ($ageCount > 2) {
        $errors[] = '得意な年代は2つまで選べます。';
    }
    // 得意分野は1〜3つ（全部選ぶと強みが伝わらないため）
    $concernCount = count(array_intersect($postedTags, array_keys($tagsByCat['concern'])));
    if ((!$draft && $concernCount < 1) || $concernCount > CONCERN_TAGS_MAX) {
        $errors[] = '得意な不安・悩みを1〜' . CONCERN_TAGS_MAX . 'つ選んでください。';
    }

    $newPhoto = null;
    if (!empty($_FILES['photo']['name'])) {
        try {
            $newPhoto = save_upload($_FILES['photo'], 'counselor_photos', ['image/jpeg', 'image/png']);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        // 公開は、相談所の審査が承認済みのときだけ（途中保存は非公開にする）
        $isActive = ($c['approval_status'] === 'approved' && !$draft) ? $in['is_active'] : 0;
        $oldPhotoPath = $c['photo_path'] ?? null;
        if ($newPhoto !== null) {
            $photoPath = $newPhoto['path'];
        } elseif ($removePhoto) {
            $photoPath = null;
        } else {
            $photoPath = $oldPhotoPath;
        }

        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                'UPDATE counselors SET display_name = ?, mbti_type = ?,
                        prefecture = ?, photo_path = ?,
                        service_style = ?, bio = ?, blog_url = ?, sns_url = ?, experience_years = ?, marriage_count = ?,
                        entry_fee = ?, monthly_fee = ?, success_fee = ?, max_members = ?, monthly_scout_limit = ?, is_active = ?
                 WHERE user_id = ?'
            )->execute([
                $in['display_name'], $in['mbti_type'] === '' ? null : $in['mbti_type'],
                $in['prefecture'] === '' ? null : $in['prefecture'], $photoPath,
                $in['service_style'], $in['bio'],
                $in['blog_url'] === '' ? null : $in['blog_url'],
                $in['sns_url'] === '' ? null : $in['sns_url'],
                int_or_null($in['experience_years']), int_or_null($in['marriage_count']),
                (int)$in['entry_fee'], (int)$in['monthly_fee'], (int)$in['success_fee'], (int)$in['max_members'], (int)$in['monthly_scout_limit'],
                $isActive, $user['id'],
            ]);

            $pdo->prepare('DELETE FROM counselor_areas WHERE counselor_id = ?')->execute([$user['id']]);
            $insA = $pdo->prepare('INSERT INTO counselor_areas (counselor_id, prefecture) VALUES (?, ?)');
            foreach ($postedAreas as $pref) {
                $insA->execute([$user['id'], $pref]);
            }

            $pdo->prepare('DELETE FROM counselor_support_tags WHERE counselor_id = ?')->execute([$user['id']]);
            $ins = $pdo->prepare('INSERT INTO counselor_support_tags (counselor_id, tag_id) VALUES (?, ?)');
            foreach ($postedTags as $tagId) {
                $ins->execute([$user['id'], $tagId]);
            }
            $pdo->commit();
            if ($oldPhotoPath !== null && $oldPhotoPath !== $photoPath) {
                delete_upload($oldPhotoPath);
            }

            flash('ok', $draft ? '途中まで保存しました（公開はされません）。' : 'プロフィールを保存しました。');
            redirect('counselor/profile.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($newPhoto !== null) {
                delete_upload($newPhoto['path']);
            }
            throw $e;
        }
    } elseif ($newPhoto !== null) {
        delete_upload($newPhoto['path']);
    }

    $c = array_merge($c, $in);
    $selectedTags = $postedTags;
    $selectedAreas = $postedAreas;
}

$style = $c['service_style'] ?? 'both';
$journeyBar = render_journey_bar(counselor_journey_step($pdo, $user['id']), COUNSELOR_JOURNEY_STEPS);
render_header('プロフィール', 'counselor', '', $journeyBar);
?>
<div class="card">
  <h1>カウンセラープロフィール</h1>
  <p class="muted">会員があなたを選ぶときの判断材料になります。成婚数などの実績は自己申告です。</p>

  <?php $ast = $c['approval_status'] ?? 'pending'; ?>
  <?php foreach ($errors as $e): ?>
    <div class="flash flash-error"><?= h($e) ?></div>
  <?php endforeach; ?>

  <form method="post" action="<?= h(url('counselor/profile.php')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <h2>基本情報</h2>
    <div class="profile-photo-row">
      <div class="profile-photo-row-fields">
        <div class="form-row">
          <label for="display_name">表示名</label>
          <input type="text" id="display_name" name="display_name" maxlength="50" value="<?= h($c['display_name'] ?? '') ?>" required>
        </div>
        <div class="form-row">
          <label for="mbti_type">MBTI <span class="hint">（任意・自己申告。会員から見えます）</span></label>
          <select id="mbti_type" name="mbti_type">
            <option value="">わからない・答えない</option>
            <?php foreach (MBTI_TYPES as $mb): ?>
              <option value="<?= h($mb) ?>" <?= ($c['mbti_type'] ?? '') === $mb ? 'selected' : '' ?>><?= h($mb) ?>（<?= h(MBTI_LABELS[$mb]) ?>）</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label>所属</label>
          <p class="static-value"><?= h($c['agency_name']) ?>（<?= h(APPROVAL_LABELS[$ast]) ?>）</p>
          <p class="hint hint-tight">
            <?php if ($ast === 'pending'): ?>
              確認が済むまで、会員への公開とスカウトはできません。
            <?php elseif ($ast === 'rejected'): ?>
              確認できませんでした。相談所の情報を直すと、もう一度確認を受けられます。
            <?php endif; ?>
            <a href="<?= h(url('counselor/agency.php')) ?>">相談所の情報を管理する</a>
          </p>
        </div>
        <div class="form-row">
          <label for="prefecture">勤務先（都道府県）</label>
          <select id="prefecture" name="prefecture" required>
            <option value="">選んでください</option>
            <?php foreach (PREFECTURES as $p): ?>
              <option value="<?= h($p) ?>" <?= ($c['prefecture'] ?? '') === $p ? 'selected' : '' ?>><?= h($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="profile-photo-row-photo">
        <div class="form-row">
          <label for="photo">プロフィール写真 <span class="hint">（任意）</span></label>
          <div class="profile-photo-box" id="photo-box">
            <?php if (!empty($c['photo_path'])): ?>
              <img src="<?= h(url('photo.php?cid=' . $user['id'])) ?>" alt="" class="profile-photo-preview" id="photo-preview">
              <button type="button" class="photo-remove-btn" id="photo-remove-btn" aria-label="写真を削除する" title="写真を削除する">&times;</button>
            <?php else: ?>
              <div class="profile-photo-placeholder" id="photo-placeholder">写真未設定</div>
            <?php endif; ?>
          </div>
          <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png">
          <p class="hint hint-tight">JPG・PNG、5MBまで</p>
          <?php if (!empty($c['photo_path'])): ?>
            <input type="hidden" id="remove_photo" name="remove_photo" value="0">
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="form-row">
      <label>対応エリア <span class="hint">（対面で会える都道府県。会員が比べやすいように、選んで登録します）</span></label>
      <p class="area-tools">
        <button type="button" class="btn btn-secondary btn-small" id="area-all">全国を選ぶ</button>
        <button type="button" class="btn btn-secondary btn-small" id="area-none">すべて解除</button>
      </p>
      <?php foreach (REGIONS as $region => $list): ?>
        <div class="area-region">
          <label class="area-region-name"><input type="checkbox" class="js-region-toggle"> <?= h($region) ?></label>
          <div class="radio-group">
            <?php foreach ($list as $pref): ?>
              <label><input type="checkbox" name="areas[]" value="<?= h($pref) ?>" <?= in_array($pref, $selectedAreas, true) ? 'checked' : '' ?>> <?= h($pref) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <p class="hint">「オンラインのみ」の場合は、選ばなくても構いません（全国として表示されます）。</p>
    </div>
    <div class="form-row">
      <label>面談の方法</label>
      <div class="radio-group">
        <?php foreach (SERVICE_STYLE_LABELS as $val => $label): ?>
          <label><input type="radio" name="service_style" value="<?= h($val) ?>" <?= $style === $val ? 'checked' : '' ?> required> <?= h($label) ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <h2>得意分野</h2>
    <div class="form-row">
      <label>得意な不安・悩み <span class="hint">（<?= CONCERN_TAGS_MAX ?>つまで）</span></label>
      <div class="radio-group js-limit-tags" data-max="<?= CONCERN_TAGS_MAX ?>">
        <?php foreach ($tagsByCat['concern'] as $id => $name): ?>
          <label><input type="checkbox" name="tags[]" value="<?= $id ?>" <?= in_array($id, $selectedTags, true) ? 'checked' : '' ?>> <?= h($name) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-row">
      <label>得意な年代 <span class="hint">（2つまで）</span></label>
      <div class="radio-group js-limit-tags" data-max="2">
        <?php foreach ($tagsByCat['age'] as $id => $name): ?>
          <label><input type="checkbox" name="tags[]" value="<?= $id ?>" <?= in_array($id, $selectedTags, true) ? 'checked' : '' ?>> <?= h($name) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-row">
      <label>得意な婚姻歴 <span class="hint">（いくつでも）</span></label>
      <div class="radio-group">
        <?php foreach ($tagsByCat['marital'] as $id => $name): ?>
          <label><input type="checkbox" name="tags[]" value="<?= $id ?>" <?= in_array($id, $selectedTags, true) ? 'checked' : '' ?>> <?= h($name) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-row">
      <label>提供できるサポート <span class="hint">（いくつでも）</span></label>
      <div class="radio-group">
        <?php foreach ($tagsByCat['service'] as $id => $name): ?>
          <label><input type="checkbox" name="tags[]" value="<?= $id ?>" <?= in_array($id, $selectedTags, true) ? 'checked' : '' ?>> <?= h($name) ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <h2>実績</h2>
    <div class="form-row">
      <label for="experience_years">カウンセラー経験年数 <span class="hint">（任意）</span></label>
      <input type="text" inputmode="numeric" id="experience_years" name="experience_years" value="<?= h((string)($c['experience_years'] ?? '')) ?>" style="max-width:8rem;"> 年
    </div>
    <div class="form-row">
      <label for="marriage_count">これまでの成婚数 <span class="hint">（任意）</span></label>
      <input type="text" inputmode="numeric" id="marriage_count" name="marriage_count" value="<?= h((string)($c['marriage_count'] ?? '')) ?>" style="max-width:8rem;"> 組
    </div>

    <h2>料金</h2>
    <div class="form-row">
      <label for="entry_fee">入会金</label>
      <input type="text" inputmode="numeric" id="entry_fee" name="entry_fee" value="<?= h((string)($c['entry_fee'] ?? '')) ?>" required style="max-width:12rem;"> 円
      <p class="hint">会員が契約した時点で、入会1件につき<?= number_format(REFERRAL_FEE_FLAT) ?>円を紹介手数料として当サービスに支払います。</p>
    </div>
    <div class="form-row">
      <label for="monthly_fee">月会費</label>
      <input type="text" inputmode="numeric" id="monthly_fee" name="monthly_fee" value="<?= h((string)($c['monthly_fee'] ?? '')) ?>" required style="max-width:12rem;"> 円
    </div>
    <div class="form-row">
      <label for="success_fee">成婚料</label>
      <input type="text" inputmode="numeric" id="success_fee" name="success_fee" value="<?= h((string)($c['success_fee'] ?? '')) ?>" required style="max-width:12rem;"> 円
      <p class="hint">かからない場合は 0 と入力してください。その他の料金は、会員が面談で確認します。</p>
    </div>

    <h2>担当・スカウト</h2>
    <div class="form-row">
      <label for="max_members">担当上限数 <span class="hint">（同時に担当できる会員の最大人数）</span></label>
      <input type="text" inputmode="numeric" id="max_members" name="max_members" value="<?= h((string)($c['max_members'] ?? '')) ?>" required style="max-width:8rem;"> 人
    </div>
    <div class="form-row">
      <label for="monthly_scout_limit">月のスカウト上限</label>
      <input type="text" inputmode="numeric" id="monthly_scout_limit" name="monthly_scout_limit" value="<?= h((string)($c['monthly_scout_limit'] ?? 20)) ?>" required style="max-width:8rem;"> 通
    </div>

    <h2>自己紹介</h2>
    <div class="form-row">
      <label for="bio">自己紹介・サポート方針 <span class="hint">（2000文字まで）</span></label>
      <textarea id="bio" name="bio" rows="7" maxlength="2000" required><?= h($c['bio'] ?? '') ?></textarea>
    </div>
    <div class="form-row">
      <label for="blog_url">ブログURL <span class="hint">（任意）</span></label>
      <input type="text" id="blog_url" name="blog_url" maxlength="255" placeholder="https://" value="<?= h($c['blog_url'] ?? '') ?>">
    </div>
    <div class="form-row">
      <label for="sns_url">SNSのURL <span class="hint">（任意）</span></label>
      <input type="text" id="sns_url" name="sns_url" maxlength="255" placeholder="https://" value="<?= h($c['sns_url'] ?? '') ?>">
    </div>

    <div class="form-row">
      <label><input type="checkbox" name="is_active" value="1" <?= !empty($c['is_active']) ? 'checked' : '' ?> <?= ($c['approval_status'] ?? '') !== 'approved' ? 'disabled' : '' ?>> このプロフィールを会員に公開する</label>
      <p class="hint hint-tight"><?= ($c['approval_status'] ?? '') === 'approved' ? 'チェックを外すと、会員の一覧に表示されません。' : '運営の確認が済むと、公開できるようになります。' ?></p>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn"><?= !empty($c['bio']) ? '上書き保存する' : '保存する' ?></button>
      <button type="submit" name="draft" value="1" formnovalidate class="btn btn-secondary">途中まで保存する（下書き）</button>
    </div>
    <p class="hint form-actions-note">まだそろっていない項目があるときは、途中まで保存を選択してください。下書きは公開されません。</p>
  </form>
</div>

<?php if (empty($c['is_representative'])): ?>
<div class="card">
  <h2>退会</h2>
  <p class="muted">退会すると、ログインできなくなり、プロフィールも非公開になります。相談所自体は継続します。</p>
  <form method="post" action="<?= h(url('counselor/profile.php')) ?>" id="withdraw-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="withdraw">
    <button type="submit" class="btn btn-secondary">退会する</button>
  </form>
</div>
<script>
document.getElementById('withdraw-form').addEventListener('submit', function (e) {
  if (!confirm('本当に退会しますか？この操作は取り消せません。')) {
    e.preventDefault();
  }
});
</script>
<?php else: ?>
<div class="card">
  <h2>退会</h2>
  <p class="muted">代表者の退会は、相談所ごとの退会になります。<a href="<?= h(url('counselor/agency.php')) ?>">相談所の情報</a>の画面から手続きしてください。</p>
</div>
<?php endif; ?>
<script>
(function () {
  // 対応エリア：全国／解除／地方ごとの一括選択
  var boxes = document.querySelectorAll('input[name="areas[]"]');
  function sync() {
    document.querySelectorAll('.area-region').forEach(function (r) {
      var all = r.querySelectorAll('input[name="areas[]"]');
      r.querySelector('.js-region-toggle').checked = Array.prototype.every.call(all, function (b) { return b.checked; });
    });
  }
  document.getElementById('area-all').addEventListener('click', function () { boxes.forEach(function (b) { b.checked = true; }); sync(); });
  document.getElementById('area-none').addEventListener('click', function () { boxes.forEach(function (b) { b.checked = false; }); sync(); });
  document.querySelectorAll('.js-region-toggle').forEach(function (t) {
    t.addEventListener('change', function () {
      t.closest('.area-region').querySelectorAll('input[name="areas[]"]').forEach(function (b) { b.checked = t.checked; });
    });
  });
  boxes.forEach(function (b) { b.addEventListener('change', sync); });
  sync();
})();
(function () {
  // 得意分野は3つまで、得意な年代は2つまで
  document.querySelectorAll('.js-limit-tags').forEach(function (box) {
    var max = parseInt(box.getAttribute('data-max'), 10);
    box.addEventListener('change', function (e) {
      if (box.querySelectorAll('input:checked').length > max) { e.target.checked = false; }
    });
  });
})();
(function () {
  // プロフィール写真：カーソルを合わせて出る×ボタンで削除する
  var btn = document.getElementById('photo-remove-btn');
  var box = document.getElementById('photo-box');
  var removeInput = document.getElementById('remove_photo');
  var photoInput = document.getElementById('photo');
  if (!btn || !box || !removeInput) { return; }
  btn.addEventListener('click', function () {
    removeInput.value = '1';
    box.innerHTML = '<div class="profile-photo-placeholder" id="photo-placeholder">写真未設定<br><span class="hint hint-tight">(保存すると削除されます)</span></div>';
  });
  // 削除マーク後に新しい写真を選び直したら、削除フラグは戻しておく（サーバー側は新しい写真を優先するが、表示もそろえる）
  if (photoInput) {
    photoInput.addEventListener('change', function () {
      if (photoInput.files && photoInput.files.length > 0) {
        removeInput.value = '0';
      }
    });
  }
})();

(function () {
  // プロフィール写真の左端を、ヘッダーの「会員検索」の左端に合わせる
  // （ヘッダーは画面幅いっぱい、本文は最大幅ありで中央寄せのため、両者の間隔は画面幅によって変わる。
  //   そのため固定の余白では合わず、実際の表示位置を測って毎回そろえている）
  var anchor = document.getElementById('nav-anchor-members');
  var row = document.querySelector('.profile-photo-row');
  var photoCol = document.querySelector('.profile-photo-row-photo');
  if (!anchor || !row || !photoCol) { return; }

  function alignPhotoColumn() {
    photoCol.style.marginLeft = '0px';
    var anchorRect = anchor.getBoundingClientRect();
    var rowRect = row.getBoundingClientRect();
    var photoRect = photoCol.getBoundingClientRect();
    var delta = anchorRect.left - photoRect.left;
    var maxDelta = rowRect.right - photoRect.right; // 対応エリアなど右側の枠からはみ出さないよう上限を設ける
    if (delta > maxDelta) { delta = maxDelta; }
    if (delta > 0) {
      photoCol.style.marginLeft = delta + 'px';
    }
  }

  alignPhotoColumn();
  var resizeTimer = null;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(alignPhotoColumn, 100);
  });
})();
</script>
<?php render_footer(); ?>
