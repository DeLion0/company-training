-- Grow/Flow
-- 012_points_badges.sql
-- LOCALHOST FIRST / RAILWAY READY
-- Additive migration only.

CREATE TABLE IF NOT EXISTS employee_point_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    points INT UNSIGNED NOT NULL,
    source_name VARCHAR(120) NOT NULL,
    reason VARCHAR(255) NULL,
    awarded_on DATE NOT NULL,
    posted_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_employee_points_company_employee (company_id, employee_id),
    KEY idx_employee_points_awarded_on (company_id, awarded_on),
    CONSTRAINT fk_employee_points_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_employee_points_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_employee_points_poster FOREIGN KEY (posted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reward_badges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    badge_key VARCHAR(80) NOT NULL,
    badge_name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    icon_key VARCHAR(40) NOT NULL DEFAULT 'star',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reward_badge_company_key (company_id, badge_key),
    KEY idx_reward_badge_company_status (company_id, status),
    CONSTRAINT fk_reward_badge_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_reward_badge_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_badges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    badge_id BIGINT UNSIGNED NOT NULL,
    source_name VARCHAR(120) NOT NULL,
    note VARCHAR(255) NULL,
    awarded_on DATE NOT NULL,
    posted_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_employee_badge_once (employee_id, badge_id),
    KEY idx_employee_badges_company_employee (company_id, employee_id),
    CONSTRAINT fk_employee_badges_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_employee_badges_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_employee_badges_badge FOREIGN KEY (badge_id) REFERENCES reward_badges(id) ON DELETE CASCADE,
    CONSTRAINT fk_employee_badges_poster FOREIGN KEY (posted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO reward_badges (company_id,badge_key,badge_name,description,icon_key,status,created_by)
SELECT c.id,'productive_employee','Productive Employee','Recognition for strong productivity and contribution.','duck','active',NULL
FROM companies c
ON DUPLICATE KEY UPDATE badge_name=VALUES(badge_name),description=VALUES(description),icon_key=VALUES(icon_key),status='active';

INSERT INTO reward_badges (company_id,badge_key,badge_name,description,icon_key,status,created_by)
SELECT c.id,'best_learner','Best Learner','Recognition for outstanding learning progress and engagement.','fish','active',NULL
FROM companies c
ON DUPLICATE KEY UPDATE badge_name=VALUES(badge_name),description=VALUES(description),icon_key=VALUES(icon_key),status='active';

SELECT 'employee_point_entries' AS table_name, COUNT(*) AS rows_now FROM employee_point_entries
UNION ALL
SELECT 'reward_badges', COUNT(*) FROM reward_badges
UNION ALL
SELECT 'employee_badges', COUNT(*) FROM employee_badges;
