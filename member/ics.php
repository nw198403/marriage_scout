<?php
// member/ics.php：確定した面談をカレンダーに追加できるよう、iCalendar（.ics）ファイルをダウンロードする

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/master.php';

$user = require_login('member');
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT s.id, s.booked_slot_id, c.display_name, a.agency_name
     FROM scouts s JOIN counselors c ON c.user_id = s.counselor_id JOIN agencies a ON a.id = c.agency_id
     WHERE s.id = ? AND s.member_id = ? AND s.status = 'booked'"
);
$stmt->execute([$id, $user['id']]);
$s = $stmt->fetch();
if (!$s || !$s['booked_slot_id']) {
    http_response_code(404);
    exit('予約が見つかりません。');
}
$stmt = $pdo->prepare('SELECT * FROM scout_slots WHERE id = ? AND scout_id = ?');
$stmt->execute([(int)$s['booked_slot_id'], $id]);
$slot = $stmt->fetch();
if (!$slot) {
    http_response_code(404);
    exit('予約が見つかりません。');
}

$startDt = $slot['chosen_start_at'] ?: $slot['starts_at'];
$summary = '面談：' . $s['display_name'] . 'さん（' . $s['agency_name'] . '）';
$descParts = [MEETING_TYPE_LABELS[$slot['meeting_type']] ?? ''];
if ($slot['meeting_url']) {
    $descParts[] = 'オンライン会議URL：' . $slot['meeting_url'];
}
$description = implode("\n", array_filter($descParts));
$location = (string)($slot['location'] ?? '');

$ics = build_ics('scout-' . $id . '-member@marriage-scout.local', $summary, $description, $location, $startDt, (int)$slot['duration_min']);

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="interview_' . $id . '.ics"');
echo $ics;
