<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Build a fixed rolling month window, oldest to newest.
 */
function dashboard_month_window(int $months = 6): array
{
    $months = max(1, min(12, $months));

    $cursor =
        new DateTimeImmutable(
            'first day of this month'
        );

    $result = [];

    for (
        $i = $months - 1;
        $i >= 0;
        $i--
    ) {
        $date =
            $cursor->modify(
                '-' . $i . ' months'
            );

        $result[] = [
            'key' =>
                $date->format('Y-m'),
            'label' =>
                $date->format('M'),
            'long_label' =>
                $date->format('F Y'),
        ];
    }

    return $result;
}

function dashboard_data(array $user): array
{
    $db = database();

    $viewerId =
        (int) ($user['id'] ?? 0);

    $isHR =
        ($user['role'] ?? '') === 'hr';

    $deptId =
        isset($user['department_id'])
            ? (int) $user['department_id']
            : 0;

    if ($viewerId < 1) {
        throw new RuntimeException(
            'Invalid dashboard viewer.'
        );
    }

    if (!$isHR && $deptId < 1) {
        return [
            'unassigned' => true,
            'months' =>
                dashboard_month_window(),
            'departments' => [],
            'employees' => [],
            'total' => 0,
            'rated' => 0,
            'average' => null,
        ];
    }

    $companyStmt =
        $db->prepare(
            'SELECT company_id
             FROM users
             WHERE id = :viewer_id
             LIMIT 1'
        );

    $companyStmt->execute([
        'viewer_id' => $viewerId,
    ]);

    $companyId =
        (int) $companyStmt->fetchColumn();

    if ($companyId < 1) {
        throw new RuntimeException(
            'The signed-in account is not assigned to a company.'
        );
    }

    $scopeSql =
        $isHR
            ? ''
            : ' AND d.id = :department_id';

    $scopeArgs = [
        'company_id' => $companyId,
    ];

    if (!$isHR) {
        $scopeArgs['department_id'] =
            $deptId;
    }

    // ==========================================
    // DEPARTMENT DIRECTORY
    // ==========================================

    $stmt = $db->prepare(
        'SELECT
            d.id,
            d.name,
            COUNT(e.id) AS total_employees
         FROM departments d
         LEFT JOIN employees e
           ON e.department_id = d.id
          AND e.company_id = d.company_id
          AND e.employment_status = \'active\'
         WHERE d.company_id = :company_id'
         . $scopeSql .
        ' GROUP BY d.id, d.name
          ORDER BY d.name'
    );

    $stmt->execute($scopeArgs);

    $departmentRows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    // ==========================================
    // EMPLOYEE DIRECTORY + LATEST OFFICIAL SCORE
    // ==========================================
    //
    // employee_monthly_performance is now the canonical
    // source for HR monthly ratings.
    //

    $stmt = $db->prepare(
        'SELECT
            e.id,
            e.full_name,
            e.employee_code,
            e.job_title,
            e.department_id,
            d.name AS department_name,
            mp.score AS latest_score,
            mp.review_month AS latest_review_date
         FROM employees e
         JOIN departments d
           ON d.id = e.department_id
          AND d.company_id = e.company_id
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
         WHERE e.employment_status = \'active\'
           AND e.company_id = :company_id'
         . $scopeSql .
        ' ORDER BY
            d.name,
            e.full_name,
            e.id'
    );

    $stmt->execute($scopeArgs);

    $employees =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    $months =
        dashboard_month_window(6);

    $firstMonth =
        $months[0]['key']
        . '-01';

    $performanceScopeSql =
        $isHR
            ? ''
            : ' AND e.department_id = :department_id';

    $monthlyArgs = [
        'company_id' => $companyId,
        'first_month' => $firstMonth,
    ];

    if (!$isHR) {
        $monthlyArgs['department_id'] =
            $deptId;
    }

    // ==========================================
    // MONTHLY DEPARTMENT PERFORMANCE
    // ==========================================
    //
    // One official monthly score exists per employee/month,
    // so department performance is the average of those
    // employee monthly scores. Employees without a rating
    // for that month are excluded.
    //

    $stmt = $db->prepare(
        'SELECT
            e.department_id,
            DATE_FORMAT(
                mp.review_month,
                \'%Y-%m\'
            ) AS month_key,
            ROUND(
                AVG(mp.score),
                1
            ) AS average_score,
            COUNT(*) AS reviewed_employees
         FROM employee_monthly_performance mp
         JOIN employees e
           ON e.id = mp.employee_id
          AND e.company_id = mp.company_id
         WHERE mp.company_id = :company_id
           AND e.employment_status = \'active\'
           AND mp.review_month >= :first_month'
         . $performanceScopeSql .
        ' GROUP BY
            e.department_id,
            DATE_FORMAT(
                mp.review_month,
                \'%Y-%m\'
            )
          ORDER BY
            month_key,
            e.department_id'
    );

    $stmt->execute($monthlyArgs);

    $departmentMonthlyRows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    // ==========================================
    // MONTHLY PERFORMANCE PER EMPLOYEE
    // ==========================================

    $stmt = $db->prepare(
        'SELECT
            e.id AS employee_id,
            DATE_FORMAT(
                mp.review_month,
                \'%Y-%m\'
            ) AS month_key,
            ROUND(
                mp.score,
                1
            ) AS average_score,
            1 AS review_count
         FROM employee_monthly_performance mp
         JOIN employees e
           ON e.id = mp.employee_id
          AND e.company_id = mp.company_id
         WHERE mp.company_id = :company_id
           AND e.employment_status = \'active\'
           AND mp.review_month >= :first_month'
         . $performanceScopeSql .
        ' ORDER BY
            e.id,
            mp.review_month'
    );

    $stmt->execute($monthlyArgs);

    $employeeMonthlyRows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    // ==========================================
    // BUILD DEPARTMENT VIEW MODEL
    // ==========================================

    $departmentMap = [];

    foreach (
        $departmentRows
        as $row
    ) {
        $id =
            (int) $row['id'];

        $departmentMap[$id] = [
            'id' => $id,
            'name' =>
                (string) $row['name'],
            'count' =>
                (int) $row['total_employees'],
            'rated' => 0,
            'sum' => 0.0,
            'average' => null,
            'monthly' =>
                array_fill_keys(
                    array_column(
                        $months,
                        'key'
                    ),
                    null
                ),
            'monthly_reviewed' =>
                array_fill_keys(
                    array_column(
                        $months,
                        'key'
                    ),
                    0
                ),
        ];
    }

    foreach (
        $departmentMonthlyRows
        as $row
    ) {
        $departmentId =
            (int) $row['department_id'];

        $monthKey =
            (string) $row['month_key'];

        if (
            isset(
                $departmentMap[
                    $departmentId
                ]
            )
            && array_key_exists(
                $monthKey,
                $departmentMap[
                    $departmentId
                ]['monthly']
            )
        ) {
            $departmentMap[
                $departmentId
            ]['monthly'][$monthKey] =
                (float) $row[
                    'average_score'
                ];

            $departmentMap[
                $departmentId
            ]['monthly_reviewed'][$monthKey] =
                (int) $row[
                    'reviewed_employees'
                ];
        }
    }

    // ==========================================
    // BUILD EMPLOYEE MONTHLY VIEW MODEL
    // ==========================================

    $employeeMonthlyMap = [];

    foreach (
        $employees
        as $employee
    ) {
        $employeeMonthlyMap[
            (int) $employee['id']
        ] =
            array_fill_keys(
                array_column(
                    $months,
                    'key'
                ),
                null
            );
    }

    foreach (
        $employeeMonthlyRows
        as $row
    ) {
        $employeeId =
            (int) $row['employee_id'];

        $monthKey =
            (string) $row['month_key'];

        if (
            isset(
                $employeeMonthlyMap[
                    $employeeId
                ]
            )
            && array_key_exists(
                $monthKey,
                $employeeMonthlyMap[
                    $employeeId
                ]
            )
        ) {
            $employeeMonthlyMap[
                $employeeId
            ][$monthKey] =
                (float) $row[
                    'average_score'
                ];
        }
    }

    // ==========================================
    // LATEST SCORE METRICS
    // ==========================================

    $rated = 0;
    $scoreSum = 0.0;

    foreach (
        $employees
        as &$employee
    ) {
        $departmentId =
            (int) $employee[
                'department_id'
            ];

        $employeeId =
            (int) $employee['id'];

        $employee['monthly'] =
            $employeeMonthlyMap[
                $employeeId
            ]
            ?? array_fill_keys(
                array_column(
                    $months,
                    'key'
                ),
                null
            );

        if (
            $employee['latest_score'] !== null
            && isset(
                $departmentMap[
                    $departmentId
                ]
            )
        ) {
            $score =
                (float) $employee[
                    'latest_score'
                ];

            $departmentMap[
                $departmentId
            ]['rated']++;

            $departmentMap[
                $departmentId
            ]['sum'] += $score;

            $rated++;
            $scoreSum += $score;
        }
    }

    unset($employee);

    foreach (
        $departmentMap
        as &$department
    ) {
        $department['average'] =
            $department['rated'] > 0
                ? round(
                    $department['sum']
                    / $department['rated'],
                    1
                )
                : null;

        unset(
            $department['sum']
        );
    }

    unset($department);

    return [
        'unassigned' => false,
        'months' => $months,
        'departments' =>
            array_values(
                $departmentMap
            ),
        'employees' => $employees,
        'total' =>
            count($employees),
        'rated' => $rated,
        'average' =>
            $rated > 0
                ? round(
                    $scoreSum / $rated,
                    1
                )
                : null,
    ];
}
