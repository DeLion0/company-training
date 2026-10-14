-- Grow/Flow
-- 008_training_department_targeting.sql
--
-- PURPOSE
-- 1) Connect each training program to one or more target departments.
-- 2) Store metadata for performance-based priority recommendations.
--
-- PREREQUISITES
-- - 004 departments/job titles migration
-- - 005_training_programs.sql
-- - 006_training_applications.sql
-- - 007_training_join_recommend.sql
--
-- Run ONCE in the existing company_training database.
-- This migration is additive and does not delete existing programs,
-- sessions, applications, employees, departments, or reviews.

CREATE TABLE IF NOT EXISTS training_program_departments (
    program_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (program_id, department_id),
    KEY idx_tpd_department (department_id),

    CONSTRAINT fk_tpd_program
        FOREIGN KEY (program_id)
        REFERENCES training_programs(id)
        ON DELETE CASCADE

    -- department_id is intentionally validated by application code.
    -- We do not add a department foreign key here so this migration
    -- remains compatible if an older local database used a different
    -- integer width for departments.id.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS training_recommendation_meta (
    application_id BIGINT UNSIGNED NOT NULL,
    recommendation_kind VARCHAR(40) NOT NULL,
    priority TINYINT(1) NOT NULL DEFAULT 0,
    performance_score DECIMAL(5,2) NULL,
    performance_month CHAR(7) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (application_id),
    KEY idx_trm_priority_kind (
        priority,
        recommendation_kind
    ),
    KEY idx_trm_performance_month (
        performance_month
    ),

    CONSTRAINT fk_trm_application
        FOREIGN KEY (application_id)
        REFERENCES training_applications(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Verification only. These SELECTs do not modify data.
SELECT
    'training_program_departments' AS table_name,
    COUNT(*) AS rows_now
FROM training_program_departments

UNION ALL

SELECT
    'training_recommendation_meta',
    COUNT(*)
FROM training_recommendation_meta;
