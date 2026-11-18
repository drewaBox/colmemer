ALTER TABLE student_enrollments
    ADD COLUMN year_level TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER course_id,
    ADD COLUMN imported_student_number VARCHAR(64) NULL AFTER year_level,
    ADD COLUMN imported_first_name VARCHAR(100) NULL AFTER imported_student_number,
    ADD COLUMN imported_middle_name VARCHAR(100) NULL AFTER imported_first_name,
    ADD COLUMN imported_surname VARCHAR(100) NULL AFTER imported_middle_name,
    ADD COLUMN imported_email VARCHAR(254) NULL AFTER imported_surname;

UPDATE student_enrollments e
JOIN student_import_batches b ON b.id = e.import_batch_id
JOIN student_registry s ON s.id = e.student_id
SET e.year_level = b.year_level,
    e.imported_student_number = s.student_number,
    e.imported_first_name = s.first_name,
    e.imported_middle_name = s.middle_name,
    e.imported_surname = s.surname,
    e.imported_email = s.email;

ALTER TABLE student_enrollments
    MODIFY COLUMN imported_student_number VARCHAR(64) NOT NULL,
    MODIFY COLUMN imported_first_name VARCHAR(100) NOT NULL,
    MODIFY COLUMN imported_surname VARCHAR(100) NOT NULL,
    MODIFY COLUMN imported_email VARCHAR(254) NOT NULL;

CREATE TABLE student_roster_memberships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    import_batch_id BIGINT UNSIGNED NOT NULL,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    academic_term_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NOT NULL,
    year_level TINYINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_roster_memberships_enrollment (enrollment_id),
    KEY ix_student_roster_memberships_term_student (academic_term_id, student_id, is_active),
    KEY ix_student_roster_memberships_course (academic_term_id, course_id, is_active),
    CONSTRAINT fk_student_roster_memberships_batch
        FOREIGN KEY (import_batch_id) REFERENCES student_import_batches (id),
    CONSTRAINT fk_student_roster_memberships_enrollment
        FOREIGN KEY (enrollment_id) REFERENCES student_enrollments (id),
    CONSTRAINT fk_student_roster_memberships_term
        FOREIGN KEY (academic_term_id) REFERENCES academic_terms (id),
    CONSTRAINT fk_student_roster_memberships_student
        FOREIGN KEY (student_id) REFERENCES student_registry (id),
    CONSTRAINT fk_student_roster_memberships_course
        FOREIGN KEY (course_id) REFERENCES courses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO student_roster_memberships
    (import_batch_id, enrollment_id, academic_term_id, student_id, course_id, year_level, is_active)
SELECT b.id, e.id, b.academic_term_id, e.student_id, e.course_id, e.year_level, 1
FROM student_enrollments e
JOIN student_import_batches b ON b.id = e.import_batch_id AND b.is_current = 1
JOIN academic_terms t ON t.id = b.academic_term_id AND t.is_current = 1;

CREATE TABLE student_roster_corrections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    membership_id BIGINT UNSIGNED NOT NULL,
    correction_type ENUM('delete', 'move') NOT NULL,
    from_course_id BIGINT UNSIGNED NOT NULL,
    from_year_level TINYINT UNSIGNED NOT NULL,
    to_course_id BIGINT UNSIGNED NULL,
    to_year_level TINYINT UNSIGNED NULL,
    corrected_by_name VARCHAR(150) NOT NULL,
    corrected_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_student_roster_corrections_membership (membership_id, corrected_at),
    CONSTRAINT fk_student_roster_corrections_membership
        FOREIGN KEY (membership_id) REFERENCES student_roster_memberships (id),
    CONSTRAINT fk_student_roster_corrections_from_course
        FOREIGN KEY (from_course_id) REFERENCES courses (id),
    CONSTRAINT fk_student_roster_corrections_to_course
        FOREIGN KEY (to_course_id) REFERENCES courses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
