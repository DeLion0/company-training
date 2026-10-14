-- Grow/Flow
-- 009_hr_monthly_performance.sql
--
-- LOCALHOST FIRST / RAILWAY READY
--
-- Purpose:
--   1) Create one canonical monthly performance record per employee.
--   2) Preserve existing demo/history by backfilling the monthly average
--      from the legacy performance_reviews table.
--   3) Give HR Manual + Excel imports a safe UPSERT target.
--
-- Prerequisites:
--   - companies
--   - users
--   - employees
--   - performance_reviews (legacy table used by the current dashboard)
--
-- Run ONCE on local company_training before using the new Performance page.
-- Before deploying the code to Railway, run the SAME migration ONCE on
-- Railway company_training.

CREATE TABLE IF NOT EXISTS employee_monthly_performance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    review_month DATE NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    notes VARCHAR(1000) NULL,
    source ENUM('manual','excel','legacy_backfill') NOT NULL DEFAULT 'manual',
    recorded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_emp_month (
        company_id,
        employee_id,
        review_month
    ),

    KEY idx_emp_perf_company_month (
        company_id,
        review_month
    ),

    KEY idx_emp_perf_employee_month (
        employee_id,
        review_month
    ),

    CONSTRAINT fk_emp_perf_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id),

    CONSTRAINT fk_emp_perf_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_emp_perf_recorder
        FOREIGN KEY (recorded_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT chk_emp_perf_score
        CHECK (score >= 0 AND score <= 100)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- Preserve the current dashboard/demo history.
-- Existing multiple reviews in the same month are averaged into one
-- official monthly record. INSERT IGNORE makes rerunning safe and does
-- not overwrite a newer HR-entered monthly record.
INSERT IGNORE INTO employee_monthly_performance (
    company_id,
    employee_id,
    review_month,
    score,
    notes,
    source,
    recorded_by
)
SELECT
    e.company_id,
    pr.employee_id,
    STR_TO_DATE(
        CONCAT(
            DATE_FORMAT(pr.review_date, '%Y-%m'),
            '-01'
        ),
        '%Y-%m-%d'
    ) AS review_month,
    ROUND(AVG(pr.overall_score), 2) AS score,
    'Backfilled from legacy performance_reviews monthly average.',
    'legacy_backfill',
    NULL
FROM performance_reviews pr
JOIN employees e
    ON e.id = pr.employee_id
GROUP BY
    e.company_id,
    pr.employee_id,
    DATE_FORMAT(pr.review_date, '%Y-%m');


-- Verification only.
SELECT
    COUNT(*) AS monthly_performance_rows,
    COUNT(DISTINCT employee_id) AS employees_with_performance
FROM employee_monthly_performance;
