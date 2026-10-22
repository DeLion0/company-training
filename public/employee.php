<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/employee-preview-data.php';
require_once __DIR__ . '/../app/core/employee-actions.php';

secure_session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');

$user = authenticated_user();

if (!$user) {
    redirect_to('index.php?page=login');
}

if (($user['role'] ?? '') !== 'employee') {
    http_response_code(403);
    exit('Access denied. Employee account required.');
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['employee_action'])
) {
    $returnModule = (string)($_POST['return_module'] ?? 'home');
    $validReturnModules = [
        'home', 'priority', 'available', 'my-training',
        'performance', 'profile', 'security',
    ];

    if (!in_array($returnModule, $validReturnModules, true)) {
        $returnModule = 'home';
    }

    try {
        if (!csrf_valid($_POST['csrf'] ?? null)) {
            throw new RuntimeException(
                'Your session token expired. Refresh the page and try again.'
            );
        }

        switch ((string)$_POST['employee_action']) {
            case 'join_training':
                $_SESSION['employee_message'] = employee_join_training(
                    (int)$user['id'],
                    (int)($_POST['program_id'] ?? 0)
                );
                break;

            case 'change_password':
                employee_change_password(
                    (int)$user['id'],
                    (string)($_POST['current_password'] ?? ''),
                    (string)($_POST['new_password'] ?? ''),
                    (string)($_POST['confirm_password'] ?? '')
                );
                $_SESSION['employee_message'] =
                    'Your password was changed successfully.';
                $returnModule = 'security';
                break;

            default:
                throw new InvalidArgumentException(
                    'Unsupported employee action.'
                );
        }
    } catch (Throwable $ex) {
        $_SESSION['employee_error'] = $ex->getMessage();
    }

    redirect_to('employee.php?module=' . rawurlencode($returnModule));
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

try {
    $employeePortalData = employee_portal_data_for_user(
        (int)$user['id']
    );
} catch (Throwable $ex) {
    error_log(
        'Employee workspace failed: '
        . $ex->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load Employee Workspace. Check the PHP error log.'
    );
}

$employeePortalPreview = false;

$employeeFlash = $_SESSION['employee_message'] ?? null;
$employeeFlashError = $_SESSION['employee_error'] ?? null;
unset($_SESSION['employee_message'], $_SESSION['employee_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6faf7">
    <title>Employee Workspace | Grow/Flow</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/employee-portal.css?v=20261015-1">
    <link rel="stylesheet" href="assets/css/employee-dashboard.css?v=20261022-mandatory1">
    <link rel="stylesheet" href="assets/css/employee-functions-v1.css?v=20261022-1">

    <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script defer src="assets/js/employee-portal.js?v=20261022-functions1"></script>
</head>

<body class="employee-portal-page" data-employee-preview="0">
    <div class="employee-mobile-overlay" id="employeeOverlay" hidden></div>

    <div class="employee-app-layout">
        <?php require __DIR__ . '/../app/partials/employee-portal-sidebar.php'; ?>

        <div class="employee-main-shell">
            <?php require __DIR__ . '/../app/partials/employee-portal-header.php'; ?>

            <?php if ($employeeFlash): ?>
                <div class="employee-workspace-flash success">
                    <i data-lucide="circle-check-big"></i>
                    <?= e((string)$employeeFlash) ?>
                </div>
            <?php endif; ?>

            <?php if ($employeeFlashError): ?>
                <div class="employee-workspace-flash error">
                    <i data-lucide="triangle-alert"></i>
                    <?= e((string)$employeeFlashError) ?>
                </div>
            <?php endif; ?>

            <?php require __DIR__ . '/../app/pages/' . $allowedModules[$employeeModule]; ?>
        </div>
    </div>

    <?php require __DIR__ . '/../app/partials/employee-training-dialog.php'; ?>

    <div class="employee-toast" id="employeeToast" role="status" aria-live="polite"></div>
</body>
</html>
