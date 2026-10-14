<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

const EMPLOYEE_ACTIVATION_HOURS = 24;

function employee_activation_base_url(): string
{
    $configured = trim(db_env('APP_URL', ''));

    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $https =
        (!empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off')
        || strtolower(
            (string) (
                $_SERVER['HTTP_X_FORWARDED_PROTO']
                ?? ''
            )
        ) === 'https';

    $host = (string) (
        $_SERVER['HTTP_HOST']
        ?? 'localhost'
    );

    $script = str_replace(
        '\\',
        '/',
        (string) (
            $_SERVER['SCRIPT_NAME']
            ?? '/index.php'
        )
    );

    $directory = rtrim(
        str_replace(
            '\\',
            '/',
            dirname($script)
        ),
        '/'
    );

    return ($https ? 'https' : 'http')
        . '://'
        . $host
        . ($directory === '' || $directory === '.'
            ? ''
            : $directory);
}

function employee_activation_issue(
    PDO $db,
    int $userId
): array {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $expiresAt = (
        new DateTimeImmutable(
            '+' . EMPLOYEE_ACTIVATION_HOURS . ' hours'
        )
    )->format('Y-m-d H:i:s');

    $stmt = $db->prepare(
        "INSERT INTO employee_activation_tokens
        (
            user_id,
            token_hash,
            expires_at,
            used_at
        )
        VALUES (?, ?, ?, NULL)
        ON DUPLICATE KEY UPDATE
            token_hash = VALUES(token_hash),
            expires_at = VALUES(expires_at),
            used_at = NULL,
            created_at = CURRENT_TIMESTAMP"
    );

    $stmt->execute([
        $userId,
        $tokenHash,
        $expiresAt,
    ]);

    $url =
        employee_activation_base_url()
        . '/activate-account.php?token='
        . rawurlencode($token);

    return [
        'token' => $token,
        'url' => $url,
        'expires_at' => $expiresAt,
    ];
}

function employee_activation_lookup(
    string $token
): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $tokenHash = hash('sha256', $token);

    $stmt = database()->prepare(
        "SELECT
            t.id AS token_id,
            t.user_id,
            t.expires_at,
            u.full_name,
            u.email,
            u.status,
            e.id AS employee_id,
            e.employee_code,
            e.contact_email
         FROM employee_activation_tokens t
         JOIN users u
           ON u.id = t.user_id
         JOIN roles r
           ON r.id = u.role_id
          AND r.role_key = 'employee'
         JOIN employees e
           ON e.user_id = u.id
          AND e.company_id = u.company_id
         WHERE t.token_hash = ?
           AND t.used_at IS NULL
           AND t.expires_at > NOW()
           AND u.status = 'inactive'
           AND e.employment_status = 'active'
         LIMIT 1"
    );

    $stmt->execute([$tokenHash]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function employee_activation_validate_password(
    string $password
): string {
    if (
        strlen($password) < 10
        || strlen($password) > 128
    ) {
        throw new InvalidArgumentException(
            'Password must be 10–128 characters.'
        );
    }

    if (
        !preg_match('/[a-z]/', $password)
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[0-9]/', $password)
        || !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        throw new InvalidArgumentException(
            'Use uppercase, lowercase, a number, and a special character.'
        );
    }

    return $password;
}

function employee_activation_complete(
    string $token,
    string $password,
    string $confirmPassword
): array {
    $record = employee_activation_lookup($token);

    if (!$record) {
        throw new InvalidArgumentException(
            'This activation link is invalid or has expired.'
        );
    }

    $password =
        employee_activation_validate_password(
            $password
        );

    if (
        !hash_equals(
            $password,
            $confirmPassword
        )
    ) {
        throw new InvalidArgumentException(
            'Password confirmation does not match.'
        );
    }

    $hash = password_hash(
        $password,
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
        $lock = $db->prepare(
            "SELECT
                t.id,
                t.user_id
             FROM employee_activation_tokens t
             JOIN users u
               ON u.id = t.user_id
             WHERE t.id = ?
               AND t.used_at IS NULL
               AND t.expires_at > NOW()
               AND u.status = 'inactive'
             FOR UPDATE"
        );

        $lock->execute([
            (int) $record['token_id'],
        ]);

        $locked = $lock->fetch(PDO::FETCH_ASSOC);

        if (!$locked) {
            throw new InvalidArgumentException(
                'This activation link is no longer available.'
            );
        }

        $updateUser = $db->prepare(
            "UPDATE users
             SET
                password_hash = ?,
                status = 'active',
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
               AND status = 'inactive'"
        );

        $updateUser->execute([
            $hash,
            (int) $record['user_id'],
        ]);

        if ($updateUser->rowCount() !== 1) {
            throw new RuntimeException(
                'Employee account could not be activated.'
            );
        }

        $useToken = $db->prepare(
            "UPDATE employee_activation_tokens
             SET used_at = NOW()
             WHERE id = ?
               AND used_at IS NULL"
        );

        $useToken->execute([
            (int) $record['token_id'],
        ]);

        $db->commit();

    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $ex;
    }

    return [
        'email' => (string) $record['email'],
        'name' => (string) $record['full_name'],
        'employee_code' =>
            (string) $record['employee_code'],
        'password' => $password,
    ];
}
