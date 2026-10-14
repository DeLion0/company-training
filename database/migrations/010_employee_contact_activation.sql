-- Grow/Flow — Employee real-email activation
-- Run ONCE after the existing employee migrations.

USE company_training;

ALTER TABLE employees
    ADD COLUMN contact_email VARCHAR(254) NULL AFTER work_email,
    ADD INDEX idx_employee_contact_email (contact_email);

CREATE TABLE IF NOT EXISTS employee_activation_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_employee_activation_user (user_id),
    UNIQUE KEY uq_employee_activation_token_hash (token_hash),
    KEY idx_employee_activation_expiry (expires_at, used_at),
    CONSTRAINT fk_employee_activation_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;
