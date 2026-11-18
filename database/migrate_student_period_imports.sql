ALTER TABLE student_registry
    MODIFY student_number VARCHAR(64) NULL;

ALTER TABLE student_import_batches
    ADD COLUMN total_rows INT UNSIGNED NOT NULL DEFAULT 0 AFTER failed_rows,
    ADD COLUMN import_status ENUM('completed', 'completed_with_errors', 'failed')
        NOT NULL DEFAULT 'completed' AFTER total_rows;

UPDATE student_import_batches
SET total_rows = row_count + skipped_rows + failed_rows,
    import_status = CASE
        WHEN failed_rows > 0 AND row_count = 0 THEN 'failed'
        WHEN skipped_rows > 0 OR failed_rows > 0 THEN 'completed_with_errors'
        ELSE 'completed'
    END;

ALTER TABLE student_enrollments
    MODIFY imported_student_number VARCHAR(64) NULL,
    ADD COLUMN enrollment_status ENUM(
        'currently_enrolled',
        'not_currently_enrolled',
        'transferred_out',
        'dropped_out',
        'alumni'
    ) NOT NULL DEFAULT 'currently_enrolled' AFTER imported_email;

CREATE TABLE student_profile_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    import_batch_id BIGINT UNSIGNED NULL,
    action ENUM('created', 'updated') NOT NULL,
    source ENUM('csv', 'manual') NOT NULL,
    changed_by_name VARCHAR(150) NOT NULL,
    changes_json LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_student_profile_audit_student (student_id, created_at),
    CONSTRAINT fk_student_profile_audit_student
        FOREIGN KEY (student_id) REFERENCES student_registry (id),
    CONSTRAINT fk_student_profile_audit_batch
        FOREIGN KEY (import_batch_id) REFERENCES student_import_batches (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
