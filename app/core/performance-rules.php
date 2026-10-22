<?php

declare(strict_types=1);

const GROWFLOW_PERFORMANCE_THRESHOLD = 50.0;
const GROWFLOW_MANDATORY_STREAK_MONTHS = 3;

/**
 * Return the current consecutive monthly streak below the threshold.
 *
 * Consecutive means calendar-consecutive months.
 * Example:
 * 2026-08 < 50
 * 2026-09 < 50
 * 2026-10 < 50
 * => streak = 3
 *
 * A missing month breaks the streak.
 * A score >= 50 breaks the streak.
 */
function growflow_low_performance_streak(
    PDO $db,
    int $companyId,
    int $employeeId,
    float $threshold = GROWFLOW_PERFORMANCE_THRESHOLD
): array {
    $stmt = $db->prepare(
        "SELECT
            review_month,
            score
         FROM employee_monthly_performance
         WHERE company_id = ?
           AND employee_id = ?
         ORDER BY review_month DESC, id DESC
         LIMIT 24"
    );

    $stmt->execute([
        $companyId,
        $employeeId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        return [
            'count' => 0,
            'start_month' => null,
            'end_month' => null,
            'latest_score' => null,
            'mandatory' => false,
        ];
    }

    $count = 0;
    $expectedMonth = null;
    $startMonth = null;
    $endMonth = null;
    $latestScore = null;

    foreach ($rows as $index => $row) {
        $monthRaw = (string) $row['review_month'];
        $score = (float) $row['score'];

        try {
            $month = new DateTimeImmutable(
                substr($monthRaw, 0, 7) . '-01'
            );
        } catch (Throwable) {
            break;
        }

        if ($index === 0) {
            $latestScore = $score;
            $expectedMonth = $month;
            $endMonth = $month->format('Y-m');
        }

        if (
            !$expectedMonth
            || $month->format('Y-m') !== $expectedMonth->format('Y-m')
        ) {
            break;
        }

        if ($score >= $threshold) {
            break;
        }

        $count++;
        $startMonth = $month->format('Y-m');
        $expectedMonth = $expectedMonth->modify('-1 month');
    }

    return [
        'count' => $count,
        'start_month' => $startMonth,
        'end_month' => $endMonth,
        'latest_score' => $latestScore,
        'mandatory' =>
            $count >= GROWFLOW_MANDATORY_STREAK_MONTHS,
    ];
}