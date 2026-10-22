-- Grow/Flow
-- 011_training_attendance_certifications.sql
--
-- LOCALHOST FIRST / RAILWAY READY
--
-- Purpose:
-- 1) Store per-session attendance imported by HR from Excel.
-- 2) Issue one certificate per employee + training program when
--    all required training sessions are marked PRESENT.
-- 3) Store which approved certificate artwork (template 1-4) is used.
--
-- Prerequisites:
-- - companies
-- - users
-- - employees
-- - training_programs
-- - training_sessions
-- - training_applications
--
-- Run ONCE on local company_training first.
-- Run the SAME migration on Railway only after local testing passes.

CREATE TABLE IF NOT EXISTS training_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    attendance_status ENUM('present','absent') NOT NULL,
    source ENUM('excel','manual') NOT NULL DEFAULT 'excel',
    imported_by BIGINT UNSIGNED NOT NULL,
    imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_training_attendance_session_employee (
        session_id,
        employee_id
    ),

    KEY idx_training_attendance_company_program (
        company_id,
        program_id
    ),

    KEY idx_training_attendance_employee (
        employee_id
    ),

    CONSTRAINT fk_training_attendance_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id),

    CONSTRAINT fk_training_attendance_program
        FOREIGN KEY (program_id)
        REFERENCES training_programs(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_training_attendance_session
        FOREIGN KEY (session_id)
        REFERENCES training_sessions(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_training_attendance_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_training_attendance_importer
        FOREIGN KEY (imported_by)
        REFERENCES users(id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS training_certificates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,

    certificate_number VARCHAR(40) NULL,
    template_key ENUM(
        'template_1',
        'template_2',
        'template_3',
        'template_4'
    ) NOT NULL DEFAULT 'template_1',

    status ENUM('issued','revoked') NOT NULL DEFAULT 'issued',
    issued_by BIGINT UNSIGNED NOT NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    revoked_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_training_certificate_program_employee (
        program_id,
        employee_id
    ),

    UNIQUE KEY uq_training_certificate_number (
        certificate_number
    ),

    KEY idx_training_certificate_company_status (
        company_id,
        status
    ),

    KEY idx_training_certificate_employee (
        employee_id,
        status
    ),

    CONSTRAINT fk_training_certificate_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id),

    CONSTRAINT fk_training_certificate_program
        FOREIGN KEY (program_id)
        REFERENCES training_programs(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_training_certificate_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_training_certificate_issuer
        FOREIGN KEY (issued_by)
        REFERENCES users(id),

    CONSTRAINT fk_training_certificate_revoker
        FOREIGN KEY (revoked_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- Verification only.
SELECT
    'training_attendance' AS table_name,
    COUNT(*) AS rows_now
FROM training_attendance

UNION ALL

SELECT
    'training_certificates',
    COUNT(*)
FROM training_certificates;
