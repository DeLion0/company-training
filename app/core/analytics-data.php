<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

const ANALYTICS_PERFORMANCE_THRESHOLD = 50.0;
const ANALYTICS_MONTH_WINDOW = 12;

/**
 * Analytics is intentionally HR-only.
 */
function analytics_company_id(array $user): int
{
    if (($user['role'] ?? '') !== 'hr') {
        throw new RuntimeException('HR authorization required.');
    }

    $userId = (int)($user['id'] ?? 0);

    if ($userId < 1) {
        throw new RuntimeException('Invalid analytics viewer.');
    }

    $q = database()->prepare(
        "SELECT u.company_id
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = ?
           AND u.status = 'active'
           AND r.role_key = 'hr'
         LIMIT 1"
    );

    $q->execute([$userId]);

    $companyId = (int)$q->fetchColumn();

    if ($companyId < 1) {
        throw new RuntimeException('The HR account is not assigned to a company.');
    }

    return $companyId;
}

function analytics_department_color(string $name, int $index): string
{
    $value = strtolower($name);

    // Requested visual convention + extensions for the complete department list.
    if (
        str_contains($value, 'people')
        || str_contains($value, 'culture')
        || str_contains($value, 'human resource')
        || preg_match('/\bhr\b/', $value)
    ) {
        return '#dc3f45'; // HR / People & Culture = red
    }

    if (str_contains($value, 'marketing')) {
        return '#f07a24'; // Marketing = orange
    }

    if (
        str_contains($value, 'account')
        || str_contains($value, 'financial')
    ) {
        return '#3478d4'; // Accounting / Finance = blue
    }

    if (
        str_contains($value, 'information')
        || str_contains($value, 'technology')
    ) {
        return '#7a5bd4';
    }

    if (str_contains($value, 'operations')) {
        return '#159b91';
    }

    if (
        str_contains($value, 'production')
        || str_contains($value, 'research')
        || str_contains($value, 'innovation')
    ) {
        return '#2f9d57';
    }

    if (
        str_contains($value, 'sales')
        || str_contains($value, 'business development')
    ) {
        return '#c88b18';
    }

    $fallback = [
        '#3478d4',
        '#dc3f45',
        '#f07a24',
        '#7a5bd4',
        '#159b91',
        '#2f9d57',
        '#c88b18',
        '#d65a9e',
    ];

    return $fallback[$index % count($fallback)];
}

function analytics_month_window(
    ?string $anchorMonth,
    int $months = ANALYTICS_MONTH_WINDOW
): array {
    $months = max(3, min(12, $months));

    $anchor = $anchorMonth !== null
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $anchorMonth)
        : false;

    if (!$anchor) {
        $anchor = new DateTimeImmutable(
            'first day of this month',
            new DateTimeZone('Asia/Manila')
        );
    }

    $result = [];

    for ($i = $months - 1; $i >= 0; $i--) {
        $date = $anchor->modify('-' . $i . ' months');

        $result[] = [
            'key' => $date->format('Y-m'),
            'date' => $date->format('Y-m-01'),
            'label' => $date->format('M'),
            'long_label' => $date->format('F Y'),
        ];
    }

    return $result;
}

function analytics_data(array $user): array
{
    $db = database();
    $companyId = analytics_company_id($user);

    $anchorQuery = $db->prepare(
        "SELECT MAX(review_month)
         FROM employee_monthly_performance
         WHERE company_id = ?"
    );

    $anchorQuery->execute([$companyId]);

    $anchorMonth = $anchorQuery->fetchColumn();

    $months = analytics_month_window(
        is_string($anchorMonth) && $anchorMonth !== ''
            ? $anchorMonth
            : null
    );

    $firstMonth = (string)$months[0]['date'];
    $lastMonth = (string)$months[count($months) - 1]['date'];

    $departmentQuery = $db->prepare(
        "SELECT
            d.id,
            d.department_code,
            d.name,
            COUNT(e.id) AS active_employees
         FROM departments d
         LEFT JOIN employees e
           ON e.department_id = d.id
          AND e.company_id = d.company_id
          AND e.employment_status = 'active'
         WHERE d.company_id = ?
           AND d.status = 'active'
         GROUP BY
            d.id,
            d.department_code,
            d.name
         ORDER BY d.name"
    );

    $departmentQuery->execute([$companyId]);

    $departmentRows = $departmentQuery->fetchAll(PDO::FETCH_ASSOC);

    $monthlyQuery = $db->prepare(
        "SELECT
            e.department_id,
            DATE_FORMAT(mp.review_month, '%Y-%m') AS month_key,
            ROUND(AVG(mp.score), 1) AS average_score,
            COUNT(DISTINCT mp.employee_id) AS reviewed_employees
         FROM employee_monthly_performance mp
         JOIN employees e
           ON e.id = mp.employee_id
          AND e.company_id = mp.company_id
         WHERE mp.company_id = ?
           AND e.employment_status = 'active'
           AND mp.review_month BETWEEN ? AND ?
         GROUP BY
            e.department_id,
            DATE_FORMAT(mp.review_month, '%Y-%m')
         ORDER BY month_key, e.department_id"
    );

    $monthlyQuery->execute([
        $companyId,
        $firstMonth,
        $lastMonth,
    ]);

    $departmentMonthlyRows = $monthlyQuery->fetchAll(PDO::FETCH_ASSOC);

    $companyMonthlyQuery = $db->prepare(
        "SELECT
            DATE_FORMAT(mp.review_month, '%Y-%m') AS month_key,
            ROUND(AVG(mp.score), 1) AS average_score,
            COUNT(DISTINCT mp.employee_id) AS reviewed_employees,
            COUNT(DISTINCT CASE
                WHEN mp.score < ?
                THEN mp.employee_id
                ELSE NULL
            END) AS below_threshold
         FROM employee_monthly_performance mp
         JOIN employees e
           ON e.id = mp.employee_id
          AND e.company_id = mp.company_id
         WHERE mp.company_id = ?
           AND e.employment_status = 'active'
           AND mp.review_month BETWEEN ? AND ?
         GROUP BY DATE_FORMAT(mp.review_month, '%Y-%m')
         ORDER BY month_key"
    );

    $companyMonthlyQuery->execute([
        ANALYTICS_PERFORMANCE_THRESHOLD,
        $companyId,
        $firstMonth,
        $lastMonth,
    ]);

    $companyMonthlyRows = $companyMonthlyQuery->fetchAll(PDO::FETCH_ASSOC);

    $totalEmployeesQuery = $db->prepare(
        "SELECT COUNT(*)
         FROM employees
         WHERE company_id = ?
           AND employment_status = 'active'"
    );

    $totalEmployeesQuery->execute([$companyId]);

    $totalEmployees = (int)$totalEmployeesQuery->fetchColumn();

    $monthKeys = array_column($months, 'key');

    $departments = [];
    $departmentMap = [];

    foreach ($departmentRows as $index => $row) {
        $id = (int)$row['id'];

        $department = [
            'id' => $id,
            'code' => (string)($row['department_code'] ?? ''),
            'name' => (string)$row['name'],
            'color' => analytics_department_color((string)$row['name'], $index),
            'active_employees' => (int)$row['active_employees'],
            'monthly' => array_fill_keys($monthKeys, null),
            'monthly_reviewed' => array_fill_keys($monthKeys, 0),
            'latest_score' => null,
            'latest_reviewed' => 0,
            'previous_score' => null,
            'change' => null,
            'coverage' => null,
        ];

        $departments[] = $department;
        $departmentMap[$id] = count($departments) - 1;
    }

    foreach ($departmentMonthlyRows as $row) {
        $departmentId = (int)$row['department_id'];
        $monthKey = (string)$row['month_key'];

        if (
            !isset($departmentMap[$departmentId])
            || !in_array($monthKey, $monthKeys, true)
        ) {
            continue;
        }

        $index = $departmentMap[$departmentId];

        $departments[$index]['monthly'][$monthKey] =
            (float)$row['average_score'];

        $departments[$index]['monthly_reviewed'][$monthKey] =
            (int)$row['reviewed_employees'];
    }

    foreach ($departments as &$department) {
        $scores = [];

        foreach ($months as $month) {
            $monthKey = (string)$month['key'];
            $score = $department['monthly'][$monthKey];

            if ($score !== null) {
                $scores[] = [
                    'key' => $monthKey,
                    'score' => (float)$score,
                    'reviewed' => (int)$department['monthly_reviewed'][$monthKey],
                ];
            }
        }

        if ($scores) {
            $latest = $scores[count($scores) - 1];
            $department['latest_score'] = $latest['score'];
            $department['latest_reviewed'] = $latest['reviewed'];

            if (count($scores) >= 2) {
                $previous = $scores[count($scores) - 2];
                $department['previous_score'] = $previous['score'];
                $department['change'] = round(
                    $latest['score'] - $previous['score'],
                    1
                );
            }

            $active = (int)$department['active_employees'];

            $department['coverage'] = $active > 0
                ? round(100 * $latest['reviewed'] / $active, 1)
                : null;
        }
    }

    unset($department);

    $companyMonthly = array_fill_keys(
        $monthKeys,
        [
            'average' => null,
            'reviewed' => 0,
            'coverage' => null,
            'below_threshold' => 0,
        ]
    );

    foreach ($companyMonthlyRows as $row) {
        $key = (string)$row['month_key'];

        if (!isset($companyMonthly[$key])) {
            continue;
        }

        $reviewed = (int)$row['reviewed_employees'];

        $companyMonthly[$key] = [
            'average' => $row['average_score'] !== null
                ? (float)$row['average_score']
                : null,
            'reviewed' => $reviewed,
            'coverage' => $totalEmployees > 0
                ? round(100 * $reviewed / $totalEmployees, 1)
                : null,
            'below_threshold' => (int)$row['below_threshold'],
        ];
    }

    $latestKey = null;

    for ($i = count($months) - 1; $i >= 0; $i--) {
        $key = (string)$months[$i]['key'];

        if ($companyMonthly[$key]['average'] !== null) {
            $latestKey = $key;
            break;
        }
    }

    $latestMonth = null;
    $latestCompany = [
        'average' => null,
        'reviewed' => 0,
        'coverage' => null,
        'below_threshold' => 0,
    ];

    if ($latestKey !== null) {
        foreach ($months as $month) {
            if ($month['key'] === $latestKey) {
                $latestMonth = $month;
                break;
            }
        }

        $latestCompany = $companyMonthly[$latestKey];
    }

    $departmentsWithData = 0;

    foreach ($departments as $department) {
        if ($department['latest_score'] !== null) {
            $departmentsWithData++;
        }
    }

    return [
        'company_id' => $companyId,
        'threshold' => ANALYTICS_PERFORMANCE_THRESHOLD,
        'months' => $months,
        'departments' => $departments,
        'company_monthly' => $companyMonthly,
        'total_employees' => $totalEmployees,
        'departments_tracked' => $departmentsWithData,
        'latest_month' => $latestMonth,
        'latest' => $latestCompany,
    ];
}
