<?php

declare(strict_types=1);

require_once __DIR__ . '/training-data.php';

function available_training_data(int $actor): array
{
    $companyId = training_company_id($actor);
    $db = database();

    $programStmt = $db->prepare(
        "SELECT
            p.*,
            (SELECT COUNT(*) FROM training_applications a WHERE a.program_id = p.id AND a.status = 'joined') AS joined_count,
            (SELECT COUNT(*) FROM training_applications a WHERE a.program_id = p.id AND a.status = 'recommended') AS recommended_count
         FROM training_programs p
         WHERE p.company_id = ?
           AND p.status = 'published'
         ORDER BY p.created_at DESC, p.id DESC"
    );
    $programStmt->execute([$companyId]);
    $programs = $programStmt->fetchAll(PDO::FETCH_ASSOC);

    $sessionStmt = $db->prepare(
        "SELECT s.program_id, s.session_number, s.session_date, s.start_time, s.end_time
         FROM training_sessions s
         JOIN training_programs p ON p.id = s.program_id
         WHERE p.company_id = ?
           AND p.status = 'published'
         ORDER BY s.program_id, s.session_number"
    );
    $sessionStmt->execute([$companyId]);
    $sessions = [];
    foreach ($sessionStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sessions[(int) $row['program_id']][] = $row;
    }

    $participantStmt = $db->prepare(
        "SELECT
            a.id,
            a.program_id,
            a.employee_id,
            a.source,
            a.status,
            a.created_at,
            e.full_name,
            e.employee_code,
            e.job_title,
            d.id AS department_id,
            d.name AS department_name,
            pr.overall_score,
            pr.review_date,
            rv.full_name AS reviewer_name
         FROM training_applications a
         JOIN training_programs p
           ON p.id = a.program_id
         JOIN employees e
           ON e.id = a.employee_id
          AND e.company_id = p.company_id
         JOIN departments d
           ON d.id = e.department_id
          AND d.company_id = p.company_id
         LEFT JOIN performance_reviews pr
           ON pr.id = (
               SELECT r.id
               FROM performance_reviews r
               WHERE r.employee_id = e.id
               ORDER BY r.review_date DESC, r.id DESC
               LIMIT 1
           )
         LEFT JOIN users rv ON rv.id = pr.reviewer_user_id
         WHERE p.company_id = ?
           AND a.status IN ('joined', 'recommended')
         ORDER BY
           CASE WHEN a.status = 'joined' THEN 0 ELSE 1 END,
           a.created_at DESC,
           a.id DESC"
    );
    $participantStmt->execute([$companyId]);
    $participants = [];
    foreach ($participantStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $participants[(int) $row['program_id']][] = $row;
    }

    // Latest employee score per employee, used for recommendation targeting.
    $employeeStmt = $db->prepare(
        "SELECT
            e.id,
            e.full_name,
            e.employee_code,
            e.job_title,
            e.department_id,
            d.name AS department_name,
            pr.overall_score,
            pr.review_date
         FROM employees e
         JOIN departments d
           ON d.id = e.department_id
          AND d.company_id = e.company_id
         LEFT JOIN performance_reviews pr
           ON pr.id = (
               SELECT r.id
               FROM performance_reviews r
               WHERE r.employee_id = e.id
               ORDER BY r.review_date DESC, r.id DESC
               LIMIT 1
           )
         WHERE e.company_id = ?
           AND e.employment_status = 'active'
         ORDER BY
           d.name,
           CASE WHEN pr.overall_score IS NULL THEN 1 ELSE 0 END,
           pr.overall_score ASC,
           e.full_name ASC"
    );
    $employeeStmt->execute([$companyId]);
    $employees = $employeeStmt->fetchAll(PDO::FETCH_ASSOC);

    // Department averages are advisory only; no hard-coded "low" threshold is imposed.
    $departmentStmt = $db->prepare(
        "SELECT
            d.id,
            d.name,
            COUNT(e.id) AS employee_count,
            ROUND(AVG(latest.overall_score), 2) AS average_score,
            COUNT(latest.overall_score) AS evaluated_count
         FROM departments d
         LEFT JOIN employees e
           ON e.department_id = d.id
          AND e.company_id = d.company_id
          AND e.employment_status = 'active'
         LEFT JOIN (
             SELECT pr.employee_id, pr.overall_score
             FROM performance_reviews pr
             JOIN (
                 SELECT employee_id, MAX(id) AS latest_id
                 FROM performance_reviews
                 GROUP BY employee_id
             ) x ON x.latest_id = pr.id
         ) latest ON latest.employee_id = e.id
         WHERE d.company_id = ?
           AND d.status = 'active'
         GROUP BY d.id, d.name
         ORDER BY
           CASE WHEN AVG(latest.overall_score) IS NULL THEN 1 ELSE 0 END,
           AVG(latest.overall_score) ASC,
           d.name ASC"
    );
    $departmentStmt->execute([$companyId]);
    $departments = $departmentStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($programs as &$program) {
        $programId = (int) $program['id'];
        $program['sessions'] = $sessions[$programId] ?? [];
        $program['participants'] = $participants[$programId] ?? [];
        $program['open_slots'] = max(0, (int) $program['capacity'] - (int) $program['joined_count']);
    }
    unset($program);

    return [
        'programs' => $programs,
        'employees' => $employees,
        'departments' => $departments,
    ];
}

function available_training_recommend(int $actor, array $input): void
{
    $companyId = training_company_id($actor);
    $programId = (int) ($input['program_id'] ?? 0);
    $departmentId = (int) ($input['department_id'] ?? 0);
    $employeeId = (int) ($input['employee_id'] ?? 0);

    if ($programId <= 0 || $departmentId <= 0 || $employeeId <= 0) {
        throw new InvalidArgumentException('Choose a department and employee to recommend.');
    }

    $db = database();

    $programStmt = $db->prepare(
        "SELECT id, capacity
         FROM training_programs
         WHERE id = ? AND company_id = ? AND status = 'published'"
    );
    $programStmt->execute([$programId, $companyId]);
    $program = $programStmt->fetch(PDO::FETCH_ASSOC);
    if (!$program) {
        throw new InvalidArgumentException('Training program was not found.');
    }

    $employeeStmt = $db->prepare(
        "SELECT id
         FROM employees
         WHERE id = ?
           AND company_id = ?
           AND department_id = ?
           AND employment_status = 'active'"
    );
    $employeeStmt->execute([$employeeId, $companyId, $departmentId]);
    if (!$employeeStmt->fetchColumn()) {
        throw new InvalidArgumentException('Employee is not active in the selected department.');
    }

    // Do not create new recommendations if all seats are already joined.
    $joinedStmt = $db->prepare(
        "SELECT COUNT(*)
         FROM training_applications
         WHERE program_id = ? AND status = 'joined'"
    );
    $joinedStmt->execute([$programId]);
    if ((int) $joinedStmt->fetchColumn() >= (int) $program['capacity']) {
        throw new InvalidArgumentException('This training has no open slots.');
    }

    try {
        $insert = $db->prepare(
            "INSERT INTO training_applications
                (program_id, employee_id, source, status, submitted_by)
             VALUES (?, ?, 'hr_recommendation', 'recommended', ?)"
        );
        $insert->execute([$programId, $employeeId, $actor]);
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            throw new InvalidArgumentException('This employee is already joined or recommended for the program.');
        }
        throw $ex;
    }
}

/**
 * Future Employee Portal hook.
 * Employee self-join is automatic, but capacity is enforced transactionally.
 */
function available_training_join_employee(int $employeeId, int $programId): void
{
    if ($employeeId <= 0 || $programId <= 0) {
        throw new InvalidArgumentException('Invalid employee or program.');
    }

    $db = database();
    $db->beginTransaction();

    try {
        $programStmt = $db->prepare(
            "SELECT p.id, p.company_id, p.capacity, p.status
             FROM training_programs p
             JOIN employees e ON e.company_id = p.company_id
             WHERE p.id = ? AND e.id = ?
             FOR UPDATE"
        );
        $programStmt->execute([$programId, $employeeId]);
        $program = $programStmt->fetch(PDO::FETCH_ASSOC);

        if (!$program || $program['status'] !== 'published') {
            throw new InvalidArgumentException('Training program is not available.');
        }

        $joinedStmt = $db->prepare(
            "SELECT COUNT(*)
             FROM training_applications
             WHERE program_id = ? AND status = 'joined'"
        );
        $joinedStmt->execute([$programId]);
        if ((int) $joinedStmt->fetchColumn() >= (int) $program['capacity']) {
            throw new InvalidArgumentException('Training is already full.');
        }

        $existingStmt = $db->prepare(
            "SELECT id, status
             FROM training_applications
             WHERE program_id = ? AND employee_id = ?
             FOR UPDATE"
        );
        $existingStmt->execute([$programId, $employeeId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ($existing['status'] === 'joined') {
                throw new InvalidArgumentException('Employee is already joined.');
            }

            $update = $db->prepare(
                "UPDATE training_applications
                 SET source = 'employee', status = 'joined', updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $update->execute([(int) $existing['id']]);
        } else {
            $insert = $db->prepare(
                "INSERT INTO training_applications
                    (program_id, employee_id, source, status, submitted_by)
                 VALUES (?, ?, 'employee', 'joined', NULL)"
            );
            $insert->execute([$programId, $employeeId]);
        }

        $db->commit();
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $ex;
    }
}
