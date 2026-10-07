-- migrate_028_drop_fee_rate.sql
-- 紹介手数料は定額（REFERRAL_FEE_FLAT、calc_referral_fee()）で計算しており、
-- contracts.fee_rate 列は実装上使われていない名残のため削除する

ALTER TABLE `contracts` DROP COLUMN `fee_rate`;
