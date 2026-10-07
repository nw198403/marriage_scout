-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- ホスト: localhost
-- 生成日時: 2026 年 10 月 07 日 15:08
-- サーバのバージョン： 10.4.28-MariaDB
-- PHP のバージョン: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `marriage_scout`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `agencies`
--

CREATE TABLE `agencies` (
  `id` int(10) UNSIGNED NOT NULL,
  `store_no` varchar(6) NOT NULL,
  `agency_name` varchar(100) NOT NULL,
  `agency_email` varchar(255) DEFAULT NULL,
  `affiliation` varchar(50) DEFAULT NULL,
  `affiliation_number` varchar(50) DEFAULT NULL,
  `affiliation_doc_path` varchar(255) DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `withdrawn_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `review_note` varchar(255) DEFAULT NULL,
  `invite_code` varchar(20) NOT NULL,
  `next_counselor_no` int(10) UNSIGNED NOT NULL DEFAULT 2,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `agency_seq`
--

CREATE TABLE `agency_seq` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `next_no` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `contracts`
--

CREATE TABLE `contracts` (
  `id` int(10) UNSIGNED NOT NULL,
  `scout_id` int(10) UNSIGNED NOT NULL,
  `member_id` int(10) UNSIGNED NOT NULL,
  `counselor_id` int(10) UNSIGNED NOT NULL,
  `contract_date` date NOT NULL,
  `entry_fee_snapshot` int(10) UNSIGNED NOT NULL,
  `referral_fee` int(10) UNSIGNED NOT NULL,
  `status` enum('reported','confirming','confirmed','rejected') NOT NULL DEFAULT 'reported',
  `reported_at` datetime NOT NULL DEFAULT current_timestamp(),
  `mails_sent_at` datetime DEFAULT NULL,
  `counselor_token` varchar(64) DEFAULT NULL,
  `agency_token` varchar(64) DEFAULT NULL,
  `counselor_response` enum('none','confirmed','denied') NOT NULL DEFAULT 'none',
  `counselor_responded_at` datetime DEFAULT NULL,
  `agency_response` enum('none','confirmed','denied') NOT NULL DEFAULT 'none',
  `agency_responded_at` datetime DEFAULT NULL,
  `admin_note` varchar(255) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `counselors`
--

CREATE TABLE `counselors` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `agency_id` int(10) UNSIGNED NOT NULL,
  `is_representative` tinyint(1) NOT NULL DEFAULT 0,
  `counselor_no` int(10) UNSIGNED NOT NULL,
  `display_name` varchar(50) NOT NULL,
  `mbti_type` enum('INTJ','INTP','ENTJ','ENTP','INFJ','INFP','ENFJ','ENFP','ISTJ','ISFJ','ESTJ','ESFJ','ISTP','ISFP','ESTP','ESFP') DEFAULT NULL,
  `prefecture` varchar(20) DEFAULT NULL,
  `service_style` enum('offline','online','both') NOT NULL DEFAULT 'both',
  `bio` text DEFAULT NULL,
  `blog_url` varchar(255) DEFAULT NULL,
  `sns_url` varchar(255) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `marriage_count` int(10) UNSIGNED DEFAULT NULL,
  `experience_years` tinyint(3) UNSIGNED DEFAULT NULL,
  `entry_fee` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `monthly_fee` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `success_fee` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `monthly_scout_limit` smallint(5) UNSIGNED NOT NULL DEFAULT 20,
  `current_members` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `max_members` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `counselor_areas`
--

CREATE TABLE `counselor_areas` (
  `counselor_id` int(10) UNSIGNED NOT NULL,
  `prefecture` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `counselor_support_tags`
--

CREATE TABLE `counselor_support_tags` (
  `counselor_id` int(10) UNSIGNED NOT NULL,
  `tag_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `events`
--

CREATE TABLE `events` (
  `id` int(10) UNSIGNED NOT NULL,
  `counselor_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `event_type` enum('seminar','consultation','qa','mini') NOT NULL,
  `starts_at` datetime NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `capacity` smallint(5) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `event_entries`
--

CREATE TABLE `event_entries` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `member_id` int(10) UNSIGNED NOT NULL,
  `entered_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `members`
--

CREATE TABLE `members` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `nickname` varchar(50) NOT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `mbti_type` enum('INTJ','INTP','ENTJ','ENTP','INFJ','INFP','ENFJ','ENFP','ISTJ','ISFJ','ESTJ','ESFJ','ISTP','ISFP','ESTP','ESFP') DEFAULT NULL,
  `birth_year` smallint(5) UNSIGNED DEFAULT NULL,
  `birth_month` tinyint(3) UNSIGNED DEFAULT NULL,
  `prefecture` varchar(20) DEFAULT NULL,
  `marital_history` enum('never','divorced','widowed') DEFAULT NULL,
  `industry` varchar(30) DEFAULT NULL,
  `employment_type` varchar(20) DEFAULT NULL,
  `income_band` tinyint(3) UNSIGNED DEFAULT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `hobbies` varchar(255) DEFAULT NULL,
  `intro` text DEFAULT NULL,
  `desired_conditions` text DEFAULT NULL,
  `readiness` enum('now','within3m','within6m','info') NOT NULL DEFAULT 'info',
  `readiness_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `member_support_tags`
--

CREATE TABLE `member_support_tags` (
  `member_id` int(10) UNSIGNED NOT NULL,
  `tag_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `reviews`
--

CREATE TABLE `reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `contract_id` int(10) UNSIGNED NOT NULL,
  `member_id` int(10) UNSIGNED NOT NULL,
  `counselor_id` int(10) UNSIGNED NOT NULL,
  `rating_comm` tinyint(3) UNSIGNED NOT NULL,
  `rating_response` tinyint(3) UNSIGNED NOT NULL,
  `rating_proposal` tinyint(3) UNSIGNED NOT NULL,
  `rating_empathy` tinyint(3) UNSIGNED NOT NULL,
  `rating_integrity` tinyint(3) UNSIGNED NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- テーブルの構造 `scouts`
--

CREATE TABLE `scouts` (
  `id` int(10) UNSIGNED NOT NULL,
  `counselor_id` int(10) UNSIGNED NOT NULL,
  `member_id` int(10) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `status` enum('sent','proposed','booked','met','contracted','closed') NOT NULL DEFAULT 'sent',
  `accepted_directly` tinyint(1) NOT NULL DEFAULT 0,
  `closed_reason` varchar(50) DEFAULT NULL,
  `proposed_slot_id` int(10) UNSIGNED DEFAULT NULL,
  `booked_slot_id` int(10) UNSIGNED DEFAULT NULL,
  `met_at` datetime DEFAULT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `scout_messages`
--

CREATE TABLE `scout_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `scout_id` int(10) UNSIGNED NOT NULL,
  `sender_role` enum('member','counselor') NOT NULL,
  `sender_user_id` int(10) UNSIGNED NOT NULL,
  `body` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_original` varchar(200) DEFAULT NULL,
  `attachment_mime` varchar(100) DEFAULT NULL,
  `attachment_size` int(10) UNSIGNED DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `edited_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `scout_slots`
--

CREATE TABLE `scout_slots` (
  `id` int(10) UNSIGNED NOT NULL,
  `scout_id` int(10) UNSIGNED NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `chosen_start_at` datetime DEFAULT NULL,
  `duration_min` smallint(5) UNSIGNED NOT NULL DEFAULT 60,
  `meeting_type` enum('online','offline','either') NOT NULL DEFAULT 'online',
  `location` varchar(150) DEFAULT NULL,
  `meeting_url` varchar(500) DEFAULT NULL,
  `counselor_note` varchar(300) DEFAULT NULL,
  `member_note` varchar(300) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `support_tags`
--

CREATE TABLE `support_tags` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `category` enum('concern','age','marital','service') NOT NULL DEFAULT 'concern',
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('member','counselor') NOT NULL,
  `status` enum('active','withdrawn') NOT NULL DEFAULT 'active',
  `email_verified_at` datetime DEFAULT NULL,
  `verify_token` varchar(64) DEFAULT NULL,
  `verify_token_expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `agencies`
--
ALTER TABLE `agencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_agencies_store_no` (`store_no`),
  ADD UNIQUE KEY `uq_agencies_invite_code` (`invite_code`);

--
-- テーブルのインデックス `agency_seq`
--
ALTER TABLE `agency_seq`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_contract_scout` (`scout_id`),
  ADD UNIQUE KEY `uq_contract_ctoken` (`counselor_token`),
  ADD UNIQUE KEY `uq_contract_atoken` (`agency_token`),
  ADD KEY `fk_contracts_member` (`member_id`),
  ADD KEY `fk_contracts_counselor` (`counselor_id`);

--
-- テーブルのインデックス `counselors`
--
ALTER TABLE `counselors`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_counselor_agency_no` (`agency_id`,`counselor_no`);

--
-- テーブルのインデックス `counselor_areas`
--
ALTER TABLE `counselor_areas`
  ADD PRIMARY KEY (`counselor_id`,`prefecture`);

--
-- テーブルのインデックス `counselor_support_tags`
--
ALTER TABLE `counselor_support_tags`
  ADD PRIMARY KEY (`counselor_id`,`tag_id`),
  ADD KEY `fk_cst_tag` (`tag_id`);

--
-- テーブルのインデックス `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_events_counselor` (`counselor_id`);

--
-- テーブルのインデックス `event_entries`
--
ALTER TABLE `event_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_event_entry` (`event_id`,`member_id`),
  ADD KEY `fk_ee_member` (`member_id`);

--
-- テーブルのインデックス `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`user_id`);

--
-- テーブルのインデックス `member_support_tags`
--
ALTER TABLE `member_support_tags`
  ADD PRIMARY KEY (`member_id`,`tag_id`),
  ADD KEY `fk_mst_tag` (`tag_id`);

--
-- テーブルのインデックス `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_review_contract` (`contract_id`),
  ADD KEY `idx_reviews_counselor` (`counselor_id`),
  ADD KEY `fk_reviews_member` (`member_id`);

--
-- テーブルのインデックス `scouts`
--
ALTER TABLE `scouts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_scout_pair` (`counselor_id`,`member_id`),
  ADD KEY `idx_scouts_member` (`member_id`),
  ADD KEY `fk_scouts_slot` (`booked_slot_id`);

--
-- テーブルのインデックス `scout_messages`
--
ALTER TABLE `scout_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scout_messages_scout` (`scout_id`,`created_at`);

--
-- テーブルのインデックス `scout_slots`
--
ALTER TABLE `scout_slots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_slots_scout` (`scout_id`);

--
-- テーブルのインデックス `support_tags`
--
ALTER TABLE `support_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_support_tags` (`category`,`name`);

--
-- テーブルのインデックス `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_verify_token` (`verify_token`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `agencies`
--
ALTER TABLE `agencies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `events`
--
ALTER TABLE `events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `event_entries`
--
ALTER TABLE `event_entries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `scouts`
--
ALTER TABLE `scouts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `scout_messages`
--
ALTER TABLE `scout_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `scout_slots`
--
ALTER TABLE `scout_slots`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `support_tags`
--
ALTER TABLE `support_tags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `fk_contracts_counselor` FOREIGN KEY (`counselor_id`) REFERENCES `counselors` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_contracts_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_contracts_scout` FOREIGN KEY (`scout_id`) REFERENCES `scouts` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `counselors`
--
ALTER TABLE `counselors`
  ADD CONSTRAINT `fk_counselors_agency` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_counselors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `counselor_areas`
--
ALTER TABLE `counselor_areas`
  ADD CONSTRAINT `fk_ca_counselor` FOREIGN KEY (`counselor_id`) REFERENCES `counselors` (`user_id`) ON DELETE CASCADE;

--
-- テーブルの制約 `counselor_support_tags`
--
ALTER TABLE `counselor_support_tags`
  ADD CONSTRAINT `fk_cst_counselor` FOREIGN KEY (`counselor_id`) REFERENCES `counselors` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cst_tag` FOREIGN KEY (`tag_id`) REFERENCES `support_tags` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_counselor` FOREIGN KEY (`counselor_id`) REFERENCES `counselors` (`user_id`) ON DELETE CASCADE;

--
-- テーブルの制約 `event_entries`
--
ALTER TABLE `event_entries`
  ADD CONSTRAINT `fk_ee_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ee_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`user_id`) ON DELETE CASCADE;

--
-- テーブルの制約 `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `fk_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `member_support_tags`
--
ALTER TABLE `member_support_tags`
  ADD CONSTRAINT `fk_mst_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mst_tag` FOREIGN KEY (`tag_id`) REFERENCES `support_tags` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_counselor` FOREIGN KEY (`counselor_id`) REFERENCES `counselors` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`user_id`) ON DELETE CASCADE;

--
-- テーブルの制約 `scouts`
--
ALTER TABLE `scouts`
  ADD CONSTRAINT `fk_scouts_counselor` FOREIGN KEY (`counselor_id`) REFERENCES `counselors` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_scouts_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_scouts_slot` FOREIGN KEY (`booked_slot_id`) REFERENCES `scout_slots` (`id`) ON DELETE SET NULL;

--
-- テーブルの制約 `scout_messages`
--
ALTER TABLE `scout_messages`
  ADD CONSTRAINT `fk_scout_messages_scout` FOREIGN KEY (`scout_id`) REFERENCES `scouts` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `scout_slots`
--
ALTER TABLE `scout_slots`
  ADD CONSTRAINT `fk_slots_scout` FOREIGN KEY (`scout_id`) REFERENCES `scouts` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
