INSERT IGNORE INTO documents
    (name, description, price, processing_min_days, processing_max_days, release_methods, is_active, sort_order)
VALUES
    (
        'Transcript of Records (TOR)',
        'Official consolidated academic transcript covering all course units, grades, and graduation credentials.',
        250.00,
        5,
        7,
        'Hardcopy',
        1,
        1
    ),
    (
        'Honorable Dismissal',
        'Official document permitting a student to transfer out and enroll in another collegiate institution.',
        350.00,
        0,
        0,
        'Hardcopy',
        1,
        2
    );

INSERT IGNORE INTO document_requirements (document_id, name, description, sort_order)
SELECT id, 'Accomplished request form', 'Complete the online request form.', 1
FROM documents
WHERE name IN ('Transcript of Records (TOR)', 'Honorable Dismissal');
