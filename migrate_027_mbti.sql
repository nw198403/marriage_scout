-- migrate_027_mbti.sql
-- 会員・カウンセラー双方にMBTIタイプ（自己申告・任意）を追加
-- 実行前提：migrate_026_user_withdrawal.sql まで適用済みのDB
--
-- 診断ロジックや相性スコアの自動計算は持たず、タイプの表示のみ（事業計画書 8-7 参照）

ALTER TABLE members
  ADD COLUMN mbti_type ENUM('INTJ','INTP','ENTJ','ENTP','INFJ','INFP','ENFJ','ENFP',
                             'ISTJ','ISFJ','ESTJ','ESFJ','ISTP','ISFP','ESTP','ESFP')
    NULL AFTER gender;

ALTER TABLE counselors
  ADD COLUMN mbti_type ENUM('INTJ','INTP','ENTJ','ENTP','INFJ','INFP','ENFJ','ENFP',
                             'ISTJ','ISFJ','ESTJ','ESFJ','ISTP','ISFP','ESTP','ESFP')
    NULL AFTER display_name;
