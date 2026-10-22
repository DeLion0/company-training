<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/performance-rules.php';

const PERFORMANCE_PRIORITY_THRESHOLD = 50.0;
const PERFORMANCE_IMPORT_MAX_ROWS = 1000;

function performance_company_id(int $actorId): int
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
    $companyId = $q->fetchColumn();

    if (!$companyId) {
        throw new RuntimeException('HR authorization required.');
    }

    return (int) $companyId;
}

function performance_review_month(string $value): string
{
    $value = trim($value);

    if (!preg_match('/^\d{4}-\d{2}$/', $value)) {
        throw new InvalidArgumentException(
            'Review month must use YYYY-MM.'
        );
    }

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value . '-01'
    );

    if (
        !$date
        || $date->format('Y-m') !== $value
    ) {
        throw new InvalidArgumentException(
            'Invalid review month.'
        );
    }

    return $date->format('Y-m-01');
}

function performance_score_value(mixed $value): float
{
    $value = is_string($value)
        ? trim($value)
        : $value;

    if (
        $value === ''
        || !is_numeric($value)
    ) {
        throw new InvalidArgumentException(
            'Performance score must be a number from 0 to 100.'
        );
    }

    $score = round((float) $value, 2);

    if ($score < 0 || $score > 100) {
        throw new InvalidArgumentException(
            'Performance score must be from 0 to 100.'
        );
    }

    return $score;
}

function performance_notes_value(mixed $value): ?string
{
    $notes = trim((string) ($value ?? ''));

    if ($notes === '') {
        return null;
    }

    $length = function_exists('mb_strlen')
        ? mb_strlen($notes)
        : strlen($notes);

    if ($length > 1000) {
        throw new InvalidArgumentException(
            'Notes must be 1000 characters or fewer.'
        );
    }

    return $notes;
}

function performance_employee(
    int $companyId,
    int $employeeId
): array {
    if ($employeeId < 1) {
        throw new InvalidArgumentException(
            'Choose a valid employee.'
        );
    }

    $q = database()->prepare(
        "SELECT
            e.id,
            e.employee_code,
            e.full_name,
            e.department_id,
            d.name AS department_name
         FROM employees e
         JOIN departments d
           ON d.id = e.department_id
          AND d.company_id = e.company_id
         WHERE e.id = ?
           AND e.company_id = ?
           AND e.employment_status = 'active'
         LIMIT 1"
    );

    $q->execute([
        $employeeId,
        $companyId,
    ]);

    $employee = $q->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        throw new InvalidArgumentException(
            'Employee was not found or is not active.'
        );
    }

    return $employee;
}

function performance_upsert(
    PDO $db,
    int $companyId,
    int $employeeId,
    string $reviewMonth,
    float $score,
    ?string $notes,
    string $source,
    int $actorId
): void {
    $q = $db->prepare(
        "INSERT INTO employee_monthly_performance (
            company_id,
            employee_id,
            review_month,
            score,
            notes,
            source,
            recorded_by
         ) VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            score = VALUES(score),
            notes = VALUES(notes),
            source = VALUES(source),
            recorded_by = VALUES(recorded_by),
            updated_at = CURRENT_TIMESTAMP"
    );

    $q->execute([
        $companyId,
        $employeeId,
        $reviewMonth,
        $score,
        $notes,
        $source,
        $actorId,
    ]);
}

/**
 * Keep automatic training priority recommendations in sync.
 *
 * - score < 50: create/mark Priority Recommended for targeted,
 *   still-active published training programs.
 * - score >= 50: remove only automatic low-performance priority
 *   recommendations that are still merely "recommended".
 * - Joined/declined participation is never deleted.
 */
function performance_sync_training_priority(
    PDO $db,
    int $companyId,
    int $employeeId,
    string $reviewMonth,
    float $score,
    int $actorId
): void {
    /*
     * Always re-evaluate from the latest official performance history.
     * This also means editing one of the latest 3 months immediately
     * recalculates the mandatory streak.
     */
    $latestQuery = $db->prepare(
        "SELECT
            review_month,
            score
         FROM employee_monthly_performance
         WHERE company_id = ?
           AND employee_id = ?
         ORDER BY
            review_month DESC,
            id DESC
         LIMIT 1"
    );

    $latestQuery->execute([
        $companyId,
        $employeeId,
    ]);

    $latest = $latestQuery->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$latest) {
        return;
    }

    $reviewMonth =
        (string) $latest['review_month'];

    $score =
        (float) $latest['score'];

    $streak = growflow_low_performance_streak(
        $db,
        $companyId,
        $employeeId,
        PERFORMANCE_PRIORITY_THRESHOLD
    );

    $mandatory =
        !empty($streak['mandatory']);

    $employee = performance_employee(
        $companyId,
        $employeeId
    );

    $departmentId =
        (int) $employee['department_id'];

    /*
     * Keep the current active-program rule compatible with the existing
     * Training History lifecycle. Use actual sessions when available.
     */
    $activeNow = (
        new DateTimeImmutable(
            'now',
            new DateTimeZone('Asia/Manila')
        )
    )->format('Y-m-d H:i:s');

    $q = $db->prepare(
        "SELECT p.id
         FROM training_programs p
         JOIN training_program_departments tpd
           ON tpd.program_id = p.id
         WHERE p.company_id = ?
           AND p.status = 'published'
           AND COALESCE(
                (
                    SELECT MAX(
                        TIMESTAMP(
                            sx.session_date,
                            sx.end_time
                        )
                    )
                    FROM training_sessions sx
                    WHERE sx.program_id = p.id
                ),
                TIMESTAMP(
                    p.range_end,
                    '23:59:59'
                )
           ) >= ?
           AND tpd.department_id = ?
         ORDER BY p.id"
    );

    $q->execute([
        $companyId,
        $activeNow,
        $departmentId,
    ]);

    $programIds = array_map(
        'intval',
        $q->fetchAll(PDO::FETCH_COLUMN)
    );

    if (!$programIds) {
        return;
    }

    $findApplication = $db->prepare(
        "SELECT
            ta.id,
            ta.source,
            ta.status,
            ta.review_notes,
            trm.recommendation_kind,
            trm.priority
         FROM training_applications ta
         LEFT JOIN training_recommendation_meta trm
           ON trm.application_id = ta.id
         WHERE ta.program_id = ?
           AND ta.employee_id = ?
         LIMIT 1"
    );

    $insertApplication = $db->prepare(
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

    $updateMandatoryApplication = $db->prepare(
        "UPDATE training_applications
         SET
            status = 'joined',
            source = CASE
                WHEN source = 'employee'
                THEN source
                ELSE 'hr_recommendation'
            END,
            submitted_by = CASE
                WHEN source = 'employee'
                THEN submitted_by
                ELSE ?
            END,
            review_notes = ?,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = ?"
    );

    $upsertPriorityMeta = $db->prepare(
        "INSERT INTO training_recommendation_meta (
            application_id,
            recommendation_kind,
            priority,
            performance_score,
            performance_month
         ) VALUES (
            ?,
            'low_performance',
            1,
            ?,
            ?
         )
         ON DUPLICATE KEY UPDATE
            recommendation_kind = 'low_performance',
            priority = 1,
            performance_score = VALUES(performance_score),
            performance_month = VALUES(performance_month),
            updated_at = CURRENT_TIMESTAMP"
    );

    $upsertMandatoryMeta = $db->prepare(
        "INSERT INTO training_recommendation_meta (
            application_id,
            recommendation_kind,
            priority,
            performance_score,
            performance_month
         ) VALUES (
            ?,
            'mandatory_low_performance',
            1,
            ?,
            ?
         )
         ON DUPLICATE KEY UPDATE
            recommendation_kind = 'mandatory_low_performance',
            priority = 1,
            performance_score = VALUES(performance_score),
            performance_month = VALUES(performance_month),
            updated_at = CURRENT_TIMESTAMP"
    );

    $deleteRecommendedApplication = $db->prepare(
        "DELETE ta
         FROM training_applications ta
         JOIN training_recommendation_meta trm
           ON trm.application_id = ta.id
         WHERE ta.id = ?
           AND ta.status = 'recommended'
           AND trm.recommendation_kind = 'low_performance'"
    );

    $deletePriorityMeta = $db->prepare(
        "DELETE FROM training_recommendation_meta
         WHERE application_id = ?
           AND recommendation_kind = 'low_performance'"
    );

    $monthKey =
        substr($reviewMonth, 0, 7);

    foreach ($programIds as $programId) {
        $findApplication->execute([
            $programId,
            $employeeId,
        ]);

        $application =
            $findApplication->fetch(
                PDO::FETCH_ASSOC
            );

        /*
         * ESCALATION:
         * 3+ consecutive calendar months below 50
         * => mandatory + automatic enrollment.
         *
         * This deliberately overrides a previous Declined state.
         */
        if ($mandatory) {
            $note = sprintf(
                'Mandatory training: %d consecutive monthly performance ratings below 50%% (%s to %s). Latest score %.2f%%.',
                (int) $streak['count'],
                (string) $streak['start_month'],
                (string) $streak['end_month'],
                $score
            );

            if (!$application) {
                $insertApplication->execute([
                    $programId,
                    $employeeId,
                    'joined',
                    $actorId,
                    $note,
                ]);

                $applicationId =
                    (int) $db->lastInsertId();
            } else {
                $applicationId =
                    (int) $application['id'];

                $updateMandatoryApplication->execute([
                    $actorId,
                    $note,
                    $applicationId,
                ]);
            }

            $upsertMandatoryMeta->execute([
                $applicationId,
                $score,
                $monthKey,
            ]);

            continue;
        }

        /*
         * Normal priority behavior: latest score below 50 but mandatory
         * streak has not reached 3 consecutive months.
         */
        if ($score < PERFORMANCE_PRIORITY_THRESHOLD) {
            if (!$application) {
                $note = sprintf(
                    'Priority recommendation: monthly performance is %.2f%% (%s), below the 50%% threshold.',
                    $score,
                    $monthKey
                );

                $insertApplication->execute([
                    $programId,
                    $employeeId,
                    'recommended',
                    $actorId,
                    $note,
                ]);

                $applicationId =
                    (int) $db->lastInsertId();

                $upsertPriorityMeta->execute([
                    $applicationId,
                    $score,
                    $monthKey,
                ]);

                continue;
            }

            /*
             * Never downgrade a previously-triggered mandatory assignment.
             * Also keep already-joined participation untouched.
             */
            if (
                (string) (
                    $application['recommendation_kind']
                    ?? ''
                ) === 'mandatory_low_performance'
                || (string) $application['status'] === 'joined'
            ) {
                continue;
            }

            /*
             * Normal declined optional recommendation remains declined
             * until/unless the 3-month mandatory rule is triggered.
             */
            if (
                (string) $application['status']
                === 'declined'
            ) {
                continue;
            }

            $upsertPriorityMeta->execute([
                (int) $application['id'],
                $score,
                $monthKey,
            ]);

            continue;
        }

        if (!$application) {
            continue;
        }

        /*
         * Mandatory assignments are sticky for the specific program.
         * A later improved score does not unassign a program that was
         * already made mandatory.
         */
        if (
            (string) (
                $application['recommendation_kind']
                ?? ''
            ) === 'mandatory_low_performance'
        ) {
            continue;
        }

        $isAutomaticLowPerformanceRecommendation =
            (string) $application['status'] === 'recommended'
            && (string) (
                $application['recommendation_kind']
                ?? ''
            ) === 'low_performance'
            && str_starts_with(
                (string) (
                    $application['review_notes']
                    ?? ''
                ),
                'Priority recommendation:'
            );

        if ($isAutomaticLowPerformanceRecommendation) {
            $deleteRecommendedApplication->execute([
                (int) $application['id'],
            ]);

            continue;
        }

        $deletePriorityMeta->execute([
            (int) $application['id'],
        ]);
    }
}

function performance_save_manual(
    int $actorId,
    array $input
): array {
    $companyId =
        performance_company_id(
            $actorId
        );

    $employeeId =
        (int) ($input['employee_id'] ?? 0);

    $employee =
        performance_employee(
            $companyId,
            $employeeId
        );

    $reviewMonth =
        performance_review_month(
            (string) (
                $input['review_month']
                ?? ''
            )
        );

    $score =
        performance_score_value(
            $input['score'] ?? null
        );

    $notes =
        performance_notes_value(
            $input['notes'] ?? null
        );

    $db = database();
    $db->beginTransaction();

    try {
        performance_upsert(
            $db,
            $companyId,
            $employeeId,
            $reviewMonth,
            $score,
            $notes,
            'manual',
            $actorId
        );

        performance_sync_training_priority(
            $db,
            $companyId,
            $employeeId,
            $reviewMonth,
            $score,
            $actorId
        );

        $db->commit();

        return [
            'employee' =>
                (string) $employee['full_name'],
            'month' =>
                substr($reviewMonth, 0, 7),
            'score' => $score,
        ];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $ex;
    }
}

/**
 * XLSX parser for the first worksheet.
 * Required headers:
 * Employee Code | Review Month | Score | Notes
 */
function performance_parse_xlsx(
    string $path
): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException(
            'ZIP extension is not available on this PHP server.'
        );
    }

    if (!function_exists('simplexml_load_string')) {
        throw new RuntimeException(
            'SimpleXML extension is not available on this PHP server.'
        );
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        throw new InvalidArgumentException(
            'Unable to open the Excel workbook.'
        );
    }

    try {
        $sharedStrings = [];

        $sharedXmlText =
            $zip->getFromName(
                'xl/sharedStrings.xml'
            );

        if ($sharedXmlText !== false) {
            $sharedXml =
                simplexml_load_string(
                    $sharedXmlText
                );

            if ($sharedXml === false) {
                throw new InvalidArgumentException(
                    'Excel shared strings could not be read.'
                );
            }

            $ns =
                $sharedXml->getNamespaces(
                    true
                );

            $mainNs =
                $ns['']
                ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

            $sharedXml->registerXPathNamespace(
                'x',
                $mainNs
            );

            $items =
                $sharedXml->xpath(
                    '//x:si'
                )
                ?: [];

            foreach ($items as $item) {
                $item->registerXPathNamespace(
                    'x',
                    $mainNs
                );

                $texts =
                    $item->xpath(
                        './/x:t'
                    )
                    ?: [];

                $value = '';

                foreach ($texts as $textNode) {
                    $value .=
                        (string) $textNode;
                }

                $sharedStrings[] =
                    $value;
            }
        }

        $sheetText =
            $zip->getFromName(
                'xl/worksheets/sheet1.xml'
            );

        if ($sheetText === false) {
            throw new InvalidArgumentException(
                'The first worksheet could not be found.'
            );
        }

        $sheet =
            simplexml_load_string(
                $sheetText
            );

        if ($sheet === false) {
            throw new InvalidArgumentException(
                'The first worksheet could not be read.'
            );
        }

        $ns =
            $sheet->getNamespaces(true);

        $mainNs =
            $ns['']
            ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

        $sheet->registerXPathNamespace(
            'x',
            $mainNs
        );

        $xmlRows =
            $sheet->xpath(
                '//x:sheetData/x:row'
            )
            ?: [];

        $rows = [];

        foreach ($xmlRows as $xmlRow) {
            $xmlRow->registerXPathNamespace(
                'x',
                $mainNs
            );

            $cells =
                $xmlRow->xpath(
                    './x:c'
                )
                ?: [];

            $row = [];

            foreach ($cells as $cell) {
                $ref =
                    (string) (
                        $cell['r']
                        ?? ''
                    );

                if (!preg_match(
                    '/^([A-Z]+)\d+$/',
                    $ref,
                    $match
                )) {
                    continue;
                }

                $columnLetters =
                    $match[1];

                $column = 0;

                for (
                    $i = 0;
                    $i < strlen($columnLetters);
                    $i++
                ) {
                    $column =
                        $column * 26
                        + (
                            ord(
                                $columnLetters[$i]
                            )
                            - 64
                        );
                }

                $column--;

                $type =
                    (string) (
                        $cell['t']
                        ?? ''
                    );

                $cell->registerXPathNamespace(
                    'x',
                    $mainNs
                );

                $value = '';

                if ($type === 'inlineStr') {
                    $texts =
                        $cell->xpath(
                            './/x:is//x:t'
                        )
                        ?: [];

                    foreach ($texts as $textNode) {
                        $value .=
                            (string) $textNode;
                    }
                } else {
                    $values =
                        $cell->xpath(
                            './x:v'
                        )
                        ?: [];

                    $raw =
                        isset($values[0])
                            ? (string) $values[0]
                            : '';

                    if (
                        $type === 's'
                        && $raw !== ''
                    ) {
                        $index =
                            (int) $raw;

                        $value =
                            $sharedStrings[
                                $index
                            ]
                            ?? '';
                    } elseif (
                        $type === 'str'
                        || $type === 'n'
                        || $type === ''
                    ) {
                        // Direct string / numeric / default cell.
                        // artifact_tool and Excel can write header text as t="str".
                        $value = $raw;
                    } else {
                        $value = $raw;
                    }
                }

                $row[$column] =
                    trim($value);
            }

            if ($row) {
                $maxColumn =
                    max(array_keys($row));

                $normalized = [];

                for (
                    $column = 0;
                    $column <= $maxColumn;
                    $column++
                ) {
                    $normalized[] =
                        $row[$column]
                        ?? '';
                }

                $rows[] =
                    $normalized;
            }
        }

        if (!$rows) {
            throw new InvalidArgumentException(
                'The Excel worksheet is empty.'
            );
        }

        return $rows;
    } finally {
        $zip->close();
    }
}

function performance_normalize_header(
    string $value
): string {
    // Lowercase FIRST, then remove separators/symbols.
    //
    // Important:
    // The old version removed capital letters before strtolower(),
    // so "Employee Code" became "mployeeode" and could never match
    // the expected "employeecode" header.
    $value = strtolower(
        trim($value)
    );

    return (string) preg_replace(
        '/[^a-z0-9]+/',
        '',
        $value
    );
}

function performance_validate_import(
    int $actorId,
    array $sheetRows
): array {
    $companyId =
        performance_company_id(
            $actorId
        );

    if (count($sheetRows) < 2) {
        throw new InvalidArgumentException(
            'The workbook must contain a header row and at least one data row.'
        );
    }

    if (
        count($sheetRows) - 1
        > PERFORMANCE_IMPORT_MAX_ROWS
    ) {
        throw new InvalidArgumentException(
            'The workbook may contain at most '
            . PERFORMANCE_IMPORT_MAX_ROWS
            . ' performance rows.'
        );
    }

    $header =
        array_map(
            fn ($value) =>
                performance_normalize_header(
                    (string) $value
                ),
            $sheetRows[0]
        );

    $aliases = [
        'employee_code' => [
            'employeecode',
            'employeeid',
            'code',
        ],
        'review_month' => [
            'reviewmonth',
            'month',
            'performancemonth',
        ],
        'score' => [
            'score',
            'performancescore',
            'rating',
            'monthlyrating',
        ],
        'notes' => [
            'notes',
            'remarks',
            'comment',
            'comments',
        ],
    ];

    $columnMap = [];

    foreach (
        $aliases
        as $key => $accepted
    ) {
        foreach (
            $header
            as $index => $normalized
        ) {
            if (
                in_array(
                    $normalized,
                    $accepted,
                    true
                )
            ) {
                $columnMap[$key] =
                    $index;

                break;
            }
        }
    }

    foreach (
        [
            'employee_code',
            'review_month',
            'score',
        ]
        as $required
    ) {
        if (!array_key_exists(
            $required,
            $columnMap
        )) {
            throw new InvalidArgumentException(
                'Missing required Excel column: '
                . str_replace(
                    '_',
                    ' ',
                    ucwords(
                        $required,
                        '_'
                    )
                )
                . '.'
            );
        }
    }

    $db = database();

    $employeeQuery =
        $db->prepare(
            "SELECT
                e.id,
                e.employee_code,
                e.full_name,
                e.department_id,
                d.name AS department_name
             FROM employees e
             JOIN departments d
               ON d.id = e.department_id
              AND d.company_id = e.company_id
             WHERE e.company_id = ?
               AND e.employment_status = 'active'
               AND UPPER(e.employee_code) = UPPER(?)
             LIMIT 1"
        );

    $preview = [];
    $seen = [];

    foreach (
        array_slice(
            $sheetRows,
            1
        )
        as $offset => $row
    ) {
        $rowNumber =
            $offset + 2;

        $employeeCode =
            trim(
                (string) (
                    $row[
                        $columnMap[
                            'employee_code'
                        ]
                    ]
                    ?? ''
                )
            );

        $monthRaw =
            trim(
                (string) (
                    $row[
                        $columnMap[
                            'review_month'
                        ]
                    ]
                    ?? ''
                )
            );

        $scoreRaw =
            $row[
                $columnMap['score']
            ]
            ?? '';

        $notesRaw =
            array_key_exists(
                'notes',
                $columnMap
            )
                ? (
                    $row[
                        $columnMap['notes']
                    ]
                    ?? ''
                )
                : '';

        if (
            $employeeCode === ''
            && $monthRaw === ''
            && trim((string) $scoreRaw) === ''
            && trim((string) $notesRaw) === ''
        ) {
            continue;
        }

        $errors = [];
        $employee = null;
        $reviewMonth = null;
        $score = null;
        $notes = null;

        if ($employeeCode === '') {
            $errors[] =
                'Employee Code is required.';
        } else {
            $employeeQuery->execute([
                $companyId,
                $employeeCode,
            ]);

            $employee =
                $employeeQuery->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!$employee) {
                $errors[] =
                    'Active employee code was not found in this company.';
            }
        }

        try {
            $reviewMonth =
                performance_review_month(
                    $monthRaw
                );
        } catch (InvalidArgumentException $ex) {
            $errors[] =
                $ex->getMessage();
        }

        try {
            $score =
                performance_score_value(
                    $scoreRaw
                );
        } catch (InvalidArgumentException $ex) {
            $errors[] =
                $ex->getMessage();
        }

        try {
            $notes =
                performance_notes_value(
                    $notesRaw
                );
        } catch (InvalidArgumentException $ex) {
            $errors[] =
                $ex->getMessage();
        }

        if (
            $employee
            && $reviewMonth
        ) {
            $duplicateKey =
                (int) $employee['id']
                . '|'
                . $reviewMonth;

            if (isset($seen[$duplicateKey])) {
                $errors[] =
                    'Duplicate employee + review month inside the workbook.';
            } else {
                $seen[$duplicateKey] =
                    true;
            }
        }

        $preview[] = [
            'row_number' =>
                $rowNumber,
            'employee_id' =>
                $employee
                    ? (int) $employee['id']
                    : 0,
            'employee_code' =>
                $employeeCode,
            'employee_name' =>
                $employee
                    ? (string) $employee['full_name']
                    : '',
            'department_name' =>
                $employee
                    ? (string) $employee['department_name']
                    : '',
            'review_month' =>
                $reviewMonth
                    ? substr(
                        $reviewMonth,
                        0,
                        7
                    )
                    : $monthRaw,
            'score' =>
                $score,
            'notes' =>
                $notes,
            'valid' =>
                !$errors,
            'errors' =>
                $errors,
        ];
    }

    if (!$preview) {
        throw new InvalidArgumentException(
            'No performance rows were found in the workbook.'
        );
    }

    return $preview;
}

function performance_import_commit(
    int $actorId,
    array $previewRows
): array {
    $companyId =
        performance_company_id(
            $actorId
        );

    if (!$previewRows) {
        throw new InvalidArgumentException(
            'Preview the Excel workbook first.'
        );
    }

    foreach ($previewRows as $row) {
        if (
            !is_array($row)
            || empty($row['valid'])
        ) {
            throw new InvalidArgumentException(
                'Fix all Excel validation errors before saving.'
            );
        }
    }

    $db = database();
    $db->beginTransaction();

    try {
        $saved = 0;
        $priority = 0;

        foreach ($previewRows as $row) {
            $employeeId =
                (int) (
                    $row['employee_id']
                    ?? 0
                );

            performance_employee(
                $companyId,
                $employeeId
            );

            $reviewMonth =
                performance_review_month(
                    (string) (
                        $row['review_month']
                        ?? ''
                    )
                );

            $score =
                performance_score_value(
                    $row['score'] ?? null
                );

            $notes =
                performance_notes_value(
                    $row['notes'] ?? null
                );

            performance_upsert(
                $db,
                $companyId,
                $employeeId,
                $reviewMonth,
                $score,
                $notes,
                'excel',
                $actorId
            );

            performance_sync_training_priority(
                $db,
                $companyId,
                $employeeId,
                $reviewMonth,
                $score,
                $actorId
            );

            if (
                $score
                < PERFORMANCE_PRIORITY_THRESHOLD
            ) {
                $priority++;
            }

            $saved++;
        }

        $db->commit();

        return [
            'saved' => $saved,
            'priority' => $priority,
        ];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $ex;
    }
}

function performance_overview(
    int $actorId
): array {
    $companyId =
        performance_company_id(
            $actorId
        );

    $db = database();

    $q = $db->prepare(
        "SELECT
            e.id,
            e.employee_code,
            e.full_name,
            e.job_title,
            e.department_id,
            d.name AS department_name
         FROM employees e
         JOIN departments d
           ON d.id = e.department_id
          AND d.company_id = e.company_id
         WHERE e.company_id = ?
           AND e.employment_status = 'active'
         ORDER BY d.name, e.full_name, e.id"
    );

    $q->execute([$companyId]);
    $employees =
        $q->fetchAll(PDO::FETCH_ASSOC);

    $q = $db->prepare(
        "SELECT
            COUNT(*) AS total_employees,
            SUM(
                CASE
                    WHEN latest.id IS NOT NULL
                    THEN 1
                    ELSE 0
                END
            ) AS evaluated,
            SUM(
                CASE
                    WHEN latest.score < ?
                    THEN 1
                    ELSE 0
                END
            ) AS below_threshold,
            ROUND(
                AVG(latest.score),
                1
            ) AS average_score
         FROM employees e
         LEFT JOIN employee_monthly_performance latest
           ON latest.id = (
                SELECT mp.id
                FROM employee_monthly_performance mp
                WHERE mp.company_id = e.company_id
                  AND mp.employee_id = e.id
                ORDER BY mp.review_month DESC, mp.id DESC
                LIMIT 1
           )
         WHERE e.company_id = ?
           AND e.employment_status = 'active'"
    );

    $q->execute([
        PERFORMANCE_PRIORITY_THRESHOLD,
        $companyId,
    ]);

    $stats =
        $q->fetch(PDO::FETCH_ASSOC)
        ?: [];

    $q = $db->prepare(
        "SELECT
            mp.id,
            mp.employee_id,
            e.employee_code,
            e.full_name,
            e.job_title,
            d.name AS department_name,
            DATE_FORMAT(mp.review_month, '%Y-%m') AS review_month,
            mp.score,
            mp.notes,
            mp.source,
            mp.updated_at
         FROM employee_monthly_performance mp
         JOIN employees e
           ON e.id = mp.employee_id
          AND e.company_id = mp.company_id
         JOIN departments d
           ON d.id = e.department_id
          AND d.company_id = e.company_id
         WHERE mp.company_id = ?
         ORDER BY
            mp.review_month DESC,
            d.name,
            e.full_name
         LIMIT 300"
    );

    $q->execute([$companyId]);
    $history =
        $q->fetchAll(PDO::FETCH_ASSOC);

    return [
        'employees' => $employees,
        'stats' => [
            'total' =>
                (int) (
                    $stats['total_employees']
                    ?? 0
                ),
            'evaluated' =>
                (int) (
                    $stats['evaluated']
                    ?? 0
                ),
            'below_threshold' =>
                (int) (
                    $stats['below_threshold']
                    ?? 0
                ),
            'average' =>
                $stats['average_score'] !== null
                    ? (float) $stats['average_score']
                    : null,
        ],
        'history' => $history,
        'threshold' =>
            PERFORMANCE_PRIORITY_THRESHOLD,
    ];
}
