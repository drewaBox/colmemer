ALTER TABLE requests
    ADD COLUMN student_registry_id BIGINT UNSIGNED NULL AFTER student_id,
    ADD KEY ix_requests_student_registry (student_registry_id),
    ADD CONSTRAINT fk_requests_student_registry
        FOREIGN KEY (student_registry_id) REFERENCES student_registry (id);

UPDATE requests r
JOIN users u ON u.id = r.student_id
JOIN student_registry s ON s.student_number = u.student_no
SET r.student_registry_id = s.id
WHERE r.student_registry_id IS NULL;

UPDATE requests r
JOIN guest_request_details g ON g.request_id = r.id
JOIN student_registry s ON s.student_number = g.student_number
SET r.student_registry_id = s.id
WHERE r.student_registry_id IS NULL
  AND g.student_number IS NOT NULL
  AND g.student_number <> '';

UPDATE requests r
JOIN guest_request_details g ON g.request_id = r.id
JOIN (
    SELECT LOWER(email) AS normalized_email
    FROM student_registry
    GROUP BY LOWER(email)
    HAVING COUNT(*) = 1
) unique_email ON unique_email.normalized_email = LOWER(g.email)
JOIN student_registry s ON LOWER(s.email) = unique_email.normalized_email
SET r.student_registry_id = s.id
WHERE r.student_registry_id IS NULL
  AND (g.student_number IS NULL OR g.student_number = '');
