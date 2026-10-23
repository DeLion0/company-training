<?php

require_once __DIR__ . '/../core/analytics-data.php';

$viewer = current_user();

try {
    $analytics = analytics_data($viewer);
} catch (Throwable $ex) {
    error_log('Analytics query error: ' . $ex->getMessage());
    $analytics = null;
}

$chartPayload = null;

if (is_array($analytics)) {
    $chartPayload = [
        'threshold' => (float)$analytics['threshold'],
        'months' => array_map(
            static fn(array $month): array => [
                'key' => (string)$month['key'],
                'label' => (string)$month['label'],
                'long_label' => (string)$month['long_label'],
            ],
            $analytics['months']
        ),
        'departments' => array_map(
            static fn(array $department): array => [
                'id' => (int)$department['id'],
                'code' => (string)$department['code'],
                'name' => (string)$department['name'],
                'color' => (string)$department['color'],
                'values' => array_values($department['monthly']),
            ],
            $analytics['departments']
        ),
        'company_average' => array_map(
            static fn(array $row): ?float => $row['average'] === null
                ? null
                : (float)$row['average'],
            array_values($analytics['company_monthly'])
        ),
        'coverage' => array_map(
            static fn(array $row): ?float => $row['coverage'] === null
                ? null
                : (float)$row['coverage'],
            array_values($analytics['company_monthly'])
        ),
        'below_threshold' => array_map(
            static fn(array $row): int => (int)$row['below_threshold'],
            array_values($analytics['company_monthly'])
        ),
        'total_employees' => (int)$analytics['total_employees'],
    ];
}

$latestMonthLabel = is_array($analytics) && is_array($analytics['latest_month'])
    ? (string)$analytics['latest_month']['long_label']
    : 'No reviews yet';
?>
<main class="content analytics-page">
    <section class="analytics-hero">
        <div>
            <div class="analytics-kicker">
                <i data-lucide="chart-no-axes-combined"></i>
                HR ANALYTICS / KPI MONITORING
            </div>

            <h1>Workforce Analytics</h1>

            <p>
                Monitor company-wide performance, evaluation coverage, and
                department trends using official monthly employee reviews.
            </p>
        </div>

        <div class="analytics-scope">
            <i data-lucide="shield-check"></i>
            <div>
                <span>ACCESS</span>
                <strong>HR only</strong>
                <small>Company-wide analytics</small>
            </div>
        </div>
    </section>

    <?php if ($analytics === null): ?>
        <section class="analytics-empty-state">
            <i data-lucide="database-zap"></i>
            <div>
                <strong>Analytics data is unavailable</strong>
                <p>
                    Check the PHP error log and confirm the employee monthly
                    performance data is available.
                </p>
            </div>
        </section>
    <?php else: ?>

        <section class="analytics-kpis" aria-label="Key performance indicators">
            <article class="analytics-kpi">
                <div class="analytics-kpi-icon is-green">
                    <i data-lucide="gauge"></i>
                </div>
                <span>Latest Company Average</span>
                <strong>
                    <?= $analytics['latest']['average'] === null
                        ? '—'
                        : number_format((float)$analytics['latest']['average'], 1) . '%' ?>
                </strong>
                <small><?= e($latestMonthLabel) ?></small>
            </article>

            <article class="analytics-kpi">
                <div class="analytics-kpi-icon is-blue">
                    <i data-lucide="clipboard-check"></i>
                </div>
                <span>Employees Evaluated</span>
                <strong>
                    <?= number_format((int)$analytics['latest']['reviewed']) ?>
                    <em>/<?= number_format((int)$analytics['total_employees']) ?></em>
                </strong>
                <small>
                    <?= $analytics['latest']['coverage'] === null
                        ? 'No evaluation coverage yet'
                        : number_format((float)$analytics['latest']['coverage'], 1) . '% coverage' ?>
                </small>
            </article>

            <article class="analytics-kpi">
                <div class="analytics-kpi-icon is-purple">
                    <i data-lucide="building-2"></i>
                </div>
                <span>Departments Tracked</span>
                <strong>
                    <?= number_format((int)$analytics['departments_tracked']) ?>
                    <em>/<?= number_format(count($analytics['departments'])) ?></em>
                </strong>
                <small>Departments with performance data</small>
            </article>

            <article class="analytics-kpi">
                <div class="analytics-kpi-icon is-amber">
                    <i data-lucide="triangle-alert"></i>
                </div>
                <span>Below 50% Threshold</span>
                <strong>
                    <?= number_format((int)$analytics['latest']['below_threshold']) ?>
                </strong>
                <small>Employees in latest review month</small>
            </article>
        </section>

        <section class="analytics-panel analytics-primary-panel">
            <div class="analytics-panel-head">
                <div>
                    <span class="analytics-panel-eyebrow">DEPARTMENT KPI TREND</span>
                    <h2>Department Performance Trends</h2>
                    <p>
                        Monthly average performance. Every department is shown
                        as a separate line.
                    </p>
                </div>

                <div class="analytics-period">
                    <i data-lucide="calendar-range"></i>
                    Last <?= count($analytics['months']) ?> months
                </div>
            </div>

            <?php if (!$analytics['departments']): ?>
                <div class="analytics-chart-empty">
                    <i data-lucide="building-2"></i>
                    <strong>No departments found</strong>
                    <span>Add department and employee data to begin analytics.</span>
                </div>
            <?php else: ?>
                <div class="analytics-legend" id="analyticsDepartmentLegend">
                    <?php foreach ($analytics['departments'] as $department): ?>
                        <button
                            type="button"
                            class="analytics-legend-item"
                            data-series-toggle="<?= (int)$department['id'] ?>"
                            aria-pressed="true"
                            style="--series-color:<?= e((string)$department['color']) ?>"
                        >
                            <span></span>
                            <?= e((string)$department['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="analytics-chart-stage analytics-chart-stage-large">
                    <canvas
                        id="analyticsDepartmentChart"
                        class="analytics-canvas"
                        aria-label="Department performance line graph"
                    ></canvas>
                    <div class="analytics-chart-tooltip" data-tooltip hidden></div>
                </div>

                <p class="analytics-chart-note">
                    Color convention: People &amp; Culture / HR uses red,
                    Marketing uses orange, Accounting / Finance uses blue,
                    and every remaining department has its own distinct color.
                </p>
            <?php endif; ?>
        </section>

        <section class="analytics-secondary-grid">
            <article class="analytics-panel">
                <div class="analytics-panel-head compact">
                    <div>
                        <span class="analytics-panel-eyebrow">COMPANY KPI</span>
                        <h2>Overall Performance</h2>
                        <p>Company-wide monthly average score.</p>
                    </div>
                    <div class="analytics-panel-icon is-green">
                        <i data-lucide="chart-spline"></i>
                    </div>
                </div>

                <div class="analytics-chart-stage">
                    <canvas
                        id="analyticsCompanyChart"
                        class="analytics-canvas"
                        aria-label="Company average performance line graph"
                    ></canvas>
                    <div class="analytics-chart-tooltip" data-tooltip hidden></div>
                </div>
            </article>

            <article class="analytics-panel">
                <div class="analytics-panel-head compact">
                    <div>
                        <span class="analytics-panel-eyebrow">EVALUATION KPI</span>
                        <h2>Evaluation Coverage</h2>
                        <p>Share of active employees evaluated each month.</p>
                    </div>
                    <div class="analytics-panel-icon is-blue">
                        <i data-lucide="users-round-check"></i>
                    </div>
                </div>

                <div class="analytics-chart-stage">
                    <canvas
                        id="analyticsCoverageChart"
                        class="analytics-canvas"
                        aria-label="Monthly evaluation coverage line graph"
                    ></canvas>
                    <div class="analytics-chart-tooltip" data-tooltip hidden></div>
                </div>
            </article>

            <article class="analytics-panel">
                <div class="analytics-panel-head compact">
                    <div>
                        <span class="analytics-panel-eyebrow">RISK KPI</span>
                        <h2>Employees Below Threshold</h2>
                        <p>
                            Monthly employee count with performance below
                            <?= number_format((float)$analytics['threshold'], 0) ?>%.
                        </p>
                    </div>
                    <div class="analytics-panel-icon is-amber">
                        <i data-lucide="trending-down"></i>
                    </div>
                </div>

                <div class="analytics-chart-stage">
                    <canvas
                        id="analyticsRiskChart"
                        class="analytics-canvas"
                        aria-label="Employees below performance threshold line graph"
                    ></canvas>
                    <div class="analytics-chart-tooltip" data-tooltip hidden></div>
                </div>
            </article>
        </section>

        <section class="analytics-panel analytics-snapshot">
            <div class="analytics-panel-head">
                <div>
                    <span class="analytics-panel-eyebrow">LATEST SNAPSHOT</span>
                    <h2>Department KPI Summary</h2>
                    <p>
                        Latest available department score, evaluation coverage,
                        and month-over-month movement.
                    </p>
                </div>
            </div>

            <div class="analytics-table-scroll">
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Latest Average</th>
                            <th>Reviewed</th>
                            <th>Coverage</th>
                            <th>Change</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['departments'] as $department): ?>
                            <?php
                            $score = $department['latest_score'];
                            $change = $department['change'];
                            $coverage = $department['coverage'];

                            $statusClass = 'is-neutral';
                            $statusLabel = 'No data';

                            if ($score !== null) {
                                if ((float)$score < (float)$analytics['threshold']) {
                                    $statusClass = 'is-risk';
                                    $statusLabel = 'Needs attention';
                                } elseif ((float)$score < 75.0) {
                                    $statusClass = 'is-watch';
                                    $statusLabel = 'Monitor';
                                } else {
                                    $statusClass = 'is-good';
                                    $statusLabel = 'On track';
                                }
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="analytics-department-cell">
                                        <span
                                            class="analytics-department-dot"
                                            style="background:<?= e((string)$department['color']) ?>"
                                        ></span>
                                        <div>
                                            <strong><?= e((string)$department['name']) ?></strong>
                                            <small><?= e((string)$department['code']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong class="analytics-score">
                                        <?= $score === null
                                            ? '—'
                                            : number_format((float)$score, 1) . '%' ?>
                                    </strong>
                                </td>
                                <td>
                                    <?= number_format((int)$department['latest_reviewed']) ?>
                                    /
                                    <?= number_format((int)$department['active_employees']) ?>
                                </td>
                                <td>
                                    <?= $coverage === null
                                        ? '—'
                                        : number_format((float)$coverage, 1) . '%' ?>
                                </td>
                                <td>
                                    <?php if ($change === null): ?>
                                        <span class="analytics-change is-flat">—</span>
                                    <?php elseif ((float)$change > 0): ?>
                                        <span class="analytics-change is-up">
                                            +<?= number_format((float)$change, 1) ?>
                                        </span>
                                    <?php elseif ((float)$change < 0): ?>
                                        <span class="analytics-change is-down">
                                            <?= number_format((float)$change, 1) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="analytics-change is-flat">0.0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="analytics-status <?= e($statusClass) ?>">
                                        <?= e($statusLabel) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <script
            type="application/json"
            id="analyticsData"
        ><?= json_encode(
            $chartPayload,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        ) ?></script>
    <?php endif; ?>
</main>
