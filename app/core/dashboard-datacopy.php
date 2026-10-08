<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Build a fixed rolling month window, oldest to newest.
 * Each key is YYYY-MM and each label is short month + year when useful.
 */
function dashboard_month_window(int $months = 6): array
{
    $months = max(1, min(12, $months));
    $cursor = new DateTimeImmutable('first day of this month');
    $result = [];

    for ($i = $months - 1; $i >= 0; $i--) {
        $date = $cursor->modify('-' . $i . ' months');
        $result[] = [
            'key' => $date->format('Y-m'),
            'label' => $date->format('M'),
            'long_label' => $date->format('F Y'),
        ];
    }

    return $result;
}

function dashboard_data(array $user): array
{
    $db = database();
    $viewerId = (int)($user['id'] ?? 0);
    $isHR = ($user['role'] ?? '') === 'hr';
    $deptId = isset($user['department_id']) ? (int)$user['department_id'] : 0;

    if ($viewerId < 1) {
        throw new RuntimeException('Invalid dashboard viewer.');
    }

    if (!$isHR && $deptId < 1) {
        return [
            'unassigned' => true,
            'months' => dashboard_month_window(),
            'departments' => [],
            'employees' => [],
            'total' => 0,
            'rated' => 0,
            'average' => null,
        ];
    }

    $companyStmt = $db->prepare('SELECT company_id FROM users WHERE id = :viewer_id LIMIT 1');
    $companyStmt->execute(['viewer_id' => $viewerId]);
    $companyId = (int)$companyStmt->fetchColumn();

    if ($companyId < 1) {
        throw new RuntimeException('The signed-in account is not assigned to a company.');
    }

    $scopeSql = $isHR ? '' : ' AND d.id = :department_id';
    $scopeArgs = ['company_id' => $companyId];
    if (!$isHR) {
        $scopeArgs['department_id'] = $deptId;
    }

    // Department directory and active employee counts.
    $stmt = $db->prepare(
        'SELECT d.id, d.name, COUNT(e.id) AS total_employees
         FROM departments d
         LEFT JOIN employees e
           ON e.department_id = d.id
          AND e.company_id = d.company_id
          AND e.employment_status = \'active\'
         WHERE d.company_id = :company_id' . $scopeSql . '
         GROUP BY d.id, d.name
         ORDER BY d.name'
    );
    $stmt->execute($scopeArgs);
    $departmentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Employee directory with latest performance review.
    $stmt = $db->prepare(
        'SELECT
            e.id,
            e.full_name,
            e.employee_code,
            e.job_title,
            e.department_id,
            d.name AS department_name,
            pr.overall_score AS latest_score,
            pr.review_date AS latest_review_date
         FROM employees e
         JOIN departments d
           ON d.id = e.department_id
          AND d.company_id = e.company_id
         LEFT JOIN performance_reviews pr
           ON pr.id = (
               SELECT p.id
               FROM performance_reviews p
               WHERE p.employee_id = e.id
               ORDER BY p.review_date DESC, p.id DESC
               LIMIT 1
           )
         WHERE e.employment_status = \'active\'
           AND e.company_id = :company_id' . $scopeSql . '
         ORDER BY d.name, e.full_name, e.id'
    );
    $stmt->execute($scopeArgs);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $months = dashboard_month_window(6);
    $firstMonth = $months[0]['key'] . '-01';

    // ==========================================
    // MONTHLY DEPARTMENT PERFORMANCE
    // ==========================================
    //
    // Step 1:
    // Average each employee's reviews per month.
    //
    // Step 2:
    // Average all employee monthly scores
    // belonging to the department.
    //
    // This prevents an employee with multiple
    // reviews from having more weight.
    //

    $performanceScopeSql = $isHR
        ? ''
        : ' AND e.department_id = :department_id';

    $stmt = $db->prepare(
        'SELECT
        monthly.department_id,
        monthly.month_key,
        ROUND(
            AVG(monthly.employee_monthly_score),
            1
        ) AS average_score,
        COUNT(*) AS reviewed_employees

     FROM
     (
        SELECT
            e.department_id,
            e.id AS employee_id,

            DATE_FORMAT(
                pr.review_date,
                \'%Y-%m\'
            ) AS month_key,

            AVG(
                pr.overall_score
            ) AS employee_monthly_score

        FROM performance_reviews pr

        JOIN employees e
            ON e.id = pr.employee_id

        WHERE e.company_id = :company_id

          AND e.employment_status = \'active\'

          AND pr.review_date >= :first_month'

            . $performanceScopeSql .

            ' GROUP BY
            e.department_id,
            e.id,
            DATE_FORMAT(
                pr.review_date,
                \'%Y-%m\'
            )
     ) monthly

     GROUP BY
        monthly.department_id,
        monthly.month_key

     ORDER BY
        monthly.month_key,
        monthly.department_id'
    );

    $monthlyArgs = [
        'company_id' => $companyId,
        'first_month' => $firstMonth
    ];

    if (!$isHR) {
        $monthlyArgs['department_id'] = $deptId;
    }

    $stmt->execute($monthlyArgs);

    $departmentMonthlyRows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);
    $monthlyArgs = $scopeArgs + ['first_month' => $firstMonth];
    $stmt->execute($monthlyArgs);
    $departmentMonthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly averages per employee, queried once for the whole visible scope.
    $employeeScope = $isHR ? '' : ' AND e.department_id = :department_id';
    $stmt = $db->prepare(
        'SELECT
            e.id AS employee_id,
            DATE_FORMAT(pr.review_date, \'%Y-%m\') AS month_key,
            ROUND(AVG(pr.overall_score), 1) AS average_score,
            COUNT(pr.id) AS review_count
         FROM performance_reviews pr
         JOIN employees e ON e.id = pr.employee_id
         WHERE e.company_id = :company_id
           AND e.employment_status = \'active\'
           AND pr.review_date >= :first_month' . $employeeScope . '
         GROUP BY e.id, DATE_FORMAT(pr.review_date, \'%Y-%m\')
         ORDER BY e.id, month_key'
    );
    $stmt->execute($monthlyArgs);
    $employeeMonthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $departmentMap = [];
    foreach ($departmentRows as $row) {
        $id = (int)$row['id'];
        $departmentMap[$id] = [
            'id' => $id,
            'name' => (string)$row['name'],
            'count' => (int)$row['total_employees'],
            'rated' => 0,
            'sum' => 0.0,
            'average' => null,
            'monthly' => array_fill_keys(array_column($months, 'key'), null),
            'monthly_reviewed' => array_fill_keys(array_column($months, 'key'), 0),
        ];
    }

    foreach ($departmentMonthlyRows as $row) {
        $departmentId = (int)$row['department_id'];
        $monthKey = (string)$row['month_key'];
        if (isset($departmentMap[$departmentId]['monthly'][$monthKey])) {
            $departmentMap[$departmentId]['monthly'][$monthKey] = (float)$row['average_score'];
            $departmentMap[$departmentId]['monthly_reviewed'][$monthKey] = (int)$row['reviewed_employees'];
        }
    }

    $employeeMonthlyMap = [];
    foreach ($employees as $employee) {
        $employeeMonthlyMap[(int)$employee['id']] = array_fill_keys(array_column($months, 'key'), null);
    }
    foreach ($employeeMonthlyRows as $row) {
        $employeeId = (int)$row['employee_id'];
        $monthKey = (string)$row['month_key'];
        if (isset($employeeMonthlyMap[$employeeId]) && array_key_exists($monthKey, $employeeMonthlyMap[$employeeId])) {
            $employeeMonthlyMap[$employeeId][$monthKey] = (float)$row['average_score'];
        }
    }

    $rated = 0;
    $scoreSum = 0.0;
    foreach ($employees as &$employee) {
        $departmentId = (int)$employee['department_id'];
        $employeeId = (int)$employee['id'];
        $employee['monthly'] = $employeeMonthlyMap[$employeeId] ?? array_fill_keys(array_column($months, 'key'), null);

        if ($employee['latest_score'] !== null && isset($departmentMap[$departmentId])) {
            $score = (float)$employee['latest_score'];
            $departmentMap[$departmentId]['rated']++;
            $departmentMap[$departmentId]['sum'] += $score;
            $rated++;
            $scoreSum += $score;
        }
    }
    unset($employee);

    foreach ($departmentMap as &$department) {
        $department['average'] = $department['rated'] > 0
            ? round($department['sum'] / $department['rated'], 1)
            : null;
        unset($department['sum']);
    }
    unset($department);

    return [
        'unassigned' => false,
        'months' => $months,
        'departments' => array_values($departmentMap),
        'employees' => $employees,
        'total' => count($employees),
        'rated' => $rated,
        'average' => $rated > 0 ? round($scoreSum / $rated, 1) : null,
    ];
}
