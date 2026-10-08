-- Run once against company_training. Keeps all existing user accounts.
USE company_training;
CREATE TABLE IF NOT EXISTS employees (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 department_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL UNIQUE,
 employee_code VARCHAR(40) NOT NULL,
 full_name VARCHAR(160) NOT NULL,
 job_title VARCHAR(120) NOT NULL DEFAULT '',
 employment_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 joined_at DATE NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_employee_code_company (company_id,employee_code),
 INDEX idx_employees_department (department_id,employment_status),
 CONSTRAINT fk_employee_company FOREIGN KEY (company_id) REFERENCES companies(id),
 CONSTRAINT fk_employee_department FOREIGN KEY (department_id) REFERENCES departments(id),
 CONSTRAINT fk_employee_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS performance_reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_id BIGINT UNSIGNED NOT NULL,
 reviewer_user_id BIGINT UNSIGNED NOT NULL,
 review_date DATE NOT NULL,
 period_label VARCHAR(80) NOT NULL DEFAULT '',
 overall_score DECIMAL(5,2) NOT NULL,
 notes TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_review_employee_date (employee_id,review_date,id),
 CONSTRAINT fk_review_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
 CONSTRAINT fk_review_author FOREIGN KEY (reviewer_user_id) REFERENCES users(id),
 CONSTRAINT chk_review_score CHECK (overall_score >= 0 AND overall_score <= 100)
) ENGINE=InnoDB;
