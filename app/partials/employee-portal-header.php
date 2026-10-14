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
    'profile' => 'My Profile',
    'security' => 'Account & Security',
];

$moduleLabel =
    $moduleLabels[$employeeModule]
    ?? 'Home';
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

        <button type="button" class="employee-notification-button" aria-label="Notifications" id="employeeNotificationButton">
            <i data-lucide="bell"></i>
            <?php if ((int)($employeePortalData['stats']['priority'] ?? 0) > 0): ?><span></span><?php endif; ?>
        </button>

        <?php if ($employee): ?>
            <div class="employee-header-avatar" title="<?= e((string)$employee['full_name']) ?>">
                <?= e(strtoupper(substr((string)$employee['full_name'], 0, 1))) ?>
            </div>
        <?php endif; ?>
    </div>
</header>
