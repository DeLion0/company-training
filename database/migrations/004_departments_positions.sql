-- Grow/Flow HR Departments Phase 1. Run ONCE after 003_hr_employees.sql.
USE company_training;
ALTER TABLE departments
 ADD COLUMN department_code VARCHAR(24) NULL,
 ADD COLUMN description TEXT NULL,
 ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 ADD UNIQUE KEY uq_company_dept_code (company_id, department_code);
CREATE TABLE IF NOT EXISTS job_positions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 department_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_department_position (department_id,name),
 KEY idx_company_position (company_id,department_id),
 CONSTRAINT fk_position_company FOREIGN KEY (company_id) REFERENCES companies(id),
 CONSTRAINT fk_position_department FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;
-- Backfill existing employee job titles without touching their current records.
INSERT IGNORE INTO job_positions (company_id,department_id,name)
SELECT DISTINCT company_id,department_id,TRIM(job_title)
FROM employees WHERE TRIM(job_title) <> '';
