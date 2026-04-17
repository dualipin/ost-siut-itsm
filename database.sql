-- Auto-generated from test_sindicato_db via mcp_dbhub_execute_sql on 2026-04-13
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `auth_logs`;
CREATE TABLE `auth_logs` (
  `auth_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `error_message` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`auth_id`),
  KEY `idx_usuario_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `auth_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `box_closings`;
CREATE TABLE `box_closings` (
  `closing_id` int NOT NULL AUTO_INCREMENT,
  `box_id` int NOT NULL,
  `closed_by` int NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `expected_balance` decimal(14,2) NOT NULL,
  `actual_balance` decimal(14,2) NOT NULL,
  `difference` decimal(14,2) GENERATED ALWAYS AS ((`actual_balance` - `expected_balance`)) STORED,
  `total_income` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total_expense` decimal(14,2) NOT NULL DEFAULT '0.00',
  `notes` text,
  `closed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`closing_id`),
  KEY `fk_closing_user` (`closed_by`),
  KEY `idx_closing_box` (`box_id`),
  KEY `idx_closing_period` (`period_start`,`period_end`),
  CONSTRAINT `fk_closing_box` FOREIGN KEY (`box_id`) REFERENCES `cash_boxes` (`box_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_closing_user` FOREIGN KEY (`closed_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `box_transaction_attachments`;
CREATE TABLE `box_transaction_attachments` (
  `attachment_id` int NOT NULL AUTO_INCREMENT,
  `transaction_id` int NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attachment_id`),
  KEY `idx_transaction` (`transaction_id`),
  CONSTRAINT `fk_attachment_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `box_transactions` (`transaction_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `box_transactions`;
CREATE TABLE `box_transactions` (
  `transaction_id` int NOT NULL AUTO_INCREMENT,
  `box_id` int NOT NULL,
  `category_id` int NOT NULL,
  `contributor_user_id` int DEFAULT NULL,
  `created_by` int NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `balance_before` decimal(14,2) NOT NULL,
  `balance_after` decimal(14,2) NOT NULL,
  `description` text,
  `transaction_date` date NOT NULL DEFAULT (curdate()),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`transaction_id`),
  KEY `fk_transaction_user` (`created_by`),
  KEY `idx_box_id` (`box_id`),
  KEY `idx_type` (`type`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_category` (`category_id`),
  KEY `idx_contributor_user_id` (`contributor_user_id`),
  CONSTRAINT `fk_transaction_box` FOREIGN KEY (`box_id`) REFERENCES `cash_boxes` (`box_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transaction_category` FOREIGN KEY (`category_id`) REFERENCES `transaction_categories` (`category_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transaction_contributor` FOREIGN KEY (`contributor_user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transaction_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `box_transactions_chk_1` CHECK ((`amount` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `box_transfers`;
CREATE TABLE `box_transfers` (
  `transfer_id` int NOT NULL AUTO_INCREMENT,
  `source_box_id` int NOT NULL,
  `destination_box_id` int NOT NULL,
  `created_by` int NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `source_balance_before` decimal(14,2) NOT NULL,
  `source_balance_after` decimal(14,2) NOT NULL,
  `destination_balance_before` decimal(14,2) NOT NULL,
  `destination_balance_after` decimal(14,2) NOT NULL,
  `notes` text,
  `transferred_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`transfer_id`),
  KEY `fk_transfer_user` (`created_by`),
  KEY `idx_source_box` (`source_box_id`),
  KEY `idx_destination_box` (`destination_box_id`),
  KEY `idx_transferred_at` (`transferred_at`),
  CONSTRAINT `fk_transfer_destination` FOREIGN KEY (`destination_box_id`) REFERENCES `cash_boxes` (`box_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transfer_source` FOREIGN KEY (`source_box_id`) REFERENCES `cash_boxes` (`box_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transfer_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `box_transfers_chk_1` CHECK ((`amount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `box_user_access`;
CREATE TABLE `box_user_access` (
  `access_id` int NOT NULL AUTO_INCREMENT,
  `box_id` int NOT NULL,
  `user_id` int NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'operator',
  `granted_by` int NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `granted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`access_id`),
  UNIQUE KEY `uq_box_user` (`box_id`,`user_id`),
  KEY `fk_access_grantor` (`granted_by`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_active` (`active`),
  CONSTRAINT `fk_access_box` FOREIGN KEY (`box_id`) REFERENCES `cash_boxes` (`box_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_access_grantor` FOREIGN KEY (`granted_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_access_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `cash_boxes`;
CREATE TABLE `cash_boxes` (
  `box_id` int NOT NULL AUTO_INCREMENT,
  `created_by` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'MXN',
  `initial_balance` decimal(14,2) DEFAULT '0.00',
  `current_balance` decimal(14,2) DEFAULT '0.00',
  `status` varchar(25) NOT NULL DEFAULT 'open',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`box_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_box_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `cat_income_types`;
CREATE TABLE `cat_income_types` (
  `income_type_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `is_periodic` tinyint(1) DEFAULT '0',
  `frequency_days` int DEFAULT NULL,
  `tentative_payment_month` int DEFAULT NULL,
  `tentative_payment_day` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`income_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `financial_reports`;
CREATE TABLE `financial_reports` (
  `report_id` int NOT NULL AUTO_INCREMENT,
  `box_id` int DEFAULT NULL,
  `generated_by` int NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `summary_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_id`),
  KEY `idx_financial_report_period` (`period_start`,`period_end`),
  KEY `idx_financial_report_box` (`box_id`),
  KEY `idx_financial_report_user` (`generated_by`),
  CONSTRAINT `fk_financial_report_box` FOREIGN KEY (`box_id`) REFERENCES `cash_boxes` (`box_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_financial_report_user` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `loan_amortization`;
CREATE TABLE `loan_amortization` (
  `amortization_id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `payment_number` int NOT NULL,
  `income_type_id` int NOT NULL,
  `scheduled_date` date NOT NULL,
  `initial_balance` decimal(10,2) NOT NULL,
  `principal` decimal(10,2) NOT NULL,
  `ordinary_interest` decimal(10,2) NOT NULL,
  `total_scheduled_payment` decimal(10,2) NOT NULL,
  `final_balance` decimal(10,2) NOT NULL,
  `payment_status` varchar(30) NOT NULL DEFAULT 'pendiente',
  `actual_payment_date` datetime DEFAULT NULL,
  `actual_paid_amount` decimal(10,2) DEFAULT '0.00',
  `days_overdue` int DEFAULT '0',
  `generated_default_interest` decimal(10,2) DEFAULT '0.00',
  `paid_by` int DEFAULT NULL,
  `payment_receipt` varchar(255) DEFAULT NULL,
  `table_version` int DEFAULT '1',
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`amortization_id`),
  KEY `fk_amort_income_type` (`income_type_id`),
  KEY `idx_loan_number` (`loan_id`,`payment_number`),
  KEY `idx_date_status` (`scheduled_date`,`payment_status`),
  KEY `idx_version_active` (`loan_id`,`table_version`,`active`),
  CONSTRAINT `fk_amort_income_type` FOREIGN KEY (`income_type_id`) REFERENCES `cat_income_types` (`income_type_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_amort_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`loan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `loan_events`;
CREATE TABLE `loan_events` (
  `event_id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `event_date` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`),
  KEY `idx_loan_date` (`loan_id`,`event_date`),
  CONSTRAINT `loan_events_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`loan_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `loan_extraordinary_payments`;
CREATE TABLE `loan_extraordinary_payments` (
  `extraordinary_payment_id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `payment_type` varchar(30) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `applied_to_principal` decimal(10,2) DEFAULT NULL,
  `applied_to_interest` decimal(10,2) DEFAULT NULL,
  `applied_to_default` decimal(10,2) DEFAULT NULL,
  `regenerated_amortization_table` tinyint(1) DEFAULT '1',
  `generated_table_version` int DEFAULT NULL,
  `observations` text,
  `payment_receipt` varchar(255) DEFAULT NULL,
  `registered_by` int DEFAULT NULL,
  PRIMARY KEY (`extraordinary_payment_id`),
  KEY `idx_loan_date` (`loan_id`,`payment_date`),
  KEY `idx_type` (`payment_type`),
  CONSTRAINT `fk_extra_payment_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`loan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `loan_legal_documents`;
CREATE TABLE `loan_legal_documents` (
  `legal_doc_id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `version` int DEFAULT '1',
  `requires_user_signature` tinyint(1) DEFAULT '0',
  `user_signature_url` varchar(255) DEFAULT NULL,
  `user_signature_date` datetime DEFAULT NULL,
  `requires_finance_validation` tinyint(1) DEFAULT '0',
  `validated_by_finance` tinyint(1) DEFAULT '0',
  `validated_by` int DEFAULT NULL,
  `validation_date` datetime DEFAULT NULL,
  `validation_observations` text,
  `generation_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `generated_by` int DEFAULT NULL,
  PRIMARY KEY (`legal_doc_id`),
  KEY `idx_loan_type` (`loan_id`,`document_type`),
  KEY `idx_pending_signature` (`requires_user_signature`,`user_signature_date`),
  CONSTRAINT `fk_legal_doc_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`loan_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `loan_payment_configuration`;
CREATE TABLE `loan_payment_configuration` (
  `payment_config_id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `income_type_id` int NOT NULL,
  `total_amount_to_deduct` decimal(10,2) NOT NULL,
  `number_of_installments` int DEFAULT '1',
  `amount_per_installment` decimal(10,2) DEFAULT NULL,
  `interest_method` varchar(20) NOT NULL DEFAULT 'simple_aleman',
  `supporting_document_path` varchar(255) DEFAULT NULL,
  `document_status` varchar(30) NOT NULL DEFAULT 'pendiente',
  `document_observations` text,
  `document_validation_date` datetime DEFAULT NULL,
  PRIMARY KEY (`payment_config_id`),
  KEY `idx_loan` (`loan_id`),
  KEY `idx_income_type` (`income_type_id`),
  CONSTRAINT `fk_config_income_type` FOREIGN KEY (`income_type_id`) REFERENCES `cat_income_types` (`income_type_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_config_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`loan_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `loan_receipts`;
CREATE TABLE `loan_receipts` (
  `receipt_id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `amortization_id` int DEFAULT NULL,
  `receipt_type` varchar(30) NOT NULL,
  `receipt_folio` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text,
  `issue_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `pdf_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`receipt_id`),
  UNIQUE KEY `receipt_folio` (`receipt_folio`),
  KEY `fk_receipt_amortization` (`amortization_id`),
  KEY `idx_folio` (`receipt_folio`),
  KEY `idx_loan_date` (`loan_id`,`issue_date`),
  CONSTRAINT `fk_receipt_amortization` FOREIGN KEY (`amortization_id`) REFERENCES `loan_amortization` (`amortization_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_receipt_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`loan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `loan_restructurings`;
CREATE TABLE `loan_restructurings` (
  `restructuring_id` int NOT NULL AUTO_INCREMENT,
  `original_loan_id` int NOT NULL,
  `new_loan_id` int NOT NULL,
  `reason` varchar(35) NOT NULL,
  `original_outstanding_balance` decimal(10,2) NOT NULL,
  `pending_interest` decimal(10,2) NOT NULL,
  `pending_default_interest` decimal(10,2) NOT NULL,
  `new_total_amount` decimal(10,2) NOT NULL,
  `new_interest_rate` decimal(5,2) DEFAULT NULL,
  `new_term_fortnights` int DEFAULT NULL,
  `restructuring_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `authorized_by` int DEFAULT NULL,
  `observations` text,
  PRIMARY KEY (`restructuring_id`),
  KEY `idx_original` (`original_loan_id`),
  KEY `idx_new` (`new_loan_id`),
  KEY `idx_date` (`restructuring_date`),
  CONSTRAINT `fk_restruct_new` FOREIGN KEY (`new_loan_id`) REFERENCES `loans` (`loan_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_restruct_original` FOREIGN KEY (`original_loan_id`) REFERENCES `loans` (`loan_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `loans`;
CREATE TABLE `loans` (
  `loan_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `folio` varchar(50) DEFAULT NULL,
  `requested_amount` decimal(10,2) NOT NULL,
  `approved_amount` decimal(10,2) DEFAULT NULL,
  `applied_interest_rate` decimal(5,2) NOT NULL,
  `daily_default_rate` decimal(5,4) DEFAULT NULL,
  `estimated_total_to_pay` decimal(10,2) DEFAULT NULL,
  `outstanding_balance` decimal(10,2) DEFAULT NULL,
  `term_months` int DEFAULT NULL,
  `term_fortnights` int DEFAULT NULL,
  `first_payment_date` date DEFAULT NULL,
  `last_scheduled_payment_date` date DEFAULT NULL,
  `application_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `document_review_date` datetime DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `document_generation_date` datetime DEFAULT NULL,
  `signature_validation_date` datetime DEFAULT NULL,
  `disbursement_date` datetime DEFAULT NULL,
  `total_liquidation_date` datetime DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'borrador',
  `original_loan_id` int DEFAULT NULL,
  `rejection_reason` text,
  `admin_observations` text,
  `internal_observations` text,
  `finance_signatory` varchar(255) DEFAULT NULL,
  `lender_signatory` varchar(255) DEFAULT NULL,
  `requires_restructuring` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `deletion_date` datetime DEFAULT NULL,
  PRIMARY KEY (`loan_id`),
  UNIQUE KEY `folio` (`folio`),
  KEY `idx_folio` (`folio`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_status_date` (`status`,`application_date`),
  KEY `idx_origin` (`original_loan_id`),
  CONSTRAINT `fk_loan_origin` FOREIGN KEY (`original_loan_id`) REFERENCES `loans` (`loan_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_loan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `mail_queue`;
CREATE TABLE `mail_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `recipient` text NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` mediumtext NOT NULL,
  `alt_body` text,
  `priority` tinyint DEFAULT '2',
  `attempts` int DEFAULT '0',
  `max_attempts` int DEFAULT '3',
  `status` enum('pending','sending','sent','failed') DEFAULT 'pending',
  `last_error` text,
  `scheduled_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `locked_at` timestamp NULL DEFAULT NULL,
  `lock_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`,`priority`,`scheduled_at`),
  KEY `idx_mail_queue_status_lock_token` (`status`,`lock_token`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `message_attachments`;
CREATE TABLE `message_attachments` (
  `attachment_id` int NOT NULL AUTO_INCREMENT,
  `message_id` int NOT NULL,
  `file_path` varchar(512) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attachment_id`),
  KEY `idx_att_message` (`message_id`),
  CONSTRAINT `fk_att_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`message_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `message_threads`;
CREATE TABLE `message_threads` (
  `thread_id` int NOT NULL AUTO_INCREMENT,
  `thread_type` varchar(20) NOT NULL,
  `sender_id` int DEFAULT NULL,
  `external_name` varchar(255) DEFAULT NULL,
  `external_email` varchar(255) DEFAULT NULL,
  `external_phone` varchar(50) DEFAULT NULL,
  `recipient_id` int DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `visibility` varchar(20) NOT NULL DEFAULT 'private',
  `assigned_to` int DEFAULT NULL,
  `external_channel` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`thread_id`),
  KEY `fk_mt_assigned` (`assigned_to`),
  KEY `idx_mt_type` (`thread_type`),
  KEY `idx_mt_status` (`status`),
  KEY `idx_mt_visibility` (`visibility`),
  KEY `idx_mt_sender` (`sender_id`),
  KEY `idx_mt_recipient` (`recipient_id`),
  CONSTRAINT `fk_mt_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mt_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mt_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `message_id` int NOT NULL AUTO_INCREMENT,
  `thread_id` int NOT NULL,
  `sender_id` int DEFAULT NULL,
  `body` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `idx_msg_thread` (`thread_id`),
  KEY `idx_msg_sender` (`sender_id`),
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_msg_thread` FOREIGN KEY (`thread_id`) REFERENCES `message_threads` (`thread_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` binary(16) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `email` (`email`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `publication_attachments`;
CREATE TABLE `publication_attachments` (
  `attachment_id` int NOT NULL AUTO_INCREMENT,
  `publication_id` int NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `attachment_type` varchar(25) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`attachment_id`),
  KEY `idx_publication` (`publication_id`),
  CONSTRAINT `fk_attachment_publication` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`publication_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `publications`;
CREATE TABLE `publications` (
  `publication_id` int NOT NULL AUTO_INCREMENT,
  `author_id` int DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `summary` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `publication_type` varchar(30) NOT NULL,
  `expiration_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `thumbnail_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`publication_id`),
  KEY `fk_publication_author` (`author_id`),
  KEY `idx_type_date` (`publication_type`,`created_at`),
  KEY `idx_expiration` (`expiration_date`),
  CONSTRAINT `fk_publication_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `request_attachments`;
CREATE TABLE `request_attachments` (
  `attachment_id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attachment_id`),
  KEY `idx_request` (`request_id`),
  CONSTRAINT `fk_ra_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `request_status_history`;
CREATE TABLE `request_status_history` (
  `history_id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `changed_by` int DEFAULT NULL,
  `status_from` varchar(30) DEFAULT NULL,
  `status_to` varchar(30) NOT NULL,
  `notes` text,
  `changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `fk_rsh_user` (`changed_by`),
  KEY `idx_request` (`request_id`),
  KEY `idx_changed_at` (`changed_at`),
  CONSTRAINT `fk_rsh_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rsh_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `request_types`;
CREATE TABLE `request_types` (
  `request_type_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_type_id`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `requests`;
CREATE TABLE `requests` (
  `request_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `request_type_id` int NOT NULL,
  `folio` varchar(50) DEFAULT NULL,
  `reason` text NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pendiente',
  `admin_notes` text,
  `resolved_by` int DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `folio` (`folio`),
  KEY `fk_request_type` (`request_type_id`),
  KEY `fk_request_resolver` (`resolved_by`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_status_date` (`status`,`created_at`),
  KEY `idx_folio` (`folio`),
  CONSTRAINT `fk_request_resolver` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_request_type` FOREIGN KEY (`request_type_id`) REFERENCES `request_types` (`request_type_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `saver_users`;
CREATE TABLE `saver_users` (
  `saver_user_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `enrollment_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`saver_user_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_user_active` (`user_id`,`active`),
  CONSTRAINT `saver_users_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sodexo_encuesta`;
CREATE TABLE `sodexo_encuesta` (
  `encuesta_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `tipo_empleado` varchar(20) NOT NULL COMMENT 'administrativo o docente',
  `adm_dic_puntualidad` decimal(6,2) DEFAULT NULL COMMENT 'Dic 2025 puntualidad',
  `adm_dic_asistencia` decimal(6,2) DEFAULT NULL COMMENT 'Dic 2025 asistencia',
  `adm_ene_puntualidad` decimal(6,2) DEFAULT NULL COMMENT 'Ene 2026 puntualidad',
  `adm_ene_asistencia` decimal(6,2) DEFAULT NULL COMMENT 'Ene 2026 asistencia',
  `adm_feb_puntualidad` decimal(6,2) DEFAULT NULL COMMENT 'Feb 2026 puntualidad',
  `adm_feb_asistencia` decimal(6,2) DEFAULT NULL COMMENT 'Feb 2026 asistencia',
  `adm_mar_puntualidad` decimal(6,2) DEFAULT NULL COMMENT 'Mar 2026 puntualidad',
  `adm_mar_asistencia` decimal(6,2) DEFAULT NULL COMMENT 'Mar 2026 asistencia',
  `adm_dic_recibo` varchar(255) DEFAULT NULL COMMENT 'Recibo Dic 2025',
  `adm_ene_recibo` varchar(255) DEFAULT NULL COMMENT 'Recibo Ene 2026',
  `adm_feb_recibo` varchar(255) DEFAULT NULL COMMENT 'Recibo Feb 2026',
  `adm_mar_recibo` varchar(255) DEFAULT NULL COMMENT 'Recibo Mar 2026',
  `doc_dic_pagado` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Dic 2025 pagado (100)',
  `doc_mar_pagado` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Mar 2026 pagado (100)',
  `doc_dic_recibo` varchar(255) DEFAULT NULL COMMENT 'Recibo Dic 2025',
  `doc_mar_recibo` varchar(255) DEFAULT NULL COMMENT 'Recibo Mar 2026',
  `firma_curp` varchar(20) DEFAULT NULL COMMENT 'CURP del agremiado (firma electrónica)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`encuesta_id`),
  UNIQUE KEY `unique_sodexo_user` (`user_id`),
  KEY `idx_tipo_empleado` (`tipo_empleado`),
  CONSTRAINT `fk_sodexo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `system_colors`;
CREATE TABLE `system_colors` (
  `id` int NOT NULL DEFAULT '1',
  `c_primary` varchar(7) DEFAULT '#611232',
  `c_secondary` varchar(7) DEFAULT '#a57f2c',
  `c_success` varchar(7) DEFAULT '#38b44a',
  `c_info` varchar(7) DEFAULT '#17a2b8',
  `c_warning` varchar(7) DEFAULT '#efb73e',
  `c_danger` varchar(7) DEFAULT '#df382c',
  `c_light` varchar(7) DEFAULT '#e9ecef',
  `c_dark` varchar(7) DEFAULT '#002f2a',
  `c_white` varchar(7) DEFAULT '#ffffff',
  `c_body` varchar(7) DEFAULT '#212529',
  `c_body_background` varchar(7) DEFAULT '#f8f9fa',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `transaction_categories`;
CREATE TABLE `transaction_categories` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `contribution_category` varchar(50) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  KEY `idx_type` (`type`),
  KEY `idx_active` (`active`),
  KEY `idx_contribution_category` (`contribution_category`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `transparency`;
CREATE TABLE `transparency` (
  `transparency_id` int NOT NULL AUTO_INCREMENT,
  `author_id` int DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `summary` varchar(255) DEFAULT NULL,
  `transparency_type` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_published` date NOT NULL,
  `is_private` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`transparency_id`),
  KEY `fk_transparency_author` (`author_id`),
  KEY `idx_type_date` (`transparency_type`,`date_published`),
  KEY `idx_expiration` (`date_published`),
  CONSTRAINT `fk_transparency_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `transparency_attachments`;
CREATE TABLE `transparency_attachments` (
  `attachment_id` int NOT NULL AUTO_INCREMENT,
  `transparency_id` int NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `attachment_type` varchar(25) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`attachment_id`),
  KEY `idx_transparency` (`transparency_id`),
  CONSTRAINT `fk_attachment_transparency` FOREIGN KEY (`transparency_id`) REFERENCES `transparency` (`transparency_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `transparency_permissions`;
CREATE TABLE `transparency_permissions` (
  `permission_id` int NOT NULL AUTO_INCREMENT,
  `transparency_id` int NOT NULL,
  `user_id` int NOT NULL,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `uq_transparency_user` (`transparency_id`,`user_id`),
  KEY `idx_transparency` (`transparency_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_permission_transparency` FOREIGN KEY (`transparency_id`) REFERENCES `transparency` (`transparency_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_permission_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `user_documents`;
CREATE TABLE `user_documents` (
  `document_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pendiente',
  `observation` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `validated_by` int DEFAULT NULL,
  PRIMARY KEY (`document_id`),
  KEY `idx_user_document_type` (`user_id`,`document_type`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_document_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'no_agremiado',
  `active` tinyint(1) DEFAULT '1',
  `curp` varchar(20) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `surnames` varchar(255) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `interbank_code` varchar(18) DEFAULT NULL,
  `bank_account` varchar(20) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `nss` varchar(15) DEFAULT NULL,
  `salary` decimal(12,2) DEFAULT '0.00',
  `work_start_date` date DEFAULT NULL,
  `last_entry` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `update_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `delete_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;
