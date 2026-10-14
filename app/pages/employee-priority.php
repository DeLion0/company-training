<?php

if (!isset($employeePortalData) || !is_array($employeePortalData)) {
    throw new RuntimeException('Employee portal data was not loaded.');
}

$employee = $employeePortalData['employee'];
$priority = $employeePortalData['priority_trainings'];
$latest = $employeePortalData['latest'];
$latestScore = $latest ? (float)$latest['score'] : null;
?>

<main class="employee-content employee-module-page">
    <header class="employee-page-heading">
        <div>
            <span class="employee-page-kicker">LEARNING / PRIORITY</span>
            <h1>Priority to Take</h1>
            <p>Training that needs your attention based on the latest official performance data and the departments targeted by HR.</p>
        </div>

        <div class="employee-heading-stat is-amber">
            <span>Current Priority</span>
            <strong><?= count($priority) ?></strong>
            <small><?= $latestScore === null ? 'No rating yet' : 'Latest performance ' . number_format($latestScore, 1) . '%' ?></small>
        </div>
    </header>

    <section class="employee-priority-explainer">
        <div class="employee-priority-explainer-icon"><i data-lucide="info"></i></div>
        <div>
            <strong>What does “Priority to Take” mean?</strong>
            <p>This does not mean you are already enrolled. It highlights a program that HR targeted to your department and that the system marked as important based on your latest official monthly rating. You still choose whether to join.</p>
        </div>
    </section>

    <?php if ($priority): ?>
        <section class="employee-priority-page-grid">
            <?php foreach ($priority as $training): ?>
                <?php $openSlots = max(0, (int)$training['capacity'] - (int)$training['joined_count']); ?>
                <article class="employee-priority-large-card">
                    <div class="employee-priority-large-top">
                        <span class="employee-priority-badge"><i data-lucide="sparkles"></i> Priority to Take</span>
                        <span class="employee-priority-score">Based on <?= number_format((float)$training['performance_score'], 1) ?>%</span>
                    </div>

                    <h2><?= e((string)$training['title']) ?></h2>
                    <p class="employee-priority-purpose"><?= e((string)$training['purpose']) ?></p>

                    <div class="employee-training-fact-grid">
                        <div><i data-lucide="calendar-range"></i><span><small>Schedule</small><strong><?= e(date('M j', strtotime((string)$training['range_start']))) ?> – <?= e(date('M j, Y', strtotime((string)$training['range_end']))) ?></strong></span></div>
                        <div><i data-lucide="user-round"></i><span><small>Instructor</small><strong><?= e((string)$training['instructors']) ?></strong></span></div>
                        <div><i data-lucide="map-pin"></i><span><small>Venue</small><strong><?= e((string)$training['venue']) ?></strong></span></div>
                        <div><i data-lucide="armchair"></i><span><small>Open Slots</small><strong><?= $openSlots ?> of <?= (int)$training['capacity'] ?></strong></span></div>
                    </div>

                    <div class="employee-priority-reason">
                        <i data-lucide="target"></i>
                        <div>
                            <small>Why this is priority</small>
                            <strong>Your latest official score is below the current 50% priority threshold for a training targeted to <?= e((string)$employee['department_name']) ?>.</strong>
                        </div>
                    </div>

                    <div class="employee-card-actions">
                        <button type="button" class="employee-button is-secondary" data-ui-only="details">View Details</button>
                        <button type="button" class="employee-button is-primary" data-ui-only="join" <?= $openSlots < 1 ? 'disabled' : '' ?>>Join Training <i data-lucide="arrow-right"></i></button>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <section class="employee-panel employee-empty-page">
            <span class="employee-empty-page-icon is-green"><i data-lucide="circle-check-big"></i></span>
            <h2>No priority training right now</h2>
            <p>You currently have no active training marked as Priority to Take. You can still explore other published programs available to your department.</p>
            <a class="employee-button is-primary" href="<?= e(employee_preview_link('available', (int)$employee['id'])) ?>">Browse Available Training <i data-lucide="arrow-right"></i></a>
        </section>
    <?php endif; ?>
</main>
