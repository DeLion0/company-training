<?php

if (!isset($employeePortalData) || !is_array($employeePortalData)) {
    throw new RuntimeException('Employee portal data was not loaded.');
}

$employeeModule = isset($employeeModule) && is_string($employeeModule)
    ? $employeeModule
    : 'home';

$employeePortalPreview =
    isset($employeePortalPreview)
    && $employeePortalPreview === true;

$employee = $employeePortalData['employee'] ?? null;

$moduleLabels = [
    'home' => 'Home',
    'priority' => 'Priority to Take',
    'available' => 'Available Training',
    'my-training' => 'My Training',
    'performance' => 'My Performance',
    'growth' => 'My Growth',
    'profile' => 'My Profile',
    'security' => 'Account & Security',
];

$moduleLabel =
    $moduleLabels[$employeeModule]
    ?? 'Home';

$employeePriorityNotifications = (array)($employeePortalData['priority_trainings'] ?? []);
$employeeUpcomingNotifications = (array)($employeePortalData['upcoming_sessions'] ?? []);
$employeeMandatoryNotifications = array_values(array_filter(
    (array)($employeePortalData['joined_active'] ?? []),
    static fn(array $training): bool =>
        (string)($training['recommendation_kind'] ?? '') === 'mandatory_low_performance'
));
$employeeNotificationCount =
    count($employeePriorityNotifications)
    + count($employeeMandatoryNotifications)
    + (!empty($employeeUpcomingNotifications) ? 1 : 0);
?>

<header class="employee-topbar">
    <div class="employee-topbar-left">
        <button type="button" class="employee-menu-button" id="employeeMenuButton" aria-label="Open employee navigation">
            <i data-lucide="panel-left"></i>
        </button>

        <span class="employee-breadcrumb">
            Employee Workspace
            <i data-lucide="chevron-right"></i>
            <strong><?= e($moduleLabel) ?></strong>
        </span>
    </div>

    <div class="employee-topbar-right">
        <?php if ($employeePortalPreview): ?>
            <div class="employee-preview-switcher">
                <span>Preview as</span>

                <select id="employeePreviewSelect" aria-label="Preview employee workspace as">
                    <?php foreach ($employeePortalData['employees'] as $previewEmployee): ?>
                        <option
                            value="<?= (int)$previewEmployee['id'] ?>"
                            <?= ($employee && (int)$previewEmployee['id'] === (int)$employee['id']) ? 'selected' : '' ?>
                        >
                            <?= e((string)$previewEmployee['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <span class="employee-topbar-date" id="employeeToday"></span>

        <div class="employee-notification-wrap">
            <button type="button" class="employee-notification-button" aria-label="Notifications" id="employeeNotificationButton" aria-expanded="false" aria-controls="employeeNotificationPanel">
                <i data-lucide="bell"></i>
                <?php if ($employeeNotificationCount > 0): ?><span></span><?php endif; ?>
            </button>

            <div class="employee-notification-panel" id="employeeNotificationPanel" hidden>
                <div class="employee-notification-head">
                    <div>
                        <strong>Notifications</strong>
                        <span><?= $employeeNotificationCount > 0 ? $employeeNotificationCount . ' item' . ($employeeNotificationCount === 1 ? '' : 's') . ' need attention' : 'You are all caught up' ?></span>
                    </div>
                    <i data-lucide="bell-ring"></i>
                </div>

                <div class="employee-notification-list">
                    <?php foreach (array_slice($employeeMandatoryNotifications, 0, 2) as $training): ?>
                        <a href="<?= e(employee_portal_link('my-training', (int)($employee['id'] ?? 0), $employeePortalPreview)) ?>">
                            <i data-lucide="shield-alert"></i>
                            <span><strong>Mandatory Training</strong><small><?= e((string)$training['title']) ?></small></span>
                        </a>
                    <?php endforeach; ?>

                    <?php foreach (array_slice($employeePriorityNotifications, 0, 3) as $training): ?>
                        <a href="<?= e(employee_portal_link('priority', (int)($employee['id'] ?? 0), $employeePortalPreview)) ?>">
                            <i data-lucide="badge-alert"></i>
                            <span><strong>Priority to Take</strong><small><?= e((string)$training['title']) ?></small></span>
                        </a>
                    <?php endforeach; ?>

                    <?php if (!empty($employeeUpcomingNotifications)): $nextNotice = $employeeUpcomingNotifications[0]; ?>
                        <a href="<?= e(employee_portal_link('my-training', (int)($employee['id'] ?? 0), $employeePortalPreview)) ?>">
                            <i data-lucide="calendar-clock"></i>
                            <span>
                                <strong>Upcoming Session</strong>
                                <small><?= e((string)$nextNotice['title']) ?> · <?= e(date('M j, g:i A', strtotime((string)$nextNotice['session_date'] . ' ' . (string)$nextNotice['start_time']))) ?></small>
                            </span>
                        </a>
                    <?php endif; ?>

                    <?php if ($employeeNotificationCount === 0): ?>
                        <div class="employee-notification-empty">
                            <i data-lucide="circle-check-big"></i>
                            <span><strong>No pending alerts</strong><small>Priority training and upcoming sessions will appear here.</small></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($employee): ?>
            <div class="employee-header-avatar" title="<?= e((string)$employee['full_name']) ?>">
                <?= e(strtoupper(substr((string)$employee['full_name'], 0, 1))) ?>
            </div>
        <?php endif; ?>
    </div>
</header>
