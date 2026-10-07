<?php
// includes/mail.php：メール送信
// 開発（DEV_MODE）では実際には送らず、確認URLを画面に表示する。
// 本番（さくら）では mb_send_mail() を使う。

require_once __DIR__ . '/helpers.php';

// 送信した（または送ったことにした）確認URLを返す。DEV_MODEのときだけ画面に出すために使う
function send_verify_mail(string $email, string $token): string
{
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    $verifyUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('verify.php?token=' . $token);

    if (!DEV_MODE) {
        mb_language('Japanese');
        mb_internal_encoding('UTF-8');
        $body = "メールアドレスの確認\n\n以下のURLを開くと登録が完了します（24時間有効）。\n\n" . $verifyUrl . "\n\n心当たりがない場合は、このメールを破棄してください。\n";
        @mb_send_mail($email, '【' . APP_NAME . '】メールアドレスの確認', $body, 'From: noreply@' . $_SERVER['SERVER_NAME']);
    }
    return $verifyUrl;
}

// 契約の確認メール。$role は 'counselor'（カウンセラー本人）または 'agency'（所属先の相談所）
// DEV_MODEでは実際には送らず、確認URLだけ返す（運営画面に表示する）
function send_contract_confirm_mail(string $to, string $role, array $c, string $token): string
{
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    $confirmUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('contract_confirm.php?t=' . $token);

    if (!DEV_MODE) {
        mb_language('Japanese');
        mb_internal_encoding('UTF-8');
        $who = $role === 'agency' ? '貴相談所' : 'ご担当のカウンセラー様';
        $body = "【" . APP_NAME . "】契約の確認のお願い\n\n"
              . "会員の「" . $c['nickname'] . "」さんから、{$who}との契約が成立したとの申告がありました。\n\n"
              . "  相談所　：" . $c['agency_name'] . "\n"
              . "  カウンセラー：" . $c['display_name'] . "\n"
              . "  契約日　：" . $c['contract_date'] . "\n"
              . "  入会金　：" . number_format((int)$c['entry_fee_snapshot']) . "円\n\n"
              . "内容に相違がなければ「契約を確認しました」を、契約の事実がない場合は「契約していません」を、以下のURLから選んでください。\n"
              . "回答は運営が確認のうえ、最終的に判断します。\n\n"
              . $confirmUrl . "\n\n（このURLは" . CONTRACT_TOKEN_DAYS . "日間有効です。心当たりがない場合は、このメールを破棄してください）\n";
        @mb_send_mail($to, '【' . APP_NAME . '】契約の確認のお願い', $body, 'From: noreply@' . $_SERVER['SERVER_NAME']);
    }
    return $confirmUrl;
}

// 初回面談の候補日程が届いたときに、会員宛に送る通知メール。
// $info には scout_id／agency_name／display_name（カウンセラー名）／count（今回追加した候補の件数）／note（カウンセラーからのメモ、任意）を渡す。
// DEV_MODEでは実際には送らず、確認用の詳細ページURLだけ返す（運営・カウンセラー画面に表示するため）
function send_interview_proposed_mail(string $to, array $info): string
{
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    $detailUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('member/scout.php?id=' . (int)$info['scout_id'] . '#interview');

    if (!DEV_MODE) {
        mb_language('Japanese');
        mb_internal_encoding('UTF-8');
        $body = "面談の候補日程が届きました。\n\n"
              . "  相談所　　　：" . $info['agency_name'] . "\n"
              . "  カウンセラー：" . $info['display_name'] . "\n"
              . "  候補の件数　：" . (int)$info['count'] . "件\n"
              . (!empty($info['note']) ? "  メモ　　　　：" . $info['note'] . "\n" : "")
              . "\nご都合のよい日時を、以下のURLからお選びください。\n\n" . $detailUrl . "\n";
        @mb_send_mail($to, '【' . APP_NAME . '】面談の候補日程が届きました', $body, 'From: noreply@' . $_SERVER['SERVER_NAME']);
    }
    return $detailUrl;
}

// 面談の日時が確定したときに、会員宛に送る通知メール。
// $info には scout_id／agency_name／display_name（カウンセラー名）／when（日時の表示文字列）／meeting_label（方法）／meeting_url（任意）を渡す。
// DEV_MODEでは実際には送らず、確認用の詳細ページURLだけ返す（運営・カウンセラー画面に表示するため）
function send_interview_booked_mail(string $to, array $info): string
{
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    $detailUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('member/scout.php?id=' . (int)$info['scout_id']);

    if (!DEV_MODE) {
        mb_language('Japanese');
        mb_internal_encoding('UTF-8');
        $body = "面談の日時が確定しました。\n\n"
              . "  相談所　　　：" . $info['agency_name'] . "\n"
              . "  カウンセラー：" . $info['display_name'] . "\n"
              . "  日時　　　　：" . $info['when'] . "\n"
              . "  方法　　　　：" . $info['meeting_label'] . "\n"
              . (!empty($info['meeting_url']) ? "  オンライン会議URL：" . $info['meeting_url'] . "\n" : "")
              . "\n詳しくは、以下のURLからご確認ください。\n\n" . $detailUrl . "\n";
        @mb_send_mail($to, '【' . APP_NAME . '】面談の日時が確定しました', $body, 'From: noreply@' . $_SERVER['SERVER_NAME']);
    }
    return $detailUrl;
}

// 契約成立（confirmed）になったときに、会員宛に送る「評価のお願い」メール。
// $info には contract_id／agency_name／display_name（カウンセラー名）を渡す。
// DEV_MODEでは実際には送らず、確認用のレビュー投稿ページURLだけ返す（運営画面に表示するため）
function send_review_request_mail(string $to, array $info): string
{
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    $reviewUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('member/review.php?contract_id=' . (int)$info['contract_id']);

    if (!DEV_MODE) {
        mb_language('Japanese');
        mb_internal_encoding('UTF-8');
        $body = "契約の成立が確認できました。\n\n"
              . "  相談所　　　：" . $info['agency_name'] . "\n"
              . "  カウンセラー：" . $info['display_name'] . "\n\n"
              . "今後の婚活を検討している方のために、" . $info['display_name'] . "さんへの評価にご協力ください（所要1分ほどです）。\n\n"
              . $reviewUrl . "\n";
        @mb_send_mail($to, '【' . APP_NAME . '】評価のお願い', $body, 'From: noreply@' . $_SERVER['SERVER_NAME']);
    }
    return $reviewUrl;
}
