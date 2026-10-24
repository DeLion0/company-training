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
$employeeId = (int)($employee['id'] ?? 0);
$currentModule = $employeeModule;

function employee_portal_link(
    string $module,
    int $employeeId,
    bool $preview
): string {
    if ($preview) {
        return 'employee-ui-preview.php?module='
            . rawurlencode($module)
            . '&employee_id='
            . $employeeId;
    }

    return 'employee.php?module='
        . rawurlencode($module);
}
?>

<aside class="employee-sidebar" id="employeeSidebar" aria-label="Employee navigation">
<div class="employee-brand">
    <img
        class="employee-brand-client-logo"
        src="assets/branding/growflow-client-logo.jpg"
        alt="Grow/Flow"
    >

    <div class="employee-brand-name">
        Grow/<span>Flow</span>
        <small>LEARNING MANAGEMENT</small>
    </div>
</div>

    <div class="employee-workspace">
        <div class="employee-workspace-icon"><i data-lucide="briefcase-business"></i></div>
        <div>
            <strong>Employee Workspace</strong>
            <small>Learning &amp; Growth</small>
        </div>
    </div>

    <nav class="employee-navigation" aria-label="Employee modules">
        <p class="employee-nav-label">MY WORKSPACE</p>

        <a
            href="<?= e(employee_portal_link('home', $employeeId, $employeePortalPreview)) ?>"
            class="employee-nav-link <?= $currentModule === 'home' ? 'active' : '' ?>"
            <?= $currentModule === 'home' ? 'aria-current="page"' : '' ?>
        >
            <i data-lucide="house"></i>
            <span>Home</span>
        </a>

        <p class="employee-nav-label second">LEARNING</p>

        <a
            href="<?= e(employee_portal_link('priority', $employeeId, $employeePortalPreview)) ?>"
            class="employee-nav-link <?= $currentModule === 'priority' ? 'active' : '' ?>"
            <?= $currentModule === 'priority' ? 'aria-current="page"' : '' ?>
        >
            <i data-lucide="badge-alert"></i>
            <span>Priority to Take</span>

            <?php if ((int)($employeePortalData['stats']['priority'] ?? 0) > 0): ?>
                <b class="employee-nav-badge"><?= (int)$employeePortalData['stats']['priority'] ?></b>
            <?php endif; ?>
        </a>

        <a
            href="<?= e(employee_portal_link('available', $employeeId, $employeePortalPreview)) ?>"
            class="employee-nav-link <?= $currentModule === 'available' ? 'active' : '' ?>"
            <?= $currentModule === 'available' ? 'aria-current="page"' : '' ?>
        >
            <i data-lucide="calendar-plus"></i>
            <span>Available Training</span>
        </a>

        <a
            href="<?= e(employee_portal_link('my-training', $employeeId, $employeePortalPreview)) ?>"
            class="employee-nav-link <?= $currentModule === 'my-training' ? 'active' : '' ?>"
            <?= $currentModule === 'my-training' ? 'aria-current="page"' : '' ?>
        >
            <i data-lucide="book-open-check"></i>
            <span>My Training</span>
        </a>

        <p class="employee-nav-label second">DEVELOPMENT</p>

        <a
            href="<?= e(employee_portal_link('growth', $employeeId, $employeePortalPreview)) ?>"
            class="employee-nav-link <?= $currentModule === 'growth' ? 'active' : '' ?>"
            <?= $currentModule === 'growth' ? 'aria-current="page"' : '' ?>
        >
            <i data-lucide="sprout"></i>
            <span>My Growth</span>
        </a>
        <a
            href="<?= e(employee_portal_link('performance', $employeeId, $employeePortalPreview)) ?>"
            class="employee-nav-link <?= $currentModule === 'performance' ? 'active' : '' ?>"
            <?= $currentModule === 'performance' ? 'aria-current="page"' : '' ?>
        >
            <i data-lucide="chart-no-axes-column-increasing"></i>
            <span>My Performance</span>
        </a>

        <?php if ($employeePortalPreview): ?>
            <a
                href="#"
                class="employee-nav-link is-disabled"
                aria-disabled="true"
            >
                <i data-lucide="award"></i>
                <span>My Certificates</span>
            </a>
        <?php else: ?>
            <a
                href="employee-certificates.php"
                class="employee-nav-link <?= $currentModule === 'certificates' ? 'active' : '' ?>"
                <?= $currentModule === 'certificates' ? 'aria-current="page"' : '' ?>
            >
                <i data-lucide="award"></i>
                <span>My Certificates</span>
            </a>
        <?php endif; ?>

        <p class="employee-nav-label second">ACCOUNT</p>

        <a
            href="<?= e(employee_portal_link('profile', $employeeId, $employeePortalPreview)) ?>"
            class="employee-nav-link <?= $currentModule === 'profile' ? 'active' : '' ?>"
            <?= $currentModule === 'profile' ? 'aria-current="page"' : '' ?>
        >
            <i data-lucide="circle-user-round"></i>
            <span>My Profile</span>
        </a>

        <a
            href="<?= e(employee_portal_link('security', $employeeId, $employeePortalPreview)) ?>"
            class="employee-nav-link <?= $currentModule === 'security' ? 'active' : '' ?>"
            <?= $currentModule === 'security' ? 'aria-current="page"' : '' ?>
        >
            <i data-lucide="shield-check"></i>
            <span>Account &amp; Security</span>
        </a>
    </nav>

    <div class="employee-sidebar-bottom">
        <?php if ($employee): ?>
            <div class="employee-user-card">
                <div class="employee-user-avatar"><?= e(strtoupper(substr((string)$employee['full_name'], 0, 1))) ?></div>
                <div>
                    <strong><?= e((string)$employee['full_name']) ?></strong>
                    <small><?= e((string)$employee['employee_code']) ?></small>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($employeePortalPreview): ?>
            <a href="index.php?page=dashboard" class="employee-exit-preview">
                <i data-lucide="arrow-left"></i>
                Return to HR
            </a>
        <?php else: ?>
            <form method="post" action="index.php?page=logout" class="employee-logout-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="employee-exit-preview employee-exit-button">
                    <i data-lucide="log-out"></i>
                    Sign out
                </button>
            </form>
        <?php endif; ?>
    </div>
</aside>
