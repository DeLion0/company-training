<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/performance-rules.php';

const TRAINING_PRIORITY_THRESHOLD = 50.0;

function training_company_id(int $actorId): int
{
    $q = database()->prepare(
        "SELECT u.company_id
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = ?
           AND u.status = 'active'
           AND r.role_key = 'hr'
         LIMIT 1"
    );

    $q->execute([$actorId]);
    $id = $q->fetchColumn();

    if (!$id) {
        throw new RuntimeException('HR authorization required.');
    }

    return (int) $id;
}

function training_text(array $input, string $key, int $max): string
{
    $value = trim((string) ($input[$key] ?? ''));
    $length = function_exists('mb_strlen')
        ? mb_strlen($value)
        : strlen($value);

    if ($value === '' || $length > $max) {
        throw new InvalidArgumentException(
            'Invalid ' . $key . ' (maximum ' . $max . ' characters).'
        );
    }

    return $value;
}

function training_date(string $value): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Invalid training date.');
    }

    return $date;
}

function training_time(string $value): string
{
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
        throw new InvalidArgumentException('Invalid training time.');
    }

    return $value;
}

function training_build_schedule(array $input): array
{
    $start = training_date((string) ($input['startDate'] ?? ''));
    $end = training_date((string) ($input['endDate'] ?? ''));

    $days = (int) $start->diff($end)->format('%r%a');

    if ($days < 0 || $days > 366) {
        throw new InvalidArgumentException(
            'Select a date range of 0–366 days.'
        );
    }

    $rawDay = (string) ($input['weekday'] ?? '');
    $rawCount = (string) ($input['meetingCount'] ?? '');

    if (
        !preg_match('/^[0-6]$/', $rawDay)
        || !preg_match('/^[1-9][0-9]{0,2}$/', $rawCount)
    ) {
        throw new InvalidArgumentException(
            'Invalid recurrence settings.'
        );
    }

    $weekday = (int) $rawDay;
    $count = (int) $rawCount;

    if ($count < 1 || $count > 100) {
        throw new InvalidArgumentException(
            'Meeting count must be 1–100.'
        );
    }

    $startTime = training_time(
        (string) ($input['startTime'] ?? '')
    );

    $endTime = training_time(
        (string) ($input['endTime'] ?? '')
    );

    if ($startTime >= $endTime) {
        throw new InvalidArgumentException(
            'End time must be later than start time.'
        );
    }

    $dates = [];

    for (
        $date = $start;
        $date <= $end;
        $date = $date->modify('+1 day')
    ) {
        if ((int) $date->format('w') === $weekday) {
            $dates[] = $date->format('Y-m-d');
        }
    }

    if (count($dates) < $count) {
        throw new InvalidArgumentException(
            'Not enough matching weekdays within date range.'
        );
    }

    return [
        array_slice($dates, 0, $count),
        $start->format('Y-m-d'),
        $end->format('Y-m-d'),
        $weekday,
        $count,
        $startTime,
        $endTime,
    ];
}

/**
 * Returns the most recent official monthly performance per employee.
 *
 * employee_monthly_performance is the canonical source.
 * Employees with no official monthly rating are omitted.
 */
function training_latest_monthly_performance_sql(): string
{
    return "
        SELECT
            mp.company_id,
            mp.employee_id,
            DATE_FORMAT(
                mp.review_month,
                '%Y-%m'
            ) AS month_key,
            mp.score AS average_score
        FROM employee_monthly_performance mp
        INNER JOIN (
            SELECT
                company_id,
                employee_id,
                MAX(review_month) AS latest_month
            FROM employee_monthly_performance
            GROUP BY
                company_id,
                employee_id
        ) latest
            ON latest.company_id = mp.company_id
           AND latest.employee_id = mp.employee_id
           AND latest.latest_month = mp.review_month
    ";
}

/**
 * Department options shown to HR while creating a program.
 * priority_candidates is an estimate based on the latest monthly
 * performance currently saved in the database.
 */
function training_department_options(int $actorId): array
{
    $companyId = training_company_id($actorId);
    $db = database();

    $monthlySql = training_latest_monthly_performance_sql();

    $q = $db->prepare(
        "SELECT
            d.id,
            d.name,
            COUNT(DISTINCT e.id) AS employee_count,
            COALESCE(
                SUM(
                    CASE
                        WHEN monthly.average_score < ?
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS priority_candidates
         FROM departments d
         LEFT JOIN employees e
           ON e.department_id = d.id
          AND e.company_id = d.company_id
          AND e.employment_status = 'active'
         LEFT JOIN (
            {$monthlySql}
         ) monthly
           ON monthly.company_id = e.company_id
          AND monthly.employee_id = e.id
         WHERE d.company_id = ?
         GROUP BY d.id, d.name
         ORDER BY d.name"
    );

    $q->execute([
        TRAINING_PRIORITY_THRESHOLD,
        $companyId,
    ]);

    return $q->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Validate that every selected department belongs to the HR user's company.
 */
function training_selected_departments(
    array $input,
    int $companyId
): array {
    $raw = $input['department_ids'] ?? [];

    if (!is_array($raw)) {
        throw new InvalidArgumentException(
            'Invalid target department selection.'
        );
    }

    $ids = [];

    foreach ($raw as $value) {
        $value = trim((string) $value);

        if (!preg_match('/^[1-9][0-9]*$/', $value)) {
            throw new InvalidArgumentException(
                'Invalid target department selection.'
            );
        }

        $ids[(int) $value] = true;
    }

    $ids = array_keys($ids);

    if (!$ids) {
        throw new InvalidArgumentException(
            'Select at least one target department.'
        );
    }

    if (count($ids) > 100) {
        throw new InvalidArgumentException(
            'Too many target departments selected.'
        );
    }

    $placeholders = implode(
        ',',
        array_fill(0, count($ids), '?')
    );

    $params = array_merge(
        [$companyId],
        $ids
    );

    $q = database()->prepare(
        "SELECT id
         FROM departments
         WHERE company_id = ?
           AND id IN ({$placeholders})"
    );

    $q->execute($params);

    $validIds = array_map(
        'intval',
        $q->fetchAll(PDO::FETCH_COLUMN)
    );

    sort($ids);
    sort($validIds);

    if ($ids !== $validIds) {
        throw new InvalidArgumentException(
            'One or more target departments are invalid.'
        );
    }

    return $validIds;
}

/**
 * Create automatic priority recommendations for selected departments.
 *
 * Rules:
 * - active employees only
 * - latest month with at least one saved performance review
 * - average all reviews inside that latest month
 * - strictly below 50 = priority recommended
 * - exactly 50 or above = not automatically recommended
 * - no review = not automatically recommended
 * - recommendation does not consume capacity and is not a Join
 */
function training_create_priority_recommendations(
    PDO $db,
    int $programId,
    int $companyId,
    int $actorId,
    array $departmentIds
): int {
    if (!$departmentIds) {
        return 0;
    }

    $placeholders = implode(
        ',',
        array_fill(0, count($departmentIds), '?')
    );

    $monthlySql =
        training_latest_monthly_performance_sql();

    $params = array_merge(
        [$companyId],
        $departmentIds,
        [TRAINING_PRIORITY_THRESHOLD]
    );

    $q = $db->prepare(
        "SELECT
            e.id AS employee_id,
            e.department_id,
            monthly.month_key,
            monthly.average_score
         FROM employees e
         INNER JOIN (
            {$monthlySql}
         ) monthly
           ON monthly.company_id = e.company_id
          AND monthly.employee_id = e.id
         WHERE e.company_id = ?
           AND e.employment_status = 'active'
           AND e.department_id IN ({$placeholders})
           AND monthly.average_score < ?
         ORDER BY e.department_id, e.id"
    );

    $q->execute($params);
    $candidates =
        $q->fetchAll(PDO::FETCH_ASSOC);

    if (!$candidates) {
        return 0;
    }

    $applicationInsert = $db->prepare(
        "INSERT INTO training_applications (
            program_id,
            employee_id,
            source,
            status,
            submitted_by,
            review_notes
         ) VALUES (
            ?,
            ?,
            'hr_recommendation',
            ?,
            ?,
            ?
         )"
    );

    $metaInsert = $db->prepare(
        "INSERT INTO training_recommendation_meta (
            application_id,
            recommendation_kind,
            priority,
            performance_score,
            performance_month
         ) VALUES (
            ?,
            ?,
            1,
            ?,
            ?
         )"
    );

    $created = 0;

    foreach ($candidates as $candidate) {
        $employeeId =
            (int) $candidate['employee_id'];

        $score = round(
            (float) $candidate['average_score'],
            2
        );

        $month =
            (string) $candidate['month_key'];

        $streak =
            growflow_low_performance_streak(
                $db,
                $companyId,
                $employeeId,
                TRAINING_PRIORITY_THRESHOLD
            );

        $mandatory =
            !empty($streak['mandatory']);

        if ($mandatory) {
            $status = 'joined';
            $kind =
                'mandatory_low_performance';

            $note = sprintf(
                'Mandatory training: %d consecutive monthly performance ratings below 50%% (%s to %s). Latest score %.2f%%.',
                (int) $streak['count'],
                (string) $streak['start_month'],
                (string) $streak['end_month'],
                $score
            );
        } else {
            $status = 'recommended';
            $kind = 'low_performance';

            $note = sprintf(
                'Priority recommendation: latest monthly performance is %.2f%% (%s), below the 50%% threshold.',
                $score,
                $month
            );
        }

        $applicationInsert->execute([
            $programId,
            $employeeId,
            $status,
            $actorId,
            $note,
        ]);

        $applicationId =
            (int) $db->lastInsertId();

        $metaInsert->execute([
            $applicationId,
            $kind,
            $score,
            $month,
        ]);

        $created++;
    }

    return $created;
}

function training_create(int $actorId, array $input): int
{
    $companyId = training_company_id($actorId);

    $title = training_text(
        $input,
        'title',
        160
    );

    $purpose = training_text(
        $input,
        'purpose',
        10000
    );

    $objectives = training_text(
        $input,
        'objectives',
        10000
    );

    $instructors = training_text(
        $input,
        'instructors',
        500
    );

    $venue = training_text(
        $input,
        'venue',
        255
    );

    $rawCapacity = (string) ($input['capacity'] ?? '');

    if (
        !preg_match('/^[1-9][0-9]{0,4}$/', $rawCapacity)
        || (int) $rawCapacity > 10000
    ) {
        throw new InvalidArgumentException(
            'Capacity must be 1–10000.'
        );
    }

    $departmentIds = training_selected_departments(
        $input,
        $companyId
    );

    [
        $dates,
        $start,
        $end,
        $weekday,
        $count,
        $startTime,
        $endTime,
    ] = training_build_schedule($input);

    $db = database();
    $db->beginTransaction();

    try {
        $q = $db->prepare(
            "INSERT INTO training_programs (
                company_id,
                created_by,
                title,
                purpose,
                objectives,
                instructors,
                venue,
                capacity,
                range_start,
                range_end,
                weekday,
                meeting_count,
                start_time,
                end_time,
                status
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published'
             )"
        );

        $q->execute([
            $companyId,
            $actorId,
            $title,
            $purpose,
            $objectives,
            $instructors,
            $venue,
            (int) $rawCapacity,
            $start,
            $end,
            $weekday,
            $count,
            $startTime,
            $endTime,
        ]);

        $programId = (int) $db->lastInsertId();

        $sessionInsert = $db->prepare(
            "INSERT INTO training_sessions (
                program_id,
                session_number,
                session_date,
                start_time,
                end_time
             ) VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($dates as $index => $date) {
            $sessionInsert->execute([
                $programId,
                $index + 1,
                $date,
                $startTime,
                $endTime,
            ]);
        }

        $departmentInsert = $db->prepare(
            "INSERT INTO training_program_departments (
                program_id,
                department_id
             ) VALUES (?, ?)"
        );

        foreach ($departmentIds as $departmentId) {
            $departmentInsert->execute([
                $programId,
                $departmentId,
            ]);
        }

        training_create_priority_recommendations(
            $db,
            $programId,
            $companyId,
            $actorId,
            $departmentIds
        );

        $db->commit();

        return $programId;
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $ex;
    }
}

function training_overview(int $actorId): array
{
    $companyId = training_company_id($actorId);
    $db = database();

    $q = $db->prepare(
        "SELECT
            COUNT(*) AS programs,
            COALESCE(SUM(capacity), 0) AS capacity
         FROM training_programs
         WHERE company_id = ?
           AND status = 'published'"
    );

    $q->execute([$companyId]);
    $stats = $q->fetch(PDO::FETCH_ASSOC);

    $q = $db->prepare(
        "SELECT COUNT(*)
         FROM training_sessions s
         JOIN training_programs p
           ON p.id = s.program_id
         WHERE p.company_id = ?
           AND p.status = 'published'
           AND s.session_date >= CURRENT_DATE()"
    );

    $q->execute([$companyId]);
    $stats['upcoming'] = (int) $q->fetchColumn();

    $q = $db->prepare(
        "SELECT
            p.id,
            p.title,
            p.purpose,
            p.objectives,
            p.instructors,
            p.venue,
            p.capacity,
            p.range_start,
            p.range_end,
            p.weekday,
            p.meeting_count,
            p.start_time,
            p.end_time,
            p.status,
            p.created_at
         FROM training_programs p
         WHERE p.company_id = ?
         ORDER BY p.created_at DESC, p.id DESC
         LIMIT 100"
    );

    $q->execute([$companyId]);
    $programs = $q->fetchAll(PDO::FETCH_ASSOC);

    $ids = array_map(
        'intval',
        array_column($programs, 'id')
    );

    $sessions = [];
    $departments = [];
    $priorityCounts = [];

    if ($ids) {
        $placeholders = implode(
            ',',
            array_fill(0, count($ids), '?')
        );

        $q = $db->prepare(
            "SELECT
                program_id,
                session_number,
                session_date,
                start_time,
                end_time
             FROM training_sessions
             WHERE program_id IN ({$placeholders})
             ORDER BY program_id, session_number"
        );

        $q->execute($ids);

        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $session) {
            $sessions[(int) $session['program_id']][] = $session;
        }

        $q = $db->prepare(
            "SELECT
                tpd.program_id,
                d.id AS department_id,
                d.name
             FROM training_program_departments tpd
             JOIN departments d
               ON d.id = tpd.department_id
             JOIN training_programs p
               ON p.id = tpd.program_id
              AND p.company_id = d.company_id
             WHERE tpd.program_id IN ({$placeholders})
             ORDER BY tpd.program_id, d.name"
        );

        $q->execute($ids);

        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $department) {
            $departments[(int) $department['program_id']][] = [
                'id' => (int) $department['department_id'],
                'name' => (string) $department['name'],
            ];
        }

        $q = $db->prepare(
            "SELECT
                ta.program_id,
                COUNT(*) AS priority_count
             FROM training_applications ta
             JOIN training_recommendation_meta trm
               ON trm.application_id = ta.id
              AND trm.priority = 1
              AND trm.recommendation_kind = 'low_performance'
             WHERE ta.program_id IN ({$placeholders})
               AND ta.status = 'recommended'
             GROUP BY ta.program_id"
        );

        $q->execute($ids);

        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $priorityCounts[(int) $row['program_id']] =
                (int) $row['priority_count'];
        }
    }

    foreach ($programs as &$program) {
        $programId = (int) $program['id'];

        $program['sessions'] =
            $sessions[$programId] ?? [];

        $program['departments'] =
            $departments[$programId] ?? [];

        $program['priority_recommendations'] =
            $priorityCounts[$programId] ?? 0;
    }

    unset($program);

    return [
        'stats' => $stats,
        'programs' => $programs,
        'departments' => training_department_options($actorId),
        'priority_threshold' => TRAINING_PRIORITY_THRESHOLD,
    ];
}
