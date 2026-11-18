ALTER TABLE guest_request_details
    MODIFY requester_status ENUM('currently_enrolled', 'alumni', 'dropped_out', 'transferred_out') NOT NULL,
    ADD COLUMN IF NOT EXISTS student_number VARCHAR(64) NULL AFTER requester_status,
    MODIFY first_name VARCHAR(100) NOT NULL,
    MODIFY last_name VARCHAR(100) NOT NULL,
    MODIFY course VARCHAR(120) NOT NULL,
    MODIFY email VARCHAR(254) NOT NULL;
