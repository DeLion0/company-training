<?php

if (!isset($employeePortalData) || !is_array($employeePortalData)) {
    throw new RuntimeException('Employee portal data was not loaded.');
}

$employee = $employeePortalData['employee'];
$latest = $employeePortalData['latest'];
$latestScore = $latest ? (float)$latest['score'] : null;
$priority = $employeePortalData['priority_trainings'];
$joined = $employeePortalData['joined_active'];
$sessions = $employeePortalData['upcoming_sessions'];
$available = $employeePortalData['available_trainings'];
$employeeId = (int)$employee['id'];
$firstName = trim(explode(' ', (string)$employee['full_name'])[0] ?? 'Employee');
?>

<main class="employee-content employee-module-page">
    <section class="employee-hero employee-home-hero">
        <div class="employee-hero-copy">
            <span class="employee-kicker">EMPLOYEE WORKSPACE</span>
            <h1>Welcome back, <?= e($firstName) ?><span>.</span></h1>
            <p>Your dashboard keeps the important things short: current performance, learning priorities, joined programs, and your next scheduled session.</p>

            <div class="employee-hero-meta">
                <span><i data-lucide="building-2"></i><?= e((string)$employee['department_name']) ?></span>
                <span><i data-lucide="briefcase-business"></i><?= e((string)$employee['job_title']) ?></span>
                <span><i data-lucide="badge-check"></i><?= e((string)$employee['employee_code']) ?></span>
            </div>
        </div>

        <div class="employee-hero-action">
            <span class="employee-hero-action-label">WHAT NEEDS ATTENTION</span>
            <?php if ($priority): ?>
                <strong><?= count($priority) ?> priority training<?= count($priority) === 1 ? '' : 's' ?> waiting</strong>
                <p>Review the training marked as Priority to Take before exploring other programs.</p>
                <a href="<?= e(employee_preview_link('priority', $employeeId)) ?>">Open Priority to Take <i data-lucide="arrow-up-right"></i></a>
            <?php elseif ($sessions): ?>
                <strong>Your next learning session is scheduled</strong>
                <p><?= e(date('F j, Y', strtotime((string)$sessions[0]['session_date']))) ?> · <?= e(date('g:i A', strtotime((string)$sessions[0]['start_time']))) ?></p>
                <a href="<?= e(employee_preview_link('my-training', $employeeId)) ?>">Open My Training <i data-lucide="arrow-up-right"></i></a>
            <?php else: ?>
                <strong>You're all caught up</strong>
                <p>Explore available training programs that are open to your department.</p>
                <a href="<?= e(employee_preview_link('available', $employeeId)) ?>">Browse Available Training <i data-lucide="arrow-up-right"></i></a>
            <?php endif; ?>
        </div>
    </section>

    <section class="employee-metrics" aria-label="Employee dashboard summary">
        <article class="employee-metric">
            <span class="employee-metric-icon is-green"><i data-lucide="chart-column-increasing"></i></span>
            <small>Latest Performance</small>
            <strong><?= $latestScore === null ? '—' : number_format($latestScore, 1) . '%' ?></strong>
            <span><?= $latest ? e(date('F Y', strtotime((string)$latest['review_month']))) : 'No official rating yet' ?></span>
        </article>

        <article class="employee-metric">
            <span class="employee-metric-icon is-amber"><i data-lucide="badge-alert"></i></span>
            <small>Priority to Take</small>
            <strong><?= count($priority) ?></strong>
            <span>Recommended for attention</span>
        </article>

        <article class="employee-metric">
            <span class="employee-metric-icon is-blue"><i data-lucide="book-open-check"></i></span>
            <small>Joined Programs</small>
            <strong><?= count($joined) ?></strong>
            <span>Active learning</span>
        </article>

        <article class="employee-metric">
            <span class="employee-metric-icon is-purple"><i data-lucide="calendar-clock"></i></span>
            <small>Upcoming Sessions</small>
            <strong><?= count($sessions) ?></strong>
            <span>Scheduled meetings</span>
        </article>
    </section>

    <section class="employee-home-grid">
        <article class="employee-panel">
            <div class="employee-panel-head">
                <div>
                    <span class="employee-section-kicker is-amber">NEEDS YOUR ATTENTION</span>
                    <h2>Learning Priority</h2>
                    <p>Your most important recommendation right now.</p>
                </div>
                <a class="employee-text-link" href="<?= e(employee_preview_link('priority', $employeeId)) ?>">View all</a>
            </div>

            <?php if ($priority): $training = $priority[0]; ?>
                <div class="employee-focus-card">
                    <div class="employee-focus-icon is-amber"><i data-lucide="sparkles"></i></div>
                    <div class="employee-focus-copy">
                        <span class="employee-status-pill is-priority">Priority to Take</span>
                        <h3><?= e((string)$training['title']) ?></h3>
                        <p><?= e((string)$training['purpose']) ?></p>
                        <div class="employee-training-card-facts">
                            <span><i data-lucide="calendar-days"></i><?= e(date('M j', strtotime((string)$training['range_start']))) ?> – <?= e(date('M j, Y', strtotime((string)$training['range_end']))) ?></span>
                            <span><i data-lucide="map-pin"></i><?= e((string)$training['venue']) ?></span>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="employee-empty horizontal">
                    <i data-lucide="circle-check-big"></i>
                    <div><strong>No priority training right now</strong><span>Future priority recommendations will appear here.</span></div>
                </div>
            <?php endif; ?>
        </article>

        <article class="employee-panel">
            <div class="employee-panel-head compact">
                <div>
                    <span class="employee-section-kicker">NEXT ON YOUR CALENDAR</span>
                    <h2>Upcoming Session</h2>
                </div>
                <a class="employee-text-link" href="<?= e(employee_preview_link('my-training', $employeeId)) ?>">My Training</a>
            </div>

            <?php if ($sessions): $session = $sessions[0]; ?>
                <div class="employee-next-session">
                    <div class="employee-next-date">
                        <strong><?= e(date('d', strtotime((string)$session['session_date']))) ?></strong>
                        <span><?= e(strtoupper(date('M', strtotime((string)$session['session_date'])))) ?></span>
                    </div>
                    <div>
                        <span class="employee-status-pill is-joined">Joined</span>
                        <h3><?= e((string)$session['title']) ?></h3>
                        <p>Meeting <?= (int)$session['session_number'] ?> · <?= e(date('g:i A', strtotime((string)$session['start_time']))) ?>–<?= e(date('g:i A', strtotime((string)$session['end_time']))) ?></p>
                        <span class="employee-mini-meta"><i data-lucide="map-pin"></i><?= e((string)$session['venue']) ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="employee-empty horizontal">
                    <i data-lucide="calendar-x"></i>
                    <div><strong>No upcoming session</strong><span>Sessions from joined training will appear here.</span></div>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <section class="employee-panel employee-home-learning">
        <div class="employee-panel-head">
            <div>
                <span class="employee-section-kicker">CONTINUE LEARNING</span>
                <h2>Your Training</h2>
                <p>Quick access to programs you joined and training currently available to you.</p>
            </div>
            <div class="employee-inline-actions">
                <a class="employee-button is-secondary" href="<?= e(employee_preview_link('my-training', $employeeId)) ?>">My Training</a>
                <a class="employee-button is-primary" href="<?= e(employee_preview_link('available', $employeeId)) ?>">Browse Training</a>
            </div>
        </div>

        <div class="employee-home-mini-grid">
            <?php foreach (array_slice($joined, 0, 2) as $training): ?>
                <article class="employee-mini-training-card">
                    <span class="employee-mini-training-icon"><i data-lucide="graduation-cap"></i></span>
                    <div>
                        <span class="employee-status-pill is-joined">Joined</span>
                        <h3><?= e((string)$training['title']) ?></h3>
                        <p><?= e((string)$training['venue']) ?> · until <?= e(date('M j, Y', strtotime((string)$training['range_end']))) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if (!$joined): ?>
                <div class="employee-empty horizontal employee-home-empty">
                    <i data-lucide="book-open"></i>
                    <div><strong>No joined training yet</strong><span>Use Available Training to find a program.</span></div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
