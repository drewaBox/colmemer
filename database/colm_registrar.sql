-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 16, 2026 at 03:36 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `colm_dbs`
--
CREATE DATABASE IF NOT EXISTS `colm_dbs` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `colm_dbs`;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(60) NOT NULL DEFAULT 'Certification',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `processing_min_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `processing_max_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `release_methods` varchar(50) NOT NULL DEFAULT 'Hardcopy',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `name`, `description`, `category`, `price`, `processing_min_days`, `processing_max_days`, `release_methods`, `is_active`, `sort_order`) VALUES
(1, 'Transcript of Records (TOR)', 'Official consolidated academic transcript covering all course units, grades, and graduation credentials.', 'Academic Records', 250.00, 5, 7, 'Hardcopy', 1, 1),
(2, 'Certification of Grades', 'Official certificate containing semester breakdown of subjects taken and grades obtained.', 'Certification', 100.00, 0, 0, 'Softcopy,Hardcopy', 1, 2),
(3, 'Certificate of Enrollment', 'Formal institutional verification confirming active student status in the current semester.', 'Certification', 100.00, 0, 0, 'Hardcopy', 1, 3),
(4, 'Certificate of Registration', 'Certified copy of the official enrollment registration and scheduled course matrix', 'Certification', 100.00, 0, 0, 'Softcopy,Hardcopy', 1, 4),
(5, 'Certificate of Graduation', 'Official institutional attestation confirming the degree earned and graduation date.', 'Certification', 100.00, 0, 0, 'Hardcopy', 1, 5),
(6, 'Certificate of Good Moral Character', 'Attestation from the Office of Student Affairs and Registrar regarding discipline record.', 'Certification', 100.00, 0, 0, 'Hardcopy', 1, 6),
(7, 'Certificate of Units Earned', 'Statement indicating total credited collegiate units completed to date.', 'Certification', 100.00, 0, 0, 'Hardcopy', 1, 7),
(8, 'Certificate of General Weighted Average (GWA)', 'Certification of overall cumulative grade point average across all completed semesters.', 'Certification', 100.00, 1, 3, 'Hardcopy', 1, 8),
(9, 'Certificate of No Pending/Unfinished Academic Requirement', 'Certification certifying completion of all coursework, prerequisites, and thesis/internship requirements.', 'Certification', 100.00, 0, 0, 'Hardcopy', 1, 9),
(10, 'Honorable Dismissal', 'Official document permitting student to transfer out and enroll in another collegiate institution.', 'Clearance', 350.00, 0, 0, 'Hardcopy', 1, 10),
(11, 'Certified True Copy of Academic Record', 'Registrar seal and dry stamp verifying authenticity of submitted photocopies of records.', 'Academic Records', 50.00, 0, 0, 'Hardcopy', 1, 11),
(13, 'Internship/Training Certification', 'Formal certification of completed on-the-job training (OJT) or practicum hours.', 'Certification', 100.00, 0, 0, 'Hardcopy', 1, 13),
(14, 'NSTP/CWTS-related Certification', 'Official certificate and serial number for completed National Service Training Program.', 'Certification', 100.00, 0, 0, 'Hardcopy', 1, 14),
(15, 'Other Certifications', 'Custom registrar certification for special academic or administrative situations.', 'Certification', 100.00, 0, 0, 'Hardcopy', 1, 15),
(16, 'Diploma', 'Official hard-copy diploma. Bring the physical payment receipt when claiming your document.', 'Academic Records', 500.00, 5, 7, 'Hardcopy', 1, 16);

-- --------------------------------------------------------

--
-- Table structure for table `document_requirements`
--

CREATE TABLE `document_requirements` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `ai_hint` text DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `requires_name` tinyint(1) NOT NULL DEFAULT 1,
  `allowed_types` varchar(50) NOT NULL DEFAULT 'pdf,jpg,jpeg,png',
  `max_size_mb` tinyint(3) UNSIGNED NOT NULL DEFAULT 3,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_requirements`
--

INSERT INTO `document_requirements` (`id`, `document_id`, `name`, `description`, `ai_hint`, `keywords`, `is_required`, `requires_name`, `allowed_types`, `max_size_mb`, `sort_order`) VALUES
(1, 1, 'Accomplished request', 'Legacy upload requirement. New requests use the online credential request form instead.', 'A filled-out document request form with the student name, student number and purpose.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(2, 1, 'Clearance/completion of requirements', 'Legacy upload requirement. New requests use the online credential request form instead.', 'A clearance form with office signatures or stamps showing the requirements are complete.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 2),
(3, 2, 'Request/application', 'Filled-out request or application form.', 'A filled-out document request or application form with the student name and student number.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(4, 2, 'Student verification', 'Proof of student identity.', 'A valid school ID or government ID showing the student name and photo.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 2),
(5, 3, 'Student identification/verification', 'Proof of student identity.', 'A valid school ID or government ID showing the student name and photo.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(6, 4, 'Student information/verification', 'Proof of student identity or enrollment.', 'A school ID or enrollment record showing the student name and student number.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(7, 5, 'Verified graduate record', 'Proof that the student has graduated.', 'A diploma, graduation list, or clearance showing the student has graduated.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(8, 5, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0),
(9, 6, 'Required clearances/verification as applicable', 'Clearance forms that apply to the student.', 'A clearance form with the required office signatures.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(10, 6, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0),
(11, 7, 'Request and record verification', 'Request form with student details.', 'A request form showing the student name and student number.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(12, 7, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0),
(13, 8, 'Request and record verification', 'Request form with student details.', 'A request form showing the student name and student number.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(14, 8, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0),
(15, 9, 'Verification of academic records', 'Request form or record copy with student details.', 'A request form or record copy showing the student name and student number.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(16, 9, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0),
(17, 10, 'Required clearance and institutional requirements', 'Legacy upload requirement. New requests use the online credential request form instead.', 'A clearance form with signatures from the required offices.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(18, 11, 'Original record/request and verification', 'The record to be certified or a signed request.', 'The academic record to be certified, or a signed request form with student details.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(19, 13, 'Verification of institutional record', 'Proof of the internship or training.', 'A certificate, endorsement letter or agreement proving the internship or training.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(20, 13, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0),
(21, 14, 'Record verification', 'Proof of the NSTP/CWTS record.', 'An NSTP/CWTS record or serial number proof showing the student name.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(22, 14, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0),
(23, 15, 'Appropriate request and supporting documents', 'Request letter and supporting document.', 'A request letter and a supporting document for the requested certification.', NULL, 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(24, 15, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `ip` varchar(45) NOT NULL,
  `username` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED DEFAULT NULL,
  `target_type` varchar(30) DEFAULT NULL,
  `target_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `message` varchar(500) NOT NULL,
  `type` enum('info','success','warning','error') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_requests`
--

CREATE TABLE `password_reset_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `ip` varchar(45) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `receipt_no` varchar(30) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('Cash','GCash') NOT NULL,
  `reference_no` varchar(40) DEFAULT NULL,
  `received_by` int(10) UNSIGNED DEFAULT NULL,
  `paid_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_receipts`
--

CREATE TABLE `payment_receipts` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `size_bytes` int(10) UNSIGNED NOT NULL,
  `status` enum('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  `remarks` varchar(500) DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `official_receipt_no` varchar(40) DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `date_paid` date DEFAULT NULL,
  `file_hash` char(64) DEFAULT NULL,
  `security_flags` text DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `tracking_no` varchar(30) NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `release_method` enum('Hardcopy','Softcopy','Both') NOT NULL DEFAULT 'Hardcopy',
  `payment_method` enum('Cash','GCash') NOT NULL DEFAULT 'Cash',
  `status` enum('SUBMITTED','AI_VERIFYING','FOR_REVIEW','NEEDS_CORRECTION','PROCESSING','READY','COMPLETED','REJECTED') NOT NULL DEFAULT 'SUBMITTED',
  `payment_status` enum('Unpaid','Paid') NOT NULL DEFAULT 'Unpaid',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `academic_year` varchar(20) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `year_graduated` smallint(5) UNSIGNED DEFAULT NULL,
  `credential_form_json` longtext DEFAULT NULL,
  `student_verification_form_json` longtext DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `target_start` date DEFAULT NULL,
  `target_end` date DEFAULT NULL,
  `registrar_remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_files`
--

CREATE TABLE `request_files` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `requirement_id` int(10) UNSIGNED DEFAULT NULL,
  `kind` enum('requirement','release') NOT NULL DEFAULT 'requirement',
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `size_bytes` int(10) UNSIGNED NOT NULL,
  `sha256` char(64) NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 1,
  `review_status` enum('Pending','Verified','Rejected','Resubmit') NOT NULL DEFAULT 'Pending',
  `review_remarks` varchar(500) DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_history`
--

CREATE TABLE `request_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `changed_by` int(10) UNSIGNED DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_items`
--

CREATE TABLE `request_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `quantity` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `softcopy_status` enum('NONE','UPLOADED','READY') NOT NULL DEFAULT 'NONE',
  `softcopy_uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `softcopy_uploaded_at` datetime DEFAULT NULL,
  `softcopy_ready_at` datetime DEFAULT NULL,
  `download_limit` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `download_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `last_downloaded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_verifications`
--

CREATE TABLE `request_verifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `route` enum('REGISTRAR','AUTO') NOT NULL,
  `verdict` enum('PASS','REVIEW','NEEDS_CORRECTION') NOT NULL,
  `summary` varchar(500) NOT NULL,
  `checks_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_courses`
--

CREATE TABLE `school_courses` (
  `id` int(10) UNSIGNED NOT NULL,
  `level_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_levels`
--

CREATE TABLE `school_levels` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_year_levels`
--

CREATE TABLE `school_year_levels` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `year_number` tinyint(3) UNSIGNED NOT NULL,
  `label` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `softcopy_downloads`
--

CREATE TABLE `softcopy_downloads` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `file_id` int(10) UNSIGNED DEFAULT NULL,
  `download_number` smallint(5) UNSIGNED DEFAULT NULL,
  `download_type` enum('ORIGINAL','ADDITIONAL','STAFF') NOT NULL,
  `downloaded_by` int(10) UNSIGNED DEFAULT NULL,
  `downloaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `extra_request_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `softcopy_download_requests`
--

CREATE TABLE `softcopy_download_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `reason_type` varchar(60) NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `status` enum('PENDING','APPROVED','REJECTED','USED') NOT NULL DEFAULT 'PENDING',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `decided_by` int(10) UNSIGNED DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `school_level` varchar(30) DEFAULT NULL,
  `course` varchar(80) DEFAULT NULL,
  `year_level` tinyint(3) UNSIGNED DEFAULT NULL,
  `status` enum('currently_enrolled','alumni','undergrad') NOT NULL DEFAULT 'currently_enrolled',
  `academic_year` varchar(9) DEFAULT NULL,
  `contact_no` varchar(30) DEFAULT NULL,
  `photo_stored` varchar(255) DEFAULT NULL,
  `photo_mime` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `upload_attempts`
--

CREATE TABLE `upload_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `upload_checks`
--

CREATE TABLE `upload_checks` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `requirement_id` int(10) UNSIGNED NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) NOT NULL,
  `size_bytes` int(10) UNSIGNED NOT NULL,
  `sha256` char(64) NOT NULL,
  `status` enum('VERIFIED','DECLINED','NEEDS_CORRECTION','PENDING') NOT NULL,
  `student_message` varchar(500) NOT NULL,
  `result_status` enum('PASS','REVIEW','FAIL','UNAVAILABLE') NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `extracted_text` text DEFAULT NULL,
  `confidence` decimal(4,3) DEFAULT NULL,
  `name_on_document` varchar(150) DEFAULT NULL,
  `name_match_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `auto_declined` tinyint(1) NOT NULL DEFAULT 0,
  `model` varchar(80) DEFAULT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `used_file_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `role` enum('student','registrar','admin') NOT NULL,
  `student_no` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_approved` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `failed_logins` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_documents_name` (`name`);

--
-- Indexes for table `document_requirements`
--
ALTER TABLE `document_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_req_document` (`document_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_ip` (`ip`,`created_at`),
  ADD KEY `idx_login_user` (`ip`,`username`,`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notes_request` (`request_id`),
  ADD KEY `idx_notes_user` (`user_id`,`is_read`);

--
-- Indexes for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_password_reset_requests_ip` (`ip`,`created_at`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_password_reset_token_hash` (`token_hash`),
  ADD KEY `idx_password_reset_expiry` (`expires_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payments_receipt` (`receipt_no`),
  ADD UNIQUE KEY `uq_payments_request` (`request_id`),
  ADD UNIQUE KEY `uq_payments_reference` (`reference_no`),
  ADD KEY `idx_payments_paid_at` (`paid_at`),
  ADD KEY `fk_payments_user` (`received_by`);

--
-- Indexes for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payment_receipts_request` (`request_id`),
  ADD KEY `idx_payment_receipts_file_hash` (`file_hash`),
  ADD KEY `idx_payment_receipts_official_status` (`official_receipt_no`,`status`),
  ADD KEY `fk_payment_receipts_uploader` (`uploaded_by`),
  ADD KEY `fk_payment_receipts_reviewer` (`reviewed_by`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_requests_tracking` (`tracking_no`),
  ADD UNIQUE KEY `uq_requests_qr` (`qr_code`),
  ADD KEY `idx_requests_student` (`student_id`),
  ADD KEY `idx_requests_status` (`status`);

--
-- Indexes for table `request_files`
--
ALTER TABLE `request_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_files_item` (`item_id`),
  ADD KEY `fk_files_requirement` (`requirement_id`),
  ADD KEY `fk_files_reviewer` (`reviewed_by`),
  ADD KEY `idx_files_request` (`request_id`),
  ADD KEY `idx_files_hash` (`request_id`,`sha256`);

--
-- Indexes for table `request_history`
--
ALTER TABLE `request_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_history_user` (`changed_by`),
  ADD KEY `idx_history_request` (`request_id`);

--
-- Indexes for table `request_items`
--
ALTER TABLE `request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_items_uploader` (`softcopy_uploaded_by`),
  ADD KEY `fk_items_document` (`document_id`),
  ADD KEY `idx_items_request` (`request_id`);

--
-- Indexes for table `request_verifications`
--
ALTER TABLE `request_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_verifications_request` (`request_id`,`id`);

--
-- Indexes for table `school_courses`
--
ALTER TABLE `school_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_courses_name` (`name`),
  ADD KEY `fk_courses_level` (`level_id`);

--
-- Indexes for table `school_levels`
--
ALTER TABLE `school_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_levels_name` (`name`);

--
-- Indexes for table `school_year_levels`
--
ALTER TABLE `school_year_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_years_course` (`course_id`,`year_number`);

--
-- Indexes for table `softcopy_downloads`
--
ALTER TABLE `softcopy_downloads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dl_item` (`item_id`,`downloaded_at`),
  ADD KEY `idx_dl_student` (`student_id`),
  ADD KEY `fk_dl_request` (`request_id`),
  ADD KEY `fk_dl_file` (`file_id`),
  ADD KEY `fk_dl_user` (`downloaded_by`),
  ADD KEY `fk_dl_extra` (`extra_request_id`);

--
-- Indexes for table `softcopy_download_requests`
--
ALTER TABLE `softcopy_download_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dlreq_item` (`item_id`,`status`),
  ADD KEY `idx_dlreq_status` (`status`,`created_at`),
  ADD KEY `idx_dlreq_student` (`student_id`),
  ADD KEY `fk_dlreq_request` (`request_id`),
  ADD KEY `fk_dlreq_decider` (`decided_by`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_students_user` (`user_id`),
  ADD KEY `idx_students_course` (`course`);

--
-- Indexes for table `upload_attempts`
--
ALTER TABLE `upload_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_upload_attempts` (`user_id`,`created_at`);

--
-- Indexes for table `upload_checks`
--
ALTER TABLE `upload_checks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_checks_requirement` (`requirement_id`),
  ADD KEY `idx_checks_user` (`user_id`,`created_at`),
  ADD KEY `idx_checks_unused` (`used_file_id`,`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_student_no` (`student_no`),
  ADD KEY `idx_users_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `document_requirements`
--
ALTER TABLE `document_requirements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_files`
--
ALTER TABLE `request_files`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_history`
--
ALTER TABLE `request_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_items`
--
ALTER TABLE `request_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_verifications`
--
ALTER TABLE `request_verifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_courses`
--
ALTER TABLE `school_courses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_levels`
--
ALTER TABLE `school_levels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_year_levels`
--
ALTER TABLE `school_year_levels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `softcopy_downloads`
--
ALTER TABLE `softcopy_downloads`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `softcopy_download_requests`
--
ALTER TABLE `softcopy_download_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `upload_attempts`
--
ALTER TABLE `upload_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `upload_checks`
--
ALTER TABLE `upload_checks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `document_requirements`
--
ALTER TABLE `document_requirements`
  ADD CONSTRAINT `fk_req_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notes_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payments_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  ADD CONSTRAINT `fk_payment_receipts_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payment_receipts_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_payment_receipts_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `fk_requests_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `request_files`
--
ALTER TABLE `request_files`
  ADD CONSTRAINT `fk_files_item` FOREIGN KEY (`item_id`) REFERENCES `request_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_files_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_files_requirement` FOREIGN KEY (`requirement_id`) REFERENCES `document_requirements` (`id`),
  ADD CONSTRAINT `fk_files_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `request_history`
--
ALTER TABLE `request_history`
  ADD CONSTRAINT `fk_history_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `request_items`
--
ALTER TABLE `request_items`
  ADD CONSTRAINT `fk_items_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  ADD CONSTRAINT `fk_items_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_items_uploader` FOREIGN KEY (`softcopy_uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `request_verifications`
--
ALTER TABLE `request_verifications`
  ADD CONSTRAINT `fk_verifications_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `school_courses`
--
ALTER TABLE `school_courses`
  ADD CONSTRAINT `fk_courses_level` FOREIGN KEY (`level_id`) REFERENCES `school_levels` (`id`);

--
-- Constraints for table `school_year_levels`
--
ALTER TABLE `school_year_levels`
  ADD CONSTRAINT `fk_years_course` FOREIGN KEY (`course_id`) REFERENCES `school_courses` (`id`);

--
-- Constraints for table `softcopy_downloads`
--
ALTER TABLE `softcopy_downloads`
  ADD CONSTRAINT `fk_dl_extra` FOREIGN KEY (`extra_request_id`) REFERENCES `softcopy_download_requests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dl_file` FOREIGN KEY (`file_id`) REFERENCES `request_files` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dl_item` FOREIGN KEY (`item_id`) REFERENCES `request_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dl_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dl_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_dl_user` FOREIGN KEY (`downloaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `softcopy_download_requests`
--
ALTER TABLE `softcopy_download_requests`
  ADD CONSTRAINT `fk_dlreq_decider` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dlreq_item` FOREIGN KEY (`item_id`) REFERENCES `request_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dlreq_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dlreq_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `upload_attempts`
--
ALTER TABLE `upload_attempts`
  ADD CONSTRAINT `fk_upload_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `upload_checks`
--
ALTER TABLE `upload_checks`
  ADD CONSTRAINT `fk_checks_file` FOREIGN KEY (`used_file_id`) REFERENCES `request_files` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_checks_requirement` FOREIGN KEY (`requirement_id`) REFERENCES `document_requirements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_checks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
--
-- Database: `colm_registrar`
--
CREATE DATABASE IF NOT EXISTS `colm_registrar` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `colm_registrar`;

-- --------------------------------------------------------

--
-- Table structure for table `academic_terms`
--

CREATE TABLE `academic_terms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `academic_year_id` bigint(20) UNSIGNED NOT NULL,
  `semester` tinyint(3) UNSIGNED NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_terms`
--

INSERT INTO `academic_terms` (`id`, `academic_year_id`, `semester`, `is_current`) VALUES
(1, 2, 1, 0),
(2, 2, 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `start_year` smallint(5) UNSIGNED NOT NULL,
  `end_year` smallint(5) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `start_year`, `end_year`) VALUES
(1, 2025, 2026),
(2, 2026, 2027),
(3, 2027, 2028);

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `name`) VALUES
(3, 'BSAIS - Accounting Information System'),
(4, 'BSFM - Financial Management'),
(5, 'BSIT - Information Technology'),
(6, 'BSMM - Marketing Management'),
(8, 'BSTM - Tourism Management');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `processing_min_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `processing_max_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `release_methods` varchar(50) NOT NULL DEFAULT 'Hardcopy',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `name`, `description`, `price`, `processing_min_days`, `processing_max_days`, `release_methods`, `is_active`, `sort_order`) VALUES
(1, 'Transcript of Records (TOR)', 'Official consolidated academic transcript covering all course units, grades, and graduation credentials.', 250.00, 5, 7, 'Hardcopy', 1, 1),
(2, 'Certification of Grades', 'Official certificate containing semester breakdown of subjects taken and grades obtained.', 100.00, 0, 0, 'Softcopy,Hardcopy', 1, 2),
(3, 'Certificate of Enrollment', 'Formal institutional verification confirming active student status in the current semester.', 100.00, 0, 0, 'Hardcopy', 1, 3),
(4, 'Certificate of Registration', 'Certified copy of the official enrollment registration and scheduled course matrix', 100.00, 0, 0, 'Softcopy,Hardcopy', 1, 4),
(5, 'Certificate of Graduation', 'Official institutional attestation confirming the degree earned and graduation date.', 100.00, 0, 0, 'Hardcopy', 1, 5),
(6, 'Certificate of Good Moral Character', 'Attestation from the Office of Student Affairs and Registrar regarding discipline record.', 100.00, 0, 0, 'Hardcopy', 1, 6),
(7, 'Certificate of Units Earned', 'Statement indicating total credited collegiate units completed to date.', 100.00, 0, 0, 'Hardcopy', 1, 7),
(8, 'Certificate of General Weighted Average (GWA)', 'Certification of overall cumulative grade point average across all completed semesters.', 100.00, 1, 3, 'Hardcopy', 1, 8),
(9, 'Certificate of No Pending/Unfinished Academic Requirement', 'Certification certifying completion of all coursework, prerequisites, and thesis/internship requirements.', 100.00, 0, 0, 'Hardcopy', 1, 9),
(10, 'Honorable Dismissal', 'Official document permitting student to transfer out and enroll in another collegiate institution.', 350.00, 0, 0, 'Hardcopy', 1, 10),
(11, 'Certified True Copy of Academic Record', 'Registrar seal and dry stamp verifying authenticity of submitted photocopies of records.', 50.00, 0, 0, 'Hardcopy', 1, 11),
(13, 'Internship/Training Certification', 'Formal certification of completed on-the-job training (OJT) or practicum hours.', 100.00, 0, 0, 'Hardcopy', 1, 13),
(14, 'NSTP/CWTS-related Certification', 'Official certificate and serial number for completed National Service Training Program.', 100.00, 0, 0, 'Hardcopy', 1, 14),
(15, 'Other Certifications', 'Custom registrar certification for special academic or administrative situations.', 100.00, 0, 0, 'Hardcopy', 1, 15),
(16, 'Diploma', 'Official hard-copy diploma. Bring the physical payment receipt when claiming your document.', 500.00, 5, 7, 'Hardcopy', 1, 16);

-- --------------------------------------------------------

--
-- Table structure for table `document_requirements`
--

CREATE TABLE `document_requirements` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `ai_hint` text DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `requires_name` tinyint(1) NOT NULL DEFAULT 1,
  `allowed_types` varchar(50) NOT NULL DEFAULT 'pdf,jpg,jpeg,png',
  `max_size_mb` tinyint(3) UNSIGNED NOT NULL DEFAULT 3,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_requirements`
--

INSERT INTO `document_requirements` (`id`, `document_id`, `name`, `description`, `ai_hint`, `keywords`, `is_required`, `requires_name`, `allowed_types`, `max_size_mb`, `sort_order`) VALUES
(1, 1, 'Accomplished request', 'Legacy upload requirement. New requests use the online credential request form instead.', 'A filled-out document request form with the student name, student number and purpose.', 'request,form,purpose,transcript', 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(2, 1, 'Clearance/completion of requirements', 'Legacy upload requirement. New requests use the online credential request form instead.', 'A clearance form with office signatures or stamps showing the requirements are complete.', 'clearance,cleared,signature,requirements', 1, 1, 'pdf,jpg,jpeg,png', 3, 2),
(3, 2, 'Request/application', 'Filled-out request or application form.', 'A filled-out document request or application form with the student name and student number.', 'request,application,form', 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(4, 2, 'Student verification', 'Proof of student identity.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,school,college', 1, 1, 'pdf,jpg,jpeg,png', 3, 2),
(5, 3, 'Student identification/verification', 'Proof of student identity.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,school,college', 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(6, 4, 'Student information/verification', 'Proof of student identity or enrollment.', 'A school ID or enrollment record showing the student name and student number.', 'student,id,school,enrollment', 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(7, 5, 'Verified graduate record', 'Proof that the student has graduated.', 'A diploma, graduation list, or clearance showing the student has graduated.', 'graduate,graduation,diploma,degree', 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(8, 5, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0),
(9, 6, 'Required clearances/verification as applicable', 'Clearance forms that apply to the student.', 'A clearance form with the required office signatures.', 'clearance,cleared,signature', 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(10, 6, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0),
(11, 7, 'Request and record verification', 'Request form with student details.', 'A request form showing the student name and student number.', 'request,form,student,record', 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(12, 7, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0),
(13, 8, 'Request and record verification', 'Request form with student details.', 'A request form showing the student name and student number.', 'request,form,student,record', 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(14, 8, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0),
(15, 9, 'Verification of academic records', 'Request form or record copy with student details.', 'A request form or record copy showing the student name and student number.', 'request,record,student,grades', 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(16, 9, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0),
(17, 10, 'Required clearance and institutional requirements', 'Legacy upload requirement. New requests use the online credential request form instead.', 'A clearance form with signatures from the required offices.', 'clearance,cleared,signature', 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(18, 11, 'Original record/request and verification', 'The record to be certified or a signed request.', 'The academic record to be certified, or a signed request form with student details.', 'record,request,certified,grades', 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(20, 13, 'Verification of institutional record', 'Proof of the internship or training.', 'A certificate, endorsement letter or agreement proving the internship or training.', 'certificate,training,internship,ojt', 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(21, 13, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0),
(22, 14, 'Record verification', 'Proof of the NSTP/CWTS record.', 'An NSTP/CWTS record or serial number proof showing the student name.', 'nstp,cwts,serial,record', 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(23, 14, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0),
(24, 15, 'Appropriate request and supporting documents', 'Request letter and supporting document.', 'A request letter and a supporting document for the requested certification.', 'request,letter,certification,supporting', 1, 1, 'pdf,jpg,jpeg,png', 3, 1),
(25, 15, 'Student ID / Verification', 'Upload a clear copy of your school ID or another valid ID showing your name and photo.', 'A valid school ID or government ID showing the student name and photo.', 'student,id,identification,school,government,photo', 1, 1, 'pdf,jpg,jpeg,png', 3, 0);

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `template_key` varchar(40) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`template_key`, `subject`, `body`, `is_enabled`, `updated_by`, `updated_at`) VALUES
('processing', 'Your document request is being processed · {tracking_number}', 'Your request for {document_name} is now being processed.\r\n\r\nExpected release date: {expected_release_date}\r\nTracking reference: {tracking_number}', 1, 3, '2026-11-12 10:24:04'),
('ready', 'Your document is ready for pickup · {tracking_number}', 'Your request for {document_name} is ready for pickup.\n\nTracking reference: {tracking_number}', 1, NULL, '2026-11-11 16:07:12'),
('rejected', 'Update about your document request · {tracking_number}', 'Your request for {document_name} was rejected.\n\nReason: {rejection_reason}\n\nTracking reference: {tracking_number}', 1, NULL, '2026-11-11 16:07:12'),
('request_submitted', 'Document request received · {tracking_number}', 'Your request for {document_name} has been submitted.\n\nTracking reference: {tracking_number}\nCurrent status: {status}\n\nTrack your request: {tracking_url}\n\nKeep this reference to check your request status.', 1, NULL, '2026-11-11 16:07:12'),
('verification_code', 'Your registrar email verification code', 'Your verification code is: {verification_code}\r\n\r\nThis code expires in {expiration_minutes} minutes and can only be used once.\r\nIf you did not request a document, you can ignore this email.', 1, 3, '2026-11-12 20:09:52');

-- --------------------------------------------------------

--
-- Table structure for table `guest_request_details`
--

CREATE TABLE `guest_request_details` (
  `request_id` int(10) UNSIGNED NOT NULL,
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
  `request_purpose` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guest_request_details`
--

INSERT INTO `guest_request_details` (`request_id`, `requester_status`, `student_number`, `first_name`, `middle_name`, `last_name`, `course`, `year_level`, `academic_year`, `email`, `address_line`, `province`, `city_municipality`, `barangay`, `birthdate`, `gender`, `mobile_number`, `elementary_school`, `elementary_year`, `junior_high_school`, `junior_high_year`, `senior_high_school`, `senior_high_year`, `request_purpose`) VALUES
(14, 'currently_enrolled', '2026-09897', 'Mark Jennon', NULL, 'Gatchalian', 'BSIT - Information Technology', 'Year 3', '2026-2027', 'mark.jennon.gatchalian.16@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'currently_enrolled', '2026-09897', 'Mark Jennon', NULL, 'Gatchalian', 'BSIT - Information Technology', 'Year 3', '2026-2027', 'mark.jennon.gatchalian.16@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'currently_enrolled', '2026-09897', 'Mark Jennon', NULL, 'Gatchalian', 'BSIT - Information Technology', 'Year 3', '2026-2027', 'mark.jennon.gatchalian.16@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 'currently_enrolled', '2026-09897', 'Mark Jennon', NULL, 'Gatchalian', 'BSIT - Information Technology', 'Year 3', '2026-2027', 'mark.jennon.gatchalian.16@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'currently_enrolled', '2026-09897', 'Mark Jennon', NULL, 'Gatchalian', 'BSIT - Information Technology', 'Year 3', '2026-2027', 'mark.jennon.gatchalian.16@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 'currently_enrolled', '2026-10001', 'Juliana', NULL, 'Yadao', 'BSIT - Information Technology', 'Year 3', '2026-2027', 'juli4na.yada0@gmail.com', '147', 'Bulacan', 'City of Malolos', 'Tikay', '2005-07-14', 'Prefer not to say', '09758367463', 'Elem', '2016-2017', 'hs', '2019-2020', 'sh', '2022-2023', 'Transfer Out'),
(21, 'alumni', NULL, 'Lovely Joy', '', 'Martin', 'BSCRIM - Criminology', 'Graduated', '2023-2024', 'mark.jennon.gatchalian.16@gmail.com', '6328', 'Cagayan', 'Gonzaga', 'Magrafil', '2005-05-06', 'Prefer not to say', '09746253985', 'elem', '2016-2017', 'jhs', '2018-2019', 'shs', '2023-2024', 'Secret'),
(27, 'currently_enrolled', '2026-12002', 'John Emerson', NULL, 'Larida', 'BSIT - Information Technology', 'Year 3', '2026-2027', 'janemersonlarida@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(28, 'currently_enrolled', '2026-12002', 'John Emerson', NULL, 'Larida', 'BSIT - Information Technology', 'Year 3', '2026-2027', 'janemersonlarida@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, 'alumni', NULL, 'Yang', NULL, 'Jungwon', 'BTVTE - Home Economics', 'Graduated', '2020-2021', 'laridajohnemerson7@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `bucket` varchar(120) NOT NULL,
  `window_start` datetime NOT NULL,
  `hits` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rate_limits`
--

INSERT INTO `rate_limits` (`bucket`, `window_start`, `hits`) VALUES
('student-lookup:eff8e7ca506627fe15dda5e0e512fcaad70b6d520f37cc76597fdb4f2d83a1a3', '2026-11-16 09:05:57', 3);

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `tracking_no` varchar(30) NOT NULL,
  `submission_token` varchar(64) DEFAULT NULL,
  `student_id` int(10) UNSIGNED DEFAULT NULL,
  `student_registry_id` bigint(20) UNSIGNED DEFAULT NULL,
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
  `deletion_audit_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`id`, `tracking_no`, `submission_token`, `student_id`, `student_registry_id`, `purpose`, `release_method`, `status`, `academic_year`, `semester`, `program`, `expected_release_date`, `registrar_remarks`, `created_at`, `completed_at`, `ready_at`, `deleted_at`, `deletion_audit_id`) VALUES
(14, 'REQ-8A7406E6D6CFA4115E208ED4', 'cd2c848f1dcb748464b77f1b7013e07d36e875e6520644703dcb9638504f2dd4', NULL, 3, 'Certificate of Good Moral Character', 'Hardcopy', 'REJECTED', NULL, NULL, NULL, '2026-11-14', 'May kaba', '2026-11-13 08:28:29', NULL, NULL, '2026-11-16 08:49:46', 11),
(15, 'REQ-05BAFCFDAFDD58BEDBFBB797', '46eb04487eb300ec5d306f0edd8867b95dff0c742410c43cfa90727aad218c54', NULL, 3, 'Transcript of Records (TOR)', 'Hardcopy', 'SUBMITTED', NULL, NULL, NULL, NULL, NULL, '2026-11-13 10:27:48', NULL, NULL, '2026-11-16 08:50:00', 13),
(17, 'REQ-C6207599C447035EAC920928', 'f2ddc61b498ba7c17ba31c5da1653c7e1a0be4713f37f25106063bca48e3f9d3', NULL, 3, 'Transcript of Records (TOR)', 'Hardcopy', 'REJECTED', NULL, NULL, NULL, NULL, 'Nakakaramdam ako ng kaba', '2026-11-13 11:04:20', NULL, NULL, '2026-11-16 08:49:53', 12),
(18, 'REQ-4D950B0BD47B13B18AC4EBE3', 'de483a5c7b3c5ad36b42830e349e5141b51f85d78c5d38ee17bff6dc13181947', NULL, 3, 'Transcript of Records (TOR)', 'Hardcopy', 'SUBMITTED', NULL, NULL, NULL, NULL, NULL, '2026-11-13 11:12:37', NULL, NULL, '2026-11-16 08:49:36', 10),
(19, 'REQ-A5B044935835469460F9BE52', '5d34eca5f7170b2f492e074ca3f399f0c5d8ea9ae2db0f29bad07e4ec1a9473e', NULL, 3, 'Transcript of Records (TOR)', 'Hardcopy', 'SUBMITTED', NULL, NULL, NULL, NULL, NULL, '2026-11-13 11:18:37', NULL, NULL, '2026-11-16 08:49:20', 9),
(20, 'REQ-73A1B115BF219F976429070D', 'e2fe42095ca674f7d405e476a5f86ca9fd4cc5a1177629513c429d1e3b2e0461', NULL, 4, 'Transcript of Records (TOR)', 'Hardcopy', 'SUBMITTED', NULL, NULL, NULL, NULL, NULL, '2026-11-13 11:53:19', NULL, NULL, '2026-11-13 17:43:00', 8),
(21, 'REQ-B155D20FCE314A65A22003EF', 'b2a4d4a2ccd7696deb906f95366017b83a64053ac2676501ab7b361b28825afb', NULL, 3, 'Transcript of Records (TOR)', 'Hardcopy', 'PROCESSING', NULL, NULL, NULL, '2026-11-15', NULL, '2026-11-13 11:58:04', NULL, NULL, '2026-11-13 17:42:40', 7),
(27, 'REQ-8FD4FECE56822A09BF862058', 'a8e314041db5d99bfac85c6b3d94a147b97aec7d04518408d8efb03104e346f3', NULL, 5, 'Certificate of General Weighted Average (GWA)', 'Hardcopy', 'PROCESSING', NULL, NULL, NULL, '2026-11-19', NULL, '2026-11-16 09:04:04', NULL, NULL, '2026-11-16 09:28:55', 15),
(28, 'REQ-226FF91960A37FDD0962335F', 'bba7c7060e63820062e8e7d93dbea236bfd742e659f11c7881536dca4eafe53b', NULL, 5, 'Certificate of Enrollment', 'Hardcopy', 'PROCESSING', NULL, NULL, NULL, '2026-11-16', NULL, '2026-11-16 09:06:23', NULL, NULL, '2026-11-16 09:27:10', 14),
(29, 'REQ-F4055B0CCF75C55A4E720BFE', '7033cfe326157723629407d8e32542cc3b8011eb819b78bdb09800418053aa47', NULL, NULL, 'Certificate of General Weighted Average (GWA)', 'Hardcopy', 'READY', NULL, NULL, NULL, '2026-11-19', NULL, '2026-11-16 10:12:46', NULL, '2026-11-16 10:16:11', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `request_deletion_audit`
--

CREATE TABLE `request_deletion_audit` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tracking_no` varchar(30) NOT NULL,
  `document_name` varchar(150) NOT NULL,
  `final_status` varchar(30) NOT NULL,
  `requested_at` datetime NOT NULL,
  `deleted_at` datetime NOT NULL,
  `deleted_by_name` varchar(150) NOT NULL,
  `status_history` longtext NOT NULL,
  `purged_at` datetime DEFAULT NULL,
  `restored_at` datetime DEFAULT NULL,
  `restored_by_name` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `request_deletion_audit`
--

INSERT INTO `request_deletion_audit` (`id`, `tracking_no`, `document_name`, `final_status`, `requested_at`, `deleted_at`, `deleted_by_name`, `status_history`, `purged_at`, `restored_at`, `restored_by_name`) VALUES
(7, 'REQ-B155D20FCE314A65A22003EF', 'Transcript of Records (TOR)', 'PROCESSING', '2026-11-13 11:58:04', '2026-11-13 17:42:40', 'Admin', '[{\"old_status\":null,\"new_status\":\"SUBMITTED\",\"created_at\":\"2026-11-13 11:58:04\",\"changed_by_name\":\"Registrar staff\"},{\"old_status\":\"SUBMITTED\",\"new_status\":\"PROCESSING\",\"created_at\":\"2026-11-13 12:15:08\",\"changed_by_name\":\"Admin\"},{\"old_status\":\"PROCESSING\",\"new_status\":\"PROCESSING\",\"created_at\":\"2026-11-13 12:16:30\",\"changed_by_name\":\"Admin\"}]', NULL, NULL, NULL),
(8, 'REQ-73A1B115BF219F976429070D', 'Transcript of Records (TOR)', 'SUBMITTED', '2026-11-13 11:53:19', '2026-11-13 17:43:00', 'Admin', '[{\"old_status\":null,\"new_status\":\"SUBMITTED\",\"created_at\":\"2026-11-13 11:53:19\",\"changed_by_name\":\"Registrar staff\"}]', NULL, NULL, NULL),
(9, 'REQ-A5B044935835469460F9BE52', 'Transcript of Records (TOR)', 'SUBMITTED', '2026-11-13 11:18:37', '2026-11-16 08:49:20', 'Admin', '[{\"old_status\":null,\"new_status\":\"SUBMITTED\",\"created_at\":\"2026-11-13 11:18:37\",\"changed_by_name\":\"Registrar staff\"}]', NULL, NULL, NULL),
(10, 'REQ-4D950B0BD47B13B18AC4EBE3', 'Transcript of Records (TOR)', 'SUBMITTED', '2026-11-13 11:12:37', '2026-11-16 08:49:36', 'Admin', '[{\"old_status\":null,\"new_status\":\"SUBMITTED\",\"created_at\":\"2026-11-13 11:12:37\",\"changed_by_name\":\"Registrar staff\"}]', NULL, NULL, NULL),
(11, 'REQ-8A7406E6D6CFA4115E208ED4', 'Certificate of Good Moral Character', 'REJECTED', '2026-11-13 08:28:29', '2026-11-16 08:49:46', 'Admin', '[{\"old_status\":null,\"new_status\":\"SUBMITTED\",\"created_at\":\"2026-11-13 08:28:29\",\"changed_by_name\":\"Registrar staff\"},{\"old_status\":\"SUBMITTED\",\"new_status\":\"PROCESSING\",\"created_at\":\"2026-11-13 08:29:54\",\"changed_by_name\":\"Admin\"},{\"old_status\":\"PROCESSING\",\"new_status\":\"PROCESSING\",\"created_at\":\"2026-11-13 08:29:59\",\"changed_by_name\":\"Admin\"},{\"old_status\":\"PROCESSING\",\"new_status\":\"REJECTED\",\"created_at\":\"2026-11-13 08:30:38\",\"changed_by_name\":\"Admin\"}]', NULL, NULL, NULL),
(12, 'REQ-C6207599C447035EAC920928', 'Transcript of Records (TOR)', 'REJECTED', '2026-11-13 11:04:20', '2026-11-16 08:49:53', 'Admin', '[{\"old_status\":null,\"new_status\":\"SUBMITTED\",\"created_at\":\"2026-11-13 11:04:20\",\"changed_by_name\":\"Registrar staff\"},{\"old_status\":\"SUBMITTED\",\"new_status\":\"REJECTED\",\"created_at\":\"2026-11-13 11:05:28\",\"changed_by_name\":\"Admin\"}]', NULL, NULL, NULL),
(13, 'REQ-05BAFCFDAFDD58BEDBFBB797', 'Transcript of Records (TOR)', 'SUBMITTED', '2026-11-13 10:27:48', '2026-11-16 08:50:00', 'Admin', '[{\"old_status\":null,\"new_status\":\"SUBMITTED\",\"created_at\":\"2026-11-13 10:27:48\",\"changed_by_name\":\"Registrar staff\"}]', NULL, NULL, NULL),
(14, 'REQ-226FF91960A37FDD0962335F', 'Certificate of Enrollment', 'PROCESSING', '2026-11-16 09:06:23', '2026-11-16 09:27:10', 'Admin', '[{\"old_status\":null,\"new_status\":\"SUBMITTED\",\"created_at\":\"2026-11-16 09:06:23\",\"changed_by_name\":\"Registrar staff\"},{\"old_status\":\"SUBMITTED\",\"new_status\":\"PROCESSING\",\"created_at\":\"2026-11-16 09:10:12\",\"changed_by_name\":\"Admin\"}]', NULL, NULL, NULL),
(15, 'REQ-8FD4FECE56822A09BF862058', 'Certificate of General Weighted Average (GWA)', 'PROCESSING', '2026-11-16 09:04:04', '2026-11-16 09:28:55', 'Admin', '[{\"old_status\":null,\"new_status\":\"SUBMITTED\",\"created_at\":\"2026-11-16 09:04:04\",\"changed_by_name\":\"Registrar staff\"},{\"old_status\":\"SUBMITTED\",\"new_status\":\"PROCESSING\",\"created_at\":\"2026-11-16 09:17:00\",\"changed_by_name\":\"Admin\"}]', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `request_history`
--

CREATE TABLE `request_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `changed_by` int(10) UNSIGNED DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `request_history`
--

INSERT INTO `request_history` (`id`, `request_id`, `old_status`, `new_status`, `changed_by`, `remarks`, `created_at`) VALUES
(65, 14, NULL, 'SUBMITTED', NULL, 'Student submitted request.', '2026-11-13 08:28:29'),
(66, 14, 'SUBMITTED', 'PROCESSING', 3, 'Expected release date: 2026-11-14.', '2026-11-13 08:29:54'),
(67, 14, 'PROCESSING', 'PROCESSING', 3, 'Expected release date updated to 2026-11-14.', '2026-11-13 08:29:59'),
(68, 14, 'PROCESSING', 'REJECTED', 3, 'May kaba', '2026-11-13 08:30:38'),
(69, 15, NULL, 'SUBMITTED', NULL, 'Student submitted request.', '2026-11-13 10:27:48'),
(70, 17, NULL, 'SUBMITTED', NULL, 'Student submitted request.', '2026-11-13 11:04:20'),
(71, 17, 'SUBMITTED', 'REJECTED', 3, 'Nakakaramdam ako ng kaba', '2026-11-13 11:05:28'),
(72, 18, NULL, 'SUBMITTED', NULL, 'Student submitted request.', '2026-11-13 11:12:37'),
(73, 19, NULL, 'SUBMITTED', NULL, 'Student submitted request.', '2026-11-13 11:18:37'),
(74, 20, NULL, 'SUBMITTED', NULL, 'Student submitted request.', '2026-11-13 11:53:19'),
(75, 21, NULL, 'SUBMITTED', NULL, 'Student submitted request.', '2026-11-13 11:58:04'),
(76, 21, 'SUBMITTED', 'PROCESSING', 3, 'Expected release date: 2026-11-14.', '2026-11-13 12:15:08'),
(77, 21, 'PROCESSING', 'PROCESSING', 3, 'Expected release date updated to 2026-11-15.', '2026-11-13 12:16:30'),
(84, 27, NULL, 'SUBMITTED', NULL, 'Student submitted request.', '2026-11-16 09:04:04'),
(85, 28, NULL, 'SUBMITTED', NULL, 'Student submitted request.', '2026-11-16 09:06:23'),
(86, 28, 'SUBMITTED', 'PROCESSING', 3, 'Processing started with an estimated release date of 2026-11-16 based on the document processing time of same day.', '2026-11-16 09:10:12'),
(87, 27, 'SUBMITTED', 'PROCESSING', 3, 'Processing started with an estimated release date of 2026-11-19 based on the document processing time of 3 weekdays.', '2026-11-16 09:17:00'),
(88, 29, NULL, 'SUBMITTED', NULL, 'Student submitted request.', '2026-11-16 10:12:46'),
(89, 29, 'SUBMITTED', 'PROCESSING', 3, 'Processing started with an estimated release date of 2026-11-19 based on the document processing time of 3 weekdays.', '2026-11-16 10:15:40'),
(90, 29, 'PROCESSING', 'READY', 3, 'Document is ready for pickup.', '2026-11-16 10:16:11');

-- --------------------------------------------------------

--
-- Table structure for table `request_items`
--

CREATE TABLE `request_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `quantity` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `purpose` varchar(255) DEFAULT NULL,
  `softcopy_status` enum('NONE','UPLOADED','READY') NOT NULL DEFAULT 'NONE',
  `softcopy_uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `softcopy_uploaded_at` datetime DEFAULT NULL,
  `softcopy_ready_at` datetime DEFAULT NULL,
  `download_limit` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `download_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `last_downloaded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `request_items`
--

INSERT INTO `request_items` (`id`, `request_id`, `document_id`, `quantity`, `purpose`, `softcopy_status`, `softcopy_uploaded_by`, `softcopy_uploaded_at`, `softcopy_ready_at`, `download_limit`, `download_count`, `last_downloaded_at`) VALUES
(14, 14, 6, 1, 'Certificate of Good Moral Character', 'NONE', NULL, NULL, NULL, 1, 0, NULL),
(15, 15, 1, 1, 'Transcript of Records (TOR)', 'NONE', NULL, NULL, NULL, 1, 0, NULL),
(17, 17, 1, 1, 'Transcript of Records (TOR)', 'NONE', NULL, NULL, NULL, 1, 0, NULL),
(18, 18, 1, 1, 'Transcript of Records (TOR)', 'NONE', NULL, NULL, NULL, 1, 0, NULL),
(19, 19, 1, 1, 'Transcript of Records (TOR)', 'NONE', NULL, NULL, NULL, 1, 0, NULL),
(20, 20, 1, 1, 'Transcript of Records (TOR)', 'NONE', NULL, NULL, NULL, 1, 0, NULL),
(21, 21, 1, 1, 'Transcript of Records (TOR)', 'NONE', NULL, NULL, NULL, 1, 0, NULL),
(22, 27, 8, 1, 'Certificate of General Weighted Average (GWA)', 'NONE', NULL, NULL, NULL, 1, 0, NULL),
(23, 28, 3, 1, 'Certificate of Enrollment', 'NONE', NULL, NULL, NULL, 1, 0, NULL),
(24, 29, 8, 1, 'Certificate of General Weighted Average (GWA)', 'NONE', NULL, NULL, NULL, 1, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `school_courses`
--

CREATE TABLE `school_courses` (
  `id` int(10) UNSIGNED NOT NULL,
  `level_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `acronym` varchar(20) NOT NULL DEFAULT '',
  `duration_years` tinyint(3) UNSIGNED NOT NULL DEFAULT 4,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_courses`
--

INSERT INTO `school_courses` (`id`, `level_id`, `name`, `acronym`, `duration_years`, `is_active`, `sort_order`) VALUES
(1, 1, 'Tourism Management', 'BSTM', 4, 1, 1),
(2, 1, 'Accountancy', 'BSA', 4, 1, 2),
(3, 1, 'Accounting Information System', 'BSAIS', 4, 1, 3),
(4, 1, 'Financial Management', 'BSFM', 4, 1, 4),
(5, 1, 'Marketing Management', 'BSMM', 4, 1, 5),
(6, 1, 'Entrepreneurship', 'BSEntrep', 4, 1, 6),
(7, 1, 'Information Technology', 'BSIT', 4, 1, 7),
(8, 1, 'Computer Science', 'BSCS', 4, 1, 8),
(9, 1, 'Criminology', 'BSCRIM', 4, 1, 9),
(10, 1, 'Hospitality Management', 'BSHM', 4, 1, 10),
(11, 1, 'Food and Service Management', 'BTVTE', 4, 1, 11),
(12, 1, 'Home Economics', 'BTVTE', 4, 1, 12),
(13, 1, 'Animal and Crop Science', 'BTVTE', 4, 1, 13),
(14, 1, 'Home Economics', 'BTLE', 4, 1, 14),
(15, 1, 'Agri-Fishery', 'BTLE', 4, 1, 15),
(16, 1, 'Radiologic Technology', 'BSRT', 4, 1, 16),
(17, 2, 'STEM', '', 4, 0, 1),
(18, 2, 'HUMSS', '', 4, 0, 2);

-- --------------------------------------------------------

--
-- Table structure for table `school_levels`
--

CREATE TABLE `school_levels` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_levels`
--

INSERT INTO `school_levels` (`id`, `name`, `is_active`, `sort_order`) VALUES
(1, 'COLLEGE', 1, 1),
(2, 'SENIOR HIGH SCHOOL', 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `school_year_levels`
--

CREATE TABLE `school_year_levels` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `year_number` tinyint(3) UNSIGNED NOT NULL,
  `label` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_year_levels`
--

INSERT INTO `school_year_levels` (`id`, `course_id`, `year_number`, `label`, `is_active`) VALUES
(1, 1, 1, '1st year', 1),
(2, 1, 2, '2nd year', 1),
(3, 1, 3, '3rd year', 1),
(4, 1, 4, '4th year', 1),
(5, 2, 1, 'First Year', 1),
(6, 2, 2, 'Second Year', 1),
(7, 2, 3, 'Third Year', 1),
(8, 2, 4, 'Fourth Year', 1),
(9, 3, 1, '1st year', 1),
(10, 3, 2, '2nd year', 1),
(11, 3, 3, '3rd year', 1),
(12, 3, 4, '4th year', 1),
(13, 4, 1, '1st year', 1),
(14, 4, 2, '2nd year', 1),
(15, 4, 3, '3rd year', 1),
(16, 4, 4, '4th year', 1),
(17, 5, 1, '1st year', 1),
(18, 5, 2, '2nd year', 1),
(19, 5, 3, '3rd year', 1),
(20, 5, 4, '4th year', 1),
(21, 6, 1, 'First Year', 1),
(22, 6, 2, 'Second Year', 1),
(23, 6, 3, 'Third Year', 1),
(24, 6, 4, 'Fourth Year', 1),
(25, 7, 1, 'First Year', 1),
(26, 7, 2, 'Second Year', 1),
(27, 7, 3, 'Third Year', 1),
(28, 7, 4, 'Fourth Year', 1),
(29, 8, 1, 'First Year', 1),
(30, 8, 2, 'Second Year', 1),
(31, 8, 3, 'Third Year', 1),
(32, 8, 4, 'Fourth Year', 1),
(33, 9, 1, 'First Year', 1),
(34, 9, 2, 'Second Year', 1),
(35, 9, 3, 'Third Year', 1),
(36, 9, 4, 'Fourth Year', 1),
(37, 10, 1, 'First Year', 1),
(38, 10, 2, 'Second Year', 1),
(39, 10, 3, 'Third Year', 1),
(40, 10, 4, 'Fourth Year', 1),
(41, 11, 1, 'First Year', 1),
(42, 11, 2, 'Second Year', 1),
(43, 11, 3, 'Third Year', 1),
(44, 11, 4, 'Fourth Year', 1),
(45, 12, 1, 'First Year', 1),
(46, 12, 2, 'Second Year', 1),
(47, 12, 3, 'Third Year', 1),
(48, 12, 4, 'Fourth Year', 1),
(49, 13, 1, 'First Year', 1),
(50, 13, 2, 'Second Year', 1),
(51, 13, 3, 'Third Year', 1),
(52, 13, 4, 'Fourth Year', 1),
(53, 14, 1, 'First Year', 1),
(54, 14, 2, 'Second Year', 1),
(55, 14, 3, 'Third Year', 1),
(56, 14, 4, 'Fourth Year', 1),
(57, 15, 1, 'First Year', 1),
(58, 15, 2, 'Second Year', 1),
(59, 15, 3, 'Third Year', 1),
(60, 15, 4, 'Fourth Year', 1),
(61, 16, 1, 'First Year', 1),
(62, 16, 2, 'Second Year', 1),
(63, 16, 3, 'Third Year', 1),
(64, 16, 4, 'Fourth Year', 1),
(65, 17, 11, 'Grade 11', 1),
(66, 17, 12, 'Grade 12', 1),
(67, 18, 11, 'Grade 11', 1),
(68, 18, 12, 'Grade 12', 1);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `course` varchar(80) DEFAULT NULL,
  `year_level` tinyint(3) UNSIGNED DEFAULT NULL,
  `status` enum('currently_enrolled','alumni','undergrad') NOT NULL DEFAULT 'currently_enrolled',
  `academic_year` varchar(9) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `course`, `year_level`, `status`, `academic_year`, `created_at`) VALUES
(1, 1, 'BSTM', 3, 'currently_enrolled', NULL, '2026-10-25 13:20:50'),
(2, 4, 'BSTM', 4, 'currently_enrolled', '2026-2027', '2026-10-25 13:23:27'),
(3, 5, 'BSIT - Information Technology', 4, 'currently_enrolled', '2026-2027', '2026-10-25 21:35:48'),
(4, 6, 'BSIT - Information Technology', 4, 'currently_enrolled', '2026-2027', '2026-10-27 14:31:37'),
(5, 7, 'BTVTE - Animal and Crop Science', 2, 'currently_enrolled', NULL, '2026-10-29 12:49:28'),
(6, 8, 'BTVTE - Home Economics', 2, 'currently_enrolled', NULL, '2026-10-29 12:54:43'),
(7, 9, 'BTVTE - Animal and Crop Science', 1, 'currently_enrolled', NULL, '2026-10-29 12:58:15'),
(8, 10, 'BSCRIM - Criminology', 2, 'currently_enrolled', NULL, '2026-10-29 13:03:29'),
(9, 11, 'BTVTE - Home Economics', 2, 'currently_enrolled', NULL, '2026-10-29 13:07:04'),
(10, 12, 'BTVTE - Animal and Crop Science', 2, 'currently_enrolled', NULL, '2026-10-29 13:09:25'),
(11, 13, 'BTLE - Home Economics', 1, 'currently_enrolled', NULL, '2026-10-29 13:23:54'),
(12, 14, 'BTVTE - Animal and Crop Science', 1, 'currently_enrolled', NULL, '2026-10-29 13:24:23'),
(13, 15, 'BTVTE - Home Economics', 2, 'currently_enrolled', NULL, '2026-10-29 13:27:38'),
(14, 16, 'BSCRIM - Criminology', 1, 'currently_enrolled', NULL, '2026-10-29 13:30:46'),
(15, 17, 'BTLE - Home Economics', 2, 'currently_enrolled', NULL, '2026-10-29 14:16:03'),
(16, 18, 'BTVTE - Animal and Crop Science', 1, 'currently_enrolled', NULL, '2026-10-29 14:18:01'),
(17, 19, 'BTVTE - Home Economics', 1, 'currently_enrolled', NULL, '2026-10-29 14:22:01'),
(18, 20, 'BTVTE - Animal and Crop Science', 2, 'currently_enrolled', NULL, '2026-10-29 14:25:01'),
(19, 21, 'BTVTE - Animal and Crop Science', 1, 'currently_enrolled', NULL, '2026-10-29 14:26:53');

-- --------------------------------------------------------

--
-- Table structure for table `student_email_verifications`
--

CREATE TABLE `student_email_verifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `email` varchar(254) NOT NULL,
  `verified_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_enrollments`
--

CREATE TABLE `student_enrollments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `import_batch_id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `year_level` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `imported_student_number` varchar(64) DEFAULT NULL,
  `imported_first_name` varchar(100) NOT NULL,
  `imported_middle_name` varchar(100) DEFAULT NULL,
  `imported_surname` varchar(100) NOT NULL,
  `imported_email` varchar(254) NOT NULL,
  `enrollment_status` enum('currently_enrolled','not_currently_enrolled','transferred_out','dropped_out','alumni') NOT NULL DEFAULT 'currently_enrolled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_enrollments`
--

INSERT INTO `student_enrollments` (`id`, `student_id`, `import_batch_id`, `course_id`, `year_level`, `imported_student_number`, `imported_first_name`, `imported_middle_name`, `imported_surname`, `imported_email`, `enrollment_status`, `created_at`) VALUES
(1, 1, 1, 5, 3, '2026-98732', 'Alimon', 'Sarip', 'Abubakar', 'alimonabubakar26@gmail.com', 'currently_enrolled', '2026-11-12 13:23:07'),
(2, 2, 1, 5, 3, '2026-17583', 'Andrew', 'Constantino', 'Mapilisan', 'andrewmapilisan@gmail.com', 'currently_enrolled', '2026-11-12 13:23:07'),
(3, 3, 1, 5, 3, '2026-09897', 'Mark Jennon', 'Iwayan', 'Gatchalian', 'mark.jennon.gatchalian.16@gmail.com', 'currently_enrolled', '2026-11-12 13:23:07'),
(4, 4, 1, 5, 3, '2026-10001', 'Juliana', 'Capule', 'Yadao', 'juli4na.yada0@gmail.com', 'currently_enrolled', '2026-11-12 13:23:07'),
(5, 5, 1, 5, 3, '2026-12002', 'John Emerson', 'Baltazar', 'Larida', 'janemersonlarida@gmail.com', 'currently_enrolled', '2026-11-12 13:23:07'),
(6, 5, 2, 5, 3, '2026-12002', 'John Emerson', 'Baltazar', 'Larida', 'janemersonlarida@gmail.com', 'currently_enrolled', '2026-11-13 06:46:59'),
(7, 1, 3, 5, 3, '2026-98732', 'Alimon', 'Sarip', 'Abubakar', 'alimonabubakar26@gmail.com', 'currently_enrolled', '2026-11-13 06:48:40'),
(8, 2, 3, 5, 3, '2026-17583', 'Andrew', 'Constantino', 'Mapilisan', 'andrewmapilisan@gmail.com', 'currently_enrolled', '2026-11-13 06:48:40'),
(9, 3, 3, 5, 3, '2026-09897', 'Mark Jennon', 'Iwayan', 'Gatchalian', 'mark.jennon.gatchalian.16@gmail.com', 'currently_enrolled', '2026-11-13 06:48:40'),
(10, 4, 3, 5, 3, '2026-10001', 'Juliana', 'Capule', 'Yadao', 'juli4na.yada0@gmail.com', 'currently_enrolled', '2026-11-13 06:48:40'),
(11, 5, 3, 5, 3, '2026-12002', 'John Emerson', 'Baltazar', 'Larida', 'janemersonlarida@gmail.com', 'currently_enrolled', '2026-11-13 06:48:40');

-- --------------------------------------------------------

--
-- Table structure for table `student_import_batches`
--

CREATE TABLE `student_import_batches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `academic_term_id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED DEFAULT NULL,
  `source` enum('csv','manual') NOT NULL DEFAULT 'csv',
  `original_filename` varchar(255) DEFAULT NULL,
  `imported_by_user_id` int(10) UNSIGNED NOT NULL,
  `year_level` tinyint(3) UNSIGNED NOT NULL,
  `row_count` int(10) UNSIGNED NOT NULL,
  `skipped_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `failed_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `import_status` enum('completed','completed_with_errors','failed') NOT NULL DEFAULT 'completed',
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `imported_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_import_batches`
--

INSERT INTO `student_import_batches` (`id`, `academic_term_id`, `course_id`, `source`, `original_filename`, `imported_by_user_id`, `year_level`, `row_count`, `skipped_rows`, `failed_rows`, `total_rows`, `import_status`, `is_current`, `imported_at`) VALUES
(1, 1, 5, 'csv', NULL, 3, 3, 5, 0, 0, 5, 'completed', 0, '2026-11-12 13:23:07'),
(2, 2, 5, 'csv', NULL, 3, 3, 1, 0, 0, 1, 'completed', 0, '2026-11-13 06:46:59'),
(3, 2, 5, 'csv', NULL, 3, 3, 5, 0, 0, 5, 'completed', 1, '2026-11-13 06:48:40');

-- --------------------------------------------------------

--
-- Table structure for table `student_import_rows`
--

CREATE TABLE `student_import_rows` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED NOT NULL,
  `row_number` int(10) UNSIGNED NOT NULL,
  `student_identifier` varchar(64) DEFAULT NULL,
  `source_data` longtext DEFAULT NULL,
  `processing_status` enum('imported','skipped','failed','review') NOT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `associated_student_id` bigint(20) UNSIGNED DEFAULT NULL,
  `associated_enrollment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_profile_audit`
--

CREATE TABLE `student_profile_audit` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `import_batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` enum('created','updated') NOT NULL,
  `source` enum('csv','manual') NOT NULL,
  `changed_by_name` varchar(150) NOT NULL,
  `changes_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_registry`
--

CREATE TABLE `student_registry` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_number` varchar(64) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `surname` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `email` varchar(254) NOT NULL,
  `contact_number` varchar(40) DEFAULT NULL,
  `classification` enum('not_currently_enrolled','transferred_out','dropped_out','alumni') NOT NULL DEFAULT 'not_currently_enrolled',
  `graduation_year` smallint(5) UNSIGNED DEFAULT NULL,
  `last_attended_academic_term_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_registry`
--

INSERT INTO `student_registry` (`id`, `student_number`, `first_name`, `middle_name`, `surname`, `suffix`, `email`, `contact_number`, `classification`, `graduation_year`, `last_attended_academic_term_id`, `created_at`, `updated_at`) VALUES
(1, '2026-98732', 'Alimon', 'Sarip', 'Abubakar', NULL, 'alimonabubakar26@gmail.com', NULL, 'not_currently_enrolled', NULL, NULL, '2026-11-12 13:23:07', '2026-11-13 06:48:40'),
(2, '2026-17583', 'Andrew', 'Constantino', 'Mapilisan', NULL, 'andrewmapilisan@gmail.com', NULL, 'not_currently_enrolled', NULL, NULL, '2026-11-12 13:23:07', '2026-11-12 13:23:07'),
(3, '2026-09897', 'Mark Jennon', 'Iwayan', 'Gatchalian', NULL, 'mark.jennon.gatchalian.16@gmail.com', NULL, 'not_currently_enrolled', NULL, NULL, '2026-11-12 13:23:07', '2026-11-12 13:23:07'),
(4, '2026-10001', 'Juliana', 'Capule', 'Yadao', NULL, 'juli4na.yada0@gmail.com', NULL, 'not_currently_enrolled', NULL, NULL, '2026-11-12 13:23:07', '2026-11-12 13:23:07'),
(5, '2026-12002', 'John Emerson', 'Baltazar', 'Larida', NULL, 'janemersonlarida@gmail.com', NULL, 'not_currently_enrolled', NULL, NULL, '2026-11-12 13:23:07', '2026-11-13 06:48:40');

-- --------------------------------------------------------

--
-- Table structure for table `student_request_otps`
--

CREATE TABLE `student_request_otps` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(254) NOT NULL,
  `code_hash` char(64) NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_request_otps`
--

INSERT INTO `student_request_otps` (`id`, `user_id`, `document_id`, `email`, `code_hash`, `attempts`, `expires_at`, `used_at`, `created_at`) VALUES
(2, NULL, 4, 'mark.jennon.gatchalian.16@gmail.com', '19dc2089627dda1a282eca408f992438517622fe753f62d1b85bb7ebfd96e344', 0, '2026-11-11 09:18:28', '2026-11-11 16:08:31', '2026-11-11 16:08:28'),
(3, NULL, 2, 'janemersonlarida@gmail.com', '5f24c4ebd14e2d97559cdf8c2bcdf249976fc09085e87f1a446e603155a2208f', 0, '2026-11-12 14:47:58', '2026-11-12 21:38:01', '2026-11-12 21:37:58'),
(4, NULL, 2, 'janemersonlarida@gmail.com', 'dd22dc9470315351f7a0db8978d63134ade3bd8e2e73eaa0d0e8c2a07191b5ed', 0, '2026-11-12 14:48:01', '2026-11-12 21:38:04', '2026-11-12 21:38:01'),
(5, NULL, 3, 'janemersonlarida@gmail.com', 'c8697455e755c17eca1585521222f36dd0a50fddfccfb4aac87bce41de74e1a1', 0, '2026-11-12 14:48:36', '2026-11-12 21:38:39', '2026-11-12 21:38:36'),
(6, NULL, 2, 'mark.jennon.gatchalian.16@gmail.com', '44470ad7d1fbf749cb9f47df348c9b52be3bc5d0eeed8669aac69381352813ae', 0, '2026-11-13 01:26:42', '2026-11-13 08:16:45', '2026-11-13 08:16:42'),
(7, NULL, 2, 'mark.jennon.gatchalian.16@gmail.com', 'e1334228ee2e6245d3048be1d2eb33ad108c7b02341be096362878e8d4f54694', 0, '2026-11-13 01:31:07', NULL, '2026-11-13 08:21:07'),
(8, NULL, 6, 'mark.jennon.gatchalian.16@gmail.com', '8624560cce95b9e5bf890d8381da6218fd3f74632b839653775e8bd78ac4951f', 0, '2026-11-13 08:37:49', '2026-11-13 08:28:29', '2026-11-13 08:27:49'),
(9, NULL, 3, 'mark.jennon.gatchalian.16@gmail.com', '126aa8bb2c348c49a5b385fd14974012969db5b8ec4678197d95bcc21b31cac2', 0, '2026-11-13 08:56:07', NULL, '2026-11-13 08:46:07'),
(10, NULL, 1, 'mark.jennon.gatchalian.16@gmail.com', 'fc6efa5229d0fe847792fa787d8e3803c1f9879493a2aa67dadc6fa8b50a570a', 0, '2026-11-13 10:37:22', '2026-11-13 10:27:48', '2026-11-13 10:27:22'),
(11, NULL, 1, 'mark.jennon.gatchalian.16@gmail.com', '7d8e91e741da9a2e0fc744bc5c2e1b9430ce8bef277af992dfe0352dcca8ce29', 0, '2026-11-13 11:11:11', '2026-11-13 11:01:53', '2026-11-13 11:01:11'),
(12, NULL, 1, 'mark.jennon.gatchalian.16@gmail.com', '4be5337067e77239f314d9ac00938219f958dc3019a93d346c7990cd1fba5071', 0, '2026-11-13 11:13:55', '2026-11-13 11:04:20', '2026-11-13 11:03:55'),
(13, NULL, 1, 'mark.jennon.gatchalian.16@gmail.com', '50140930e2cf3683db53829f2fa72b3c9d996922959150e0137f6d7ea831f1de', 0, '2026-11-13 11:22:12', '2026-11-13 11:12:37', '2026-11-13 11:12:12'),
(14, NULL, 1, 'mark.jennon.gatchalian.16@gmail.com', '63dcb3aea46672d0c0a234df1dfa01ba84d1289acae5e6b6c2ce76f9eb0243b2', 0, '2026-11-13 11:28:10', '2026-11-13 11:18:37', '2026-11-13 11:18:10'),
(15, NULL, 1, 'juli4na.yada0@gmail.com', '951346943714e6adf038ca0554de32093d0c0d1bbd2400e6cead0010f2daf93e', 0, '2026-11-13 12:02:46', '2026-11-13 11:53:19', '2026-11-13 11:52:46'),
(16, NULL, 1, 'mark.jennon.gatchalian.16@gmail.com', '6dcdc9f82729535d352bc491882294813eef9c00279e8d7febc1e2593630a975', 0, '2026-11-13 12:06:52', NULL, '2026-11-13 11:56:52'),
(17, NULL, 1, 'mark.jennon.gatchalian.16@gmail.com', '821f4e7f7236bb31f6686bb613fa5516207c0daf920d0728c522ea66ef54a990', 2, '2026-11-13 12:06:56', '2026-11-13 11:58:04', '2026-11-13 11:56:56'),
(18, NULL, 8, 'janemersonlarida@gmail.com', '9d5c1130f3d836e624b6ac0b5726ff8ece1f772e462f2a648418a796e1ce5d1a', 0, '2026-11-16 09:00:56', NULL, '2026-11-16 08:50:56'),
(19, NULL, 8, 'janemersonlarida@gmail.com', '02019172eea9d93054c0bb0b4d2d4a6b314df08825670a2d6e0a0df4161f76db', 0, '2026-11-16 09:06:42', NULL, '2026-11-16 08:56:42'),
(20, NULL, 8, 'janemersonlarida@gmail.com', '6369eaa56433dc5c0b23e33a5b58e9739885ee9d6d36e46c4ff1d2a46f033479', 0, '2026-11-16 09:09:17', NULL, '2026-11-16 08:59:17'),
(21, NULL, 8, 'janemersonlarida@gmail.com', 'c57891afc3a42593b82b3510ac9bc8b8a96569db071face6ec2816770a0748d4', 0, '2026-11-16 09:12:04', NULL, '2026-11-16 09:02:04'),
(22, NULL, 8, 'janemersonlarida@gmail.com', 'eaa89f17f4d97f580866384c74cb687463275a60dfc0f597592524c35db779bc', 1, '2026-11-16 09:13:16', '2026-11-16 09:04:04', '2026-11-16 09:03:16'),
(23, NULL, 3, 'janemersonlarida@gmail.com', '41626ddaecaeab2e19634db7b061be27fdc887b401d466d3b75ef9184571c99e', 0, '2026-11-16 09:16:05', '2026-11-16 09:06:23', '2026-11-16 09:06:05'),
(24, NULL, 8, 'laridajohnemerson7@gmail.com', '01f6602e0a36710949f7fad359de324b22fcf95d23cfeef175ef6b96db195c5f', 0, '2026-11-16 10:21:43', NULL, '2026-11-16 10:11:43'),
(25, NULL, 8, 'laridajohnemerson7@gmail.com', '861966a2f646f1bd491b988f26ea30f3c73f5f662d06776ad8754fdba59b9bf7', 0, '2026-11-16 10:21:47', '2026-11-16 10:12:46', '2026-11-16 10:11:47');

-- --------------------------------------------------------

--
-- Table structure for table `student_roster_corrections`
--

CREATE TABLE `student_roster_corrections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `membership_id` bigint(20) UNSIGNED NOT NULL,
  `correction_type` enum('delete','move') NOT NULL,
  `from_course_id` bigint(20) UNSIGNED NOT NULL,
  `from_year_level` tinyint(3) UNSIGNED NOT NULL,
  `to_course_id` bigint(20) UNSIGNED DEFAULT NULL,
  `to_year_level` tinyint(3) UNSIGNED DEFAULT NULL,
  `corrected_by_name` varchar(150) NOT NULL,
  `corrected_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_roster_memberships`
--

CREATE TABLE `student_roster_memberships` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `import_batch_id` bigint(20) UNSIGNED NOT NULL,
  `enrollment_id` bigint(20) UNSIGNED NOT NULL,
  `academic_term_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `year_level` tinyint(3) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_roster_memberships`
--

INSERT INTO `student_roster_memberships` (`id`, `import_batch_id`, `enrollment_id`, `academic_term_id`, `student_id`, `course_id`, `year_level`, `is_active`, `created_at`) VALUES
(1, 3, 7, 2, 1, 5, 3, 1, '2026-11-13 08:29:52'),
(2, 3, 8, 2, 2, 5, 3, 1, '2026-11-13 08:29:52'),
(3, 3, 9, 2, 3, 5, 3, 1, '2026-11-13 08:29:52'),
(4, 3, 10, 2, 4, 5, 3, 1, '2026-11-13 08:29:52'),
(5, 3, 11, 2, 5, 5, 3, 1, '2026-11-13 08:29:52');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `role` enum('student','registrar','admin') NOT NULL,
  `student_no` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_approved` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `password_changed_at`, `full_name`, `email`, `role`, `student_no`, `is_active`, `is_approved`, `created_at`) VALUES
(1, '2026-00001', '$2y$10$zxXbQ2L40T3XuSARyhz5v.OWcTLIJZN7FHlMrVvSLKz48ZCZY9us.', '2026-10-25 13:20:50', 'Juan Dela Cruz', NULL, 'student', '2026-00001', 1, 1, '2026-10-25 13:20:50'),
(2, 'registrar', '$2y$10$zxXbQ2L40T3XuSARyhz5v.OWcTLIJZN7FHlMrVvSLKz48ZCZY9us.', '2026-10-25 13:20:50', 'Registrar', NULL, 'registrar', NULL, 1, 1, '2026-10-25 13:20:50'),
(3, 'admin', '$2y$10$QQedVkNz711NzT0JZs.OHOpV3OTVHgTBPteLVuozvA/vVK9MDgTlC', '2026-11-13 08:25:28', 'Admin', NULL, 'admin', NULL, 1, 1, '2026-10-25 13:20:50'),
(4, '2026-10001', '$2y$10$aN.oOMAytRaqIYeKiwcu0OdERJa5sh0dHodl09Z/JyjcwqW4YQ3Ga', '2026-10-25 22:13:46', 'Kisha Nicole Guino-o', 'labitankisha@gmail.com', 'student', '2026-10001', 1, 1, '2026-10-25 13:23:27'),
(5, '2026-00002', '$2y$10$Mh7Mx8b9bsHJPJt36uGR2OSaa/9.UFrRTZ/VcjO8nBgiIS570UVDu', '2026-10-25 21:35:48', 'Juliana Yadao', 'juli4na.yada0@gmail.com', 'student', '2026-00002', 1, 1, '2026-10-25 21:35:48'),
(6, '2026-10002', '$2y$10$9GHrbof0kijvPXH3EMEYMOXCYjgwCGVnWvyDf4yM8VgV.FTyHFtNG', '2026-10-27 14:31:37', 'Juliana Yadao', 'colm.document.requests@gmail.com', 'student', '2026-10002', 1, 1, '2026-10-27 14:31:37'),
(7, 'guest-2c06084f913978e4', '$2y$10$CVcKztzBI/isA/n.POWV4OiuV8v7SmhJ9NE/1bzfXKcFgYdhue3lq', '2026-10-29 12:49:28', 'Juliana Yadao', 'juli4na.yada0@gmail.com', 'student', '2026-10023', 1, 1, '2026-10-29 12:49:28'),
(8, 'guest-92943b308f55e05f', '$2y$10$ZK9AV3vkfi0d4Zfwxsk9s.RQTKBGw5EcpPa2Mvm8wP9D3yiKVemju', '2026-10-29 12:54:43', 'Juliana Yadao', 'changcapule@gmail.com', 'student', '2026-20021', 1, 1, '2026-10-29 12:54:43'),
(9, 'guest-40268d195675b15b', '$2y$10$gxfjEjERVioTlhR3TK2bSumtPwZEUfPfIaCl3koHGCyDlGzGLYDgS', '2026-10-29 12:58:15', 'Juliana Yadao', 'juli4na.yada0@gmail.com', 'student', '2026-99811', 1, 1, '2026-10-29 12:58:15'),
(10, 'guest-c9fcad23ebcca2cc', '$2y$10$gBMMhWeFizWMCk7YtQ1heuRoltywFaiidW7JXfRKpLQ9vtbpdKjFe', '2026-10-29 13:03:29', 'Juliana Yadao', 'changcapule@gmail.com', 'student', '2026-12345', 1, 1, '2026-10-29 13:03:29'),
(11, 'guest-8a4042b9f5f8e0d7', '$2y$10$I119BAWM5BpF9zn0OwxEBeYGXqdOE7k2F7cTe66s8HfUGR3ceZYT6', '2026-10-29 13:07:04', 'Juliana Yadao', 'juli4na.yada0@gmail.com', 'student', '2026-13245', 1, 1, '2026-10-29 13:07:04'),
(12, 'guest-36d31888b0ff9e32', '$2y$10$cTGyWgcwBx59dvXRaP/kmu.V5vIMsDyv6sfpmuK8/djKrR5tPoBba', '2026-10-29 13:09:25', 'juliana yadao', 'changcapule@gmail.com', 'student', '2026-14325', 1, 1, '2026-10-29 13:09:25'),
(13, 'guest-be3d52cb7e798f0f', '$2y$10$yaaJME5mc0pyLPBRaIf6AOrl/hf0NKKJSmiyCrXC63hrgWorHPD66', '2026-10-29 13:23:54', 'Juliana Yadao', 'juli4na.yada0@gmail.com', 'student', '2026-16789', 1, 1, '2026-10-29 13:23:54'),
(14, 'guest-60e3c391dfa5351d', '$2y$10$218c38ultrBOQIwqRK/Io.V53LDbCcV504s3jgVNUWxGvHlvxKsJC', '2026-10-29 13:24:23', 'Juliana Yadao', 'changcapule@gmail.com', 'student', '2026-09876', 1, 1, '2026-10-29 13:24:23'),
(15, 'guest-9caaabe8a0baa91f', '$2y$10$JOFwV.As5C7hOQiRD4QgNu9OjONVnkFJbTZw1FyosevuFhvHXd.m2', '2026-10-29 13:27:38', 'Juliana Yadao', 'juli4na.yada0@gmail.com', 'student', '2012-09872', 1, 1, '2026-10-29 13:27:38'),
(16, 'guest-45c41485c3f98f11', '$2y$10$HmZR.ls/M48Nmmuk2acqvOcz4qeoNsrYzfajtKEafZ6AiE7AMfgBm', '2026-10-29 13:30:46', 'Juliana Yadao', 'changcapule@gmail.com', 'student', '2098-98644', 1, 1, '2026-10-29 13:30:46'),
(17, 'guest-4495973624bde985', '$2y$10$jHcrRFKoEP6JyHEXbbc7V.8Lrgc9eDM613b14Uv3vNYy6TaVXG9pC', '2026-10-29 14:16:03', 'Juliana h', 'jy@gmail.com', 'student', '204329879', 1, 1, '2026-10-29 14:16:03'),
(18, 'guest-0125745cc4c20846', '$2y$10$tNBCfi.zE0bDcu9RNSP/vu2FWB7KRaTJYgMAY7Dbo3L35JKO2hQOO', '2026-10-29 14:18:01', 'Juliana Yadao', 'juli4na.yada0@gmail.com', 'student', '5435334', 1, 1, '2026-10-29 14:18:01'),
(19, 'guest-9fcc7c8a84ab3877', '$2y$10$7o3M5PHdkKF0afUvVe8ULeITV.gEYF94IILJeBVKwH710eJFHRhRq', '2026-10-29 14:22:01', 'Juliana y', 'changcapule@gmail.com', 'student', '34646364', 1, 1, '2026-10-29 14:22:01'),
(20, 'guest-f38918cfafbfb751', '$2y$10$.uYR2lhKNZ8FPKgA5Ng4uep9MUPhUzQ2oZhmKtzzOAc/vgzFGthRK', '2026-10-29 14:25:01', 'Juliana Juliana', 'juli4na.yada0@gmail.com', 'student', '7987979', 1, 1, '2026-10-29 14:25:01'),
(21, 'guest-3ce7cff697ef3cff', '$2y$10$CnxHi/I3pdgWn7RNa3tv7eMBxW.sH8GKau3jF08djqS6JDL3.ZYzy', '2026-10-29 14:26:53', 'Juliana y', 'changcapule@gmail.com', 'student', '297947', 1, 1, '2026-10-29 14:26:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_terms`
--
ALTER TABLE `academic_terms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_academic_terms_year_semester` (`academic_year_id`,`semester`),
  ADD KEY `ix_academic_terms_current` (`is_current`);

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_academic_years_range` (`start_year`,`end_year`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_courses_name` (`name`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_documents_name` (`name`);

--
-- Indexes for table `document_requirements`
--
ALTER TABLE `document_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_req_document` (`document_id`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`template_key`),
  ADD KEY `fk_email_template_editor` (`updated_by`);

--
-- Indexes for table `guest_request_details`
--
ALTER TABLE `guest_request_details`
  ADD PRIMARY KEY (`request_id`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`bucket`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_requests_tracking` (`tracking_no`),
  ADD UNIQUE KEY `uq_requests_submission` (`submission_token`),
  ADD KEY `idx_requests_student` (`student_id`),
  ADD KEY `idx_requests_status` (`status`),
  ADD KEY `ix_requests_deleted_at` (`deleted_at`),
  ADD KEY `ix_requests_student_registry` (`student_registry_id`);

--
-- Indexes for table `request_deletion_audit`
--
ALTER TABLE `request_deletion_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_request_deletion_audit_tracking` (`tracking_no`),
  ADD KEY `ix_request_deletion_audit_deleted` (`deleted_at`),
  ADD KEY `ix_request_deletion_audit_purged` (`purged_at`);

--
-- Indexes for table `request_history`
--
ALTER TABLE `request_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_history_user` (`changed_by`),
  ADD KEY `idx_history_request` (`request_id`);

--
-- Indexes for table `request_items`
--
ALTER TABLE `request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_items_uploader` (`softcopy_uploaded_by`),
  ADD KEY `fk_items_document` (`document_id`),
  ADD KEY `idx_items_request` (`request_id`);

--
-- Indexes for table `school_courses`
--
ALTER TABLE `school_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_school_courses_level_acronym_name` (`level_id`,`acronym`,`name`);

--
-- Indexes for table `school_levels`
--
ALTER TABLE `school_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_levels_name` (`name`);

--
-- Indexes for table `school_year_levels`
--
ALTER TABLE `school_year_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_years_course` (`course_id`,`year_number`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_students_user` (`user_id`),
  ADD KEY `idx_students_course` (`course`);

--
-- Indexes for table `student_email_verifications`
--
ALTER TABLE `student_email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_email_verifications_user_email` (`user_id`,`email`);

--
-- Indexes for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_enrollments_batch_student` (`import_batch_id`,`student_id`),
  ADD KEY `ix_student_enrollments_student` (`student_id`),
  ADD KEY `fk_student_enrollments_course` (`course_id`);

--
-- Indexes for table `student_import_batches`
--
ALTER TABLE `student_import_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_student_import_batches_term_current` (`academic_term_id`,`is_current`),
  ADD KEY `ix_student_import_batches_imported_at` (`imported_at`),
  ADD KEY `fk_student_import_batches_user` (`imported_by_user_id`),
  ADD KEY `ix_student_import_batches_course_roster` (`academic_term_id`,`course_id`,`year_level`,`is_current`),
  ADD KEY `fk_student_import_batches_course` (`course_id`);

--
-- Indexes for table `student_import_rows`
--
ALTER TABLE `student_import_rows`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_import_rows_batch_row` (`batch_id`,`row_number`),
  ADD KEY `ix_student_import_rows_status` (`batch_id`,`processing_status`),
  ADD KEY `fk_student_import_rows_student` (`associated_student_id`),
  ADD KEY `fk_student_import_rows_enrollment` (`associated_enrollment_id`);

--
-- Indexes for table `student_profile_audit`
--
ALTER TABLE `student_profile_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_student_profile_audit_student` (`student_id`,`created_at`),
  ADD KEY `fk_student_profile_audit_batch` (`import_batch_id`);

--
-- Indexes for table `student_registry`
--
ALTER TABLE `student_registry`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_registry_number` (`student_number`),
  ADD KEY `ix_student_registry_surname_first_name` (`surname`,`first_name`),
  ADD KEY `ix_student_registry_classification` (`classification`),
  ADD KEY `fk_student_registry_last_term` (`last_attended_academic_term_id`);

--
-- Indexes for table `student_request_otps`
--
ALTER TABLE `student_request_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_student_request_otps_email_created` (`email`,`created_at`),
  ADD KEY `fk_student_request_otps_user` (`user_id`),
  ADD KEY `fk_student_request_otps_document` (`document_id`);

--
-- Indexes for table `student_roster_corrections`
--
ALTER TABLE `student_roster_corrections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_student_roster_corrections_membership` (`membership_id`,`corrected_at`),
  ADD KEY `fk_student_roster_corrections_from_course` (`from_course_id`),
  ADD KEY `fk_student_roster_corrections_to_course` (`to_course_id`);

--
-- Indexes for table `student_roster_memberships`
--
ALTER TABLE `student_roster_memberships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_roster_memberships_enrollment` (`enrollment_id`),
  ADD KEY `ix_student_roster_memberships_term_student` (`academic_term_id`,`student_id`,`is_active`),
  ADD KEY `ix_student_roster_memberships_course` (`academic_term_id`,`course_id`,`is_active`),
  ADD KEY `fk_student_roster_memberships_batch` (`import_batch_id`),
  ADD KEY `fk_student_roster_memberships_student` (`student_id`),
  ADD KEY `fk_student_roster_memberships_course` (`course_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_student_no` (`student_no`),
  ADD KEY `idx_users_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_terms`
--
ALTER TABLE `academic_terms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1419;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `document_requirements`
--
ALTER TABLE `document_requirements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `request_deletion_audit`
--
ALTER TABLE `request_deletion_audit`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `request_history`
--
ALTER TABLE `request_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `request_items`
--
ALTER TABLE `request_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `school_courses`
--
ALTER TABLE `school_courses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `school_levels`
--
ALTER TABLE `school_levels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `school_year_levels`
--
ALTER TABLE `school_year_levels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `student_email_verifications`
--
ALTER TABLE `student_email_verifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `student_import_batches`
--
ALTER TABLE `student_import_batches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_import_rows`
--
ALTER TABLE `student_import_rows`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_profile_audit`
--
ALTER TABLE `student_profile_audit`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_registry`
--
ALTER TABLE `student_registry`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `student_request_otps`
--
ALTER TABLE `student_request_otps`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `student_roster_corrections`
--
ALTER TABLE `student_roster_corrections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_roster_memberships`
--
ALTER TABLE `student_roster_memberships`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_terms`
--
ALTER TABLE `academic_terms`
  ADD CONSTRAINT `fk_academic_terms_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`);

--
-- Constraints for table `document_requirements`
--
ALTER TABLE `document_requirements`
  ADD CONSTRAINT `fk_req_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD CONSTRAINT `fk_email_template_editor` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `guest_request_details`
--
ALTER TABLE `guest_request_details`
  ADD CONSTRAINT `fk_guest_request_details_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `fk_requests_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_requests_student_registry` FOREIGN KEY (`student_registry_id`) REFERENCES `student_registry` (`id`);

--
-- Constraints for table `request_history`
--
ALTER TABLE `request_history`
  ADD CONSTRAINT `fk_history_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `request_items`
--
ALTER TABLE `request_items`
  ADD CONSTRAINT `fk_items_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  ADD CONSTRAINT `fk_items_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_items_uploader` FOREIGN KEY (`softcopy_uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `school_courses`
--
ALTER TABLE `school_courses`
  ADD CONSTRAINT `fk_courses_level` FOREIGN KEY (`level_id`) REFERENCES `school_levels` (`id`);

--
-- Constraints for table `school_year_levels`
--
ALTER TABLE `school_year_levels`
  ADD CONSTRAINT `fk_years_course` FOREIGN KEY (`course_id`) REFERENCES `school_courses` (`id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_email_verifications`
--
ALTER TABLE `student_email_verifications`
  ADD CONSTRAINT `fk_student_email_verifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD CONSTRAINT `fk_student_enrollments_batch` FOREIGN KEY (`import_batch_id`) REFERENCES `student_import_batches` (`id`),
  ADD CONSTRAINT `fk_student_enrollments_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  ADD CONSTRAINT `fk_student_enrollments_registry` FOREIGN KEY (`student_id`) REFERENCES `student_registry` (`id`);

--
-- Constraints for table `student_import_batches`
--
ALTER TABLE `student_import_batches`
  ADD CONSTRAINT `fk_student_import_batches_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  ADD CONSTRAINT `fk_student_import_batches_term` FOREIGN KEY (`academic_term_id`) REFERENCES `academic_terms` (`id`),
  ADD CONSTRAINT `fk_student_import_batches_user` FOREIGN KEY (`imported_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `student_import_rows`
--
ALTER TABLE `student_import_rows`
  ADD CONSTRAINT `fk_student_import_rows_batch` FOREIGN KEY (`batch_id`) REFERENCES `student_import_batches` (`id`),
  ADD CONSTRAINT `fk_student_import_rows_enrollment` FOREIGN KEY (`associated_enrollment_id`) REFERENCES `student_enrollments` (`id`),
  ADD CONSTRAINT `fk_student_import_rows_student` FOREIGN KEY (`associated_student_id`) REFERENCES `student_registry` (`id`);

--
-- Constraints for table `student_profile_audit`
--
ALTER TABLE `student_profile_audit`
  ADD CONSTRAINT `fk_student_profile_audit_batch` FOREIGN KEY (`import_batch_id`) REFERENCES `student_import_batches` (`id`),
  ADD CONSTRAINT `fk_student_profile_audit_student` FOREIGN KEY (`student_id`) REFERENCES `student_registry` (`id`);

--
-- Constraints for table `student_registry`
--
ALTER TABLE `student_registry`
  ADD CONSTRAINT `fk_student_registry_last_term` FOREIGN KEY (`last_attended_academic_term_id`) REFERENCES `academic_terms` (`id`);

--
-- Constraints for table `student_request_otps`
--
ALTER TABLE `student_request_otps`
  ADD CONSTRAINT `fk_student_request_otps_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  ADD CONSTRAINT `fk_student_request_otps_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_roster_corrections`
--
ALTER TABLE `student_roster_corrections`
  ADD CONSTRAINT `fk_student_roster_corrections_from_course` FOREIGN KEY (`from_course_id`) REFERENCES `courses` (`id`),
  ADD CONSTRAINT `fk_student_roster_corrections_membership` FOREIGN KEY (`membership_id`) REFERENCES `student_roster_memberships` (`id`),
  ADD CONSTRAINT `fk_student_roster_corrections_to_course` FOREIGN KEY (`to_course_id`) REFERENCES `courses` (`id`);

--
-- Constraints for table `student_roster_memberships`
--
ALTER TABLE `student_roster_memberships`
  ADD CONSTRAINT `fk_student_roster_memberships_batch` FOREIGN KEY (`import_batch_id`) REFERENCES `student_import_batches` (`id`),
  ADD CONSTRAINT `fk_student_roster_memberships_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  ADD CONSTRAINT `fk_student_roster_memberships_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `student_enrollments` (`id`),
  ADD CONSTRAINT `fk_student_roster_memberships_student` FOREIGN KEY (`student_id`) REFERENCES `student_registry` (`id`),
  ADD CONSTRAINT `fk_student_roster_memberships_term` FOREIGN KEY (`academic_term_id`) REFERENCES `academic_terms` (`id`);
--
-- Database: `phpmyadmin`
--
CREATE DATABASE IF NOT EXISTS `phpmyadmin` DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;
USE `phpmyadmin`;

-- --------------------------------------------------------

--
-- Table structure for table `pma__bookmark`
--

CREATE TABLE `pma__bookmark` (
  `id` int(10) UNSIGNED NOT NULL,
  `dbase` varchar(255) NOT NULL DEFAULT '',
  `user` varchar(255) NOT NULL DEFAULT '',
  `label` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `query` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Bookmarks';

-- --------------------------------------------------------

--
-- Table structure for table `pma__central_columns`
--

CREATE TABLE `pma__central_columns` (
  `db_name` varchar(64) NOT NULL,
  `col_name` varchar(64) NOT NULL,
  `col_type` varchar(64) NOT NULL,
  `col_length` text DEFAULT NULL,
  `col_collation` varchar(64) NOT NULL,
  `col_isNull` tinyint(1) NOT NULL,
  `col_extra` varchar(255) DEFAULT '',
  `col_default` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Central list of columns';

-- --------------------------------------------------------

--
-- Table structure for table `pma__column_info`
--

CREATE TABLE `pma__column_info` (
  `id` int(5) UNSIGNED NOT NULL,
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `column_name` varchar(64) NOT NULL DEFAULT '',
  `comment` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `mimetype` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `transformation` varchar(255) NOT NULL DEFAULT '',
  `transformation_options` varchar(255) NOT NULL DEFAULT '',
  `input_transformation` varchar(255) NOT NULL DEFAULT '',
  `input_transformation_options` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Column information for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__designer_settings`
--

CREATE TABLE `pma__designer_settings` (
  `username` varchar(64) NOT NULL,
  `settings_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Settings related to Designer';

-- --------------------------------------------------------

--
-- Table structure for table `pma__export_templates`
--

CREATE TABLE `pma__export_templates` (
  `id` int(5) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL,
  `export_type` varchar(10) NOT NULL,
  `template_name` varchar(64) NOT NULL,
  `template_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Saved export templates';

-- --------------------------------------------------------

--
-- Table structure for table `pma__favorite`
--

CREATE TABLE `pma__favorite` (
  `username` varchar(64) NOT NULL,
  `tables` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Favorite tables';

-- --------------------------------------------------------

--
-- Table structure for table `pma__history`
--

CREATE TABLE `pma__history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL DEFAULT '',
  `db` varchar(64) NOT NULL DEFAULT '',
  `table` varchar(64) NOT NULL DEFAULT '',
  `timevalue` timestamp NOT NULL DEFAULT current_timestamp(),
  `sqlquery` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='SQL history for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__navigationhiding`
--

CREATE TABLE `pma__navigationhiding` (
  `username` varchar(64) NOT NULL,
  `item_name` varchar(64) NOT NULL,
  `item_type` varchar(64) NOT NULL,
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Hidden items of navigation tree';

-- --------------------------------------------------------

--
-- Table structure for table `pma__pdf_pages`
--

CREATE TABLE `pma__pdf_pages` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `page_nr` int(10) UNSIGNED NOT NULL,
  `page_descr` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='PDF relation pages for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__recent`
--

CREATE TABLE `pma__recent` (
  `username` varchar(64) NOT NULL,
  `tables` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Recently accessed tables';

--
-- Dumping data for table `pma__recent`
--

INSERT INTO `pma__recent` (`username`, `tables`) VALUES
('root', '[{\"db\":\"colm_dbs\",\"table\":\"students\"},{\"db\":\"colm_dbs\",\"table\":\"users\"},{\"db\":\"colm_dbs\",\"table\":\"ai_verifications\"}]');

-- --------------------------------------------------------

--
-- Table structure for table `pma__relation`
--

CREATE TABLE `pma__relation` (
  `master_db` varchar(64) NOT NULL DEFAULT '',
  `master_table` varchar(64) NOT NULL DEFAULT '',
  `master_field` varchar(64) NOT NULL DEFAULT '',
  `foreign_db` varchar(64) NOT NULL DEFAULT '',
  `foreign_table` varchar(64) NOT NULL DEFAULT '',
  `foreign_field` varchar(64) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Relation table';

-- --------------------------------------------------------

--
-- Table structure for table `pma__savedsearches`
--

CREATE TABLE `pma__savedsearches` (
  `id` int(5) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL DEFAULT '',
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `search_name` varchar(64) NOT NULL DEFAULT '',
  `search_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Saved searches';

-- --------------------------------------------------------

--
-- Table structure for table `pma__table_coords`
--

CREATE TABLE `pma__table_coords` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `pdf_page_number` int(11) NOT NULL DEFAULT 0,
  `x` float UNSIGNED NOT NULL DEFAULT 0,
  `y` float UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Table coordinates for phpMyAdmin PDF output';

-- --------------------------------------------------------

--
-- Table structure for table `pma__table_info`
--

CREATE TABLE `pma__table_info` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `display_field` varchar(64) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Table information for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__table_uiprefs`
--

CREATE TABLE `pma__table_uiprefs` (
  `username` varchar(64) NOT NULL,
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `prefs` text NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Tables'' UI preferences';

-- --------------------------------------------------------

--
-- Table structure for table `pma__tracking`
--

CREATE TABLE `pma__tracking` (
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `version` int(10) UNSIGNED NOT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime NOT NULL,
  `schema_snapshot` text NOT NULL,
  `schema_sql` text DEFAULT NULL,
  `data_sql` longtext DEFAULT NULL,
  `tracking` set('UPDATE','REPLACE','INSERT','DELETE','TRUNCATE','CREATE DATABASE','ALTER DATABASE','DROP DATABASE','CREATE TABLE','ALTER TABLE','RENAME TABLE','DROP TABLE','CREATE INDEX','DROP INDEX','CREATE VIEW','ALTER VIEW','DROP VIEW') DEFAULT NULL,
  `tracking_active` int(1) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Database changes tracking for phpMyAdmin';

-- --------------------------------------------------------

--
-- Table structure for table `pma__userconfig`
--

CREATE TABLE `pma__userconfig` (
  `username` varchar(64) NOT NULL,
  `timevalue` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `config_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='User preferences storage for phpMyAdmin';

--
-- Dumping data for table `pma__userconfig`
--

INSERT INTO `pma__userconfig` (`username`, `timevalue`, `config_data`) VALUES
('root', '2026-11-16 02:36:34', '{\"Console\\/Mode\":\"collapse\",\"NavigationWidth\":0}');

-- --------------------------------------------------------

--
-- Table structure for table `pma__usergroups`
--

CREATE TABLE `pma__usergroups` (
  `usergroup` varchar(64) NOT NULL,
  `tab` varchar(64) NOT NULL,
  `allowed` enum('Y','N') NOT NULL DEFAULT 'N'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='User groups with configured menu items';

-- --------------------------------------------------------

--
-- Table structure for table `pma__users`
--

CREATE TABLE `pma__users` (
  `username` varchar(64) NOT NULL,
  `usergroup` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Users and their assignments to user groups';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `pma__bookmark`
--
ALTER TABLE `pma__bookmark`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pma__central_columns`
--
ALTER TABLE `pma__central_columns`
  ADD PRIMARY KEY (`db_name`,`col_name`);

--
-- Indexes for table `pma__column_info`
--
ALTER TABLE `pma__column_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `db_name` (`db_name`,`table_name`,`column_name`);

--
-- Indexes for table `pma__designer_settings`
--
ALTER TABLE `pma__designer_settings`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `pma__export_templates`
--
ALTER TABLE `pma__export_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_user_type_template` (`username`,`export_type`,`template_name`);

--
-- Indexes for table `pma__favorite`
--
ALTER TABLE `pma__favorite`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `pma__history`
--
ALTER TABLE `pma__history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`,`db`,`table`,`timevalue`);

--
-- Indexes for table `pma__navigationhiding`
--
ALTER TABLE `pma__navigationhiding`
  ADD PRIMARY KEY (`username`,`item_name`,`item_type`,`db_name`,`table_name`);

--
-- Indexes for table `pma__pdf_pages`
--
ALTER TABLE `pma__pdf_pages`
  ADD PRIMARY KEY (`page_nr`),
  ADD KEY `db_name` (`db_name`);

--
-- Indexes for table `pma__recent`
--
ALTER TABLE `pma__recent`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `pma__relation`
--
ALTER TABLE `pma__relation`
  ADD PRIMARY KEY (`master_db`,`master_table`,`master_field`),
  ADD KEY `foreign_field` (`foreign_db`,`foreign_table`);

--
-- Indexes for table `pma__savedsearches`
--
ALTER TABLE `pma__savedsearches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_savedsearches_username_dbname` (`username`,`db_name`,`search_name`);

--
-- Indexes for table `pma__table_coords`
--
ALTER TABLE `pma__table_coords`
  ADD PRIMARY KEY (`db_name`,`table_name`,`pdf_page_number`);

--
-- Indexes for table `pma__table_info`
--
ALTER TABLE `pma__table_info`
  ADD PRIMARY KEY (`db_name`,`table_name`);

--
-- Indexes for table `pma__table_uiprefs`
--
ALTER TABLE `pma__table_uiprefs`
  ADD PRIMARY KEY (`username`,`db_name`,`table_name`);

--
-- Indexes for table `pma__tracking`
--
ALTER TABLE `pma__tracking`
  ADD PRIMARY KEY (`db_name`,`table_name`,`version`);

--
-- Indexes for table `pma__userconfig`
--
ALTER TABLE `pma__userconfig`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `pma__usergroups`
--
ALTER TABLE `pma__usergroups`
  ADD PRIMARY KEY (`usergroup`,`tab`,`allowed`);

--
-- Indexes for table `pma__users`
--
ALTER TABLE `pma__users`
  ADD PRIMARY KEY (`username`,`usergroup`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `pma__bookmark`
--
ALTER TABLE `pma__bookmark`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__column_info`
--
ALTER TABLE `pma__column_info`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__export_templates`
--
ALTER TABLE `pma__export_templates`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__history`
--
ALTER TABLE `pma__history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__pdf_pages`
--
ALTER TABLE `pma__pdf_pages`
  MODIFY `page_nr` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__savedsearches`
--
ALTER TABLE `pma__savedsearches`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- Database: `test`
--
CREATE DATABASE IF NOT EXISTS `test` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `test`;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
