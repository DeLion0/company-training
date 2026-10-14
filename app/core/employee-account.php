<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

const EMPLOYEE_ACTIVATION_TTL = 1200;

function employee_activation_clear(): void
{
    unset($_SESSION['employee_activation']);
}

function employee_activation_begin(
    string $email,
    string $temporaryPassword
): bool {
    $db = database();

    $stmt = $db->prepare(
        "SELECT
            u.id,
            u.full_name,
            u.email,
            u.password_hash,
            u.status,
            e.id AS employee_id,
            e.employee_code
         FROM users u
         JOIN roles r
           ON r.id = u.role_id
          AND r.role_key = 'employee'
         JOIN employees e
           ON e.user_id = u.id
          AND e.company_id = u.company_id
         WHERE u.email = ?
           AND e.employment_status = 'active'
         LIMIT 1"
    );

    $stmt->execute([
        trim($email),
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $dummy =
        '$2y$10$abcdefghijklmnopqrstuu.eH6W38FyADGSpEU9pOzFppLfHPpXNC';

    $valid = password_verify(
        $temporaryPassword,
        (string)($row['password_hash'] ?? $dummy)
    );

    if (
        !$row
        || !$valid
        || (string)$row['status'] !== 'inactive'
    ) {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['employee_activation'] = [
        'user_id' => (int)$row['id'],
        'employee_id' => (int)$row['employee_id'],
        'email' => (string)$row['email'],
        'name' => (string)$row['full_name'],
        'employee_code' => (string)$row['employee_code'],
        'started_at' => time(),
    ];

    return true;
}

function employee_activation_state(): ?array
{
    $state =
        $_SESSION['employee_activation']
        ?? null;

    if (
        !is_array($state)
        || empty($state['user_id'])
        || empty($state['email'])
        || empty($state['started_at'])
    ) {
        employee_activation_clear();
        return null;
    }

    if (
        time() - (int)$state['started_at']
        > EMPLOYEE_ACTIVATION_TTL
    ) {
        employee_activation_clear();
        return null;
    }

    $stmt = database()->prepare(
        "SELECT
            u.id,
            u.full_name,
            u.email,
            u.password_hash,
            u.status,
            e.id AS employee_id,
            e.employee_code
         FROM users u
         JOIN roles r
           ON r.id = u.role_id
          AND r.role_key = 'employee'
         JOIN employees e
           ON e.user_id = u.id
          AND e.company_id = u.company_id
         WHERE u.id = ?
           AND u.status = 'inactive'
           AND e.employment_status = 'active'
         LIMIT 1"
    );

    $stmt->execute([
        (int)$state['user_id'],
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        employee_activation_clear();
        return null;
    }

    return [
        'user_id' => (int)$row['id'],
        'employee_id' => (int)$row['employee_id'],
        'email' => (string)$row['email'],
        'name' => (string)$row['full_name'],
        'employee_code' => (string)$row['employee_code'],
        'password_hash' => (string)$row['password_hash'],
        'started_at' => (int)$state['started_at'],
    ];
}

function employee_activation_password(
    string $password
): string {
    if (
        strlen($password) < 10
        || strlen($password) > 128
    ) {
        throw new InvalidArgumentException(
            'New password must be 10–128 characters.'
        );
    }

    if (
        !preg_match('/[a-z]/', $password)
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[0-9]/', $password)
        || !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        throw new InvalidArgumentException(
            'Use at least one uppercase letter, lowercase letter, number, and special character.'
        );
    }

    return $password;
}

function employee_activate_account(
    string $newPassword,
    string $confirmPassword
): void {
    $state = employee_activation_state();

    if (!$state) {
        throw new RuntimeException(
            'Activation session expired. Sign in again using your temporary password.'
        );
    }

    $newPassword =
        employee_activation_password(
            $newPassword
        );

    if (
        !hash_equals(
            $newPassword,
            $confirmPassword
        )
    ) {
        throw new InvalidArgumentException(
            'New password and confirmation do not match.'
        );
    }

    if (
        password_verify(
            $newPassword,
            $state['password_hash']
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a new password different from your temporary password.'
        );
    }

    $hash = password_hash(
        $newPassword,
        PASSWORD_DEFAULT
    );

    if ($hash === false) {
        throw new RuntimeException(
            'Unable to secure the new password.'
        );
    }

    $db = database();

    $db->beginTransaction();

    try {
        $update = $db->prepare(
            "UPDATE users
             SET
                password_hash = ?,
                status = 'active',
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
               AND status = 'inactive'"
        );

        $update->execute([
            $hash,
            (int)$state['user_id'],
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException(
                'The employee account could not be activated.'
            );
        }

        $db->commit();

    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $ex;
    }

    $email = $state['email'];

    employee_activation_clear();

    if (!login_user($email, $newPassword)) {
        throw new RuntimeException(
            'Account was activated, but automatic sign-in failed. Sign in again with your new password.'
        );
    }
}
