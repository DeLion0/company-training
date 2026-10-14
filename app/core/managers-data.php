<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/employees-data.php';

function manager_company_id(int $actorId): int
{
    $stmt = database()->prepare(
        "SELECT u.company_id
         FROM users u
         JOIN roles r
           ON r.id = u.role_id
         WHERE u.id = ?
           AND u.status = 'active'
           AND r.role_key = 'hr'
         LIMIT 1"
    );

    $stmt->execute([$actorId]);

    $companyId =
        (int) $stmt->fetchColumn();

    if ($companyId < 1) {
        throw new RuntimeException(
            'HR authorization required.'
        );
    }

    return $companyId;
}

function manager_departments(
    int $companyId
): array {
    $stmt = database()->prepare(
        "SELECT
            d.id,
            d.name,
            d.department_code,
            d.status,
            (
                SELECT COUNT(*)
                FROM employees e
                WHERE e.company_id = d.company_id
                  AND e.department_id = d.id
                  AND e.employment_status = 'active'
            ) AS employee_count,
            (
                SELECT COUNT(*)
                FROM users u
                JOIN roles r
                  ON r.id = u.role_id
                 AND r.role_key = 'department_head'
                WHERE u.company_id = d.company_id
                  AND u.department_id = d.id
                  AND u.status = 'active'
            ) AS active_head_count,
            (
                SELECT GROUP_CONCAT(
                    u.full_name
                    ORDER BY u.full_name
                    SEPARATOR ', '
                )
                FROM users u
                JOIN roles r
                  ON r.id = u.role_id
                 AND r.role_key = 'department_head'
                WHERE u.company_id = d.company_id
                  AND u.department_id = d.id
                  AND u.status = 'active'
            ) AS current_head
         FROM departments d
         WHERE d.company_id = ?
         ORDER BY
            CASE WHEN d.status = 'active' THEN 0 ELSE 1 END,
            d.name"
    );

    $stmt->execute([$companyId]);

    return $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
}

function manager_list(
    int $companyId
): array {
    $stmt = database()->prepare(
        "SELECT
            u.id,
            u.full_name,
            u.email,
            u.status,
            u.department_id,
            u.last_login_at,
            u.created_at,
            d.name AS department_name,
            d.department_code,
            (
                SELECT COUNT(*)
                FROM employees e
                WHERE e.company_id = u.company_id
                  AND e.manager_user_id = u.id
                  AND e.employment_status = 'active'
            ) AS assigned_employee_count
         FROM users u
         JOIN roles r
           ON r.id = u.role_id
          AND r.role_key = 'department_head'
         LEFT JOIN departments d
           ON d.id = u.department_id
          AND d.company_id = u.company_id
         WHERE u.company_id = ?
         ORDER BY
            CASE WHEN u.status = 'active' THEN 0 ELSE 1 END,
            d.name,
            u.full_name"
    );

    $stmt->execute([$companyId]);

    return $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
}

function manager_name_part(
    mixed $value,
    string $label
): string {
    $name = trim(
        (string) $value
    );

    if (
        !preg_match(
            '/^[\p{L} .\'-]{2,80}$/u',
            $name
        )
    ) {
        throw new InvalidArgumentException(
            'Enter a valid '
            . $label
            . ' (2–80 letters).'
        );
    }

    return $name;
}

function manager_create(
    int $actorId,
    array $input
): array {
    $companyId =
        manager_company_id(
            $actorId
        );

    $firstName =
        manager_name_part(
            $input['first_name']
                ?? '',
            'first name'
        );

    $lastName =
        manager_name_part(
            $input['last_name']
                ?? '',
            'last name'
        );

    $departmentId =
        (int) (
            $input['department_id']
            ?? 0
        );

    if ($departmentId < 1) {
        throw new InvalidArgumentException(
            'Choose a department.'
        );
    }

    $db = database();

    $departmentQuery =
        $db->prepare(
            "SELECT
                id,
                name
             FROM departments
             WHERE id = ?
               AND company_id = ?
               AND status = 'active'
             LIMIT 1"
        );

    $departmentQuery->execute([
        $departmentId,
        $companyId,
    ]);

    $department =
        $departmentQuery->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$department) {
        throw new InvalidArgumentException(
            'Choose an active department in your company.'
        );
    }

    // Phase 1 rule:
    // exactly one active Department Head per department.
    $existingHeadQuery =
        $db->prepare(
            "SELECT u.full_name
             FROM users u
             JOIN roles r
               ON r.id = u.role_id
              AND r.role_key = 'department_head'
             WHERE u.company_id = ?
               AND u.department_id = ?
               AND u.status = 'active'
             LIMIT 1"
        );

    $existingHeadQuery->execute([
        $companyId,
        $departmentId,
    ]);

    $existingHead =
        $existingHeadQuery->fetchColumn();

    if ($existingHead) {
        throw new InvalidArgumentException(
            (string) $department['name']
            . ' already has an active Department Head: '
            . (string) $existingHead
            . '.'
        );
    }

    $roleQuery =
        $db->query(
            "SELECT id
             FROM roles
             WHERE role_key = 'department_head'
             LIMIT 1"
        );

    $roleId =
        (int) $roleQuery->fetchColumn();

    if ($roleId < 1) {
        throw new RuntimeException(
            'Department Head role is missing. Run database/schema.sql.'
        );
    }

    // Use the exact same work-email domain and local/production
    // password rules already used by HR employee creation.
    $domain =
        employee_domain();

    $password =
        employee_make_password(
            $lastName
        );

    $passwordHash =
        password_hash(
            $password,
            PASSWORD_DEFAULT
        );

    if ($passwordHash === false) {
        throw new RuntimeException(
            'Unable to secure the temporary password.'
        );
    }

    $fullName =
        $firstName
        . ' '
        . $lastName;

    for (
        $attempt = 1;
        $attempt <= 100;
        $attempt++
    ) {
        $email =
            employee_email_candidate(
                $firstName,
                $lastName,
                $domain,
                $attempt
            );

        if (strlen($email) > 254) {
            throw new InvalidArgumentException(
                'Generated work email is too long.'
            );
        }

        try {
            $insert =
                $db->prepare(
                    "INSERT INTO users
                    (
                        company_id,
                        department_id,
                        role_id,
                        full_name,
                        email,
                        password_hash,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'active'
                    )"
                );

            $insert->execute([
                $companyId,
                $departmentId,
                $roleId,
                $fullName,
                $email,
                $passwordHash,
            ]);

            return [
                'id' =>
                    (int) $db->lastInsertId(),
                'name' =>
                    $fullName,
                'department' =>
                    (string) $department['name'],
                'email' =>
                    $email,
                'temporary_password' =>
                    $password,
                'status' =>
                    'active',
            ];

        } catch (PDOException $ex) {
            if (
                ($ex->errorInfo[1] ?? null)
                === 1062
            ) {
                // The email may already exist.
                // Retry using firstname.lastname.2, .3, etc.
                continue;
            }

            throw $ex;
        }
    }

    throw new RuntimeException(
        'Could not assign a unique work email.'
    );
}
