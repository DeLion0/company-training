<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

const CERTIFICATE_TEMPLATE_KEYS = [
    'template_1',
    'template_2',
    'template_3',
    'template_4',
];

function cert_hr_company_id(int $actorId): int
{
    $stmt = database()->prepare(
        "SELECT u.company_id
         FROM users u
         JOIN roles r
           ON r.id = u.role_id
          AND r.role_key = 'hr'
         WHERE u.id = ?
           AND u.status = 'active'
         LIMIT 1"
    );
    $stmt->execute([$actorId]);

    $companyId = (int) $stmt->fetchColumn();

    if ($companyId < 1) {
        throw new RuntimeException('HR authorization required.');
    }

    return $companyId;
}

function cert_template_key(string $value): string
{
    if (!in_array($value, CERTIFICATE_TEMPLATE_KEYS, true)) {
        throw new InvalidArgumentException('Choose a valid certificate design.');
    }

    return $value;
}

function cert_program_for_company(
    int $companyId,
    int $programId
): array {
    $stmt = database()->prepare(
        "SELECT
            p.id,
            p.title,
            p.instructors,
            p.venue,
            p.range_start,
            p.range_end,
            p.meeting_count,
            p.status
         FROM training_programs p
         WHERE p.id = ?
           AND p.company_id = ?
         LIMIT 1"
    );
    $stmt->execute([$programId, $companyId]);

    $program = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$program) {
        throw new InvalidArgumentException('Training program was not found.');
    }

    return $program;
}

function cert_session_for_program(
    int $programId,
    int $sessionId
): array {
    $stmt = database()->prepare(
        "SELECT
            id,
            program_id,
            session_number,
            session_date,
            start_time,
            end_time
         FROM training_sessions
         WHERE id = ?
           AND program_id = ?
         LIMIT 1"
    );
    $stmt->execute([$sessionId, $programId]);

    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        throw new InvalidArgumentException(
            'Selected meeting does not belong to this training program.'
        );
    }

    return $session;
}


function cert_sessions_for_program(int $programId): array
{
    $stmt = database()->prepare(
        "SELECT
            id,
            program_id,
            session_number,
            session_date,
            start_time,
            end_time
         FROM training_sessions
         WHERE program_id = ?
         ORDER BY session_number, id"
    );
    $stmt->execute([$programId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cert_xlsx_column_index(string $letters): int
{
    $letters = strtoupper($letters);
    $index = 0;

    foreach (str_split($letters) as $letter) {
        if ($letter < 'A' || $letter > 'Z') {
            continue;
        }

        $index = ($index * 26)
            + (ord($letter) - ord('A') + 1);
    }

    return max(0, $index - 1);
}

function cert_parse_attendance_xlsx(string $path): array
{
    if (
        !class_exists('ZipArchive')
        || !class_exists('SimpleXMLElement')
    ) {
        throw new RuntimeException(
            'PHP zip and SimpleXML extensions are required for Excel import.'
        );
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        throw new InvalidArgumentException('Invalid Excel workbook.');
    }

    try {
        $shared = [];
        $sharedRaw = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedRaw !== false) {
            if (strlen($sharedRaw) > 3000000) {
                throw new InvalidArgumentException(
                    'Excel shared strings are too large.'
                );
            }

            $sharedXml = @simplexml_load_string(
                $sharedRaw,
                'SimpleXMLElement',
                LIBXML_NONET
            );

            if ($sharedXml === false) {
                throw new InvalidArgumentException(
                    'Invalid Excel shared strings.'
                );
            }

            foreach (
                $sharedXml->xpath('//*[local-name()="si"]')
                as $si
            ) {
                $text = '';

                foreach (
                    $si->xpath('.//*[local-name()="t"]')
                    as $part
                ) {
                    $text .= (string) $part;
                }

                $shared[] = $text;
            }
        }

        $worksheetRaw = $zip->getFromName(
            'xl/worksheets/sheet1.xml'
        );

        if (
            $worksheetRaw === false
            || strlen($worksheetRaw) > 7000000
        ) {
            throw new InvalidArgumentException(
                'Excel worksheet is missing or too large.'
            );
        }

        $worksheet = @simplexml_load_string(
            $worksheetRaw,
            'SimpleXMLElement',
            LIBXML_NONET
        );

        if ($worksheet === false) {
            throw new InvalidArgumentException(
                'Unable to read Excel worksheet.'
            );
        }

        $rows = [];

        foreach (
            $worksheet->xpath(
                '//*[local-name()="sheetData"]/*[local-name()="row"]'
            )
            as $row
        ) {
            $cells = [];

            foreach (
                $row->xpath('./*[local-name()="c"]')
                as $cell
            ) {
                $reference = (string) $cell['r'];

                preg_match(
                    '/^[A-Z]+/',
                    $reference,
                    $matches
                );

                $columnLetters = $matches[0] ?? '';

                if ($columnLetters === '') {
                    continue;
                }

                $columnIndex = cert_xlsx_column_index(
                    $columnLetters
                );

                if ($columnIndex > 40) {
                    throw new InvalidArgumentException(
                        'Attendance workbook contains too many columns.'
                    );
                }

                $kind = (string) $cell['t'];

                if ($kind === 'inlineStr') {
                    $nodes = $cell->xpath(
                        './*[local-name()="is"]//*[local-name()="t"]'
                    );
                } else {
                    $nodes = $cell->xpath(
                        './*[local-name()="v"]'
                    );
                }

                $value = '';

                foreach (($nodes ?: []) as $node) {
                    $value .= (string) $node;
                }

                if ($kind === 's') {
                    $value = $shared[(int) $value] ?? '';
                }

                $cells[$columnIndex] = trim($value);
            }

            if (
                array_filter(
                    $cells,
                    static fn ($value): bool =>
                        trim((string) $value) !== ''
                )
            ) {
                $rows[] = [
                    'row' => (int) $row['r'],
                    'cells' => $cells,
                ];
            }

            if (count($rows) > 501) {
                throw new InvalidArgumentException(
                    'Maximum 500 attendance rows per import.'
                );
            }
        }

        if (!$rows) {
            throw new InvalidArgumentException(
                'Attendance worksheet is empty.'
            );
        }

        $header = array_shift($rows);

        if (!$rows) {
            throw new InvalidArgumentException(
                'No employee attendance rows were found.'
            );
        }

        return [
            'headers' => (array) ($header['cells'] ?? []),
            'rows' => $rows,
        ];
    } finally {
        $zip->close();
    }
}

function cert_expected_attendance_headers(array $sessions): array
{
    $headers = [
        0 => 'Employee ID',
        1 => 'Employee Name',
    ];

    foreach ($sessions as $index => $session) {
        $headers[$index + 2] =
            'Meeting '
            . (int) $session['session_number']
            . ' | '
            . (string) $session['session_date'];
    }

    return $headers;
}

function cert_validate_attendance_import(
    int $actorId,
    int $programId,
    array $parsedWorkbook
): array {
    $companyId = cert_hr_company_id($actorId);

    cert_program_for_company($companyId, $programId);

    $sessions = cert_sessions_for_program($programId);

    if (!$sessions) {
        throw new InvalidArgumentException(
            'This training program has no meetings to import.'
        );
    }

    $headers = (array) ($parsedWorkbook['headers'] ?? []);
    $rows = (array) ($parsedWorkbook['rows'] ?? []);

    if (!$rows) {
        throw new InvalidArgumentException(
            'No attendance rows were supplied.'
        );
    }

    $expectedHeaders = cert_expected_attendance_headers(
        $sessions
    );

    foreach ($expectedHeaders as $column => $expected) {
        $actual = trim(
            (string) ($headers[$column] ?? '')
        );

        $simpleExpected = $column < 2
            ? $expected
            : 'Meeting '
                . (int) $sessions[$column - 2]['session_number'];

        if (
            strcasecmp($actual, $expected) !== 0
            && strcasecmp($actual, $simpleExpected) !== 0
        ) {
            throw new InvalidArgumentException(
                'Wrong attendance template. Expected column '
                . ($column + 1)
                . ' to be "'
                . $expected
                . '". Download a fresh template for the selected training program.'
            );
        }
    }

    if (
        count($headers) > count($expectedHeaders)
    ) {
        foreach (
            array_slice(
                $headers,
                count($expectedHeaders),
                null,
                true
            )
            as $extraHeader
        ) {
            if (trim((string) $extraHeader) !== '') {
                throw new InvalidArgumentException(
                    'The workbook contains extra attendance columns. Download a fresh template for this program.'
                );
            }
        }
    }

    $db = database();

    $employeeStmt = $db->prepare(
        "SELECT
            e.id,
            e.employee_code,
            e.full_name,
            e.employment_status,
            d.name AS department_name,
            ta.status AS training_status
         FROM employees e
         JOIN departments d
           ON d.id = e.department_id
          AND d.company_id = e.company_id
         LEFT JOIN training_applications ta
           ON ta.employee_id = e.id
          AND ta.program_id = ?
         WHERE e.company_id = ?
           AND e.employee_code = ?
         LIMIT 1"
    );

    $seen = [];
    $preview = [];

    foreach ($rows as $row) {
        $cells = (array) ($row['cells'] ?? []);

        $employeeCode = strtoupper(
            trim((string) ($cells[0] ?? ''))
        );

        $employeeNameExcel = trim(
            (string) ($cells[1] ?? '')
        );

        $valid = true;
        $errors = [];
        $employee = null;
        $attendance = [];
        $presentCount = 0;
        $absentCount = 0;

        if ($employeeCode === '') {
            $valid = false;
            $errors[] = 'Employee ID is required.';
        } elseif (isset($seen[$employeeCode])) {
            $valid = false;
            $errors[] =
                'Duplicate Employee ID in this workbook.';
        } else {
            $seen[$employeeCode] = true;
        }

        foreach ($sessions as $index => $session) {
            $status = strtolower(
                trim(
                    (string) ($cells[$index + 2] ?? '')
                )
            );

            if (
                !in_array(
                    $status,
                    ['present', 'absent'],
                    true
                )
            ) {
                $valid = false;

                $errors[] =
                    'Meeting '
                    . (int) $session['session_number']
                    . ' must be Present or Absent.';
            } elseif ($status === 'present') {
                $presentCount++;
            } else {
                $absentCount++;
            }

            $attendance[] = [
                'session_id' => (int) $session['id'],
                'session_number' =>
                    (int) $session['session_number'],
                'session_date' =>
                    (string) $session['session_date'],
                'status' => $status,
            ];
        }

        if ($employeeCode !== '') {
            $employeeStmt->execute([
                $programId,
                $companyId,
                $employeeCode,
            ]);

            $employee = $employeeStmt->fetch(
                PDO::FETCH_ASSOC
            ) ?: null;

            if (!$employee) {
                $valid = false;

                $errors[] =
                    'Employee ID does not exist in this company.';
            } elseif (
                (string) $employee['employment_status']
                !== 'active'
            ) {
                $valid = false;

                $errors[] = 'Employee is not active.';
            } elseif (
                (string) ($employee['training_status'] ?? '')
                !== 'joined'
            ) {
                $valid = false;

                $errors[] =
                    'Employee did not join this training program.';
            }
        }

        $preview[] = [
            'row' => (int) ($row['row'] ?? 0),
            'employee_id' => $employee
                ? (int) $employee['id']
                : null,
            'employee_code' => $employeeCode,
            'employee_name_excel' => $employeeNameExcel,
            'employee_name' => $employee
                ? (string) $employee['full_name']
                : $employeeNameExcel,
            'department_name' => $employee
                ? (string) $employee['department_name']
                : '',
            'attendance' => $attendance,
            'present_count' => $presentCount,
            'absent_count' => $absentCount,
            'eligible' =>
                $valid
                && $absentCount === 0
                && $presentCount === count($sessions),
            'valid' => $valid,
            'error' => implode(' ', $errors),
        ];
    }

    return [
        'sessions' => $sessions,
        'rows' => $preview,
    ];
}

function cert_preview_counts(array $rows): array
{
    $counts = [
        'valid' => 0,
        'invalid' => 0,
        'eligible' => 0,
        'blocked' => 0,
    ];

    foreach ($rows as $row) {
        if (!empty($row['valid'])) {
            $counts['valid']++;

            if (!empty($row['eligible'])) {
                $counts['eligible']++;
            } else {
                $counts['blocked']++;
            }
        } else {
            $counts['invalid']++;
        }
    }

    return $counts;
}

function cert_issue_eligible_for_program(
    PDO $db,
    int $companyId,
    int $programId,
    int $actorId,
    string $templateKey
): int {
    $templateKey = cert_template_key($templateKey);

    $sessionCountStmt = $db->prepare(
        "SELECT COUNT(*)
         FROM training_sessions
         WHERE program_id = ?"
    );
    $sessionCountStmt->execute([$programId]);

    $requiredSessions = (int) $sessionCountStmt->fetchColumn();

    if ($requiredSessions < 1) {
        return 0;
    }

    $eligibleStmt = $db->prepare(
        "SELECT
            e.id AS employee_id
         FROM training_applications ta
         JOIN employees e
           ON e.id = ta.employee_id
          AND e.company_id = ?
          AND e.employment_status = 'active'
         WHERE ta.program_id = ?
           AND ta.status = 'joined'
           AND (
                SELECT COUNT(DISTINCT att.session_id)
                FROM training_attendance att
                WHERE att.company_id = ?
                  AND att.program_id = ?
                  AND att.employee_id = e.id
                  AND att.attendance_status = 'present'
           ) = ?
           AND NOT EXISTS (
                SELECT 1
                FROM training_attendance missing
                JOIN training_sessions sess
                  ON sess.id = missing.session_id
                 AND sess.program_id = ?
                WHERE missing.company_id = ?
                  AND missing.program_id = ?
                  AND missing.employee_id = e.id
                  AND missing.attendance_status = 'absent'
           )
           AND NOT EXISTS (
                SELECT 1
                FROM training_certificates tc
                WHERE tc.program_id = ?
                  AND tc.employee_id = e.id
           )
         ORDER BY e.id"
    );

    $eligibleStmt->execute([
        $companyId,
        $programId,
        $companyId,
        $programId,
        $requiredSessions,
        $programId,
        $companyId,
        $programId,
        $programId,
    ]);

    $employeeIds = array_map(
        'intval',
        array_column(
            $eligibleStmt->fetchAll(PDO::FETCH_ASSOC),
            'employee_id'
        )
    );

    if (!$employeeIds) {
        return 0;
    }

    $insert = $db->prepare(
        "INSERT INTO training_certificates (
            company_id,
            program_id,
            employee_id,
            certificate_number,
            template_key,
            status,
            issued_by,
            issued_at
         ) VALUES (
            ?,
            ?,
            ?,
            NULL,
            ?,
            'issued',
            ?,
            NOW()
         )"
    );

    $numberUpdate = $db->prepare(
        "UPDATE training_certificates
         SET certificate_number = ?
         WHERE id = ?"
    );

    $issued = 0;
    $year = date('Y');

    foreach ($employeeIds as $employeeId) {
        $insert->execute([
            $companyId,
            $programId,
            $employeeId,
            $templateKey,
            $actorId,
        ]);

        $certificateId = (int) $db->lastInsertId();

        $number = 'GF-'
            . $year
            . '-'
            . str_pad(
                (string) $certificateId,
                6,
                '0',
                STR_PAD_LEFT
            );

        $numberUpdate->execute([
            $number,
            $certificateId,
        ]);

        $issued++;
    }

    return $issued;
}

function cert_import_commit(
    int $actorId,
    int $programId,
    string $templateKey,
    array $previewRows
): array {
    $companyId = cert_hr_company_id($actorId);
    $templateKey = cert_template_key($templateKey);

    cert_program_for_company($companyId, $programId);

    $sessions = cert_sessions_for_program($programId);

    if (!$sessions) {
        throw new InvalidArgumentException(
            'This training program has no meetings.'
        );
    }

    $sessionIds = array_map(
        'intval',
        array_column($sessions, 'id')
    );

    $validRows = array_values(
        array_filter(
            $previewRows,
            static fn (array $row): bool =>
                !empty($row['valid'])
        )
    );

    if (!$validRows) {
        throw new InvalidArgumentException(
            'There are no valid employee attendance rows to save.'
        );
    }

    $db = database();
    $db->beginTransaction();

    try {
        $verifyEmployee = $db->prepare(
            "SELECT e.id
             FROM employees e
             JOIN training_applications ta
               ON ta.employee_id = e.id
              AND ta.program_id = ?
              AND ta.status = 'joined'
             WHERE e.id = ?
               AND e.company_id = ?
               AND e.employment_status = 'active'
             LIMIT 1"
        );

        $upsert = $db->prepare(
            "INSERT INTO training_attendance (
                company_id,
                program_id,
                session_id,
                employee_id,
                attendance_status,
                source,
                imported_by
             ) VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                'excel',
                ?
             )
             ON DUPLICATE KEY UPDATE
                attendance_status = VALUES(attendance_status),
                source = 'excel',
                imported_by = VALUES(imported_by),
                imported_at = CURRENT_TIMESTAMP"
        );

        $savedAttendance = 0;
        $savedEmployees = 0;

        foreach ($validRows as $row) {
            $employeeId =
                (int) ($row['employee_id'] ?? 0);

            $verifyEmployee->execute([
                $programId,
                $employeeId,
                $companyId,
            ]);

            if (!$verifyEmployee->fetchColumn()) {
                throw new InvalidArgumentException(
                    'An employee changed while previewing. Preview the workbook again.'
                );
            }

            $rowAttendance =
                (array) ($row['attendance'] ?? []);

            if (
                count($rowAttendance)
                !== count($sessions)
            ) {
                throw new InvalidArgumentException(
                    'Attendance meeting count changed while previewing. Download a fresh template and try again.'
                );
            }

            foreach ($rowAttendance as $meeting) {
                $sessionId =
                    (int) ($meeting['session_id'] ?? 0);

                $status = strtolower(
                    trim(
                        (string) ($meeting['status'] ?? '')
                    )
                );

                if (
                    !in_array(
                        $sessionId,
                        $sessionIds,
                        true
                    )
                    || !in_array(
                        $status,
                        ['present', 'absent'],
                        true
                    )
                ) {
                    throw new InvalidArgumentException(
                        'Invalid attendance meeting data in preview.'
                    );
                }

                $upsert->execute([
                    $companyId,
                    $programId,
                    $sessionId,
                    $employeeId,
                    $status,
                    $actorId,
                ]);

                $savedAttendance++;
            }

            $savedEmployees++;
        }

        /*
         * IMPORTANT:
         * Eligibility is evaluated PER EMPLOYEE.
         *
         * One employee with an Absent meeting does not block another
         * employee who completed every required meeting as Present.
         */
        $issued = cert_issue_eligible_for_program(
            $db,
            $companyId,
            $programId,
            $actorId,
            $templateKey
        );

        $db->commit();

        return [
            'saved' => $savedAttendance,
            'employees' => $savedEmployees,
            'issued' => $issued,
        ];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $ex;
    }
}

function cert_issue_eligible_manual(
    int $actorId,
    int $programId,
    string $templateKey
): int {
    $companyId = cert_hr_company_id($actorId);
    cert_program_for_company($companyId, $programId);

    $db = database();
    $db->beginTransaction();

    try {
        $issued = cert_issue_eligible_for_program(
            $db,
            $companyId,
            $programId,
            $actorId,
            $templateKey
        );

        $db->commit();

        return $issued;
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $ex;
    }
}

function cert_dashboard_data(int $actorId): array
{
    $companyId = cert_hr_company_id($actorId);
    $db = database();

    $programStmt = $db->prepare(
        "SELECT
            p.id,
            p.title,
            p.range_start,
            p.range_end,
            p.instructors,
            p.venue,
            p.meeting_count,
            p.status,
            (
                SELECT COUNT(*)
                FROM training_applications ta
                WHERE ta.program_id = p.id
                  AND ta.status = 'joined'
            ) AS joined_count,
            (
                SELECT COUNT(*)
                FROM training_certificates tc
                WHERE tc.program_id = p.id
                  AND tc.status = 'issued'
            ) AS certificate_count,
            (
                SELECT COUNT(*)
                FROM training_attendance att
                WHERE att.program_id = p.id
            ) AS attendance_rows
         FROM training_programs p
         WHERE p.company_id = ?
           AND p.status = 'published'
         ORDER BY p.range_end DESC, p.id DESC"
    );
    $programStmt->execute([$companyId]);
    $programs = $programStmt->fetchAll(PDO::FETCH_ASSOC);

    $sessionStmt = $db->prepare(
        "SELECT
            s.id,
            s.program_id,
            s.session_number,
            s.session_date,
            s.start_time,
            s.end_time
         FROM training_sessions s
         JOIN training_programs p
           ON p.id = s.program_id
          AND p.company_id = ?
         ORDER BY s.program_id, s.session_number"
    );
    $sessionStmt->execute([$companyId]);
    $sessions = $sessionStmt->fetchAll(PDO::FETCH_ASSOC);

    $sessionsByProgram = [];

    foreach ($sessions as $session) {
        $sessionsByProgram[
            (int) $session['program_id']
        ][] = $session;
    }

    foreach ($programs as &$program) {
        $program['sessions'] =
            $sessionsByProgram[
                (int) $program['id']
            ] ?? [];
    }
    unset($program);

    $certificateStmt = $db->prepare(
        "SELECT
            tc.id,
            tc.certificate_number,
            tc.template_key,
            tc.status,
            tc.issued_at,
            e.employee_code,
            e.full_name,
            d.name AS department_name,
            p.title AS training_title,
            p.range_start,
            p.range_end
         FROM training_certificates tc
         JOIN employees e
           ON e.id = tc.employee_id
          AND e.company_id = tc.company_id
         JOIN departments d
           ON d.id = e.department_id
          AND d.company_id = e.company_id
         JOIN training_programs p
           ON p.id = tc.program_id
          AND p.company_id = tc.company_id
         WHERE tc.company_id = ?
         ORDER BY tc.issued_at DESC, tc.id DESC
         LIMIT 200"
    );
    $certificateStmt->execute([$companyId]);
    $certificates = $certificateStmt->fetchAll(PDO::FETCH_ASSOC);

    $metricsStmt = $db->prepare(
        "SELECT
            (
                SELECT COUNT(*)
                FROM training_certificates
                WHERE company_id = ?
                  AND status = 'issued'
            ) AS issued_count,
            (
                SELECT COUNT(*)
                FROM training_attendance
                WHERE company_id = ?
            ) AS attendance_count,
            (
                SELECT COUNT(DISTINCT program_id)
                FROM training_attendance
                WHERE company_id = ?
            ) AS programs_with_attendance"
    );
    $metricsStmt->execute([
        $companyId,
        $companyId,
        $companyId,
    ]);
    $metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'company_id' => $companyId,
        'programs' => $programs,
        'certificates' => $certificates,
        'metrics' => [
            'issued' => (int) ($metrics['issued_count'] ?? 0),
            'attendance' => (int) ($metrics['attendance_count'] ?? 0),
            'programs' => count($programs),
            'programs_with_attendance' =>
                (int) ($metrics['programs_with_attendance'] ?? 0),
        ],
    ];
}

function cert_employee_certificates(
    int $userId
): array {
    $db = database();

    $employeeStmt = $db->prepare(
        "SELECT
            e.id,
            e.company_id,
            e.employee_code,
            e.full_name,
            e.department_id,
            d.name AS department_name
         FROM users u
         JOIN roles r
           ON r.id = u.role_id
          AND r.role_key = 'employee'
         JOIN employees e
           ON e.user_id = u.id
          AND e.company_id = u.company_id
          AND e.employment_status = 'active'
         JOIN departments d
           ON d.id = e.department_id
          AND d.company_id = e.company_id
         WHERE u.id = ?
           AND u.status = 'active'
         LIMIT 1"
    );
    $employeeStmt->execute([$userId]);

    $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        throw new RuntimeException(
            'No active employee profile is linked to this account.'
        );
    }

    $certificateStmt = $db->prepare(
        "SELECT
            tc.id,
            tc.certificate_number,
            tc.template_key,
            tc.issued_at,
            p.title AS training_title,
            p.range_start,
            p.range_end,
            p.venue,
            p.instructors
         FROM training_certificates tc
         JOIN training_programs p
           ON p.id = tc.program_id
          AND p.company_id = tc.company_id
         WHERE tc.company_id = ?
           AND tc.employee_id = ?
           AND tc.status = 'issued'
         ORDER BY tc.issued_at DESC, tc.id DESC"
    );
    $certificateStmt->execute([
        (int) $employee['company_id'],
        (int) $employee['id'],
    ]);

    return [
        'employee' => $employee,
        'certificates' => $certificateStmt->fetchAll(
            PDO::FETCH_ASSOC
        ),
    ];
}

function cert_certificate_record_for_view(
    int $certificateId,
    int $viewerUserId,
    string $viewerRole
): array {
    $db = database();

    if ($viewerRole === 'hr') {
        $companyId = cert_hr_company_id($viewerUserId);

        $stmt = $db->prepare(
            "SELECT
                tc.*,
                e.employee_code,
                e.full_name,
                d.name AS department_name,
                p.title AS training_title,
                p.range_start,
                p.range_end,
                p.venue,
                p.instructors
             FROM training_certificates tc
             JOIN employees e
               ON e.id = tc.employee_id
              AND e.company_id = tc.company_id
             JOIN departments d
               ON d.id = e.department_id
              AND d.company_id = e.company_id
             JOIN training_programs p
               ON p.id = tc.program_id
              AND p.company_id = tc.company_id
             WHERE tc.id = ?
               AND tc.company_id = ?
               AND tc.status = 'issued'
             LIMIT 1"
        );
        $stmt->execute([
            $certificateId,
            $companyId,
        ]);
    } elseif ($viewerRole === 'employee') {
        $stmt = $db->prepare(
            "SELECT
                tc.*,
                e.employee_code,
                e.full_name,
                d.name AS department_name,
                p.title AS training_title,
                p.range_start,
                p.range_end,
                p.venue,
                p.instructors
             FROM users u
             JOIN employees e
               ON e.user_id = u.id
              AND e.company_id = u.company_id
             JOIN training_certificates tc
               ON tc.employee_id = e.id
              AND tc.company_id = e.company_id
              AND tc.status = 'issued'
             JOIN departments d
               ON d.id = e.department_id
              AND d.company_id = e.company_id
             JOIN training_programs p
               ON p.id = tc.program_id
              AND p.company_id = tc.company_id
             WHERE u.id = ?
               AND u.status = 'active'
               AND tc.id = ?
             LIMIT 1"
        );
        $stmt->execute([
            $viewerUserId,
            $certificateId,
        ]);
    } else {
        throw new RuntimeException('Certificate access denied.');
    }

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new RuntimeException(
            'Certificate was not found or access is denied.'
        );
    }

    return $record;
}
