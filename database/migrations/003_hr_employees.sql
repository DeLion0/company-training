-- Import AFTER schema.sql and 002_hr_dashboard.sql in company_training.
-- Leaves existing HR, department head, and employee records intact.
USE company_training;
ALTER TABLE employees
  ADD COLUMN first_name VARCHAR(80) NULL,
  ADD COLUMN last_name VARCHAR(80) NULL,
  ADD COLUMN manager_user_id BIGINT UNSIGNED NULL,
  ADD COLUMN work_email VARCHAR(254) NULL;
-- Run only once. The users.email UNIQUE constraint already prevents duplicate work email logins.
