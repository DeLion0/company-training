<?php

if (!isset($employeePortalData) || !is_array($employeePortalData)) {
    throw new RuntimeException('Employee portal data was not loaded.');
}

$employee = $employeePortalData['employee'];
$available = $employeePortalData['available_trainings'];
?>

<main class="employee-content employee-module-page">
    <header class="employee-page-heading employee-page-heading-with-tools">
        <div>
            <span class="employee-page-kicker">LEARNING / DISCOVER</span>
            <h1>Available Training</h1>
            <p>Published training that is open to your department. Priority and joined status are shown directly on each program.</p>
        </div>

        <div class="employee-training-toolbar">
            <label class="employee-available-search employee-large-search">
                <i data-lucide="search"></i>
                <input type="search" id="employeeTrainingSearch" placeholder="Search title, instructor, venue..." aria-label="Search available training">
            </label>

            <select id="employeeTrainingStatusFilter" aria-label="Filter available training">
                <option value="">All Programs</option>
                <option value="mandatory">Mandatory Training</option>
                <option value="priority">Priority to Take</option>
                <option value="recommended">Recommended</option>
                <option value="joined">Joined</option>
                <option value="open">Open to Join</option>
            </select>
        </div>
    </header>

    <section class="employee-available-summary">
        <div><span>Programs in view</span><strong><?= count($available) ?></strong></div>
        <div><span>Your department</span><strong><?= e((string)$employee['department_name']) ?></strong></div>
        <div><span>Priority</span><strong><?= (int)$employeePortalData['stats']['priority'] ?></strong></div>
    </section>

    <?php if ($available): ?>
        <section class="employee-available-grid" id="employeeAvailableGrid">
            <?php foreach ($available as $training): ?>
                <?php
                $openSlots = max(0, (int)$training['capacity'] - (int)$training['joined_count']);
                $status = (string)($training['application_status'] ?? '');
                $isMandatory = (string)($training['recommendation_kind'] ?? '') === 'mandatory_low_performance' && $status === 'joined';
                $isPriority = (int)($training['priority'] ?? 0) === 1 && $status === 'recommended';
                $filterStatus = $isMandatory
                    ? 'mandatory'
                    : ($isPriority
                        ? 'priority'
                        : ($status === 'recommended'
                            ? 'recommended'
                            : ($status === 'joined'
                                ? 'joined'
                                : 'open')));
                $searchText = strtolower((string)$training['title'] . ' ' . (string)$training['instructors'] . ' ' . (string)$training['venue']);
                ?>

                <article
                    class="employee-available-card <?= $isPriority ? 'is-priority' : '' ?>"
                    data-search="<?= e($searchText) ?>"
                    data-status="<?= e($filterStatus) ?>"
                >
                    <div class="employee-available-card-top">
                        <div class="employee-available-icon"><i data-lucide="book-open-check"></i></div>

                        <div class="employee-available-badges">
                            <?php if ($isMandatory): ?>
                                <span class="employee-status-pill is-mandatory">Mandatory Training</span>
                            <?php elseif ($isPriority): ?>
                                <span class="employee-status-pill is-priority">Priority to Take</span>
                            <?php elseif ($status === 'recommended'): ?>
                                <span class="employee-status-pill is-recommended">Recommended</span>
                            <?php elseif ($status === 'joined'): ?>
                                <span class="employee-status-pill is-joined">Joined</span>
                            <?php else: ?>
                                <span class="employee-status-pill is-open">Available</span>
                            <?php endif; ?>

                            <span class="employee-slot-badge"><?= $openSlots ?> slot<?= $openSlots === 1 ? '' : 's' ?></span>
                        </div>
                    </div>

                    <h2><?= e((string)$training['title']) ?></h2>
                    <p><?= e((string)$training['purpose']) ?></p>

                    <div class="employee-available-facts">
                        <span><i data-lucide="calendar-range"></i><?= e(date('M j', strtotime((string)$training['range_start']))) ?> – <?= e(date('M j, Y', strtotime((string)$training['range_end']))) ?></span>
                        <span><i data-lucide="user-round"></i><?= e((string)$training['instructors']) ?></span>
                        <span><i data-lucide="map-pin"></i><?= e((string)$training['venue']) ?></span>
                    </div>

                    <div class="employee-seat-progress">
                        <div>
                            <span>Seats joined</span>
                            <strong><?= (int)$training['joined_count'] ?> / <?= (int)$training['capacity'] ?></strong>
                        </div>
                        <div class="employee-seat-track"><span style="width:<?= min(100, round(100 * (int)$training['joined_count'] / max(1, (int)$training['capacity']), 2)) ?>%"></span></div>
                    </div>

                    <div class="employee-card-actions">
                        <button type="button" class="employee-button is-secondary" data-training-details="<?= (int)$training['id'] ?>">View Details</button>
                        <?php if ($isMandatory): ?>
                            <button type="button" class="employee-button is-mandatory-static" disabled><i data-lucide="shield-check"></i> Mandatory · Enrolled</button>
                        <?php elseif ($status === 'joined'): ?>
                            <button type="button" class="employee-button is-joined-static" disabled><i data-lucide="check"></i> Joined</button>
                        <?php else: ?>
                            <button type="button" class="employee-button is-primary" data-join-training="<?= (int)$training['id'] ?>" <?= $openSlots < 1 ? 'disabled' : '' ?>>Join Training <i data-lucide="plus"></i></button>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <div class="employee-empty employee-filter-empty" id="employeeTrainingNoMatch" hidden>
            <i data-lucide="search-x"></i>
            <strong>No matching training</strong>
            <span>Try a different search or filter.</span>
        </div>
    <?php else: ?>
        <section class="employee-panel employee-empty-page">
            <span class="employee-empty-page-icon"><i data-lucide="calendar-search"></i></span>
            <h2>No available training right now</h2>
            <p>New published training for <?= e((string)$employee['department_name']) ?> will appear here when HR makes it available.</p>
        </section>
    <?php endif; ?>
</main>
