<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function employee_action_identity(int $userId): array
{
    $stmt = database()->prepare(
        "SELECT
            u.id AS user_id,
            u.company_id,
            u.password_hash,
            e.id AS employee_id,
            e.department_id,
            e.full_name,
            e.employee_code
         FROM users u
         JOIN roles r
           ON r.id = u.role_id
          AND r.role_key = 'employee'
         JOIN employees e
           ON e.user_id = u.id
          AND e.company_id = u.company_id
          AND e.employment_status = 'active'
         WHERE u.id = ?
           AND u.status = 'active'
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $identity = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$identity) {
        throw new RuntimeException('Active employee account was not found.');
    }

    return $identity;
}

function employee_action_manila_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
}

function employee_action_training_end(PDO $db, int $programId, string $fallbackRangeEnd): ?DateTimeImmutable
{
    $stmt = $db->prepare(
        "SELECT MAX(TIMESTAMP(session_date, end_time))
         FROM training_sessions
         WHERE program_id = ?"
    );
    $stmt->execute([$programId]);
    $raw = trim((string)$stmt->fetchColumn());

    try {
        if ($raw !== '') {
            return new DateTimeImmutable($raw, new DateTimeZone('Asia/Manila'));
        }

        if ($fallbackRangeEnd !== '') {
            return new DateTimeImmutable(
                $fallbackRangeEnd . ' 23:59:59',
                new DateTimeZone('Asia/Manila')
            );
        }
    } catch (Throwable) {
        return null;
    }

    return null;
}

function employee_join_training(int $userId, int $programId): string
{
    if ($programId < 1) {
        throw new InvalidArgumentException('Choose a valid training program.');
    }

    $identity = employee_action_identity($userId);
    $db = database();
    $db->beginTransaction();

    try {
        $programStmt = $db->prepare(
            "SELECT id, company_id, title, capacity, range_end, status
             FROM training_programs
             WHERE id = ? AND company_id = ?
             FOR UPDATE"
        );
        $programStmt->execute([$programId, (int)$identity['company_id']]);
        $program = $programStmt->fetch(PDO::FETCH_ASSOC);

        if (!$program || (string)$program['status'] !== 'published') {
            throw new InvalidArgumentException('Training program is not available.');
        }

        $endAt = employee_action_training_end(
            $db,
            $programId,
            (string)$program['range_end']
        );

        if ($endAt !== null && $endAt < employee_action_manila_now()) {
            throw new InvalidArgumentException('This training program has already ended.');
        }

        $targetStmt = $db->prepare(
            "SELECT
                COUNT(*) AS target_count,
                SUM(CASE WHEN department_id = ? THEN 1 ELSE 0 END) AS matching_count
             FROM training_program_departments
             WHERE program_id = ?"
        );
        $targetStmt->execute([(int)$identity['department_id'], $programId]);
        $target = $targetStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if (
            (int)($target['target_count'] ?? 0) > 0
            && (int)($target['matching_count'] ?? 0) < 1
        ) {
            throw new InvalidArgumentException('This training is not available to your department.');
        }

        $existingStmt = $db->prepare(
            "SELECT ta.id, ta.status, trm.recommendation_kind
             FROM training_applications ta
             LEFT JOIN training_recommendation_meta trm
               ON trm.application_id = ta.id
             WHERE ta.program_id = ?
               AND ta.employee_id = ?
             FOR UPDATE"
        );
        $existingStmt->execute([$programId, (int)$identity['employee_id']]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing && (string)$existing['status'] === 'joined') {
            throw new InvalidArgumentException('You are already enrolled in this training.');
        }

        $joinedStmt = $db->prepare(
            "SELECT COUNT(*)
             FROM training_applications
             WHERE program_id = ? AND status = 'joined'"
        );
        $joinedStmt->execute([$programId]);

        if ((int)$joinedStmt->fetchColumn() >= (int)$program['capacity']) {
            throw new InvalidArgumentException('This training currently has no open slots.');
        }

        if ($existing) {
            $update = $db->prepare(
                "UPDATE training_applications
                 SET source = 'employee',
                     status = 'joined',
                     submitted_by = ?,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $update->execute([$userId, (int)$existing['id']]);
        } else {
            $insert = $db->prepare(
                "INSERT INTO training_applications
                    (program_id, employee_id, source, status, submitted_by)
                 VALUES (?, ?, 'employee', 'joined', ?)"
            );
            $insert->execute([$programId, (int)$identity['employee_id'], $userId]);
        }

        $db->commit();
        return 'You joined "' . (string)$program['title'] . '".';
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $ex;
    }
}

function employee_change_password(
    int $userId,
    string $currentPassword,
    string $newPassword,
    string $confirmPassword
): void {
    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        throw new InvalidArgumentException('Complete all password fields.');
    }

    if ($newPassword !== $confirmPassword) {
        throw new InvalidArgumentException('New password and confirmation do not match.');
    }

    $length = strlen($newPassword);
    if ($length < 12 || $length > 128) {
        throw new InvalidArgumentException('New password must be 12–128 characters.');
    }

    if (
        !preg_match('/[a-z]/', $newPassword)
        || !preg_match('/[A-Z]/', $newPassword)
        || !preg_match('/[0-9]/', $newPassword)
        || !preg_match('/[^A-Za-z0-9]/', $newPassword)
    ) {
        throw new InvalidArgumentException(
            'Use uppercase, lowercase, a number, and a symbol in the new password.'
        );
    }

    $identity = employee_action_identity($userId);
    $hash = (string)($identity['password_hash'] ?? '');

    if ($hash === '' || !password_verify($currentPassword, $hash)) {
        throw new InvalidArgumentException('Current password is incorrect.');
    }

    if (password_verify($newPassword, $hash)) {
        throw new InvalidArgumentException(
            'Choose a new password different from your current password.'
        );
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if (!is_string($newHash)) {
        throw new RuntimeException('Unable to secure the new password.');
    }

    $stmt = database()->prepare(
        "UPDATE users SET password_hash = ? WHERE id = ? AND status = 'active'"
    );
    $stmt->execute([$newHash, $userId]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Password was not updated.');
    }
}

function employee_training_details_payload(array $portalData): array
{
    $details = [];

    foreach (
        ['priority_trainings', 'recommended_trainings', 'joined_trainings', 'available_trainings']
        as $key
    ) {
        foreach ((array)($portalData[$key] ?? []) as $training) {
            $id = (int)($training['id'] ?? 0);
            if ($id < 1) {
                continue;
            }

            $details[$id] = [
                'id' => $id,
                'title' => (string)($training['title'] ?? ''),
                'purpose' => (string)($training['purpose'] ?? ''),
                'objectives' => (string)($training['objectives'] ?? ''),
                'instructors' => (string)($training['instructors'] ?? ''),
                'venue' => (string)($training['venue'] ?? ''),
                'range_start' => (string)($training['range_start'] ?? ''),
                'range_end' => (string)($training['range_end'] ?? ''),
                'capacity' => (int)($training['capacity'] ?? 0),
                'joined_count' => (int)($training['joined_count'] ?? 0),
                'status' => (string)(
                    $training['application_status']
                    ?? $training['status']
                    ?? ''
                ),
                'recommendation_kind' => (string)($training['recommendation_kind'] ?? ''),
            ];
        }
    }

    return $details;
}
