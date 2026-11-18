CREATE TABLE IF NOT EXISTS email_templates (
    template_key VARCHAR(40) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (template_key),
    KEY fk_email_template_editor (updated_by),
    CONSTRAINT fk_email_template_editor
        FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @email_template_enabled_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_templates'
      AND COLUMN_NAME = 'is_enabled'
);
SET @email_template_enabled_column_migration = IF(
    @email_template_enabled_column_exists = 0,
    'ALTER TABLE email_templates ADD COLUMN is_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER body',
    'SET @email_template_enabled_column_exists = 1'
);
PREPARE email_template_enabled_column_statement FROM @email_template_enabled_column_migration;
EXECUTE email_template_enabled_column_statement;
DEALLOCATE PREPARE email_template_enabled_column_statement;

INSERT INTO email_templates (template_key, subject, body, is_enabled) VALUES
    (
        'verification_code',
        'Your registrar email verification code',
        'Your verification code is: {verification_code}\n\nThis code expires in {expiration_minutes} minutes and can only be used once.\nIf you did not request a document, you can ignore this email.',
        1
    ),
    (
        'request_submitted',
        'Document request received · {tracking_number}',
        'Your request for {document_name} has been submitted.\n\nTracking reference: {tracking_number}\nCurrent status: {status}\n\nTrack your request: {tracking_url}\n\nKeep this reference to check your request status.',
        1
    ),
    (
        'processing',
        'Your document request is being processed · {tracking_number}',
        'Your request for {document_name} is now being processed.\n\nEstimated release date: {expected_release_date}\nTracking reference: {tracking_number}\n\nEstimated dates are based on weekdays (Monday-Friday). Public holidays, class suspensions, office closures, or other unforeseen circumstances may still cause delays.',
        1
    ),
    (
        'ready',
        'Your document is ready for pickup · {tracking_number}',
        'Your request for {document_name} is ready for pickup.\n\nTracking reference: {tracking_number}',
        1
    ),
    (
        'rejected',
        'Update about your document request · {tracking_number}',
        'Your request for {document_name} was rejected.\n\nReason: {rejection_reason}\n\nTracking reference: {tracking_number}',
        1
    )
ON DUPLICATE KEY UPDATE template_key = VALUES(template_key);
