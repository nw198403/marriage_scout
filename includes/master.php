<?php
// includes/master.php：選択肢などの共通マスタ

const PREFECTURES = [
    '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
    '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
    '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県', '静岡県', '愛知県',
    '三重県', '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
    '鳥取県', '島根県', '岡山県', '広島県', '山口県',
    '徳島県', '香川県', '愛媛県', '高知県',
    '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
];

// 婚活を始める時期（本気度）
const READINESS_LABELS = [
    'now'     => 'すぐに婚活を始めたい',
    'within3m' => '2〜3ヶ月以内に始めたい',
    'within6m' => '6ヶ月以内に始めたい',
    'info'    => 'まずは情報収集したい',
];

const MARITAL_LABELS = [
    'never'    => '未婚（初婚）',
    'divorced' => '離婚歴あり',
    'widowed'  => '死別',
];

const GENDER_OPTIONS = ['女性', '男性', '回答しない'];

// MBTI（自己申告・任意。会員・カウンセラー共通。診断ロジックや相性スコアの自動計算は持たず、表示のみ）
const MBTI_TYPES = [
    'INTJ', 'INTP', 'ENTJ', 'ENTP',
    'INFJ', 'INFP', 'ENFJ', 'ENFP',
    'ISTJ', 'ISFJ', 'ESTJ', 'ESFJ',
    'ISTP', 'ISFP', 'ESTP', 'ESFP',
];
// 型コードだけだと分からない人もいるため、日本語の通称もあわせて表示する
const MBTI_LABELS = [
    'INTJ' => '建築家',   'INTP' => '論理学者', 'ENTJ' => '指揮官',   'ENTP' => '討論者',
    'INFJ' => '提唱者',   'INFP' => '仲介者',   'ENFJ' => '主人公',   'ENFP' => '広報運動家',
    'ISTJ' => '管理者',   'ISFJ' => '擁護者',   'ESTJ' => '幹部',     'ESFJ' => '領事官',
    'ISTP' => '巨匠',     'ISFP' => '冒険家',   'ESTP' => '起業家',   'ESFP' => 'エンターテイナー',
];

// 本気度の有効日数（期限が切れた会員は、カウンセラーの会員検索に出ない）
const READINESS_VALID_DAYS = 30;

// 生年月から満年齢を計算する（カウンセラー側の表示用。会員本人の画面には出さない）
function calc_age(int $year, int $month): int
{
    $age = (int)date('Y') - $year;
    if ((int)date('n') < $month) {
        $age--;
    }
    return $age;
}

// カウンセラー側の選択肢
const AFFILIATION_OPTIONS = [
    'IBJ（日本結婚相談所連盟）',
    'TMS（全国結婚相談事業者連盟）',
    'BIU（日本ブライダル連盟）',
    'JBA（日本結婚相談協会）',
    'その他',
    '無所属',
];
// 以前の表記で保存されている値を、新しい表記に読み替える
const AFFILIATION_LEGACY = [
    'IBJ'                  => 'IBJ（日本結婚相談所連盟）',
    'TMS'                  => 'TMS（全国結婚相談事業者連盟）',
    '日本結婚相談所連盟（JBA）' => 'JBA（日本結婚相談協会）',
];

const SERVICE_STYLE_LABELS = [
    'offline' => '対面のみ',
    'online'  => 'オンラインのみ',
    'both'    => '対面・オンライン両方',
];

// 入会金に対する紹介手数料率（契約成立時にカウンセラー側が支払う）
const REFERRAL_FEE_FLAT = 10000;   // 紹介手数料（入会1件につき・定額）

// スカウト
const SCOUT_VALID_DAYS = 14;   // 送信からの返答期限
const SCOUT_MESSAGE_MAX = 1000;

const SCOUT_STATUS_LABELS = [
    'sent'       => 'スカウト送信済み',
    'proposed'   => '日程提案あり',
    'booked'     => '面談予約済み',
    'met'        => '面談済み',
    'contracted' => '契約成立',
    'closed'     => '終了',
];

// 会員の業種・雇用形態・年収帯（一覧に出す情報。会社名・役職は面談を予約した相手にだけ見せる）
const INDUSTRIES = ['金融・保険', 'IT・通信', 'メーカー', '商社・卸売', '小売・流通・飲食', '建設・不動産', '医療・福祉',
                    '教育', '公務員・団体', '士業・コンサル', '運輸・物流', 'サービス', 'その他'];
// 業種（業界の分類）とは別に、働き方の分類として持つ項目
const EMPLOYMENT_TYPES = ['会社員', '会社役員', '公務員', '士業', '医師', '個人事業主', 'その他'];
const INCOME_BANDS = [
    1 => '300万円未満', 2 => '300〜400万円', 3 => '400〜500万円', 4 => '500〜600万円',
    5 => '600〜800万円', 6 => '800〜1000万円', 7 => '1000〜1200万円', 8 => '1200〜1500万円', 9 => '1500万円以上',
];
// 会社名・役職を見せてよいスカウトの状態（会員が面談を予約したあと）
const COMPANY_VISIBLE_STATUSES = ['booked', 'met', 'contracted'];

// 会員検索の年代（満年齢）
const AGE_BANDS = [
    '20' => ['20代', 20, 29],
    '30' => ['30代', 30, 39],
    '40' => ['40代', 40, 49],
    '50' => ['50代〜', 50, 120],
];

// 会員検索の対象条件（SQLの共通部分）：本気度の期限内・プロフィール入力済み
// 「まずは情報収集したい」の会員も、良い条件であればスカウトしたいカウンセラーがいるため検索対象に含める（9/24〜）
const SEARCHABLE_MEMBER_SQL = "m.readiness_expires_at > NOW()
    AND m.gender IS NOT NULL AND m.birth_year IS NOT NULL AND m.birth_month IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM users u WHERE u.id = m.user_id AND u.status = 'withdrawn')";
const MEMBER_AGE_SQL = "TIMESTAMPDIFF(YEAR, STR_TO_DATE(CONCAT(m.birth_year, '-', m.birth_month, '-01'), '%Y-%m-%d'), CURDATE())";

// 担当者ID（運営の画面・会員には出さない内部表示用。店番AA0001＋相談所内の通し番号00001 → "AA0001-00001"）
function counselor_display_id(string $storeNo, int $counselorNo): string
{
    return $storeNo . '-' . str_pad((string)$counselorNo, 5, '0', STR_PAD_LEFT);
}

// 店番（AA0001のように、アルファベット2桁＋数字4桁。約676万件まで採番できる）
// agency_seq テーブルの通し番号（1, 2, 3…）から変換する
function store_no_from_seq(int $n): string
{
    $n0 = max(0, $n - 1);
    $letterIdx = intdiv($n0, 10000);
    $digits = $n0 % 10000 + 1;
    $l1 = chr(65 + intdiv($letterIdx, 26) % 26);
    $l2 = chr(65 + $letterIdx % 26);
    return $l1 . $l2 . str_pad((string)$digits, 4, '0', STR_PAD_LEFT);
}

// 次の店番を払い出す（行ロックして採番するので、同時登録でも重複しない）。トランザクション内で呼ぶこと
function next_store_no(PDO $pdo): string
{
    $stmt = $pdo->prepare('SELECT next_no FROM agency_seq WHERE id = 1 FOR UPDATE');
    $stmt->execute();
    $n = (int)$stmt->fetchColumn();
    $pdo->prepare('UPDATE agency_seq SET next_no = next_no + 1 WHERE id = 1')->execute();
    return store_no_from_seq($n);
}

// 相談所への招待コード（担当者を追加するときに使う。紛らわしい文字を避けて8桁）
const INVITE_CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
function gen_invite_code(): string
{
    $s = '';
    for ($i = 0; $i < 8; $i++) {
        $s .= INVITE_CODE_CHARS[random_int(0, strlen(INVITE_CODE_CHARS) - 1)];
    }
    return $s;
}

// 契約の申告と確認（会員が「契約した」と申告 → 運営が、カウンセラーと相談所にメールで確認 → 運営が確定）
const CONTRACT_STATUS_LABELS = [
    'reported'   => '申告を受け付けました',
    'confirming' => '運営が確認中です',
    'confirmed'  => '契約成立',
    'rejected'   => '確認できませんでした',
];
const CONTRACT_RESPONSE_LABELS = ['none' => '未回答', 'confirmed' => '契約を確認', 'denied' => '否認'];
const CONTRACT_TOKEN_DAYS = 30;   // 確認メールのURLの有効期限

// 紹介手数料＝契約した時点の入会金×手数料率（円未満は四捨五入）
function calc_referral_fee(): int
{
    return REFERRAL_FEE_FLAT;
}

// カウンセラーの掲載審査（連盟の加盟番号・書類を運営が確認して承認する）
const APPROVAL_LABELS = [
    'pending'  => '確認待ち',
    'approved' => '承認済み',
    'rejected' => '確認できませんでした',
];

// 会員側から見たスカウトの状態（期限切れは表示時に判定する）
function scout_display_status(array $s): string
{
    if ($s['status'] === 'sent' && $s['expires_at'] !== null && strtotime($s['expires_at']) < time()) {
        return '期限切れ';
    }
    if ($s['status'] === 'closed') {
        return $s['closed_reason'] === 'member_declined' ? '辞退しました' : '終了';
    }
    return SCOUT_STATUS_LABELS[$s['status']] ?? $s['status'];
}

// 面談
const INTERVIEW_LIMIT_PER_COUNSELOR = 3;   // 1人のカウンセラーが同時に受けられる面談予約（面談前）の数
const SLOT_MAX_PER_SCOUT = 5;              // 1つのスカウトに出せる候補日時の数
const SLOT_DURATIONS = [30 => '30分', 60 => '60分', 90 => '90分'];

// 面談の方法（候補ごとに選べる。対面を含む場合は場所を自由記述で入力できる）
const MEETING_TYPE_LABELS = ['online' => 'オンライン', 'offline' => '対面', 'either' => 'どちらでも'];
const SLOT_LOCATION_MAX = 150;
const SLOT_NOTE_MAX = 300;   // カウンセラー・会員それぞれの自由記述メモの上限
const SLOT_MEETING_URL_MAX = 500;   // オンライン会議URLの文字数上限（Google Meet・Zoom・Teamsなど、特定のサービスに限定しない）

// 面談候補時刻の選択肢（00:00〜23:30、30分刻み）。日付とは別に、プルダウンで選ばせる
function time_slot_options(): array
{
    $opts = [];
    for ($m = 0; $m < 24 * 60; $m += 30) {
        $opts[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }
    return $opts;
}

// 面談の方法・場所の表示文字列（例："オンライン" "対面：渋谷駅前のカフェ" "オンライン/対面（渋谷駅前のカフェ）"）。h()でエスケープして使うこと
function slot_meeting_label(array $slot): string
{
    $type = (string)($slot['meeting_type'] ?? 'online');
    $loc = trim((string)($slot['location'] ?? ''));
    if ($type === 'offline') {
        return $loc === '' ? '対面' : '対面：' . $loc;
    }
    if ($type === 'either') {
        return $loc === '' ? 'オンライン/対面' : 'オンライン/対面（' . $loc . '）';
    }
    return 'オンライン';
}

// 候補の時間帯の表示文字列（例："2026年9月24日（木）14:00〜18:00"）
function fmt_slot_range(array $slot): string
{
    return fmt_dt($slot['starts_at']) . '〜' . date('H:i', strtotime($slot['ends_at']));
}

function fmt_dt(string $dt): string
{
    $w = ['日', '月', '火', '水', '木', '金', '土'][(int)date('w', strtotime($dt))];
    return date('Y年n月j日', strtotime($dt)) . "（{$w}）" . date('H:i', strtotime($dt));
}

// 会員が候補の時間帯の中から選べる、具体的な開始時刻の選択肢（30分刻み）。
// 所要時間ぶんが時間帯に収まる範囲までを選択肢にする（例：10:00〜18:00・60分なら、10:00〜17:00までの開始時刻）
function slot_time_choice_options(array $slot): array
{
    $start = strtotime($slot['starts_at']);
    $end = strtotime($slot['ends_at']);
    $dur = (int)$slot['duration_min'] * 60;
    $latest = max($start, $end - $dur);   // 所要時間が時間帯より長い場合は、開始時刻を1つだけにする
    $opts = [];
    for ($t = $start; $t <= $latest; $t += 1800) {
        $opts[] = date('H:i', $t);
    }
    if (!$opts) {
        $opts[] = date('H:i', $start);
    }
    return $opts;
}

// 確定した面談時刻の表示文字列。会員が具体的な開始時刻を選んでいれば「開始〜終了」、まだなら時間帯全体を表示する
function fmt_chosen_time(array $slot): string
{
    if (empty($slot['chosen_start_at'])) {
        return fmt_slot_range($slot);
    }
    $start = strtotime($slot['chosen_start_at']);
    $end = $start + (int)$slot['duration_min'] * 60;
    return fmt_dt($slot['chosen_start_at']) . '〜' . date('H:i', $end);
}

// iCalendar（.ics）形式のイベントを1件組み立てる（Googleカレンダー・Outlook・Appleカレンダーなど、特定のサービスに限らず使える汎用形式）
// $startDt は「YYYY-MM-DD HH:MM:SS」形式（日本時間として扱う）
function build_ics(string $uid, string $summary, string $description, string $location, string $startDt, int $durationMin): string
{
    $tz = new DateTimeZone('Asia/Tokyo');
    $start = new DateTime($startDt, $tz);
    $end = (clone $start)->modify('+' . $durationMin . ' minutes');
    $start->setTimezone(new DateTimeZone('UTC'));
    $end->setTimezone(new DateTimeZone('UTC'));
    $esc = function (string $s): string {
        return str_replace(["\\", "\n", ",", ";"], ["\\\\", "\\n", "\\,", "\\;"], $s);
    };
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//婚活スカウト//JP',
        'CALSCALE:GREGORIAN',
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . $start->format('Ymd\THis\Z'),
        'DTEND:' . $end->format('Ymd\THis\Z'),
        'SUMMARY:' . $esc($summary),
    ];
    if ($description !== '') {
        $lines[] = 'DESCRIPTION:' . $esc($description);
    }
    if ($location !== '') {
        $lines[] = 'LOCATION:' . $esc($location);
    }
    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';
    return implode("\r\n", $lines) . "\r\n";
}

// 対応エリア（都道府県を、地方ごとにまとめて選べるようにする）
const REGIONS = [
    '北海道・東北' => ['北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県'],
    '関東'         => ['茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県'],
    '中部'         => ['新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県', '静岡県', '愛知県'],
    '近畿'         => ['三重県', '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県'],
    '中国・四国'   => ['鳥取県', '島根県', '岡山県', '広島県', '山口県', '徳島県', '香川県', '愛媛県', '高知県'],
    '九州・沖縄'   => ['福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'],
];

// 対応エリアの表示用文字列。地方の全県を選んでいたら「関東全域」のようにまとめる
function format_areas(array $prefs, string $style = 'both'): string
{
    if (!$prefs) {
        return $style === 'online' ? '全国（オンライン）' : '—';
    }
    if (count(array_intersect($prefs, PREFECTURES)) >= count(PREFECTURES)) {
        return '全国';
    }
    $parts = [];
    foreach (REGIONS as $region => $list) {
        $have = array_values(array_intersect($list, $prefs));
        if (count($have) === count($list)) {
            $parts[] = $region . '全域';
        } else {
            foreach ($have as $p) {
                $parts[] = $p;
            }
        }
    }
    return implode('、', $parts);
}

// カウンセラーごとの対応エリア（都道府県の配列）をまとめて取得する。[counselor_id => [都道府県...]]
function load_counselor_areas(PDO $pdo, array $counselorIds): array
{
    $map = [];
    $ids = array_values(array_unique(array_map('intval', $counselorIds)));
    if (!$ids) {
        return $map;
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT counselor_id, prefecture FROM counselor_areas WHERE counselor_id IN ($in)");
    $st->execute($ids);
    foreach ($st->fetchAll() as $r) {
        $map[(int)$r['counselor_id']][] = $r['prefecture'];
    }
    return $map;
}

// カウンセラーが選べる得意分野の数（全部選ぶと強みが伝わらないため）
const CONCERN_TAGS_MAX = 3;

// レビュー・星評価（契約成立（confirmed）した会員のみ、1契約につき1件）
// 5項目×5段階で評価し、その平均を星評価として扱う
const REVIEW_LABELS = [
    'rating_comm'      => 'コミュニケーション',
    'rating_response'  => 'レスポンス・丁寧さ',
    'rating_proposal'  => '分析力・提案力',
    'rating_empathy'   => '共感・伴走スタンス',
    'rating_integrity' => '誠実さ・営業感',
];
// 各項目の観察するポイント（レビュー投稿フォームで、項目名の下に補足として表示する）
const REVIEW_HINTS = [
    'rating_comm'      => '話しやすさ、傾聴力、会話のテンポ・心地よさ',
    'rating_response'  => '連絡の早さ、確認事項への回答の正確さ',
    'rating_proposal'  => '自分の属性・希望に合わせた具体的な戦略提示',
    'rating_empathy'   => '自分の価値観への理解、個別の寄り添い感',
    'rating_integrity' => '強引な勧誘の有無、説明の透明性・客観性',
];
const REVIEW_COMMENT_MAX = 500;

// 1件のレビューの平均点（REVIEW_LABELSの各項目の平均。小数第1位まで）
function review_avg(array $r): float
{
    $keys = array_keys(REVIEW_LABELS);
    $sum = 0;
    foreach ($keys as $k) {
        $sum += (int)$r[$k];
    }
    return round($sum / count($keys), 1);
}

// 星を★☆で表示する（平均は四捨五入して星の数にする。数値・件数も添える）
function star_html(float $avg, ?int $count = null): string
{
    $full = max(0, min(5, (int)round($avg)));
    $stars = str_repeat('★', $full) . str_repeat('☆', 5 - $full);
    $out = '<span class="stars" aria-hidden="true">' . $stars . '</span> <span class="stars-num">' . number_format($avg, 1) . '</span>';
    if ($count !== null) {
        $out .= ' <span class="stars-count muted">（' . (int)$count . '件）</span>';
    }
    return $out;
}

// カウンセラーごとの評価まとめ（[counselor_id => ['avg' => float, 'count' => int]]）。レビューが無いカウンセラーはキーが存在しない
function counselor_rating_summary(PDO $pdo, array $counselorIds): array
{
    $map = [];
    $ids = array_values(array_unique(array_map('intval', $counselorIds)));
    if (!$ids) {
        return $map;
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $keys = array_keys(REVIEW_LABELS);
    $sumExpr = implode(' + ', $keys);
    $st = $pdo->prepare(
        "SELECT counselor_id, COUNT(*) AS cnt, AVG(($sumExpr) / " . count($keys) . ") AS avg_rating
         FROM reviews WHERE counselor_id IN ($in) GROUP BY counselor_id"
    );
    $st->execute($ids);
    foreach ($st->fetchAll() as $r) {
        $map[(int)$r['counselor_id']] = ['avg' => round((float)$r['avg_rating'], 1), 'count' => (int)$r['cnt']];
    }
    return $map;
}

// --- メッセージ（チャット） -------------------------------------------------
// メンバーがスカウトに反応した（status が sent から進んだ）後だけ、メッセージのやり取りができる
const CHAT_ELIGIBLE_STATUSES = ['proposed', 'booked', 'met', 'contracted'];
const CHAT_MESSAGE_MAX = 2000;

function chat_eligible(array $scout): bool
{
    return in_array($scout['status'], CHAT_ELIGIBLE_STATUSES, true);
}

// 自分（$role）宛ての、相手からの未読メッセージ件数
// 1件のスカウトについて、相手からの未読メッセージ件数を返す（スカウト詳細ページの軽いリンク表示に使う）
function unread_message_count_for_scout(PDO $pdo, int $scoutId, string $myRole): int
{
    $otherRole = $myRole === 'member' ? 'counselor' : 'member';
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM scout_messages WHERE scout_id = ? AND sender_role = ? AND read_at IS NULL AND deleted_at IS NULL');
    $stmt->execute([$scoutId, $otherRole]);
    return (int)$stmt->fetchColumn();
}

function unread_message_count(PDO $pdo, int $userId, string $role): int
{
    $col = $role === 'member' ? 'member_id' : 'counselor_id';
    $otherRole = $role === 'member' ? 'counselor' : 'member';
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM scout_messages sm JOIN scouts s ON s.id = sm.scout_id
         WHERE s.$col = ? AND sm.sender_role = ? AND sm.read_at IS NULL AND sm.deleted_at IS NULL"
    );
    $stmt->execute([$userId, $otherRole]);
    return (int)$stmt->fetchColumn();
}

// バイト数を「1.2MB」のような表示用文字列にする
function format_file_size(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1) . 'MB';
    }
    if ($bytes < 1024) {
        return '1KB未満';
    }
    return number_format($bytes / 1024, 0) . 'KB';
}

// スカウトのメッセージ一覧（チャットとして古い順）を返し、あわせて相手からの未読を既読にする
function load_scout_messages(PDO $pdo, int $scoutId, string $myRole): array
{
    $stmt = $pdo->prepare('SELECT * FROM scout_messages WHERE scout_id = ? ORDER BY created_at, id');
    $stmt->execute([$scoutId]);
    $messages = $stmt->fetchAll();

    $otherRole = $myRole === 'member' ? 'counselor' : 'member';
    $pdo->prepare("UPDATE scout_messages SET read_at = NOW() WHERE scout_id = ? AND sender_role = ? AND read_at IS NULL")
        ->execute([$scoutId, $otherRole]);

    return $messages;
}

// チャットの吹き出しの間に挟む日付の見出し（LINEのように「今日」「昨日」「9月20日（日）」）
function chat_date_label(string $dt): string
{
    $day = date('Y-m-d', strtotime($dt));
    if ($day === date('Y-m-d')) {
        return '今日';
    }
    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return '昨日';
    }
    $w = ['日', '月', '火', '水', '木', '金', '土'][(int)date('w', strtotime($dt))];
    return date('n月j日', strtotime($dt)) . "（{$w}）";
}

// メッセージ一覧（スレッド一覧）の右側に出す、簡潔な日時表示（今日はH:i、昨日は「昨日」、2〜6日前は「n日前」、それより前はn月j日）
function chat_list_time(string $dt): string
{
    $day = date('Y-m-d', strtotime($dt));
    $today = date('Y-m-d');
    if ($day === $today) {
        return date('H:i', strtotime($dt));
    }
    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return '昨日';
    }
    $diffDays = (int)round((strtotime($today) - strtotime($day)) / 86400);
    if ($diffDays >= 2 && $diffDays <= 6) {
        return $diffDays . '日前';
    }
    return date('n月j日', strtotime($dt));
}

// スカウトの行の配列（各行に id＝scout_idを含む）に、チャットの最新メッセージのプレビュー・日時・未読件数を付け加えて返す。
// メッセージがまだなければ last_message_time／last_message_preview はnullのまま返す（呼び出し側でスカウト送信日時などにフォールバックする）
function attach_message_summaries(PDO $pdo, array $rows, string $myRole): array
{
    if (!$rows) {
        return $rows;
    }
    $ids = array_column($rows, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $otherRole = $myRole === 'member' ? 'counselor' : 'member';

    $stmt = $pdo->prepare("SELECT * FROM scout_messages WHERE scout_id IN ($in) AND deleted_at IS NULL ORDER BY created_at, id");
    $stmt->execute($ids);
    $bySid = [];
    foreach ($stmt->fetchAll() as $m) {
        $bySid[(int)$m['scout_id']][] = $m;
    }

    foreach ($rows as &$r) {
        $sid = (int)$r['id'];
        $msgs = $bySid[$sid] ?? [];
        $last = $msgs ? end($msgs) : null;
        if ($last) {
            $r['last_message_time'] = $last['created_at'];
            if ($last['body']) {
                $r['last_message_preview'] = mb_strimwidth(str_replace(["\r", "\n"], ' ', $last['body']), 0, 40, '…');
            } elseif ($last['attachment_original']) {
                $r['last_message_preview'] = '📎 ' . $last['attachment_original'];
            } else {
                $r['last_message_preview'] = '';
            }
        } else {
            $r['last_message_time'] = null;
            $r['last_message_preview'] = null;
        }
        $unread = 0;
        foreach ($msgs as $m) {
            if ($m['sender_role'] === $otherRole && $m['read_at'] === null) {
                $unread++;
            }
        }
        $r['unread_count'] = $unread;
    }
    unset($r);

    return $rows;
}

// 会員の婚活の進み具合（プロフィール登録／スカウトを比較／面談して決定）を判定する。
// HOW IT WORKSの3ステップと対応させた、ざっくりした目安表示用。
const MEMBER_JOURNEY_STEPS = [
    1 => 'プロフィール登録',
    2 => 'スカウトを比較',
    3 => '面談して決定',
];

function member_journey_step(PDO $pdo, int $memberUserId): int
{
    $stmt = $pdo->prepare('SELECT status FROM scouts WHERE member_id = ?');
    $stmt->execute([$memberUserId]);
    $statuses = array_column($stmt->fetchAll(), 'status');

    if (!$statuses) {
        return 1;
    }
    foreach ($statuses as $st) {
        if (in_array($st, ['booked', 'met', 'contracted'], true)) {
            return 3;
        }
    }
    return 2;
}

// カウンセラーの進み具合（プロフィール登録／会員にスカウト／面談・契約）を判定する。
const COUNSELOR_JOURNEY_STEPS = [
    1 => 'プロフィール登録',
    2 => '会員にスカウト',
    3 => '面談・契約',
];

function counselor_journey_step(PDO $pdo, int $counselorUserId): int
{
    $stmt = $pdo->prepare(
        'SELECT c.bio, c.is_active, a.approval_status
         FROM counselors c JOIN agencies a ON a.id = c.agency_id
         WHERE c.user_id = ?'
    );
    $stmt->execute([$counselorUserId]);
    $c = $stmt->fetch();

    // プロフィール未入力、または未承認・非公開の間は、引き続き「プロフィール登録」の段階として扱う
    if (!$c || empty($c['bio']) || $c['approval_status'] !== 'approved' || empty($c['is_active'])) {
        return 1;
    }

    $stmt = $pdo->prepare('SELECT status FROM scouts WHERE counselor_id = ?');
    $stmt->execute([$counselorUserId]);
    $statuses = array_column($stmt->fetchAll(), 'status');
    foreach ($statuses as $st) {
        if (in_array($st, ['booked', 'met', 'contracted'], true)) {
            return 3;
        }
    }
    return 2;
}

// ヘッダー直下（preMain）に出す、画面幅いっぱいのステップバーのHTMLを組み立てる。
function render_journey_bar(int $current, array $steps = MEMBER_JOURNEY_STEPS): string
{
    ob_start();
    ?>
<div class="journey-bar">
  <div class="journey-bar-inner">
    <?php foreach ($steps as $n => $label): ?>
      <div class="journey-step<?= $n === $current ? ' is-current' : ($n < $current ? ' is-done' : '') ?>">
        <span class="journey-step-num"><?= $n < $current ? '✓' : $n ?></span>
        <span class="journey-step-label"><?= h($label) ?></span>
      </div>
      <?php if ($n < count($steps)): ?><span class="journey-step-arrow">→</span><?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
    <?php
    return ob_get_clean();
}
