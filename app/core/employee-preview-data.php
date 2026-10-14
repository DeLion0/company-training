<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function employee_preview_company_id(int $actorId): int
{
    $stmt = database()->prepare(
        "SELECT company_id
         FROM users
         WHERE id = ?
           AND status = 'active'
         LIMIT 1"
    );
    $stmt->execute([$actorId]);

    $companyId = (int) $stmt->fetchColumn();

    if ($companyId < 1) {
        throw new RuntimeException('Company not found for this account.');
    }

    return $companyId;
}

function employee_preview_months(int $count = 6): array
{
    $count = max(1, min(12, $count));
    $cursor = new DateTimeImmutable('first day of this month');
    $months = [];

    for ($i = $count - 1; $i >= 0; $i--) {
        $date = $cursor->modify('-' . $i . ' months');
        $months[] = [
            'key' => $date->format('Y-m'),
            'label' => $date->format('M'),
            'long_label' => $date->format('F Y'),
        ];
    }

    return $months;
}

function employee_preview_data(
    int $actorId,
    ?int $requestedEmployeeId = null
): array {
    $db = database();
    $companyId = employee_preview_company_id($actorId);

    $employeesQuery = $db->prepare(
        "SELECT
            e.id,
            e.employee_code,
            e.full_name,
            e.job_title,
            e.department_id,
            e.joined_at,
            e.work_email,
            e.user_id,
            d.name AS department_name,
            u.email AS account_email,
            u.status AS account_status,
            u.last_login_at,
            m.full_name AS manager_name
         FROM employees e
         JOIN departments d
           ON d.id = e.department_id
          AND d.company_id = e.company_id
         LEFT JOIN users u
           ON u.id = e.user_id
          AND u.company_id = e.company_id
         LEFT JOIN users m
           ON m.id = e.manager_user_id
          AND m.company_id = e.company_id
         WHERE e.company_id = ?
           AND e.employment_status = 'active'
         ORDER BY e.full_name"
    );
    $employeesQuery->execute([$companyId]);
    $employees = $employeesQuery->fetchAll(PDO::FETCH_ASSOC);

    if (!$employees) {
        return [
            'company_id' => $companyId,
            'employees' => [],
            'employee' => null,
            'months' => employee_preview_months(),
            'monthly' => [],
            'latest' => null,
            'performance_history' => [],
            'priority_trainings' => [],
            'recommended_trainings' => [],
            'joined_trainings' => [],
            'joined_active' => [],
            'joined_completed' => [],
            'available_trainings' => [],
            'upcoming_sessions' => [],
            'stats' => [
                'priority' => 0,
                'joined' => 0,
                'upcoming' => 0,
                'available' => 0,
            ],
        ];
    }

    $employee = null;

    if ($requestedEmployeeId !== null && $requestedEmployeeId > 0) {
        foreach ($employees as $candidate) {
            if ((int) $candidate['id'] === $requestedEmployeeId) {
                $employee = $candidate;
                break;
            }
        }
    }

    $employee ??= $employees[0];
    $employeeId = (int) $employee['id'];
    $departmentId = (int) $employee['department_id'];

    $months = employee_preview_months(6);
    $firstMonth = $months[0]['key'] . '-01';

    $performanceQuery = $db->prepare(
        "SELECT
            DATE_FORMAT(review_month, '%Y-%m') AS month_key,
            review_month,
            score,
            notes,
            source
         FROM employee_monthly_performance
         WHERE company_id = ?
           AND employee_id = ?
           AND review_month >= ?
         ORDER BY review_month"
    );
    $performanceQuery->execute([$companyId, $employeeId, $firstMonth]);
    $performanceRows = $performanceQuery->fetchAll(PDO::FETCH_ASSOC);

    $monthly = array_fill_keys(array_column($months, 'key'), null);

    foreach ($performanceRows as $row) {
        $key = (string) $row['month_key'];
        if (array_key_exists($key, $monthly)) {
            $monthly[$key] = (float) $row['score'];
        }
    }

    $latestQuery = $db->prepare(
        "SELECT review_month, score, notes, source
         FROM employee_monthly_performance
         WHERE company_id = ?
           AND employee_id = ?
         ORDER BY review_month DESC, id DESC
         LIMIT 1"
    );
    $latestQuery->execute([$companyId, $employeeId]);
    $latest = $latestQuery->fetch(PDO::FETCH_ASSOC) ?: null;

    $historyQuery = $db->prepare(
        "SELECT review_month, score, notes, source
         FROM employee_monthly_performance
         WHERE company_id = ?
           AND employee_id = ?
         ORDER BY review_month DESC, id DESC
         LIMIT 12"
    );
    $historyQuery->execute([$companyId, $employeeId]);
    $performanceHistory = $historyQuery->fetchAll(PDO::FETCH_ASSOC);

    $priorityQuery = $db->prepare(
        "SELECT
            p.id,
            p.title,
            p.purpose,
            p.objectives,
            p.instructors,
            p.venue,
            p.range_start,
            p.range_end,
            p.capacity,
            ta.status,
            ta.source,
            trm.performance_score,
            trm.performance_month,
            trm.priority,
            (
                SELECT COUNT(*)
                FROM training_applications x
                WHERE x.program_id = p.id
                  AND x.status = 'joined'
            ) AS joined_count
         FROM training_applications ta
         JOIN training_programs p
           ON p.id = ta.program_id
         JOIN training_recommendation_meta trm
           ON trm.application_id = ta.id
          AND trm.recommendation_kind = 'low_performance'
          AND trm.priority = 1
         WHERE ta.employee_id = ?
           AND p.company_id = ?
           AND p.status = 'published'
           AND ta.status = 'recommended'
           AND p.range_end >= CURRENT_DATE()
         ORDER BY p.range_start, p.id"
    );
    $priorityQuery->execute([$employeeId, $companyId]);
    $priorityTrainings = $priorityQuery->fetchAll(PDO::FETCH_ASSOC);

    $recommendedQuery = $db->prepare(
        "SELECT
            p.id,
            p.title,
            p.purpose,
            p.objectives,
            p.instructors,
            p.venue,
            p.range_start,
            p.range_end,
            p.capacity,
            ta.status,
            ta.source,
            COALESCE(trm.priority, 0) AS priority,
            trm.performance_score,
            trm.performance_month,
            (
                SELECT COUNT(*)
                FROM training_applications x
                WHERE x.program_id = p.id
                  AND x.status = 'joined'
            ) AS joined_count
         FROM training_applications ta
         JOIN training_programs p
           ON p.id = ta.program_id
         LEFT JOIN training_recommendation_meta trm
           ON trm.application_id = ta.id
          AND trm.recommendation_kind = 'low_performance'
         WHERE ta.employee_id = ?
           AND p.company_id = ?
           AND p.status = 'published'
           AND ta.status = 'recommended'
         ORDER BY COALESCE(trm.priority, 0) DESC, p.range_start, p.id"
    );
    $recommendedQuery->execute([$employeeId, $companyId]);
    $recommendedTrainings = $recommendedQuery->fetchAll(PDO::FETCH_ASSOC);

    $joinedQuery = $db->prepare(
        "SELECT
            p.id,
            p.title,
            p.purpose,
            p.objectives,
            p.instructors,
            p.venue,
            p.range_start,
            p.range_end,
            p.capacity,
            ta.status,
            ta.source,
            (
                SELECT COUNT(*)
                FROM training_applications x
                WHERE x.program_id = p.id
                  AND x.status = 'joined'
            ) AS joined_count
         FROM training_applications ta
         JOIN training_programs p
           ON p.id = ta.program_id
         WHERE ta.employee_id = ?
           AND p.company_id = ?
           AND p.status = 'published'
           AND ta.status = 'joined'
         ORDER BY p.range_start DESC, p.id DESC"
    );
    $joinedQuery->execute([$employeeId, $companyId]);
    $joinedTrainings = $joinedQuery->fetchAll(PDO::FETCH_ASSOC);

    $joinedActive = [];
    $joinedCompleted = [];
    $today = new DateTimeImmutable('today');

    foreach ($joinedTrainings as $training) {
        $end = new DateTimeImmutable((string) $training['range_end']);
        if ($end < $today) {
            $joinedCompleted[] = $training;
        } else {
            $joinedActive[] = $training;
        }
    }

    $availableQuery = $db->prepare(
        "SELECT
            p.id,
            p.title,
            p.purpose,
            p.objectives,
            p.instructors,
            p.venue,
            p.range_start,
            p.range_end,
            p.capacity,
            ta.status AS application_status,
            ta.source AS application_source,
            COALESCE(trm.priority, 0) AS priority,
            trm.performance_score,
            trm.performance_month,
            (
                SELECT COUNT(*)
                FROM training_applications x
                WHERE x.program_id = p.id
                  AND x.status = 'joined'
            ) AS joined_count
         FROM training_programs p
         LEFT JOIN training_applications ta
           ON ta.program_id = p.id
          AND ta.employee_id = ?
         LEFT JOIN training_recommendation_meta trm
           ON trm.application_id = ta.id
          AND trm.recommendation_kind = 'low_performance'
         WHERE p.company_id = ?
           AND p.status = 'published'
           AND p.range_end >= CURRENT_DATE()
           AND (
                NOT EXISTS (
                    SELECT 1
                    FROM training_program_departments tpd0
                    WHERE tpd0.program_id = p.id
                )
                OR EXISTS (
                    SELECT 1
                    FROM training_program_departments tpd
                    WHERE tpd.program_id = p.id
                      AND tpd.department_id = ?
                )
           )
         ORDER BY COALESCE(trm.priority, 0) DESC, p.range_start, p.id"
    );
    $availableQuery->execute([$employeeId, $companyId, $departmentId]);
    $availableTrainings = $availableQuery->fetchAll(PDO::FETCH_ASSOC);

    $sessionQuery = $db->prepare(
        "SELECT
            s.session_date,
            s.start_time,
            s.end_time,
            s.session_number,
            p.id AS program_id,
            p.title,
            p.venue
         FROM training_applications ta
         JOIN training_programs p
           ON p.id = ta.program_id
         JOIN training_sessions s
           ON s.program_id = p.id
         WHERE ta.employee_id = ?
           AND p.company_id = ?
           AND p.status = 'published'
           AND ta.status = 'joined'
           AND s.session_date >= CURRENT_DATE()
         ORDER BY s.session_date, s.start_time
         LIMIT 20"
    );
    $sessionQuery->execute([$employeeId, $companyId]);
    $upcomingSessions = $sessionQuery->fetchAll(PDO::FETCH_ASSOC);

    return [
        'company_id' => $companyId,
        'employees' => $employees,
        'employee' => $employee,
        'months' => $months,
        'monthly' => $monthly,
        'latest' => $latest,
        'performance_history' => $performanceHistory,
        'priority_trainings' => $priorityTrainings,
        'recommended_trainings' => $recommendedTrainings,
        'joined_trainings' => $joinedTrainings,
        'joined_active' => $joinedActive,
        'joined_completed' => $joinedCompleted,
        'available_trainings' => $availableTrainings,
        'upcoming_sessions' => $upcomingSessions,
        'stats' => [
            'priority' => count($priorityTrainings),
            'joined' => count($joinedActive),
            'upcoming' => count($upcomingSessions),
            'available' => count($availableTrainings),
        ],
    ];
}


function employee_portal_data_for_user(int $userId): array
{
    $db = database();

    $stmt = $db->prepare(
        "SELECT
            e.id AS employee_id
         FROM users u
         JOIN roles r
           ON r.id = u.role_id
          AND r.role_key = 'employee'
         JOIN employees e
           ON e.user_id = u.id
          AND e.company_id = u.company_id
         WHERE u.id = ?
           AND u.status = 'active'
           AND e.employment_status = 'active'
         LIMIT 1"
    );

    $stmt->execute([$userId]);

    $employeeId = (int)$stmt->fetchColumn();

    if ($employeeId < 1) {
        throw new RuntimeException(
            'No active employee profile is linked to this account.'
        );
    }

    $data = employee_preview_data(
        $userId,
        $employeeId
    );

    // Never expose the company-wide employee preview list
    // in the real Employee Workspace.
    $data['employees'] = $data['employee']
        ? [$data['employee']]
        : [];

    return $data;
}
