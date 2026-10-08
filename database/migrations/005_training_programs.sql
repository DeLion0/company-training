-- Run once in the existing company_training database. Additive migration.
CREATE TABLE IF NOT EXISTS training_programs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  purpose TEXT NOT NULL,
  objectives TEXT NOT NULL,
  instructors VARCHAR(500) NOT NULL,
  venue VARCHAR(255) NOT NULL,
  capacity INT UNSIGNED NOT NULL,
  range_start DATE NOT NULL,
  range_end DATE NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL,
  meeting_count SMALLINT UNSIGNED NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  status ENUM('draft','published','cancelled') NOT NULL DEFAULT 'published',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tp_company_status (company_id,status),
  CONSTRAINT fk_tp_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_tp_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS training_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  program_id BIGINT UNSIGNED NOT NULL,
  session_number SMALLINT UNSIGNED NOT NULL,
  session_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  UNIQUE KEY uq_session_number (program_id,session_number),
  UNIQUE KEY uq_session_date (program_id,session_date),
  INDEX idx_session_date (session_date),
  CONSTRAINT fk_session_program FOREIGN KEY (program_id) REFERENCES training_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB;
