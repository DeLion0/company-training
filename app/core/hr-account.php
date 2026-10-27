<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function hr_account_data(int $userId): array
{
    $stmt = database()->prepare(
        "SELECT
            u.id,
            u.full_name,
            u.email,
            u.status,
            u.last_login_at,
            u.created_at,
            c.name AS company_name,
            r.role_label
         FROM users u
         JOIN companies c
           ON c.id = u.company_id
         JOIN roles r
           ON r.id = u.role_id
         WHERE u.id = ?
           AND r.role_key = 'hr'
         LIMIT 1"
    );

    $stmt->execute([$userId]);

    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        throw new RuntimeException(
            'HR account was not found.'
        );
    }

    return $account;
}

function hr_change_password(
    int $userId,
    string $currentPassword,
    string $newPassword,
    string $confirmPassword
): void {
    if (
        $currentPassword === ''
        || $newPassword === ''
        || $confirmPassword === ''
    ) {
        throw new InvalidArgumentException(
            'Complete all password fields.'
        );
    }

    if ($newPassword !== $confirmPassword) {
        throw new InvalidArgumentException(
            'New password and confirmation do not match.'
        );
    }

    if (
        strlen($newPassword) < 12
        || strlen($newPassword) > 128
    ) {
        throw new InvalidArgumentException(
            'New password must be 12–128 characters.'
        );
    }

    if (
        !preg_match('/[a-z]/', $newPassword)
        || !preg_match('/[A-Z]/', $newPassword)
        || !preg_match('/[0-9]/', $newPassword)
        || !preg_match('/[^A-Za-z0-9]/', $newPassword)
    ) {
        throw new InvalidArgumentException(
            'Use uppercase, lowercase, a number, and a symbol.'
        );
    }

    $stmt = database()->prepare(
        "SELECT u.password_hash
         FROM users u
         JOIN roles r
           ON r.id = u.role_id
         WHERE u.id = ?
           AND u.status = 'active'
           AND r.role_key = 'hr'
         LIMIT 1"
    );

    $stmt->execute([$userId]);

    $hash = (string) $stmt->fetchColumn();

    if (
        $hash === ''
        || !password_verify(
            $currentPassword,
            $hash
        )
    ) {
        throw new InvalidArgumentException(
            'Current password is incorrect.'
        );
    }

    if (password_verify($newPassword, $hash)) {
        throw new InvalidArgumentException(
            'Choose a password different from your current password.'
        );
    }

    $newHash = password_hash(
        $newPassword,
        PASSWORD_DEFAULT
    );

    if (!is_string($newHash)) {
        throw new RuntimeException(
            'Unable to secure the new password.'
        );
    }

    $update = database()->prepare(
        "UPDATE users
         SET password_hash = ?
         WHERE id = ?
           AND status = 'active'"
    );

    $update->execute([
        $newHash,
        $userId,
    ]);
}