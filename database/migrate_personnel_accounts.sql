RENAME TABLE admin_users TO personnel_accounts;

ALTER TABLE personnel_accounts
    ADD COLUMN full_name VARCHAR(150) NULL AFTER username,
    ADD COLUMN email VARCHAR(254) NULL AFTER full_name,
    ADD COLUMN role ENUM('admin', 'staff') NOT NULL DEFAULT 'admin' AFTER password_hash,
    ADD UNIQUE KEY uq_personnel_accounts_email (email),
    RENAME INDEX uq_admin_users_username TO uq_personnel_accounts_username;
