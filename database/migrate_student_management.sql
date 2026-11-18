ALTER TABLE student_registry
    ADD COLUMN suffix VARCHAR(20) NULL AFTER surname,
    ADD COLUMN contact_number VARCHAR(40) NULL AFTER email,
    ADD COLUMN classification ENUM(
        'not_currently_enrolled',
        'transferred_out',
        'dropped_out',
        'alumni'
    ) NOT NULL DEFAULT 'not_currently_enrolled' AFTER contact_number,
    ADD COLUMN graduation_year SMALLINT UNSIGNED NULL AFTER classification,
    ADD COLUMN last_attended_academic_term_id BIGINT UNSIGNED NULL AFTER graduation_year,
    ADD KEY ix_student_registry_classification (classification),
    ADD CONSTRAINT fk_student_registry_last_term
        FOREIGN KEY (last_attended_academic_term_id) REFERENCES academic_terms (id);

ALTER TABLE student_import_batches
    ADD COLUMN source ENUM('csv', 'manual') NOT NULL DEFAULT 'csv' AFTER course_id,
    ADD COLUMN original_filename VARCHAR(255) NULL AFTER source,
    ADD COLUMN skipped_rows INT UNSIGNED NOT NULL DEFAULT 0 AFTER row_count,
    ADD COLUMN failed_rows INT UNSIGNED NOT NULL DEFAULT 0 AFTER skipped_rows;

CREATE TABLE student_import_rows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    row_number INT UNSIGNED NOT NULL,
    student_identifier VARCHAR(64) NULL,
    processing_status ENUM('imported', 'skipped', 'failed', 'review') NOT NULL,
    error_message VARCHAR(500) NULL,
    associated_student_id BIGINT UNSIGNED NULL,
    associated_enrollment_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_import_rows_batch_row (batch_id, row_number),
    KEY ix_student_import_rows_status (batch_id, processing_status),
    CONSTRAINT fk_student_import_rows_batch
        FOREIGN KEY (batch_id) REFERENCES student_import_batches (id),
    CONSTRAINT fk_student_import_rows_student
        FOREIGN KEY (associated_student_id) REFERENCES student_registry (id),
    CONSTRAINT fk_student_import_rows_enrollment
        FOREIGN KEY (associated_enrollment_id) REFERENCES student_enrollments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
