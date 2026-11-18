ALTER TABLE student_import_batches
    ADD COLUMN course_id BIGINT UNSIGNED NULL AFTER academic_term_id,
    ADD KEY ix_student_import_batches_course_roster (academic_term_id, course_id, year_level, is_current),
    ADD CONSTRAINT fk_student_import_batches_course
        FOREIGN KEY (course_id) REFERENCES courses (id);

UPDATE student_import_batches b
JOIN (
    SELECT import_batch_id, MIN(course_id) AS course_id
    FROM student_enrollments
    GROUP BY import_batch_id
    HAVING COUNT(DISTINCT course_id) = 1
) e ON e.import_batch_id = b.id
SET b.course_id = e.course_id;
