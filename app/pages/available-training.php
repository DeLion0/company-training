<?php
$user = authenticated_user();
if (!$user || ($user['role'] ?? '') !== 'hr') {
    http_response_code(403);
    exit('Access denied. HR only.');
}

require_once __DIR__ . '/../core/available-training-data.php';

$availableData = available_training_data((int) $user['id']);
$programs = $availableData['programs'];
$employees = $availableData['employees'];
$departments = $availableData['departments'];

$flash = $_SESSION['available_message'] ?? null;
$flashError = $_SESSION['available_error'] ?? null;
unset($_SESSION['available_message'], $_SESSION['available_error']);

$totals = [
    'programs' => count($programs),
    'joined' => 0,
    'recommended' => 0,
    'open_slots' => 0,
];

foreach ($programs as $program) {
    $totals['joined'] += (int) $program['joined_count'];
    $totals['recommended'] += (int) $program['recommended_count'];
    $totals['open_slots'] += (int) $program['open_slots'];
}
?>

<main class="content available-page" id="availablePage">
    <div class="av-heading">
        <div>
            <div class="av-kicker">LEARNING &amp; DEVELOPMENT</div>
            <h1>Available Training<span>.</span></h1>
            <p>Monitor published programs, joined employees, and HR recommendations.</p>
        </div>

        <a class="av-btn av-btn-primary" href="?page=training">
            <i data-lucide="plus"></i>
            Create Program
        </a>
    </div>

    <?php if ($flash): ?>
        <div class="av-alert success" role="status"><?= e((string) $flash) ?></div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="av-alert error" role="alert"><?= e((string) $flashError) ?></div>
    <?php endif; ?>

    <section class="av-metrics" aria-label="Available training overview">
        <div class="av-metric">
            <i data-lucide="book-open-check"></i>
            <span>Published Programs</span>
            <strong><?= (int) $totals['programs'] ?></strong>
        </div>

        <div class="av-metric">
            <i data-lucide="user-check"></i>
            <span>Joined Employees</span>
            <strong><?= (int) $totals['joined'] ?></strong>
        </div>

        <div class="av-metric">
            <i data-lucide="send"></i>
            <span>Recommended</span>
            <strong><?= (int) $totals['recommended'] ?></strong>
        </div>

        <div class="av-metric">
            <i data-lucide="armchair"></i>
            <span>Open Slots</span>
            <strong><?= (int) $totals['open_slots'] ?></strong>
        </div>
    </section>

    <div class="av-toolbar">
        <div>
            <h2>Training Directory</h2>
            <p>Select a program to view sessions, joined employees, recommendations, and performance.</p>
        </div>

        <label class="av-search">
            <i data-lucide="search"></i>
            <input
                id="avSearch"
                aria-label="Search training programs"
                placeholder="Search programs or instructors..."
            >
        </label>
    </div>

    <div class="av-grid" id="avGrid">
        <?php foreach ($programs as $program): ?>
            <?php
            $joined = (int) $program['joined_count'];
            $capacity = (int) $program['capacity'];
            $openSlots = (int) $program['open_slots'];
            $joinedWidth = min(100, round(100 * $joined / max(1, $capacity)));
            ?>

            <article
                class="av-program"
                data-filter="<?= e(strtolower((string) $program['title'] . ' ' . (string) $program['instructors'])) ?>"
            >
                <div class="av-program-top">
                    <span class="av-pill">Published</span>
                    <span class="av-program-id">#<?= (int) $program['id'] ?></span>
                </div>

                <h3><?= e((string) $program['title']) ?></h3>

                <p class="av-description">
                    <?= e((function_exists('mb_substr')
                        ? mb_substr((string) $program['purpose'], 0, 140)
                        : substr((string) $program['purpose'], 0, 140))) ?>
                </p>

                <div class="av-meta">
                    <span>
                        <i data-lucide="calendar-days"></i>
                        <?= e((string) $program['range_start']) ?> – <?= e((string) $program['range_end']) ?>
                    </span>
                    <span>
                        <i data-lucide="user-round"></i>
                        <?= e((string) $program['instructors']) ?>
                    </span>
                </div>

                <div class="av-capacity">
                    <div>
                        <span>Joined</span>
                        <strong><?= $joined ?> / <?= $capacity ?></strong>
                    </div>
                    <div class="av-progress" aria-label="<?= $joined ?> of <?= $capacity ?> seats joined">
                        <span style="width: <?= $joinedWidth ?>%"></span>
                    </div>
                </div>

                <div class="av-program-bottom">
                    <span>
                        <?= (int) $program['recommended_count'] ?> recommended ·
                        <?= $openSlots ?> open slot<?= $openSlots === 1 ? '' : 's' ?>
                    </span>

                    <button
                        type="button"
                        class="av-btn av-btn-outline av-open"
                        data-id="<?= (int) $program['id'] ?>"
                    >
                        View Details
                        <i data-lucide="arrow-up-right"></i>
                    </button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (!$programs): ?>
        <div class="av-empty">
            No published training programs yet. Create and publish a program to see it here.
        </div>
    <?php endif; ?>

    <div id="avNoMatch" class="av-empty" hidden>
        No programs match your search.
    </div>
</main>

<?php foreach ($programs as $program): ?>
    <?php
    $programId = (int) $program['id'];
    $joined = (int) $program['joined_count'];
    $recommended = (int) $program['recommended_count'];
    $peopleTotal = $joined + $recommended;
    $joinedPercent = $peopleTotal > 0 ? round(($joined / $peopleTotal) * 100, 2) : 0;
    $openSlots = (int) $program['open_slots'];

    $existingEmployeeIds = [];
    foreach ($program['participants'] as $participant) {
        $existingEmployeeIds[(int) $participant['employee_id']] = true;
    }
    ?>

    <dialog
        class="av-dialog"
        id="avDialog<?= $programId ?>"
        aria-label="Training details for <?= e((string) $program['title']) ?>"
    >
        <div class="av-dialog-head">
            <div>
                <span class="av-kicker">TRAINING PROGRAM DETAILS</span>
                <h2><?= e((string) $program['title']) ?></h2>
                <span class="av-pill">Published</span>
            </div>

            <button class="av-icon av-close" aria-label="Close details" type="button">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="av-dialog-scroll">
            <div class="av-info-grid">
                <section>
                    <h3>Purpose</h3>
                    <p><?= nl2br(e((string) $program['purpose'])) ?></p>
                </section>

                <section>
                    <h3>Learning Objectives</h3>
                    <p><?= nl2br(e((string) $program['objectives'])) ?></p>
                </section>
            </div>

            <div class="av-facts">
                <span>
                    <i data-lucide="user-round-check"></i>
                    <?= e((string) $program['instructors']) ?>
                </span>
                <span>
                    <i data-lucide="map-pin"></i>
                    <?= e((string) $program['venue']) ?>
                </span>
                <span>
                    <i data-lucide="users"></i>
                    <?= $joined ?> / <?= (int) $program['capacity'] ?> joined
                </span>
                <span>
                    <i data-lucide="armchair"></i>
                    <?= $openSlots ?> open slot<?= $openSlots === 1 ? '' : 's' ?>
                </span>
            </div>

            <details class="av-schedule">
                <summary>
                    <i data-lucide="calendar-days"></i>
                    Meeting Schedule (<?= count($program['sessions']) ?>)
                    <i data-lucide="chevron-down"></i>
                </summary>

                <div>
                    <?php foreach ($program['sessions'] as $session): ?>
                        <p>
                            <strong>Meeting <?= (int) $session['session_number'] ?></strong>
                            · <?= e((string) $session['session_date']) ?>
                            · <?= e(substr((string) $session['start_time'], 0, 5)) ?>–<?= e(substr((string) $session['end_time'], 0, 5)) ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            </details>

            <div class="av-dialog-section-title">
                <div>
                    <h3>Participants &amp; Recommendations</h3>
                    <p>Joined employees use seats. Recommendations do not use a seat until the employee joins.</p>
                </div>
            </div>

            <div class="av-analytics">
                <div
                    class="av-chart"
                    role="img"
                    aria-label="Participation breakdown: <?= $joined ?> joined and <?= $recommended ?> recommended"
                >
                    <div
                        class="av-donut <?= $peopleTotal === 0 ? 'empty' : '' ?>"
                        style="--joined: <?= $joinedPercent ?>%"
                    >
                        <div>
                            <strong><?= $peopleTotal ?></strong>
                            <small>People</small>
                        </div>
                    </div>
                </div>

                <div class="av-chart-legend">
                    <p>
                        <i class="av-dot joined"></i>
                        Joined
                        <strong><?= $joined ?></strong>
                    </p>
                    <p>
                        <i class="av-dot recommended"></i>
                        Recommended
                        <strong><?= $recommended ?></strong>
                    </p>
                </div>

                <div class="av-slot-card">
                    <span>Open Slots</span>
                    <strong><?= $openSlots ?></strong>
                    <small>
                        Capacity <?= (int) $program['capacity'] ?> minus <?= $joined ?> employee<?= $joined === 1 ? '' : 's' ?> already joined.
                    </small>
                </div>
            </div>

            <section class="av-recommend" aria-label="Recommend training">
                <div class="av-recommend-head">
                    <div>
                        <strong>Recommend this training</strong>
                        <small>
                            Choose a department first. Departments are listed with their latest average performance, lowest evaluated averages first. Then choose an employee from that department.
                        </small>
                    </div>
                </div>

                <form
                    method="post"
                    action="?page=available-training"
                    class="av-recommend-form"
                    data-recommend-form
                    data-full="<?= $openSlots <= 0 ? '1' : '0' ?>"
                >
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="available_action" value="recommend">
                    <input type="hidden" name="program_id" value="<?= $programId ?>">

                    <div class="av-field-mini">
                        <label for="avDept<?= $programId ?>">Department</label>
                        <select
                            id="avDept<?= $programId ?>"
                            name="department_id"
                            data-recommend-department
                            required
                            <?= $openSlots <= 0 ? 'disabled' : '' ?>
                        >
                            <option value="">Choose department</option>
                            <?php foreach ($departments as $department): ?>
                                <?php
                                $avg = $department['average_score'];
                                $performanceLabel = $avg !== null
                                    ? 'Avg ' . number_format((float) $avg, 1) . '%'
                                    : 'No performance data';
                                ?>
                                <option value="<?= (int) $department['id'] ?>">
                                    <?= e((string) $department['name'] . ' · ' . $performanceLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="av-field-mini">
                        <label for="avEmp<?= $programId ?>">Employee</label>
                        <select
                            id="avEmp<?= $programId ?>"
                            name="employee_id"
                            data-recommend-employee
                            required
                            disabled
                        >
                            <option value="">Choose employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <?php
                                $employeeId = (int) $employee['id'];
                                $scoreLabel = $employee['overall_score'] !== null
                                    ? number_format((float) $employee['overall_score'], 1) . '%'
                                    : 'No evaluation';
                                $alreadyIncluded = isset($existingEmployeeIds[$employeeId]);
                                ?>
                                <option
                                    value="<?= $employeeId ?>"
                                    data-department="<?= (int) $employee['department_id'] ?>"
                                    <?= $alreadyIncluded ? 'disabled' : '' ?>
                                >
                                    <?= e((string) $employee['full_name'] . ' · ' . (string) $employee['job_title'] . ' · ' . $scoreLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button
                        class="av-btn av-btn-primary"
                        type="submit"
                        disabled
                    >
                        <i data-lucide="send"></i>
                        Recommend
                    </button>
                </form>

                <p class="av-dept-note">
                    Performance is only a decision aid. No fixed low-performance cutoff is hard-coded yet; HR still chooses the department and employee.
                </p>
            </section>

            <div class="av-table-wrap">
                <table class="av-table">
                    <thead>
                        <tr>
                            <th>Employee / Department</th>
                            <th>Performance</th>
                            <th>Source</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($program['participants'] as $participant): ?>
                            <tr>
                                <td>
                                    <strong><?= e((string) $participant['full_name']) ?></strong>
                                    <small>
                                        <?= e((string) $participant['employee_code'] . ' · ' . (string) $participant['department_name']) ?>
                                    </small>
                                    <small><?= e((string) $participant['job_title']) ?></small>
                                </td>
                                <td>
                                    <?php if ($participant['overall_score'] !== null): ?>
                                        <strong><?= e((string) $participant['overall_score']) ?>%</strong>
                                        <small><?= e((string) $participant['review_date']) ?></small>
                                        <small>By <?= e((string) ($participant['reviewer_name'] ?? 'Unknown')) ?></small>
                                    <?php else: ?>
                                        <span class="av-muted">No manager evaluation</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $participant['source'] === 'employee'
                                        ? 'Employee joined'
                                        : 'HR recommendation' ?>
                                </td>
                                <td>
                                    <span class="av-status <?= e((string) $participant['status']) ?>">
                                        <?= e(ucfirst((string) $participant['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$program['participants']): ?>
                            <tr>
                                <td colspan="4" class="av-muted av-no-applicants">
                                    No employees have joined or been recommended yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="av-dialog-footer">
            <button type="button" class="av-btn av-btn-outline av-close">
                Close
            </button>
        </div>
    </dialog>
<?php endforeach; ?>
