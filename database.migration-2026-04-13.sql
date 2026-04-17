-- Structural migration generated on 2026-04-13
-- Source comparison:
-- - Current schema: database.sql
-- - Production schema: ostitsm1_database_test.sql
--
-- Goal:
-- - Add missing structures only
-- - Do not touch data
-- - Keep compatibility with shared-hosting MySQL/MariaDB environments
--
-- NOTE:
-- Production uses MyISAM/latin1 in most tables. To avoid foreign key failures
-- against MyISAM parent tables, this migration intentionally creates the missing
-- tables without foreign keys.

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ---------------------------------------------------------------------------
-- Missing table: loan_events
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `loan_events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `loan_id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`event_id`),
  KEY `idx_loan_date` (`loan_id`,`event_date`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Hardening in case the table exists but is incomplete.
ALTER TABLE `loan_events`
  ADD COLUMN IF NOT EXISTS `loan_id` int(11) NOT NULL,
  ADD COLUMN IF NOT EXISTS `event_type` varchar(50) NOT NULL,
  ADD COLUMN IF NOT EXISTS `description` text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `event_date` datetime DEFAULT current_timestamp();

ALTER TABLE `loan_events`
  ADD INDEX IF NOT EXISTS `idx_loan_date` (`loan_id`, `event_date`);

-- ---------------------------------------------------------------------------
-- Missing table: saver_users
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `saver_users` (
  `saver_user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `enrollment_date` datetime DEFAULT current_timestamp(),
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`saver_user_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_user_active` (`user_id`,`active`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Hardening in case the table exists but is incomplete.
ALTER TABLE `saver_users`
  ADD COLUMN IF NOT EXISTS `user_id` int(11) NOT NULL,
  ADD COLUMN IF NOT EXISTS `enrollment_date` datetime DEFAULT current_timestamp(),
  ADD COLUMN IF NOT EXISTS `active` tinyint(1) DEFAULT 1;

ALTER TABLE `saver_users`
  ADD UNIQUE INDEX IF NOT EXISTS `user_id` (`user_id`),
  ADD INDEX IF NOT EXISTS `idx_user_active` (`user_id`, `active`);

-- End of migration.