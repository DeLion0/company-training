-- Grow/Flow
-- Performance -> Dashboard -> Training sync verification
-- READ-ONLY queries unless otherwise stated.

-- 1) Official monthly ratings
SELECT
    e.employee_code,
    e.full_name,
    d.name AS department,
    DATE_FORMAT(mp.review_month, '%Y-%m') AS review_month,
    mp.score,
    mp.source
FROM employee_monthly_performance mp
JOIN employees e
    ON e.id = mp.employee_id
   AND e.company_id = mp.company_id
JOIN departments d
    ON d.id = e.department_id
ORDER BY
    mp.review_month DESC,
    e.full_name;


-- 2) Latest official rating per employee
SELECT
    e.employee_code,
    e.full_name,
    d.name AS department,
    DATE_FORMAT(mp.review_month, '%Y-%m') AS latest_month,
    mp.score AS latest_score
FROM employees e
JOIN departments d
    ON d.id = e.department_id
LEFT JOIN employee_monthly_performance mp
    ON mp.id = (
        SELECT p.id
        FROM employee_monthly_performance p
        WHERE p.company_id = e.company_id
          AND p.employee_id = e.id
        ORDER BY
            p.review_month DESC,
            p.id DESC
        LIMIT 1
    )
WHERE e.employment_status = 'active'
ORDER BY d.name, e.full_name;


-- 3) Current automatic low-performance training priorities
SELECT
    p.title,
    e.employee_code,
    e.full_name,
    d.name AS department,
    ta.status,
    trm.priority,
    trm.performance_score,
    trm.performance_month
FROM training_recommendation_meta trm
JOIN training_applications ta
    ON ta.id = trm.application_id
JOIN training_programs p
    ON p.id = ta.program_id
JOIN employees e
    ON e.id = ta.employee_id
JOIN departments d
    ON d.id = e.department_id
WHERE trm.recommendation_kind = 'low_performance'
ORDER BY
    p.id DESC,
    e.full_name;
