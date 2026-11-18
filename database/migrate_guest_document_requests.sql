ALTER TABLE requests
    MODIFY student_id INT UNSIGNED NULL;

ALTER TABLE student_request_otps
    MODIFY user_id INT UNSIGNED NULL;

CREATE TABLE guest_request_details (
    request_id INT UNSIGNED NOT NULL,
    requester_status ENUM('currently_enrolled', 'alumni', 'dropped_out', 'transferred_out') NOT NULL,
    student_number VARCHAR(64) NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    course VARCHAR(120) NOT NULL,
    year_level VARCHAR(40) NOT NULL,
    academic_year VARCHAR(9) NOT NULL,
    email VARCHAR(254) NOT NULL,
    PRIMARY KEY (request_id),
    CONSTRAINT fk_guest_request_details_request
        FOREIGN KEY (request_id) REFERENCES requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
