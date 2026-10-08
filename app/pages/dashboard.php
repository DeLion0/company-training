<?php
require_once __DIR__ . '/../core/dashboard-data.php';

$viewer = current_user();
$isHR = ($viewer['role'] ?? '') === 'hr';

try {
    $data = dashboard_data($viewer);
} catch (Throwable $ex) {
    error_log('Dashboard query error: ' . $ex->getMessage());
    $data = null;
}

$hasDepartmentPerformance = false;
if (is_array($data) && empty($data['unassigned'])) {
    foreach ($data['departments'] as $department) {
        foreach (($department['monthly'] ?? []) as $score) {
            if ($score !== null) {
                $hasDepartmentPerformance = true;
                break 2;
            }
        }
    }
}

$defaultPerformanceDepartmentId = 0;
if (is_array($data) && empty($data['unassigned']) && !empty($data['departments'])) {
    foreach ($data['departments'] as $department) {
        $monthlyScores = $department['monthly'] ?? [];
        if (array_filter($monthlyScores, static fn($score): bool => $score !== null)) {
            $defaultPerformanceDepartmentId = (int)$department['id'];
            break;
        }
    }

    if ($defaultPerformanceDepartmentId < 1) {
        $defaultPerformanceDepartmentId = (int)$data['departments'][0]['id'];
    }
}

$chartPayload = null;
if (is_array($data) && empty($data['unassigned'])) {
    $chartPayload = [
        'months' => $data['months'],
        'employees' => array_map(static function (array $employee): array {
            return [
                'id' => (int)$employee['id'],
                'name' => (string)$employee['full_name'],
                'employee_code' => (string)$employee['employee_code'],
                'department' => (string)$employee['department_name'],
                'job_title' => (string)$employee['job_title'],
                'latest_score' => $employee['latest_score'] !== null ? (float)$employee['latest_score'] : null,
                'latest_review_date' => $employee['latest_review_date'],
                'monthly' => $employee['monthly'],
            ];
        }, $data['employees']),
    ];
}
?>
<main class="content hr-dashboard">
    <div class="hr-intro">
        <div>
            <div class="hr-eyebrow"><i data-lucide="chart-no-axes-combined"></i> <?= $isHR ? 'PEOPLE ANALYTICS' : 'MY DEPARTMENT' ?></div>
            <h1><?= $isHR ? 'HR Executive Dashboard' : 'Department Overview' ?></h1>
            <p><?= $isHR ? 'Workforce distribution and monthly performance trends across your company.' : 'Monitor your team and its monthly performance trends.' ?></p>
        </div>
        <div class="hr-live"><span></span> Database-powered overview</div>
    </div>

    <?php if ($data === null): ?>
        <div class="hr-notice">
            <i data-lucide="database-zap"></i>
            <div><strong>Dashboard data is not ready</strong><p>Review the PHP error log and confirm the employee/performance tables are available.</p></div>
        </div>
    <?php elseif ($data['unassigned']): ?>
        <div class="hr-notice">
            <i data-lucide="shield-alert"></i>
            <div><strong>No department assigned</strong><p>Ask HR to assign this account to a department.</p></div>
        </div>
    <?php else: ?>
        <section class="hr-metrics" aria-label="Workforce summary">
            <article class="hr-metric"><div class="hr-metric-icon green"><i data-lucide="users-round"></i></div><span>Active Employees</span><strong><?= number_format($data['total']) ?></strong><small>Across your <?= $isHR ? 'company' : 'department' ?></small></article>
            <article class="hr-metric"><div class="hr-metric-icon lime"><i data-lucide="building-2"></i></div><span><?= $isHR ? 'Departments' : 'My Department' ?></span><strong><?= count($data['departments']) ?></strong><small>Departments in view</small></article>
            <article class="hr-metric"><div class="hr-metric-icon teal"><i data-lucide="chart-column-increasing"></i></div><span>Average Performance</span><strong><?= $data['average'] === null ? '—' : number_format($data['average'], 1) . '%' ?></strong><small>Latest review per evaluated employee</small></article>
            <article class="hr-metric"><div class="hr-metric-icon amber"><i data-lucide="clipboard-check"></i></div><span>Employees Evaluated</span><strong><?= number_format($data['rated']) ?><em> / <?= number_format($data['total']) ?></em></strong><small>Employees with at least one review</small></article>
        </section>

        <section class="hr-grid hr-grid-performance">
            <article class="hr-panel">
                <div class="hr-panel-head"><div><h2>Employees by Department</h2><p>Active employee distribution</p></div><i data-lucide="users"></i></div>
                <?php if (!$data['departments']): ?>
                    <div class="hr-empty">No departments found for this workspace.</div>
                <?php else:
                    $maxCount = max(1, ...array_column($data['departments'], 'count'));
                    foreach ($data['departments'] as $department): ?>
                        <div class="hr-bar-row">
                            <div class="hr-bar-label"><span><?= e((string)$department['name']) ?></span><strong><?= (int)$department['count'] ?></strong></div>
                            <div class="hr-bar-track"><span style="width:<?= round(100 * $department['count'] / $maxCount, 2) ?>%"></span></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </article>

            <article class="hr-panel hr-monthly-panel hr-department-performance-card">
                <div class="hr-performance-head">
                    <div>
                        <h2>Department Performance</h2>
                        <p>Monthly average of employee performance · last 6 months</p>
                    </div>

                    <?php if ($isHR && count($data['departments']) > 1): ?>
                        <label class="hr-department-performance-filter">
                            <span>Department</span>
                            <select id="hrPerformanceDepartmentFilter" aria-label="Choose department performance to view">
                                <?php foreach ($data['departments'] as $department): ?>
                                    <option
                                        value="<?= (int)$department['id'] ?>"
                                        <?= (int)$department['id'] === $defaultPerformanceDepartmentId ? 'selected' : '' ?>
                                    ><?= e($department['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php else: ?>
                        <div class="hr-performance-head-icon"><i data-lucide="chart-column-big"></i></div>
                    <?php endif; ?>
                </div>

                <?php if (!$data['departments']): ?>
                    <div class="hr-empty hr-chart-empty hr-chart-empty-compact">
                        <i data-lucide="building-2"></i>
                        <strong>No departments yet</strong>
                        <span>Add departments and employees to begin performance tracking.</span>
                    </div>
                <?php elseif (!$hasDepartmentPerformance): ?>
                    <div class="hr-empty hr-chart-empty hr-chart-empty-compact">
                        <i data-lucide="chart-column-big"></i>
                        <strong>No performance reviews yet</strong>
                        <span>Monthly department bars will appear after managers submit employee performance reviews.</span>
                    </div>
                <?php else: ?>
                    <div class="hr-department-performance-views">
                        <?php foreach ($data['departments'] as $department):
                            $departmentId = (int)$department['id'];
                            $latestMonthLabel = null;
                            $latestScore = null;
                            $latestReviewed = 0;

                            foreach (array_reverse($data['months']) as $month) {
                                $monthScore = $department['monthly'][$month['key']] ?? null;
                                if ($monthScore !== null) {
                                    $latestMonthLabel = $month['long_label'];
                                    $latestScore = (float)$monthScore;
                                    $latestReviewed = (int)($department['monthly_reviewed'][$month['key']] ?? 0);
                                    break;
                                }
                            }
                        ?>
                            <section
                                class="hr-dept-performance-view"
                                data-performance-department="<?= $departmentId ?>"
                                <?= $departmentId === $defaultPerformanceDepartmentId ? '' : 'hidden' ?>
                                aria-label="<?= e($department['name']) ?> monthly performance"
                            >
                                <div class="hr-performance-summary">
                                    <div class="hr-performance-department-name">
                                        <span class="hr-performance-dot"></span>
                                        <div>
                                            <strong><?= e($department['name']) ?></strong>
                                            <small><?= (int)$department['count'] ?> active employee<?= (int)$department['count'] === 1 ? '' : 's' ?></small>
                                        </div>
                                    </div>

                                    <div class="hr-performance-summary-badges">
                                        <div class="hr-performance-badge">
                                            <span>Latest score</span>
                                            <strong><?= $latestScore === null ? '—' : number_format($latestScore, 1) . '%' ?></strong>
                                        </div>
                                        <div class="hr-performance-badge muted">
                                            <span>Reviewed</span>
                                            <strong><?= $latestScore === null ? '—' : $latestReviewed . ' / ' . (int)$department['count'] ?></strong>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($latestScore === null): ?>
                                    <div class="hr-dept-no-performance">
                                        <i data-lucide="clipboard-minus"></i>
                                        <div>
                                            <strong>No reviews for this department</strong>
                                            <span>Select another department or wait for manager-submitted evaluations.</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="hr-compact-chart" role="img" aria-label="Six-month performance bar chart for <?= e($department['name']) ?>">
                                        <div class="hr-compact-y" aria-hidden="true">
                                            <span>100</span><span>75</span><span>50</span><span>25</span><span>0</span>
                                        </div>

                                        <div class="hr-compact-plot">
                                            <div class="hr-compact-grid" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>

                                            <div class="hr-compact-bars">
                                                <?php foreach ($data['months'] as $month):
                                                    $score = $department['monthly'][$month['key']] ?? null;
                                                    $reviewed = (int)($department['monthly_reviewed'][$month['key']] ?? 0);
                                                    $normalizedScore = $score === null ? 0 : max(0, min(100, (float)$score));
                                                ?>
                                                    <div class="hr-compact-month <?= $score === null ? 'no-review' : '' ?>">
                                                        <div
                                                            class="hr-compact-bar-area"
                                                            title="<?= e($month['long_label']) ?> · <?= $score === null ? 'No review' : number_format((float)$score, 1) . '% · ' . $reviewed . ' employee' . ($reviewed === 1 ? '' : 's') . ' reviewed' ?>"
                                                        >
                                                            <?php if ($score !== null): ?>
                                                                <span class="hr-compact-score"><?= number_format((float)$score, 1) ?>%</span>
                                                                <span class="hr-compact-bar" style="height:<?= max(3, $normalizedScore) ?>%"></span>
                                                            <?php else: ?>
                                                                <span class="hr-compact-no-review">No review</span>
                                                                <span class="hr-compact-missing"></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <strong><?= e($month['label']) ?></strong>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="hr-performance-footer">
                                        <span><i data-lucide="calendar-range"></i><?= e($latestMonthLabel ?? '') ?></span>
                                        <span><i data-lucide="users-round"></i><?= $latestReviewed ?> employee<?= $latestReviewed === 1 ? '' : 's' ?> included in latest monthly average</span>
                                    </div>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>

        <section class="hr-panel hr-employees">
            <div class="hr-panel-head">
                <div><h2>Employee Directory & Performance</h2><p>Click an employee to view their monthly performance</p></div>
                <span class="hr-total"><?= count($data['employees']) ?> employees</span>
            </div>

            <div class="hr-filters">
                <label class="hr-search"><i data-lucide="search"></i><input id="hrEmployeeSearch" type="search" placeholder="Search employees, job titles, or ID" aria-label="Search employees"></label>
                <?php if ($isHR): ?>
                    <select id="hrDepartmentFilter" aria-label="Filter by department"><option value="">All departments</option><?php foreach ($data['departments'] as $department): ?><option value="<?= (int)$department['id'] ?>"><?= e($department['name']) ?></option><?php endforeach; ?></select>
                <?php endif; ?>
            </div>

            <div class="hr-table-scroll">
                <table class="hr-table">
                    <thead><tr><th>Employee</th><th>Department</th><th>Position</th><th>Latest Performance</th><th>Review Date</th></tr></thead>
                    <tbody id="hrEmployeeRows">
                    <?php foreach ($data['employees'] as $employee): ?>
                        <tr data-search="<?= e(strtolower($employee['full_name'] . ' ' . $employee['employee_code'] . ' ' . $employee['job_title'] . ' ' . $employee['department_name'])) ?>" data-department="<?= (int)$employee['department_id'] ?>">
                            <td>
                                <button type="button" class="hr-employee-open" data-employee-id="<?= (int)$employee['id'] ?>">
                                    <span class="hr-initial"><?= e(strtoupper(substr($employee['full_name'], 0, 1))) ?></span>
                                    <span><strong><?= e($employee['full_name']) ?></strong><small><?= e($employee['employee_code']) ?></small></span>
                                    <i data-lucide="chart-column-increasing"></i>
                                </button>
                            </td>
                            <td><?= e($employee['department_name']) ?></td>
                            <td><?= e($employee['job_title'] ?: 'Not specified') ?></td>
                            <td><?php if ($employee['latest_score'] === null): ?><span class="hr-pill muted">Not evaluated</span><?php else: ?><span class="hr-pill"><?= number_format((float)$employee['latest_score'], 1) ?>%</span><?php endif; ?></td>
                            <td><?= $employee['latest_review_date'] ? e(date('M j, Y', strtotime($employee['latest_review_date']))) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="hrEmptyResults" class="hr-empty" <?= $data['employees'] ? 'hidden' : '' ?>><?= $data['employees'] ? 'No employees match your search.' : 'No employee records yet.' ?></div>
            <div class="hr-table-bottom"><span id="hrVisibleCount"><?= count($data['employees']) ?></span> employees shown</div>
        </section>

        <dialog id="hrEmployeePerformanceDialog" class="hr-performance-dialog">
            <div class="hr-dialog-shell">
                <div class="hr-dialog-head">
                    <div>
                        <span class="hr-dialog-kicker">MONTHLY PERFORMANCE</span>
                        <h2 id="hrDialogEmployeeName">Employee Performance</h2>
                        <p id="hrDialogEmployeeMeta"></p>
                    </div>
                    <button type="button" class="hr-dialog-close" id="hrDialogClose" aria-label="Close employee performance"><i data-lucide="x"></i></button>
                </div>
                <div class="hr-dialog-body">
                    <div class="hr-dialog-summary">
                        <div><span>Latest performance</span><strong id="hrDialogLatest">—</strong></div>
                        <div><span>Latest review</span><strong id="hrDialogDate">—</strong></div>
                    </div>
                    <div class="hr-employee-chart-wrap">
                        <div class="hr-employee-chart-y"><span>100</span><span>75</span><span>50</span><span>25</span><span>0</span></div>
                        <div class="hr-employee-chart-plot">
                            <div class="hr-chart-grid" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
                            <div class="hr-employee-bars" id="hrEmployeeMonthlyBars"></div>
                        </div>
                    </div>
                    <p class="hr-chart-note">Monthly bars are based on performance reviews submitted for this employee. Missing months are shown as no review.</p>
                </div>
            </div>
        </dialog>

        <script type="application/json" id="hrPerformanceData"><?= json_encode($chartPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php endif; ?>
</main>
