-- Grow/Flow Department Head Phase 1
-- READ-ONLY verification

-- 1) Department Heads and assigned departments
SELECT
    u.id,
    u.full_name,
    u.email,
    u.status,
    d.name AS department,
    u.last_login_at
FROM users u
JOIN roles r
    ON r.id = u.role_id
JOIN departments d
    ON d.id = u.department_id
   AND d.company_id = u.company_id
WHERE r.role_key = 'department_head'
ORDER BY d.name, u.full_name;


-- 2) Head coverage by department
SELECT
    d.name AS department,
    d.status,
    COUNT(
        CASE
            WHEN u.status = 'active'
            THEN 1
        END
    ) AS active_heads
FROM departments d
LEFT JOIN users u
    ON u.department_id = d.id
   AND u.company_id = d.company_id
LEFT JOIN roles r
    ON r.id = u.role_id
   AND r.role_key = 'department_head'
GROUP BY d.id, d.name, d.status
ORDER BY d.name;


-- 3) Employees assigned to each Department Head
SELECT
    h.full_name AS department_head,
    d.name AS department,
    COUNT(e.id) AS active_employees_assigned
FROM users h
JOIN roles r
    ON r.id = h.role_id
   AND r.role_key = 'department_head'
JOIN departments d
    ON d.id = h.department_id
LEFT JOIN employees e
    ON e.manager_user_id = h.id
   AND e.company_id = h.company_id
   AND e.employment_status = 'active'
WHERE h.status = 'active'
GROUP BY h.id, h.full_name, d.name
ORDER BY d.name, h.full_name;
