<?php

if (!isset($employeePortalData) || !is_array($employeePortalData)) {
    throw new RuntimeException('Employee portal data was not loaded.');
}

$joinedActive = $employeePortalData['joined_active'];
$joinedCompleted = $employeePortalData['joined_completed'];
$recommended = $employeePortalData['recommended_trainings'];
$sessions = $employeePortalData['upcoming_sessions'];
?>

<main class="employee-content employee-module-page">
    <header class="employee-page-heading">
        <div>
            <span class="employee-page-kicker">LEARNING / MY TRAINING</span>
            <h1>My Training</h1>
            <p>Track programs you joined, upcoming sessions, recommendations, and completed learning.</p>
        </div>

        <div class="employee-heading-stat">
            <span>Active Joined</span>
            <strong><?= count($joinedActive) ?></strong>
            <small><?= count($sessions) ?> upcoming session<?= count($sessions) === 1 ? '' : 's' ?></small>
        </div>
    </header>

    <div class="employee-tabs" role="tablist" aria-label="My Training sections">
        <button type="button" class="employee-tab active" data-training-tab="joined" role="tab" aria-selected="true">Joined <b><?= count($joinedActive) ?></b></button>
        <button type="button" class="employee-tab" data-training-tab="upcoming" role="tab" aria-selected="false">Upcoming Sessions <b><?= count($sessions) ?></b></button>
        <button type="button" class="employee-tab" data-training-tab="recommended" role="tab" aria-selected="false">Recommended <b><?= count($recommended) ?></b></button>
        <button type="button" class="employee-tab" data-training-tab="completed" role="tab" aria-selected="false">Completed <b><?= count($joinedCompleted) ?></b></button>
    </div>

    <section class="employee-tab-panel" data-training-panel="joined">
        <?php if ($joinedActive): ?>
            <div class="employee-my-training-grid">
                <?php foreach ($joinedActive as $training): ?>
                    <article class="employee-my-training-card">
                        <div class="employee-my-training-card-head">
                            <span class="employee-my-training-icon"><i data-lucide="graduation-cap"></i></span>
                            <span class="employee-status-pill is-joined">Joined</span>
                        </div>

                        <h2><?= e((string)$training['title']) ?></h2>
                        <p><?= e((string)$training['purpose']) ?></p>

                        <div class="employee-training-fact-list">
                            <span><i data-lucide="calendar-range"></i><?= e(date('M j', strtotime((string)$training['range_start']))) ?> – <?= e(date('M j, Y', strtotime((string)$training['range_end']))) ?></span>
                            <span><i data-lucide="user-round"></i><?= e((string)$training['instructors']) ?></span>
                            <span><i data-lucide="map-pin"></i><?= e((string)$training['venue']) ?></span>
                        </div>

                        <button type="button" class="employee-button is-secondary full" data-ui-only="details">View Program Details</button>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="employee-panel employee-empty-page compact-page">
                <span class="employee-empty-page-icon"><i data-lucide="book-open"></i></span>
                <h2>No active joined training</h2>
                <p>Programs you join will appear here while they are active.</p>
            </div>
        <?php endif; ?>
    </section>

    <section class="employee-tab-panel" data-training-panel="upcoming" hidden>
        <?php if ($sessions): ?>
            <div class="employee-session-timeline">
                <?php foreach ($sessions as $index => $session): ?>
                    <article class="employee-session-timeline-item">
                        <div class="employee-session-timeline-marker"><span></span></div>

                        <div class="employee-session-timeline-date">
                            <strong><?= e(date('D', strtotime((string)$session['session_date']))) ?></strong>
                            <span><?= e(date('M j, Y', strtotime((string)$session['session_date']))) ?></span>
                        </div>

                        <div class="employee-session-timeline-content">
                            <span class="employee-status-pill is-joined">Meeting <?= (int)$session['session_number'] ?></span>
                            <h2><?= e((string)$session['title']) ?></h2>
                            <div class="employee-training-fact-list horizontal-list">
                                <span><i data-lucide="clock-3"></i><?= e(date('g:i A', strtotime((string)$session['start_time']))) ?>–<?= e(date('g:i A', strtotime((string)$session['end_time']))) ?></span>
                                <span><i data-lucide="map-pin"></i><?= e((string)$session['venue']) ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="employee-panel employee-empty-page compact-page">
                <span class="employee-empty-page-icon"><i data-lucide="calendar-x"></i></span>
                <h2>No upcoming sessions</h2>
                <p>Upcoming sessions from programs you joined will appear here.</p>
            </div>
        <?php endif; ?>
    </section>

    <section class="employee-tab-panel" data-training-panel="recommended" hidden>
        <?php if ($recommended): ?>
            <div class="employee-my-training-grid">
                <?php foreach ($recommended as $training): ?>
                    <?php $isPriority = (int)($training['priority'] ?? 0) === 1; ?>
                    <article class="employee-my-training-card <?= $isPriority ? 'is-priority' : '' ?>">
                        <div class="employee-my-training-card-head">
                            <span class="employee-my-training-icon <?= $isPriority ? 'is-amber' : '' ?>"><i data-lucide="send"></i></span>
                            <span class="employee-status-pill <?= $isPriority ? 'is-priority' : 'is-recommended' ?>"><?= $isPriority ? 'Priority to Take' : 'Recommended' ?></span>
                        </div>

                        <h2><?= e((string)$training['title']) ?></h2>
                        <p><?= e((string)$training['purpose']) ?></p>

                        <div class="employee-training-fact-list">
                            <span><i data-lucide="calendar-range"></i><?= e(date('M j', strtotime((string)$training['range_start']))) ?> – <?= e(date('M j, Y', strtotime((string)$training['range_end']))) ?></span>
                            <span><i data-lucide="user-round"></i><?= e((string)$training['instructors']) ?></span>
                            <span><i data-lucide="map-pin"></i><?= e((string)$training['venue']) ?></span>
                        </div>

                        <div class="employee-card-actions">
                            <button type="button" class="employee-button is-secondary" data-ui-only="details">View Details</button>
                            <button type="button" class="employee-button is-primary" data-ui-only="join">Join Training <i data-lucide="arrow-right"></i></button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="employee-panel employee-empty-page compact-page">
                <span class="employee-empty-page-icon"><i data-lucide="inbox"></i></span>
                <h2>No recommendations</h2>
                <p>HR recommendations will appear here when a training is suggested for you.</p>
            </div>
        <?php endif; ?>
    </section>

    <section class="employee-tab-panel" data-training-panel="completed" hidden>
        <?php if ($joinedCompleted): ?>
            <div class="employee-completed-list">
                <?php foreach ($joinedCompleted as $training): ?>
                    <article class="employee-completed-row">
                        <span class="employee-completed-icon"><i data-lucide="badge-check"></i></span>
                        <div>
                            <strong><?= e((string)$training['title']) ?></strong>
                            <span><?= e((string)$training['instructors']) ?> · Ended <?= e(date('M j, Y', strtotime((string)$training['range_end']))) ?></span>
                        </div>
                        <span class="employee-status-pill is-completed">Completed</span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="employee-panel employee-empty-page compact-page">
                <span class="employee-empty-page-icon"><i data-lucide="award"></i></span>
                <h2>No completed training yet</h2>
                <p>Finished joined programs will move here after their training period ends.</p>
            </div>
        <?php endif; ?>
    </section>
</main>
