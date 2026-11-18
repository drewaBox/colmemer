DROP TABLE IF EXISTS ai_verifications;
DROP TABLE IF EXISTS upload_checks;
DROP TABLE IF EXISTS softcopy_downloads;
DROP TABLE IF EXISTS validation_records;
DROP TABLE IF EXISTS request_signatures;
DROP TABLE IF EXISTS request_verifications;
DROP TABLE IF EXISTS guest_otps;
DROP TABLE IF EXISTS students_non_college_backup;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS email_log;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS master_list_imports;
DROP TABLE IF EXISTS upload_attempts;
DROP TABLE IF EXISTS password_reset_requests;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS request_files;
DROP TABLE IF EXISTS softcopy_download_requests;
DROP TABLE IF EXISTS legacy_payments_archive;
DROP TABLE IF EXISTS legacy_payment_receipts_archive;
DROP TABLE IF EXISTS requests_payment_archive;
DROP TABLE IF EXISTS request_items_price_archive;
DROP TABLE IF EXISTS notifications;

ALTER TABLE requests
    DROP FOREIGN KEY IF EXISTS fk_requests_student_registry,
    DROP COLUMN IF EXISTS student_registry_id,
    DROP COLUMN IF EXISTS year_graduated,
    DROP COLUMN IF EXISTS credential_form_json,
    DROP COLUMN IF EXISTS student_verification_form_json,
    DROP COLUMN IF EXISTS qr_code,
    DROP COLUMN IF EXISTS target_start,
    DROP COLUMN IF EXISTS target_end,
    DROP COLUMN IF EXISTS updated_at,
    DROP COLUMN IF EXISTS cancelled_at,
    DROP COLUMN IF EXISTS cancel_reason;

ALTER TABLE student_email_verifications
    DROP FOREIGN KEY IF EXISTS fk_student_email_verifications_registry,
    DROP INDEX IF EXISTS ix_student_email_verifications_registry,
    DROP INDEX IF EXISTS uq_student_email_verifications_registry_email,
    DROP COLUMN IF EXISTS student_registry_id;

ALTER TABLE student_request_otps
    DROP FOREIGN KEY IF EXISTS fk_student_request_otps_registry,
    DROP INDEX IF EXISTS ix_student_request_otps_registry,
    DROP COLUMN IF EXISTS student_registry_id;

ALTER TABLE students
    DROP COLUMN IF EXISTS school_level,
    DROP COLUMN IF EXISTS contact_no,
    DROP COLUMN IF EXISTS photo_stored,
    DROP COLUMN IF EXISTS photo_mime;

ALTER TABLE users
    DROP COLUMN IF EXISTS audit_name,
    DROP COLUMN IF EXISTS must_change_password,
    DROP COLUMN IF EXISTS failed_logins,
    DROP COLUMN IF EXISTS locked_until;

ALTER TABLE documents
    DROP COLUMN IF EXISTS category;
