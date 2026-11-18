CREATE TABLE request_deletion_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tracking_no VARCHAR(30) NOT NULL,
    document_name VARCHAR(150) NOT NULL,
    final_status VARCHAR(30) NOT NULL,
    requested_at DATETIME NOT NULL,
    deleted_at DATETIME NOT NULL,
    deleted_by_name VARCHAR(150) NOT NULL,
    status_history LONGTEXT NOT NULL,
    purged_at DATETIME NULL,
    restored_at DATETIME NULL,
    restored_by_name VARCHAR(150) NULL,
    PRIMARY KEY (id),
    KEY ix_request_deletion_audit_tracking (tracking_no),
    KEY ix_request_deletion_audit_deleted (deleted_at),
    KEY ix_request_deletion_audit_purged (purged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE requests
    ADD COLUMN deleted_at DATETIME NULL,
    ADD COLUMN deletion_audit_id BIGINT UNSIGNED NULL,
    ADD KEY ix_requests_deleted_at (deleted_at);
