CREATE TABLE courses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_courses_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE academic_years (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    start_year SMALLINT UNSIGNED NOT NULL,
    end_year SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academic_years_range (start_year, end_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE academic_terms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academic_terms_year_semester (academic_year_id, semester),
    KEY ix_academic_terms_current (is_current),
    CONSTRAINT fk_academic_terms_year
        FOREIGN KEY (academic_year_id) REFERENCES academic_years (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_registry (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_number VARCHAR(64) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    surname VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_registry_number (student_number),
    KEY ix_student_registry_surname_first_name (surname, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_import_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_term_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NULL,
    imported_by_personnel_id BIGINT UNSIGNED NOT NULL,
    year_level TINYINT UNSIGNED NOT NULL,
    row_count INT UNSIGNED NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_student_import_batches_term_current (academic_term_id, is_current),
    KEY ix_student_import_batches_course_roster (academic_term_id, course_id, year_level, is_current),
    KEY ix_student_import_batches_imported_at (imported_at),
    CONSTRAINT fk_student_import_batches_term
        FOREIGN KEY (academic_term_id) REFERENCES academic_terms (id),
    CONSTRAINT fk_student_import_batches_course
        FOREIGN KEY (course_id) REFERENCES courses (id),
    CONSTRAINT fk_student_import_batches_personnel
        FOREIGN KEY (imported_by_personnel_id) REFERENCES personnel_accounts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_enrollments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    import_batch_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_enrollments_batch_student (import_batch_id, student_id),
    KEY ix_student_enrollments_student (student_id),
    CONSTRAINT fk_student_enrollments_student
        FOREIGN KEY (student_id) REFERENCES student_registry (id),
    CONSTRAINT fk_student_enrollments_batch
        FOREIGN KEY (import_batch_id) REFERENCES student_import_batches (id),
    CONSTRAINT fk_student_enrollments_course
        FOREIGN KEY (course_id) REFERENCES courses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
