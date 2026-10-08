-- Import via phpMyAdmin > Import, or mysql CLI. UTF8MB4 and InnoDB.
CREATE DATABASE IF NOT EXISTS company_training CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE company_training;

CREATE TABLE IF NOT EXISTS companies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS departments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_company_department (company_id,name),
  CONSTRAINT fk_department_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_key VARCHAR(40) NOT NULL UNIQUE,
  role_label VARCHAR(70) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  department_id BIGINT UNSIGNED NULL,
  role_id TINYINT UNSIGNED NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  email VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

INSERT INTO roles (role_key,role_label) VALUES
('hr','HR Administrator'),('department_head','Department Head'),('employee','Employee'),('organization_admin','Organization Admin')
ON DUPLICATE KEY UPDATE role_label=VALUES(role_label);

INSERT INTO companies (id,name) VALUES (1,'Demo Company') ON DUPLICATE KEY UPDATE id=id;
INSERT INTO departments (company_id,name) VALUES (1,'Human Resources'),(1,'Information Technology')
ON DUPLICATE KEY UPDATE name=VALUES(name);
