<?php

declare(strict_types=1);

/**
 * Centralized training lifecycle rules.
 *
 * Training date/time values are interpreted as Asia/Manila local time.
 * A published training becomes historical only after its actual final
 * training_sessions.session_date + end_time has passed.
 */

function training_lifecycle_timezone(): DateTimeZone
{
    static $timezone = null;

    if (!$timezone instanceof DateTimeZone) {
        $timezone = new DateTimeZone('Asia/Manila');
    }

    return $timezone;
}

function training_lifecycle_now(): DateTimeImmutable
{
    return new DateTimeImmutable(
        'now',
        training_lifecycle_timezone()
    );
}

function training_lifecycle_now_string(): string
{
    return training_lifecycle_now()->format(
        'Y-m-d H:i:s'
    );
}

function training_lifecycle_session_end(
    string $date,
    string $endTime
): ?DateTimeImmutable {
    $date = trim($date);
    $endTime = trim($endTime);

    if ($date === '' || $endTime === '') {
        return null;
    }

    try {
        return new DateTimeImmutable(
            $date . ' ' . $endTime,
            training_lifecycle_timezone()
        );
    } catch (Throwable) {
        return null;
    }
}

function training_lifecycle_end_from_sessions(
    array $sessions,
    ?string $fallbackRangeEnd = null
): ?DateTimeImmutable {
    $latest = null;

    foreach ($sessions as $session) {
        $candidate = training_lifecycle_session_end(
            (string) ($session['session_date'] ?? ''),
            (string) ($session['end_time'] ?? '')
        );

        if (
            $candidate !== null
            && ($latest === null || $candidate > $latest)
        ) {
            $latest = $candidate;
        }
    }

    if ($latest !== null) {
        return $latest;
    }

    if ($fallbackRangeEnd !== null && trim($fallbackRangeEnd) !== '') {
        try {
            return new DateTimeImmutable(
                trim($fallbackRangeEnd) . ' 23:59:59',
                training_lifecycle_timezone()
            );
        } catch (Throwable) {
            return null;
        }
    }

    return null;
}

function training_lifecycle_has_ended_from_sessions(
    array $sessions,
    ?string $fallbackRangeEnd = null,
    ?DateTimeImmutable $now = null
): bool {
    $endAt = training_lifecycle_end_from_sessions(
        $sessions,
        $fallbackRangeEnd
    );

    if ($endAt === null) {
        return false;
    }

    $now ??= training_lifecycle_now();

    return $endAt < $now;
}

function training_lifecycle_program_has_ended(
    PDO $db,
    int $programId,
    ?DateTimeImmutable $now = null
): bool {
    if ($programId < 1) {
        return true;
    }

    $stmt = $db->prepare(
        "SELECT
            p.range_end,
            MAX(
                TIMESTAMP(
                    s.session_date,
                    s.end_time
                )
            ) AS last_session_at
         FROM training_programs p
         LEFT JOIN training_sessions s
           ON s.program_id = p.id
         WHERE p.id = ?
         GROUP BY p.id, p.range_end"
    );

    $stmt->execute([$programId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return true;
    }

    $now ??= training_lifecycle_now();

    $rawLast = trim(
        (string) ($row['last_session_at'] ?? '')
    );

    if ($rawLast !== '') {
        try {
            $last = new DateTimeImmutable(
                $rawLast,
                training_lifecycle_timezone()
            );

            return $last < $now;
        } catch (Throwable) {
            // Fall through to range_end.
        }
    }

    $rangeEnd = trim(
        (string) ($row['range_end'] ?? '')
    );

    if ($rangeEnd === '') {
        return false;
    }

    try {
        $fallback = new DateTimeImmutable(
            $rangeEnd . ' 23:59:59',
            training_lifecycle_timezone()
        );

        return $fallback < $now;
    } catch (Throwable) {
        return false;
    }
}

function training_lifecycle_parse_last_session(
    ?string $lastSessionAt,
    ?string $fallbackRangeEnd = null
): ?DateTimeImmutable {
    $lastSessionAt = trim(
        (string) $lastSessionAt
    );

    if ($lastSessionAt !== '') {
        try {
            return new DateTimeImmutable(
                $lastSessionAt,
                training_lifecycle_timezone()
            );
        } catch (Throwable) {
            // Fall through.
        }
    }

    if (
        $fallbackRangeEnd !== null
        && trim($fallbackRangeEnd) !== ''
    ) {
        try {
            return new DateTimeImmutable(
                trim($fallbackRangeEnd)
                . ' 23:59:59',
                training_lifecycle_timezone()
            );
        } catch (Throwable) {
            return null;
        }
    }

    return null;
}