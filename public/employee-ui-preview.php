<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/employee-preview-data.php';

secure_session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$viewer = authenticated_user();

if (!$viewer) {
    redirect_to('index.php?page=login');
}

if (($viewer['role'] ?? '') !== 'hr') {
    http_response_code(403);
    exit('Employee UI preview is available to HR only.');
}

$allowedModules = [
    'home' => 'employee-home.php',
    'priority' => 'employee-priority.php',
    'available' => 'employee-available-training.php',
    'my-training' => 'employee-my-training.php',
    'performance' => 'employee-performance.php',
    'profile' => 'employee-profile.php',
    'security' => 'employee-security.php',
];

$employeeModule = (string)($_GET['module'] ?? 'home');

if (!isset($allowedModules[$employeeModule])) {
    $employeeModule = 'home';
}

$requestedEmployeeId = isset($_GET['employee_id'])
    ? (int)$_GET['employee_id']
    : null;

try {
    $employeePortalData = employee_preview_data(
        (int)$viewer['id'],
        $requestedEmployeeId
    );
} catch (Throwable $ex) {
    error_log('Employee UI preview failed: ' . $ex->getMessage());
    http_response_code(500);
    exit('Unable to load employee UI preview. Check the PHP error log.');
}

$employeePortalPreview = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6faf7">
    <title>Employee Workspace Preview | Grow/Flow</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/employee-portal.css?v=20261015-1">
    <link rel="stylesheet" href="assets/css/employee-dashboard.css?v=20261015-1">

    <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script defer src="assets/js/employee-portal.js?v=20261015-1"></script>
</head>

<body class="employee-portal-page">
<?php if (empty($employeePortalData['employee'])): ?>
    <div class="employee-preview-empty">
        <i data-lucide="users"></i>
        <h1>No active employees to preview</h1>
        <p>Create an employee first, then return to the Employee Workspace UI preview.</p>
        <a href="index.php?page=employees">Return to Employees</a>
    </div>
<?php else: ?>
    <div class="employee-mobile-overlay" id="employeeOverlay" hidden></div>

    <div class="employee-app-layout">
        <?php require __DIR__ . '/../app/partials/employee-portal-sidebar.php'; ?>

        <div class="employee-main-shell">
            <?php require __DIR__ . '/../app/partials/employee-portal-header.php'; ?>

            <div class="employee-preview-banner">
                <i data-lucide="eye"></i>
                <span>
                    <strong>Employee UI Preview</strong>
                    HR preview only. The real employee account uses employee.php after activation.
                </span>
            </div>

            <?php require __DIR__ . '/../app/pages/' . $allowedModules[$employeeModule]; ?>
        </div>
    </div>

    <div class="employee-toast" id="employeeToast" role="status" aria-live="polite"></div>
<?php endif; ?>
</body>
</html>
