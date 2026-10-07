<?php
// member/profile.php：会員プロフィールの入力・編集

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('member');
$pdo = db();

// 現在の登録内容
$stmt = $pdo->prepare('SELECT * FROM members WHERE user_id = ?');
$stmt->execute([$user['id']]);
$m = $stmt->fetch();

// 選べる「不安なこと」（category = concern）
$concernTags = $pdo->query("SELECT id, name FROM support_tags WHERE category = 'concern' ORDER BY sort_order")->fetchAll();
$validTagIds = array_map('intval', array_column($concernTags, 'id'));

$stmt = $pdo->prepare('SELECT tag_id FROM member_support_tags WHERE member_id = ?');
$stmt->execute([$user['id']]);
$selectedTags = array_map('intval', array_column($stmt->fetchAll(), 'tag_id'));

$errors = [];
$now = (int)date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'withdraw') {
        $pdo->prepare("UPDATE users SET status = 'withdrawn' WHERE id = ?")->execute([$user['id']]);
        logout_user();
        flash('ok', '退会しました。ご利用ありがとうございました。');
        redirect('index.php');
    }

    $in = [
        'nickname'           => trim((string)($_POST['nickname'] ?? '')),
        'gender'             => (string)($_POST['gender'] ?? ''),
        'mbti_type'          => (string)($_POST['mbti_type'] ?? ''),
        'birth_year'         => (int)($_POST['birth_year'] ?? 0),
        'birth_month'        => (int)($_POST['birth_month'] ?? 0),
        'prefecture'         => (string)($_POST['prefecture'] ?? ''),
        'marital_history'    => (string)($_POST['marital_history'] ?? ''),
        'intro'              => trim((string)($_POST['intro'] ?? '')),
        'desired_conditions' => trim((string)($_POST['desired_conditions'] ?? '')),
        'industry'           => (string)($_POST['industry'] ?? ''),
        'employment_type'    => (string)($_POST['employment_type'] ?? ''),
        'income_band'        => (string)($_POST['income_band'] ?? ''),
        'company_name'       => trim((string)($_POST['company_name'] ?? '')),
        'job_title'          => trim((string)($_POST['job_title'] ?? '')),
        'hobbies'            => trim((string)($_POST['hobbies'] ?? '')),
        'readiness'          => (string)($_POST['readiness'] ?? ''),
    ];
    $postedTags = array_map('intval', (array)($_POST['tags'] ?? []));

    if ($in['nickname'] === '' || mb_strlen($in['nickname']) > 50) {
        $errors[] = 'ニックネームを50文字以内で入力してください。';
    }
    if (!in_array($in['gender'], GENDER_OPTIONS, true)) {
        $errors[] = '性別を選んでください。';
    }
    if ($in['mbti_type'] !== '' && !in_array($in['mbti_type'], MBTI_TYPES, true)) {
        $errors[] = 'MBTIタイプの指定が正しくありません。';
    }
    if ($in['birth_year'] < $now - 80 || $in['birth_year'] > $now || $in['birth_month'] < 1 || $in['birth_month'] > 12) {
        $errors[] = '生まれた年と月を選んでください。';
    } elseif (calc_age($in['birth_year'], $in['birth_month']) < 18) {
        $errors[] = '18歳以上の方が登録できます。';
    }
    if (!in_array($in['prefecture'], PREFECTURES, true)) {
        $errors[] = '居住地（都道府県）を選んでください。';
    }
    if (!array_key_exists($in['marital_history'], MARITAL_LABELS)) {
        $errors[] = '婚姻歴を選んでください。';
    }
    if (!array_key_exists($in['readiness'], READINESS_LABELS)) {
        $errors[] = '婚活を始める時期を選んでください。';
    }
    if (!in_array($in['industry'], INDUSTRIES, true)) {
        $errors[] = '業種を選んでください。';
    }
    if (!in_array($in['employment_type'], EMPLOYMENT_TYPES, true)) {
        $errors[] = '雇用形態を選んでください。';
    }
    if (!array_key_exists((int)$in['income_band'], INCOME_BANDS)) {
        $errors[] = '年収帯を選んでください。';
    }
    foreach (['company_name' => ['会社名', 100], 'job_title' => ['役職', 100], 'hobbies' => ['趣味・休日の過ごし方', 255]] as $k => [$label, $max]) {
        if (mb_strlen($in[$k]) > $max) {
            $errors[] = "{$label}は{$max}文字以内で入力してください。";
        }
    }
    if (mb_strlen($in['intro']) > 1000) {
        $errors[] = '自己紹介は1000文字以内で入力してください。';
    }
    if (mb_strlen($in['desired_conditions']) > 1000) {
        $errors[] = '希望条件は1000文字以内で入力してください。';
    }
    // 存在するタグだけを採用（改ざん対策）
    $postedTags = array_values(array_intersect($postedTags, $validTagIds));

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                'UPDATE members SET nickname = ?, gender = ?, mbti_type = ?, birth_year = ?, birth_month = ?, prefecture = ?, marital_history = ?,
                        industry = ?, employment_type = ?, income_band = ?, company_name = ?, job_title = ?, hobbies = ?,
                        intro = ?, desired_conditions = ?, readiness = ?,
                        readiness_expires_at = DATE_ADD(NOW(), INTERVAL ' . READINESS_VALID_DAYS . ' DAY)
                 WHERE user_id = ?'
            )->execute([
                $in['nickname'], $in['gender'], $in['mbti_type'] === '' ? null : $in['mbti_type'], $in['birth_year'], $in['birth_month'], $in['prefecture'], $in['marital_history'],
                $in['industry'], $in['employment_type'], (int)$in['income_band'],
                $in['company_name'] === '' ? null : $in['company_name'],
                $in['job_title'] === '' ? null : $in['job_title'],
                $in['hobbies'] === '' ? null : $in['hobbies'],
                $in['intro'] === '' ? null : $in['intro'],
                $in['desired_conditions'] === '' ? null : $in['desired_conditions'],
                $in['readiness'], $user['id'],
            ]);

            $pdo->prepare('DELETE FROM member_support_tags WHERE member_id = ?')->execute([$user['id']]);
            $ins = $pdo->prepare('INSERT INTO member_support_tags (member_id, tag_id) VALUES (?, ?)');
            foreach ($postedTags as $tagId) {
                $ins->execute([$user['id'], $tagId]);
            }
            $pdo->commit();

            flash('ok', 'プロフィールを保存しました。');
            redirect('member/profile.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // エラー時は入力内容を残して再表示
    $m = array_merge($m, $in);
    $selectedTags = $postedTags;
}

$journeyBar = render_journey_bar(member_journey_step($pdo, $user['id']));
render_header('プロフィール', 'member', 'page-profile', $journeyBar);
?>
<div class="card">
  <h1>プロフィール</h1>
  <p class="muted">カウンセラーには、<span class="pub">*</span>がついた項目のみが表示されます。<br>会社名と役職は、あなたが面談を予約したカウンセラーにのみ表示されます。<br>
    <small>※氏名・メールアドレス・電話番号は表示されません。</small></p>

  <?php foreach ($errors as $e): ?>
    <div class="flash flash-error"><?= h($e) ?></div>
  <?php endforeach; ?>

  <form method="post" action="<?= h(url('member/profile.php')) ?>" class="profile-form">
    <?= csrf_field() ?>

    <div class="form-row-pair">
    <div class="form-row">
      <label for="nickname">ニックネーム <span class="pub">*</span></label>
      <input type="text" id="nickname" name="nickname" maxlength="50" value="<?= h($m['nickname'] ?? '') ?>" required>
    </div>

    <div class="form-row">
      <label for="gender">性別 <span class="pub">*</span></label>
      <select id="gender" name="gender" required>
        <option value="">選んでください</option>
        <?php foreach (GENDER_OPTIONS as $g): ?>
          <option value="<?= h($g) ?>" <?= ($m['gender'] ?? '') === $g ? 'selected' : '' ?>><?= h($g) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    </div>

    <div class="form-row-pair">
    <div class="form-row">
      <label for="mbti_type">MBTI <span class="pub">*</span> <span class="hint">（任意・自己申告）</span></label>
      <select id="mbti_type" name="mbti_type">
        <option value="">わからない・答えない</option>
        <?php foreach (MBTI_TYPES as $mb): ?>
          <option value="<?= h($mb) ?>" <?= ($m['mbti_type'] ?? '') === $mb ? 'selected' : '' ?>><?= h($mb) ?>（<?= h(MBTI_LABELS[$mb]) ?>）</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row form-row-spacer" aria-hidden="true"></div>
    </div>

    <div class="form-row-pair">
    <div class="form-row">
      <label for="birth_year">生まれた年 <span class="pub">*</span></label>
      <select id="birth_year" name="birth_year" required>
        <option value="">年</option>
        <?php for ($y = $now - 18; $y >= $now - 80; $y--): ?>
          <option value="<?= $y ?>" <?= (int)($m['birth_year'] ?? 0) === $y ? 'selected' : '' ?>><?= $y ?>年</option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="form-row">
      <label for="birth_month">生まれた月 <span class="pub">*</span></label>
      <select id="birth_month" name="birth_month" required>
        <option value="">月</option>
        <?php for ($mo = 1; $mo <= 12; $mo++): ?>
          <option value="<?= $mo ?>" <?= (int)($m['birth_month'] ?? 0) === $mo ? 'selected' : '' ?>><?= $mo ?>月</option>
        <?php endfor; ?>
      </select>
    </div>
    </div>

    <div class="form-row-pair">
    <div class="form-row">
      <label for="prefecture">居住地（都道府県） <span class="pub">*</span></label>
      <select id="prefecture" name="prefecture" required>
        <option value="">選んでください</option>
        <?php foreach (PREFECTURES as $p): ?>
          <option value="<?= h($p) ?>" <?= ($m['prefecture'] ?? '') === $p ? 'selected' : '' ?>><?= h($p) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row form-row-spacer" aria-hidden="true"></div>
    </div>

    <div class="form-row">
      <label>婚姻歴 <span class="pub">*</span></label>
      <div class="radio-group">
        <?php foreach (MARITAL_LABELS as $val => $label): ?>
          <label><input type="radio" name="marital_history" value="<?= h($val) ?>" <?= ($m['marital_history'] ?? '') === $val ? 'checked' : '' ?> required> <?= h($label) ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-row-pair">
    <div class="form-row">
      <label for="employment_type">雇用形態 <span class="pub">*</span></label>
      <select id="employment_type" name="employment_type" required>
        <option value="">選んでください</option>
        <?php foreach (EMPLOYMENT_TYPES as $e): ?>
          <option value="<?= h($e) ?>" <?= ($m['employment_type'] ?? '') === $e ? 'selected' : '' ?>><?= h($e) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="industry">業種 <span class="pub">*</span></label>
      <select id="industry" name="industry" required>
        <option value="">選んでください</option>
        <?php foreach (INDUSTRIES as $i): ?>
          <option value="<?= h($i) ?>" <?= ($m['industry'] ?? '') === $i ? 'selected' : '' ?>><?= h($i) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    </div>

    <div class="form-row-pair">
    <div class="form-row">
      <label for="income_band">年収 <span class="pub">*</span> <span class="hint hint-1line">（前年の源泉徴収票の金額を目安にお選びください）</span></label>
      <select id="income_band" name="income_band" required>
        <option value="">選んでください</option>
        <?php foreach (INCOME_BANDS as $k => $l): ?>
          <option value="<?= $k ?>" <?= (int)($m['income_band'] ?? 0) === $k ? 'selected' : '' ?>><?= h($l) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row form-row-spacer" aria-hidden="true"></div>
    </div>

    <div class="form-row-pair">
    <div class="form-row">
      <label for="company_name">会社名 <span class="hint">（任意）</span></label>
      <input type="text" id="company_name" name="company_name" maxlength="100" value="<?= h($m['company_name'] ?? '') ?>">
    </div>

    <div class="form-row">
      <label for="job_title">役職 <span class="hint">（任意）</span></label>
      <input type="text" id="job_title" name="job_title" maxlength="100" value="<?= h($m['job_title'] ?? '') ?>">
    </div>
    </div>

    <div class="form-row">
      <label>婚活を始める時期 <span class="pub">*</span> <span class="hint">（保存すると、この回答の有効期限が<?= READINESS_VALID_DAYS ?>日延びます）</span></label>
      <div class="radio-group">
        <?php foreach (READINESS_LABELS as $val => $label): ?>
          <label><input type="radio" name="readiness" value="<?= h($val) ?>" <?= ($m['readiness'] ?? '') === $val && !empty($m['gender']) ? 'checked' : '' ?> required> <?= h($label) ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-row">
      <label>不安なこと <span class="pub">*</span> <span class="hint">（いくつでも）</span></label>
      <div class="radio-group">
        <?php foreach ($concernTags as $t): ?>
          <label><input type="checkbox" name="tags[]" value="<?= (int)$t['id'] ?>" <?= in_array((int)$t['id'], $selectedTags, true) ? 'checked' : '' ?>> <?= h($t['name']) ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-row">
      <label for="hobbies">趣味・休日の過ごし方 <span class="pub">*</span> <span class="hint">（任意）</span></label>
      <input type="text" id="hobbies" name="hobbies" maxlength="255" value="<?= h($m['hobbies'] ?? '') ?>">
    </div>

    <div class="form-row">
      <label for="intro" class="label-normal">自己紹介 <span class="pub">*</span> <span class="hint">（1000文字まで）</span></label>
      <textarea id="intro" name="intro" rows="5" maxlength="1000" placeholder="例：IT関係の仕事をしています。休日はカフェ巡りや読書をして過ごすことが多いです。お互いを尊重しながら、将来を見据えたお付き合いができればと思っています。"><?= h($m['intro'] ?? '') ?></textarea>
    </div>

    <div class="form-row">
      <label for="desired_conditions" class="label-normal">希望条件・カウンセラーに求めること <span class="pub">*</span> <span class="hint">（1000文字まで）</span></label>
      <textarea id="desired_conditions" name="desired_conditions" rows="4" maxlength="1000"><?= h($m['desired_conditions'] ?? '') ?></textarea>
    </div>

    <button type="submit" class="btn btn-block"><?= !empty($m['gender']) ? '上書き保存する' : '保存する' ?></button>
  </form>
</div>

<div class="card">
  <h2>退会</h2>
  <p class="muted">退会すると、ログインできなくなり、カウンセラーからの検索・スカウト対象からも外れます。すでに届いているスカウトの記録は残ります。</p>
  <form method="post" action="<?= h(url('member/profile.php')) ?>" id="withdraw-form">
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
<?php render_footer(); ?>
