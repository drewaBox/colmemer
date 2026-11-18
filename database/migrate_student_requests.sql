CREATE TABLE requests_payment_archive (
    request_id INT UNSIGNED NOT NULL,
    payment_method ENUM('Cash', 'GCash') NOT NULL,
    payment_term ENUM('UPON_RELEASE', 'ADVANCE') NOT NULL,
    payment_status ENUM('Unpaid', 'Paid', 'Refunded') NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO requests_payment_archive
    (request_id, payment_method, payment_term, payment_status, total_amount)
SELECT id, payment_method, payment_term, payment_status, total_amount
FROM requests;

CREATE TABLE request_items_price_archive (
    item_id INT UNSIGNED NOT NULL,
    request_id INT UNSIGNED NOT NULL,
    document_id INT UNSIGNED NOT NULL,
    quantity TINYINT UNSIGNED NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id),
    KEY ix_request_items_price_archive_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO request_items_price_archive (item_id, request_id, document_id, quantity, unit_price)
SELECT id, request_id, document_id, quantity, unit_price
FROM request_items;

RENAME TABLE
    payments TO legacy_payments_archive,
    payment_receipts TO legacy_payment_receipts_archive;

ALTER TABLE requests
    DROP INDEX idx_requests_ready,
    DROP COLUMN payment_method,
    DROP COLUMN payment_term,
    DROP COLUMN payment_status,
    DROP COLUMN total_amount;

ALTER TABLE request_items
    DROP COLUMN unit_price;

CREATE TABLE student_email_verifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    email VARCHAR(120) NOT NULL,
    verified_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_email_verifications_user_email (user_id, email),
    CONSTRAINT fk_student_email_verifications_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_request_otps (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    document_id INT UNSIGNED NOT NULL,
    email VARCHAR(120) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_student_request_otps_email_created (email, created_at),
    CONSTRAINT fk_student_request_otps_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_student_request_otps_document
        FOREIGN KEY (document_id) REFERENCES documents (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
