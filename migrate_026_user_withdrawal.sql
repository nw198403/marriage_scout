-- migrate_026_user_withdrawal.sql
-- 退会機能（会員・カウンセラー・相談所）の追加
-- 実行前提：migrate_025_message_edit_delete.sql まで適用済みのDB

-- users：会員・カウンセラー共通の退会フラグ（active=通常 / withdrawn=退会済み。退会するとログイン不可）
ALTER TABLE users
  ADD COLUMN status ENUM('active','withdrawn') NOT NULL DEFAULT 'active' AFTER role;

-- agencies：相談所ごと退会したかどうかの記録（審査状況＝approval_statusとは別管理。運営画面の表示用）
ALTER TABLE agencies
  ADD COLUMN withdrawn_at DATETIME NULL AFTER approval_status;
