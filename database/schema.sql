-- Fresh-install baseline: current application tables only, with no user or request data.
-- Seed documents and email templates separately after importing this file.
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `academic_years` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `start_year` smallint(5) unsigned NOT NULL,
  `end_year` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_academic_years_range` (`start_year`,`end_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `courses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_courses_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `processing_min_days` smallint(5) unsigned NOT NULL DEFAULT 0,
  `processing_max_days` smallint(5) unsigned NOT NULL DEFAULT 0,
  `release_methods` varchar(50) NOT NULL DEFAULT 'Hardcopy',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documents_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_requirements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `ai_hint` text DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `requires_name` tinyint(1) NOT NULL DEFAULT 1,
  `allowed_types` varchar(50) NOT NULL DEFAULT 'pdf,jpg,jpeg,png',
  `max_size_mb` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_req_document` (`document_id`),
  CONSTRAINT `fk_req_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rate_limits` (
  `bucket` varchar(120) NOT NULL,
  `window_start` datetime NOT NULL,
  `hits` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `request_deletion_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tracking_no` varchar(30) NOT NULL,
  `document_name` varchar(150) NOT NULL,
  `final_status` varchar(30) NOT NULL,
  `requested_at` datetime NOT NULL,
  `deleted_at` datetime NOT NULL,
  `deleted_by_name` varchar(150) NOT NULL,
  `status_history` longtext NOT NULL,
  `purged_at` datetime DEFAULT NULL,
  `restored_at` datetime DEFAULT NULL,
  `restored_by_name` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_request_deletion_audit_tracking` (`tracking_no`),
  KEY `ix_request_deletion_audit_deleted` (`deleted_at`),
  KEY `ix_request_deletion_audit_purged` (`purged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `school_levels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_levels_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `role` enum('student','registrar','admin') NOT NULL,
  `student_no` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_approved` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_student_no` (`student_no`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `personnel_password_reset_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_personnel_reset_user_created` (`user_id`,`created_at`),
  KEY `ix_personnel_reset_expiration` (`expires_at`,`consumed_at`),
  CONSTRAINT `fk_personnel_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` int(10) unsigned DEFAULT NULL,
  `actor_name` varchar(150) NOT NULL,
  `action` varchar(64) NOT NULL,
  `entity_type` varchar(64) NOT NULL,
  `entity_id` varchar(64) DEFAULT NULL,
  `summary` varchar(500) NOT NULL,
  `details_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_audit_events_created` (`created_at`,`id`),
  KEY `ix_audit_events_actor` (`actor_user_id`,`created_at`),
  KEY `ix_audit_events_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `fk_audit_events_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `academic_terms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `academic_year_id` bigint(20) unsigned NOT NULL,
  `semester` tinyint(3) unsigned NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_academic_terms_year_semester` (`academic_year_id`,`semester`),
  KEY `ix_academic_terms_current` (`is_current`),
  CONSTRAINT `fk_academic_terms_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_templates` (
  `template_key` varchar(40) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`template_key`),
  KEY `fk_email_template_editor` (`updated_by`),
  CONSTRAINT `fk_email_template_editor` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `school_courses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `level_id` int(10) unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `acronym` varchar(20) NOT NULL DEFAULT '',
  `duration_years` tinyint(3) unsigned NOT NULL DEFAULT 4,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_school_courses_level_acronym_name` (`level_id`,`acronym`,`name`),
  CONSTRAINT `fk_courses_level` FOREIGN KEY (`level_id`) REFERENCES `school_levels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `school_year_levels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int(10) unsigned NOT NULL,
  `year_number` tinyint(3) unsigned NOT NULL,
  `label` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_years_course` (`course_id`,`year_number`),
  CONSTRAINT `fk_years_course` FOREIGN KEY (`course_id`) REFERENCES `school_courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_email_verifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `email` varchar(254) NOT NULL,
  `verified_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_email_verifications_user_email` (`user_id`,`email`),
  CONSTRAINT `fk_student_email_verifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_import_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `academic_term_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned DEFAULT NULL,
  `source` enum('csv','manual') NOT NULL DEFAULT 'csv',
  `original_filename` varchar(255) DEFAULT NULL,
  `imported_by_user_id` int(10) unsigned NOT NULL,
  `year_level` tinyint(3) unsigned NOT NULL,
  `row_count` int(10) unsigned NOT NULL,
  `skipped_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `failed_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `total_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `import_status` enum('completed','completed_with_errors','failed') NOT NULL DEFAULT 'completed',
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `imported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_student_import_batches_term_current` (`academic_term_id`,`is_current`),
  KEY `ix_student_import_batches_imported_at` (`imported_at`),
  KEY `fk_student_import_batches_user` (`imported_by_user_id`),
  KEY `ix_student_import_batches_course_roster` (`academic_term_id`,`course_id`,`year_level`,`is_current`),
  KEY `fk_student_import_batches_course` (`course_id`),
  CONSTRAINT `fk_student_import_batches_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `fk_student_import_batches_term` FOREIGN KEY (`academic_term_id`) REFERENCES `academic_terms` (`id`),
  CONSTRAINT `fk_student_import_batches_user` FOREIGN KEY (`imported_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_registry` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_number` varchar(64) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `surname` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `email` varchar(254) NOT NULL,
  `contact_number` varchar(40) DEFAULT NULL,
  `classification` enum('not_currently_enrolled','transferred_out','dropped_out','alumni') NOT NULL DEFAULT 'not_currently_enrolled',
  `graduation_year` smallint(5) unsigned DEFAULT NULL,
  `last_attended_academic_term_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_registry_number` (`student_number`),
  KEY `ix_student_registry_surname_first_name` (`surname`,`first_name`),
  KEY `ix_student_registry_classification` (`classification`),
  KEY `fk_student_registry_last_term` (`last_attended_academic_term_id`),
  CONSTRAINT `fk_student_registry_last_term` FOREIGN KEY (`last_attended_academic_term_id`) REFERENCES `academic_terms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_request_otps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `document_id` int(10) unsigned NOT NULL,
  `email` varchar(254) NOT NULL,
  `code_hash` char(64) NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_student_request_otps_email_created` (`email`,`created_at`),
  KEY `fk_student_request_otps_user` (`user_id`),
  KEY `fk_student_request_otps_document` (`document_id`),
  CONSTRAINT `fk_student_request_otps_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `fk_student_request_otps_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tracking_no` varchar(30) NOT NULL,
  `submission_token` varchar(64) DEFAULT NULL,
  `student_id` int(10) unsigned DEFAULT NULL,
  `student_registry_id` bigint(20) unsigned DEFAULT NULL,
  `purpose` varchar(255) NOT NULL,
  `release_method` enum('Hardcopy','Softcopy','Both') NOT NULL DEFAULT 'Hardcopy',
  `status` enum('SUBMITTED','AI_VERIFYING','FOR_REVIEW','NEEDS_CORRECTION','PROCESSING','READY','COMPLETED','REJECTED','CANCELLED') NOT NULL DEFAULT 'SUBMITTED',
  `academic_year` varchar(20) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `expected_release_date` date DEFAULT NULL,
  `registrar_remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `ready_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deletion_audit_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_requests_tracking` (`tracking_no`),
  UNIQUE KEY `uq_requests_submission` (`submission_token`),
  KEY `idx_requests_student` (`student_id`),
  KEY `idx_requests_status` (`status`),
  KEY `ix_requests_deleted_at` (`deleted_at`),
  KEY `ix_requests_student_registry` (`student_registry_id`),
  CONSTRAINT `fk_requests_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_requests_student_registry` FOREIGN KEY (`student_registry_id`) REFERENCES `student_registry` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `request_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int(10) unsigned NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `changed_by` int(10) unsigned DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_history_user` (`changed_by`),
  KEY `idx_history_request` (`request_id`),
  CONSTRAINT `fk_history_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `request_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int(10) unsigned NOT NULL,
  `document_id` int(10) unsigned NOT NULL,
  `quantity` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `purpose` varchar(255) DEFAULT NULL,
  `softcopy_status` enum('NONE','UPLOADED','READY') NOT NULL DEFAULT 'NONE',
  `softcopy_uploaded_by` int(10) unsigned DEFAULT NULL,
  `softcopy_uploaded_at` datetime DEFAULT NULL,
  `softcopy_ready_at` datetime DEFAULT NULL,
  `download_limit` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `download_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `last_downloaded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_items_uploader` (`softcopy_uploaded_by`),
  KEY `fk_items_document` (`document_id`),
  KEY `idx_items_request` (`request_id`),
  CONSTRAINT `fk_items_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `fk_items_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_uploader` FOREIGN KEY (`softcopy_uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `import_batch_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `year_level` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `imported_student_number` varchar(64) DEFAULT NULL,
  `imported_first_name` varchar(100) NOT NULL,
  `imported_middle_name` varchar(100) DEFAULT NULL,
  `imported_surname` varchar(100) NOT NULL,
  `imported_email` varchar(254) NOT NULL,
  `enrollment_status` enum('currently_enrolled','not_currently_enrolled','transferred_out','dropped_out','alumni') NOT NULL DEFAULT 'currently_enrolled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_enrollments_batch_student` (`import_batch_id`,`student_id`),
  KEY `ix_student_enrollments_student` (`student_id`),
  KEY `fk_student_enrollments_course` (`course_id`),
  CONSTRAINT `fk_student_enrollments_batch` FOREIGN KEY (`import_batch_id`) REFERENCES `student_import_batches` (`id`),
  CONSTRAINT `fk_student_enrollments_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `fk_student_enrollments_registry` FOREIGN KEY (`student_id`) REFERENCES `student_registry` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_import_rows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` bigint(20) unsigned NOT NULL,
  `row_number` int(10) unsigned NOT NULL,
  `student_identifier` varchar(64) DEFAULT NULL,
  `source_data` longtext DEFAULT NULL,
  `processing_status` enum('imported','skipped','failed','review') NOT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `associated_student_id` bigint(20) unsigned DEFAULT NULL,
  `associated_enrollment_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_import_rows_batch_row` (`batch_id`,`row_number`),
  KEY `ix_student_import_rows_status` (`batch_id`,`processing_status`),
  KEY `fk_student_import_rows_student` (`associated_student_id`),
  KEY `fk_student_import_rows_enrollment` (`associated_enrollment_id`),
  CONSTRAINT `fk_student_import_rows_batch` FOREIGN KEY (`batch_id`) REFERENCES `student_import_batches` (`id`),
  CONSTRAINT `fk_student_import_rows_enrollment` FOREIGN KEY (`associated_enrollment_id`) REFERENCES `student_enrollments` (`id`),
  CONSTRAINT `fk_student_import_rows_student` FOREIGN KEY (`associated_student_id`) REFERENCES `student_registry` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_profile_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `import_batch_id` bigint(20) unsigned DEFAULT NULL,
  `action` enum('created','updated') NOT NULL,
  `source` enum('csv','manual') NOT NULL,
  `changed_by_name` varchar(150) NOT NULL,
  `changes_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_student_profile_audit_student` (`student_id`,`created_at`),
  KEY `fk_student_profile_audit_batch` (`import_batch_id`),
  CONSTRAINT `fk_student_profile_audit_batch` FOREIGN KEY (`import_batch_id`) REFERENCES `student_import_batches` (`id`),
  CONSTRAINT `fk_student_profile_audit_student` FOREIGN KEY (`student_id`) REFERENCES `student_registry` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_roster_memberships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `import_batch_id` bigint(20) unsigned NOT NULL,
  `enrollment_id` bigint(20) unsigned NOT NULL,
  `academic_term_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `year_level` tinyint(3) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_roster_memberships_enrollment` (`enrollment_id`),
  KEY `ix_student_roster_memberships_term_student` (`academic_term_id`,`student_id`,`is_active`),
  KEY `ix_student_roster_memberships_course` (`academic_term_id`,`course_id`,`is_active`),
  KEY `fk_student_roster_memberships_batch` (`import_batch_id`),
  KEY `fk_student_roster_memberships_student` (`student_id`),
  KEY `fk_student_roster_memberships_course` (`course_id`),
  CONSTRAINT `fk_student_roster_memberships_batch` FOREIGN KEY (`import_batch_id`) REFERENCES `student_import_batches` (`id`),
  CONSTRAINT `fk_student_roster_memberships_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `fk_student_roster_memberships_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `student_enrollments` (`id`),
  CONSTRAINT `fk_student_roster_memberships_student` FOREIGN KEY (`student_id`) REFERENCES `student_registry` (`id`),
  CONSTRAINT `fk_student_roster_memberships_term` FOREIGN KEY (`academic_term_id`) REFERENCES `academic_terms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `guest_request_details` (
  `request_id` int(10) unsigned NOT NULL,
  `requester_status` enum('currently_enrolled','alumni','dropped_out','transferred_out') NOT NULL,
  `student_number` varchar(64) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `course` varchar(120) NOT NULL,
  `year_level` varchar(40) NOT NULL,
  `academic_year` varchar(9) NOT NULL,
  `email` varchar(254) NOT NULL,
  `address_line` varchar(255) DEFAULT NULL,
  `province` varchar(120) DEFAULT NULL,
  `city_municipality` varchar(120) DEFAULT NULL,
  `barangay` varchar(120) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` varchar(30) DEFAULT NULL,
  `mobile_number` varchar(30) DEFAULT NULL,
  `elementary_school` varchar(180) DEFAULT NULL,
  `elementary_year` varchar(9) DEFAULT NULL,
  `junior_high_school` varchar(180) DEFAULT NULL,
  `junior_high_year` varchar(9) DEFAULT NULL,
  `senior_high_school` varchar(180) DEFAULT NULL,
  `senior_high_year` varchar(9) DEFAULT NULL,
  `request_purpose` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  CONSTRAINT `fk_guest_request_details_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_roster_corrections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `membership_id` bigint(20) unsigned NOT NULL,
  `correction_type` enum('delete','move') NOT NULL,
  `from_course_id` bigint(20) unsigned NOT NULL,
  `from_year_level` tinyint(3) unsigned NOT NULL,
  `to_course_id` bigint(20) unsigned DEFAULT NULL,
  `to_year_level` tinyint(3) unsigned DEFAULT NULL,
  `corrected_by_name` varchar(150) NOT NULL,
  `corrected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_student_roster_corrections_membership` (`membership_id`,`corrected_at`),
  KEY `fk_student_roster_corrections_from_course` (`from_course_id`),
  KEY `fk_student_roster_corrections_to_course` (`to_course_id`),
  CONSTRAINT `fk_student_roster_corrections_from_course` FOREIGN KEY (`from_course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `fk_student_roster_corrections_membership` FOREIGN KEY (`membership_id`) REFERENCES `student_roster_memberships` (`id`),
  CONSTRAINT `fk_student_roster_corrections_to_course` FOREIGN KEY (`to_course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO school_levels (name, sort_order)
VALUES ('COLLEGE', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);
