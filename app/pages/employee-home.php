<?php

if (!isset($employeePortalData) || !is_array($employeePortalData)) {
    throw new RuntimeException('Employee portal data was not loaded.');
}

$employee = $employeePortalData['employee'];
$latest = $employeePortalData['latest'];
$months = $employeePortalData['months'] ?? [];
$monthly = $employeePortalData['monthly'] ?? [];
$priority = $employeePortalData['priority_trainings'] ?? [];
$recommended = $employeePortalData['recommended_trainings'] ?? [];
$joined = $employeePortalData['joined_active'] ?? [];
$completed = $employeePortalData['joined_completed'] ?? [];
$sessions = $employeePortalData['upcoming_sessions'] ?? [];
$available = $employeePortalData['available_trainings'] ?? [];

$employeeId = (int) $employee['id'];
$firstName = trim(explode(' ', (string) $employee['full_name'])[0] ?? 'Employee');
$latestScore = $latest ? (float) $latest['score'] : null;
$latestMonth = $latest
    ? date('F Y', strtotime((string) $latest['review_month']))
    : null;

$employeePortalPreview =
    isset($employeePortalPreview)
    && $employeePortalPreview === true;

$moduleUrl = static function (string $module) use (
    $employeePortalPreview,
    $employeeId
): string {
    if ($employeePortalPreview) {
        return 'employee-ui-preview.php?module='
            . rawurlencode($module)
            . '&employee_id='
            . $employeeId;
    }

    return 'employee.php?module=' . rawurlencode($module);
};

$nextPriority = $priority[0] ?? null;
$nextSession = $sessions[0] ?? null;

$attentionCount =
    count($priority)
    + ($latest === null ? 1 : 0)
    + ($nextSession ? 1 : 0);

$trend = 'No trend yet';
$trendClass = 'neutral';
$validScores = [];

foreach ($months as $month) {
    $key = (string) ($month['key'] ?? '');
    if (isset($monthly[$key]) && $monthly[$key] !== null) {
        $validScores[] = (float) $monthly[$key];
    }
}

if (count($validScores) >= 2) {
    $last = $validScores[count($validScores) - 1];
    $previous = $validScores[count($validScores) - 2];
    $delta = $last - $previous;

    if ($delta > 0.05) {
        $trend = '+' . number_format($delta, 1) . ' pts';
        $trendClass = 'up';
    } elseif ($delta < -0.05) {
        $trend = number_format($delta, 1) . ' pts';
        $trendClass = 'down';
    } else {
        $trend = 'No change';
    }
}
?>

<main class="employee-content employee-module-page employee-dashboard">

    <section class="employee-dashboard-hero">
        <div class="employee-dashboard-hero-copy">
            <span class="employee-kicker">EMPLOYEE WORKSPACE</span>

            <h1>
                Welcome back, <?= e($firstName) ?><span>.</span>
            </h1>

            <p>
                Your dashboard keeps the important things visible:
                current performance, learning priorities, joined training,
                and your next scheduled session.
            </p>

            <div class="employee-hero-meta">
                <span>
                    <i data-lucide="building-2"></i>
                    <?= e((string) $employee['department_name']) ?>
                </span>

                <span>
                    <i data-lucide="briefcase-business"></i>
                    <?= e((string) $employee['job_title']) ?>
                </span>

                <span>
                    <i data-lucide="badge-check"></i>
                    <?= e((string) $employee['employee_code']) ?>
                </span>
            </div>
        </div>

        <div class="employee-dashboard-hero-focus">
            <span class="employee-dashboard-focus-icon">
                <i
                    data-lucide="<?= $nextPriority
                        ? 'badge-alert'
                        : ($nextSession ? 'calendar-clock' : 'circle-check-big')
                    ?>"
                ></i>
            </span>

            <div>
                <small>WHAT NEEDS ATTENTION</small>

                <?php if ($nextPriority): ?>
                    <strong>
                        <?= count($priority) ?>
                        priority training<?= count($priority) === 1 ? '' : 's' ?> waiting
                    </strong>

                    <p><?= e((string) $nextPriority['title']) ?></p>

                    <a href="<?= e($moduleUrl('priority')) ?>">
                        Review Priority
                        <i data-lucide="arrow-right"></i>
                    </a>

                <?php elseif ($latest === null): ?>
                    <strong>Performance rating pending</strong>

                    <p>
                        Your latest official monthly rating will appear
                        once it is recorded.
                    </p>

                    <a href="<?= e($moduleUrl('performance')) ?>">
                        Open My Performance
                        <i data-lucide="arrow-right"></i>
                    </a>

                <?php elseif ($nextSession): ?>
                    <strong>Your next session is scheduled</strong>

                    <p>
                        <?= e(date(
                            'M j, Y',
                            strtotime((string) $nextSession['session_date'])
                        )) ?>
                        ·
                        <?= e(date(
                            'g:i A',
                            strtotime((string) $nextSession['start_time'])
                        )) ?>
                    </p>

                    <a href="<?= e($moduleUrl('my-training')) ?>">
                        Open My Training
                        <i data-lucide="arrow-right"></i>
                    </a>

                <?php else: ?>
                    <strong>You're all caught up</strong>

                    <p>
                        No priority action is waiting right now.
                    </p>

                    <a href="<?= e($moduleUrl('available')) ?>">
                        Browse Training
                        <i data-lucide="arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>


    <section class="employee-dashboard-metrics" aria-label="Employee dashboard summary">

        <a class="employee-dashboard-metric" href="<?= e($moduleUrl('performance')) ?>">
            <span class="employee-dashboard-metric-icon is-green">
                <i data-lucide="chart-column-increasing"></i>
            </span>

            <span class="employee-dashboard-metric-copy">
                <small>Latest Performance</small>
                <strong>
                    <?= $latestScore === null
                        ? '—'
                        : number_format($latestScore, 1) . '%'
                    ?>
                </strong>
                <span>
                    <?= $latestMonth
                        ? e($latestMonth)
                        : 'No official rating yet'
                    ?>
                </span>
            </span>

            <i class="employee-dashboard-metric-arrow" data-lucide="arrow-up-right"></i>
        </a>


        <a class="employee-dashboard-metric" href="<?= e($moduleUrl('priority')) ?>">
            <span class="employee-dashboard-metric-icon is-amber">
                <i data-lucide="badge-alert"></i>
            </span>

            <span class="employee-dashboard-metric-copy">
                <small>Priority Training</small>
                <strong><?= count($priority) ?></strong>
                <span>
                    <?= count($priority) > 0
                        ? 'Needs your attention'
                        : 'No priority training'
                    ?>
                </span>
            </span>

            <i class="employee-dashboard-metric-arrow" data-lucide="arrow-up-right"></i>
        </a>


        <a class="employee-dashboard-metric" href="<?= e($moduleUrl('my-training')) ?>">
            <span class="employee-dashboard-metric-icon is-blue">
                <i data-lucide="book-open-check"></i>
            </span>

            <span class="employee-dashboard-metric-copy">
                <small>Joined Training</small>
                <strong><?= count($joined) ?></strong>
                <span>Active learning programs</span>
            </span>

            <i class="employee-dashboard-metric-arrow" data-lucide="arrow-up-right"></i>
        </a>


        <a class="employee-dashboard-metric" href="<?= e($moduleUrl('my-training')) ?>">
            <span class="employee-dashboard-metric-icon is-purple">
                <i data-lucide="calendar-clock"></i>
            </span>

            <span class="employee-dashboard-metric-copy">
                <small>Upcoming Sessions</small>
                <strong><?= count($sessions) ?></strong>
                <span>Scheduled learning sessions</span>
            </span>

            <i class="employee-dashboard-metric-arrow" data-lucide="arrow-up-right"></i>
        </a>

    </section>


    <section class="employee-dashboard-primary-grid">

        <article class="employee-dashboard-panel">

            <div class="employee-dashboard-panel-head">
                <div>
                    <span class="employee-section-kicker">
                        PERFORMANCE AT A GLANCE
                    </span>

                    <h2>Six-Month Performance</h2>

                    <p>
                        Official monthly ratings only.
                        Full history stays in My Performance.
                    </p>
                </div>

                <a href="<?= e($moduleUrl('performance')) ?>">
                    View performance
                    <i data-lucide="arrow-up-right"></i>
                </a>
            </div>


            <?php if ($validScores): ?>

                <div class="employee-dashboard-performance-top">
                    <div>
                        <small>Latest score</small>

                        <strong>
                            <?= number_format($latestScore ?? 0, 1) ?>%
                        </strong>

                        <span>
                            <?= $latestMonth ? e($latestMonth) : '' ?>
                        </span>
                    </div>

                    <span class="employee-dashboard-trend is-<?= e($trendClass) ?>">
                        <i
                            data-lucide="<?= $trendClass === 'up'
                                ? 'trending-up'
                                : ($trendClass === 'down' ? 'trending-down' : 'minus')
                            ?>"
                        ></i>
                        <?= e($trend) ?>
                    </span>
                </div>


                <div
                    class="employee-dashboard-bars"
                    aria-label="Six month performance chart"
                >
                    <?php foreach ($months as $month): ?>
                        <?php
                        $monthKey = (string) ($month['key'] ?? '');
                        $score = isset($monthly[$monthKey])
                            && $monthly[$monthKey] !== null
                                ? (float) $monthly[$monthKey]
                                : null;

                        $barHeight = $score === null
                            ? 0
                            : max(3, min(100, $score));
                        ?>

                        <div class="employee-dashboard-bar-item">
                            <div class="employee-dashboard-bar-track">

                                <?php if ($score !== null): ?>
                                    <span
                                        class="employee-dashboard-bar-fill"
                                        style="height:<?= e((string) $barHeight) ?>%;"
                                        title="<?= e((string) $month['long_label']) ?>: <?= number_format($score, 1) ?>%"
                                    >
                                        <b><?= number_format($score, 0) ?></b>
                                    </span>
                                <?php else: ?>
                                    <span class="employee-dashboard-bar-empty">—</span>
                                <?php endif; ?>

                            </div>

                            <small><?= e((string) $month['label']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>

                <div class="employee-dashboard-empty">
                    <span>
                        <i data-lucide="chart-no-axes-column"></i>
                    </span>

                    <div>
                        <strong>No performance data yet</strong>

                        <p>
                            Once HR records your official monthly performance,
                            your trend will appear here.
                        </p>
                    </div>
                </div>

            <?php endif; ?>

        </article>


        <article class="employee-dashboard-panel employee-dashboard-attention">

            <div class="employee-dashboard-panel-head">
                <div>
                    <span class="employee-section-kicker is-amber">
                        WHAT NEEDS YOUR ATTENTION
                    </span>

                    <h2>Focus Now</h2>

                    <p>Only the next useful actions.</p>
                </div>

                <?php if ($attentionCount > 0): ?>
                    <span class="employee-dashboard-count">
                        <?= $attentionCount ?>
                    </span>
                <?php endif; ?>
            </div>


            <div class="employee-dashboard-attention-list">

                <?php if ($nextPriority): ?>

                    <a
                        class="employee-dashboard-attention-item is-priority"
                        href="<?= e($moduleUrl('priority')) ?>"
                    >
                        <span>
                            <i data-lucide="badge-alert"></i>
                        </span>

                        <div>
                            <small>PRIORITY TO TAKE</small>
                            <strong><?= e((string) $nextPriority['title']) ?></strong>

                            <?php if (
                                isset($nextPriority['performance_score'])
                                && $nextPriority['performance_score'] !== null
                            ): ?>
                                <p>
                                    Recommended from your
                                    <?= number_format(
                                        (float) $nextPriority['performance_score'],
                                        1
                                    ) ?>%
                                    performance result.
                                </p>
                            <?php else: ?>
                                <p>
                                    This training is marked as a current learning priority.
                                </p>
                            <?php endif; ?>
                        </div>

                        <i data-lucide="chevron-right"></i>
                    </a>

                <?php endif; ?>


                <?php if ($latest === null): ?>

                    <a
                        class="employee-dashboard-attention-item"
                        href="<?= e($moduleUrl('performance')) ?>"
                    >
                        <span>
                            <i data-lucide="clipboard-clock"></i>
                        </span>

                        <div>
                            <small>PERFORMANCE</small>
                            <strong>No official rating yet</strong>
                            <p>Your first monthly evaluation has not been recorded.</p>
                        </div>

                        <i data-lucide="chevron-right"></i>
                    </a>

                <?php endif; ?>


                <?php if ($nextSession): ?>

                    <a
                        class="employee-dashboard-attention-item"
                        href="<?= e($moduleUrl('my-training')) ?>"
                    >
                        <span>
                            <i data-lucide="calendar-days"></i>
                        </span>

                        <div>
                            <small>NEXT SESSION</small>
                            <strong><?= e((string) $nextSession['title']) ?></strong>

                            <p>
                                <?= e(date(
                                    'M j',
                                    strtotime((string) $nextSession['session_date'])
                                )) ?>
                                ·
                                <?= e(date(
                                    'g:i A',
                                    strtotime((string) $nextSession['start_time'])
                                )) ?>
                            </p>
                        </div>

                        <i data-lucide="chevron-right"></i>
                    </a>

                <?php endif; ?>


                <?php if (!$nextPriority && $latest !== null && !$nextSession): ?>

                    <div class="employee-dashboard-attention-item is-clear">
                        <span>
                            <i data-lucide="circle-check-big"></i>
                        </span>

                        <div>
                            <small>STATUS</small>
                            <strong>You're all caught up</strong>

                            <p>
                                No priority training or upcoming session needs
                                your attention right now.
                            </p>
                        </div>
                    </div>

                <?php endif; ?>

            </div>

        </article>

    </section>


    <section class="employee-dashboard-secondary-grid">

        <article class="employee-dashboard-panel">

            <div class="employee-dashboard-panel-head">
                <div>
                    <span class="employee-section-kicker">
                        NEXT ON YOUR CALENDAR
                    </span>

                    <h2>Upcoming Session</h2>
                </div>

                <a href="<?= e($moduleUrl('my-training')) ?>">
                    My Training
                    <i data-lucide="arrow-up-right"></i>
                </a>
            </div>


            <?php if ($nextSession): ?>

                <div class="employee-dashboard-next-session">

                    <div class="employee-dashboard-date-block">
                        <strong>
                            <?= e(date(
                                'd',
                                strtotime((string) $nextSession['session_date'])
                            )) ?>
                        </strong>

                        <span>
                            <?= e(strtoupper(date(
                                'M',
                                strtotime((string) $nextSession['session_date'])
                            ))) ?>
                        </span>
                    </div>

                    <div class="employee-dashboard-next-copy">
                        <span class="employee-status-pill is-joined">
                            Joined
                        </span>

                        <h3><?= e((string) $nextSession['title']) ?></h3>

                        <p>
                            Session <?= (int) $nextSession['session_number'] ?>
                            ·
                            <?= e(date(
                                'g:i A',
                                strtotime((string) $nextSession['start_time'])
                            )) ?>
                            –
                            <?= e(date(
                                'g:i A',
                                strtotime((string) $nextSession['end_time'])
                            )) ?>
                        </p>

                        <div>
                            <span>
                                <i data-lucide="map-pin"></i>
                                <?= e((string) $nextSession['venue']) ?>
                            </span>

                            <span>
                                <i data-lucide="calendar"></i>
                                <?= e(date(
                                    'l, F j, Y',
                                    strtotime((string) $nextSession['session_date'])
                                )) ?>
                            </span>
                        </div>
                    </div>

                </div>

            <?php else: ?>

                <div class="employee-dashboard-empty is-compact">
                    <span>
                        <i data-lucide="calendar-x"></i>
                    </span>

                    <div>
                        <strong>No upcoming session</strong>

                        <p>
                            Join an available training program to see
                            scheduled sessions here.
                        </p>

                        <a href="<?= e($moduleUrl('available')) ?>">
                            Explore Available Training
                        </a>
                    </div>
                </div>

            <?php endif; ?>

        </article>


        <article class="employee-dashboard-panel">

            <div class="employee-dashboard-panel-head">
                <div>
                    <span class="employee-section-kicker">
                        LEARNING SNAPSHOT
                    </span>

                    <h2>My Training</h2>

                    <p>
                        Summary only — full lists stay in My Training.
                    </p>
                </div>

                <a href="<?= e($moduleUrl('my-training')) ?>">
                    View all
                    <i data-lucide="arrow-up-right"></i>
                </a>
            </div>


            <div class="employee-dashboard-learning-stats">

                <div>
                    <span class="is-blue">
                        <i data-lucide="book-open-check"></i>
                    </span>

                    <div>
                        <strong><?= count($joined) ?></strong>
                        <small>Joined</small>
                    </div>
                </div>

                <div>
                    <span class="is-amber">
                        <i data-lucide="sparkles"></i>
                    </span>

                    <div>
                        <strong><?= count($recommended) ?></strong>
                        <small>Recommended</small>
                    </div>
                </div>

                <div>
                    <span class="is-green">
                        <i data-lucide="circle-check-big"></i>
                    </span>

                    <div>
                        <strong><?= count($completed) ?></strong>
                        <small>Completed</small>
                    </div>
                </div>

            </div>


            <div class="employee-dashboard-learning-footer">
                <div>
                    <span>Available for you</span>

                    <strong>
                        <?= count($available) ?>
                        training<?= count($available) === 1 ? '' : 's' ?>
                    </strong>
                </div>

                <a
                    href="<?= e($moduleUrl('available')) ?>"
                    class="employee-button is-primary employee-dashboard-browse"
                >
                    Browse Training
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>

        </article>

    </section>

</main>
