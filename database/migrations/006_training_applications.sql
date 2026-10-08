-- Run in company_training after 005_training_programs.sql and 002_hr_dashboard.sql.
-- No changes to existing programs, employees, or review records.
CREATE TABLE IF NOT EXISTS training_applications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 program_id BIGINT UNSIGNED NOT NULL,
 employee_id BIGINT UNSIGNED NOT NULL,
 source ENUM('employee','hr_nomination') NOT NULL DEFAULT 'hr_nomination',
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 submitted_by BIGINT UNSIGNED NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 review_notes VARCHAR(500) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_program_employee (program_id, employee_id),
 KEY idx_program_status (program_id,status),
 KEY idx_employee (employee_id),
 CONSTRAINT fk_ta_program FOREIGN KEY(program_id) REFERENCES training_programs(id) ON DELETE CASCADE,
 CONSTRAINT fk_ta_employee FOREIGN KEY(employee_id) REFERENCES employees(id),
 CONSTRAINT fk_ta_submitter FOREIGN KEY(submitted_by) REFERENCES users(id),
 CONSTRAINT fk_ta_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB;
